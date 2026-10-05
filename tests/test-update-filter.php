<?php
/**
 * Test the update filter
 */
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
define( 'INDEX_WP_MYSQL_FOR_SPEED_TESTING', true );
require_once( plugin_dir_path( __FILE__ ) . '../code/assets/mu/index-wp-mysql-for-speed-update-filter.php' );

dbDelta( 'all', false );
