<?php

require_once( 'getstatus.php' );

class QueryMonControl {
  function start( $specs, $db ) {

    $duration = $specs['duration'] * 60;
    $now      = time();
    $stopTime = $now + $duration;
    $name     = $specs['name'];

    $monval             = (object) [];
    $monval->starttime  = $now;
    $monval->stoptime   = $stopTime;
    $monval->name       = $specs['name'];
    $monval->samplerate = $specs['samplerate'] * 0.01;
    $monval->keys       = $db->getIndexList();

    $monval->targets = intval( $specs['targets'] );
    $targetText      = __( 'Monitoring dashboard and site', index_wp_mysql_for_speed_domain );
    if ( $monval->targets === 2 ) {
      $targetText = __( 'Monitoring site only', index_wp_mysql_for_speed_domain );
    } else if ( $monval->targets === 1 ) {
      $targetText = __( 'Monitoring dashboard only', index_wp_mysql_for_speed_domain );
    }

    if ( $specs['samplerate'] != 100 ) {
      $stopTimeString = sprintf( __( '%s for %d minutes until %s. Random sampling %d%% of page views. Monitoring output saved into %s', index_wp_mysql_for_speed_domain ),
        $targetText, $specs['duration'], wp_date( 'g:i:s a T', $stopTime ), $specs['samplerate'], $monval->name );
    } else {
      $stopTimeString = sprintf( __( '%s  for %d minutes until %s. Monitoring output saved into %s', index_wp_mysql_for_speed_domain ),
        $targetText, $specs['duration'], wp_date( 'g:i:s a T', $stopTime ), $monval->name );
    }
    update_option( index_wp_mysql_for_speed_monitor, $monval, true );

    $status              = getGlobalStatus();
    $status['starttime'] = $now;
    $status['stoptime']  = $stopTime;
    $statusName          = index_wp_mysql_for_speed_monitor . '-Status-' . $name;
    set_transient( $statusName, $status, $duration + 3600 );
    delete_option( index_wp_mysql_for_speed_monitor . 'Gather' );
    /** @noinspection PhpRedundantOptionalArgumentInspection */
    add_option( index_wp_mysql_for_speed_monitor . 'Gather', '' );

    return $stopTimeString;
  }
}