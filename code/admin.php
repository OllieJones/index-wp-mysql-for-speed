<?php

/** Class wrapper for Index MySQL for Speed admin page
 */

class IndexMySqlAdminPage {

	/**
	 * Initialize the administration page operations.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_actions' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	function admin_actions() {
		load_plugin_textdomain( index_wp_mysql_for_speed_domain, index_wp_mysql_for_speed_PLUGIN_DIR, 'languages' );
		add_options_page(
			__( 'Index MySQL', index_wp_mysql_for_speed_domain ),
			__( 'Index MySQL', index_wp_mysql_for_speed_domain ),
			'activate_plugins',
			'imfsdb',
			array( $this, 'admin_page' ) );
	}

	function register_setting() {
		register_setting( 'index_wp_mysql_for_speed', '_options', array( $this, 'validate_options' ) );
	}

	/**
	 * emit the options heading for the overview section
	 */
	function general_text() {
		echo '<p>' . __( 'Adding indexes to make your database faster.', index_wp_mysql_for_speed_domain ) . '</p>';
	}


	/**
	 * emit a yes-no question
	 */
	function populate_question( $optitem, $optyesanswer, $optnoanswer ) {
		// get option 'populate_tags' value from the database
		$options = get_option( 'index_wp_mysql_for_speed_options' );
		$choice  = ( empty( $options[ $optitem ] ) ) ? 'no' : $options[ $optitem ];

		$choices = array(
			'yes' => $optyesanswer,
			'no'  => $optnoanswer,
		);
		$pattern = '<input type="radio" id="index_wp_mysql_for_speed_4$s" name="index_wp_mysql_for_speed_options[%4$s]" value="%1$s" %2$s> %3$s';

		$f = array();
		foreach ( $choices as $i => $k ) {
			$checked = ( $choice == $i ) ? 'checked' : '';
			$f[]     = sprintf( $pattern, $i, $checked, $k, $optitem );
		}
		echo implode( '&nbsp;&nbsp;&nbsp;&nbsp', $f );
		unset ( $f );
	}

	/**
	 * emit a text question
	 *
	 * @param string $item contains the options item name ... e.g. audio_caption
	 */
	function admin_text( $item ) {
		$options = get_option( 'index_wp_mysql_for_speed_options' );
		$value   = ( empty( $options[ $item ] ) ) ? ' ' : $options[ $item ];
		$pattern = '<input type="text" id="index_wp_mysql_for_speedadmin_%2$s" name="index_wp_mysql_for_speed_options[%2$s]" value="%1$s" size="80" />';
		$pattern = sprintf( $pattern, htmlspecialchars( $value ), $item );
		echo $pattern;
		echo "\n";
	}

	/**
	 * validate the options settings
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	function validate_options( $input ) {
		$codes = array( 'telemetry_ok' );
		$valid = array();
		foreach ( $codes as $code ) {
			$valid[ $code ] = htmlspecialchars_decode( $input[ $code ] );
		}

		return $valid;
	}

	/**
	 * emit the telemetry ok question
	 */
	function telemetry_ok_text() {
		$this->populate_question( 'telemetry_ok',
			__( 'Yes', index_wp_mysql_for_speed_domain ),
			__( 'No', index_wp_mysql_for_speed_domain )
		);
	}


	function admin_page() {
		?>
        <div class="wrap">
            <div id="icon-plugins" class="icon32"></div>
            <div id="icon-options-general" class="icon32"></div>
			<?php
			printf( '<div class="wrap"><h2>' .
			        __( 'Index MySQL For Speed', index_wp_mysql_for_speed_domain ) .
			        '<small> (v%1s)</small>' .
			        '</h2></div>', index_wp_mysql_for_speed_VERSION_NUM );

			add_settings_section(
				'imfsdb_admin_general',
				__( 'Settings', index_wp_mysql_for_speed_domain ),
				array( $this, 'general_text' ),
				'imfsdb' );


			add_settings_field(
				'imfsdb_admin_telemetry_ok',
				__( 'Upload anonymous information about your database', index_wp_mysql_for_speed_domain ),
				array( $this, 'telemetry_ok_text' ),
				'imfsdb',
				'imfsdb_admin_general'
			);


			?>
            <form action="options.php" method="post">

				<?php
				settings_fields( 'index_wp_mysql_for_speed' );
				do_settings_sections( 'index_wp_mysql_for_speed' );
				?>

                <p class="submit">
                    <input name="Submit" type="submit" id="submit"
                           class="button button-primary"
                           value="<?php _e( 'Save Changes', index_wp_mysql_for_speed_domain ); ?>"/>
                </p></form>
        </div>

		<?php

	}
}

new IndexMySqlAdminPage();
