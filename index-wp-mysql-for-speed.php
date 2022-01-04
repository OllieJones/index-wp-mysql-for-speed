<?php

/*
Plugin Name: Index WP MySQL For Speed
Plugin URI: https://plumislandmedia.org/
Description: Add useful indexes to your WordPress installation's MySQL database.
Version: 1.4,1
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
define( 'index_wp_mysql_for_speed_VERSION_NUM', '1.4.0' );
define( 'index_mysql_for_speed_major_version', 1.4 );
define( 'index_mysql_for_speed_previous_major_version', 1.3 );

/* set up some handy globals */
define( 'index_wp_mysql_for_speed_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
define( 'index_wp_mysql_for_speed_domain', index_wp_mysql_for_speed_PLUGIN_NAME );
define( 'index_wp_mysql_for_speed_stats_endpoint', $target = 'https://lit-mesa-75588.herokuapp.com/imfsstats' );
define( 'index_wp_mysql_for_speed_monitor', 'imfsQueryMonitor' );
define( 'index_wp_mysql_for_speed_querytag', '/*imfs-query-tag*/' );
/* 32814 was the advent of utfmb4 */
define( 'index_wp_mysql_for_speed_first_compatible_db_version', 32814 );
define( 'index_wp_mysql_for_speed_last_compatible_db_version', 51917 );


register_activation_hook( __FILE__, 'index_wp_mysql_for_speed_activate' );
register_deactivation_hook( __FILE__, 'index_wp_mysql_for_speed_deactivate' );

add_action( 'init', 'index_wp_mysql_for_speed_do_everything' );

function index_wp_mysql_for_speed_do_everything() {
  /* admin page activation */
  $admin = is_admin();
  if ( $admin ) {
    $userCanLoad = is_multisite() ? is_super_admin() : current_user_can( 'activate_plugins' );
    if ( $userCanLoad ) {
      requireThemAll();
      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'index_wp_mysql_for_speed_action_link' );
    }
  }
  /* wp-cli interface activation */
  if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( plugin_dir_path( __FILE__ ) . 'code/cli.php' );
    requireThemAll();
  }
}

add_action( 'init', 'index_wp_mysql_for_speed_monitor', 0 );

function index_wp_mysql_for_speed_monitor() {
  /* monitoring code ... as light as possible when not monitoring. */
  $monval = get_option( index_wp_mysql_for_speed_monitor );
  if ( $monval ) {
    if ( $monval->stoptime > time() ) {
      $admin = is_admin();
      if ( ( ( $monval->targets & 1 ) !== 0 && $admin ) || ( ( $monval->targets & 2 ) !== 0 && ! $admin ) ) {
        if ( $monval->samplerate === 1.0 || rand() <= $monval->samplerate * getrandmax() ) {
          require_once( plugin_dir_path( __FILE__ ) . 'code/querymon.php' );
          new ImfsMonitor( $monval, 'capture' );
        }
      }
    } else {
      require_once( plugin_dir_path( __FILE__ ) . 'code/querymon.php' );
      $m = new ImfsMonitor( $monval, 'nocapture' );
      $m->completeMonitoring();
      delete_option( index_wp_mysql_for_speed_monitor );
    }
  }
  //}
  /* wp-cli interface activation */
  if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( plugin_dir_path( __FILE__ ) . 'code/cli.php' );
    requireThemAll();
  }
}

function requireThemAll() {
  require_once( plugin_dir_path( __FILE__ ) . 'code/imsfdb.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'afp/admin-page-framework.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/admin.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/upload.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/querymoncontrol.php' );
}

function index_wp_mysql_for_speed_activate() {
  if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ) {
    deactivate_plugins( basename( __FILE__ ) ); /* fail activation */
  }
}

function index_wp_mysql_for_speed_deactivate() {
  /* clean up options and transients */
  global $wpdb;
  delete_option( 'ImfsPage' );
  $q  = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '" . index_wp_mysql_for_speed_monitor . "%'";
  $rs = $wpdb->get_results( index_wp_mysql_for_speed_querytag . $q );
  foreach ( $rs as $r ) {
    delete_option( $r->option_name );
  }
  $q  = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_" . index_wp_mysql_for_speed_monitor . "%'";
  $rs = $wpdb->get_results( index_wp_mysql_for_speed_querytag . $q );
  foreach ( $rs as $r ) {
    delete_transient( $r->option_name );
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
  $mylinks = [
    '<a href="' . admin_url( 'tools.php?page=imfs_settings' ) . '">' . $name . '</a>',
  ];

  return array_merge( $mylinks, $actions );
}
