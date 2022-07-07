<?php /** @noinspection PhpRedundantOptionalArgumentInspection */

/**
 * Draws the contents of a captured monitor
 */
class renderMonitor {

  private $monitor;
  private $db;
  private $queryLog;
  private $prefix;
  private $classPrefix = 'rendermon';
  private $dateFormat;
  private $maxStringLength = 12;

  public function __construct( $monitor, $db ) {
    $this->monitor    = $monitor;
    $this->db         = $db;
    $this->prefix     = index_wp_mysql_for_speed_monitor . '-Log-';
    $this->dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
  }

  static function renderMonitors( $list, $part, $db ) {
    $renders  = [];
    $monitors = RenderMonitor::getMonitors();

    foreach ( $monitors as $monitor ) {
      if ( is_null( $list )
           || ( is_string( $list ) && $monitor === $list )
           || ( is_array( $list ) && in_array( $monitor, $list ) ) ) {
        $rm        = new RenderMonitor( $monitor, $db );
        $renders[] = $rm->render( $part );
      }
    }

    return implode( "\r", $renders );
  }

  /** Retrieve a list of saved monitors
   * @return array
   */
  static function getMonitors() {
    global $wpdb;
    $prefix = index_wp_mysql_for_speed_monitor . '-Log-';
    $result = [];
    $q      = "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '" . $prefix . "%' AND LENGTH(option_value) > 0";
    $rs     = $wpdb->get_results( $q );
    foreach ( $rs as $r ) {
      $name     = str_replace( $prefix, '', $r->option_name );
      $result[] = $name;
    }

    return $result;
  }

  /** Render the monitor to a string
   *
   * @param string $part 'top' or 'bottom'
   *
   * @return string
   */
  function render( $part ) {
    $this->load();
    $c      = $this->classPrefix;
    $prefix = "<div class=\"$c index-wp-mysql-for-speed-content-container\">";
    $suffix = "</div>";
    if ( $part === 'top' ) {
      return $prefix . "<div>" . $this->top() . "</div>";
    } else if ( $part === 'bottom' ) {
      return $this->table() . $suffix;
    }

    return '';
  }

  /** Load the monitor
   */
  public function load() {
    $this->queryLog          = json_decode( get_option( $this->prefix . $this->monitor ) );
    $this->queryLog->queries = (array) $this->queryLog->queries;

    return $this;
  }

  /** Render the header part of the monitor
   * @return string
   */
  public function top() {
    $c           = $this->classPrefix;
    $times       = $this->capturedQuerySummary();
    $dbSumm      = $this->dbStatusSummary();
    $exhortation = __( "Please consider uploading your saved monitor! Learning from our users' monitors is how we improve the plugin.", 'index-wp-mysql-for-speed' );

    return <<<END
    <div class="$c capture-header">
		<h1 class="$c h1">$this->monitor</h1>
		<div class="$c top time">$times</div>
		<div class="$c top summary">$dbSumm</div>
		<div class="$c top exhort">$exhortation</div>
    </div>
END;
  }

  public function capturedQuerySummary() {
    $l          = $this->queryLog;
    $c          = $this->classPrefix;
    $start      = wp_date( $this->dateFormat, $l->start );
    $end        = wp_date( $this->dateFormat, $l->end );
    $duration   = $this->timeCell( 1000000 * ( $l->end - $l->start ) );
    $querycount = number_format_i18n( $l->querycount, 0 );
    $capString  = __( 'queries captured.', 'index-wp-mysql-for-speed' );
    $result     = <<<END
		<span class="$c start">$start</span>―<span class="$c end">$end</span> <span class="$c count">(${duration[0]})</span>&emsp;<span class="$c count">$querycount</span>
END;

    return $result . ' ' . $capString;
  }

  /** get cell data for microsecond times
   *
   * @param number $time in microseconds (multiply seconds by a million)
   * @param null $unit
   * @param string $prefix
   *
   * @return array
   */
  public function timeCell( $time, $unit = null, $prefix = '' ) {
    if ( $time === 0.0 ) {
      $displayTime = $prefix !== '' ? '' : '0';

      return [ $displayTime, '0' ];
    }
    $renderTime = $time * 0.000001;
    if ( $unit === null ) {
      $unit = $this->getTimeUnit( $renderTime );
    }
    $displayTime = $prefix . number_format_i18n( $renderTime / $unit[0], $unit[2] ) . $unit[1];

    return [ $displayTime, - $renderTime ];
  }

