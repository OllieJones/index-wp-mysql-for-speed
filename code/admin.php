<?php

class ImfsPage extends Imfs_AdminPageFramework {

	public string $pluginName;
	public string $pluginSlug;
	public string $domain;
	private ImfsDb $db;
	/**
	 * @var bool true if the dbms allows reindexing at all.
	 */
	public bool $canReindex = false;
	/**
	 * @var bool true if reindexing does not have the 191 constraint
	 */
	private $unconstrained;

	public function __construct( $slug = index_wp_mysql_for_speed_domain ) {
		parent::__construct();
		$this->domain     = $slug;
		$this->pluginName = __( 'Index WP MySQL For Speed', $this->domain );
		$this->pluginSlug = $slug;
		$this->db         = new ImfsDb();
	}

	// https://admin-page-framework.michaeluno.jp/tutorials/01-create-a-wordpress-admin-page/

	public function setUp() {
		//$this->setRootMenuPage( 'Settings' );
		$this->setRootMenuPage( 'Dashboard' ); //TODO put this back to Tools

		$pageName = $this->pluginName;
		/* translators: settings page menu text */
		$menuName = __( 'Index MySQL', $this->domain );
		$this->addSubMenuItems(
			array(
				'title'      => $pageName,
				'menu_title' => $menuName,
				'page_slug'  => 'imfs_settings',
				'order'      => 50,
				'capability' => 'activate_plugins'

			)
		);
	}

