<?php

require_once( 'rendermonitor.php' );

class ImfsPage extends Imfs_AdminPageFramework {

	public $pluginName;
	public $pluginSlug;
	public $domain;
	public $monitors;
	/**
	 * @var bool true if the dbms allows reindexing at all.
	 */
	public $canReindex = false;
	/**
	 * @var bool true if reindexing does not have the 191 prefix index limitation.
	 */
	public $unconstrained = false;
	private $db;
	private $dontNavigate;
	private $valid;

	public function __construct( $slug = index_wp_mysql_for_speed_domain ) {
		parent::__construct();
		$this->domain       = $slug;
		$this->pluginName   = __( 'Index WP MySQL For Speed', $this->domain );
		$this->pluginSlug   = $slug;
		$this->db           = new ImfsDb();
		$this->dontNavigate = __( 'This may take a few minutes. <em>Please do not navigate away from this page while you wait</em>.', $this->domain );
		$this->tabSuffix    = "_m";
	}

	// https://admin-page-framework.michaeluno.jp/tutorials/01-create-a-wordpress-admin-page/

	public function setUp() {
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
		$tabs           = array();
		$tabs[]         = array(
			'tab_slug' => 'rekey',
			'title'    => __( 'High-Performance Keys', $this->domain )
		);
		$tabs[]         = array(
			'tab_slug' => 'monitor',
			'title'    => __( 'Monitor Database Operations', $this->domain )
		);
		$this->monitors = RenderMonitor::getMonitors();
		foreach ( $this->monitors as $monitor ) {
			$tabs[] = array(
				'tab_slug' => $monitor . $this->tabSuffix,
				'title'    => $monitor
			);
		}
		$tabs[] = array(
			'tab_slug' => 'info',
			'title'    => __( 'About', $this->domain )
		);
		$this->addInPageTabs( 'imfs_settings', ...$tabs );
		$this->setPageHeadingTabsVisibility( false );
	}

	/** Enqueue css for all tabs.
	 *
	 * @param string $sHTML
	 *
	 * @return string
	 */
	public function content_imfs_settings( $sHTML ) {
		$this->enqueueStyles(
			array(
				plugins_url( 'assets/imfs.css', __FILE__ )
			), 'imfs_settings' );

		return $sHTML;
	}

	/** If the current tab is a monitor, render the data
	 *
	 * @param string $sHTML
	 *
	 * @return string
	 */
	public function content_bottom_imfs_settings( $sHTML ) {
		return $sHTML . $this->renderMonitor();
	}

	/** @noinspection PhpUnused */

	/**
	 * @param string $sHTML
	 *
	 * @return string
	 */
	private function renderMonitor(): string {
		/* See https://wordpress.org/support/topic/when-naming-inpagetabs-with-variables-how-can-i-use-content_pageslug/#post-14924022 */
		$tab = $this->oProp->getCurrentTabSlug();
		$pos = strrpos( $tab, $this->tabSuffix );
		if ( $pos !== strlen( $tab ) - strlen( $this->tabSuffix ) ) {
			return '';
		}
		$monitor = substr( $tab, 0, $pos );
		if ( array_search( $monitor, $this->monitors ) === false ) {
			return '';
		}
		$this->enqueueStyles(
			array(
				plugins_url( 'assets/datatables/datatables.min.css', __FILE__ )
			), 'imfs_settings' );
		$this->enqueueScripts(
			array(
				plugins_url( 'assets/datatables/datatables.min.js', __FILE__ ),
				plugins_url( 'assets/imfs.js', __FILE__ )
			), 'imfs_settings' );

		return RenderMonitor::renderMonitors( $monitor );
	}

	/** render informational content at the top of the About tab
	 *
	 * @param string $sHTML
	 *
	 * @return string
	 */
	public function content_top_imfs_settings_info( $sHTML ) {
		/** @noinspection HtmlUnknownTarget */
		$hyperlink     = '<a href="%s" target="_blank">%s</a>';
		$supportUrl    = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/";
		$reviewUrl     = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/reviews/";
		$detailsUrl    = "https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/#what-specific-key-changes-do-we-make-even-wonkier";
		$wpCliUrl      = '<a href="https://make.wordpress.org/cli/handbook/">WP-CLI</a>';
		$clickHere     = __( 'click here', $this->domain );
		$support       = sprintf( $hyperlink, $supportUrl, $clickHere );
		$review        = sprintf( $hyperlink, $reviewUrl, $clickHere );
		$details       = sprintf( $hyperlink, $detailsUrl, $clickHere );
		$supportString = '<p class="topinfo">' . __( 'For support please %s. If you create a topic in the support forum, please upload your diagnostic metadata, and mention the id of your upload.  Please %s to rate this plugin.', $this->domain ) . '</p>';
		$supportString = sprintf( $supportString, $support, $review );
		$detailsString = '<p class="topinfo">' . __( 'For detailed information about this plugin\'s actions on your database, please %s.', $this->domain ) . '</p>';
		$detailsString = sprintf( $detailsString, $details );
		$wpCliString   = '<p class="topinfo">' . __( 'This plugin supports %s. You may run its operations that way if your hosting machine is set up for it. If your tables are large, using WP-CLI may be a good choice to avoid timeouts.', $this->domain ) . '</p>';
		$wpCliString   = sprintf( $wpCliString, $wpCliUrl );

		return $sHTML . '<div class="index-wp-mysql-for-speed-content-container">' . $supportString . $detailsString . $wpCliString . '</div>';
	}