  public function getTimeUnit( $timeSeconds ) {
    if ( $timeSeconds >= 86400 ) {
      $unit = [ 86400, 'd', 1 ];
    } else if ( $timeSeconds >= 3600 * 0.9 ) {
      $unit = [ 3600, 'h', 1 ];
    } else if ( $timeSeconds >= 60 * 0.9 ) {
      $unit = [ 60, 'm', 1 ];
    } else if ( $timeSeconds >= 0.9 ) {
      $unit = [ 1, 's', 2 ];
    } else if ( $timeSeconds >= 0.1 ) {
      $unit = [ 0.001, 'ms', 0 ];
    } else if ( $timeSeconds >= 0.01 ) {
      $unit = [ 0.001, 'ms', 1 ];
    } else if ( $timeSeconds >= 0.001 ) {
      $unit = [ 0.001, 'ms', 2 ];
    } else {
      $unit = [ 0.000001, 'μs', 0 ];
    }

    return $unit;
  }

  public function dbStatusSummary() {
    global $wpdb;
    $l = $this->queryLog;
    $c = $this->classPrefix;
    if ( ! isset ( $l->status ) ) {
      return null;
    }
    $dt = $l->end - $l->start;
    if ( ! $dt ) {
      return null;
    }
    $status = $l->status;

    /* server state */
    $result = "<div class=\"$c top line\">";
    $pool   = $wpdb->get_results( "SHOW GLOBAL VARIABLES LIKE 'innodb_buffer_pool_size' " );
    $pool   = $pool[0]->Value;
    $result .= $this->getServerUptime( $status );
    $result .= $this->getBufferPool( $status, $pool );
    $result .= $this->getRam( $status );
    $result .= $this->getThreads( $status );
    $result .= __( 'Version:', 'index-wp-mysql-for-speed' ) . ' ';
    $result .= $this->db->semver->version;

    $result .= "</div><div class=\"$c top line indent\">";

    $failedConnections = $this->getFailed_connections( $status );
    $goodConnections   = ( isset( $status->Connections ) ? $status->Connections : 0 ) - $failedConnections;
    $goodCps           = number_format_i18n( $goodConnections / $dt, 2 );
    $failedCps         = number_format_i18n( $failedConnections / $dt, 2 );

    $result .= "<span class=\"$c count\">$goodCps</span> ";
    $result .= __( "Connections/s", 'index-wp-mysql-for-speed' ) . '&ensp;';
    if ( $failedConnections > 0 ) {
      $result .= "<span class=\"$c count\">$failedCps</span> ";
      $result .= __( "Failed connections/s", 'index-wp-mysql-for-speed' ) . '&ensp;';
    }

    if ( ( isset( $status->Aborted_clients ) ? $status->Aborted_clients : 0 ) > 0 ) {
      $abruptDisconnects = $status->Aborted_clients;
      $abruptDps         = number_format_i18n( $abruptDisconnects / $dt, 2 );
      $result            .= "<span class=\"$c count\">$abruptDps</span> ";
      $result            .= __( "Abrupt disconnections/s", 'index-wp-mysql-for-speed' ) . '&ensp;';
    }

    /* Queries from clients (not counting those within stored code */
    $qps    = number_format_i18n( ( isset( $status->Questions ) ? $status->Questions : 0 ) / $dt, 2 );
    $result .= "<span class=\"$c count\">$qps</span> ";
    $result .= __( "Queries/s", 'index-wp-mysql-for-speed' ) . '&ensp;';

    $result .= "</div><div class=\"$c top line indent\">";

    /* net traffic to and from database server */
    $dataSent     = $this->byteCell( ( isset( $status->Bytes_sent ) ? $status->Bytes_sent : 0 ) / $dt );
    $dataReceived = $this->byteCell( ( isset( $status->Bytes_received ) ? $status->Bytes_received : 0 ) / $dt );
    $result       .= __( 'Net:', 'index-wp-mysql-for-speed' ) . ' ';
    $result       .= "<span class=\"$c count\">$dataReceived/s</span> ";
    $result       .= __( "received", 'index-wp-mysql-for-speed' ) . ' ';
    $result       .= "<span class=\"$c count\">$dataSent/s</span> ";
    $result       .= __( "sent", 'index-wp-mysql-for-speed' ) . '&ensp;';

    $result .= "</div><div class=\"$c top line indent\">";

    $dataRead = $this->byteCell( ( isset( $status->Innodb_data_read ) ? $status->Innodb_data_read : 0 ) / $dt );
    $result   .= __( 'IO:', 'index-wp-mysql-for-speed' ) . ' ';
    $result   .= "<span class=\"$c count\">$dataRead/s</span> ";
    $result   .= __( "read", 'index-wp-mysql-for-speed' ) . ' ';

    $rows = __( "rows", 'index-wp-mysql-for-speed' );
    if ( ( isset( $status->Innodb_rows_sent ) ? $status->Innodb_rows_sent : 0 ) > 0 ) {
      $rowsSent = number_format_i18n( $status->Innodb_rows_sent / $dt, 2 ) . ' ' . $rows;
      $result   .= "<span class=\"$c count\">$rowsSent/s</span> ";
      $result   .= __( "fetched", 'index-wp-mysql-for-speed' ) . ' ';
    }
    if ( ( isset( $status->Innodb_data_written ) ? $status->Innodb_data_written : 0 ) > 0 ) {
      $dataWritten = $this->byteCell( $status->Innodb_data_written / $dt );
      $result      .= "<span class=\"$c count\">$dataWritten/s</span> ";
      $result      .= __( "written", 'index-wp-mysql-for-speed' ) . '&ensp;';
    }
    $rowsRead = isset( $status->Innodb_rows_read ) ? $status->Innodb_rows_read : 0;
    $rowsRead = number_format_i18n( $rowsRead / $dt, 2 ) . ' ' . $rows;
    $result   .= "<span class=\"$c count\">$rowsRead/s</span> ";
    $result   .= __( "retrieved", 'index-wp-mysql-for-speed' ) . ' ';

    $rowcount      = $this->getRowsStored( $status );
    $rowsProcessed = round( $rowcount / $dt, 2 ) . ' ' . $rows;
    $result        .= "<span class=\"$c count\">$rowsProcessed/s</span> ";
    $result        .= __( "stored", 'index-wp-mysql-for-speed' ) . ' ';

    $result .= "</div>";

    if ( isset ( $l->mintime ) && $l->mintime < PHP_INT_MAX ) {
      $result        .= "<div class=\"$c top line indent\">";
      $nullQueryTime = $this->timeCell( $l->mintime );
      $result        .= __( 'Shortest Query Time:', 'index-wp-mysql-for-speed' ) . ' ';
      $result        .= "<span class=\"$c count\">$nullQueryTime[0]</span> ";
      $result        .= '</div>';
    }
    return $result;
  }

