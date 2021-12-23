<?php
/**
 * @param bool|array $prior if given, this returns the difference between the current and prior results.
 *
 * @return array MySQL's global status with zero values removed.
 */
function getGlobalStatus( $prior = false ): array {
	global $wpdb;
	$resultSet = $wpdb->get_results( index_wp_mysql_for_speed_querytag . "SHOW GLOBAL STATUS", ARRAY_N );

	/* add in a copy of Uptime that's not a difference */
	$uptime = - 1;
	foreach ( $resultSet as $row ) {
		if ( $row[0] === 'Uptime' ) {
			$uptime = $row[1];
			break;
		}
	}

	$result = array();
	if ( $uptime >= 0 ) {
		$result['UptimeSinceStart'] = intval( $uptime );
	}
	foreach ( $resultSet as $row ) {
		$key = $row[0];
		$val = $row[1];
		if ( is_numeric( $val ) ) {
			$val      = intval( $val );
			$priorVal = is_array( $prior ) && isset( $prior[ $key ] ) ? $prior[ $key ] : 0;
			if ( is_array( $prior ) && is_numeric( $priorVal ) ) {
				$val = $val - intval( $priorVal );
			}
		}
		if ( $val !== 0 ) {
			$result[ $key ] = $val;
		}
	}

	return $result;
}
