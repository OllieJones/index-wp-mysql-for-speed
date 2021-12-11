<?php

function ImfsStripPrefix( $name ) {
	global $wpdb;
	$pattern = '/^' . $wpdb->prefix . '/';

	return preg_replace( $pattern, '', $name );
}

function ImfsRedactHost( $host ) {
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

function makeNumeric( $ob ): object {
	$result = array();
	foreach ( $ob as $key => $val ) {
		if ( is_numeric( $val ) ) {
			$val = intval( $val );
		}
		$result[ $key ] = $val;
	}

	return (object) $result;
}

function getMySQLVersion(): object {
	global $wpdb;
	$semver  = " 
	 SELECT VERSION() version,
	        1 canreindex,
	        0 unconstrained,
            CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) major,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED) minor,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(VERSION(), '-', '.'), '.', 3), '.', -1) AS UNSIGNED) build,
            '' fork, '' distro";
	$results = $wpdb->get_results( index_wp_mysql_for_speed_querytag . $semver );
	$results = $results[0];

	$results->db_host = imfsRedactHost( DB_HOST );
	$ver              = explode( '-', $results->version, 3 );
	if ( count( $ver ) >= 2 ) {
		$results->fork = $ver[1];
	}
	if ( count( $ver ) >= 3 ) {
		$results->distro = $ver[2];
	}
	$isMaria = ! ! stripos( $results->version, "mariadb" );
	/* work out whether we have Antelope or Barracuda InnoDB format */
	/* mysql 8+ */
	if ( ! $isMaria && $results->major >= 8 ) {
		$results->unconstrained = 1;

		return makeNumeric( $results );
	}
	/* work out whether we have Antelope or Barracuda InnoDB format */
	/* mariadb 10.3 + */
	if ( $isMaria && $results->major >= 10 && $results->minor >= 3 ) {
		$results->unconstrained = 1;

		return makeNumeric( $results );
	}
	/* mariadb 10.2 ar before */
	if ( $isMaria && $results->major >= 10 ) {

		$results->unconstrained = hasLargePrefix();

		return makeNumeric( $results );
	}

	/* waaay too old */
	if ( $results->major < 5 ) {
		$results->canreindex = 0;

		return makeNumeric( $results );
	}
	/* before 5.5 */
	if ( $results->major == 5 && $results->minor < 5 ) {
		$results->canreindex = 0;

		return makeNumeric( $results );
	}
	/* older 5.5 */
	if ( $results->major === 5 && $results->minor === 5 && $results->build < 62 ) {
		$results->canreindex = 0;

		return makeNumeric( $results );
	}
	/* older 5.6 */
	if ( $results->major === 5 && $results->minor === 6 && $results->build < 4 ) {
		$results->canreindex = 0;

		return makeNumeric( $results );
	}
	$results->unconstrained = hasLargePrefix();

	return makeNumeric( $results );
}

/**
 * @return int 1 if the MySQL instance says it has innodb_large_prefix, 0 otherwise.
 */
function hasLargePrefix(): int {
	global $wpdb;
	/* innodb_large_prefix variable is missing in MySQL 8+ */
	$prefix = $wpdb->get_results( index_wp_mysql_for_speed_querytag . "SHOW VARIABLES LIKE 'innodb_large_prefix'", OBJECT_K );
	if ( $prefix && is_array( $prefix ) && array_key_exists( 'innodb_large_prefix', $prefix ) ) {
		$prefix = $prefix['innodb_large_prefix'];

		return ( $prefix->Value === 'ON' || $prefix->Value === '1' ) ? 1 : 0;
	}

	return 0;
}

/**
 * @param $semver
 *
 * @return array
 */
