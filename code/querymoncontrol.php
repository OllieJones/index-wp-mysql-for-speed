<?php

class QueryMonControl {
	function start( $specs ) {

		$duration = $specs['duration'] * 60;
		$stoptime = time() + $duration;

		$monval             = (object) array();
		$monval->stoptime   = $stoptime;
		$monval->name       = $specs['name'];
		$monval->samplerate = floatval( $specs['samplerate'] * 0.01 );

		$monval->targets = intval( $specs['targets'] );
		$targetText      = __( 'Monitoring dashboard and site', index_wp_mysql_for_speed_domain );
		if ( $monval->targets === 1 ) {
			$targetText = __( 'Monitoring site only', index_wp_mysql_for_speed_domain );
		} else if ( $monval->targets === 2 ) {
			$targetText = __( 'Monitoring dashboard only', index_wp_mysql_for_speed_domain );
		}

		if ( $specs['samplerate'] != 100 ) {
			$stoptimestring = sprintf( __( '%s for %d minutes until %s. Random sampling %d%% of page views. Monitoring output saved into %s', index_wp_mysql_for_speed_domain ),
				$targetText, $specs['duration'], wp_date( 'g:i:s a T', $stoptime ), $specs['samplerate'], $monval->name );
		} else {
			$stoptimestring = sprintf( __( '%s  for %d minutes until %s. Monitoring output saved into %s', index_wp_mysql_for_speed_domain ),
				$targetText, $specs['duration'], wp_date( 'g:i:s a T', $stoptime ), $monval->name );
		}

		update_option( index_wp_mysql_for_speed_monitor, $monval, true );

		return $stoptimestring;

	}
}