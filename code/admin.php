<?php

class Imfs_CreatePage extends Imfs_AdminPageFramework {

	public string $pluginName;
	public string $pluginSlug;
	public string $domain;

	public function __construct( $slug = index_wp_mysql_for_speed_domain ) {
		parent::__construct();
		$this->domain     = $slug;
		$this->pluginName = __( 'Index WP MySQL For Speed', $this->domain );
		$this->pluginSlug = $slug;
	}

	//https://admin-page-framework.michaeluno.jp/tutorials/01-create-a-wordpress-admin-page/

	public function setUp() {

// Create the root menu - specifies to which parent menu to add.
		$this->setRootMenuPage( 'Settings' );

// Add the sub menus and the pages.
		$pageName = $this->pluginName . ': ' . __( 'Settings', $this->domain );
		/* translators: settings page menu text */
		$menuName = __('Index MySQL', $this->domain);
		$this->addSubMenuItems(
			array(
				'title'     => $pageName,
				'menu_title' => $menuName,
				'page_slug' => 'imfs_settings',
				'order' => 50,
				'capability' => 'activate_plugins'

			)
		);
	}

	public function do_imfs_settings () {
		?>
		<h3>Action Hook</h3>
		<p>This is inserted by the 'do_' + page slug method.</p>
		<?php
	}

	public function load_imfs_settings ( $oAdminPage) {
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

}

new Imfs_CreatePage;