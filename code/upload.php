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

  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  $stats = [
    'id'           => '', /* id should be first */
    'wordpress'    => $wordpress,
    'dbms'         => $dbms,
    'mysqlVer'     => $db->semver,
    'keys'         => $db->getIndexList(),
    'alltables'    => $tableStats,
    //'timings'      => $db->timings,
    'globalStatus' => $globalStatus,
    'variables'    => $variables,
  ];

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

function imfsGetNullQueryTime () {
  /* Measure and report the elapsed wall time for a trivial query,
 * hopefully to identify bogged-down and/or shared servers. */
  global $wpdb;
  $startTime = imfsGetTime();
  $wpdb->get_var( ImfsQueries::tagQuery('SELECT 1') );
  return floatval( round( 1000 * ( imfsGetTime() - $startTime ), 3 ) );
}

function imfsGetTime() {
  try {
    $hasHrTime = function_exists( 'hrtime' );
  } catch ( Exception $ex ) {
    $hasHrTime = false;
  }

  try {
    /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
    return $hasHrTime ? hrtime( true ) * 0.000000001 : time();
  } catch ( Exception $ex ) {
    return time();
  }
}

function imfs_upload_monitor( $db, $idString, $name, $monitor ) {
  $wordpress    = ImfsQueries::getWpDescription( $db );
  $globalStatus = ImfsQueries::toObject( $db->getStatus() );
  $variables    = ImfsQueries::toObject( $db->getVariables() );
  $dbms         = imfs_get_dbms_stats( $globalStatus, $variables );
  try {
    $monitor['id']        = $idString;
    $monitor['wordpress'] = $wordpress;
    $monitor['dbms']      = $dbms;
    $monitor['alltables'] = $db->getTableStats();
    imfs_upload_post( (object) $monitor );
  } catch ( Exception $e ) {
    /* empty, intentionally. don't croak on uploading */
  }

  return $idString;
}

function imfs_upload_stats( $db, $idString, $target = index_wp_mysql_for_speed_stats_endpoint ) {

  try {
    $stats       = imfsGetAllStats( $db );
    $stats['id'] = $idString;
    imfs_upload_post( (object) $stats, $target );
  } catch ( Exception $e ) {
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