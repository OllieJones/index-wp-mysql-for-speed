<?php

/*
Plugin Name: Index WP MySQL For Speed
Plugin URI: https://plumislandmedia.org/
Description: Add useful indexes to your WordPress installation's MySQL database.
Version: 0.0.1
Author: Ollie Jones
Author URI: https://github.com/OllieJones
Requires at least: 5.2
Requires PHP:      7.2
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       index-wp-mysql-for-speed
Domain Path:       /languages
*/

/** current version number  */
defineIfNot( 'index_wp_mysql_for_speed_VERSION_NUM', '0.0.1' );

/* set up some handy globals */
defineIfNot( 'index_wp_mysql_for_speed_THEME_DIR', ABSPATH . 'wp-content/themes/' . get_template() );
defineIfNot( 'index_wp_mysql_for_speed_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
defineIfNot( 'index_wp_mysql_for_speed_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . index_wp_mysql_for_speed_PLUGIN_NAME );
defineIfNot( 'index_wp_mysql_for_speed_PLUGIN_URL', WP_PLUGIN_URL . '/' . index_wp_mysql_for_speed_PLUGIN_NAME );
defineIfNot( 'index_wp_mysql_for_speed_POSTMETA_KEY', '_' . index_wp_mysql_for_speed_PLUGIN_NAME . '_metadata' );
defineIfNot( 'index_wp_mysql_for_speed_domain', '_' . index_wp_mysql_for_speed_PLUGIN_NAME );

register_activation_hook( __FILE__, 'index_wp_mysql_for_speed_activate' );

$saved = get_include_path();
set_include_path( $saved . PATH_SEPARATOR . index_wp_mysql_for_speed_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'code' );

add_action( 'init', 'index_wp_mysql_for_speed_do_everything' );
add_action( 'plugins_loaded', 'index_wp_mysql_for_speed_l12n' );

function index_wp_mysql_for_speed_do_everything() {
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		require_once( 'code/imsfdb.php' );
		require_once( 'code/admin.php' );
		$db = new ImfsDb();
		$output = $db->getStats();
		$problems = $db->anyProblems("enable");
		$foo = $db->newIndexing();
	}
}

function index_wp_mysql_for_speed_l12n() {
	if ( is_admin() ) {
		/* no need for translation except in admin */
		load_plugin_textdomain( 'index-wp-mysql-for-speed', false, dirname( plugin_basename( __FILE__ ) ) .
		                                                        DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR );
	}
}

function index_wp_mysql_for_speed_activate() {
	if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ) {
		deactivate_plugins( basename( __FILE__ ) ); /* fail activation */
	}
	/* make sure the options are loaded, but don't overwrite existing version */
	add_option( 'index_wp_mysql_for_speed_version', index_wp_mysql_for_speed_VERSION_NUM, false, 'no' );

	/* check version and upgrade plugin if need be. */
	if ( index_wp_mysql_for_speed_VERSION_NUM != ( $opt = get_option( 'index_wp_mysql_for_speed_version', '0.0.0' ) ) ) {
		/* do update procedure here as needed */
		update_option( 'index_wp_mysql_for_speed_version', index_wp_mysql_for_speed_VERSION_NUM );
	}


	/* handle options settings defaults */
	$o = array(
		'option1' => 'TBD',
		'option2' => 'TBD',
	);
	add_option( 'index_wp_mysql_for_speed_options', $o, false, 'no' );
}

/** conditionally define a symbol
 *
 * @param $name
 * @param $value
 */
function defineIfNot( $name, $value ) {
	if ( ! defined( $name ) ) {
		define( $name, $value );
	}
}


