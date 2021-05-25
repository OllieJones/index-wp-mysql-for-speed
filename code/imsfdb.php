<?php

function getQueries() {
	global $wpdb;
	$p = $wpdb->prefix;
	/** @var array $queryArray an array of arrays of queries for this to use */
	$queryArray = array(
		"postmeta" => array(
			"tablename"     => "postmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (post_id, meta_key(191), meta_id)",
				"DROP KEY post_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), post_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)"
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
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"user_id"     => "ADD KEY user_id (user_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY umeta_id (umeta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (user_id, meta_key(191), umeta_id)",
				"DROP KEY user_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), user_id)"
			),
			"check.disable" => array(
				"umeta_id"    => "ADD KEY umeta_id (umeta_id)",
				"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key(191), umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), user_id"
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
				"term_id"     => "ADD KEY term_id (term_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (term_id, meta_key(191), meta_id)",
				"DROP KEY term_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), term_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key(191), meta_id)",
				"meta_id"     => "ADD KEY meta_id (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), term_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),
		"options"  => array(
			"tablename"     => "options",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
				"option_name" => "ADD UNIQUE KEY option_name (option_name)",
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
				"option_name" => "ADD UNIQUE KEY option_name (option_name)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (option_id)",
				"DROP KEY option_id",
				"ADD KEY autoload (autoload)"
			),
		),
		"posts"    => array(
			"tablename"     => "posts",
			"check.enable"  => array(
				// no need to verify the keys we don't alter
				//"PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
				//"post_name"        => "ADD KEY post_name (post_name)",
				//"post_parent"      => "ADD KEY post_parent (post_parent)",
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
				"post_author"      => "ADD KEY post_author (post_author)"
			),
			"enable"        => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date ON wp_posts (post_type, post_status, post_date, post_author, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author ON wp_posts (post_author, post_type, post_status, post_date, ID)"
			),
			"check.disable" => array(
				//"PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
				//"post_name"        => "ADD KEY post_name (post_name)",
				//"post_parent"      => "ADD KEY post_parent (post_parent)",
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
				"post_author"      => "ADD KEY post_author ON wp_posts (post_author, post_type, post_status, post_date, ID)"
			),
			"disable"       => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date ON wp_posts (post_type, post_status, post_date, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author ON wp_posts (post_author)"
			),
		),
		"comments" => array(
			"tablename"     => "comments",
			"check.enable"  => array(
				"PRIMARY KEY"               => "ADD PRIMARY KEY (comment_ID)",
				"comment_post_ID"           => "ADD KEY comment_post_ID (comment_post_ID)",
				"comment_approved_date_gmt" => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
				"comment_date_gmt"          => "ADD KEY comment_date_gmt (comment_date_gmt)",
				"comment_parent"            => "ADD KEY comment_parent (comment_parent)",
				"comment_author_email"      => "ADD KEY comment_author_email (comment_author_email(10))"
			),
			"enable"        => array(
				"ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved)"
			),
			"check.disable" => array(
				"PRIMARY KEY"                  => "ADD PRIMARY KEY (comment_ID)",
				"comment_post_ID"              => "ADD KEY comment_post_ID (comment_post_ID",
				"comment_approved_date_gmt"    => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
				"comment_date_gmt"             => "ADD KEY comment_date_gmt (comment_date_gmt)",
				"comment_parent"               => "ADD KEY comment_parent (comment_parent)",
				"comment_author_email"         => "ADD KEY comment_author_email (comment_author_email)",
				"comment_post_parent_approved" => "ADD KEY post_parent_approved (comment_post_ID, comment_parent, comment_approved)"
			),
			"disable"       => array(
				"DROP KEY comment_post_parent_approved"
			),
		),
		"indexes"  => "		
        SELECT IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT (s.INDEX_NAME)) key_name,   
		       IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', 1, 0) is_primary,
		       CASE WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN 1 
		            WHEN tc.CONSTRAINT_TYPE LIKE 'UNIQUE' THEN 1
		            ELSE 0 END is_unique,
		       IF(MAX(c.EXTRA) = 'auto_increment', 1, 0) 'contains_autoincrement',
		       IF(MAX(c.EXTRA) = 'auto_increment' AND COUNT(*) = 1, 1, 0) 'is_autoincrement',
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
		       CONCAT ('ALTER TABLE ', s.TABLE_SCHEMA, '.', s.TABLE_NAME, ' ') `alter`
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
		 WHERE s.TABLE_SCHEMA = DATABASE()
   AND s.TABLE_NAME = %s
		 GROUP BY s.TABLE_NAME, s.INDEX_NAME
		 ORDER BY s.TABLE_NAME, s.INDEX_NAME",

		"dbstats" => array(
			"SELECT VARIABLE_NAME variable, COALESCE(SESSION_VALUE, GLOBAL_VALUE) value FROM information_schema.SYSTEM_VARIABLES ORDER BY VARIABLE_NAME",
			/* fetch key/value statistics */
			<<<QQQ
		SELECT 'postmeta' AS 'table',
		       '${p}' AS 'prefix',
		        COUNT(*) AS 'count',
		        COUNT(DISTINCT post_id) distinct_id,
		        COUNT(DISTINCT meta_key) distinct_key,
		        MAX(LENGTH(meta_key)) key_max_length,
		        MAX(LENGTH(meta_value)) value_max_length,
		        MIN(LENGTH(meta_key)) key_min_length,
		        MIN(LENGTH(meta_value)) value_min_length,
		        SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
		        SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
		        0 autoload_count
		  FROM ${p}postmeta
		UNION ALL
		SELECT 'usermeta' AS 'table',
		       '${p}' AS 'prefix',
		        COUNT(*) AS 'count',
		        COUNT(DISTINCT user_id) distinct_id,
		        COUNT(DISTINCT meta_key) distinct_key,
		        MAX(LENGTH(meta_key)) key_max_length,
		        MAX(LENGTH(meta_value)) meta_value_max_length,
		        MIN(LENGTH(meta_key)) key_min_length,
		        MIN(LENGTH(meta_value)) value_min_length,
		        SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
		        SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
		        0 autoload_count
		  FROM ${p}usermeta
		UNION ALL
		SELECT 'termmeta' AS 'table',
		       '${p}' AS 'prefix',
		        COUNT(*) AS 'count',
		        COUNT(DISTINCT term_id) distinct_id,
		        COUNT(DISTINCT meta_key) distinct_key,
		        MAX(LENGTH(meta_key)) key_max_length,
		        MAX(LENGTH(meta_value)) value_max_length,
		        MIN(LENGTH(meta_key)) key_min_length,
		        MIN(LENGTH(meta_value)) value_min_length,
		        SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
		        SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
		        0 autoload_count
		  FROM ${p}termmeta
		UNION ALL 
		SELECT 'options' AS  'table',
		       '${p}' AS 'prefix',
		        COUNT(*) AS 'count',
		        0 AS distinct_id,
		        COUNT(DISTINCT option_name) distinct_meta_key,
		        MAX(LENGTH(option_name)) key_max_length,
		        MAX(LENGTH(option_value)) value_max_length,
		        MIN(LENGTH(option_name)) min_length,
		        MIN(LENGTH(option_value)) value_min_length,
		        SUM(CASE WHEN LENGTH(option_name) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
		        SUM(CASE WHEN LENGTH(option_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
		        SUM(CASE WHEN autoload = 'yes' THEN 1 ELSE 0 END) autoload_count
		  FROM ${p}options;
		QQQ
		)
	);

	return $queryArray;
}


class ImfsDb {

	public array $queries;
	public array $messages;
	public bool $lookForMissingKeys = true;
	public bool $lookForExtraKeys = false;

	public function __construct() {
		$this->queries  = getQueries();
		$this->messages = array();
	}

	/**
	 * @return array server information
	 */
	public function getStats(): array {
		global $wpdb;
		$output  = array();
		$dbstats = $this->queries['dbstats'];
		foreach ( $dbstats as $q ) {
			$results = $wpdb->get_results( $q, OBJECT_K );
			array_push( $output, $results );
		}

		return $output;
	}

	/** List of tables to manipulate
	 * @return Generator tables manipulated by this module
	 */
	public function tables(): Generator {
		foreach ( $this->queries as $name => $stmts ) {
			if ( array_key_exists( 'tablename', $stmts ) && $name == $stmts['tablename'] ) {
				yield $name;
			}
		}

	}

	/**
	 * Retrieve DML for the keys in the named table.
	 * @param $name table name (without prefix)
	 *
	 * @return array|object|null
	 */
	public function getKeyDML( $name ) {
		global $wpdb;
		$stmt = $wpdb->prepare( $this->queries['indexes'], $wpdb->prefix . $name );

		return $wpdb->get_results( $stmt, OBJECT_K );
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
		foreach ( $indexes as $index => $desc ) {
			if ( $this->lookForExtraKeys && ! array_key_exists( $index, $checks ) ) {
				$msg = sprintf(
				/* translators: %1$s is table name, %2$s is key (index) name */
					__( 'Table %1$s: Found an unexpected key %2$s. Cannot rekey this table' ),
					$table, $index
				);
				array_push( $this->messages, $msg );
				$result = false;
			} else if ( $desc->add !== $checks[ $index ] ) {
				$msg = sprintf(
				/* translators: %1$s is table name, %2$s is key (index) name, %3$s is expected key, %4$s is actual */
					__( 'Table %1$s: Found an unexpected definition for key %2$s. It should be %3$s, but is %4$s. Cannot rekey this table' ),
					$table, $index, $desc->add, $checks[ $index ]
				);
				array_push( $this->messages, $msg );
				$result = false;
			}
		}

		return $result;
	}

	protected function rekeyTable( $action, $name ) {
		global $wpdb;
		$block = $this->queries[ $name ];
		$stmts = $block[ $action ];
		$table = $wpdb->prefix . $name;
		if ( $action ) {
			foreach ( $stmts as $fragment ) {
				$q = "ALTER TABLE " . $table . " " . $fragment;
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

	/** Check the tables for any issues.
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
}