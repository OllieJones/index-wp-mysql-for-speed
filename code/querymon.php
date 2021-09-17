<?php
require_once( 'litesqlparser.php' );

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', 1 );
}

class ImfsMonitor {

	public $thresholdMicroseconds = 100;
	public $queryLogSizeThreshold = 1048576; /* 1 MiB */
	public $queryGatherSizeLimit = 524288; /* 0.5 MiB */
	public $gatherDelimiter = "\e\036\e"; /* unlikely oldtimey ascii esc and rs */
	public $gatherExpiration = 60; /* seconds */
	public $cronInterval = 30; /* seconds, always less than $gatherExpiration */
	public $queryLogExpiration = 864000; /* ten days */
	public $parser;
	public $explainVerb = "EXPLAIN";
	public $analyzeVerb = "ANALYZE"; /* change to EXPLAIN to avoid ANALYZE overhead */

	public function __construct() {
		$this->parser = new LightSQLParser();
		add_action( 'shutdown', [ $this, 'imfsMonitorGather' ], 99 );

		add_option(index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', time() + $this->cronInterval);
	}

	function imfsMonitorGather() {
		global $wpdb;

		$transientName = index_wp_mysql_for_speed_monitor . 'Gather';

		$uploads = array();

		/* examine queries: over time threshold, not SHOW */
		foreach ( $wpdb->queries as $q ) {
			$q[1] = intval( $q[1] * 1000000 );
			if ( $q[1] >= $this->thresholdMicroseconds ) {
				$query = preg_replace( '/[\t\r\n]+/m', ' ', trim( $q[0] ) );
				if ( stripos( $query, 'SHOW ' ) === false ) {
					$q[0]    = $query;
					$encoded = $this->encodeQuery( $q );
					if ( $encoded ) {
						$uploads[] = $encoded;
					}
				}
			}
		}
		$this->storeGathered( $transientName, $uploads, $this->gatherExpiration, $this->gatherDelimiter, $this->queryGatherSizeLimit );

		$nextMonitorUpdate = intval(get_option(index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate'));
		$now = time();
		if ($now > $nextMonitorUpdate) {
			$this->imfsMonitorProcess();
			update_option(index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', $now + $this->cronInterval);
		}
	}

	function encodeQuery( $q, $explain = true ) {
		global $wpdb;
		try {
			$item    = (object) [];
			$item->q = $q[0];
			$item->t = $q[1]; /* duration in microseconds */
			//$item->c      = $q[2]; /* call traceback */
			//$item->s      = $q[3]; /* query start time */
			$item->a = ! ! is_admin(); /* 0 if front-end, 1 if admin */
			if ( $explain ) {
				$explainer = stripos( $q[0], 'SELECT ' ) === 0 ? $this->analyzeVerb : $this->explainVerb;
				$explainq  = $explainer . ' ' . $q[0];
				$item->e   = $wpdb->get_results( $explainq );
			}

			return json_encode( $item );
		} catch ( Exception $e ) {
			return null;/*  no crash on query parse fail */
		}
	}

	function storeGathered( $transient, $uploadArray, $expiration, $separator, $maxlength ) {
		$this->imfs_append_to_transient( $transient, implode( $separator, $uploadArray ), $expiration, $separator, $maxlength );
	}

	/** Upsert and append data to a WordPress transient.
	 *
	 * This function deletes the transient from the WordPress options cache to avoid stale data.
	 *
	 * @param string $transient Transient name.
	 * @param string $value Data to append.
	 * @param int $expiration Transient expiration (default 120 sec). 0 means the transient does not expire.
	 * @param string $separator Separator between appended values (default '|||').
	 * @param int $maxlength Stop appending when value reaches this length to avoid bloat (default 512 KiB).
	 */
	function imfs_append_to_transient( $transient, $value, $expiration = 120, $separator = '|||', $maxlength = 524288 ) {
		if ( ! $transient || strlen( $transient ) === 0 ) {
			return;
		}
		if ( ! $value ) {
			return;
		}
		try {
			global $wpdb;
			if ( $expiration ) {
				$name = '_transient_timeout_' . $transient;
				wp_cache_delete( $name, 'options' );
				$query = "INSERT IGNORE INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, %d, 'no')";
				$query = $wpdb->prepare( $query, $name, $expiration + time() );
				$wpdb->get_results( $query );
			}
			$name = '_transient_' . $transient;
			wp_cache_delete( $name, 'options' );
			/* INSERT ... ON DUPLICATE KEY UPDATE requires us to mention the $value twice.
			 * We can't use ON DUPLICATE KEY UPDATE col = VALUES(option_value)
			 * because Oracle MySQL deprecated it, but MariaDB didn't. Grumble.
			 * So we'll put it into a server variable just once; it can be fat. */
			$wpdb->get_results( $wpdb->prepare( "SET @upload = %s", $value ) );
			$query = "INSERT INTO $wpdb->options (option_name, option_value, autoload)"
			         . "VALUES (%s, @upload, 'no')"
			         . "ON DUPLICATE KEY UPDATE option_value ="
			         . "IF(%d > 0 AND LENGTH(option_value) <= %d - LENGTH(@upload),"
			         . "CONCAT(option_value, %s, @upload), option_value)";
			$query = $wpdb->prepare( $query, $name, $maxlength, $maxlength, $separator );
			$wpdb->get_results( $query );
		} catch ( Exception $e ) {
			/* empty, intentionally, don't crash when logging fails */
		}
	}

	function imfsMonitorProcess() {
		$queryLogOverflowing = false;
		$queryLog            = get_transient( index_wp_mysql_for_speed_monitor . 'Log' );
		if ( ! $queryLog ) {
			$queryLog          = (object) array();
			$queryLog->queries = array();
			$queryLog->count   = 1;
		} else {
			$queryLogOverflowing = strlen( $queryLog ) > $this->queryLogSizeThreshold;
			$queryLog            = json_decode( $queryLog );
			$queryLog->queries   = (array) $queryLog->queries;
			$queryLog->count ++;
		}

		$queryGather = get_transient( index_wp_mysql_for_speed_monitor . 'Gather' );
		/* tiny race condition window between get and delete here. */
		delete_transient( index_wp_mysql_for_speed_monitor . 'Gather' );

		$queries     = array();
		$queryGather = explode( $this->gatherDelimiter, $queryGather );
		foreach ( $queryGather as $q ) {
			try {
				$queries[] = json_decode( $q );
			} catch ( Exception $e ) {
				/* empty, intentionally */
			}
		}
		/* get queries in descending order of elapsed time */
		usort( $queries, function ( $a, $b ) {
			return $b->t - $a->t;
		} );

		foreach ( $queries as $q ) {
			try {
				$query = $q->q;
				$this->parser->setQuery( $query );
				$method = $this->parser->getMethod();
				/* don't analyze SHOW VARIABLES and other SHOW commands */
				if ( $method != 'SHOW' ) {
					$fingerprint = $this->parser->getFingerprint();
					$qid         = substr( hash( 'md5', $fingerprint, false ), 0, 16 );
					if ( array_key_exists( $qid, $queryLog->queries ) ) {
						$qe       = $queryLog->queries[ $qid ];
						$qe->n    += 1;
						$qe->t    += $q->t;
						$qe->tsq  += $q->t * $q->t;
						$qe->ts[] = $q->t;
						if ( $qe->maxt < $q->t ) {
							$qe->maxt = $q->t;
							$qe->e    = $q->e;
							$qe->q    = $q->q;
						}
						$qe->mint                   = $qe->t > $q->t ? $q->t : $qe->t;
						$queryLog->queries [ $qid ] = $qe;
					} else if ( ! $queryLogOverflowing ) {
						$qe                        = (object) [];
						$qe->f                     = $fingerprint;
						$qe->t                     = $q->t;
						$qe->q                     = $q->q;
						$qe->e                     = $q->e;
						$qe->ts                    = array( $q->t );
						$qe->mint                  = $q->t;
						$qe->maxt                  = $q->t;
						$qe->n                     = 1;
						$qe->a                     = $q->a;
						$queryLog->queries[ $qid ] = $qe;
					} else {
						if ( $queryLog->overflowed ) {
							$queryLog->overflowed += 1;
						} else {
							$queryLog->overflowed = 1;
						}
					}
				}
			} catch ( Exception $e ) {
				/* empty, intentionally ... no crash on query parse fail */
			}
		}
		set_transient( index_wp_mysql_for_speed_monitor . 'Log', json_encode( $queryLog ), $this->queryLogExpiration );
	}
}

new ImfsMonitor;