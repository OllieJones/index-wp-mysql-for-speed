<?php

/**
 * Index WP MySQL For Speed plugin
 *
 * ## OPTIONS
 *
 * [--all]
 * : Process all eligible tables.
 *
 * [--exclude=<table[,table...]]
 * : Exclude named tables.
 *
 * [--format=<format>]
 * : The display format. table, csv, json, yaml.
 *
 * [--blogid=<blogid>]
 * : The blog id for a multisite network. --url also selects the blog if you prefer.
 *
 */
class ImsfCli extends WP_CLI_Command {

	public $db;
	public $domain = index_wp_mysql_for_speed_domain;
	public $assoc_args;
	public $cmd = 'wp index-mysql';
	public $allSwitch = false;
	public $rekeying;
	public $errorMessages = array();

	/**
	 * Upload diagnostic metadata to plugin developers' site.
	 */
	function upload_metadata( $args, $assoc_args ) {
		$this->setupCliEnvironment( $args, $assoc_args );
		$id = imfs_upload_stats( $this->db );
		WP_CLI::log( __( 'Metadata uploaded to id ', $this->domain ) . $id );
		restore_current_blog();
	}

	private function setupCliEnvironment( $args, $assoc_args, $preamble = true ) {
		global $wp_version;
		$this->allSwitch  = ! empty( $assoc_args['all'] );
		$this->assoc_args = $assoc_args;
		if ( is_multisite() ) {
			$restoreBlogId = get_current_blog_id();
			if ( ! empty( $assoc_args['blogid'] ) ) {
				$this->cmd .= ' --blogid=' . $assoc_args['blogid'];
				switch_to_blog( $assoc_args['blogid'] );
			} else {
				switch_to_blog( $restoreBlogId );
			}
		} else {
			$restoreBlogId = 0;
		}
		$this->db = new ImfsDb();
		$this->db->init();
		$this->rekeying = $this->db->getRekeying();

		/* the rekeying array for `reset` is a little strange: it has error messages after table names */
		if ( array_key_exists( 'reset', $this->rekeying ) && count( $this->rekeying['reset'] ) > 0 ) {
			$trimmed = array();
			foreach ( $this->rekeying['reset'] as $item ) {
				$splits                = explode( ' ', $item, 2 );
				$trimmed[]             = $splits[0];
				$this->errorMessages[] = str_replace( '<br />&emsp;', PHP_EOL . '  ', $item );
			}
			$this->rekeying['reset'] = $trimmed;
		}

		if ( $preamble ) {
			$wpDescription = imfsGetWpDescription( $this->db );
			WP_CLI::log( __( 'Index WP MySQL For Speed', $this->domain ) . ' ' . index_wp_mysql_for_speed_VERSION_NUM );
			$versions = 'MySQL:' . $wpDescription['mysqlversion'] . ' WordPress:' . $wp_version . ' php:' . phpversion();
			WP_CLI::log( __( 'Versions', $this->domain ) . ' ' . $versions );
		}

		if ( ! $this->db->canReindex ) {
			$fmt = __( 'Sorry, you cannot use this plugin with your version of MySQL.', $this->domain ) . ' ' .
			       __( 'Your MySQL version is outdated. Please consider upgrading,', $this->domain );
			WP_CLI::exit( $fmt );

		}
		if ( $preamble && ! $this->db->unconstrained ) {
			$fmt = __( 'Upgrading your MySQL server will give you better performance when you add high-performance keys. Please consider upgrading.', $this->domain );
			WP_CLI::warning( $fmt );
		}

	}

	/**
	 * Display version information.
	 *
	 */
	function version( $args, $assoc_args ) {
		$this->setupCliEnvironment( $args, $assoc_args );
		$wpDescription = imfsToResultSet( imfsGetWpDescription( $this->db ), 'Item', 'Value' );
		WP_CLI\Utils\format_items( $assoc_args['format'], $wpDescription, array( 'Item', 'Value' ) );
	}

	/**
	 * Add high-performance keys.
	 */
	function enable( $args, $assoc_args ) {
		$action = 'enable';
		$this->setupCliEnvironment( $args, $assoc_args );
		$this->doRekeying( $args, $assoc_args, $action );
	}

