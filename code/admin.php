<?php

class ImfsPage extends Imfs_AdminPageFramework {

	public $pluginName;
	public $pluginSlug;
	public $domain;
	/**
	 * @var bool true if the dbms allows reindexing at all.
	 */
	public $canReindex = false;
	private $db;
	/**
	 * @var bool true if reindexing does not have the 191 prefix index limitation.
	 */
	public $unconstrained = false;
	/**
	 * @var false|mixed
	 */
	private $dontNavigate;

	public function __construct( $slug = index_wp_mysql_for_speed_domain ) {
		parent::__construct();
		$this->domain       = $slug;
		$this->pluginName   = __( 'Index WP MySQL For Speed', $this->domain );
		$this->pluginSlug   = $slug;
		$this->db           = new ImfsDb();
		$this->dontNavigate = __( 'This may take a few minutes. <em>Please do not navigate away from this page while you wait</em>.', $this->domain );
	}

	// https://admin-page-framework.michaeluno.jp/tutorials/01-create-a-wordpress-admin-page/

	public function setUp() {
		//$this->setRootMenuPage( 'Settings' );
		$this->setRootMenuPage( 'Tools' );

		$pageName = $this->pluginName;
		/* translators: settings page menu text */
		$menuName = __( 'Index MySQL', $this->domain );
		$this->addSubMenuItems(
			array(
				'title'      => $pageName,
				'menu_title' => $menuName,
				'page_slug'  => 'imfs_settings',
				'order'      => 31,
				'capability' => 'activate_plugins'

			)
		);
	}

	/** @noinspection PhpUnused */
	public function content_ImfsPage( $sHTML ) {
		/** @noinspection HtmlUnknownTarget */
		$hyperlink          = '<a href="%s" target="_blank">%s</a>';
		$supportUrl         = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/";
		$reviewUrl          = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/reviews/";
		$detailsUrl         = "https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/#what-specific-key-changes-do-we-make-even-wonkier";
		$wpCliUrl           = '<a href="https://make.wordpress.org/cli/handbook/">WP-CLI</a>';
		$clickHere          = __( 'click here', $this->domain );
		$support            = sprintf( $hyperlink, $supportUrl, $clickHere );
		$review             = sprintf( $hyperlink, $reviewUrl, $clickHere );
		$details            = sprintf( $hyperlink, $detailsUrl, $clickHere );
		$supportString      = '<p class="topinfo">' . __( 'For support please %s. If you create a topic in the support forum, please upload your diagnostic metadata, and mention the id of your upload.  Please %s to rate this plugin.', $this->domain ) . '</p>';
		$supportString      = sprintf( $supportString, $support, $review );
		$detailsString      = '<p class="topinfo">' . __( 'For detailed information about this plugin\'s actions on your database, please %s.', $this->domain ) . '</p>';
		$detailsString      = sprintf( $detailsString, $details );
		$wpCliString        = '<p class="topinfo">' . __( 'This plugin supports %s. You may run its operations that way if your hosting machine is set up for it. If your tables are large, WP-CLI may be a good choice to avoid timeouts.', $this->domain ) . '</p>';
		$wpCliString        = sprintf( $wpCliString, $wpCliUrl );

		return $supportString . $detailsString . $wpCliString . $sHTML;
	}

