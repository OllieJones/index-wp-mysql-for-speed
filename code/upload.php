<?php

/** get a random text string from letters and numbers.
 *
 * @param $length
 *
 * @return string
 */
function imfsRandomString( $length ) {
  /* some characters removed from this set to reduce confusion reading aloud */
  $characters       = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXYZ';
  $charactersLength = strlen( $characters );
  $randomString     = '';
  for ( $i = 0; $i < $length; $i ++ ) {
    $randomString .= $characters[ rand( 0, $charactersLength - 1 ) ];
  }

  return $randomString;
}

function imfsToObject( $rows ) {
  $variables = [];
  foreach ( $rows as $row ) {
    $variables[ $row->Variable_name ] = is_numeric( $row->Value ) ? intval( $row->Value ) : $row->Value;
  }

  return (object) $variables;
}

function imfsToResultSet( $rows, $nameCaption = 'Item', $valueCaption = 'Value' ) {
  $res = [];
  foreach ( $rows as $name => $value ) {
    $rsrow = [ $nameCaption => $name, $valueCaption => $value ];
    $res[] = $rsrow;
  }

  return $res;
}

function getActivePlugins() {
  $plugins = get_plugins();
  $result  = [];
  foreach ( $plugins as $path => $desc ) {
    if ( is_plugin_active( $path ) ) {
      $result[] = $desc['Name'];
    }
  }

  return $result;
}

function imfsGetWpDescription( $db ) {
  global $wp_db_version;
  global $wp_version;
  global $required_php_version;
  global $required_mysql_version;
  global $_SERVER;
  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  $wordpress = [
    'webserverversion'       => $_SERVER['SERVER_SOFTWARE'],
    'wp_version'             => $wp_version,
    'wp_db_version'          => $wp_db_version,
    'phpversion'             => phpversion(),
    'required_php_version'   => $required_php_version,
    'mysqlversion'           => $db->semver->version,
    'required_mysql_version' => $required_mysql_version,
    'pluginversion'          => index_wp_mysql_for_speed_VERSION_NUM,
    'is_multisite'           => is_multisite(),
    'is_main_site'           => is_main_site(),
    'current_blog_id'        => get_current_blog_id(),
    'active_plugins'         => implode( '|', getActivePlugins() ),
  ];

  return $wordpress;
}

function imfsGetAllStats( $db ) {
  global $_SERVER;
  $variables     = imfsToObject( $db->stats[0] );
  $globalStatus  = imfsToObject( $db->stats[3] );
  $innoDbMetrics = imfsToObject( $db->stats[4] );

  $variables->hostname        = imfsRedactHost( $variables->hostname );
  $variables->report_host     = imfsRedactHost( $variables->report_host );
  $variables->report_password = imfsRedactHost( $variables->report_password );
  if ( $globalStatus->Rsa_public_key ) {
    $globalStatus->Rsa_public_key = 'Redacted';
  }
  $wordpress = imfsGetWpDescription( $db );
  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  $stats = [
    'id'            => '', /* id should be first */
    'wordpress'     => $wordpress,
    'mysqlVer'      => $db->semver,
    'keys'          => $db->getIndexList(),
    'alltables'     => $db->stats[1],
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