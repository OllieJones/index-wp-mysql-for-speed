<?php
/* uninstall index-wp-mysql-for-speed */
global $wpdb;

/* if uninstall.php is not called by WordPress, die */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  die;
}

/* delete settings */
delete_option( 'ImfsPage' );
/* delete saved monitors */
$q  = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'imfsQueryMonitor%'";
$rs = $wpdb->get_results( $q );
foreach ( $rs as $r ) {
  delete_option( $r->option_name );
}
