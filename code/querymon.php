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
	public $analyzeVerb = "EXPLAIN"; /* change to EXPLAIN to avoid ANALYZE overhead */
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
		$this->storeGathered( $optionName, $uploads, $this->gatherDelimiter, $this->queryGatherSizeLimit );

		$nextMonitorUpdate = intval( get_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' ) );
		$now               = time();
		if ( $now > $nextMonitorUpdate ) {
			$this->imfsMonitorProcess();
			update_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', $now + $this->cronInterval, true );
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
			$item->a = ! ! is_admin();
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
		$now                 = time();
		$queryLogOverflowing = false;
		$logName             = index_wp_mysql_for_speed_monitor . '-Log-' . $this->captureName;
		$queryLog            = get_option( $logName );
		if ( ! $queryLog ) {
			$queryLog          = (object) array();
			$queryLog->count   = 1;
			$queryLog->start   = $now;
			$queryLog->stop    = $now;
			$queryLog->queries = array();
		} else {
			$queryLogOverflowing = strlen( $queryLog ) > $this->queryLogSizeThreshold;
			$queryLog            = json_decode( $queryLog );
			$queryLog->queries   = (array) $queryLog->queries;
			$queryLog->count ++;
			$queryLog->stop = $now;
		}

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
		/* get queries in descending order of elapsed time */
		usort( $queries, function ( $a, $b ) {
			return $b->t - $a->t;
		} );

		foreach ( $queries as $thisQuery ) {
			try {
				$query = $thisQuery->q;
				$this->parser->setQuery( $query );
				$method = $this->parser->getMethod();
				/* don't analyze SHOW VARIABLES and other SHOW commands */
				if ( $method != 'SHOW' ) {
					$fingerprint = $this->parser->getFingerprint();
					$qid         = substr( hash( 'md5', $fingerprint, false ), 0, 16 );
					if ( array_key_exists( $qid, $queryLog->queries ) ) {
						$qe       = $queryLog->queries[ $qid ];
						$qe->n    += 1;
						$qe->t    += $thisQuery->t;
						$qe->ts[] = $thisQuery->t;
						if ( $qe->maxt < $thisQuery->t ) {
							$qe->maxt = $thisQuery->t;
							$qe->e    = $thisQuery->e; /* grab the longest-running query to report out */
							$qe->q    = $thisQuery->q;
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
		update_option( $logName, json_encode( $queryLog ), false );
	}

	function completeMonitoring() {
		$this->imfsMonitorProcess();
		delete_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' );
		delete_option( index_wp_mysql_for_speed_monitor . 'Gather' );
	}
}
