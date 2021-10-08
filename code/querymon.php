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
	public $cronInterval = 30; /* seconds, always less than $gatherExpiration */
	public $parser;
	public $explainVerb = "EXPLAIN";
	public $analyzeVerb = "EXPLAIN"; /* change to EXPLAIN to avoid EXPLAIN ANALYZE overhead */
	public $captureName;

	public function __construct( $monval, $action ) {
		$this->captureName = $monval->name;
		$this->parser      = new LightSQLParser();
		if ( $action === 'capture' ) {
			add_action( 'shutdown', [ $this, 'imfsMonitorGather' ], 99 );

			update_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', time() + $this->cronInterval, true );
		}
	}

	function imfsMonitorGather() {
		global $wpdb;

		$optionName = index_wp_mysql_for_speed_monitor . 'Gather';

		$uploads = array();
		$skipped = 0;

		/* examine queries: over time threshold, not SHOW */
		foreach ( $wpdb->queries as $q ) {
			$q[1] = intval( $q[1] * 1000000 );
			if ( $q[1] >= $this->thresholdMicroseconds ) {
				$callTrace = $q[2];
				if ( strpos( $q[0], index_wp_mysql_for_speed_querytag ) === false
				     && strpos( $callTrace, 'index_wp_mysql_for_speed_do_everything' ) === false
				     && strpos( $callTrace, 'Imfs_AdminPageFramework' ) === false ) {
					$query = preg_replace( '/[\t\r\n]+/m', ' ', trim( $q[0] ) );
					if ( stripos( $query, 'SHOW ' ) === false ) {
						$q[0]    = $query;
						$encoded = $this->encodeQuery( $q );
						if ( $encoded ) {
							$uploads[] = $encoded;
						}
					}
				} else {
					$skipped ++;
				}
			}
		}
		$this->storeGathered( $optionName, $uploads, $this->gatherDelimiter, $this->queryGatherSizeLimit );

		$nextMonitorUpdate = intval( get_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' ) );
		$now               = time();
		if ( $now > $nextMonitorUpdate ) {
			$this->processGatheredQueries();
			update_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', $now + $this->cronInterval, true );
		}
	}

	/** make a JSON-encoded object containing a query's vital signs.
	 *
	 * @param array $q element in $wpdb->queries  array
	 * @param bool $explain yes, run EXPLAIN on the query. no, skip it.
	 *
	 * @return string|null
	 */
	function encodeQuery( $q, $explain = true ) {
		global $wpdb;
		try {
			$item    = (object) [];
			$item->q = $q[0];
			$item->t = $q[1]; /* duration in microseconds */
			$item->c = $q[2]; /* call traceback */
			$item->s = intval( $q[3] ); /* query start time */
			$item->a = ! ! is_admin();
			if ( $explain ) {
				$explainer = stripos( $q[0], 'SELECT ' ) === 0 ? $this->analyzeVerb : $this->explainVerb;
				$explainq  = $explainer . ' ' . $q[0];
				$item->e   = $wpdb->get_results( index_wp_mysql_for_speed_querytag . $explainq );
			}

			return json_encode( $item );
		} catch ( Exception $e ) {
			return null;/*  no crash on query parse fail */
		}
	}

	function storeGathered( $name, $uploadArray, $separator, $maxlength ) {
		$this->imfs_append_to_option( $name, implode( $separator, $uploadArray ), $separator, $maxlength );
	}

	/** Upsert and append data to a WordPress option.
	 *
	 * This function deletes the option from the WordPress options cache to avoid stale data.
	 *
	 * @param string $name option name.
	 * @param string $value Data to append.
	 * @param string $separator Separator between appended values (default '|||').
	 * @param int $maxlength Stop appending when value reaches this length to avoid bloat (default 512 KiB).
	 */
	function imfs_append_to_option( $name, $value, $separator = '|||', $maxlength = 524288 ) {
		if ( ! $name || strlen( $name ) === 0 ) {
			return;
		}
		if ( ! $value ) {
			return;
		}
		try {
			global $wpdb;
			wp_cache_delete( $name, 'options' );
			/* INSERT ... ON DUPLICATE KEY UPDATE requires us to mention the $value twice.
			 * We can't use ON DUPLICATE KEY UPDATE col = VALUES(option_value)
			 * because Oracle MySQL deprecated it, but MariaDB didn't. Grumble.
			 * So we'll put it into a server variable just once; it can be fat. */
			$wpdb->query( $wpdb->prepare( index_wp_mysql_for_speed_querytag . "SET @upload = %s", $value ) );
			$query = "INSERT INTO $wpdb->options (option_name, option_value, autoload)"
			         . "VALUES (%s, @upload, 'no')"
			         . "ON DUPLICATE KEY UPDATE option_value ="
			         . "IF(%d > 0 AND LENGTH(option_value) <= %d - LENGTH(@upload),"
			         . "CONCAT(option_value, %s, @upload), option_value)";
			$query = $wpdb->prepare( index_wp_mysql_for_speed_querytag . $query, $name, $maxlength, $maxlength, $separator );
			$wpdb->query( $query );
		} catch ( Exception $e ) {
			/* empty, intentionally, don't crash when logging fails */
		}
	}

	/**
	 * process the queries gathered by imfsMonitorGather, creating / updating a queryLog
	 */
	function processGatheredQueries() {
		$logName = index_wp_mysql_for_speed_monitor . '-Log-' . $this->captureName;
		list( $queryLog, $queryLogOverflowing ) = $this->getQueryLog( $logName );

		$queries = $this->getGatheredQueries();
		foreach ( $queries as $thisQuery ) {
			$this->processQuery( $queryLog, $thisQuery, $queryLogOverflowing );
		}
		update_option( $logName, json_encode( $queryLog ), false );
	}

	/**
	 * @param $logName
	 * @param $queryLogOverflowing
	 * @param $now
	 *
	 * @return array
	 */
	private function getQueryLog( $logName ) {
		$queryLog = get_option( $logName );
		if ( ! $queryLog ) {
			$queryLogOverflowing   = false;
			$queryLog              = (object) array();
			$queryLog->gathercount = 1;
			$queryLog->querycount  = 0;
			$queryLog->start       = PHP_INT_MAX;
			$queryLog->end         = PHP_INT_MIN;
			$queryLog->queries     = array();
		} else {
			$queryLogOverflowing = strlen( $queryLog ) > $this->queryLogSizeThreshold;
			$queryLog            = json_decode( $queryLog );
			$queryLog->queries   = (array) $queryLog->queries;
			$queryLog->gathercount ++;
		}

		return array( $queryLog, $queryLogOverflowing );
	}

	/** retrieve gathered queries.
	 * @return array query objects just gathered.
	 */
	private function getGatheredQueries() {
		$queryGather = get_option( index_wp_mysql_for_speed_monitor . 'Gather' );
		/* tiny race condition window between get and delete here. */
		delete_option( index_wp_mysql_for_speed_monitor . 'Gather' );

		$queries     = array();
		$queryGather = explode( $this->gatherDelimiter, $queryGather );
		foreach ( $queryGather as $q ) {
			try {
				$queries[] = json_decode( $q );
			} catch ( Exception $e ) {
				/* empty, intentionally */
			}
		}

		return $queries;
	}

	/** Insert a query into the $queryLog
	 *
	 * @param object $queryLog
	 * @param object $thisQuery
	 * @param bool $queryLogOverflowing
	 */
	private function processQuery( $queryLog, $thisQuery, $queryLogOverflowing ) {
		try {
			/* track time range of items in this queryLog */
			if ( $queryLog->start > $thisQuery->s ) {
				$queryLog->start = $thisQuery->s;
			}
			if ( $queryLog->end < $thisQuery->s ) {
				$queryLog->end = $thisQuery->s;
			}
			$queryLog->querycount ++;
			$query = $thisQuery->q;
			$this->parser->setQuery( $query );
			$method = $this->parser->getMethod();
			/* don't analyze SHOW VARIABLES and other SHOW commands */
			if ( $method != 'SHOW' ) {
				$fingerprint = $this->parser->getFingerprint();
				$qid         = self::getQueryId( $thisQuery->a . $fingerprint );
				if ( array_key_exists( $qid, $queryLog->queries ) ) {
					$qe       = $queryLog->queries[ $qid ];
					$qe->n    += 1;
					$qe->t    += $thisQuery->t;
					$qe->ts[] = $thisQuery->t;
					if ( $qe->maxt < $thisQuery->t ) {
						$qe->maxt = $thisQuery->t;
						$qe->e    = $thisQuery->e; /* grab the longest-running query to report out */
						$qe->q    = $thisQuery->q;
						$qe->c    = $thisQuery->c;
					}
					if ( $thisQuery->t < $qe->mint ) {
						$qe->mint = $thisQuery->t;
					}
					$queryLog->queries [ $qid ] = $qe;
				} else if ( ! $queryLogOverflowing ) {
					$qe                        = (object) [];
					$qe->f                     = $fingerprint;
					$qe->a                     = $thisQuery->a;
					$qe->n                     = 1;
					$qe->t                     = $thisQuery->t;
					$qe->mint                  = $thisQuery->t;
					$qe->maxt                  = $thisQuery->t;
					$qe->e                     = $thisQuery->e;
					$qe->ts                    = array( $thisQuery->t );
					$qe->q                     = $thisQuery->q;
					$qe->c                     = $thisQuery->c;
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

	/** Get a queryId, a hash, for a query fingerprint
	 *
	 * @param string $fingerprint query fingerprint like SELECT col, col FROM tbl WHERE col = ?i?
	 *
	 * @return false|string
	 */
	private static function getQueryId( $fingerprint ) {
		return substr( hash( 'md5', $fingerprint, false ), 0, 16 );
	}

	/**
	 * process any left-over queries gathered by imfsMonitor and stop gathering.
	 */
	function completeMonitoring() {
		$this->processGatheredQueries();
		delete_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' );
		delete_option( index_wp_mysql_for_speed_monitor . 'Gather' );
	}
}
