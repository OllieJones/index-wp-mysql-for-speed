<?php /** @noinspection ALL */
/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */

/**
 * Index WP MySQL For Speed plugin
 *
 * ## OPTIONS
 *
 * [--all]
 * : Process all eligible tables.
 *
 * [--dryrun]
 * : Show SQL statements to change keys but don't run them. If you use this option you can pipe the output to wp db query. For example:
 *     wp index-mysql enable --all --dryrun | wp db query
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
  public $assoc_args;
  public $cmd = 'wp index-mysql';
  public $allSwitch = false;
  public $rekeying;
  public $errorMessages = [];
  public $dryrun;
  private $commentPrefix;

  /**
   * Display version information.
   *
   */
  function version( $args, $assoc_args ) {
    $this->setupCliEnvironment( $args, $assoc_args );
    $wpDescription = ImfsQueries::toResultSet( ImfsQueries::getWpDescription( $this->db ) );
    $format        = array_key_exists( 'format', $assoc_args ) ? $assoc_args['format'] : null;
    WP_CLI\Utils\format_items( $format, $wpDescription, [ 'Item', 'Value' ] );
  }

  /** @noinspection PhpUnusedParameterInspection */
  private function setupCliEnvironment( $args, $assoc_args ) {
    global $wp_version;
    global $wp_db_version;
    $this->allSwitch  = ! empty( $assoc_args['all'] );
    $this->dryrun     = ! empty( $assoc_args['dryrun'] );
    $this->assoc_args = $assoc_args;
    if ( is_multisite() ) {
      $restoreBlogId = get_current_blog_id();
      if ( ! empty( $assoc_args['blogid'] ) ) {
        $this->cmd .= ' --blogid=' . $assoc_args['blogid'];
        switch_to_blog( $assoc_args['blogid'] );
      } else {
        switch_to_blog( $restoreBlogId );
      }
    }
    $this->db = new ImfsDb( index_mysql_for_speed_major_version, index_mysql_for_speed_inception_major_version );
    $this->db->init();
    $this->rekeying = $this->db->getRekeying();

    if ( true ) {
      /*  -- comment is the form of comments in SQL, with dash dash space */
      $this->commentPrefix = $this->dryrun ? '-- ' : '';
      $wpDescription       = ImfsQueries::getWpDescription( $this->db );
      WP_CLI::log( $this->commentPrefix . __( 'Index WP MySQL For Speed', 'index-wp-mysql-for-speed' ) . ' ' . index_wp_mysql_for_speed_VERSION_NUM );
      $versions = ' Plugin:' . $wpDescription['pluginversion'] . ' MySQL:' . $wpDescription['mysqlversion'] . ' WordPress:' . $wp_version . ' WordPress database:' . $wp_db_version . ' php:' . phpversion();
      WP_CLI::log( $this->commentPrefix . __( 'Versions', 'index-wp-mysql-for-speed' ) . ' ' . $versions );
    }

    if ( ! $this->db->canReindex ) {
      if ( $wp_db_version < index_wp_mysql_for_speed_first_compatible_db_version ||
           $wp_db_version > index_wp_mysql_for_speed_last_compatible_db_version ) {
        $fmt = __( 'Sorry, this plugin\'s version is not compatible with your WordPress database version.', 'index-wp-mysql-for-speed' );
      } else {
        $fmt = __( 'Sorry, you cannot use this plugin with your version of MySQL.', 'index-wp-mysql-for-speed' ) . ' ' .
               __( 'Your MySQL version is outdated. Please consider upgrading,', 'index-wp-mysql-for-speed' );
      }
      WP_CLI::exit( $this->commentPrefix . $fmt );
    }
    if ( ! $this->db->unconstrained ) {
      $fmt = __( 'Upgrading your MySQL server to a later version will give you better performance when you add high-performance keys.', 'index-wp-mysql-for-speed' );
      WP_CLI::warning( $this->commentPrefix . $fmt );
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

    $fmt = __( 'Database tables need upgrading to MySQL\'s latest table storage format, InnoDB with dynamic rows.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'upgrade', 'upgrade', $fmt, true, true );

    $fmt = __( 'Add or upgrade high-performance keys to make your WordPress database faster.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'enable', 'enable', $fmt, false, false );

    $fmt = __( 'You set some keys from outside this plugin. You can convert them to this plugin\'s high-performance keys.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'nonstandard', 'enable', $fmt, false, false );
    $fmt = __( 'Or, you can revert them to WordPress\'s standard keys.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'nonstandard', 'disable', $fmt, false, false );

    $fmt = __( 'You added high-performance keys using an earlier version of this plugin. You can update them to the latest high-performance keys.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'old', 'enable', $fmt, false, false );
    $fmt = __( 'Or, you can revert them to WordPress\'s standard keys.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'old', 'disable', $fmt, false, false );

    $fmt = __( 'You successfully added high-performance keys.', 'index-wp-mysql-for-speed' ) . ' ' .
           __( 'You can revert them to WordPress\'s standard keys.', 'index-wp-mysql-for-speed' );
    $this->showCommandLine( 'fast', 'disable', $fmt, false, false );
  }

  /** display  sample command line to user.
   *
   * @param $actionKey string  enable/disable/rekey/reset.
   * @param $action string command to display
   * @param $caption string prefix text.
   * @param $warning boolean display warning not log.
   * @param $alreadyPrefixed boolean  tables in list already have wp_ style prefixes.
   */
  private function showCommandLine( $actionKey, $action, $caption, $warning, $alreadyPrefixed ) {
    $array = $this->rekeying;
    if ( array_key_exists( $actionKey, $array ) && count( $array[ $actionKey ] ) > 0 ) {
      $msg  = $caption . ' ' . __( 'Use this command:', 'index-wp-mysql-for-speed' );
      $tbls = $this->addPrefixes( $array[ $actionKey ], $alreadyPrefixed );
      if ( $warning ) {
        WP_CLI::warning( $this->commentPrefix . $msg );
      } else {
        WP_CLI::log( $this->commentPrefix . $msg );
      }
      WP_CLI::log( $this->commentPrefix . $this->getCommand( $action, $tbls ) );
      if ( count( $this->errorMessages ) > 0 ) {
        WP_CLI::log( $this->commentPrefix . implode( PHP_EOL, $this->errorMessages ) );
        $this->errorMessages = [];
      }
    }
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

  /** format actual cmd
   *
   * @param $cmd string
   * @param $tbls array
   *
   * @return string
   */
  private function getCommand( $cmd, $tbls ) {
    if ( count( $tbls ) > 0 ) {
      $list = implode( ' ', $tbls );

      return sprintf( '  ' . "%s %s %s", $this->cmd, $cmd, $list );
    }

    return '';
  }

  /**
   * Add high-performance keys.
   */
  function enable( $args, $assoc_args ) {
    $targetAction = 1;
    $this->setupCliEnvironment( $args, $assoc_args );
    $this->doRekeying( $args, $assoc_args, $targetAction );
  }

  /** @noinspection PhpSameParameterValueInspection */
  private function doRekeying( $args, $assoc_args, $targetAction, $alreadyPrefixed = true ) {
    $action = $targetAction === 0 ? 'disable' : 'enable';
    if ( $this->dryrun ) {
      /* translators: this appears in wpcli output. 1: site name  2: site URL  3: localized date and time */
      $dateMessage = __( 'Generated from %1$s (%2$s) at %3$s.', 'index-wp-mysql-for-speed' );
      $dateMessage = sprintf( $dateMessage, get_option( 'blogname' ), get_option( 'siteurl' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
      WP_CLI::log( $this->commentPrefix . __( $dateMessage ) );
      WP_CLI::log( $this->commentPrefix . __( 'Do not save these statements for later use. Instead, regnerate them.', 'index-wp-mysql-for-speed' ) );
      WP_CLI::log( $this->commentPrefix . __( 'Dry run SQL statements. These statements were NOT run.', 'index-wp-mysql-for-speed' ) );
      WP_CLI::log( "SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');" );
    }
    $tbls = $this->getTablesToProcess( $args, $assoc_args, $action );
    foreach ( $tbls as $tbl ) {
      $this->db->timings = [];
      $arr               = [ $tbl ];
      $statements        = $this->db->rekeyTables( $targetAction, $arr, index_mysql_for_speed_major_version, $alreadyPrefixed, $this->dryrun );
      if ( $this->dryrun ) {
        WP_CLI::log( implode( PHP_EOL, $statements ) );
      } else {
        WP_CLI::log( $this->commentPrefix . $this->reportCompletion( $action, $tbl ) );
      }
    }
    /* store current version of schema to suppress nag in UI */
    $this->setCurrentVersion();
  }

  /** Filter and validate the list of tables.
   *
   * @param $args array incoming args
   * @param $assoc_args array incoming switch args
   * @param $action string enable/disable/rekey/reset
   *
   * @return array list of tables
   */
  private function getTablesToProcess( array $args, array $assoc_args, $action ) {

    $alreadyPrefixed = ( $action === 'upgrade' );
    $tbls            = $this->rekeying[ $action ];
    if ( $action === 'enable' ) {
      $tbls = array_merge( $tbls, $this->rekeying['old'], $this->rekeying['nonstandard'] );
    }
    $tbls    = $this->addPrefixes( $tbls, $alreadyPrefixed );
    $res     = [];
    $exclude = [];
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
      $fmt = __( 'These tables are not found or not eligible to', 'index-wp-mysql-for-speed' ) . ' ' . $action . ': ' . implode( ' ', $err ) . '.';
      WP_CLI::error( $this->commentPrefix . $fmt );
    }
    if ( count( $res ) == 0 ) {
      $fmt = __( 'No tables are eligible to', 'index-wp-mysql-for-speed' ) . ' ' . $action . '.';
      WP_CLI::error( $this->commentPrefix . $fmt );
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
    $this->db->timings = [];
    $msg               = _n( 'MySQL statement', 'MySQL statements', $queries, 'index-wp-mysql-for-speed' );

    return sprintf( "%s %s %s (%d %s, %ss)",
      $action, $tbl, __( "complete.", 'index-wp-mysql-for-speed' ), $queries, $msg, number_format_i18n( $time, 2 ) );
  }

  /** set the current majorVersion into the options structure.
   * (The UI checks this to see whether it should nag the user a bit
   * about adding / updating keys.
   *
   * @param $optName
   *
   * @return void
   */
  private function setCurrentVersion( $optName = 'ImfsPage' ) {
    global $wp_version, $wp_db_version;
    $opts = get_option( $optName );
    if ( ! $opts ) {
      $opts = [];
    }
    $opts['majorVersion']  = index_mysql_for_speed_major_version;
    $opts['wp_version']    = $wp_version;
    $opts['wp_db_version'] = $wp_db_version;

    update_option( $optName, $opts );
  }

  /**
   * Remove high-performance keys, reverting to WordPress standard.
   */
  function disable( $args, $assoc_args ) {
    $targetAction = 0;
    $this->setupCliEnvironment( $args, $assoc_args );
    $this->doRekeying( $args, $assoc_args, $targetAction );
  }

  /**
   * Upgrade the storage engine and row format to InnoDB and Dynamic
   */
  function upgrade( $args, $assoc_args ) {
    $action = 'upgrade';
    $this->setupCliEnvironment( $args, $assoc_args );
    $tbls = $this->getTablesToProcess( $args, $assoc_args, $action );
    if ( $this->dryrun ) {
      WP_CLI::log( $this->commentPrefix . __( 'Dry run SQL statements. These statements were NOT run.', 'index-wp-mysql-for-speed' ) );
    }
    try {
      $this->db->lock( $tbls, true );
      foreach ( $tbls as $tbl ) {
        $this->db->timings = [];
        $arr               = [ $tbl ];
        $statements        = $this->db->upgradeTableStorageEngines( $arr, $this->dryrun );
        if ( $this->dryrun ) {
          WP_CLI::log( implode( PHP_EOL, $statements ) );
        } else {
          WP_CLI::log( $this->commentPrefix . $this->reportCompletion( $action, $tbl ) );
        }
      }
    } catch ( ImfsException $ex ) {
      WP_CLI::error( $this->commentPrefix . $ex->getMessage() );
    } finally {
      $this->db->unlock();
    }
  }

  /**
   * Display information about tables.
   */
  function tables( $args, $assoc_args ) {
    global $wpdb;
    $this->setupCliEnvironment( $args, $assoc_args );
    $list = $this->db->tableFormats;
    $hdrs = [];
    $row  = reset( $list );
    foreach ( $row as $key => $val ) {
      $hdrs[] = $key;
    }
    $format = array_key_exists( 'format', $assoc_args ) ? $assoc_args['format'] : 'table';
    WP_CLI\Utils\format_items( $format, $list, $hdrs );

    $list   = [];
    $tables = $this->db->tables();
    foreach ( $tables as $table ) {
      $outrow = [];
      $ddls   = $this->db->getKeyDDL( $table );
      foreach ( $ddls as $keyname => $row ) {
        $outrow['table']      = $wpdb->prefix . $table;
        $outrow['key']        = $keyname;
        $outrow['definition'] = $row->add;
        $list[]               = $outrow;
      }
    }
    $hdrs = [];
    $row  = reset( $list );
    foreach ( $row as $key => $val ) {
      $hdrs[] = $key;
    }
    WP_CLI\Utils\format_items( $format, $list, $hdrs );
  }

  /**
   * Upload diagnostic metadata to plugin developers' site.
   * Uploads are anonymous. We use your data only to help with support issues
   * and improve the plugin. We never sell or give it to any third party.
   */
  function upload_metadata( $args, $assoc_args ) {
    $this->setupCliEnvironment( $args, $assoc_args );
    $id = ImfsQueries::getRandomString( 8 );
    $id = imfs_upload_stats( $this->db, $id );
    WP_CLI::log( __( 'Metadata uploaded to id ', 'index-wp-mysql-for-speed' ) . $id );
  }

}

WP_CLI::add_command( 'index-mysql', 'ImsfCli' );