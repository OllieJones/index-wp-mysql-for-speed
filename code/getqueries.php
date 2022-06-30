<?php

class ImfsQueries {

  public static function stripPrefix( $name ) {
    global $wpdb;
    $pattern = '/^' . $wpdb->prefix . '/';

    return preg_replace( $pattern, '', $name );
  }

  /** Get version information from the database server
   * @return object
   */
  public static function getMySQLVersion() {
    global $wpdb;
    global $wp_db_version;
    $semver  = " 
	 SELECT VERSION() version,
	        1 canreindex,
	        0 unconstrained,
            CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) major,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED) minor,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(VERSION(), '-', '.'), '.', 3), '.', -1) AS UNSIGNED) build,
            '' fork, '' distro";
    $results = $wpdb->get_results( ImfsQueries::tagQuery( $semver ) );
    $results = $results[0];

    $results->db_host = ImfsQueries::redactHost( DB_HOST );
    $ver              = explode( '-', $results->version, 3 );
    if ( count( $ver ) >= 2 ) {
      $results->fork = $ver[1];
    }
    if ( count( $ver ) >= 3 ) {
      $results->distro = $ver[2];
    }

    /* check db version ... TODO with new db versions, test again */
    if ( $wp_db_version < index_wp_mysql_for_speed_first_compatible_db_version ||
         ( index_wp_mysql_for_speed_last_compatible_db_version &&
           $wp_db_version > index_wp_mysql_for_speed_last_compatible_db_version ) ) {
      /* fail if we don't have an expected version */
      $results->canreindex = 0;

      return ImfsQueries::makeNumeric( $results );
    }

    $isMaria = ! ! stripos( $results->version, "mariadb" );
    /* work out whether we have Antelope or Barracuda InnoDB format */
    /* mysql 8+ */
    if ( ! $isMaria && $results->major >= 8 ) {
      $results->unconstrained = 1;

      return ImfsQueries::makeNumeric( $results );
    }
    /* work out whether we have Antelope or Barracuda InnoDB format */
    /* mariadb 10.3 + */
    if ( $isMaria && $results->major >= 10 && $results->minor >= 3 ) {
      $results->unconstrained = 1;

      return ImfsQueries::makeNumeric( $results );
    }
    /* mariadb 10.2 ar before */
    if ( $isMaria && $results->major >= 10 ) {

      $results->unconstrained = ImfsQueries::hasLargePrefix();

      return ImfsQueries::makeNumeric( $results );
    }

    /* waaay too old */
    if ( $results->major < 5 ) {
      $results->canreindex = 0;

      return ImfsQueries::makeNumeric( $results );
    }
    /* before 5.5 */
    if ( $results->major == 5 && $results->minor < 5 ) {
      $results->canreindex = 0;

      return ImfsQueries::makeNumeric( $results );
    }
    /* older 5.5 */
    if ( $results->major === 5 && $results->minor === 5 && $results->build < 62 ) {
      $results->canreindex = 0;

      return ImfsQueries::makeNumeric( $results );
    }
    /* older 5.6 */
    if ( $results->major === 5 && $results->minor === 6 && $results->build < 4 ) {
      $results->canreindex = 0;

      return ImfsQueries::makeNumeric( $results );
    }
    $results->unconstrained = ImfsQueries::hasLargePrefix();

    return ImfsQueries::makeNumeric( $results );
  }

  public static function tagQuery( $q ) {
    return $q . '/*' . index_wp_mysql_for_speed_querytag . rand( 0, 999999999 ) . '*/';
  }

  public static function redactHost( $host ) {
    if ( trim( $host ) === '' ) {
      return $host;
    }
    if ( trim( $host ) === '127.0.0.1' ) {
      return $host;
    }
    if ( trim( $host ) === 'localhost' ) {
      return $host;
    }
    if ( trim( $host ) === '::1' ) {
      return $host;
    }

    return "Redacted, not localhost";
  }

  /** Convert values in objects to numbers wherever possible.
   *
   * @param object $ob
   *
   * @return object the input suitable for serializing into JSON
   */
  public static function makeNumeric( $ob ) {
    $result = [];
    foreach ( $ob as $key => $val ) {
      if ( is_numeric( $val ) ) {
        $val = $val + 0;
      }
      $result[ $key ] = $val;
    }

    return (object) $result;
  }

  /**
   * @return int 1 if the MySQL instance supports 3072-byte index columns
   */
  public static function hasLargePrefix() {
    global $wpdb;
    /* innodb_large_prefix variable is missing in MySQL 8+ */
    $prefix = $wpdb->get_results( ImfsQueries::tagQuery( "SHOW VARIABLES LIKE 'innodb_large_prefix'" ), OBJECT_K );
    if ( $prefix && is_array( $prefix ) && array_key_exists( 'innodb_large_prefix', $prefix ) ) {
      $prefix = $prefix['innodb_large_prefix'];

      return ( $prefix->Value === 'ON' || $prefix->Value === '1' ) ? 1 : 0;
    }

    return 0;
  }

  /** @noinspection PhpUnused */
  public static function getTableStatsQuery() {
    global $wpdb;
    $p = $wpdb->prefix;

    return
      "SELECT REPLACE(t.TABLE_NAME, '$p', '') AS 'table',
               '$p' AS 'prefix',
                MAX(t.TABLE_ROWS) AS 'count',
                MAX(p.CARDINALITY) AS distinct_id,
                MIN(k.CARDINALITY) AS distinct_key,
                MAX(autoload.autoload_count) AS autoload_count,
                MAX(t.ENGINE) AS engine,
                MAX(t.ROW_FORMAT) AS row_format,
                MAX(t.TABLE_COLLATION) AS collation
             FROM information_schema.TABLES t
             LEFT JOIN information_schema.STATISTICS p 
				       ON t.TABLE_SCHEMA = p.TABLE_SCHEMA
						AND t.TABLE_NAME = p.TABLE_NAME
						AND LOWER(p.COLUMN_NAME) LIKE '%id'
						AND p.CARDINALITY < t.TABLE_ROWS
             LEFT JOIN information_schema.STATISTICS k 
				       ON t.TABLE_SCHEMA = k.TABLE_SCHEMA
						AND t.TABLE_NAME = k.TABLE_NAME
						AND LOWER(k.COLUMN_NAME) LIKE '%_key'
             LEFT JOIN (
                  SELECT '${p}options' TABLE_NAME,
                         DATABASE() TABLE_SCHEMA,    
                         SUM(autoload = 'yes') autoload_count
                    FROM {$p}options
                  ) autoload 
				       ON t.TABLE_SCHEMA = autoload.TABLE_SCHEMA
						AND t.TABLE_NAME = autoload.TABLE_NAME
             WHERE t.TABLE_SCHEMA = DATABASE() 
               AND t.TABLE_NAME IN ('${p}postmeta','${p}termmeta','${p}usermeta' ,'${p}posts','${p}comments', '${p}options', '${p}users', '${p}commentmeta')
             GROUP BY REPLACE(t.TABLE_NAME, 'wp_', '')";
  }

  /** get a query to list the tables, with approx row count, engine, row_format, and collation
   * @return string
   */
  public static function getTableCountsQuery() {
    global $wpdb;
    $p = $wpdb->prefix;

    return
      "SELECT REPLACE(t.TABLE_NAME, '$p', '') AS 'table',
               '$p' AS 'prefix',
                t.TABLE_ROWS AS 'count',
                t.ENGINE AS engine,
                t.ROW_FORMAT AS row_format,
                t.TABLE_COLLATION AS collation
             FROM information_schema.TABLES t
             WHERE t.TABLE_SCHEMA = DATABASE() 
               AND t.TABLE_NAME IN ('${p}postmeta','${p}termmeta','${p}usermeta' ,'${p}posts','${p}comments', '${p}options', '${p}users', '${p}commentmeta')";
  }

  public static function getTableFormatsQuery() {
    return "
            SELECT t.TABLE_NAME,
                   t.ENGINE,
                   t.ROW_FORMAT,
                   t.TABLE_ROWS row_count
                 FROM information_schema.TABLES t
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND t.TABLE_TYPE = 'BASE TABLE'
                   AND t.ENGINE IS NOT NULL";
  }

  /** @noinspection PhpUnused */
  public static function getFullTableFormatsQuery() {
    return "
            SELECT c.TABLE_NAME,
                   t.ENGINE,
                   t.ROW_FORMAT,
                   COUNT(*) column_count,
                   t.TABLE_ROWS row_count,
                   SUM(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_OCTET_LENGTH, 0)) varchar_total_octets,
                   MAX(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_OCTET_LENGTH, 0)) varchar_max_octets,
                   SUM(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_MAXIMUM_LENGTH, 0)) varchar_total_length,
                   MAX(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_MAXIMUM_LENGTH, 0)) varchar_max_length,
                   SUM(c.DATA_TYPE= 'varchar') varchar_columns,
                   SUM(IF(c.DATA_TYPE = 'char', c.CHARACTER_OCTET_LENGTH, 0)) char_total_octets,
                   MAX(IF(c.DATA_TYPE = 'char', c.CHARACTER_OCTET_LENGTH, 0)) char_max_octets,
                   SUM(IF(c.DATA_TYPE = 'char', c.CHARACTER_MAXIMUM_LENGTH, 0)) char_total_length,
                   MAX(IF(c.DATA_TYPE = 'char', c.CHARACTER_MAXIMUM_LENGTH, 0)) char_max_length,
                   SUM(c.DATA_TYPE= 'char') char_columns,
                   SUM(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_OCTET_LENGTH, 0)) longtext_total_octets,
                   MAX(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_OCTET_LENGTH, 0)) longtext_max_octets,
                   SUM(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_MAXIMUM_LENGTH, 0)) longtext_total_length,
                   MAX(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_MAXIMUM_LENGTH, 0)) longtext_max_length,
                   SUM(c.DATA_TYPE= 'longtext') longtext_columns,
                   SUM(IF(c.DATA_TYPE = 'text', c.CHARACTER_OCTET_LENGTH, 0)) text_sum_octets,
                   MAX(IF(c.DATA_TYPE = 'text', c.CHARACTER_OCTET_LENGTH, 0)) text_max_octets,
                   SUM(IF(c.DATA_TYPE = 'text', c.CHARACTER_MAXIMUM_LENGTH, 0)) text_total_length,
                   MAX(IF(c.DATA_TYPE = 'text', c.CHARACTER_MAXIMUM_LENGTH, 0)) text_max_length,
                   SUM(c.DATA_TYPE= 'text') text_columns,		        
                   SUM(
                       CASE WHEN c.DATA_TYPE IN ('varchar', 'char') THEN c.CHARACTER_OCTET_LENGTH
                            WHEN c.DATA_TYPE = 'int' THEN 4
                            WHEN c.DATA_TYPE = 'bigint' THEN 8
                            WHEN c.DATA_TYPE = 'float' THEN 4
                            WHEN c.DATA_TYPE = 'double' THEN 8
                            WHEN c.DATA_TYPE = 'date' THEN 3
                            WHEN c.DATA_TYPE = 'time' THEN 3
                            WHEN c.DATA_TYPE = 'timestamp' THEN 4
                            WHEN c.DATA_TYPE = 'datetime' THEN 5
                            ELSE 0 END                
                       ) rowlength
                 FROM information_schema.COLUMNS c
                 JOIN information_schema.TABLES t
                       ON c.TABLE_NAME = t.TABLE_NAME
                      AND c.TABLE_SCHEMA = t.TABLE_SCHEMA
                      AND c.TABLE_CATALOG = t.TABLE_CATALOG
                 WHERE c.TABLE_SCHEMA = DATABASE()
                   AND t.TABLE_TYPE = 'BASE TABLE'
                   AND t.ENGINE IS NOT NULL
                GROUP BY c.TABLE_NAME, c.TABLE_SCHEMA, c.TABLE_CATALOG
            ";
  }

  /** Get the indexes for a table, with name, add, drop columns.
   * @return string
   * @see getFullTableIndexesQuery for a slower but more complete index-description query.
   */
  public static function getTableIndexesQuery() {
    return
      "SELECT key_name, `add`, `drop`
         FROM (
          SELECT             
            IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT (s.INDEX_NAME)) key_name,   
            IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', 1, 0) is_primary,
            CASE WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN 1 
                WHEN tc.CONSTRAINT_TYPE LIKE 'UNIQUE' THEN 1
                ELSE 0 END is_unique,
            CONCAT ( 'ADD ',
                CASE WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN CONCAT ('UNIQUE KEY ', s.INDEX_NAME)
                     WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN tc.CONSTRAINT_TYPE
                                         ELSE CONCAT ('KEY', ' ', s.INDEX_NAME) END,
                ' (',
                GROUP_CONCAT(
                  IF(s.SUB_PART IS NULL, s.COLUMN_NAME, CONCAT(s.COLUMN_NAME,'(',s.SUB_PART,')'))
                  ORDER BY s.SEQ_IN_INDEX 
                  SEPARATOR ', '),
                ')'
                ) `add`,
            CONCAT ( 'DROP ',
                IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT ('KEY', ' ', s.INDEX_NAME))
                ) `drop`
          FROM information_schema.STATISTICS s
          LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                  ON s.TABLE_NAME = tc.TABLE_NAME
                 AND s.TABLE_SCHEMA = tc.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = tc.CONSTRAINT_CATALOG 
                 AND s.INDEX_NAME = tc.CONSTRAINT_NAME
         WHERE s.TABLE_SCHEMA = DATABASE()
           AND s.TABLE_NAME = %s
           /* #37 don't do anything with FULLTEXT indexes */
           AND s.INDEX_TYPE <> 'FULLTEXT'
         GROUP BY s.INDEX_NAME
        ) q
        ORDER BY is_primary DESC, is_unique DESC, key_name";
  }

  /** Get the index descriptions for a table.
   * @return string
   * @see getTableIndexesQuery for a faster index-description query.
   */
  public static function getFullTableIndexesQuery() {
    return
      "        SELECT *,
               IF(is_autoincrement = 1, columns, NULL) autoincrement_column
         FROM (
          SELECT             
           IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT (s.INDEX_NAME)) key_name,   
           s.TABLE_NAME,  
               IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', 1, 0) is_primary,
               CASE WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN 1 
                    WHEN tc.CONSTRAINT_TYPE LIKE 'UNIQUE' THEN 1
                    ELSE 0 END is_unique,
               IF(MAX(c.EXTRA) = 'auto_increment', 1, 0) 'contains_autoincrement',
               IF(MAX(c.EXTRA) = 'auto_increment' AND COUNT(*) = 1, 1, 0) 'is_autoincrement',
               GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX SEPARATOR ', ') 'columns',
               CONCAT ( 'ADD ',
                CASE WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN CONCAT ('UNIQUE KEY ', s.INDEX_NAME)
                     WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN tc.CONSTRAINT_TYPE
                                         ELSE CONCAT ('KEY', ' ', s.INDEX_NAME) END,
                ' (',
                GROUP_CONCAT(
                  IF(s.SUB_PART IS NULL, s.COLUMN_NAME, CONCAT(s.COLUMN_NAME,'(',s.SUB_PART,')'))
                  ORDER BY s.SEQ_IN_INDEX 
                  SEPARATOR ', '),
                ')'
                ) `add`,
               CONCAT ( 'DROP ',
                IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT ('KEY', ' ', s.INDEX_NAME))
                ) `drop`,
               CONCAT ('ALTER TABLE ', s.TABLE_SCHEMA, '.', s.TABLE_NAME, ' ') `alter`,	
               MAX(t.ENGINE) 'engine',
               MAX(t.ROW_FORMAT) 'row_format'
          FROM information_schema.STATISTICS s
          LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                  ON s.TABLE_NAME = tc.TABLE_NAME
                 AND s.TABLE_SCHEMA = tc.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = tc.CONSTRAINT_CATALOG 
                 AND s.INDEX_NAME = tc.CONSTRAINT_NAME
          LEFT JOIN information_schema.COLUMNS c
                  ON s.TABLE_NAME = c.TABLE_NAME
                 AND s.TABLE_SCHEMA = c.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = c.TABLE_CATALOG 
                 AND s.COLUMN_NAME = c.COLUMN_NAME
      LEFT JOIN information_schema.TABLES t
                  ON s.TABLE_NAME = t.TABLE_NAME
                 AND s.TABLE_SCHEMA = t.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = t.TABLE_CATALOG
         WHERE s.TABLE_SCHEMA = DATABASE()
           AND s.TABLE_NAME = %s
         GROUP BY TABLE_NAME, INDEX_NAME
        ) q
        ORDER BY TABLE_NAME, is_primary DESC, is_unique DESC, key_name";
  }

  /** Get a pseudo-random text string from letters and numbers.
   *  This function does not generate cryptographically secure values, and should not be used for cryptographic purposes.
   *
   * @param integer $length desired string length
   *
   * @return string random string suitable for upload id and similar purposes
   */
  public static function getRandomString( $length ) {
    /* some characters removed from this set to reduce confusion reading aloud */
    $characters       = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXYZ';
    $charactersLength = strlen( $characters );
    $randomString     = '';
    for ( $i = 0; $i < $length; $i ++ ) {
      $randomString .= $characters[ rand( 0, $charactersLength - 1 ) ];
    }

    return $randomString;
  }

  /** Transform a result set array of objects, like from SHOW VARIABLES, to an object
   *
   * @param array $rows Array of {Variable_name, Value} objects
   *
   * @return object
   */
  public static function toObject( $rows ) {
    $variables = [];
    foreach ( $rows as $row ) {
      $variables[ $row->Variable_name ] = is_numeric( $row->Value ) ? $row->Value + 0 : $row->Value;
    }

    return (object) $variables;
  }

  /** Make an ordinary 2-column result set object look like a result set array
   *
   * @param object $rows result set to transform
   * @param string $nameCaption the name of the first column, default Item
   * @param string $valueCaption the name of the second column, default Value
   *
   * @return array
   */
  public static function toResultSet( $rows, $nameCaption = 'Item', $valueCaption = 'Value' ) {
    $res = [];
    foreach ( $rows as $name => $value ) {
      $rsrow = [ $nameCaption => $name, $valueCaption => $value ];
      $res[] = $rsrow;
    }

    return $res;
  }

  /** Retrieve WordPress configuration
   *
   * @param ImfsDb $db database instance
   *
   * @return array describing the current WordPress configuration
   */
  public static function getWpDescription( $db ) {
    global $wp_db_version;
    global $wp_version;
    global $_SERVER;
    $dropins    = get_dropins();
    $dropinList = [];
    foreach ( $dropins as $dropin ) {
      $dropinList[] = $dropin ['Name'];
    }
    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    $wordpress = [
      'webserverversion' => $_SERVER['SERVER_SOFTWARE'],
      'wp_version'       => $wp_version,
      'wp_db_version'    => $wp_db_version,
      'phpversion'       => phpversion(),
      'mysqlversion'     => $db->semver->version,
      'pluginversion'    => index_wp_mysql_for_speed_VERSION_NUM,
      'is_multisite'     => is_multisite(),
      'is_main_site'     => is_main_site(),
      'current_blog_id'  => get_current_blog_id(),
      'active_plugins'   => implode( '|', ImfsQueries::getActivePlugins() ),
      'active_dropins'   => implode( '|', $dropinList ),
    ];

    return $wordpress;
  }

  /** Get a list of active plugins.
   * @return array plugin names
   */
  public static function getActivePlugins() {
    $plugins = get_plugins();
    $result  = [];
    foreach ( $plugins as $path => $desc ) {
      if ( is_plugin_active( $path ) ) {
        $result[] = $desc['Name'];
      }
    }

    return $result;
  }

}


