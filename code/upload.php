<?php

function imfsGetAllStats( $db ) {
  global $_SERVER;
  $variables     = ImfsQueries::toObject( $db->getVariables() );
  $globalStatus  = ImfsQueries::toObject( $db->getStatus() );
  $innoDbMetrics = ImfsQueries::toObject( $db->getInnodbMetrics() );
  $tableStats    = $db->getTableStats();

  $variables->hostname        = ImfsQueries::redactHost( $variables->hostname );
  $variables->report_host     = ImfsQueries::redactHost( $variables->report_host );
  $variables->report_password = ImfsQueries::redactHost( $variables->report_password );
  if ( $globalStatus->Rsa_public_key ) {
    $globalStatus->Rsa_public_key = 'Redacted';
  }
  $wordpress = ImfsQueries::getWpDescription( $db );
  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  $stats = [
    'id'            => '', /* id should be first */
    'wordpress'     => $wordpress,
    'mysqlVer'      => $db->semver,
    'keys'          => $db->getIndexList(),
    'alltables'     => $tableStats,
    //'timings'      => $db->timings,
    'globalStatus'  => $globalStatus,
    'innodbMetrics' => $innoDbMetrics,
    'variables'     => $variables,
  ];

  return $stats;

}

function imfs_upload_monitor( $db, $idString, $name, $monitor ) {

  try {
    $monitor['id'] = $idString;
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