	/** Render the plugin's admin page.
	 *
	 * @param $oAdminPage
	 *
	 * @noinspection PhpUnusedParameterInspection PhpUnused
	 */
	public function load_ImfsPage( $oAdminPage ) {
		try {
			$this->populate();
		} catch ( ImfsException $ex ) {
			$msg = __( 'Something went wrong inspecting your database', $this->domain ) . ': ' . $ex->getMessage();
			$this->setSettingNotice( $msg );

			return;
		}
		$this->enqueueStyles(
			array( plugins_url( 'assets/imfs.css', __FILE__ ) ), 'imfs_settings' );

		if ( $this->MySQLVersionInfo() ) {

			$rekeying = $this->db->getRekeying();
			/* errors **********************************/
			$this->resetKeysToWPStandard( $rekeying );

			/* engine upgrade ***************************/
			$this->upgradeIndex();

			/* rekeying ***************************/
			$action = 'enable';
			if ( count( $rekeying[ $action ] ) > 0 ) {
				$title        = __( 'Add keys', $this->domain );
				$caption      = __( 'Add high-performance keys to these tables to make your WordPress database faster.', $this->domain );
				$callToAction = __( 'Add Keys Now', $this->domain );
				$this->renderListOfTables( $rekeying[ $action ], false, $action, $title, $caption, $callToAction, true );
			}
			/* disabling  ***************************/
			$action = 'disable';
			if ( count( $rekeying[ $action ] ) > 0 ) {

				$this->addSettingFields(
					array(
						'field_id' => 'successcaption',
						'title'    => 'Success',
						'default'  => __( 'Your WordPress tables now have high-performance keys.', $this->domain ),
						'save'     => false,
						'class'    => array(
							'fieldrow' => array( 'major', 'success' ),
						),
					) );

				$title        = __( 'Revert', $this->domain );
				$caption      = __( 'Revert the keys on these tables to restore WordPress\'s defaults.', $this->domain );
				$callToAction = __( 'Revert Keys Now', $this->domain );
				$this->renderListOfTables( $rekeying[ $action ], false, $action, $title, $caption, $callToAction, false );
			}

		}
		$this->uploadMetadata();
	}

	/**
	 * @throws ImfsException
	 */
	protected function populate() {

		$this->db->init();
		$this->canReindex    = $this->db->canReindex;
		$this->unconstrained = $this->db->unconstrained;
	}

	private function MySQLVersionInfo() {
		global $wp_version;
		$versionString = 'MySQL:' . htmlspecialchars( $this->db->semver->version ) . ' WordPress:' . $wp_version . ' php:' . phpversion();

		$this->addSettingFields(
			array(
				'field_id' => 'backup',
				'title'    => __( 'Backup', $this->domain ),
				'label'    => __( 'This plugin modifies your WordPress database. Make a backup before you proceed.', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),

				array(
					'field_id' => 'backup_done',
					'type'     => 'checkbox',
					'label'    => __( 'I have made a backup', $this->domain ),
					'default'  => 0,
					'save'     => true,
					'class'    => array(
						'fieldrow' => 'major',
					),
				)
			),
			array(
				'field_id' => 'version',
				'title'    => __( 'Versions', $this->domain ),
				'default'  => $versionString,
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
			)
		);

		if ( ! $this->db->canReindex ) {
			$this->addSettingFields(
				array(
					'field_id'    => 'version_error',
					'title'       => 'Notice',
					'default'     => __( 'Sorry, you cannot use this plugin with your version of MySQL.', $this->domain ),
					'description' => __( 'Your MySQL version is outdated. Please consider upgrading,', $this->domain ),
					'save'        => false,
					'class'       => array(
						'fieldrow' => 'failure',
					),
				) );
		} else {
			if ( ! $this->db->unconstrained ) {
				$this->addSettingFields(
					array(
						'field_id' => 'constraint_notice',
						'title'    => 'Notice',
						'default'  => __( 'Upgrading your MySQL server version will give you better performance when you add high-performance keys. Please consider doing that before you add these keys.', $this->domain ),
						'save'     => false,
						'class'    => array(
							'fieldrow' => 'warning',
						),
					) );

			}
		}

		return $this->db->canReindex;
	}

	/**
	 * @param array $rekeying
	 */
	private function resetKeysToWPStandard( array $rekeying ) {
		if ( count( $rekeying['reset'] ) > 0 ) {
			$action = 'reset';
			$this->addSettingFields(
				array(
					'field_id'    => 'norekeycaption',
					'title'       => 'Problems Rekeying',
					'default'     => __( 'You cannot rekey some tables without resetting their keys first.', $this->domain ),
					'description' => __( 'This often means they have already been rekeyed by some other plugin or workflow.', $this->domain ),
					'save'        => false,
					'class'       => array(
						'fieldrow' => array( 'warning', 'header' ),
					),
				) );
			$title        = '<span class="warning header">' . __( 'Reset Keys', $this->domain ) . '</span>';
			$caption      = __( 'Reset the keys on these tables: remove the keys set by some other plugin or workflow.', $this->domain );
			$callToAction = __( 'Reset Keys Now', $this->domain );

			$this->renderListOfTables( $rekeying[ $action ], true, $action, $title, $caption, $callToAction, false );
		}
	}