  /** get cell data for byte counts
   *
   * @param number $bytes
   * @param null $unit
   * @param string $prefix
   *
   * @return string
   */
  public function byteCell( $bytes, $unit = null, $prefix = '' ) {
    if ( $bytes === 0.0 ) {
      return $prefix !== '' ? '' : '0';
    }
    if ( $unit === null ) {
      $unit = $this->getByteUnit( $bytes );
    }

    return $prefix . number_format_i18n( $bytes / $unit[0], $unit[2] ) . $unit[1];
  }

  public function getByteUnit( $bytes ) {
    if ( $bytes >= 1024 * 1024 * 1024 * 1024 ) {
      $unit = [ 1024 * 1024 * 1024 * 1024, 'TiB', 0 ];
    } else if ( $bytes >= 1024 * 1024 * 1024 * 1024 * 0.5 ) {
      $unit = [ 1024 * 1024 * 1024 * 1024, 'TiB', 1 ];
    } else if ( $bytes >= 1024 * 1024 * 1024 ) {
      $unit = [ 1024 * 1024 * 1024 * 1024, 'GiB', 0 ];
    } else if ( $bytes >= 1024 * 1024 * 1024 * 0.5 ) {
      $unit = [ 1024 * 1024 * 1024, 'GiB', 1 ];
    } else if ( $bytes >= 1024 * 1024 ) {
      $unit = [ 1024 * 1024, 'MiB', 0 ];
    } else if ( $bytes >= 1024 * 1024 * 0.5 ) {
      $unit = [ 1024 * 1024, 'MiB', 1 ];
    } else if ( $bytes >= 1024 ) {
      $unit = [ 1024, 'KiB', 0 ];
    } else if ( $bytes >= 1024 * 0.1 ) {
      $unit = [ 1024, 'KiB', 1 ];
    } else {
      $unit = [ 1, 'B', 0 ];
    }

    return $unit;
  }

