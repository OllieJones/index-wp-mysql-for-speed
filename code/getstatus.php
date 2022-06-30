<?php

/**
 * @param bool|array $prior if given, this returns the difference between the current and prior results.
 *
 * @return array MySQL's global status with zero values removed.
 */
function getGlobalStatus( $prior = false ) {
  global $wpdb;

  $q         = "SHOW GLOBAL STATUS" . '/*' . index_wp_mysql_for_speed_querytag . rand( 0, 999999999 ) . '*/';
  $resultSet = $wpdb->get_results( $q, ARRAY_N );

  $result = [];


  /* add in a copy of some items that's not a difference */
  $stateVars = 'Uptime|Memory_used|Threads_running|Innodb_buffer_pool_bytes_data|Innodb_buffer_pool_bytes_dirty';
  $stateVars = explode( '|', $stateVars );

  foreach ( $resultSet as $row ) {
    $key = $row[0];
    $val = $row[1];
    if ( array_search( $key, $stateVars ) ) {
      $result[ $key . '_state' ] = $val;
    }
  }

  foreach ( $resultSet as $row ) {
    $key = $row[0];
    $val = $row[1];
    if ( is_numeric( $val ) ) {
      $val      = $val + 0;
      $priorVal = is_array( $prior ) && isset( $prior[ $key ] ) ? $prior[ $key ] : 0;
      if ( is_array( $prior ) && is_numeric( $priorVal ) ) {
        $val = $val - $priorVal + 0;
      }
    }
    if ( $val !== 0 ) {
      $result[ $key ] = $val;
    }
  }

  return $result;
}
