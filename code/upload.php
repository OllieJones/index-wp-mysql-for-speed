<?php

function imfs_to_object( $rows ) {
	$variables = array();
	foreach ( $rows as $row ) {
		$variables[ $row->Variable_name ] = is_numeric( $row->Value ) ? intval( $row->Value ) : $row->Value;
	}

	return (object) $variables;
}

function get_active_plugins() {
	$plugins = get_plugins();
	$result  = array();
	foreach ( $plugins as $path => $desc ) {
		if ( is_plugin_active( $path ) ) {
			$result[] = $desc['Name'];
		}
	}

	return $result;
}

function imfs_upload_stats( $db, $target = index_wp_mysql_for_speed_stats_endpoint ) {
	global $_SERVER;
	global $wp_db_version;
	global $wp_version;
	global $required_php_version;
	global $required_mysql_version;
	$variables    = imfs_to_object( $db->stats[0] );
	$globalStatus = imfs_to_object( $db->stats[3] );

	try {
		$variables->hostname        = imfsRedactHost( $variables->hostname );
		$variables->report_host     = imfsRedactHost( $variables->report_host );
		$variables->report_password = imfsRedactHost( $variables->report_password );
		$wordpress                  = array(
			'phpversion'             => phpversion(),
			'webserverversion'       => $_SERVER['SERVER_SOFTWARE'],
			'wp_version'             => $wp_version,
			'wp_db_version'          => $wp_db_version,
			'required_php_version'   => $required_php_version,
			'required_mysql_version' => $required_mysql_version,
			'is_multisite'           => is_multisite(),
			'is_main_site'           => is_main_site(),
			'current_blog_id'        => get_current_blog_id(),
			'active_plugins'         => implode( '|', get_active_plugins() )
		);
		$stats                      = (object) array(
			'wordpress'    => $wordpress,
			'mysqlVer'     => $db->semver,
			'alltables'    => $db->stats[1],
			'timings'      => $db->timings,
			'globalStatus' => $globalStatus,
			'variables'    => $variables
		);

		imfs_upload_post( $stats, $target );
	} catch ( Error $e ) {
		/* empty, intentionally. don't croak on uploading */
	} catch ( Exception $e ) {
		/* empty, intentionally. don't croak on uploading */
	}
}

function imfs_upload_post( $stats, $target = index_wp_mysql_for_speed_stats_endpoint ) {

	$payload = json_encode( $stats );
	$options = [
		'body'        => $payload,
		'headers'     => [
			'Content-Type' => 'application/json',
		],
		'timeout'     => 60,
		'redirection' => 5,
		'blocking'    => false,
		'httpversion' => '1.0',
		'sslverify'   => false,
		'data_format' => 'body',
	];

	wp_remote_post( $target, $options );
}