  /**
   * @param $status
   *
   * @return int
   */
  private function getFailed_connections( $status ) {
    return ( isset( $status->Connection_errors_accept ) ? $status->Connection_errors_accept : 0 )
           + ( isset( $status->Connection_errors_internal ) ? $status->Connection_errors_internal : 0 )
           + ( isset( $status->Connection_errors_max_connections ) ? $status->Connection_errors_max_connections : 0 )
           + ( isset( $status->Connection_errors_peer_address ) ? $status->Connection_errors_peer_address : 0 )
           + ( isset( $status->Connection_errors_select ) ? $status->Connection_errors_select : 0 )
           + ( isset( $status->Connection_errors_tcpwrap ) ? $status->Connection_errors_tcpwrap : 0 );
  }

  /**
   * @param $status
   *
   * @return int
   */
  private function getRowsStored( $status ) {
    $rowcount = 0;
    $rowcount += isset( $status->Innodb_rows_deleted ) ? $status->Innodb_rows_deleted : 0;
    $rowcount += isset( $status->Innodb_rows_inserted ) ? $status->Innodb_rows_inserted : 0;
    $rowcount += isset( $status->Innodb_rows_updated ) ? $status->Innodb_rows_updated : 0;

    return $rowcount;
  }

  public function table() {
    $l   = $this->queryLog;
    $c   = $this->classPrefix;
    $res = '';
    $row = $this->row( [
      "Where",
      "Count",
      "Total",
      "Mean",
      "Spread",
      "P95",
      "Query Pattern",
      "Plan",
      "Traceback",
      "Actual",
    ], "query header row" );
    $res .= <<<END
		<div class="$c query table-container"><table class="$c query table"><thead>
		<tr>$row</tr></thead><tbody>
END;

    foreach ( $l->queries as $q ) {
      if ( $q->n > 0 && ! is_null( $q->f ) ) {
        $row   = [];
        $row[] = $q->a ? [ "Dashboard", 1 ] : [ "Site", 0 ];
        $row[] = [ number_format_i18n( $q->n, 0 ), $q->n ];
        $row[] = $this->timeCell( $q->t );
        $mean  = $this->mean( $q->ts );
        $unit  = $this->getTimeUnit( $mean * 0.000001 );
        $row[] = $this->timeCell( $mean, $unit );
        $row[] = $this->timeCell( $this->mad( $q->ts ), $unit, '±' );
        $row[] = $this->timeCell( $this->percentile( $q->ts, 0.95 ), $unit );
        $row[] = $q->f;
        $row[] = $this->queryPlan( $q );
        $row[] = $q->c;
        $row[] = $q->q;
        $res   .= "</tr>" . $this->row( $row, "query data row" ) . "</tr>";
      }
    }

    $res .= "</tbody></table></div>";

    return $res;
  }

  public function row( $a, $class = 'row' ) {
    $res = '';
    foreach ( $a as $item ) {
      $res .= $this->cell( $item, $class );
    }

    return $res;
  }

  public function cell( $item, $class ) {
    $c = $this->classPrefix;
    if ( is_string( $item ) ) {
      $item = htmlspecialchars( $item );

      return <<<END
		<td class="$c $class">$item</td>
END;
    }
    if ( is_numeric( $item ) ) {
      return <<<END
		<td class="$c $class number">$item</td>
END;
    }
    if ( is_array( $item ) && count( $item ) === 2 ) {
      return <<<END
		<td class="$c $class" data-order="$item[1]">$item[0]</td>
END;
    }
    if ( is_array( $item ) ) {
      $item = htmlspecialchars( implode( '', $item ) );

      return <<<END
		<td class="$c $class">$item</td>
END;
    }

    return <<<END
		<td class="$c $class"></td>
END;
  }