	public function load_ImfsPage( $oAdminPage ) {
		global $wpdb;
		$this->populate();

		$this->enqueueStyles(
			array( plugins_url( 'assets/imfs.css', __FILE__ ) ), 'imfs_settings' );

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
				'title'    => __( 'MySQL server', $this->domain ),
				'default'  => __( 'Version', $this->domain ) . ' ' . htmlspecialchars( $this->db->semver->version ),
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
					'default'     => __( 'Sorry, you cannot use this plugin on this version of MySQL', $this->domain ),
					'description' => __( 'Your MySQL version is outdated. Please consider upgrading,', $this->domain ),
					'save'        => false,
					'class'       => array(
						'fieldrow' => 'failure',
					),
				) );
		} else {
			if (!$this->db->unconstrained) {
				$this->addSettingFields(
					array(
						'field_id'    => 'constraint_notice',
						'title'       => 'Notice',
						'default'     => __( 'Upgrading your MySQL server version will give you even better performance.', $this->domain ),
						'save'        => false,
						'class'       => array(
							'fieldrow' => 'warning',
						),
					) );

			}

			/* engine upgrade ***************************/
			if ( count( $this->db->oldEngineTables ) > 0 ) {
				$field = array(
					'field_id' => 'fix_engine_all',
					'title'    => __( 'Storage Engine Upgrade Needed', $this->domain ),
					'default'  => __( 'All database tables need upgrading to InnoDB, MySQL\'s latest storage engine.', $this->domain ),
					'save'     => false,
					'class'    => array(
						'fieldrow' => 'info',
					)
				);

				if ( count( $this->db->newEngineTables ) === 0 ) {
					$field['default'] = __( 'Upgrade your database tables to InnoDB, MySQL\'s latest storage engine.', $this->domain );
				} else {
					$field['default']     = __( 'Upgrade these database tables to InnoDB, MySQL\'s latest storage engine.', $this->domain );
					$field['description'] = htmlspecialchars( implode( ', ', $this->db->oldEngineTables ) );
				}
				$this->addSettingField( $field );
				$this->addSettingFields(
					array(
						'field_id' => 'upgrade_storage_engine_now',
						'title'    => __( 'Upgrade Storage Engine', $this->domain ),
						'type'     => 'submit',
						'save'     => 'false',
						'value'    => __( 'Upgrade Storage Engine Now', $this->domain ),
						'class'    => array(
							'fieldrow' => 'action',
						),
					) );

				return;

			}
			/* cannot rekey ***************************/
			$rekeying = $this->db->getRekeying();
			/* rekeying ***************************/
			if ( count( $rekeying['enable'] ) > 0 ) {
				$this->addSettingFields(
					array(
						'field_id' => 'enablecaption',
						'title'    => 'Add high-performance keys',
						'default'  => __( 'Add keys to these tables to make your WordPress database faster.', $this->domain ),
						'save'     => false,
						'class'    => array(
							'fieldrow' => 'major',
						),
					),
				);

				$labels   = array();
				$defaults = array();
				foreach ( $rekeying['enable'] as $tbl ) {
					$labels[ $tbl ]   = $wpdb->prefix . $tbl;
					$defaults[ $tbl ] = true;
				}

				$this->addSettingFields(
					array(
						'field_id'           => 'enable',
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
						'field_id' => 'enable_now',
						'type'     => 'submit',
						'save'     => 'false',
						'value'    => __( 'Add Keys Now', $this->domain ),
						'class'    => array(
							'fieldrow' => 'action',
						),
					) );
			}
			/* reverting  ***************************/
			if ( count( $rekeying['disable'] ) > 0 ) {
				$this->addSettingFields(
					array(
						'field_id' => 'successcaption',
						'title'    => 'Success',
						'default'  => __( 'Your WordPress tables now have high-performance keys.', $this->domain ),
						'save'     => false,
						'class'    => array(
							'fieldrow' => array('major', 'success'),
						),
					),
					array(
						'field_id' => 'disablecaption',
						'title'    => 'Revert keys',
						'default'  => __( 'Revert the keys on these tables to restore WordPress\'s defaults.', $this->domain ),
						'save'     => false,
						'class'    => array(
							'fieldrow' => 'major',
						),
					),
				);

				$labels   = array();
				$defaults = array();
				foreach ( $rekeying['disable'] as $tbl ) {
					$labels[ $tbl ]   = $wpdb->prefix . $tbl;
					$defaults[ $tbl ] = false;
				}

				$this->addSettingFields(
					array(
						'field_id'           => 'revert',
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
						'field_id' => 'revert_now',
						'type'     => 'submit',
						'save'     => 'false',
						'value'    => __( 'Revert Keys Now', $this->domain ),
						'class'    => array(
							'fieldrow' => 'action',
						),
					) );
			}
			/* errors **********************************/
			if ( count( $rekeying['errors'] ) > 0 ) {
				$this->addSettingFields(
					array(
						'field_id'    => 'norekeycaption',
						'title'       => 'Problems Rekeying',
						'default'     => __( 'We cannot rekey some tables.', $this->domain ),
						'description' => __( 'This often means they have already been rekeyed by some other plugin or workflow.', $this->domain ),
						'save'        => false,
						'class'       => array(
							'fieldrow' => array( 'warning', 'header' ),
						),
					) );
				foreach ( $rekeying['errors'] as $tbl => $message ) {
					$this->addSettingFields(
						array(
							'field_id' => 'norekey_' . $tbl,
							'title'    => $wpdb->prefix . $tbl,
							'default'  => $message,
							'save'     => false,
							'class'    => array(
								'fieldrow' => array( 'warning', 'detail' ),
							),
						) );
				}
			}
		}

		if (extension_loaded('curl') ) {

			$this->addSettingFields(
				array(
					'field_id' => 'permission',
					'title'    => __( 'Diagnostic data', $this->domain ),
					'label'    => __( 'We upload metadata about your WordPress site to our plugin\'s servers. We cannot identify you or your web site from it, and we never sell nor give it to any third party. We use it only to improve this plugin.', $this->domain ),
					'save'     => true,
					'class'    => array(
						'fieldrow' => 'info',
					),
					array(  //TODO put an action button here
						'field_id' => 'permission',
						'type'     => 'checkbox',
						'label'    => __( 'You may upload my site\'s diagnostic metadata', $this->domain ),
						'default'  => 0,
						'save'     => true,
					),
				)
			);
		}
	}


	protected function populate() {

		$this->db->init();
		$this->canReindex    = $this->db->canReindex;
		$this->unconstrained = $this->db->unconstrained;
	}

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
		if ( $action === 'revert_now' ) {
			if ( count( $this->listFromCheckboxes( $inputs['revert'] ) ) === 0 ) {
				$valid            = false;
				$errors['revert'] = $err;
			}
		}

		if ( ! $valid ) {
			$this->setFieldErrors( $errors );
			$this->setSettingNotice( __( 'Make corrections and try again.', $this->domain ) );

			return $oldInputs;
		}

		return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
	}

	private function listFromCheckboxes( $cbs ): array {
		$result = array();
		foreach ( $cbs as $name => $val ) {
			if ( $val ) {
				$result[] = $name;
			}
		}

		return $result;
	}

	private function action( $button, $inputs, $oldInputs, $factory, $submitInfo ) {
		try {
			switch ( $button ) {
				case 'upgrade_storage_engine_now':
					$msg = $this->db->upgradeStorageEngine();
					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'enable_now':
					$msg = $this->db->rekeyTables( 'enable', $this->listFromCheckboxes( $inputs['enable'] ) );
					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'revert_now':
					$msg = $this->db->rekeyTables( 'disable', $this->listFromCheckboxes( $inputs['revert'] ) );
					$this->setSettingNotice( $msg, 'updated' );
					break;
			}

			return $inputs;
		} catch ( ImfsException $ex ) {
			$msg = $ex->getMessage();
			$this->setSettingNotice( $msg, 'error' );

			return $oldInputs;
		}
	}

}

new ImfsPage;