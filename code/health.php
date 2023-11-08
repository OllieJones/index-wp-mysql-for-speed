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

  public function sizes_r() {
    $d = $this->stats;

    /* Does the stats array have database sizes */
    if ( ! array_key_exists( 'version', $d ) ) {
      return '';
    }

    $sizes = (object) $d['dbms'];

    $o = '<p>';
    /* translators: 1: size like 4.3Mib  2: percentage like 40.5  3: complementary percentage like 59.5 */
    $text  = __( 'This site\'s data size: %1$s. %2$s%% data, %3$s%% keys', 'index-wp-mysql-for-speed' );
    $total = $sizes->innodb_data_len + $sizes->innodb_key_len;
    if ( $total > 0 ) {
      $p1 = $sizes->innodb_data_len / $total;
      $p2 = $sizes->innodb_key_len / $total;
      $to = ImfsQueries::byteCell( $total );
      $o  .= sprintf( $text, $to, ImfsQueries::percent( $sizes->innodb_data_len, $total ), ImfsQueries::percent( $sizes->innodb_key_len, $total ) );
    }
    $o .= '</p>';

    if ( $sizes->database_count > 1 ) {
      $o .= '<p>';
      /* translators: 1: size like 4.3Mib  2: percentage like 40.5  3: complementary percentage like 59.5  4:total number of databases visible */
      $text  = __( 'All %4$s databases size: %1$s. %2$s%% data, %3$s%% keys', 'index-wp-mysql-for-speed' );
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
        $text = __( 'Shared servers may contain other databases than these %1$s.', 'index-wp-mysql-for-speed' );
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
    $text = __( 'Database buffer pool size: %1$s. %2$s%% used, %3$s%% dirty (changed)', 'index-wp-mysql-for-speed' );

    return sprintf( $text,
      ImfsQueries::byteCell( $bufferPoolSize ),
      ImfsQueries::percent( $bufferPoolUsed, $bufferPoolSize ),
      ImfsQueries::percent( $bufferPoolDirty, $bufferPoolSize ) );

  }

  public function uptime_r() {
    $d      = $this->stats;
    $v      = $d['variables'];
    $g      = $d['globalStatus'];
    $uptime = $g->Uptime;
    $since  = $d['t'] - $uptime;
    $up     = ImfsQueries::timeCell( 1000000.0 * $uptime );

    /* translators: 1: time interval  2: date-time string  3: hostname or "Redacted, not localhost" */
    $text = __( 'Database server [%3$s] up for: %1$s since %2$s', 'index-wp-mysql-for-speed' );

    return sprintf( $text, $up[0], ImfsQueries::date( $since ), $v->hostname );

  }

}