  /** arithmetic mean
   *
   * @param array $a dataset
   *
   * @return number
   */
  public function mean( array $a ) {
    $n = count( $a );
    if ( ! $n ) {
      return null;
    }
    if ( $n === 1 ) {
      return $a[0];
    }
    $acc = 0;
    foreach ( $a as $v ) {
      $acc += $v;
    }

    return $acc / $n;
  }

  /** mean absolute deviation
   *
   * @param array $a dataset
   *
   * @return float|int|null
   */
  public function mad( array $a ) {
    $n = count( $a );
    if ( ! $n ) {
      return null;
    }
    if ( $n === 1 ) {
      return 0.0;
    }
    $acc = 0;
    foreach ( $a as $v ) {
      $acc += $v;
    }
    $mean = $acc / $n;
    $acc  = 0;
    foreach ( $a as $v ) {
      $acc += abs( $v - $mean );
    }

    return $acc / $n;
  }

  /** percentile
   *
   * @param array $a dataset
   * @param number $p percentile as fraction 0-1
   *
   * @return float
   */
  public function percentile( array $a, $p ) {
    $n = count( $a );
    sort( $a );
    $i = floor( $n * $p );
    if ( $i >= $n ) {
      $i = $n - 1;
    }

    return $a[ $i ];
  }

  /** retrieve a short summary query plan
   *
   * @param $q object the query
   *
   * @return mixed|string
   */
  public function queryPlan( $q ) {
    if ( ! $q->e || ! is_array( $q->e ) || count( $q->e ) === 0 ) {
      return '';
    }
    $erow   = $q->e[0];
    $extras = $erow->Extra;
    if ( false !== stripos( $extras, 'impossible where' ) ) {
      return $extras;
    } else if ( false !== stripos( $extras, 'no tables' ) ) {
      return $extras;
    }

    $expl   = [];
    $expl[] = $erow->table;

    $type = $erow->select_type;
    if ( false === stripos( $type, 'SIMPLE' ) ) {
      $expl[] = $type . ':';
    }

    if ( $erow->key ) {
      $expl[] = '(' . $erow->key . ')';
    }
    if ( $erow->type && $erow->ref ) {
      $expl[] = "[" . $erow->type . ":" . $erow->ref . "]";
    } else if ( $erow->type ) {
      $expl[] = "[" . $erow->type . "]";
    } else if ( $erow->ref ) {
      $expl[] = "[" . $erow->type . "]";
    }
    foreach ( explode( ";", $erow->Extra ) as $extra ) {
      $extra = trim( $extra );
      if ( false !== stripos( $extra, "using where" ) ) {
        $expl[] = "Where" . ';';
      } else if ( false !== stripos( $extra, "using index condition" ) ) {
        $expl[] = "Index Condition" . ';';
      } else if ( false !== stripos( $extra, "using index" ) ) {
        $expl[] = "Covering index" . ';';
      } else if ( false !== stripos( $extra, "using temporary" ) ) {
        $expl[] = "Temp" . ';';
      } else if ( false !== stripos( $extra, "using filesort" ) ) {
        $expl[] = "Sort" . ';';
      } else if ( strlen( $extra ) > 0 ) {
        $expl[] = $extra . ';';
      }
    }

    return implode( " ", $expl );
  }

  static function deleteMonitor( $monitor ) {
    $prefix = index_wp_mysql_for_speed_monitor . '-Log-';
    delete_option( $prefix . $monitor );
  }

