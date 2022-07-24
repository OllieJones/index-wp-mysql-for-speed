<?php
/** @noinspection SpellCheckingInspection */
require_once( 'getindexes.php' );
require_once( 'getqueries.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

class ImfsDb {
  /** @var bool true if this server can support reindexing at all */
  public $canReindex = false;
  /** @var int 1 if we have Barracuda, 0 if we have Antelope */
  public $unconstrained;
  public $messages = [];
  public $semver;
  public $tableCounts;
  public $oldEngineTables;
  public $newEngineTables;
  public $timings;
  private $initialized = false;
  private $hasHrTime;
  /** @var string[] list of index prefixes to ignore. */
  private $indexStopList = [ 'woo_', 'crp_', 'yarpp_' ];
  private $pluginOldVersion;
  private $pluginVersion;

  private $indexQueryCache = [];
  /** @var int the time in seconds allowed for each ALTER operation */
  private $scriptTimeLimit = 600;

  /**
   * @param float $pluginVersion
   * @param float $pluginOldVersion
   */
  public function __construct( $pluginVersion, $pluginOldVersion ) {
    $this->pluginOldVersion = $pluginOldVersion;
    $this->pluginVersion    = $pluginVersion;
  }

  /**
   * @throws ImfsException
   * @noinspection PhpRedundantOptionalArgumentInspection
   */
  public function init() {
    global $wpdb;
    if ( ! $this->initialized ) {
      $this->initialized   = true;
      $this->timings       = [];
      $this->semver        = ImfsQueries::getMySQLVersion();
      $this->canReindex    = $this->semver->canreindex;
      $this->unconstrained = $this->semver->unconstrained;

      if ( $this->canReindex ) {
        $this->tableCounts  = $this->getTableCounts();
        $this->tableFormats = $this->getTableFormats();
        $oldEngineTables    = [];
        $newEngineTables    = [];
        /* make sure we only upgrade the engine on WordPress's own tables */
        $wpTables = array_flip( $wpdb->tables( 'blog', true ) );
        if ( is_main_site() ) {
          $wpTables = $wpTables + array_flip( $wpdb->tables( 'global', true ) );
        }
        foreach ( $this->tableFormats as $name => $info ) {
          $activeTable = false;
          if ( isset( $wpTables[ $name ] ) ) {
            $activeTable = true;
          }
          /* This ignores an empty prefix. Nevertheless, that is not a supported WordPress configuration. */
          if ( !empty( $wpdb->prefix ) && 0 === strpos( $name, $wpdb->prefix ) ) {
            $activeTable = true;
          }
          if ( $activeTable ) {
            /* not InnoDB, we should upgrade */
            $wrongEngine = $info->ENGINE !== 'InnoDB';
            /* one of the old row formats, probably compact. But ignore if old MySQL version. */
            $wrongRowFormat = $this->unconstrained && $info->ROW_FORMAT !== 'Dynamic' && $info->ROW_FORMAT !== 'Compressed';

            if ( $wrongEngine || $wrongRowFormat ) {
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

  private function getTableCounts() {
    global $wpdb;
    $wpdb->flush();
    $query = ImfsQueries::getTableCountsQuery();

    return $this->get_results( $query );
  }

  /** run a SELECT
   *
   * @param $sql
   * @param bool $doTiming
   * @param string $outputFormat default OBJECT_K
   *
   * @return array|object|null
   * @throws ImfsException
   */
  public function get_results( $sql, $doTiming = false, $outputFormat = OBJECT_K ) {
    global $wpdb;
    $thentime = $doTiming ? $this->getTime() : - 1;
    $results  = $wpdb->get_results( $this->tagQuery( $sql ), $outputFormat );
    if ( false === $results || $wpdb->last_error ) {
      throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
    }
    if ( $doTiming ) {
      $delta           = round( floatval( $this->getTime() - $thentime ), 3 );
      $this->timings[] = [ 't' => $delta, 'q' => $sql ];
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

  /** place a tag comment at the end of a SQL statement,
   * including text to recognize our queries and
   * a random cache-busting number.
   * (So WP Total Cache and others won't cache.)
   *
   * @param $q
   *
   * @return string
   */
  private function tagQuery( $q ) {
    return $q . '/*' . index_wp_mysql_for_speed_querytag . rand( 0, 999999999 ) . '*/';
  }

  private function getTableFormats() {
    global $wpdb;
    $wpdb->flush();
    $query = ImfsQueries::getTableFormatsQuery();

    return $this->get_results( $query );
  }

  function getTableStats() {
    return $this->get_results( ImfsQueries::getTableStatsQuery() );
  }

  function getVariables() {
    return $this->get_results( "SHOW GLOBAL VARIABLES" );
  }

  function getStatus() {
    return $this->get_results( "SHOW GLOBAL STATUS" );
  }

  /**
   * @param $targetAction int   0 - WordPress standard  1 -- high performance
   * @param $tables array  tables like ['postmeta','termmeta']
   * @param $version
   * @param $alreadyPrefixed bool false if wp_ prefix needs to be added to table names
   * @param bool $dryrun true when doing a dry run, default false -- do the operation
   * @return array|string status string, or if
   * @throws ImfsException
   */
  public function rekeyTables( $targetAction, array $tables, $version, $alreadyPrefixed = false, $dryrun = false ) {
    $statements = [];
    /* changing indexes: get rid of the cache showing present indexes */
    $this->indexQueryCache = [];
    $count                 = 0;
    if ( count( $tables ) > 0 ) {
      foreach ( $tables as $name ) {
        $statements[] = $this->rekeyTable( $targetAction, $name, $version, $alreadyPrefixed, $dryrun );
        $count ++;
      }
    }

    wp_cache_flush();
    if ( $dryrun ) {
      return $statements;
    }
    $msg = '';
    switch ( $targetAction ) {
      case 1:
        /* translators: 1: number of tables processed */
        $msg = __( 'High-performance keys added to %1$d tables.', 'index-wp-mysql-for-speed' );
        break;
      case 0:
        /* translators: 1: number of tables processed */
        $msg = __( 'Keys on %1$d tables reverted to WordPress standard.', 'index-wp-mysql-for-speed' );
        break;
    }

    return sprintf( $msg, $count );
  }

  /** Redo the keys on the selected table.
   *
   * @param int $targetAction 0 -- WordPress standard.  1 -- high-perf
   * @param string $name table name without prefix
   * @param bool $dryrun true when doing a dry run, default false -- do the operation
   * @returns string the text of the SQL statement doing the rekeying
   *
   * @throws ImfsException
   */
  public function rekeyTable( $targetAction, $name, $version, $alreadyPrefixed = false, $dryrun = false ) {
    global $wpdb;

    $unprefixedName = $alreadyPrefixed ? ImfsQueries::stripPrefix( $name ) : $name;
    $prefixedName   = $alreadyPrefixed ? $name : $wpdb->prefix . $name;

    $actions = $this->getConversionList( $targetAction, $unprefixedName, $version );

    if ( count( $actions ) === 0 ) {
      return '';
    }

    $q = 'ALTER TABLE ' . $prefixedName . ' ' . implode( ', ', $actions );
    set_time_limit( $this->scriptTimeLimit );
    if ( ! $dryrun ) {
      $this->query( $q, true );
    }
    return $q . ';';
  }

  /**
   * @param int $targetState 0 -- WordPress default    1 -- high-performance
   * @param string $name table name
   * @param float $version
   *
   * @return array
   * @throws ImfsException
   */
  public function getConversionList( $targetState, $name, $version ) {
    $target  = $targetState === 0
      ? ImfsGetIndexes::getStandardIndexes( $this->unconstrained )
      : ImfsGetIndexes::getHighPerformanceIndexes( $this->unconstrained, $version );
    $target  = array_key_exists( $name, $target ) ? $target[ $name ] : [];
    $current = $this->getKeyDDL( $name );

    /* build a list of all index names, target first so UNIQUEs come first */
    $indexes = [];
    foreach ( $target as $key => $value ) {
      if ( ! isset( $indexes[ $key ] ) ) {
        $indexes[ $key ] = $key;
      }
    }
    foreach ( $current as $key => $value ) {
      if ( ! isset( $indexes[ $key ] ) ) {
        $indexes[ $key ] = $key;
      }
    }

    /* Ignore index names prefixed with anything ins
     * the indexStoplist array (skip woocommerce indexes) */
    foreach ( $this->indexStopList as $stop ) {
      foreach ( $indexes as $index => $val ) {
        if ( strpos( $index, $stop ) === 0 ) {
          unset ( $indexes[ $index ] );
        }
      }
    }
    $actions = [];

    $curDexes = 'current indexes ' . $name . ':';
    foreach ( $current as $value ) {
      $curDexes .= $value->add . ' ';
    }

    foreach ( $indexes as $key => $value ) {
      if ( array_key_exists( $key, $current ) && array_key_exists( $key, $target ) && $current[ $key ]->add === $target[ $key ] ) {
        /* empty, intentionally.  no action required */
      } else if ( array_key_exists( $key, $current ) && array_key_exists( $key, $target ) && $current[ $key ]->add !== $target[ $key ] ) {
        $actions[] = $current[ $key ]->drop;
        $actions[] = $target[ $key ];
      } else if ( array_key_exists( $key, $current ) && ! array_key_exists( $key, $target ) ) {
        $actions[] = $current[ $key ]->drop;
      } else if ( ! array_key_exists( $key, $current ) && array_key_exists( $key, $target ) ) {
        $actions[] = $target[ $key ];
      } else {
        throw new ImfsException( 'weird key compare failure' );
      }
    }

    return $actions;
  }

  /**
   * Retrieve DML for the keys in the named table.
   *
   * @param string $name table name (without prefix)
   * @param bool $addPrefix
   *
   * @return array
   * @throws ImfsException
   */
  public function getKeyDDL( $name, $addPrefix = true ) {
    global $wpdb;
    if ( $addPrefix ) {
      $name = $wpdb->prefix . $name;
    }

    if ( array_key_exists( $name, $this->indexQueryCache ) ) {
      return $this->indexQueryCache[ $name ];
    }

    $stmt                           = $wpdb->prepare( ImfsQueries::getTableIndexesQuery(), $name );
    $result                         = $this->get_results( $stmt );
    $this->indexQueryCache[ $name ] = $result;

    return $result;
  }

  /** run a query*
   *
   * @param $sql
   * @param bool $doTiming
   *
   * @return bool|int
   * @throws ImfsException
   */
  public function query( $sql, $doTiming = false ) {
    global $wpdb;
    $thentime = $doTiming ? $this->getTime() : - 1;
    $results  = $wpdb->query( $this->tagQuery( $sql ) );
    $this->logDDLQuery( $sql );
    if ( false === $results || $wpdb->last_error ) {
      throw new ImfsException( $wpdb->last_error, $wpdb->last_query );
    }
    if ( $doTiming ) {
      $delta           = round( floatval( $this->getTime() - $thentime ), 3 );
      $this->timings[] = [ 't' => $delta, 'q' => $sql ];
    }

    return $results;
  }

  private function logDDLQuery( $sql ) {
    if ( index_mysql_for_speed_log !== null ) {
      $log = get_option( index_mysql_for_speed_log );
      if ( strlen( $log ) >= 1024 * 1024 ) {
        $log = '';
      }
      $log .= PHP_EOL . $sql;
      update_option( index_mysql_for_speed_log, $log );
    }
  }

  /** get the current index situation
   * @return array
   */
  public function getIndexList() {

    $results = [];
    try {
      $rekeying = $this->getRekeying();
      foreach ( $rekeying as $key => $list ) {
        if ( is_array( $list ) && count( $list ) > 0 ) {
          if ( $key === 'standard' || $key === 'old' || $key === 'fast' || $key === 'upgrade' ) {
            $results[ $key ] = $list;
          }
        }
      }
      /* nonstandard? show the actual keys */
      $nonstandard = $rekeying['nonstandard'];
      if ( is_array( $nonstandard ) && count( $nonstandard ) > 0 ) {
        $tables = [];
        foreach ( $nonstandard as $table ) {
          $keys    = [];
          $current = $this->getKeyDDL( $table );
          foreach ( $current as $key => $value ) {
            $dex    = preg_replace( '/^ADD +/', '', $value->add, 1 );
            $keys[] = $dex;
          }
          $tables[ $table ] = $keys;
        }
        $results['nonstandard'] = $tables;
      }
    } catch ( ImfsException $ex ) {
      $results[] = $ex->getMessage();
    }

    return $results;
  }

  /** figure out, based on current DDL and target DDL,
   * what tables need to change, and how they could change.
   *
   * @return array
   * @throws ImfsException
   */
  public function getRekeying() {
    global $wpdb;
    $originalTables = $this->tables();
    /* don't process tables still on old storage engines */
    $tables = [];
    if ( is_array( $this->oldEngineTables ) ) {
      foreach ( $originalTables as $name ) {
        if ( ! in_array( $wpdb->prefix . $name, $this->oldEngineTables ) ) {
          $tables[] = $name;
        }
      }
    }
    /* any rekeyable tables? */
    $updatable    = [];
    $newToThisVer = [];

    $addable    = [];
    $revertable = [];
    $repairable = [];
    $enableList = [];
    $oldList    = [];
    $fastList   = [];

    if ( is_array( $tables ) && count( $tables ) > 0 ) {
      sort( $tables );
      foreach ( $tables as $name ) {
        $hasFastIndexes     = ! $this->anyIndexChangesNeededForAction( 1, $name, $this->pluginVersion );
        $hasStandardIndexes = ! $this->anyIndexChangesNeededForAction( 0, $name, $this->pluginVersion );
        $hasOldIndexes      = ! $this->anyIndexChangesNeededForAction( 1, $name, $this->pluginOldVersion );

        if ( ! $hasFastIndexes && ! $hasStandardIndexes && ! $hasOldIndexes ) {
          /* some index config we don't recognize */
          $repairable[] = $name;
          $revertable[] = $name;
        } else if ( ! $hasFastIndexes && ! $hasStandardIndexes && $hasOldIndexes ) {
          /* indexes from old version of plugin */
          $updatable[]  = $name;
          $revertable[] = $name;
        } else if ( ! $hasFastIndexes && $hasStandardIndexes && ! $hasOldIndexes ) {
          /* standard indexes, not rekeyed by this or older plugin version */
          $addable[] = $name;
        } else if ( ! $hasFastIndexes && $hasStandardIndexes && $hasOldIndexes ) {
          /* not rekeyed in older version, but rekeyed in this version */
          $newToThisVer[] = $name;
        } else if ( $hasFastIndexes && ! $hasStandardIndexes && ! $hasOldIndexes ) {
          /* already rekeyed by this version */
          $revertable[] = $name;
          $fastList[]   = $name;
        } else {
          throw new Exception( "Table $name invalid state $hasFastIndexes $hasStandardIndexes $hasOldIndexes" );
        }
      }
      /* handle version update logic */
      if ( count( $updatable ) > 0 ) {
        /* version update time: offer to update both new and upd */
        $oldList    = array_merge( $newToThisVer, $updatable );
        $enableList = $addable;
      } else {
        $enableList = array_merge( $newToThisVer, $addable );
      }
    }
    sort( $enableList );
    sort( $oldList );

    /* What do we return here?
     * enable: list of tables that can have fast keys added.
     * old: list of tables that have previous-version fast keys,
     *      including tables the previous version did not touch.
     * disable: list of tables that have indexes that can be
     *          restored to the default.
     * fast: list of tables that have this plugin version's fast indexes.
     * nonstandard:  list of tables with indexes this plugin doesn't recognize.
     * standard: list of tables with WordPress standard keys
     * upgrade: list of MyISAM and / or COMPACT row-format tables
     *          needing upgrading.
     */

    return [
      'enable'      => $enableList,
      'old'         => $oldList,
      'disable'     => $revertable,
      'fast'        => $fastList,
      'nonstandard' => $repairable,
      'standard'    => $addable,
      'upgrade'     => $this->oldEngineTables,
    ];
  }

  /** List of tables to manipulate
   *
   * @param bool $prefixed true if you want wp_postmeta, false if you want postmeta
   *
   * @return array tables manipulated by this module
   * @throws ImfsException
   */
  public function tables( $prefixed = false ) {
    global $wpdb;
    $avail = $wpdb->tables;
    if ( is_main_site() ) {
      foreach ( $wpdb->global_tables as $table ) {
        $avail[] = $table;
      }
    }
    /* match to the tables we know how to reindex */
    $allTables = ImfsGetIndexes::getIndexableTables( $this->unconstrained );
    $tables    = [];
    foreach ( $allTables as $table ) {
      if ( in_array( $table, $avail ) ) {
        $tables[] = $table;
      }
    }
    sort( $tables );
    if ( ! $prefixed ) {
      return $tables;
    }
    $result = [];
    foreach ( $tables as $table ) {
      $result[] = $wpdb->prefix . $table;
    }

    return $result;
  }

  /** Check whether a table is ready to be acted upon
   *
   * @param int $targetAction "enable" or "disable"
   * @param $name
   * @param $version
   *
   * @return bool
   * @throws ImfsException
   */
  public function anyIndexChangesNeededForAction( $targetAction, $name, $version ) {
    $actions = $this->getConversionList( $targetAction, $name, $version );

    return count( $actions ) > 0;
  }

  /**
   * @param array $list of tables
   * @param bool $dryrun true if we're doing a dry run.
   * @return array|string list of DDL statements, or display messat
   * @throws ImfsException
   */
  public function upgradeTableStorageEngines( array $list, $dryrun = false ) {
    /* reworking tables; flush any index cache */
    $this->indexQueryCache = [];
    $statements            = [];

    $counter = 0;
    try {
      $this->lock( $list, true );
      foreach ( $list as $table ) {
        $statements[] = $this->upgradeTableStorageEngine( $table, $dryrun );
        $counter ++;
      }
      /* translators: 1: count of tables upgraded from MyISAM to InnoDB */
      $msg = __( '%1$d tables upgraded', 'index-wp-mysql-for-speed' );
    } catch ( ImfsException $ex ) {
      $msg = implode( ', ', $this->clearMessages() );
      throw ( $ex );
    } finally {
      $this->unlock();
    }

    if ( $dryrun ) {
      return $statements;
    }
    return sprintf( $msg, $counter );
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
    $tablesToLock = [];
    foreach ( $tableList as $tbl ) {
      if ( ! $alreadyPrefixed ) {
        $tbl = $wpdb->prefix . $tbl;
      }
      $tablesToLock[] = $tbl;
    }

    /* always specify locks in the same order to avoid starving the philosophers */
    sort( $tablesToLock );
    $tables = [];
    foreach ( $tablesToLock as $tbl ) {
      $tables[] = $tbl . ' WRITE';
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
      $maintain     = [];
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

  /**
   * @throws ImfsException
   */
  public function upgradeTableStorageEngine( $table, $dryrun = false ) {
    $sql = 'ALTER TABLE ' . $table . ' ENGINE=InnoDb, ROW_FORMAT=DYNAMIC;';
    if ( $dryrun ) {
      return $sql;
    }
    set_time_limit( $this->scriptTimeLimit );
    $this->query( $sql, true );

    return true;
  }

  /** Resets the messages in this class and returns the previous messages.
   * @return array
   */
  public function clearMessages() {
    $msgs           = $this->messages;
    $this->messages = [];

    return $msgs;
  }

  /** Undo lock.  This is ideally called from a finally{} clause.
   * @throws ImfsException
   */
  public function unlock() {
    $this->query( "UNLOCK TABLES" );
    $this->leaveMaintenanceMode();
  }

  public function leaveMaintenanceMode() {
    $maintenanceFileName = ABSPATH . '.maintenance';
    if ( is_writable( ABSPATH ) && file_exists( $maintenanceFileName ) ) {
      unlink( $maintenanceFileName );
    }
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