function getReindexingInstructions( $semver ): array {
	$reindexAnyway = array(
		"posts"    => array(
			"tablename"     => "posts",
			"check.enable"  => array(
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
				"post_author"      => "ADD KEY post_author (post_author)"
			),
			"enable"        => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author (post_author, post_type, post_status, post_date, ID)"
			),
			"check.disable" => array(
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
				"post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date, ID)"
			),
			"disable"       => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date (post_type, post_status, post_date, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author (post_author)"
			),
		),
		"comments" => array(
			"tablename"     => "comments",
			"check.enable"  => array(
				"comment_post_parent_approved" => null,
			),
			"enable"        => array(
				"ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_ID)"
			),
			"check.disable" => array(
				"comment_post_parent_approved" => "ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_ID)"
			),
			"disable"       => array(
				"DROP KEY comment_post_parent_approved"
			),
		)
	);


	$reindexWithoutConstraint = array(
		"postmeta" => array(
			"tablename"     => "postmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)",
				"meta_id"     => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (post_id, meta_key, meta_id)",
				"DROP KEY post_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, post_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_key, meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key, post_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"ADD KEY post_id (post_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),

		"usermeta" => array(
			"tablename"     => "usermeta",
			"mainSiteOnly"  => true,
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"user_id"     => "ADD KEY user_id (user_id)",
				"umeta_id"    => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY umeta_id (umeta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
				"DROP KEY user_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, user_id)"
			),
			"check.disable" => array(
				"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
				"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key, user_id)",
				"user_id"     => null,
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (umeta_id)",
				"DROP KEY umeta_id",
				"ADD KEY user_id (user_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),

		),
		"termmeta" => array(
			"tablename"     => "termmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"term_id"     => "ADD KEY term_id (term_id)",
				"meta_id"     => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (term_id, meta_key, meta_id)",
				"DROP KEY term_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, term_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key, meta_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key, term_id)",
				"term_id"     => null,
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
				"ADD KEY term_id (term_id)",
			),

		),
		"options"  => array(
			"tablename"     => "options",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
				"autoload"    => "ADD KEY autoload (autoload)",
				"option_name" => "ADD UNIQUE KEY option_name (option_name)",
				"option_id"   => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY option_id (option_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (autoload, option_id)",
				"DROP KEY autoload"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
				"option_id"   => "ADD UNIQUE KEY option_id (option_id)",
				"option_name" => "ADD UNIQUE KEY option_name (option_name)",
				"autoload"    => null,
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (option_id)",
				"DROP KEY option_id",
				"ADD KEY autoload (autoload)"
			),
		)
	);

	$reindexWith191Constraint = array(
		"postmeta" => array(
			"tablename"     => "postmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)",
				"meta_id"     => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (post_id, meta_id)",
				"DROP KEY post_id",
				"ADD KEY post_id (post_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), post_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_id)",
				"post_id"     => "ADD KEY post_id (post_id, meta_key(191))",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), post_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY post_id",
				"ADD KEY post_id (post_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),

		"usermeta" => array(
			"tablename"     => "usermeta",
			"mainSiteOnly"  => true,
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"user_id"     => "ADD KEY user_id (user_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY umeta_id (umeta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (user_id, umeta_id)",
				"DROP KEY user_id",
				"ADD KEY user_id (user_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), user_id)"
			),
			"check.disable" => array(
				"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
				"user_id"     => "ADD KEY user_id (user_id, meta_key(191))",
				"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), user_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (umeta_id)",
				"DROP KEY umeta_id",
				"DROP KEY user_id",
				"ADD KEY user_id (user_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),
		"termmeta" => array(
			"tablename"     => "termmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"term_id"     => "ADD KEY term_id (term_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (term_id, meta_id)",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), term_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
				"term_id"     => "ADD KEY term_id (term_id, meta_key(191))",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), term_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id)",
			),
		),
		"options"  => array(
			"tablename"     => "options",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
				"autoload"    => "ADD KEY autoload (autoload)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY option_id (option_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (autoload, option_id)",
				"DROP KEY autoload"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
				"option_id"   => "ADD UNIQUE KEY option_id (option_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (option_id)",
				"DROP KEY option_id",
				"ADD KEY autoload (autoload)"
			),
		)
	);
	switch ( $semver->unconstrained ) {
		case 1:
			return array_merge( $reindexWithoutConstraint, $reindexAnyway );
		case 0:
			return array_merge( $reindexWith191Constraint, $reindexAnyway );
		default:
			return $reindexAnyway;
	}
}

function getStandardIndexes(): array {
	return array(
		'postmeta' => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
			"post_id"     => "ADD KEY post_id (post_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'usermeta' => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
			"user_id"     => "ADD KEY user_id (user_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'termmeta' => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
			"term_id"     => "ADD KEY term_id (term_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'options'  => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
			"option_name" => "ADD UNIQUE KEY option_name (option_name)",
			"autoload"    => "ADD KEY autoload (autoload)"
		),
		'posts'    => array(
			"PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
			"post_parent"      => "ADD KEY post_parent (post_parent)",
			"post_name"        => "ADD KEY post_name (post_name(191))",
			"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
			"post_author"      => "ADD KEY post_author (post_author)",
		),
		'comments' => array(
			"PRIMARY KEY"               => "ADD PRIMARY KEY (comment_ID)",
			"comment_post_ID"           => "ADD KEY comment_post_ID (comment_post_ID)",
			"comment_approved_date_gmt" => "ADD KEY comment_approved_date_gmt (comment_approved,comment_date_gmt)",
			"comment_date_gmt"          => "ADD KEY comment_date_gmt (comment_date_gmt)",
			"comment_parent"            => "ADD KEY comment_parent (comment_parent)",
			"comment_author_email"      => "ADD KEY comment_author_email (comment_author_email(10))"
		)
	);
}

function getQueries(): array {
	global $wpdb;
	$p     = $wpdb->prefix;
	$stats = array(
		"SELECT REPLACE(t.TABLE_NAME, '${p}', '') AS 'table',
               '${p}' AS 'prefix',
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
               AND t.TABLE_NAME IN ('${p}postmeta','${p}termmeta','${p}usermeta' ,'${p}posts','${p}comments', '${p}options', '${p}users')
             GROUP BY REPLACE(t.TABLE_NAME, 'wp_', '')",
	);

	$queryArray = array(
		"indexes" => "		
        SELECT *,
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
               MAX(t.ROW_FORMAT) 'row_format',
            r.rowlength
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
      LEFT JOIN (
        SELECT c.TABLE_NAME,
               c.TABLE_SCHEMA,
               c.TABLE_CATALOG,
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
        GROUP BY c.TABLE_NAME, c.TABLE_SCHEMA, c.TABLE_CATALOG
        ) r   ON s.TABLE_NAME = r.TABLE_NAME
                 AND s.TABLE_SCHEMA = r.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = r.TABLE_CATALOG
         WHERE s.TABLE_SCHEMA = DATABASE()
           AND s.TABLE_NAME = %s
         GROUP BY TABLE_NAME, INDEX_NAME
        ) q
        ORDER BY TABLE_NAME, is_primary DESC, is_unique DESC, key_name",

		"dbstats" => array(
			"SHOW VARIABLES",
			implode( " UNION ALL ", $stats ),
			"SELECT c.TABLE_NAME,
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
        ",
			"SHOW GLOBAL STATUS",
			/* make this match SHOW STATUS in column names */
			"SELECT NAME Variable_name, COUNT Value
               FROM information_schema.INNODB_METRICS
              WHERE COMMENT NOT LIKE 'Deprecated%'
                AND STATUS='enabled'
              ORDER BY NAME"
		)
	);

	return $queryArray;
}