	private function doRekeying( $args, $assoc_args, $action, $alreadyPrefixed = true ) {
		$tbls = $this->getTablesToProcess( $args, $assoc_args, $action );

		foreach ( $tbls as $tbl ) {
			$this->db->timings = array();
			$arr               = array( $tbl );
			$this->db->rekeyTables( $action, $arr, $alreadyPrefixed );
			WP_CLI::log( $this->reportCompletion( $action, $tbl ) );
		}
	}

	/** Filter and validate the list of tables.
	 *
	 * @param $args array incoming args
	 * @param $assoc_args array incoming switch args
	 * @param $action string enable/disable/rekey/reset
	 *
	 * @return array list of tables
	 */
	private function getTablesToProcess( $args, $assoc_args, $action ) {

		$alreadyPrefixed = ( $action === 'upgrade' || $action === 'reset' );
		$tbls            = $this->rekeying[ $action ];
		$tbls            = $this->addPrefixes( $tbls, $alreadyPrefixed );
		$res             = array();
		$exclude         = array();
		if ( ! empty( $assoc_args['exclude'] ) ) {
			$exclude = explode( ',', $assoc_args['exclude'] );
		}

		if ( $this->allSwitch ) {
			$args = $tbls;
		}

		$err = [];
		foreach ( $args as $arg ) {
			if ( in_array( $arg, $tbls ) ) {
				if ( ! in_array( $arg, $exclude ) ) {
					$res[] = $arg;
				}
			} else {
				$err[] = $arg;
			}
		}
		if ( count( $err ) > 0 ) {
			$fmt = __( 'These tables are not not found or not eligible to', $this->domain ) . ' ' . $action . ': ' . implode( ' ', $err ) . '.';
			WP_CLI::error( $fmt );
		}
		if ( count( $res ) == 0 ) {
			$fmt = __( 'No tables are eligible to', $this->domain ) . ' ' . $action . '.';
			WP_CLI::error( $fmt );
		}

		return $res;
	}

	private function addPrefixes( $tbls, $alreadyPrefixed ) {
		global $wpdb;
		if ( $alreadyPrefixed ) {
			return $tbls;
		}
		$res = [];
		foreach ( $tbls as $tbl ) {
			$res[] = $wpdb->prefix . $tbl;
		}

		return $res;
	}

	/** Display a line showing completion, with time.
	 *
	 * @param string $action
	 * @param string $tbl
	 *
	 * @return string
	 */
	private function reportCompletion( $action, $tbl ) {
		$time    = 0.0;
		$queries = 0;
		foreach ( $this->db->timings as $item ) {
			$time += $item['t'];
			$queries ++;
		}
		$this->db->timings = array();

		return sprintf( "%s %s %s (%d %s, %ss)",
			$action, $tbl, __( "complete.", $this->domain ), $queries, __( 'MySQL commands', $this->domain ), number_format_i18n( $time, 2 ) );
	}

	/**
	 * Remove high-performance keys, reverting to WordPress standard.
	 */
	function disable( $args, $assoc_args ) {
		$action = 'disable';
		$this->setupCliEnvironment( $args, $assoc_args );
		$this->doRekeying( $args, $assoc_args, $action );
	}

	/**
	 * Upgrade the storage engine for a list of tables.
	 */
	function upgrade( $args, $assoc_args ) {
		$action = 'upgrade';
		$this->setupCliEnvironment( $args, $assoc_args );
		$tbls = $this->getTablesToProcess( $args, $assoc_args, $action );
		try {
			$this->db->lock( $tbls, true );
			foreach ( $tbls as $tbl ) {
				$this->db->lock( array( $tbl ), true );
				$this->db->timings = array();
				$arr               = array( $tbl );
				$this->db->upgradeStorageEngine( $arr );
				WP_CLI::log( $this->reportCompletion( $action, $tbl ) );
			}
		} catch ( ImfsException $ex ) {
			WP_CLI::error( $ex->getMessage() );
		} finally {
			$this->db->unlock();
		}
	}

