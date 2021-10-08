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
		$res   = <<<END
		<h1 class="$c h1">$this->monitor</h1>
		<div class="$c top time">$times</div>
		<div class="$c top stats">
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

	public function table() {
		$l   = $this->queryLog;
		$c   = $this->classPrefix;
		$res = '';
		$row = $this->row( [
			"Where",
			"Count",
			"Total",
			"Mean",
			"Spread",
			"95th",
			"How",
			"Query",
			"Traceback",
			"Actual",
		], "query header row" );
		$res .= <<<END
		<div class="$c query table-container"><table class="$c query table"><thead>
		<tr>$row</tr></thead><tbody>
END;

		foreach ( $l->queries as $q ) {
			if ( $q->n > 0 && ! is_null( $q->f ) ) {
				$row   = [];
				$row[] = $q->a ? [ "Dashboard", 1 ] : [ "Site", 0 ];
				$row[] = [ number_format_i18n( $q->n, 0 ), $q->n ];
				$row[] = $this->timeCell( $q->t );
				$mean  = $this->mean( $q->ts );
				$unit  = $this->getTimeUnit( $mean * 0.000001 );
				$row[] = $this->timeCell( $mean, $unit );
				$row[] = $this->timeCell( $this->mad( $q->ts ), $unit, '±' );
				$row[] = $this->timeCell( $this->percentile( $q->ts, 0.95 ), $unit );
				$row[] = $this->queryPlan( $q );
				$row[] = $q->f;
				$row[] = $q->c;
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
		if ( is_array( $item ) ) {
			$item = htmlspecialchars( implode( '', $item ) );

			return <<<END
		<td class="$c $class">$item</td>
END;
		}

		return <<<END
		<td class="$c $class"></td>
END;


	}

	/** get cell data for microsecond times
	 *
	 * @param number $time
	 *
	 * @return array
	 */
	public function timeCell( $time, $unit = null, $prefix = '' ) {
		if ( $time === 0.0 ) {
			$displayTime = $prefix !== '' ? '' : '0';

			return [ $displayTime, '0' ];
		}
		$renderTime = $time * 0.000001;
		if ( $unit === null ) {
			$unit = $this->getTimeUnit( $renderTime );
		}
		$displayTime = $prefix . number_format_i18n( $renderTime / $unit[0], $unit[2] ) . $unit[1];

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
			$unit = [ 0.000001, 'μs', 0 ];
		}

		return $unit;
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
		if ( $n === 1 ) {
			return $a[0];
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
		if ( $n === 1 ) {
			return 0.0;
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

	/** percentile
	 *
	 * @param array $a dataset
	 * @param number $p percentile as fraction 0-1
	 *
	 * @return number
	 */
	public function percentile( $a, $p ) {
		$n = count( $a );
		sort( $a );
		$i = floor( $n * $p );
		if ( $i >= $n ) {
			$i = $n - 1;
		}

		return $a[ $i ];
	}

	public function queryPlan( $q ) {
		if ( ! $q->e || ! is_array( $q->e ) || count( $q->e ) === 0 ) {
			return '';
		}
		$erow   = $q->e[0];
		$extras = $erow->Extra;
		if ( false !== stripos( $extras, 'impossible where' ) ) {
			return $extras;
		} else if ( false !== stripos( $extras, 'no tables' ) ) {
			return $extras;
		}

		$expl   = array();
		$expl[] = $erow->table;

		$type = $erow->select_type;
		if ( false === stripos( $type, 'SIMPLE' ) ) {
			$expl[] = $type . ':';
		}

		if ( $erow->key ) {
			$expl[] = '(' . $erow->key . ')';
		}
		if ( $erow->type && $erow->ref ) {
			$expl[] = "[" . $erow->type . ":" . $erow->ref . "]";
		} else if ( $erow->type ) {
			$expl[] = "[" . $erow->type . "]";
		} else if ( $erow->ref ) {
			$expl[] = "[" . $erow->type . "]";
		}
		foreach ( explode( ";", $erow->Extra ) as $extra ) {
			$extra = trim( $extra );
			if ( false !== stripos( $extra, "using where" ) ) {
				$expl[] = "Where" . ';';
			} else if ( false !== stripos( $extra, "using index condition" ) ) {
				$expl[] = "Index Condition" . ';';
			} else if ( false !== stripos( $extra, "using index" ) ) {
				$expl[] = "Index" . ';';
			} else if ( false !== stripos( $extra, "using temporary" ) ) {
				$expl[] = "Temp" . ';';
			} else if ( false !== stripos( $extra, "using filesort" ) ) {
				$expl[] = "Sort" . ';';
			} else if ( strlen( $extra ) > 0 ) {
				$expl[] = $extra . ';';
			}
		}

		return implode( " ", $expl );
	}

	static function deleteMonitor( $monitor ) {
		$prefix = index_wp_mysql_for_speed_monitor . '-Log-';
		delete_option( $prefix . $monitor );
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

	/** standard deviation
	 *
	 * @param array $a dataset
	 *
	 * @return number
	 */
	public function stdev( $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		if ( $n === 1 ) {
			return 0.0;
		}
		$sum   = 0.0;
		$sumsq = 0.0;
		foreach ( $a as $v ) {
			$sum   += $v;
			$sumsq += ( $v * $v );
		}
		$mean = $sum / $n;

		return sqrt( ( $sumsq / $n ) - ( $mean * $mean ) );
	}
}