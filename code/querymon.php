<?php
require_once( 'litesqlparser.php' );

if ( ! defined( 'SAVEQUERIES' ) ) {
  define( 'SAVEQUERIES', true );
}

class ImfsMonitor {

  public $thresholdMicroseconds = 10;
  public $queryLogSizeThreshold = 1048576 * 2; /* 2 MiB */
  public $queryGatherSizeLimit = 1048576; /* 1 MiB */
  public $gatherDelimiter = "\e\036\e"; /* unlikely oldtimey ascii esc and rs */
  public $cronInterval = 30; /* seconds, always less than $gatherExpiration */
  public $parser;
  public $explainVerb = "EXPLAIN";
  public $captureName;
  public $monval;

  public function __construct( $monval, $action ) {
    $this->monval      = $monval;
    $this->captureName = $monval->name;
    $this->parser      = new LightSQLParser();
    if ( $action === 'capture' ) {
      add_action( 'shutdown', [ $this, 'imfsMonitorGather' ], 9999 );

      update_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', time() + $this->cronInterval, true );
    }
  }

  function imfsMonitorGather() {
    global $wpdb;

    $optionName = index_wp_mysql_for_speed_monitor . 'Gather';

    $uploads = [];
    $skipped = 0;

    /* examine queries: over time threshold, not SHOW */
    if ( isset( $wpdb->queries ) && is_array( $wpdb->queries ) && count( $wpdb->queries ) > 0 ) {
      foreach ( $wpdb->queries as $q ) {
        $q[1] = intval( $q[1] * 1000000 );
        if ( $q[1] >= $this->thresholdMicroseconds ) {
          $callTrace = $q[2];
          /* don't monitor this plugin's own queries. */
          if ( strpos( $q[0], index_wp_mysql_for_speed_querytag ) === false
               && strpos( $callTrace, 'index_wp_mysql_for_speed_monitor' ) === false
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
    }

    $nextMonitorUpdate = get_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' ) + 0;
    $now               = time();
    if ( $now > $nextMonitorUpdate ) {
      $this->processGatheredQueries();
      update_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate', $now + $this->cronInterval, true );
    }
  }

  /** make a JSON-encoded object containing a query's vital signs.
   *
   * @param array $q element in $wpdb->queries  array
   * @param bool $explain true: run EXPLAIN on the query. false: skip it.
   *
   * @return string|null
   */
  function encodeQuery( array $q, $explain = true ) {
    global $wpdb;
    try {
      $item    = (object) [];
      $item->q = $q[0];
      $item->t = $q[1]; /* duration in microseconds */
      $item->c = $q[2]; /* call traceback */
      $item->s = intval( $q[3] ); /* query start time */
      $item->a = ! ! is_admin();
      if ( $explain ) {
        $explainer = $this->explainVerb;
        /* EXPLAIN SELECT is the only explain that works in MySQL 5.5 */
        if ( stripos( $q[0], 'SELECT ' ) !== 0 && $this->monval->semver->major <= 5 && $this->monval->semver->minor <= 5 ) {
          /* do not do the EXPLAIN */
          $item->e = null;
        } else if ( stripos( $q[0], 'SET ' ) === 0 ) {
          /* do not do the EXPLAIN on SET operations */
          $item->e = null;
        } else {
          $explainq = $explainer . ' ' . $q[0];
          $item->e  = $wpdb->get_results( $this->tagQuery( $explainq ) );
        }
      }

      return json_encode( $item );
    } catch ( Exception $e ) {
      return null;/*  no crash on query parse fail */
    }
  }

  private function tagQuery( $q ) {
    $r = strval( rand( 1000000000, 9999999999 ) );

    return $q . '/*' . index_wp_mysql_for_speed_querytag . $r . '*/';
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
      /* INSERT ... ON DUPLICATE KEY UPDATE requires us to mention the $value twice.
       * We can't use ON DUPLICATE KEY UPDATE col = VALUES(option_value)
       * because Oracle MySQL deprecated it, but MariaDB didn't. Grumble.
       * So we'll put it into a server variable just once; it can be fat. */
      $wpdb->query( $wpdb->prepare( $this->tagQuery( "SET @upload = %s" ), $value ) );
      $query = "INSERT INTO $wpdb->options (option_name, option_value, autoload)"
               . "VALUES (%s, @upload, 'no')"
               . "ON DUPLICATE KEY UPDATE option_value ="
               . "IF(%d > 0 AND LENGTH(option_value) <= %d - LENGTH(@upload),"
               . "CONCAT(option_value, %s, @upload), option_value)";
      $query = $wpdb->prepare( $this->tagQuery( $query ), $name, $maxlength, $maxlength, $separator );
      $wpdb->query( $query );
      wp_cache_delete( $name, 'options' );
    } catch ( Exception $e ) {
      /* empty, intentionally, don't crash when logging fails */
    }
  }

  /**
   * process the queries gathered by imfsMonitorGather, creating / updating a queryLog
   */
  function processGatheredQueries() {
    require_once( 'getstatus.php' );
    $logName             = index_wp_mysql_for_speed_monitor . '-Log-' . $this->captureName;
    $r                   = $this->getQueryLog( $logName );
    $queryLog            = $r[0];
    $queryLogOverflowing = $r[1];
    $statusName          = index_wp_mysql_for_speed_monitor . '-Status-' . $this->captureName;
    $priorStatus         = get_transient( $statusName );
    $queryLog->status    = getGlobalStatus( $priorStatus );

    $queries = $this->getGatheredQueries();
    foreach ( $queries as $thisQuery ) {
      if ( isset( $thisQuery ) ) {
        $this->processQuery( $queryLog, $thisQuery, $queryLogOverflowing );
      }
    }
    update_option( $logName, json_encode( $queryLog ), false );
  }

  /**
   * @param $logName
   *
   * @return array
   */
  private function getQueryLog( $logName ) {
    $queryLog = get_option( $logName );
    if ( ! $queryLog ) {
      /* initialize a new monitor log object if it doesn't exist */
      $queryLogOverflowing   = false;
      $queryLog              = (object) [];
      $queryLog->gathercount = 1;
      $queryLog->querycount  = 0;
      /* initialize both ends of the time range to the start time. */
      $queryLog->start   = $this->monval->starttime;
      $queryLog->end     = $this->monval->starttime;
      /* track the minimum query time for all queries */
      $queryLog->mintime = PHP_INT_MAX;
      $queryLog->queries = [];
      /* get the key status from when the monitor storted, for later reporting */
      $monval         = get_option( index_wp_mysql_for_speed_monitor );
      $queryLog->keys = $monval->keys;
    } else {
      /* use the existing monitor log object */
      $queryLogOverflowing = strlen( $queryLog ) > $this->queryLogSizeThreshold;
      $queryLog            = json_decode( $queryLog );
      $queryLog->queries   = (array) $queryLog->queries;
      $queryLog->gathercount ++;
    }

    return [ $queryLog, $queryLogOverflowing ];
  }

  /** retrieve gathered queries.
   * @return array query objects just gathered.
   */
  private function getGatheredQueries() {
    $queryGather = $this->imfs_get_appended_option( index_wp_mysql_for_speed_monitor . 'Gather' );

    $queries = [];
    if ( isset ( $queryGather ) ) {
      $queryGather = explode( $this->gatherDelimiter, $queryGather );
      foreach ( $queryGather as $q ) {
        if ( is_string( $q ) ) {
          try {
            $queries[] = json_decode( $q );
          } catch ( Exception $e ) {
            /* empty, intentionally */
          }
        }
      }
    }

    return $queries;
  }

  /** Get the value of an option, then clear the option, in a transaction
   *
   * @param string $name of option
   *
   * @return string|null
   */
  function imfs_get_appended_option( $name ) {
    try {
      global $wpdb;
      $wpdb->query( $this->tagQuery( "START TRANSACTION" ) );
      $query  = "SELECT option_value FROM $wpdb->options WHERE option_name = %s FOR UPDATE";
      $result = $wpdb->get_var( $wpdb->prepare( $this->tagQuery( $query ), $name ) );
      $query  = "UPDATE $wpdb->options SET option_value = '' WHERE option_name = %s";
      $wpdb->query( $wpdb->prepare( $this->tagQuery( $query ), $name ) );
      $wpdb->query( $this->tagQuery( "COMMIT" ) );

      return $result;
    } catch ( Exception $e ) {
      /* don't crash when query logging fails */
      return "";
    }
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
          $qe    = $queryLog->queries[ $qid ];
          $qe->n += 1;
          $qe->t += $thisQuery->t;
          /* Keep track of the shortest query time */
          if ($queryLog->mintime > $thisQuery->t){
            $queryLog->mintime = $thisQuery->t;
          }
          /* accumulate list of times taken */
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
          $qe->ts                    = [ $thisQuery->t ];
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
    return substr( hash( 'md5', $fingerprint ), 0, 16 );
  }

  /**
   * process any left-over queries gathered by imfsMonitor and stop gathering.
   */
  function completeMonitoring() {
    $this->processGatheredQueries();
    delete_option( index_wp_mysql_for_speed_monitor . 'nextMonitorUpdate' );
    delete_option( index_wp_mysql_for_speed_monitor . 'Gather' );
    delete_transient( index_wp_mysql_for_speed_monitor . '-Status-' . $this->captureName );
  }
}