	/**
	 * Reset tables with unexpected keys to their WordPress standard key configuration.
	 */
	function reset( $args, $assoc_args ) {
		$action = 'reset';
		$this->setupCliEnvironment( $args, $assoc_args );
		$tbls = $this->getTablesToProcess( $args, $assoc_args, $action );
		try {
			$this->db->lock( $tbls, true );
			foreach ( $tbls as $tbl ) {
				$this->db->timings = array();
				$this->db->repairKeys( $tbl );
				WP_CLI::log( $this->reportCompletion( $action, $tbl ) );
			}
		} catch ( ImfsException $ex ) {
			WP_CLI::error( $ex->getMessage() );
		} finally {
			$this->db->unlock();
		}
	}

	/**
	 * Show indexing status.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	function status( $args, $assoc_args ) {
		$this->setupCliEnvironment( $args, $assoc_args );
		$fmt = __( 'You cannot rekey some tables without resetting their keys first.', $this->domain ) . ' ' .
		       __( 'This often means they have already been rekeyed by some other plugin or workflow.', $this->domain );
		$this->showCommandLine( 'reset', $fmt, true, true );
		$fmt = __( 'These database tables need upgrading to InnoDB with the Dynamic row format, MySQL\'s latest storage scheme.', $this->domain );
		$this->showCommandLine( 'upgrade', $fmt, true, true );
		$fmt = __( 'Add high-performance keys to these tables to make your WordPress database faster.', $this->domain );
		$this->showCommandLine( 'enable', $fmt, false );

		$fmt = __( 'Your WordPress tables now have high-performance keys.', $this->domain ) . ' ' .
		       __( 'Revert the keys on these tables to restore WordPress\'s defaults.', $this->domain );

		$this->showCommandLine( 'disable', $fmt, false, false );
	}

	/** display  sample command line to user.
	 *
	 * @param $action string  enable/disable/rekey/reset.
	 * @param $caption string prefix text.
	 * @param $warning boolean display warning not log.
	 * @param $alreadyPrefixed boolean  tables in list already have wp_ style prefixes.
	 */
	private function showCommandLine( $action, $caption, $warning = false, $alreadyPrefixed = false ) {
		$array = $this->rekeying;
		if ( array_key_exists( $action, $array ) && count( $array[ $action ] ) > 0 ) {
			$tbls = $this->addPrefixes( $array[ $action ], $alreadyPrefixed );
			if ( $warning ) {
				WP_CLI::warning( $caption );
			} else {
				WP_CLI::log( $caption );
			}
			WP_CLI::log( $this->getCommand( $action, $tbls ) );
			if ( count( $this->errorMessages ) > 0 ) {
				WP_CLI::log( implode( PHP_EOL, $this->errorMessages ) );
				$this->errorMessages = array();
			}
			WP_CLI::log( '' );
		}
	}

	/** format actual cmd
	 *
	 * @param $cmd string
	 * @param $tbls array
	 *
	 * @return string|void
	 */
	private function getCommand( $cmd, $tbls ) {
		if ( count( $tbls ) > 0 ) {
			$list = implode( ' ', $tbls );

			return sprintf( "Use this command: %s %s %s", $this->cmd, $cmd, $list );
		}
	}

	/**
	 * Display information about tables.
	 */
	function tables( $args, $assoc_args ) {
		global $wpdb;
		$this->setupCliEnvironment( $args, $assoc_args );
		$list = $this->db->stats[1];
		$hdrs = array();
		$row  = reset( $list );
		foreach ( $row as $key => $val ) {
			$hdrs[] = $key;
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $list, $hdrs );

		$list   = array();
		$tables = $this->db->tables();
		foreach ( $tables as $table ) {
			$outrow = array();
			$ddls   = $this->db->getKeyDDL( $table );
			foreach ( $ddls as $keyname => $row ) {
				$outrow['table']      = $wpdb->prefix . $table;
				$outrow['key']        = $keyname;
				$outrow['definition'] = $row->add;
				$list[]               = $outrow;
			}
		}
		$hdrs = array();
		$row  = reset( $list );
		foreach ( $row as $key => $val ) {
			$hdrs[] = $key;
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $list, $hdrs );
	}


}

WP_CLI::add_command( 'index-mysql', 'ImsfCli' );