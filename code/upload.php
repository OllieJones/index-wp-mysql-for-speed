<?php

function randomString( $length ) {
	/* some characters removed from this set to reduce confusion reading aloud */
	$characters       = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXYZ';
	$charactersLength = strlen( $characters );
	$randomString     = '';
	for ( $i = 0; $i < $length; $i ++ ) {
		$randomString .= $characters[ rand( 0, $charactersLength - 1 ) ];
	}

	return $randomString;
}

function imfsToObject( $rows ) {
	$variables = array();
	foreach ( $rows as $row ) {
		$variables[ $row->Variable_name ] = is_numeric( $row->Value ) ? intval( $row->Value ) : $row->Value;
	}

	return (object) $variables;
}

function imfsToResultSet( $rows, $nameCaption = 'Item', $valueCaption = 'Value' ) {
	$res = array();
	foreach ( $rows as $name => $value ) {
		$rsrow = array( $nameCaption => $name, $valueCaption => $value );
		$res[] = $rsrow;
	}
	return $res;
}

function getActivePlugins() {
	$plugins = get_plugins();
	$result  = array();
	foreach ( $plugins as $path => $desc ) {
		if ( is_plugin_active( $path ) ) {
			$result[] = $desc['Name'];
		}
	}

	return $result;
}

function imfsGetWpDescription( $db ) {
	global $wp_db_version;
	global $wp_version;
	global $required_php_version;
	global $required_mysql_version;
	global $_SERVER;
	/** @noinspection PhpUnnecessaryLocalVariableInspection */
	$wordpress = array(
		'webserverversion'       => $_SERVER['SERVER_SOFTWARE'],
		'wp_version'             => $wp_version,
		'wp_db_version'          => $wp_db_version,
		'phpversion'             => phpversion(),
		'required_php_version'   => $required_php_version,
		'mysqlversion'           => $db->semver->version,
		'required_mysql_version' => $required_mysql_version,
		'pluginversion'          => index_wp_mysql_for_speed_VERSION_NUM,
		'is_multisite'           => is_multisite(),
		'is_main_site'           => is_main_site(),
		'current_blog_id'        => get_current_blog_id(),
		'active_plugins'         => implode( '|', getActivePlugins() )
	);

	return $wordpress;
}

function imfsGetAllStats( $db, $idString ) {
	global $_SERVER;
	$variables    = imfsToObject( $db->stats[0] );
	$globalStatus = imfsToObject( $db->stats[3] );

	$variables->hostname        = imfsRedactHost( $variables->hostname );
	$variables->report_host     = imfsRedactHost( $variables->report_host );
	$variables->report_password = imfsRedactHost( $variables->report_password );
	$wordpress                  = imfsGetWpDescription( $db );
	/** @noinspection PhpUnnecessaryLocalVariableInspection */
	$stats = (object) array(
		'id'           => $idString,
		'wordpress'    => $wordpress,
		'mysqlVer'     => $db->semver,
		'alltables'    => $db->stats[1],
		//'timings'      => $db->timings,
		'globalStatus' => $globalStatus,
		'variables'    => $variables
	);

	return $stats;

}

function imfs_upload_stats( $db, $target = index_wp_mysql_for_speed_stats_endpoint ) {

	$idString = randomString( 10 );
	try {
		$stats = imfsGetAllStats( $db, $idString );
		imfs_upload_post( $stats, $target );
	} catch ( Exception $e ) {
		/* empty, intentionally. don't croak on uploading */
	}

	return $idString;
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