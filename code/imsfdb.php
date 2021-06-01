<?php
require_once( 'getqueries.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');


class ImfsException extends Exception {
	protected $message;
	private string $query;

	public function __construct( $message, $query, $code = 0, Throwable $previous = null ) {
		global $wpdb;
		$this->query = $query;
		parent::__construct( $message, $code, $previous );
		$wpdb->flush();
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message} in {$this->query}\n";
	}
}

class ImfsDb {

	public array $queries;
	public array $messages;
	public bool $lookForMissingKeys = true;
	public bool $lookForExtraKeys = false;

	public function __construct() {
		$this->queries             = getQueries();
		$this->messages            = array();
	}

	/** run a SELECT
	 *
	 * @param $sql
	 *
	 * @return array|object|null
	 * @throws ImfsException
	 */
	public function get_results( $sql ) {
		global $wpdb;
		$results = $wpdb->get_results( $sql, OBJECT_K );
		if ( false === $results || $wpdb->last_error ) {
			throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
		}

		return $results;

	}

	/** run a query
	 *
	 * @param $sql
	 *
	 * @return bool|int
	 * @throws ImfsException
	 */
	public function query( $sql ) {
		global $wpdb;
		$results = $wpdb->query( $sql );
		if ( false === $results || $wpdb->last_error ) {
			throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
		}

		return $results;

	}

	/**
	 * @return array server information
	 * @throws ImfsException
	 */
	public function getStats(): array {
		global $wpdb;
		$wpdb->flush();
		$output  = array();
		$dbstats = $this->queries['dbstats'];
		foreach ( $dbstats as $q ) {
			$results = $this->get_results( $q );
			array_push( $output, $results );
		}

		return $output;
	}

	/** List of tables to manipulate
	 * @return Generator tables manipulated by this module
	 */
	public function tables( $prefixed = false ): Generator {
		global $wpdb;
		foreach ( $this->queries as $name => $stmts ) {
			if ( is_array( $stmts ) && array_key_exists( 'tablename', $stmts ) && $name === $stmts['tablename'] ) {
				yield $prefixed ? $wpdb->prefix . $name : $name;
			}
		}

	}

	/**
	 * Retrieve DML for the keys in the named table.
	 *
	 * @param string $name table name (without prefix)
	 *
	 * @return array|object|null
	 * @throws ImfsException
	 */
	public function getKeyDML( string $name ) {
		global $wpdb;
		$stmt = $wpdb->prepare( $this->queries['indexes'], $wpdb->prefix . $name );

		return $this->get_results( $stmt );
	}

	/** Check whether a table is ready to be acted upon
	 *
	 * @param $action "enable" or "disable"
	 * @param $name
	 *
	 * @return bool
	 */
	public function checkTable( $action, $name ): bool {
		global $wpdb;
		$block   = $this->queries[ $name ];
		$checks  = $block[ 'check.' . $action ];
		$table   = $wpdb->prefix . $name;
		$result  = true;
		$indexes = $this->getKeyDML( $name );
		if ( $this->lookForMissingKeys ) {
			foreach ( $checks as $index => $desc ) {
				if ( ! array_key_exists( $index, $indexes ) ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name */
						__( 'Table %1s: Cannot find the expected key %2$s. Cannot rekey this table.' ),
						$table, $index
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}
		if ( $this->lookForExtraKeys ) {
			foreach ( $indexes as $index => $desc ) {
				if ( ! array_key_exists( $index, $checks ) ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name */
						__( 'Table %1$s: Found an unexpected key %2$s. Cannot rekey this table' ),
						$table, $index
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}
		foreach ( $checks as $index => $stmt ) {
			if ( array_key_exists( $index, $indexes ) ) {
				$desc = $indexes[ $index ];
				if ( $desc->add !== $stmt ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name, %4$s is expected key, %3$s is actual */
						__( 'Table %1$s: Found an unexpected definition for key %2$s. It should be %4$s, but is %3$s. Cannot rekey this table' ),
						$table, $index, $desc->add, $checks[ $index ]
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}

		return $result;
	}

	/** Redo the keys on the selected table.
	 *
	 * @param string $action "enable" or "disable"
	 * @param string $name table name without prefix
	 *
	 * @throws ImfsException
	 */
	public function rekeyTable( string $action, string $name ) {
		global $wpdb;
		$block = $this->queries[ $name ];
		$stmts = $block[ $action ];
		$table = $wpdb->prefix . $name;
		if ( $action ) {
			foreach ( $stmts as $fragment ) {
				$q = "ALTER TABLE " . $table . " " . $fragment;
				$this->query( $q );
			}
		}
	}

	/** Resets the messages in this class and returns the previous messages.
	 * @return array
	 */
	public function clearMessages(): array {
		$msgs           = $this->messages;
		$this->messages = array();

		return $msgs;
	}

	/** Check the tables for any issues prior to rekeying them.
	 *
	 * @param $action "enable" or "disable"
	 *
	 * @return array|bool strings with messages describing problems, or falsey if no problems
	 */
	public function anyProblems( $action ) {
		$problems = false;
		$this->clearMessages();
		foreach ( $this->tables() as $name ) {
			if ( ! $this->checkTable( $action, $name ) ) {
				$problems = true;
			}
		}
		if ( $problems ) {
			return $this->messages;
		}

		return false;
	}

	public function rekey( $action ) {
		foreach ( $this->tables() as $name ) {
			$this->rekeyTable( $action, $name );
		}

		return true;
	}

	public function lock() {
		$this->enterMaintenanceMode();
		$tables = array();
		foreach ( $this->tables( true ) as $tbl ) {
			array_push( $tables, $tbl . ' ' . 'WRITE' );
		}
		/* always specify locks in the same order to avoid starving the philosophers */
		sort( $tables );
		$q = "LOCK TABLES " . implode( ', ', $tables );
		$this->query( $q );
	}

	public function unlock() {
		$this->query( "UNLOCK TABLES" );
		$this->leaveMaintenanceMode();
	}

	/**
	 * @param int $duration how many seconds until maintenance expires
	 */
	public function enterMaintenanceMode( int $duration = 60 ) {
		$maintenanceFileName = ABSPATH . '.maintenance';
		$maintain     = array();
		$expirationTs = time() + $duration - 600;
		array_push( $maintain,
			'<?php',
			'/* Maintenance Mode was entered by ' .
			index_wp_mysql_for_speed_PLUGIN_NAME .
			' for table reindexing at ' .
			date( "Y-m-d H:i:s" ) . ' */',
			/* notice that maintenance expires ten minutes, 600 sec, after the time in the file. */
			'$upgrading = ' . $expirationTs . ';',
			'?>' );

		$result = file_put_contents( $maintenanceFileName, implode( PHP_EOL, $maintain ) );
	}

	public function leaveMaintenanceMode() {
		$maintenanceFileName = ABSPATH . '.maintenance';
		unlink( $maintenanceFileName );
	}
}