	private function renderListOfTables( $tablesToRekey, $prefixed, $action, $title, $caption, $callToAction, $prechecked ) {
		global $wpdb;
		$this->addSettingFields(
			array(
				'field_id' => $action . 'caption',
				'title'    => $title,
				'default'  => $caption,
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'major',
				),
			)
		);

		$labels   = array();
		$defaults = array();
		$prefix   = $prefixed ? '' : $wpdb->prefix;
		foreach ( $tablesToRekey as $tbl ) {
			$unprefixed = ImfsStripPrefix( $tbl );
			$rowcount   = - 1;
			if ( array_key_exists( $tbl, $this->db->stats[1] ) ) {
				$rowcount = $this->db->stats[1][ $tbl ]->count;
			} else if ( array_key_exists( $unprefixed, $this->db->stats[1] ) ) {
				$rowcount = $this->db->stats[1][ $unprefixed ]->count;
			}
			if ( $rowcount > 1 ) {
				$rowcount   = number_format_i18n( $rowcount );
				$itemString = $rowcount . ' ' . __( 'items', $this->domain );
			} else if ( $rowcount == 1 ) {
				$itemString = $rowcount . ' ' . __( 'item', $this->domain );
			} else if ( $rowcount == 0 ) {
				$itemString = __( 'no items', $this->domain );
			} else {
				$itemString = '';
			}
			if ( strlen( $itemString ) > 0 ) {
				$itemString = ' (' . $itemString . ')';
			}
			$labels[ $tbl ]   = $prefix . $tbl . $itemString;
			$defaults[ $tbl ] = $prechecked;
		}

		$this->addSettingFields(
			array(
				'field_id'           => $action,
				'type'               => 'checkbox',
				'label'              => $labels,
				'default'            => $defaults,
				'save'               => false,
				'after_label'        => '<br />',
				'select_all_button'  => true,
				'select_none_button' => true,
			)
		);

		$this->addSettingFields(
			array(
				'field_id'    => $action . '_now',
				'type'        => 'submit',
				'save'        => 'false',
				'value'       => $callToAction,
				'description' => $this->dontNavigate,
				'class'       => array(
					'fieldrow' => 'action',
				),
			),
			array(
				'field_id' => $action . '_wp',
				'label'    => $this->cliMessage( $action . ' --all', __( $title, $this->domain ) ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
			)
		);
	}

	private function cliMessage( $command, $function ) {
		//$cliLink = ' <a href="https://make.wordpress.org/cli/handbook/" target="_blank">WP-CLI</a>';
		$cliLink = ' WP-CLI';
		$wp      = 'wp index-mysql';
		$blogid = get_current_blog_id();
		if ($blogid > 1 ) {
			$wp .= ' ' . '--blogid=' . $blogid;
		}
		/* translators: %1$s is WP-CLI hyperlink, %2s is 'wp index-mysql',  %3$s describes the function, %4$s is the cli commmand */
		$fmt = __( 'Using %1$s, %2$s: <code>%3$s %4$s</code>', $this->domain );

		return sprintf( $fmt, $cliLink, $function, $wp, $command );
	}

	private function upgradeIndex() {
		if ( count( $this->db->oldEngineTables ) > 0 ) {
			$action       = 'upgrade';
			$title        = '<span class="warning header">' . __( 'Upgrade storage engine', $this->domain ) . '</span>';
			$caption      = __( 'These database tables need upgrading to InnoDB, MySQL\'s latest storage engine.', $this->domain );
			$callToAction = __( 'Upgrade Storage Engine Now', $this->domain );
			$this->renderListOfTables( $this->db->oldEngineTables, true, $action, $title, $caption, $callToAction, true );
		}
	}

