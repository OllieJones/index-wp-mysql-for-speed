<?php
require_once( 'getindexes.php' );
require_once( 'getqueries.php' );

class Health {

  public $stats;

  /**
   * @param array $allStats The metadata object uploaded to
   */
  public function __construct( $allStats, $timestamp = null ) {
    $this->stats = $allStats;
    if ( ! isset ( $this->stats['t'] ) ) {
      $this->stats['t'] = $timestamp ?: time();
    }
  }

  public function getReport( $prefix = '<p>', $suffix = '</p>' ) {
    $reflector = new ReflectionClass ( $this );
    $methods   = $reflector->getMethods( ReflectionMethod::IS_PUBLIC );

    $o = '';
    foreach ( $methods as $method ) {
      if ( str_contains( $method->name, '_r' ) ) {
        try {
          $o .= $prefix;
          $o .= $method->invoke( $this );
          $o .= $suffix;
        } catch ( Exception $ex ) {
          /* Empty, intentionally. Ignore not-computable stuff */
        }
      }
    }

    return $o;

  }

  public function aa_r() {
    $d      = $this->stats;
    $v      = $d['variables'];
    $g      = $d['globalStatus'];
    $uptime = $this->getUptime();
    $since  = $d['t'] - $uptime;
    $up     = ImfsQueries::timeCell( 1000000.0 * $uptime );

    /* translators: 1: time interval  2: date-time string  3: hostname or "Redacted, not localhost"  */
    $text = __( 'This database server [%3$s] up for: %1$s (since %2$s).', 'index-wp-mysql-for-speed' );

    return sprintf( $text, $up[0], ImfsQueries::date( $since ), $v->hostname, $this->stats );

  }


  public function sizes_r() {
    $d = $this->stats;

    /* Does the stats array have database sizes */
    if ( ! array_key_exists( 'version', $d ) ) {
      return '';
    }

    $sizes = (object) $d['dbms'];

    $o = '<p>';
    /* translators: 1: size like 4.3Mib  2: percentage like 40.5  3: complementary percentage like 59.5 */
    $text  = __( 'This site\'s database size: %1$s. %2$s%% data, %3$s%% keys.', 'index-wp-mysql-for-speed' );
    $total = $sizes->innodb_data_len + $sizes->innodb_key_len;
    if ( $total > 0 ) {
      $to = ImfsQueries::byteCell( $total );
      $o  .= sprintf( $text, $to, ImfsQueries::percent( $sizes->innodb_data_len, $total ), ImfsQueries::percent( $sizes->innodb_key_len, $total ) );
    }
    $o .= '</p>';

    if ( $sizes->database_count > 1 ) {
      $o .= '<p>';
      /* translators: 1: size like 4.3Mib  2: percentage like 40.5  3: complementary percentage like 59.5  4:total number of databases visible */
      $text  = __( 'All %4$s databases size: %1$s. %2$s%% data, %3$s%% keys.', 'index-wp-mysql-for-speed' );
      $text  = __( 'All %4$s databases size: %1$s. %2$s%% data, %3$s%% keys.', 'index-wp-mysql-for-speed' );
      $total = $sizes->innodb_data_total + $sizes->innodb_key_total;
      if ( $total > 0 ) {
        $p1 = $sizes->innodb_data_total / $total;
        $p2 = $sizes->innodb_key_total / $total;
        $to = ImfsQueries::byteCell( $total );
        $o  .= sprintf( $text,
          $to,
          ImfsQueries::percent( $sizes->innodb_data_total, $total ),
          ImfsQueries::percent( $sizes->innodb_key_total, $total ),
          $sizes->database_count );

        $o .= ' <i>';
        /* translators: 1: database count */
        $text = __( 'Shared database servers may contain more than these %1$s databases.', 'index-wp-mysql-for-speed' );
        $o    .= sprintf( $text, $sizes->database_count );
        $o    .= '</i>';

      }
      $o .= '</p>';

    }

    return $o;

  }

