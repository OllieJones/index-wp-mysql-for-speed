<?php

class renderMonitor {

	private $monitor;
	private $queryLog;
	private $prefix;
	private $classPrefix = 'rendermon';
	private $dateFormat;

	public function __construct( $monitor ) {
		$this->monitor    = $monitor;
		$this->prefix     = index_wp_mysql_for_speed_monitor . '-Log-';
		$this->dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}

	static function renderMonitors( $list = null ) {
		$renders  = array();
		$monitors = RenderMonitor::getMonitors();

		foreach ( $monitors as $monitor ) {
			if ( is_null( $list )
			     || ( is_string( $list ) && $monitor === $list )
			     || ( is_array( $list ) && array_search( $monitor, $list ) !== false ) ) {
				$rm        = new RenderMonitor( $monitor );
				$renders[] = $rm->render();
			}
		}

		return implode( "\r", $renders );
	}

	static function getMonitors() {
		global $wpdb;
		$prefix = index_wp_mysql_for_speed_monitor . '-Log-';
		$result = [];
		$q      = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '" . $prefix . "%' AND LENGTH(option_value) > 0";
		$rs     = $wpdb->get_results( $q );
		foreach ( $rs as $r ) {
			$name     = str_replace( $prefix, '', $r->option_name );
			$result[] = $name;
		}

		return $result;
	}

	function render() {
		$this->load();
		$c      = $this->classPrefix;
		$prefix = "<div class=\"$c index-wp-mysql-for-speed-content-container\">";

		return $prefix . $this->top() . $this->table() . "</div>";
	}

	public function load(): renderMonitor {
		$this->queryLog          = json_decode( get_option( $this->prefix . $this->monitor ) );
		$this->queryLog->queries = (array) $this->queryLog->queries;

		return $this;
	}

	public function top() {
		$l     = $this->queryLog;
		$c     = $this->classPrefix;
		$times = $this->summary();
		list ( $allNineFive, $avgNineFive, $maxNineFive, $allMedian, $avgMedian, $maxMedian ) = $this->stats();
		$res = <<<END
		<h1 class="$c h1">$this->monitor</h1>
		<div class="$c top time">$times</div>
		<div class="$c top stats">
		<div>
		<span class="$c stat caption">95th percentiles:</span>
		<span class="$c stat number">All: $allNineFive</span>
		<span class="$c stat number">Average: $avgNineFive</span>
		<span class="$c stat number">Max: $maxNineFive</span>
		</div>
		<span class="$c stat caption">Medians:</span>
		<span class="$c stat number">All: $allMedian</span>
		<span class="$c stat number">Average: $avgMedian</span>
		<span class="$c stat number">Max: $maxMedian</span>
		</div>
END;

		return $res;
	}

	public function summary(): string {
		$l          = $this->queryLog;
		$c          = $this->classPrefix;
		$start      = wp_date( $this->dateFormat, $l->start );
		$end        = wp_date( $this->dateFormat, $l->end );
		$querycount = number_format_i18n( $l->querycount, 0 );

		return <<<END
		<span class="$c start">$start</span>―<span class="$c end">$end</span> <span class="$c count">$querycount</span> queries.
END;
	}

	public function stats() {
		$allTimes = [];
		$avgTimes = [];
		$maxTimes = [];
		$queries  = $this->queryLog->queries;
		foreach ( $queries as $qid => $q ) {
			$maxTimes[] = $q->maxt;
			$avgTimes[] = $q->t / $q->n;
			foreach ( $q->ts as $t ) {
				$allTimes[] = $t;
			}
		}

		sort( $allTimes );
		sort( $avgTimes );
		sort( $maxTimes );

		$allNinefive = $this->percentile( $allTimes, 0.95 );
		$avgNinefive = $this->percentile( $avgTimes, 0.95 );
		$maxNineFive = $this->percentile( $maxTimes, 0.95 );
		$allMedian   = $this->percentile( $allTimes, 0.5 );
		$avgMedian   = $this->percentile( $avgTimes, 0.5 );
		$maxMedian   = $this->percentile( $maxTimes, 0.5 );

		return [ $allNinefive, $avgNinefive, $maxNineFive, $allMedian, $avgMedian, $maxMedian ];

	}

	/** percentile
	 *
	 * @param array $a dataset
	 * @param number $p percentile as fraction 0-1
	 *
	 * @return number
	 */
	public function percentile( $a, $p ) {
		$n = count( $a );
		$i = floor( $n * $p );
		if ( $i >= $n ) {
			$i = $n - 1;
		}

		return $a[ $i ];
	}

