<?php /** @noinspection ALL */

function imfsGetAllStats( $db ) {
  global $_SERVER;
  $variables    = ImfsQueries::toObject( $db->getVariables() );
  $globalStatus = ImfsQueries::toObject( $db->getStatus() );
  $tableStats   = $db->getTableStats();

  $variables->hostname        = ImfsQueries::redactHost( $variables->hostname );
  $variables->report_host     = ImfsQueries::redactHost( $variables->report_host );
  $variables->report_password = ImfsQueries::redactHost( $variables->report_password );
  if ( property_exists( $globalStatus, 'Rsa_public_key' ) ) {
    $globalStatus->Rsa_public_key = 'Redacted';
  }
  if ( property_exists( $globalStatus, 'Caching_sha2_password_rsa_public_key' ) ) {
    $globalStatus->Caching_sha2_password_rsa_public_key = 'Redacted';
  }
  $wordpress = ImfsQueries::getWpDescription( $db );
  $dbms      = imfs_get_dbms_stats( $globalStatus, $variables );
  $sizes     = $db->getSizes();
  foreach ( $sizes as $k => $v ) {
    $dbms[ $k ] = (int) $v;
  }
  $meminfo = imfs_meminfo();
  $cpuinfo = imfs_cpuinfo();


  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  $stats = array(
    'id'           => '', /* id should be first */
    'version'      => index_wp_mysql_for_speed_VERSION_NUM,
    'wordpress'    => $wordpress,
    'dbms'         => $dbms,
    'mysqlVer'     => $db->semver,
    'keys'         => $db->getIndexList(),
    'alltables'    => $tableStats,
    //'timings'      => $db->timings,
    'globalStatus' => $globalStatus,
    'variables'    => $variables,
    'version'      => index_wp_mysql_for_speed_VERSION_NUM,
    'meminfo'      => $meminfo,
    'cpuinfo'      => $cpuinfo,
    't'            => (int) time(),
  );

  return $stats;
}

function imfs_get_dbms_stats( $globalStatus, $variables ) {
  $dbms = [];
  if ( isset( $globalStatus->Memory_used ) ) {
    $dbms['mbytesRam'] = round( $globalStatus->Memory_used / ( 1024 * 1024 ), 0 );
  }
  if ( isset( $variables->innodb_buffer_pool_size ) ) {
    $dbms['mbytesBufferPoolSize'] = round( $variables->innodb_buffer_pool_size / ( 1024 * 1024 ), 0 );
  }
  if ( isset( $globalStatus->Innodb_buffer_pool_bytes_data ) ) {
    $dbms['mbytesBufferPoolActive'] = round( $globalStatus->Innodb_buffer_pool_bytes_data / ( 1024 * 1024 ), 0 );
  }
  if ( isset( $globalStatus->Innodb_buffer_pool_bytes_dirty ) ) {
    $dbms['mbytesBufferPoolDirty'] = round( $globalStatus->Innodb_buffer_pool_bytes_dirty / ( 1024 * 1024 ), 0 );
  }
  if ( isset( $globalStatus->Uptime ) ) {
    $dbms['sUptime'] = round( $globalStatus->Uptime, 0 );
  }

  $dbms['msNullQueryTime'] = imfsGetNullQueryTime();

  return $dbms;
}

function imfsGetNullQueryTime() {
  /* Measure and report the elapsed wall time for a trivial query,
 * hopefully to identify bogged-down and/or shared servers. */
  global $wpdb;
  $startTime = imfsGetTime();
  $wpdb->get_var( ImfsQueries::tagQuery( 'SELECT 1' ) );

  return floatval( round( 1000 * ( imfsGetTime() - $startTime ), 3 ) );
}

/**
 * Get the time in seconds for a minimum (SELECT 1) query
 * @return float Time in seconds.
 */
function imfsGetTime() {
  try {
    $hasHrTime = function_exists( 'hrtime' );
  } catch( Exception $ex ) {
    $hasHrTime = false;
  }

  try {
    /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
    return $hasHrTime ? hrtime( true ) * 0.000000001 : time();
  } catch( Exception $ex ) {
    return time();
  }
}

