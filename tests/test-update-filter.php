<?php
/**
 * Test the update filter
 */

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
require_once( plugin_dir_path( __FILE__ ) . '../code/assets/mu/index-wp-mysql-for-speed-update-filter.php' );

dbDelta( 'all', false );
