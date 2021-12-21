<?php


/**
 * @param int unconstrained  1 means barracuda, 0 antelope
 *
 * @return array
 */
function getHighPerformanceIndexes( $unconstrained ): array {

	/* When changing a PRIMARY KEY,
	 * for example to a compound clustered index
	 * you also need to add a UNIQUE KEY
	 * to hold the autoincrementing column.
	 * Put that UNIQUE KEY first in the list of keys to add.
	 * That makes sure we always have some sort of
	 * no-duplicates constraint on the autoincrementing ID.
	 */


	/* these are the indexes not dependent on Antelope or Barracuda */
	$reindexAnyway = array(
		"options" => array(
			"option_id"   => "ADD UNIQUE KEY option_id (option_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
			"option_name" => "ADD UNIQUE KEY option_name (option_name)",
		),
		"posts"   => array(
			"PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
			"post_name"        => "ADD KEY post_name (post_name(191))",
			"post_parent"      => "ADD KEY post_parent (post_parent)",
			"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
			"post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date, ID)"
		),

		"comments" => array(
			"comment_ID"                   => "ADD UNIQUE KEY comment_ID (comment_ID)",
			"PRIMARY KEY"                  => "ADD PRIMARY KEY (comment_post_ID, comment_ID)",
			"comment_approved_date_gmt"    => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
			"comment_date_gmt"             => "ADD KEY comment_date_gmt (comment_date_gmt)",
			"comment_parent"               => "ADD KEY comment_parent (comment_parent)",
			"comment_author_email"         => "ADD KEY comment_author_email (comment_author_email(32))",
			"comment_post_parent_approved" => "ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_type, comment_date_gmt)",
		),
	);

	/* These are the Barracuda-dependent (unprefixed) indexes
	 * Notice that the indexed columns following prefix-indexed columns
	 * [for example post_id in (meta_key, meta_value(32), post_id) ]
	 * are intended for covering-index use, as if in an INCLUDE clause. */
	$reindexWithoutConstraint = array(
		"postmeta" => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_key, meta_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), post_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key, post_id)",
		),

		"usermeta"    => array(
			"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), user_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key, user_id)",
		),
		"termmeta"    => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key, meta_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), term_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key, meta_id)",
		),
		'commentmeta' => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_key, comment_id, meta_id)",
			"comment_id"  => "ADD KEY comment_id (comment_id, meta_key, meta_value(32))",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key, comment_id)",
		),
		"users"       => array(
			"PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
			"user_login_key" => "ADD KEY user_login_key (user_login)",
			"user_nicename"  => "ADD KEY user_nicename (user_nicename)",
			"user_email"     => "ADD KEY user_email (user_email)",
			"display_name"   => "ADD KEY display_name (display_name)",
		),
	);

	/* these are the Antelope-dependent (prefixed) indexes */
	/* we can use shorter prefix indexes and still get almost all the value */
	$reindexWith191Constraint = array(
		"postmeta" => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_id)",
			"post_id"     => "ADD KEY post_id (post_id, meta_key(32), meta_value(32))",
			"meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), post_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key(32), post_id)",
		),

		"usermeta"    => array(
			"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, umeta_id)",
			"user_id"     => "ADD KEY user_id (user_id, meta_key(32), meta_value(32))",
			"meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), user_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key(32), user_id)",
		),
		"termmeta"    => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_id)",
			"term_id"     => "ADD KEY term_id (term_id, meta_key(32), meta_value(32))",
			"meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), term_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key(32), term_id)",
		),
		'commentmeta' => array(
			"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
			"PRIMARY KEY" => "ADD PRIMARY KEY (comment_id, meta_id)",
			"comment_id"  => "ADD KEY comment_id (comment_id, meta_key(32), meta_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), comment_id)",
			"meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key(32), comment_id)",
		),
		"users"       => array(
			"PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
			"user_login_key" => "ADD KEY user_login_key (user_login)",
			"user_nicename"  => "ADD KEY user_nicename (user_nicename)",
			"user_email"     => "ADD KEY user_email (user_email)",
			"display_name"   => "ADD KEY display_name (display_name(32))",
		),

	);
	switch ( $unconstrained ) {
		case 1: /* barracuda */
			$reindexes = array_merge( $reindexWithoutConstraint, $reindexAnyway );
			break;
		case 0: /* antelope */
			$reindexes = array_merge( $reindexWith191Constraint, $reindexAnyway );
			break;
		default:
			$reindexes = $reindexAnyway;
			break;
	}

	return $reindexes;
}


function getStandardIndexes( $unconstrained ): array {
	/* these are WordPress's standard indexes. */
	return array(
		'postmeta'    => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
			"post_id"     => "ADD KEY post_id (post_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'usermeta'    => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
			"user_id"     => "ADD KEY user_id (user_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'termmeta'    => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
			"term_id"     => "ADD KEY term_id (term_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		'options'     => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
			"option_name" => "ADD UNIQUE KEY option_name (option_name)",
			"autoload"    => "ADD KEY autoload (autoload)"
		),
		'posts'       => array(
			"PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
			"post_name"        => "ADD KEY post_name (post_name(191))",
			"post_parent"      => "ADD KEY post_parent (post_parent)",
			"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
			"post_author"      => "ADD KEY post_author (post_author)",
		),
		'comments'    => array(
			"PRIMARY KEY"               => "ADD PRIMARY KEY (comment_ID)",
			"comment_post_ID"           => "ADD KEY comment_post_ID (comment_post_ID)",
			"comment_approved_date_gmt" => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
			"comment_date_gmt"          => "ADD KEY comment_date_gmt (comment_date_gmt)",
			"comment_parent"            => "ADD KEY comment_parent (comment_parent)",
			"comment_author_email"      => "ADD KEY comment_author_email (comment_author_email(10))",
			"woo_idx_comment_type"      => "ADD KEY woo_idx_comment_type (comment_type)",
		),
		'commentmeta' => array(
			"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
			"comment_id"  => "ADD KEY comment_id (comment_id)",
			"meta_key"    => "ADD KEY meta_key (meta_key(191))",
		),
		"users"       => array(
			"PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
			"user_login_key" => "ADD KEY user_login_key (user_login)",
			"user_nicename"  => "ADD KEY user_nicename (user_nicename)",
			"user_email"     => "ADD KEY user_email (user_email)",
		),
	);
}

/** the list of tables we can handle
 *
 * @param $unconstrained
 *
 * @return array
 */
function getIndexableTables( $unconstrained ): array {
	$tables = [];
	$x      = getStandardIndexes( $unconstrained );
	foreach ( $x as $table => $indexes ) {
		$tables[ $table ] = 1;
	}
	$x = getHighPerformanceIndexes( $unconstrained );
	foreach ( $x as $table => $indexes ) {
		$tables[ $table ] = 1;
	}
	$result = [];
	foreach ( $tables as $table => $z ) {
		$result[] = $table;
	}

	return $result;
}
