<?php

function imfs_upload_stats( $db, $target = index_wp_mysql_for_speed_stats_endpoint ) {
	$variables = array();
	foreach ( $db->stats[0] as $var ) {
		$variables[ $var->Variable_name ] = is_numeric( $var->Value ) ? intval( $var->Value ) : $var->Value;
	}
	$variables = (object) $variables;

	$stats = (object) array( 'semver' => $db->semver, 'variables' => $variables, 'alltables' => $db->stats[1] );

	imfs_upload_post( $stats, $target );
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
		'blocking'    => true,
		'httpversion' => '1.0',
		'sslverify'   => false,
		'data_format' => 'body',
	];

	wp_remote_post( $target, $options );
}