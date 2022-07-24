<?php

/**
 * Index WP MySQL For Speed
 *
 * @author: Oliver Jones, Rick James
 * @copyright: 2022 Oliver Jones
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin0
 * Plugin Name: Index WP MySQL For Speed
 * Plugin URI:  https://plumislandmedia.org/index-wp-mysql-for-speed/
 * Description: Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.
 * Version:           1.4.6
 * Requires at least: 5.2
 * Tested up to:      6.0
 * Requires PHP:      5.6
 * Author:       OllieJones, rjasdfiii
 * Author URI:   https://github.com/OllieJones
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  index-wp-mysql-for-speed
 * Domain Path:  /languages
 * Network:      true
 * Tags:         database, index, key, mysql, wp-cli
 */

/** current version number  */
define( 'index_wp_mysql_for_speed_VERSION_NUM', '1.4.6' );
define( 'index_mysql_for_speed_major_version', 1.4 );
define( 'index_mysql_for_speed_inception_major_version', 1.3 );
define( 'index_mysql_for_speed_inception_wp_version', '5.8.3' );
define( 'index_mysql_for_speed_inception_wp_db_version', 49752 );
define( 'index_mysql_for_speed_log', null );

/* set up some handy globals */
define( 'index_wp_mysql_for_speed_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
define( 'index_wp_mysql_for_speed_stats_endpoint', $target = 'https://lit-mesa-75588.herokuapp.com/imfsstats' );
define( 'index_wp_mysql_for_speed_monitor', 'imfsQueryMonitor' );
define( 'index_wp_mysql_for_speed_querytag', '*imfs-query-tag*' );
/* version 32814 was the advent of utfmb4 */
define( 'index_wp_mysql_for_speed_first_compatible_db_version', 32814 );
define( 'index_wp_mysql_for_speed_last_compatible_db_version', 0 ); /*tested up to 53496 */

define( 'index_wp_mysql_for_speed_help_site', 'https://plumislandmedia.net/index-wp-mysql-for-speed/' );

register_activation_hook( __FILE__, 'index_wp_mysql_for_speed_activate' );
register_deactivation_hook( __FILE__, 'index_wp_mysql_for_speed_deactivate' );

if (defined('WP_DEBUG') && WP_DEBUG) {
  /* suppress core deprecated hook  TODO remove this after that is fixed. */
  add_filter( 'deprecated_hook_trigger_error', '__return_false' );
}

add_action( 'init', 'index_wp_mysql_for_speed_do_everything' );

function index_wp_mysql_for_speed_do_everything() {
  /* admin page activation */
  $admin = is_admin();
  if ( $admin ) {
    $userCanLoad = is_multisite() ? is_super_admin() : current_user_can( 'activate_plugins' );
    if ( $userCanLoad ) {
      /* recent install or upgrade ? */
      $nag = updateNag();
      requireThemAll( $nag );
      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'index_wp_mysql_for_speed_action_link' );
    }
  }
  /* wp-cli interface activation */
  if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( plugin_dir_path( __FILE__ ) . 'code/cli.php' );
    requireThemAll();
  }
}

/**
 * Figure out whether user hasn't yet added or updated indexes.
 * Doing anything on the highperf index page clears this.
 * We check for plugin major version updates and wp database version updates.
 * @return string|bool
 */
function updateNag() {
  global $wp_version, $wp_db_version;
  $result = null;
  if ( ! wp_doing_ajax() ) {
    $imfsPage       = get_option( 'ImfsPage' );
    $majorVersion   = ( $imfsPage !== false && isset( $imfsPage['majorVersion'] ) && is_numeric( $imfsPage['majorVersion'] ) )
      ? floatval( $imfsPage['majorVersion'] ) : index_mysql_for_speed_inception_major_version;
    $savedWpVersion = ( $imfsPage !== false && isset( $imfsPage['wp_version'] ) ) ? $imfsPage['wp_version'] : index_mysql_for_speed_inception_wp_version;
    $savedDbVersion = ( $imfsPage !== false && isset( $imfsPage['wp_db_version'] ) ) ? $imfsPage['wp_db_version'] : index_mysql_for_speed_inception_wp_db_version;
    if ( ! $imfsPage ) {
      $result = 'add';
    } else if ( $wp_db_version != $savedDbVersion ) {
      $result = 'version_update';
    } else if ( $majorVersion !== index_mysql_for_speed_major_version ) {
      $result = 'update';
    }
  }

  return $result;
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

/** @noinspection PhpIncludeInspection */
function requireThemAll( $nag = false ) {
  require_once( plugin_dir_path( __FILE__ ) . 'code/imsfdb.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'afp/admin-page-framework.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/admin.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/upload.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/querymoncontrol.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'code/notice.php' );
  new ImfsNotice ( $nag );
}

function index_wp_mysql_for_speed_activate() {
  if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ) {
    deactivate_plugins( basename( __FILE__ ) ); /* fail activation */
  }
}

function index_wp_mysql_for_speed_deactivate() {
  /* clean up emphemeral options */
  delete_option( 'imfsQueryMonitor' );
  delete_option( 'imfsQueryMonitornextMonitorUpdate' );
  delete_option( 'imfsQueryMonitorGather' );
}

/**
 * Add Settings link to this plugin's listing on the Plugins page.
 *
 * @param $actions
 *
 * @return array
 */
function index_wp_mysql_for_speed_action_link( $actions ) {
  /* translators: for settings link on plugin page */
  $name    = __( "Settings", 'index-wp-mysql-for-speed' );
  $mylinks = [
    '<a href="' . admin_url( 'tools.php?page=imfs_settings' ) . '">' . $name . '</a>',
  ];

  return array_merge( $mylinks, $actions );
}
