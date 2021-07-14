<?php

/*
Plugin Name: Index WP MySQL For Speed
Plugin URI: https://plumislandmedia.org/
Description: Add useful indexes to your WordPress installation's MySQL database.
Version: 1.0.2
Author: Ollie Jones
Author URI: https://github.com/OllieJones
Requires at least: 5.2
Requires PHP:      7.2
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       index-wp-mysql-for-speed
Domain Path:       /languages
Network:           true
*/

/** current version number  */
define( 'index_wp_mysql_for_speed_VERSION_NUM', '1.0.2' );

/* set up some handy globals */
define( 'index_wp_mysql_for_speed_THEME_DIR', ABSPATH . 'wp-content/themes/' . get_template() );
define( 'index_wp_mysql_for_speed_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
define( 'index_wp_mysql_for_speed_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . index_wp_mysql_for_speed_PLUGIN_NAME );
define( 'index_wp_mysql_for_speed_PLUGIN_URL', WP_PLUGIN_URL . '/' . index_wp_mysql_for_speed_PLUGIN_NAME );
define( 'index_wp_mysql_for_speed_domain', index_wp_mysql_for_speed_PLUGIN_NAME );
define( 'index_wp_mysql_for_speed_stats_endpoint', $target = 'https://lit-mesa-75588.herokuapp.com/imfsstats' );

register_activation_hook( __FILE__, 'index_wp_mysql_for_speed_activate' );

add_action( 'init', 'index_wp_mysql_for_speed_do_everything' );

function index_wp_mysql_for_speed_do_everything() {
	if ( is_admin() ) {
		if ( is_multisite() ) {
			$userCanLoad = is_super_admin();
		} else {
			$userCanLoad = current_user_can( 'activate_plugins' );
		}
		if ( $userCanLoad ) {
			require_once( dirname( __FILE__ ) . '/code/imsfdb.php' );
			require_once( dirname( __FILE__ ) . '/afp/admin-page-framework.php' );
			require_once( dirname( __FILE__ ) . '/code/admin.php' );
			require_once( dirname( __FILE__ ) . '/code/upload.php' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'index_wp_mysql_for_speed_action_link' );
		}
	}
}

function index_wp_mysql_for_speed_activate() {
	if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ) {
		deactivate_plugins( basename( __FILE__ ) ); /* fail activation */
	}
}

/**
 * Add Settings link to this plugin's listing on the Plugins page.
 *
 * @param $actions
 *
 * @return array
 */
function index_wp_mysql_for_speed_action_link( $actions ) {
	$name    = __( "Settings", index_wp_mysql_for_speed_domain );
	$mylinks = array(
		'<a href="' . admin_url( 'tools.php?page=imfs_settings' ) . '">' . $name . '</a>',
	);

	return array_merge( $mylinks, $actions );
}
