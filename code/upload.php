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
	$c       = curl_init( $target );
	curl_setopt( $c, CURLOPT_POST, true );
	curl_setopt( $c, CURLOPT_POSTFIELDS, $payload );
	curl_setopt( $c, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json' ) );
	curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
	$result = curl_exec( $c );
	curl_close( $c );
}