	/** @noinspection PhpUnused */

	/**
	 * render the upload-metadata page.
	 */
	function uploadMetadata() {
		$this->addSettingFields(
			array(
				'field_id' => 'permission',
				'title'    => __( 'Diagnostic data', $this->domain ),
				'label'    => __( 'With your permission we upload metadata about your WordPress site to our plugin\'s servers. We cannot identify you or your web site from it, and we never sell nor give it to any third party. We use it only to improve this plugin.', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
				array(
					'field_id'    => 'upload_metadata_now',
					'type'        => 'submit',
					'save'        => 'false',
					'value'       => __( 'Upload metadata', $this->domain ),
					'description' => $this->dontNavigate,
					'class'       => array(
						'fieldrow' => 'action',
					),
				),
				array(
					'label' => $this->cliMessage( 'upload_metadata', __( 'Upload metadata', $this->domain ) ),
					'type'  => 'label',
					'save'  => false,
					'class' => array(
						'fieldrow' => 'info',
					),
				)
			)
		);
	}

	/** @noinspection PhpUnused */
	function validation_ImfsPage( $inputs, $oldInputs, $factory, $submitInfo ) {
		$valid  = true;
		$errors = array();

		if ( ! isset ( $inputs['backup']['1'] ) || ! $inputs['backup']['1'] ) {
			$valid            = false;
			$errors['backup'] = __( 'Please acknowledge that you have made a backup', $this->domain );
		}

		$action = $submitInfo['field_id'];
		$err    = __( 'Please select at least one table.', $this->domain );
		if ( $action === 'enable_now' ) {
			if ( count( $this->listFromCheckboxes( $inputs['enable'] ) ) === 0 ) {
				$valid            = false;
				$errors['enable'] = $err;
			}
		}
		if ( $action === 'disable_now' ) {
			if ( count( $this->listFromCheckboxes( $inputs['disable'] ) ) === 0 ) {
				$valid             = false;
				$errors['disable'] = $err;
			}
		}
		if ( $action === 'upgrade_now' ) {
			if ( count( $this->listFromCheckboxes( $inputs['upgrade'] ) ) === 0 ) {
				$valid             = false;
				$errors['upgrade'] = $err;
			}
		}

		if ( ! $valid ) {
			$this->setFieldErrors( $errors );
			$this->setSettingNotice( __( 'Make corrections and try again.', $this->domain ) );

			return $oldInputs;
		}

		return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
	}

	/** @noinspection PhpUnusedParameterInspection */

	private function listFromCheckboxes( $cbs ) {
		$result = array();
		foreach ( $cbs as $name => $val ) {
			if ( $val ) {
				$result[] = $name;
			}
		}

		return $result;
	}

	/** @noinspection PhpUnusedParameterInspection */
	private function action( $button, $inputs, $oldInputs, $factory, $submitInfo ) {
		try {
			switch ( $button ) {
				case 'upload_metadata_now':
					$id = imfs_upload_stats( $this->db );
					$this->setSettingNotice( __( 'Metadata uploaded to id ', $this->domain ) . $id, 'updated' );
					break;
				case 'upgrade_now':
					$msg = $this->db->upgradeStorageEngine( $this->listFromCheckboxes( $inputs['upgrade'] ) );
					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'enable_now':
					$msg = $this->db->rekeyTables( 'enable', $this->listFromCheckboxes( $inputs['enable'] ) );
					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'disable_now':
					$msg = $this->db->rekeyTables( 'disable', $this->listFromCheckboxes( $inputs['disable'] ) );
					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'reset_now':
					$msg = $this->db->repairTables( 'reset', $this->listFromCheckboxes( $inputs['reset'] ) );
					$this->setSettingNotice( $msg, 'reset' );
					break;
			}

			return $inputs;
		} catch ( ImfsException $ex ) {
			$msg = $ex->getMessage();
			$this->setSettingNotice( $msg );

			return $oldInputs;
		}
	}

}

new ImfsPage;