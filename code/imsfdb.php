<?php
require_once( 'getqueries.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );


class ImfsDb {

	public $canReindex = false;
	public $unconstrained;
	public $queries;
	public $messages = array();
	public $lookForMissingKeys = true;
	public $lookForExtraKeys = false;
	public $semver;
	public $stats;
	public $oldEngineTables;
	public $newEngineTables;
	public $timings;
	private $initialized = false;
	private $reindexingInstructions;
	private $hasHrTime;

	/**
	 * @throws ImfsException
	 */
	public function init() {
		global $wpdb;
		if ( ! $this->initialized ) {
			$this->initialized   = true;
			$this->timings       = array();
			$this->queries       = getQueries();
			$this->semver        = getMySQLVersion();
			$this->canReindex    = $this->semver->canreindex;
			$this->unconstrained = $this->semver->unconstrained;
			if ( $this->canReindex ) {
				$this->reindexingInstructions = getReindexingInstructions( $this->semver );
			}

			if ( $this->canReindex ) {
				$this->stats     = $this->getStats();
				$oldEngineTables = array();
				$newEngineTables = array();
				/* make sure we only upgrade the engine on WordPress's own tables */
				$wpTables = array_flip( $wpdb->tables( 'blog', true ) );
				if ( is_main_site() ) {
					$wpTables = $wpTables + array_flip( $wpdb->tables( 'global', true ) );
				}
				$tablesData = $this->stats[2];
				foreach ( $tablesData as $name => $info ) {
					$activeTable = false;
					if ( isset( $wpTables[ $name ] ) ) {
						$activeTable = true;
					}
					if ( 0 === strpos( $name, $wpdb->prefix ) ) {
						$activeTable = true;
					}
					if ( $activeTable ) {
						if ( $info->ENGINE !== 'InnoDB' ) {
							$oldEngineTables[] = $name;
						} else {
							$newEngineTables[] = $name;
						}
					}
				}
				$this->oldEngineTables = $oldEngineTables;
				$this->newEngineTables = $newEngineTables;
			}
			try {
				$this->hasHrTime = function_exists( 'hrtime' );
			} catch ( Exception $ex ) {
				$this->hasHrTime = false;
			}
		}
	}

	/**
	 * @return array server information
	 * @throws ImfsException
	 */
	public function getStats() {
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

	/** run a SELECT
	 *
	 * @param $sql
	 *
	 * @return array|object|null
	 * @throws ImfsException
	 */
	public function get_results( $sql, $doTiming = false ) {
		global $wpdb;
		$thentime = $doTiming ? $this->getTime() : - 1;
		$results  = $wpdb->get_results( $sql, OBJECT_K );
		if ( false === $results || $wpdb->last_error ) {
			throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
		}
		if ( $doTiming ) {
			$delta           = round( floatval( $this->getTime() - $thentime ), 3 );
			$this->timings[] = array( 't' => $delta, 'q' => $sql );
		}

		return $results;

	}

	public function getTime() {
		try {
			/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
			return $this->hasHrTime ? hrtime( true ) * 0.000000001 : time();
		} catch ( Exception $ex ) {
			return time();
		}
	}

	/**
	 * @param $action string  'enable' or 'disable'
	 * @param $tables array  tables like ['postmeta','termmeta']
	 * @param $alreadyPrefixed bool false if wp_ prefix needs to be added to table names
	 *
	 * @return string message to display in SettingNotice.
	 * @throws ImfsException
	 *
	 */
	public function rekeyTables( $action, array $tables, $alreadyPrefixed = false ) {
		$count = 0;
		if ( count( $tables ) > 0 ) {
			try {
				$this->lock( $tables, $alreadyPrefixed );
				foreach ( $tables as $name ) {
					$this->rekeyTable( $action, $name, $alreadyPrefixed );
					$count ++;
				}
			} finally {
				$this->unlock();
			}
		}
		if ( $action === 'enable' ) {
			$msg = __( 'High-performance keys added to %d tables.', index_wp_mysql_for_speed_domain );
		} else {
			$msg = __( 'Keys on %d tables reverted to WordPress standard.', index_wp_mysql_for_speed_domain );
		}

		return sprintf( $msg, $count );

	}

	/** Put the site into maintenance mode and lock a list of tables
	 *
	 * @param $tableList
	 * @param $alreadyPrefixed
	 *
	 * @throws ImfsException
	 */
	public function lock( $tableList, $alreadyPrefixed ) {
		global $wpdb;
		if ( ! is_array( $tableList ) || count( $tableList ) === 0 ) {
			throw new ImfsException( "Invalid attempt to lock. At least one table must be locked" );
		}
		$tablesToLock = array();
		foreach ( $tableList as $tbl ) {
			if ( ! $alreadyPrefixed ) {
				$tbl = $wpdb->prefix . $tbl;
			}
			array_push( $tablesToLock, $tbl );
		}

		/* always specify locks in the same order to avoid starving the philosophers */
		sort( $tablesToLock );
		$tables = array();
		foreach ( $tablesToLock as $tbl ) {
			array_push( $tables, $tbl . ' WRITE' );
		}

		$this->enterMaintenanceMode();
		$q = "LOCK TABLES " . implode( ', ', $tables );
		$this->query( $q );
	}

	/**
	 * @param int $duration how many seconds until maintenance expires
	 */
	public function enterMaintenanceMode( $duration = 60 ) {
		$maintenanceFileName = ABSPATH . '.maintenance';
		if ( is_writable( ABSPATH ) ) {
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

			file_put_contents( $maintenanceFileName, implode( PHP_EOL, $maintain ) );
		}
	}

	/** run a query*
	 *
	 * @param $sql
	 *
	 * @return bool|int
	 * @throws ImfsException
	 */
	public function query( $sql, $doTiming = false ) {
		global $wpdb;
		$thentime = $doTiming ? $this->getTime() : - 1;
		$results  = $wpdb->query( $sql );
		if ( false === $results || $wpdb->last_error ) {
			throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
		}
		if ( $doTiming ) {
			$delta           = round( floatval( $this->getTime() - $thentime ), 3 );
			$this->timings[] = array( 't' => $delta, 'q' => $sql );
		}

		return $results;

	}

	/** Redo the keys on the selected table.
	 *
	 * @param string $action "enable" or "disable"
	 * @param string $name table name without prefix
	 *
	 * @throws ImfsException
	 */
	public function rekeyTable( $action, $name, $alreadyPrefixed = false ) {
		global $wpdb;
		$unprefixedName = $alreadyPrefixed ? ImfsStripPrefix( $name ) : $name;
		$prefixedName   = $alreadyPrefixed ? $name : $wpdb->prefix . $name;
		$block          = $this->reindexingInstructions[ $unprefixedName ];
		$stmts          = $block[ $action ];
		if ( $action ) {
			foreach ( $stmts as $fragment ) {
				set_time_limit( 120 );
				$q = "ALTER TABLE " . $prefixedName . " " . $fragment;
				$this->query( $q, true );
			}
		}
	}

	/** Undo lock.  This is ideally called from a finally clause.
	 * @throws ImfsException
	 */
	public function unlock() {
		$this->query( "UNLOCK TABLES" );
		$this->leaveMaintenanceMode();
	}

	public function leaveMaintenanceMode() {
		$maintenanceFileName = ABSPATH . '.maintenance';
		if ( is_writable( ABSPATH ) ) {
			unlink( $maintenanceFileName );
		}
	}

	public function repairTables( $action, $tables ) {
		$count = 0;
		$names = array();
		foreach ($tables as $table) {
			$splits  = explode( ' ', $table, 2 );
			$names[] = $splits[0];
		}
		if ( count( $names ) > 0 ) {
			try {
				$this->lock( $names, true );
				foreach ( $names as $name ) {
					$this->repairKeys( $name );
					$count ++;
				}
			} finally {
				$this->unlock();
			}
		}
		$msg = __( 'Keys on %d tables reset.', index_wp_mysql_for_speed_domain );

		return sprintf( $msg, $count );

	}

	/** Repair a table by restoring its WordPress default indexes, removing the ones we put in.
	 *
	 * @param $name  string like "wp_postmeta" not "postmeta"
	 *
	 * @return bool
	 */
	public function repairKeys( $name ) {
		$ddl             = array();
		$before          = '';
		$after           = '';
		$standardIndexes = getStandardIndexes();
		$shortName       = ImfsStripPrefix( $name );
		if ( ! array_key_exists( $shortName, $standardIndexes ) ) {
			return false;
		}
		$keydefs         = $this->getKeyDDL( $name, false );
		$alter           = '';
		$standardIndexes = $standardIndexes[ $shortName ];

		/* get the union of the list of keynames in the two places... actual table and standard */
		$keynames = array();
		foreach ( $keydefs as $keyname => $_ ) {
			$keynames[ $keyname ] = 1;
		}
		foreach ( $standardIndexes as $keyname => $_ ) {
			$keynames[ $keyname ] = 1;
		}

		foreach ( $keynames as $keyname => $_ ) {
			/* ignore indexes put in there by woocommerce. */
			if ( strpos( $keyname, 'woo_' ) ) {
				continue;
			}
			$keyinfo = null;
			if ( array_key_exists( $keyname, $keydefs ) ) {
				$keyinfo = $keydefs [ $keyname ];
			}
			if ( $keyinfo ) {
				$alter = $keyinfo->alter;

				if ( $keyinfo->is_autoincrement ) {
					$before = sprintf( "ADD UNIQUE KEY imfsdb_unique(%s)", $keyinfo->autoincrement_column );
					$after  = "DROP KEY imfsdb_unique";
				}
				if ( $keyinfo->add != $standardIndexes ) {
					/* this index isn't in the standard list */
					$ddl[] = $keyinfo->drop;
				}
			}
			$standardIndex = '';
			if ( array_key_exists( $keyname, $standardIndexes ) ) {
				$standardIndex = $standardIndexes[ $keyname ];
			}
			if ( strlen( $standardIndex ) > 0 ) {
				$ddl[] = $standardIndex;
			}
		}

		if ( strlen( $before ) > 0 ) {
			set_time_limit( 120 );
			$this->queryIgnore( $alter . ' ' . $before, true );
		}
		foreach ( $ddl as $stmt ) {
			set_time_limit( 120 );
			$this->queryIgnore( $alter . ' ' . $stmt, true );
		}

		if ( strlen( $after ) > 0 ) {
			set_time_limit( 120 );
			$this->queryIgnore( $alter . ' ' . $after, true );
		}

		return true;
	}

	/**
	 * Retrieve DML for the keys in the named table.
	 *
	 * @param string $name table name (without prefix)
	 *
	 * @return array|object|null
	 * @throws ImfsException
	 */
	public function getKeyDDL( $name, $addPrefix = true ) {
		global $wpdb;
		if ( $addPrefix ) {
			$name = $wpdb->prefix . $name;
		}
		$stmt = $wpdb->prepare( $this->queries['indexes'], $name );

		return $this->get_results( $stmt );
	}

	/**
	 * @param $sql string
	 * @param bool $doTiming
	 */
	private function queryIgnore( $sql, $doTiming = false ) {
		try {
			$this->query( $sql, $doTiming );

			return;
		} catch ( ImfsException $ex ) {
			return;
			/* empty intentionally */
		}
	}

	public function getRekeying() {
		global $wpdb;
		$enableList     = array();
		$disableList    = array();
		$errorList      = array();
		$originalTables = $this->tables();
		/* don't process tables still on old storage engins */
		$tables = array();
		foreach ( $originalTables as $name ) {
			if ( ! in_array( $wpdb->prefix . $name, $this->oldEngineTables ) ) {
				$tables[] = $name;
			}
		}
		/* any rekeyable tables? */
		if (is_array($tables) && count($tables) > 0) {
			try {
				$this->lock( $tables, false );
				foreach ( $tables as $name ) {
					$canEnable   = $this->checkTable( 'enable', $name );
					$enableMsgs  = $this->clearMessages();
					$canDisable  = $this->checkTable( 'disable', $name );
					$disableMsgs = $this->clearMessages();
					if ( $canEnable && ! $canDisable ) {
						$enableList[] = $name;
					} else if ( $canDisable && ! $canEnable ) {
						$disableList[] = $name;
					} else {
						$msg   = __( '%s has unexpected keys, so you cannot rekey it without resetting it first.', index_wp_mysql_for_speed_domain );
						$msg   = sprintf( $wpdb->prefix . $msg, $name );
						$delim = '<br />&emsp;';
						if ( ! $canEnable ) {
							$msg = $msg . $delim . implode( $delim, $enableMsgs );
						}
						$errorList[ $name ] = $msg;
					}
				}
			} finally {
				$this->unlock();
			}
		}

		return array(
			'enable'  => $enableList,
			'disable' => $disableList,
			'reset'   => $errorList,
			'upgrade' => $this->oldEngineTables
		);
	}

	/** List of tables to manipulate
	 * @return array tables manipulated by this module
	 */
	public function tables( $prefixed = false ) {
		$result = array();
		global $wpdb;
		foreach ( $this->reindexingInstructions as $name => $stmts ) {
			if ( is_array( $stmts ) && array_key_exists( 'tablename', $stmts ) && $name === $stmts['tablename'] ) {
				$mainSiteOnly = array_key_exists( 'mainSiteOnly', $stmts ) && $stmts['mainSiteOnly'];
				if ( is_main_site() || ! $mainSiteOnly ) {
					$result[] = $prefixed ? $wpdb->prefix . $name : $name;
				}
			}
		}

		return $result;
	}

	/** Check whether a table is ready to be acted upon
	 *
	 * @param $action "enable" or "disable"
	 * @param $name
	 *
	 * @return bool
	 * @throws ImfsException
	 */
	public function checkTable( $action, $name ) {
		global $wpdb;
		$block          = $this->reindexingInstructions[ $name ];
		$checks         = $block[ 'check.' . $action ];
		$table          = $wpdb->prefix . $name;
		$result         = true;
		$presentIndexes = $this->getKeyDDL( $name );
		if ( $this->lookForMissingKeys ) {
			foreach ( $checks as $index => $desc ) {
				if ( ! $desc && array_key_exists( $index, $presentIndexes ) ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name */
						__( 'The key %2$s exists when it should not.', index_wp_mysql_for_speed_domain ),
						$table, $index
					);
					array_push( $this->messages, $msg );
					$result = false;
				} else if ( $desc && ! array_key_exists( $index, $presentIndexes ) ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name */
						__( 'The expected key %2$s does not exist.', index_wp_mysql_for_speed_domain ),
						$table, $index
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}
		if ( $this->lookForExtraKeys ) {
			foreach ( $presentIndexes as $index => $desc ) {
				if ( ! array_key_exists( $index, $checks ) ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name */
						__( 'Found an unexpected key %2$s.', index_wp_mysql_for_speed_domain ),
						$table, $index
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}
		foreach ( $checks as $index => $stmt ) {
			if ( array_key_exists( $index, $presentIndexes ) ) {
				$desc = $presentIndexes[ $index ];
				if ( strlen( $stmt ) > 0 && $desc->add !== $stmt ) {
					$msg = sprintf(
					/* translators: %1$s is table name, %2$s is key (index) name, %4$s is expected key, %3$s is actual */
						__( 'Found an unexpected definition for key %2$s. It should be %4$s, but is %3$s.', index_wp_mysql_for_speed_domain ),
						$table, $index, $desc->add, $stmt
					);
					array_push( $this->messages, $msg );
					$result = false;
				}
			}
		}

		return $result;
	}

	/** Resets the messages in this class and returns the previous messages.
	 * @return array
	 */
	public function clearMessages() {
		$msgs           = $this->messages;
		$this->messages = array();

		return $msgs;
	}

	/**
	 * @throws ImfsException
	 */
	public function upgradeStorageEngine( $list ) {
		$counter = 0;
		try {
			$this->lock( $list, true );
			foreach ( $list as $table ) {
				$this->upgradeTableStorageEngine( $table );
				$counter ++;
			}
			$msg = __( '%d tables upgraded', index_wp_mysql_for_speed_domain );
		} catch ( ImfsException $ex ) {
			$msg = implode( ', ', $this->clearMessages() );
			throw ( $ex );
		} finally {
			$this->unlock();
		}

		return sprintf( $msg, $counter );
	}

	/**
	 * @throws ImfsException
	 */
	public function upgradeTableStorageEngine( $table ) {
		set_time_limit( 120 );
		$sql = 'ALTER TABLE ' . $table . ' ENGINE=InnoDb';
		$this->query( $sql, true );

		return true;
	}
}

class ImfsException extends Exception {
	protected $message;
	private $query;

	public function __construct( $message, $query = '', $code = 0, $previous = null ) {
		global $wpdb;
		$this->query = $query;
		parent::__construct( $message, $code, $previous );
		$wpdb->flush();
	}

	public function __toString() {
		return __CLASS__ . ": [$this->code]: $this->message in $this->query\n";
	}
}