	public function table() {
		$l   = $this->queryLog;
		$c   = $this->classPrefix;
		$res = '';
		$row = $this->row( [ "Where", "Count", "Total", "Mean", "How", "Query", "Actual" ], "query header row" );
		$res .= <<<END
		<div class="$c query table-container"><table class="$c query table"><thead>
		<tr>$row</tr></thead><tbody>
END;

		foreach ( $l->queries as $q ) {
			if ( $q->n > 0 ) {
				$row   = [];
				$row[] = $q->a ? [ "Dashboard", 1 ] : [ "Site", 0 ];
				$row[] = [ number_format_i18n( $q->n, 0 ), $q->n ];
				$row[] = $this->timeCell( $q->t );
				$row[] = $this->timeStatsCell( $q->ts );
				$row[] = $this->queryPlan( $q );
				$row[] = $q->f;
				$row[] = $q->q;
				$res   .= "</tr>" . $this->row( $row, "query data row" ) . "</tr>";
			}
		}

		$res .= "</tbody></table></div>";

		return $res;
	}

	public function row( $a, $class = 'row' ) {
		$res = '';
		foreach ( $a as $item ) {
			$res .= $this->cell( $item, $class );
		}

		return $res;
	}

	public function cell( $item, $class ) {
		$c = $this->classPrefix;
		if ( is_string( $item ) ) {
			$item = htmlspecialchars( $item );

			return <<<END
		<td class="$c $class">$item</td>
END;
		}
		if ( is_array( $item ) && count( $item ) === 2 ) {
			return <<<END
		<td class="$c $class" data-order="$item[1]">$item[0]</td>
END;
		}
		$item = htmlspecialchars( implode( '', $item ) );

		return <<<END
		<td class="$c $class">$item</td>
END;

	}

	/** get cell data for microsecond times
	 *
	 * @param number $time
	 *
	 * @return array
	 */
	public function timeCell( $time ) {
		$renderTime  = $time * 0.000001;
		$unit        = $this->getTimeUnit( $renderTime );
		$displayTime = number_format_i18n( $renderTime / $unit[0], $unit[2] ) . $unit[1];

		return [ $displayTime, - $renderTime ];
	}

	public function getTimeUnit( $timeSeconds ) {
		if ( $timeSeconds >= 3600 * 0.9 ) {
			$unit = [ 3600, 'h', 2 ];
		} else if ( $timeSeconds >= 60 * 0.9 ) {
			$unit = [ 60, 'm', 2 ];
		} else if ( $timeSeconds >= 0.9 ) {
			$unit = [ 1, 's', 2 ];
		} else if ( $timeSeconds >= 0.1 ) {
			$unit = [ 0.001, 'ms', 0 ];
		} else if ( $timeSeconds >= 0.01 ) {
			$unit = [ 0.001, 'ms', 1 ];
		} else if ( $timeSeconds >= 0.001 ) {
			$unit = [ 0.001, 'ms', 2 ];
		} else {
			$unit = [ 0.000001, 'µs', 0 ];
		}

		return $unit;
	}

	/** get cell data for microsecond times
	 *
	 * @param number $time
	 *
	 * @return array
	 */
	public function timeStatsCell( $times ) {
		$time        = $this->mean( $times ) * 0.000001;
		$mad         = $this->mad( $times ) * 0.000001;
		$unit        = $this->getTimeUnit( $time );
		$displayTime = number_format_i18n( $time / $unit[0], $unit[2] ) . '±' .
		               number_format_i18n( $mad / $unit[0], $unit[2] ) . $unit[1];

		return [ $displayTime, - $time ];
	}

	/** arithmetic mean
	 *
	 * @param array $a dataset
	 *
	 * @return number
	 */
	public function mean( $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}

		return $acc / $n;
	}

	/** mean absolute deviation
	 *
	 * @param array $a dataset
	 *
	 * @return number
	 */
	public function mad( $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}
		$mean = $acc / $n;
		$acc  = 0;
		foreach ( $a as $v ) {
			$acc += abs( $v - $mean );
		}

		return $acc / $n;

	}

	public function queryPlan( $q ) {
		if ( ! $q->e || ! is_array( $q->e ) || count( $q->e ) === 0 ) {
			return '';
		}
		$erow   = $q->e[0];
		$expl   = array();
		$expl[] = $erow->table;
		$expl[] = $erow->key;
		$expl[] = "(" . $erow->type . ":" . $erow->ref . ")";
		foreach ( explode( ";", $erow->Extra ) as $extra ) {
			$extra  = strtolower( $extra );
			$expl[] = strpos( $extra, "no tables" ) !== false ? "No Tables" : null;
			$expl[] = strpos( $extra, "impossible where" ) !== false ? "Impossible Where" : null; // TODO after reading const tables
			$expl[] = strpos( $extra, "using where" ) !== false ? "Filter" : null;
			$expl[] = strpos( $extra, "using index" ) !== false ? "Index" : null;  //TODO Using Index COndition
			$expl[] = strpos( $extra, "using temporary" ) !== false ? "Temp" : null;
			$expl[] = strpos( $extra, "using filesort" ) !== false ? "Sort" : null;
		}

		return implode( " ", $expl );
	}

	static function deleteMonitor( $monitor ) {
		$prefix = index_wp_mysql_for_speed_monitor . '-Log-';
		delete_option( $prefix . $monitor );
	}
}