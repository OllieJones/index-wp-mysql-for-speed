<?php

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
  private $domain = index_wp_mysql_for_speed_domain;
  private $maxStringLength = 12;

  public function __construct( $monitor, $db ) {
    $this->monitor    = $monitor;
    $this->db         = $db;
    $this->prefix     = index_wp_mysql_for_speed_monitor . '-Log-';
    $this->dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
  }

  static function renderMonitors( $list, $db ): string {
    $renders  = [];
    $monitors = RenderMonitor::getMonitors();

    foreach ( $monitors as $monitor ) {
      if ( is_null( $list )
           || ( is_string( $list ) && $monitor === $list )
           || ( is_array( $list ) && array_search( $monitor, $list ) !== false ) ) {
        $rm        = new RenderMonitor( $monitor, $db );
        $renders[] = $rm->render();
      }
    }

    return implode( "\r", $renders );
  }

  /** Retrieve a list of saved monitors
   * @return array
   */
  static function getMonitors(): array {
    global $wpdb;
    $prefix = index_wp_mysql_for_speed_monitor . '-Log-';
    $result = [];
    $q      = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '" . $prefix . "%' AND LENGTH(option_value) > 0";
    $rs     = $wpdb->get_results( $q );
    foreach ( $rs as $r ) {
      $name     = str_replace( $prefix, '', $r->option_name );
      $result[] = $name;
    }

    return $result;
  }

  /** Render the monitor to a string
   * @return string
   */
  function render(): string {
    $this->load();
    $c      = $this->classPrefix;
    $prefix = "<div class=\"$c index-wp-mysql-for-speed-content-container\">";

    return $prefix . $this->top() . $this->table() /*. $this->statusTable() */ . "</div>";
  }

  /** Load the monitor
   */
  public function load(): renderMonitor {
    $this->queryLog          = json_decode( get_option( $this->prefix . $this->monitor ) );
    $this->queryLog->queries = (array) $this->queryLog->queries;

    return $this;
  }

  /** Render the header part of the monitor
   * @return string
   */
  public function top(): string {
    $c      = $this->classPrefix;
    $times  = $this->capturedQuerySummary();
    $dbSumm = $this->dbStatusSummary();
    $res    = <<<END
		<h1 class="$c h1">$this->monitor</h1>
		<div class="$c top time">$times</div>
		<div class="$c top summary">$dbSumm</div>
		<div class="$c top stats">
END;

    return $res;
  }

  public function capturedQuerySummary(): string {
    $l          = $this->queryLog;
    $c          = $this->classPrefix;
    $start      = wp_date( $this->dateFormat, $l->start );
    $end        = wp_date( $this->dateFormat, $l->end );
    $duration   = $this->timeCell( 1000000 * ( $l->end - $l->start ) );
    $querycount = number_format_i18n( $l->querycount, 0 );
    $capString  = __( 'queries captured.', $this->domain );
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
  public function timeCell( $time, $unit = null, string $prefix = '' ): array {
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

  public function getTimeUnit( $timeSeconds ): array {
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

  public function dbStatusSummary(): ?string {
    $l      = $this->queryLog;
    $c      = $this->classPrefix;
    $result = '';
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
    $result .= __( 'Database server' ) . ' ' . DB_HOST . '&ensp;';
    if ( ( $status->Uptime_state ?? 0 ) > 0 ) {
      $uptime = $status->Uptime_state * 1000000;
      $uptime = $this->timeCell( $uptime );
      $uptime = $uptime[0];
      $result .= __( 'Uptime', $this->domain ) . ' ';
      $result .= "<span class=\"$c count\">$uptime</span>&ensp;";
    }
    /* RAM: MariaDB only */
    $memUsedTotal = $status->Memory_used_state ?? 0;
    if ( $memUsedTotal > 0 ) {
      $result  .= __( 'RAM:', $this->domain ) . ' ';
      $ramUsed = $this->byteCell( $memUsedTotal );
      $result  .= "<span class=\"$c count\">$ramUsed</span>" . '&ensp;';
    }

    $threads = $status->Threads_running_state ?? 0;
    if ( $threads > 0 ) {
      $result .= __( 'Threads:', $this->domain ) . ' ';
      $result .= "<span class=\"$c count\">$threads</span>" . '&ensp;';
    }

    $result .= __( 'Version:', $this->domain ) . ' ';
    $result .= $this->db->semver->version;

    $result .= "</div><div class=\"$c top line indent\">";

    $failedConnections = ( $status->Connection_errors_accept ?? 0 )
                         + ( $status->Connection_errors_internal ?? 0 )
                         + ( $status->Connection_errors_max_connections ?? 0 )
                         + ( $status->Connection_errors_peer_address ?? 0 )
                         + ( $status->Connection_errors_select ?? 0 )
                         + ( $status->Connection_errors_tcpwrap ?? 0 );
    $goodConnections   = ( $status->Connections ?? 0 ) - $failedConnections;
    $goodCps           = number_format_i18n( $goodConnections / $dt, 2 );
    $failedCps         = number_format_i18n( $failedConnections / $dt, 2 );

    $result .= "<span class=\"$c count\">$goodCps</span> ";
    $result .= __( "Connections/s", $this->domain ) . '&ensp;';
    if ( $failedConnections > 0 ) {
      $result .= "<span class=\"$c count\">$failedCps</span> ";
      $result .= __( "Failed connections/s", $this->domain ) . '&ensp;';
    }

    if ( ( $status->Aborted_clients ?? 0 ) > 0 ) {
      $abruptDisconnects = $status->Aborted_clients;
      $abruptDps         = number_format_i18n( $abruptDisconnects / $dt, 2 );
      $result            .= "<span class=\"$c count\">$abruptDps</span> ";
      $result            .= __( "Abrupt disconnections/s", $this->domain ) . '&ensp;';
    }

    /* Queries from clients (not counting those within stored code */
    $qps    = number_format_i18n( ( $status->Questions ?? 0 ) / $dt, 2 );
    $result .= "<span class=\"$c count\">$qps</span> ";
    $result .= __( "Queries/s", $this->domain ) . '&ensp;';

    $result .= "</div><div class=\"$c top line indent\">";

    /* net traffic to and from database server */
    $dataSent     = $this->byteCell( ( $status->Bytes_sent ?? 0 ) / $dt );
    $dataReceived = $this->byteCell( ( $status->Bytes_received ?? 0 ) / $dt );
    $result       .= __( 'Net:', $this->domain ) . ' ';
    $result       .= "<span class=\"$c count\">$dataReceived/s</span> ";
    $result       .= __( "received", $this->domain ) . ' ';
    $result       .= "<span class=\"$c count\">$dataSent/s</span> ";
    $result       .= __( "sent", $this->domain ) . '&ensp;';

    $result .= "</div><div class=\"$c top line indent\">";

    $dataRead = $this->byteCell( ( $status->Innodb_data_read ?? 0 ) / $dt );
    $result   .= __( 'IO:', $this->domain ) . ' ';
    $result   .= "<span class=\"$c count\">$dataRead/s</span> ";
    $result   .= __( "read", $this->domain ) . ' ';

    $rows = __( "rows", $this->domain );
    if ( ( $status->Innodb_rows_sent ?? 0 ) > 0 ) {
      $rowsSent = number_format_i18n( $status->Innodb_rows_sent / $dt, 2 ) . ' ' . $rows;
      $result   .= "<span class=\"$c count\">$rowsSent/s</span> ";
      $result   .= __( "fetched", $this->domain ) . ' ';
    }
    if ( ( $status->Innodb_data_written ?? 0 ) > 0 ) {
      $dataWritten = $this->byteCell( $status->Innodb_data_written / $dt );
      $result      .= "<span class=\"$c count\">$dataWritten/s</span> ";
      $result      .= __( "written", $this->domain ) . '&ensp;';
    }
    $rowsRead = $status->Innodb_rows_read ?? 0;
    $rowsRead = number_format_i18n( $rowsRead / $dt, 2 ) . ' ' . $rows;
    $result   .= "<span class=\"$c count\">$rowsRead/s</span> ";
    $result   .= __( "retrieved", $this->domain ) . ' ';

    $rowcount      = 0;
    $rowcount      += $status->Innodb_rows_deleted ?? 0;
    $rowcount      += $status->Innodb_rows_inserted ?? 0;
    $rowcount      += $status->Innodb_rows_updated ?? 0;
    $rowsProcessed = round( $rowcount / $dt, 2 ) . ' ' . $rows;
    $result        .= "<span class=\"$c count\">$rowsProcessed/s</span> ";
    $result        .= __( "stored", $this->domain ) . ' ';

    $result .= '</div>';

    return $result;
  }

  /** get cell data for byte counts
   *
   * @param number $bytes
   *
   * @return string
   */
  public function byteCell( $bytes, $unit = null, $prefix = '' ): string {
    if ( $bytes === 0.0 ) {
      $displayBytes = $prefix !== '' ? '' : '0';

      return $displayBytes;
    }
    if ( $unit === null ) {
      $unit = $this->getByteUnit( $bytes );
    }
    $displayBytes = $prefix . number_format_i18n( $bytes / $unit[0], $unit[2] ) . $unit[1];

    return $displayBytes;
  }

  public function getByteUnit( $bytes ): array {
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

  public function table(): string {
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
      "Plan",
      "Query",
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
        $row[] = $this->queryPlan( $q );
        $row[] = $q->f;
        $row[] = $q->c;
        $row[] = $q->q;
        $res   .= "</tr>" . $this->row( $row, "query data row" ) . "</tr>";
      }
    }

    $res .= "</tbody></table></div>";

    return $res;
  }

  public function row( $a, $class = 'row' ): string {
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
  public function percentile( array $a, $p ): float {
    $n = count( $a );
    sort( $a );
    $i = floor( $n * $p );
    if ( $i >= $n ) {
      $i = $n - 1;
    }

    return $a[ $i ];
  }

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
        $expl[] = "Index" . ';';
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

  public function statusTable(): string {
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

  /** standard deviation
   *
   * @param array $a dataset
   *
   * @return number
   */
  public function stdev( $a ): ?float {
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
}