<?php
require_once('litesqlparser.php');

define( 'SAVEQUERIES', 1 );

add_action( 'shutdown', 'imfsMonitorGather', 99 );

function imfsquerysort( $a, $b ) {
	if ( $a[1] == $b[1] ) {
		return 0;
	}
	$d = $a[1] - $b[1];

	return $d < 0.0 ? 1 : - 1;
}

function imfsMonitorGather() {
	$parser = new LightSQLParser();
	global $wpdb;

	$queries = array();

	foreach ( $wpdb->queries as $q ) {
		$callstack = explode(',',$q[2]);
		$leaf = $callstack[count($callstack)-1];
		if ($leaf != 'xxx') {
			$query = trim( $q[0] );
			$query = preg_replace('/[\t\r\n]+/m', ' ', $query);
			$q[0] = $query;
			$queries[] = $q;
		}
	}
	/* get queries in descending order of elapsed time */
	usort( $queries, "imfsquerysort" );

	$extras = array();
	$keys = array();
	foreach ( $queries as $q ) {
		try {
			$query = $q[0];
			$parser->setQuery( $query );
			$method = $parser->getMethod();
			if ($method != 'SHOW') {
				/* don't use ANALYZE on queries that change data, because it will actually change the data. */
				$explainer = $method == 'SELECT' ? 'ANALYZE' : 'EXPLAIN';
				$explain     = $explainer . ' ' . $query;
				$explanations = $wpdb->get_results( $explain );
				foreach ($explanations as $explanation) {
					if (strlen($explanation->Extra) > 0) {
						$extras[$explanation->Extra] += 1;
					}
					$key = $explanation->table . '.' . $explanation->key;
					$keys[$key] += 1;
				}
			}
		} catch (Exception $e) {
			$q = $e;
			/* empty, intentionally ... no crash on query parse fail */
		}
	}
	$a = 1;
}