  public function makeUpload() {
    require_once( 'litesqlparser.php' );
    $parser  = new LightSQLParser();
    $l       = $this->queryLog;
    $qs      = [];
    $queries = $l->queries;

    /* sort queries in descending order of total time */
    uasort( $queries, function ( $a, $b ) {
      $a = $a->t;
      $b = $b->t;
      /* don't forget, in php these compare functions
       * must return -1, 0, or 1, not any signed number */
      if ( $a === $b ) {
        return 0;
      }

      /* descending order sort */

      return $a < $b ? 1 : - 1;
    } );
    $counter = 0;
    foreach ( $queries as $query ) {
      /* only the slowest queries */
      if ( $counter ++ >= 25 ) {
        $qs[] = 'Fastest ' . ( count( $queries ) - $counter ) . ' queries omitted.';
        break;
      }
      $parser->setQuery( $query->q );
      $shortened = $parser->getShortened();
      $traceback = $query->c;
      $traceback = preg_replace( '/\s+/', '', $traceback );
      if ( strlen( $traceback ) > 240 ) {
        $traceback = substr( $traceback, 0, 120 ) . '...' . substr( $traceback, - 117 );
      }

      $q = [
        'f' => $query->f,
        'a' => $query->a,
        'p' => $this->queryPlan( $query ),
        'n' => $query->n,
        't' => $query->t,
        'q' => $shortened,
        'c' => $traceback,
      ];
      if ( $query->n > 1 ) {
        $q['avg'] = round( $query->t / $query->n, 0 );
        $q['p95'] = round( $this->percentile( $query->ts, 0.95 ), 0 );
        $q['mad'] = round( $this->mad( $query->ts ), 0 );
        $q['std'] = round( $this->stdev( $query->ts ), 0 );
      }
      $qs[] = $q;
    }

    return [
      'id'      => '',
      'monitor' => $this->monitor,
      'start'   => $l->start,
      'end'     => $l->end,
      'stats'   => $this->getDbStatistics(),
      'keys'    => $l->keys,
      'queries' => $qs,
    ];
  }

  /** standard deviation
   *
   * @param array $a dataset
   *
   * @return float|null
   * @noinspection PhpUnused
   */
  public function stdev( $a ) {
    $n = count( $a );
    if ( ! $n ) {
      return null;
    }
    if ( $n === 1 ) {
      return 0.0;
    }
    $sum   = 0.0;
    $sumsq = 0.0;
    foreach ( $a as $v ) {
      $sum   += $v;
      $sumsq += ( $v * $v );
    }
    $mean = $sum / $n;

    return sqrt( ( $sumsq / $n ) - ( $mean * $mean ) );
  }

  /** Retrieve an array of statistics about this monitor to upload
   * @return array
   */
  public function getDbStatistics() {
    $status = $this->queryLog->status;
    if ( ! isset ( $status ) ) {
      return [];
    }
    $dt              = $this->queryLog->end - $this->queryLog->start;
    $res             = [];
    $res['duration'] = $dt;
    if ( isset( $status->Uptime_state ) ) {
      $res['uptime'] = $status->Uptime_state;
    }
    if ( isset( $status->Threads_running_state ) ) {
      $res['threads'] = $status->Threads_running_state + 0;
    }
    $failedConnections = $this->getFailed_connections( $status );
    $goodConnections   = ( isset( $status->Connections ) ? $status->Connections : 0 ) - $failedConnections;
    $res['connRate']   = round( $goodConnections / $dt, 2 );
    if ( $failedConnections ) {
      $res['failedConnRate'] = round( $failedConnections / $dt, 2 );
    }
    if ( isset( $status->Aborted_clients ) ) {
      $res['abortedConnRate'] = round( $status->Aborted_clients / $dt, 2 );
    }
    $res['queryRate'] = round( ( isset( $status->Questions ) ? $status->Questions : 0 ) / $dt, 2 );

    $res['mbytesSentRate'] = round( ( isset( $status->Bytes_sent ) ? $status->Bytes_sent : 0 ) / ( $dt * 1024 * 1024 ), 2 );
    $res['mbytesRecvRate'] = round( ( isset( $status->Bytes_received ) ? $status->Bytes_received : 0 ) / ( $dt * 1024 * 1024 ), 2 );

    if ( ( isset( $status->Innodb_rows_sent ) ? $status->Innodb_rows_sent : 0 ) > 0 ) {
      $res['rowsSentRate'] = round( $status->Innodb_rows_sent / $dt, 2 );
    }
    if ( ( isset( $status->Innodb_data_written ) ? $status->Innodb_data_written : 0 ) > 0 ) {
      $res['mbytesWrittenRate'] = round( $status->Innodb_data_written / ( $dt * 1024 * 1024 ), 2 );
    }
    if ( ( isset( $status->Innodb_rows_read ) ? $status->Innodb_rows_read : 0 ) > 0 ) {
      $res['rowsReadRate'] = round( $status->Innodb_rows_read / $dt, 2 );
    }
    $rowsStored = $this->getRowsStored( $status );
    if ( $rowsStored ) {
      $res['rowsStoredRate'] = round( $rowsStored / $dt, 2 );
    }

    if (isset ($this->queryLog->mintime) && $this->queryLog->mintime < PHP_INT_MAX ) {
      $res['minQueryTime'] = round($this->queryLog->mintime);
    }

    return $res;
  }

