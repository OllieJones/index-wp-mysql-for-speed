<?php

class QueryMonControl {
	function start( $specs ) {

		$duration = $specs['duration'] * 60;
		$stoptime = time() + $duration;

		$monval = (object)array();
		$monval->stoptime = $stoptime;
		$monval->name = $specs['name'];
		$monval->samplerate = floatval($specs['samplerate'] * 0.01);
		set_transient(index_wp_mysql_for_speed_monitor, $monval);

		if ($monval->sampleRate !== 1.0) {
			$stoptimestring = sprintf( __( 'Monitoring until %s. Random sampling %d%% of page views. Monitoring output saved into %s' ),
				wp_date( 'g:i:s a T', $stoptime ), round( $monval->samplerate * 100 ), $monval->name );
		}
		else {
			$stoptimestring = sprintf( __( 'Monitoring until %s. Capturing all page views. Monitoring output saved into %s' ),
				wp_date( 'g:i:s a T', $stoptime ),  $monval->name );
		}

		return $stoptimestring;

	}
}