function imfs_upload_monitor( $db, $idString, $name, $monitor ) {

  /* tidy up monitor query text strings, as JSON.parse can gack on bad utf-8 */
  foreach ( $monitor['queries'] as &$query ) {
    if ( is_array( $query ) ) {
      $q          = mb_convert_encoding( $query['q'], 'UTF-8', 'UTF-8' );
      $query['q'] = $q;
    }
  }
  $wordpress    = ImfsQueries::getWpDescription( $db );
  $globalStatus = ImfsQueries::toObject( $db->getStatus() );
  $variables    = ImfsQueries::toObject( $db->getVariables() );
  $dbms         = imfs_get_dbms_stats( $globalStatus, $variables );
  try {
    $monitor['id']        = $idString;
    $monitor['wordpress'] = $wordpress;
    $monitor['mysqlVer']  = $db->semver;
    $monitor['dbms']      = $dbms;
    $monitor['alltables'] = $db->getTableStats();
    $monitor['meminfo']   = imfs_meminfo();
    $monitor['cpuinfo']   = imfs_cpuinfo();
    imfs_upload_post( (object) $monitor );
  } catch( Exception $e ) {
    /* empty, intentionally. don't croak on uploading */
  }

  return $idString;
}

function imfs_upload_stats( $db, $idString, $target = index_wp_mysql_for_speed_stats_endpoint ) {

  try {
    $stats       = imfsGetAllStats( $db );
    $stats['id'] = $idString;
    imfs_upload_post( (object) $stats, $target );
  } catch( Exception $e ) {
    /* empty, intentionally. don't croak on uploading */
  }

  return $idString;
}

/**
 * @param object $stats
 * @param string $target
 */
function imfs_upload_post( $stats, $target = index_wp_mysql_for_speed_stats_endpoint ) {

  $payload = json_encode( $stats );
  $options = [
    'body'        => $payload,
    'headers'     => [
      'Content-Type' => 'application/json',
    ],
    'timeout'     => 60,
    'redirection' => 5,
    'blocking'    => false,
    'httpversion' => '1.0',
    'sslverify'   => false,
    'data_format' => 'body',
  ];

  $result = wp_remote_post( $target, $options );
}

/**
 * Get contents of /proc/meminfo.
 *
 * @param string $file Meminfo file name. Default /proc/meminfo.
 *
 * @return object Name->value object containing memoinfo contents. Empty object upon failure.
 */
function imfs_meminfo ( $file = '/proc/meminfo') {
  $result = array();
  /* Belt and suspenders failureproofing to defend against unsupported OSs */
  try {
    if ( @file_exists( $file ) && @is_file( $file ) && @is_readable( $file ) ) {
      $fh = @fopen( $file, 'r' );
      if ( $fh ) {
        while ( $line = @fgets( $fh ) ) {
          if ( preg_match( '/^([A-Za-z0-9_\(\)]+)[: ]+([0-9]+).*$/', $line, $splits ) ) {
            $key            = preg_replace( '/[^A-Za-z0-9]/', '_', $splits[1] );
            $key            = ( '_' === substr( $key, - 1 ) ) ? substr( $key, 0, strlen( $key ) - 1 ) : $key;
            $result[ $key ] = is_numeric( $splits[2] ) ? intval( $splits[2] ) : $splits[2];
          }
        }
        fclose( $fh );
      }
    }
    return (object) $result;
  } catch ( Exception $ex ) {
    return (object) $result;
  }
}
/**
 * Get summary of /proc/cpuinfo.
 *
 * @param string $file Cpuinfo file name. Default /proc/cpuinfo.
 *
 * @return object Name->value object containing memoinfo contents. Empty object upon failure.
 */
function imfs_cpuinfo ( $file = '/proc/cpuinfo') {
  $result = array();
  /* Belt and suspenders failureproofing to defend against unsupported OSs */
  try {
    if ( @file_exists( $file ) && @is_file( $file ) && @is_readable( $file ) ) {
      $fh = @fopen( $file, 'r' );
      if ( $fh ) {
        while ( $line = @fgets( $fh ) ) {
          if ( preg_match( '/^([a-zA-Z0-9 ]+[a-zA-Z0-9])+\s*:\s*(.+) *$/', $line, $splits ) ) {
            $key = preg_replace( '/[^A-Za-z0-9]/', '_', $splits[1] );
            $val = $splits[2];
            if ( array_key_exists( $key, $result ) ) {
              if ( $result[ $key ] !== $val ) {
                $result[ $key ] .= '|' . $val;
              }
            } else {
              $result[ $key ] = $val;
            }
          }
        }
        fclose( $fh );
      }
    }
    return (object) $result;
  } catch ( Exception $ex ) {
    return (object) $result;
  }
}