  public function statusTable() {
    $l = $this->queryLog;
    if ( ! isset ( $l->status ) ) {
      return '';
    }
    $c   = $this->classPrefix;
    $res = '';
    $row = $this->row( [
      "Item",
      "Value",
    ], "status header row" );
    $res .= <<<END
		<div class="$c status table-container"><table class="$c status table"><thead>
		<tr>$row</tr></thead><tbody>
END;

    foreach ( $l->status as $key => $val ) {
      if ( is_string( $val ) && strlen( trim( $val ) ) === 0 ) {
        continue;
      }
      if ( is_string( $val ) && ! is_numeric( $val ) && strlen( trim( $val ) ) >= $this->maxStringLength ) {
        $val = '…' . substr( $val, - $this->maxStringLength - 1 );
      }
      $row   = [];
      $row[] = $key;
      $row[] = $val;
      $res   .= "</tr>" . $this->row( $row, "status data row" ) . "</tr>";
    }

    $res .= "</tbody></table></div>";

    return $res;
  }

  /**
   * @param $status
   * @return string
   */
  private function getServerUptime( &$status ) {
    $result = __( 'Database server' ) . ' ' . DB_HOST . '&ensp;';
    if ( ( isset( $status->Uptime_state ) ? $status->Uptime_state : 0 ) > 0 ) {
      $uptime = $status->Uptime_state * 1000000;
      $uptime = $this->timeCell( $uptime );
      $uptime = $uptime[0];
      $result .= __( 'Uptime', 'index-wp-mysql-for-speed' ) . ' ';
      $result .= "<span class=\"$this->classPrefix count\">$uptime</span>&ensp;";
    }
    return $result;
  }

  private function getBufferPool( &$status, $pool ) {
    /* Buffer pool */
    $result           = '';
    $bufferPoolActive = isset ( $status->Innodb_buffer_pool_bytes_data_state ) ? $status->Innodb_buffer_pool_bytes_data_state : - 1;
    $bufferPoolDirty  = isset ( $status->Innodb_buffer_pool_bytes_dirty_state ) ? $status->Innodb_buffer_pool_bytes_dirty_state : - 1;
    if ( isset( $pool ) && $pool > 0 ) {
      $result     .= __( 'Buffer Pool: Total:', 'index-wp-mysql-for-speed' ) . ' ';
      $bufferPool = $this->byteCell( $pool );
      $result     .= "<span class=\"$this->classPrefix count\">$bufferPool</span> ";
    }
    if ( $bufferPoolActive > 0 ) {
      $result     .= __( 'Active:', 'index-wp-mysql-for-speed' ) . ' ';
      $bufferPool = $this->byteCell( $bufferPoolActive );
      $result     .= "<span class=\"$this->classPrefix count\">$bufferPool</span>";
      if ( $bufferPoolDirty > 0 ) {
        $percentDirty = round( 100 * $bufferPoolDirty / $bufferPoolActive );
        $result       .= " <span class=\"$this->classPrefix count\">$percentDirty</span>";
        $result       .= __( '% dirty', 'index-wp-mysql-for-speed' );
      }
      $result .= '&ensp;';
    }
    return $result;
  }

  private function getRam( $status ) {
    /* RAM: MariaDB only */
    $result       = '';
    $memUsedTotal = isset( $status->Memory_used_state ) ? $status->Memory_used_state : 0;
    if ( $memUsedTotal > 0 ) {
      $result  .= __( 'RAM:', 'index-wp-mysql-for-speed' ) . ' ';
      $ramUsed = $this->byteCell( $memUsedTotal );
      $result  .= "<span class=\"$this->classPrefix count\">$ramUsed</span>" . '&ensp;';
    }
    return $result;
  }

  private function getThreads( $status ) {
    $result  = '';
    $threads = isset( $status->Threads_running_state ) ? $status->Threads_running_state : 0;
    if ( $threads > 0 ) {
      $result .= __( 'Threads:', 'index-wp-mysql-for-speed' ) . ' ';
      $result .= "<span class=\"$this->classPrefix count\">$threads</span>" . '&ensp;';
    }
    return $result;
  }
}