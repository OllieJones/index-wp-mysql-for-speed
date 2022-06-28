<?php

require_once( 'getstatus.php' );

class QueryMonControl {
  function start( $specs, $db ) {

    $duration = $specs['duration'] * 60;
    $now      = time();
    $stopTime = $now + $duration;
    $name     = $specs['name'];

    $monval             = (object) [];
    $monval->semver     = $db->semver;
    $monval->starttime  = $now;
    $monval->stoptime   = $stopTime;
    $monval->name       = $specs['name'];
    $monval->samplerate = $specs['samplerate'] * 0.01;
    $monval->keys       = $db->getIndexList();

    $monval->targets = intval( $specs['targets'] );
    $targetText      = __( 'Monitoring dashboard and site', 'index-wp-mysql-for-speed' );
    if ( $monval->targets === 2 ) {
      $targetText = __( 'Monitoring site only', 'index-wp-mysql-for-speed' );
    } else if ( $monval->targets === 1 ) {
      $targetText = __( 'Monitoring dashboard only', 'index-wp-mysql-for-speed' );
    }

    if ( $specs['samplerate'] != 100 ) {
      /* translators: 1: "Monitoring dashboard and site", etc. 2: number of minutes  3: end time  4: percentage 5: monitor name */
      $stopTimeString = sprintf( __( '%1$s for %2$d minutes until %3$s. Random sampling %4$d%% of page views. Monitoring output saved into <i>%5$s</i>.', 'index-wp-mysql-for-speed' ),
        $targetText, $specs['duration'], wp_date( 'g:i:s a T', $stopTime ), $specs['samplerate'], $monval->name );
    } else {
      /* translators: 1: "Monitoring dashboard and site", etc. 2: number of minutes  3: end time  4: monitor name */
      $stopTimeString = sprintf( __( '%1$s for %2$d minutes until %3$s. Monitoring output saved into %4$s', 'index-wp-mysql-for-speed' ),
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