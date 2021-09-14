<?php
require_once( 'litesqlparser.php' );

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', 1 );
}

add_action( 'shutdown', 'imfsMonitorGather', 99 );

function imfsMonitorGather() {
	$time_threshold = 0.00001;
	$queryLogSizeThreshold = 1024 * 1024 * 4;
	$parser         = new LightSQLParser();
	global $wpdb;

	$queryLogOverflowing = false;
	$queryLog            = get_transient( index_wp_mysql_for_speed_monitor . 'Log' );
	if ( ! $queryLog ) {
		$queryLog = array();
	} else {
		$queryLogOverflowing = strlen( $queryLog ) > $queryLogSizeThreshold;
		$queryLog            = json_decode( $queryLog );
	}

	$queries = array();

	foreach ( $wpdb->queries as $q ) {
		if ( $q[1] < $time_threshold ) {
			continue;
		}
		$query     = trim( $q[0] );
		$query     = preg_replace( '/[\t\r\n]+/m', ' ', $query );
		$q[0]      = $query;
		$queries[] = $q;
	}
	/* get queries in descending order of elapsed time */
	usort( $queries, function ( $a, $b ) {
		if ( $a[1] == $b[1] ) {
			return 0;
		}
		$d = $a[1] - $b[1];

		return $d < 0.0 ? 1 : - 1;
	} );

	$extras = array();
	$keys   = array();
	foreach ( $queries as $q ) {
		try {
			$caller = '';
			$query  = $q[0];
			$parser->setQuery( $query );
			$method = $parser->getMethod();
			/* don't analyze SHOW VARIABLES and other SHOW commands */
			if ( $method != 'SHOW' ) {
				/* don't use ANALYZE on queries that change data, because it will actually change the data. */
				$explainer       = $method == 'SELECT' ? 'ANALYZE' : 'EXPLAIN';
				$explain         = $explainer . ' ' . $query;
				$explanations    = $wpdb->get_results( $explain );
				$logEntry        = (object) [];
				$qid             = substr( hash( 'md5', $query, false ), 0, 12 );
				$logEntry->q     = $query;
				$logEntry->t     = $q[1];
				$logEntry->n     = 1;
				$logEntry->admin = !!is_admin();
				$logEntry->exp   = $explanations;
				if ( $queryLog->{$qid} ) {
					$queryLog->{$qid}->n     += 1;
					$queryLog->{$qid}->t     += $logEntry->t;
					$queryLog->{$qid}->admin += $logEntry->admin;
					$queryLog->{$qid}->exp   += $logEntry->exp;
				} else if ( ! $queryLogOverflowing ) {
					$queryLog[ $qid ] = $logEntry;
				} else {
					if ($queryLog->overflowed) $queryLog->overflowed += 1;
					else $queryLog->overflowed = 1;
				}
			}
		} catch ( Exception $e ) {
			/* empty, intentionally ... no crash on query parse fail */
		}
	}
	set_transient( index_wp_mysql_for_speed_monitor . 'Log', json_encode( $queryLog ), 60 * 60 * 24 * 7 );
}