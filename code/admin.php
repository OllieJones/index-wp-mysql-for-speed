<?php

class ImfsPage extends Imfs_AdminPageFramework {

	public string $pluginName;
	public string $pluginSlug;
	public string $domain;
	private ImfsDb $db;
	public bool $canReindex = false;
	/**
	 * @var false|mixed|string
	 */
	private $stats = false;

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
		$this->setRootMenuPage( 'Dashboard' ); //TODO put this back to Settings

		$pageName = $this->pluginName . ': ' . __( 'Settings', $this->domain );
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

		$this->populate();

		$this->enqueueStyles(
			array(plugins_url('assets/imfs.css',  __FILE__ )),'imfs_settings'    );
		$this->enqueueScripts(
			array(plugins_url('assets/imfs.js',  __FILE__ )),'imfs_settings'    );

		$this->addSettingFields(
			array(
				'field_id'    => 'backup_done',
				'type'        => 'checkbox',
				'title'       => __( 'My WordPress installation is backed up', $this->domain ),
				'description' => __( 'This plugin modifies your WordPress database. It is vital to make a backup before you proceed.', $this->domain ),
				'default'     => 0,
                'save' => false,
			),
			array(
				'field_id'    => 'version',
				'title'       => __( 'Your MySQL server version', $this->domain ),
				'default'     => htmlspecialchars($this->db->semver->version),
				'save' => false,
			) );

		if ( ! $this->db->canReindex ) {
			$this->addSettingFields(
				array(
					'field_id'    => 'version_error',
					'title'       => 'Notice',
					'default'     => __( 'Sorry, you cannot use this plugin on this version of MySQL', $this->domain ),
					'description' => __( 'Your MySQL version is outdated. Pleas consider upgrading', $this->domain ),
                'save' => false,
				) );

			return;
		}

		if ( count( $this->db->oldEngineTables ) > 0 ) {

			if ( count( $this->db->newEngineTables ) === 0 ) {
				$this->addSettingFields(
					array(
						'field_id' => 'fix_engine_all',
						'title'    => __( 'Storage Engine Upgrade Needed', $this->domain ),
						'default'  => __( 'All database tables need upgrading to InnoDB, MySQL\'s latest storage engine.', $this->domain ),
						'save' => false,
					) );
			} else {
				$tables = htmlspecialchars( implode( ', ', $this->db->oldEngineTables ) );

				$this->addSettingFields(
					array(
						'field_id'    => 'fix_engine_some',
						'title'       => __( 'Storage Engine Upgrade Needed', $this->domain ),
						'default'     => __( 'These database tables need upgrading to InnoDB, MySQL\'s latest storage engine.', $this->domain ),
						'description' => $tables,
						'save' => false,

					) );
			}
			$this->addSettingFields(
				array(
					'field_id' => 'upgrade_storage_engine_button',
					'title'    => __( 'Upgrade Storage Engine', $this->domain ),
					'type'     => 'submit',
					'save'     => 'false',
					'value'    => __( 'Upgrade Storage Engine Now', $this->domain )
				) );

			return;

		}
		$this->addSettingFields(
			array(    // Single text field
				'field_id'    => 'my_text_field',
				'type'        => 'text',
				'title'       => 'Text',
				'description' => 'text.',
			),
			array(    // Text Area
				'field_id'    => 'my_textarea_field',
				'type'        => 'textarea',
				'title'       => 'Single Text Area',
				'description' => 'Type a text string here.',
				'default'     => 'Hello World! This is set as the default string.',
			),
			array(    // Text Area
				'field_id'    => 'my_checkbox_field',
				'type'        => 'checkbox',
				'title'       => 'Allow upload',
				'description' => 'Checkbox.',
				'default'     => 0,
			),
			array( // Submit button
				'field_id' => 'submit_button',
				'type'     => 'submit',
			)
		);
	}


	protected function populate() {

		$this->db->init();
		$this->canReindex = $this->db->canReindex;
	}

	function validation_ImfsPage($inputs, $oldInputs, $factory, $submitInfo) {
	    $valid = true;
	    $errors = array();

	    if ( ! isset ($inputs['backup_done']) || ! $inputs['backup_done']) {
	        $valid = false;
	        $errors['backup_done'] = __('Please acknowledge that you have made a backup', $this->domain);
	    }

	    if (!$valid) {
	        $this->setFieldErrors($errors);
	        $this->setSettingNotice( __('Make corrections and try again.', $this->domain));
	        return $oldInputs;
	    }

	    $inputs = $this->action ($submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo);

	    return $inputs;
	}

	private function action ($button, $inputs, $oldInputs, $factory, $submitInfo) {
		try {
			switch ( $button ) {
				case 'upgrade_storage_engine_button':

					$msg = $this->db->upgradeStorageEngine();

					$this->setSettingNotice( $msg, 'updated' );
					break;
				case 'reindex_button':

					$this->setSettingNotice( __( 'Stub reindex', $this->domain ), 'updated' );
					break;
				case 'revert_index_button':

					$this->setSettingNotice( __( 'Stub revert index', $this->domain ), 'updated' );
					break;
			}

			return $inputs;
		} catch (ImfsException $ex) {
			$msg = $ex->getMessage();
			$this->setSettingNotice($msg, 'error');
			return $oldInputs;
		}



		return $inputs;

	}

}

new ImfsPage;