	/** Render the form in the rekey tab
	 *
	 * @param object $oAdminPage
	 *
	 * @noinspection PhpUnusedParameterInspection PhpUnused
	 */
	public function load_imfs_settings_rekey( $oAdminPage ) {
		$this->enqueueStyles(
			array(
				plugins_url( 'assets/imfs.css', __FILE__ ),
			), 'imfs_settings' );

		if ( $this->checkVersionInfo() ) {

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
	}

	/** Make sure our MySQL version is sufficient to do all this.
	 * @return bool
	 */
	private function checkVersionInfo(): bool {

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
					'save'     => false,
					'class'    => array(
						'fieldrow' => 'major',
					),
				)
			)
		);

		if ( ! $this->db->canReindex ) {
			$this->showVersionInfo();
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
				$this->showVersionInfo();
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
	 * text field showing versions
	 */
	private function showVersionInfo() {
		global $wp_version;
		$versionString = 'MySQL:' . htmlspecialchars( $this->db->semver->version ) . '&emsp;WordPress:' . $wp_version . '&emsp;php:' . phpversion();
		$this->addSettingFields(
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

	/** list of tables
	 *
	 * @param array $tablesToRekey
	 * @param bool $prefixed true if $tablesToRekey ar wp_foometa not just foometa
	 * @param string $action "rekey" or "revert"
	 * @param string $title
	 * @param string $caption
	 * @param string $callToAction button caption
	 * @param bool $prechecked items should be prechecked
	 */
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
				'save'        => false,
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

	/** text string with wp cli instrutions
	 *
	 * @param string $command cli command string
	 * @param string $function description of function to carry out
	 *
	 * @return string
	 */
	private function cliMessage( $command, $function ) {
		//$cliLink = ' <a href="https://make.wordpress.org/cli/handbook/" target="_blank">WP-CLI</a>';
		$cliLink = ' WP-CLI';
		$wp      = 'wp index-mysql';
		$blogid  = get_current_blog_id();
		if ( $blogid > 1 ) {
			$wp .= ' ' . '--blogid=' . $blogid;
		}
		/* translators: %1$s is WP-CLI hyperlink, %2s is 'wp index-mysql',  %3$s describes the function, %4$s is the cli commmand */
		$fmt = __( 'Using %1$s, %2$s: <code>%3$s %4$s</code>', $this->domain );

		return sprintf( $fmt, $cliLink, $function, $wp, $command );
	}

	/**
	 * form for upgrading tables to InnoDB
	 */
	private function upgradeIndex() {
		if ( count( $this->db->oldEngineTables ) > 0 ) {
			$action       = 'upgrade';
			$title        = '<span class="warning header">' . __( 'Upgrade table storage', $this->domain ) . '</span>';
			$caption      = __( 'These database tables need upgrading to InnoDB with the Dynamic row format, MySQL\'s latest table storage.', $this->domain );
			$callToAction = __( 'Upgrade Storage Now', $this->domain );
			$this->renderListOfTables( $this->db->oldEngineTables, true, $action, $title, $caption, $callToAction, true );
		}
	}

	/** Render the Monitor Database Operations form
	 *
	 * @param $oAdminPage
	 *
	 * @noinspection PhpUnusedParameterInspection PhpUnused
	 */
	public function load_imfs_settings_monitor( $oAdminPage ) {
		$this->enqueueStyles(
			array(
				plugins_url( 'assets/imfs.css', __FILE__ ),
			), 'imfs_settings' );

		$sampleText = __( 'sampling %d%% of pageviews.', $this->domain );
		$this->addSettingFields(
			array(
				'field_id' => 'monitoring_parameters',
				'title'    => __( 'Monitoring', $this->domain ),
				'label'    => __( 'We can monitor your site\'s use of MySQL for a few minutes to help you understand what runs slowly.', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
			) );
		$this->addSettingFields(
			array(
				'field_id' => 'monitor_specs',
				'type'     => 'inline_mixed',
				'content'  => array(
					array(
						'field_id' => 'targets',
						'type'     => 'select',
						'save'     => true,
						'default'  => 3,
						'label'    => array(
							3 => __( 'Monitor Dashboard and Site', $this->domain ),
							2 => __( 'Monitor Site Only', $this->domain ),
							1 => __( 'Monitor Dashboard Only', $this->domain ),
						),
					),
					array(
						'field_id'        => 'duration',
						'type'            => 'number',
						'label_min_width' => '',
						'label'           => __( 'for', $this->domain ),
						'save'            => true,
						'default'         => 5,
						'class'           => array(
							'fieldset' => 'inline',
							'fieldrow' => 'number',
						),
					),
					array(
						'field_id' => 'duration_text_minutes',
						'label'    => __( 'minutes', $this->domain ),
						'save'     => false,
					),
					array(
						'field_id'   => 'samplerate',
						'type'       => 'select',
						'save'       => true,
						'default'    => 100,
						'label'      => array(
							100 => __( 'capturing all pageviews.', $this->domain ),
							50  => sprintf( $sampleText, 50 ),
							20  => sprintf( $sampleText, 20 ),
							10  => sprintf( $sampleText, 10 ),
							5   => sprintf( $sampleText, 5 ),
							2   => sprintf( $sampleText, 2 ),
							1   => sprintf( $sampleText, 1 ),
						),
						'attributes' => array(
							'title' => __( 'If your site is very busy, chooose a lower sample rate.', $this->domain ),
						),
					),
					array(
						'field_id' => 'name',
						'type'     => 'text',
						'label'    => __( 'Save into', $this->domain ),
						'save'     => true,
						'default'  => 'monitor',
						'class'    => array(
							'fieldset' => 'inline',
							'fieldrow' => 'name',
						),
					),
				),
			) );

		$this->addSettingFields(
			array(
				'field_id' => 'monitoring_starter',
				'label'    => __( 'Monitoring stops automatically.', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
				array(
					'field_id' => 'start_monitoring_now',
					'type'     => 'submit',
					'save'     => false,
					'value'    => __( 'Start Monitoring', $this->domain ),
					'class'    => array(
						'fieldrow' => 'action',
					),
				),
//	 TODO add wp cli for monitoring
//              array(
//					'label' => $this->cliMessage( 'monitor --minutes=n', __( 'Monitor', $this->domain ) ),
//					'type'  => 'label',
//					'save'  => false,
//					'class' => array(
//						'fieldrow' => 'info',
//					),
//				),

			)
		);

		$this->addSettingFields(
			array(
				'field_id' => 'monitor_headers',
				'title'    => __( 'Monitors', $this->domain ),
				'label'    => __( 'Available monitors', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
			) );


		foreach ( $this->monitors as $monitor ) {
			$log         = new RenderMonitor( $monitor );
			$summary     = $log->load()->summary();
			$monitorText = sprintf( "<a href=\"%s&tab=%s%s\">%s</a> %s",
				admin_url( 'tools.php?page=imfs_settings' ), $monitor, $this->tabSuffix, $monitor, $summary );
			$this->addSettingFields(
				array(
					'field_id' => 'monitor_row_' . $monitor,
					'type'     => 'inline_mixed',
					'content'  => array(
						array(
							'field_id'   => 'delete_' . $monitor . '_now',
							'type'       => 'submit',
							'save'       => false,
							'value'      => 'X',
							'tip'        => __( 'Delete', $this->domain ) . ' ' . $monitor,
							'attributes' => array(
								'class' => 'button button_secondary button_delete',
								'title' => __( 'Delete', $this->domain ) . ' ' . $monitor,
							),
						),

						array(
							'field_id' => $monitor . '_title',
							'default'  => $monitorText,
							'save'     => false,
							'class'    => array(
								'fieldrow' => 'info',
							),
						),
					)
				) );
		}

	}

	/** Render the About form
	 *
	 * @param $oAdminPage
	 *
	 * @noinspection PhpUnusedParameterInspection PhpUnused
	 */
	public function load_imfs_settings_info( $oAdminPage ) {
		$this->enqueueStyles(
			array(
				plugins_url( 'assets/imfs.css', __FILE__ ),
			), 'imfs_settings' );

		global $wp_version;
		$versionString = 'MySQL:' . htmlspecialchars( $this->db->semver->version ) . ' WordPress:' . $wp_version . ' php:' . phpversion();
		$this->addSettingFields(
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
		if ( ! $this->db->unconstrained ) {
			$this->addSettingFields(
				array(
					'field_id' => 'constraint_notice',
					'title'    => 'Notice',
					'default'  => __( 'Upgrading your MySQL server version will give you better performance when you add high-performance keys.', $this->domain ),
					'save'     => false,
					'class'    => array(
						'fieldrow' => 'warning',
					),
				) );
		}

		$this->uploadMetadata();
	}

	/** @noinspection PhpUnused */

	/**
	 * render the upload-metadata form fields.
	 */
	function uploadMetadata() {
		$this->addSettingFields(
			array(
				'field_id' => 'permission',
				'title'    => __( 'Diagnostic data', $this->domain ),
				'label'    => __( 'With your permission we upload metadata about your WordPress site to our plugin\'s servers. We cannot identify you or your website from it, and we never sell nor give it to any third party. We use it only to improve this plugin.', $this->domain ),
				'save'     => false,
				'class'    => array(
					'fieldrow' => 'info',
				),
				array(
					'field_id'    => 'upload_metadata_now',
					'type'        => 'submit',
					'save'        => false,
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

	/** load overall page
	 *
	 * @param $oAdminPage
	 */
	public function load_imfs_settings( $oAdminPage ) {
		try {
			$this->populate();
		} catch ( ImfsException $ex ) {
			$msg = __( 'Something went wrong inspecting your database', $this->domain ) . ': ' . $ex->getMessage();
			$this->setSettingNotice( $msg );

			return;
		}
	}

	/** @noinspection PhpUnused */

	/**
	 * @throws ImfsException
	 */
	private function populate() {

		$this->db->init();
		$this->canReindex    = $this->db->canReindex;
		$this->unconstrained = $this->db->unconstrained;
	}

	/** @noinspection PhpUnused */

	function validation_imfs_settings_rekey( $inputs, $oldInputs, $factory, $submitInfo ) {
		$valid  = true;
		$errors = array();

		if ( ! isset ( $inputs['backup']['1'] ) || ! $inputs['backup']['1'] ) {
			$valid            = false;
			$errors['backup'] = __( 'Please acknowledge that you have made a backup.', $this->domain );
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

		$this->valid = $valid;

		if ( ! $valid ) {
			$this->setFieldErrors( $errors );
			$this->setSettingNotice( __( 'Make corrections and try again.', $this->domain ) );

			return $oldInputs;
		}

		return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
	}

	/** @noinspection PhpUnused */

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
				case 'start_monitoring_now':
					$qmc     = new QueryMonControl();
					$message = $qmc->start( $inputs['monitor_specs'] );
					$this->setSettingNotice( $message, 'updated' );
					break;
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

	/** @noinspection PhpUnusedParameterInspection */

	function validation_imfs_settings_monitor( $inputs, $oldInputs, $factory, $submitInfo ) {
		$valid  = true;
		$errors = array();

		foreach ( $inputs as $key => $value ) {
			if ( 0 === strpos( $key, "monitor_row_" ) ) {
				foreach ( $value as $rowkey => $button ) {
					$monitor = preg_replace( "/^delete_(.+)_now$/", "$1", $rowkey );
					if ( array_search( $monitor, $this->monitors ) !== false ) {
						RenderMonitor::deleteMonitor( $monitor );
						$this->monitors = RenderMonitor::getMonitors();
						$message        = __( 'Monitor %s deleted.', $this->domain );
						$message        = sprintf( $message, $monitor );
						$this->setSettingNotice( $message, 'updated' );
						$this->valid = true;

						return $oldInputs;
					}
				}
			}
		}

		if ( is_array( $submitInfo ) && is_string( $submitInfo['field_id'] ) && $submitInfo['field_id'] === 'start_monitoring_now' ) {
			$monitor = $inputs['monitor_specs']['name'];
			if ( ctype_alnum( $monitor ) === false ) {
				$valid                   = false;
				$errors['monitor_specs'] = __( "Letters and numbers only for your monitor name, please.", $this->domain );
			}
		}

		if ( ! $valid ) {
			$this->setFieldErrors( $errors );
			$this->setSettingNotice( __( 'Make corrections and try again.', $this->domain ) );

			return $oldInputs;
		}

		return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
	}

	function validation_imfs_settings_info( $inputs, $oldInputs, $factory, $submitInfo ) {
		$valid  = true;
		$errors = array();
		if ( ! $valid ) {
			$this->setFieldErrors( $errors );
			$this->setSettingNotice( __( 'Make corrections and try again.', $this->domain ) );

			return $oldInputs;
		}

		return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
	}

}

new ImfsPage;