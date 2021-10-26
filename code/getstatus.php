<?php
/**
 * @param bool|array $prior if given, this returns the difference between the current and prior results.
 *
 * @return array MySQL's global status with zero values removed.
 */
function getGlobalStatus( $prior = false ): array {
	global $wpdb;
	$resultSet = $wpdb->get_results( index_wp_mysql_for_speed_querytag . "SHOW GLOBAL STATUS", ARRAY_N );
	$result    = array();
	foreach ( $resultSet as $row ) {
		$key = $row[0];
		$val = $row[1];
		if (is_numeric ($val)) {
			$val      = intval( $val );
			$priorVal = is_array($prior) && isset( $prior[$key] ) ? $prior[$key] : 0;
			if (is_array($prior) && is_numeric( $priorVal ) ) {
				$val = $val - intval( $priorVal );
			}
		}
		if ($val !== 0) {
			$result[ $key ] = $val;
		}
	}

	return $result;
}