  public function aaa_r() {

    $d               = $this->stats;
    $v               = $d['variables'];
    $g               = $d['globalStatus'];
    $bufferPoolSize  = $v->innodb_buffer_pool_size;
    $bufferPoolUsed  = $g->Innodb_buffer_pool_bytes_data;
    $bufferPoolDirty = $g->Innodb_buffer_pool_bytes_dirty;

    /* translators: 1: size like 4.3Mib  2: percentage like 40.5  3: complementary percentage like 59.5 */
    $text = __( 'Database buffer pool size: %1$s. %2$s%% used, %3$s%% dirty.', 'index-wp-mysql-for-speed' );

    return sprintf( $text,
      ImfsQueries::byteCell( $bufferPoolSize ),
      ImfsQueries::percent( $bufferPoolUsed, $bufferPoolSize ),
      ImfsQueries::percent( $bufferPoolDirty, $bufferPoolSize ) );

  }

  public function connections_r() {
    $d      = $this->stats;
    $v      = $d['variables'];
    $g      = $d['globalStatus'];
    $result = array();
    if ( is_numeric( $g->Threads_connected ) && isset ( $v->max_connections ) ) {
      /* translators: 1: formatted number,  2: formatted number */
      $text     = __( 'Number of connections to database server:  current: %1$s  limit: %2$s', 'index-wp-mysql-for-speed' );
      $result[] = sprintf( $text,
        number_format_i18n( $g->Threads_connected, 0 ),
        number_format_i18n( $v->max_connections, 0 ) );
    }

    if ( is_numeric( $g->Max_used_connections ) ) {
      /* translators: 1: formatted number */
      $text     = __( 'peak: %1$s', 'index-wp-mysql-for-speed' );
      $result[] = sprintf( $text, number_format_i18n( $g->Max_used_connections, 0 ) );

      if ( isset ( $g->Max_used_connections_time ) ) {
        /* translators: 1: time string */
        $text     = __( 'at %1$s', 'index-wp-mysql-for-speed' );
        $result[] = sprintf( $text, $g->Max_used_connections_time );
      }
    }

    return implode( ' ', $result ) . '.';
  }

  public function nullquerytime_r() {
    if ( isset( $this->stats['dbms']['msNullQueryTime'] ) ) {
      $nqt = $this->stats['dbms']['msNullQueryTime'] * 1000.0;
      /* translators: 1: time interval with unit like 0.5ms */
      $text = __( 'Minimum database query round-trip time: %1$s.', 'index-wp-mysql-for-speed' );

      return sprintf( $text, ImfsQueries::timeCell( $nqt )[0] );
    }

    return '';
  }

  public function tmptable_traffic_r() {
    $d = $this->stats;
    $v = $d['variables'];
    $g = $d['globalStatus'];
    if ( $this->getUptime() > 0 && is_numeric( $g->Created_tmp_tables ) && is_numeric( $g->Created_tmp_disk_tables ) ) {
      $since = $d['t'] - $this->getUptime();


      /* translators: 1: datestamp  2: number  number like 123.4  4: percentage */
      $text = __( 'Temporary results tables used (since %1$s): %2$s/sec. %3$s%% overflowed to SSD/HDD.', 'index-wp-mysql-for-speed' );

      return sprintf( $text,
        ImfsQueries::date( $since ),
        number_format_i18n( $g->Created_tmp_tables / $this->getUptime(), 1 ),
        ImfsQueries::percent( $g->Created_tmp_disk_tables, $g->Created_tmp_tables ) );

    }

    return '';

  }


  public function uptime_traffic_r() {
    $d = $this->stats;
    $v = $d['variables'];
    $g = $d['globalStatus'];
    if ( $this->getUptime() > 0 && is_numeric( $g->Bytes_received ) && is_numeric( $g->Bytes_sent ) ) {
      $since = $d['t'] - $this->getUptime();

      $recvPerSec = $g->Bytes_received / $this->getUptime();
      $sentPerSec = $g->Bytes_sent / $this->getUptime();

      /* translators: 1: datestamp  2: byte-count like 3.6GiB  3: byte-count like 3.6GiB */
      $text = __( 'Database server network traffic (since %1$s): %2$s/sec sent to WordPress, %3$s/sec received.', 'index-wp-mysql-for-speed' );

      return sprintf( $text,
        ImfsQueries::date( $since ),
        ImfsQueries::byteCell( $sentPerSec ),
        ImfsQueries::byteCell( $recvPerSec ) );

    }

    return '';

  }

  private function getUptime() {
    if ( is_numeric( $this->stats['globalStatus']->Uptime_since_flush_status ) ) {
      return $this->stats['globalStatus']->Uptime_since_flush_status;
    }

    return $this->stats['globalStatus']->Uptime;

  }


}
