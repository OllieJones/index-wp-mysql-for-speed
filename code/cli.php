<?php /** @noinspection PhpUnused */
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
  public $errorMessages = [];

  /**
   * Display version information.
   *
   */
  function version( $args, $assoc_args ) {
    $this->setupCliEnvironment( $args, $assoc_args );
    $wpDescription = imfsToResultSet( imfsGetWpDescription( $this->db ) );
    $format        = array_key_exists( 'format', $assoc_args ) ? $assoc_args['format'] : null;
    WP_CLI\Utils\format_items( $format, $wpDescription, [ 'Item', 'Value' ] );
  }

  /** @noinspection PhpUnusedParameterInspection */
  private function setupCliEnvironment( $args, $assoc_args ) {
    global $wp_version;
    global $wp_db_version;
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
    }
    $this->db = new ImfsDb( index_mysql_for_speed_major_version, index_mysql_for_speed_inception_major_version );
    $this->db->init();
    $this->rekeying = $this->db->getRekeying();

    if ( true ) {
      $wpDescription = imfsGetWpDescription( $this->db );
      WP_CLI::log( __( 'Index WP MySQL For Speed', $this->domain ) . ' ' . index_wp_mysql_for_speed_VERSION_NUM );
      $versions = ' Plugin:' . $wpDescription['pluginversion'] . ' MySQL:' . $wpDescription['mysqlversion'] . ' WordPress:' . $wp_version . ' WordPress database:' . $wp_db_version . ' php:' . phpversion();
      WP_CLI::log( __( 'Versions', $this->domain ) . ' ' . $versions );
    }

    if ( ! $this->db->canReindex ) {
      if ( $wp_db_version < index_wp_mysql_for_speed_first_compatible_db_version ||
           $wp_db_version > index_wp_mysql_for_speed_last_compatible_db_version ) {
        $fmt = __( 'Sorry, this plugin\'s version is not compatible with your WordPress database version.', $this->domain );
      } else {
        $fmt = __( 'Sorry, you cannot use this plugin with your version of MySQL.', $this->domain ) . ' ' .
               __( 'Your MySQL version is outdated. Please consider upgrading,', $this->domain );
      }
      WP_CLI::exit( $fmt );

    }
    if ( ! $this->db->unconstrained ) {
      $fmt = __( 'Upgrading your MySQL server to a later version will give you better performance when you add high-performance keys.', $this->domain );
      WP_CLI::warning( $fmt );
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

    $fmt = __( 'Database tables need upgrading to MySQL\'s latest table storage format, InnoDB with dynamic rows.', $this->domain );
    $this->showCommandLine( 'upgrade', 'upgrade', $fmt, true, true );

    $fmt = __( 'Add or upgrade high-performance keys to make your WordPress database faster.', $this->domain );
    $this->showCommandLine( 'enable', 'enable', $fmt, false, false );

    $fmt = __( 'You set some keys from outside this plugin. You can convert them to this plugin\'s high-performance keys.', $this->domain );
    $this->showCommandLine( 'nonstandard', 'enable', $fmt, false, false );
    $fmt = __( 'Or, you can revert them to WordPress\'s standard keys.', $this->domain );
    $this->showCommandLine( 'nonstandard', 'disable', $fmt, false, false );

    $fmt = __( 'You added high-performance keys using an earlier version of this plugin. You can update them to the latest high-performance keys.', $this->domain );
    $this->showCommandLine( 'old', 'enable', $fmt, false, false );
    $fmt = __( 'Or, you can revert them to WordPress\'s standard keys.', $this->domain );
    $this->showCommandLine( 'old', 'disable', $fmt, false, false );

    $fmt = __( 'You successfully added high-performance keys.', $this->domain ) . ' ' .
           __( 'You can revert them to WordPress\'s standard keys.', $this->domain );
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
      $msg  = $caption . ' ' . __( 'Use this command:', $this->domain );
      $tbls = $this->addPrefixes( $array[ $actionKey ], $alreadyPrefixed );
      if ( $warning ) {
        WP_CLI::warning( $msg );
      } else {
        WP_CLI::log( $msg );
      }
      WP_CLI::log( $this->getCommand( $action, $tbls ) );
      if ( count( $this->errorMessages ) > 0 ) {
        WP_CLI::log( implode( PHP_EOL, $this->errorMessages ) );
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

      $fmt = __( "%s %s %s", $this->domain );

      return sprintf( '  ' . $fmt, $this->cmd, $cmd, $list );
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
    $tbls   = $this->getTablesToProcess( $args, $assoc_args, $action );
    foreach ( $tbls as $tbl ) {
      $this->db->timings = [];
      $arr               = [ $tbl ];
      $this->db->rekeyTables( $targetAction, $arr, index_mysql_for_speed_major_version, $alreadyPrefixed );
      WP_CLI::log( $this->reportCompletion( $action, $tbl ) );
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
      $fmt = __( 'These tables are not found or not eligible to', $this->domain ) . ' ' . $action . ': ' . implode( ' ', $err ) . '.';
      WP_CLI::error( $fmt );
    }
    if ( count( $res ) == 0 ) {
      $fmt = __( 'No tables are eligible to', $this->domain ) . ' ' . $action . '.';
      WP_CLI::error( $fmt );
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
    $msg               = _n( 'MySQL statement', 'MySQL statements', $queries, $this->domain );

    return sprintf( "%s %s %s (%d %s, %ss)",
      $action, $tbl, __( "complete.", $this->domain ), $queries, $msg, number_format_i18n( $time, 2 ) );
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
    $opts['majorVersion'] = index_mysql_for_speed_major_version;
    $opts['wp_version'] = $wp_version;
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
    try {
      $this->db->lock( $tbls, true );
      foreach ( $tbls as $tbl ) {
        $this->db->timings = [];
        $arr               = [ $tbl ];
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
   * Display information about tables.
   */
  function tables( $args, $assoc_args ) {
    global $wpdb;
    $this->setupCliEnvironment( $args, $assoc_args );
    $list = $this->db->stats[1];
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
    $id = imfsRandomString( 8 );
    $id = imfs_upload_stats( $this->db, $id );
    WP_CLI::log( __( 'Metadata uploaded to id ', $this->domain ) . $id );
  }


}

WP_CLI::add_command( 'index-mysql', 'ImsfCli' );