<?php

class QueryMonControl {
	function start( $duration, $name ) {
		$duration *= MINUTE_IN_SECONDS;  /* minutes */
		$stoptime = time() + $duration;

		$monval = (object)array();
		$monval->stoptime = $stoptime;
		$monval->name = $name;
		set_transient(index_wp_mysql_for_speed_monitor, $monval);

		$stoptimestring = sprintf( __( 'Monitoring until %s. Monitoring output saved into %s' ),
			wp_date( 'g:i:s a T', $stoptime, null ), $name );

		return $stoptimestring;

	}
}