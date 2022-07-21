<?php

class ImfsGetIndexes {

  public static $imfsStandardIndexes;


  /** the list of tables we can handle
   *
   * @param $unconstrained
   *
   * @return array
   * @throws ImfsException
   */
  static function getIndexableTables( $unconstrained ) {
    $tables = [];
    $x      = ImfsGetIndexes::getStandardIndexes( $unconstrained );
    foreach ( $x as $table => $indexes ) {
      $tables[ $table ] = 1;
    }
    $x = ImfsGetIndexes::getHighPerformanceIndexes( $unconstrained );
    foreach ( $x as $table => $indexes ) {
      $tables[ $table ] = 1;
    }
    $result = [];
    foreach ( $tables as $table => $z ) {
      $result[] = $table;
    }

    return $result;
  }

  /**
   * @param $unconstrained bool false if Antelope
   * @param int $version WordPress database version.
   *
   * @return array
   * @noinspection PhpUnusedParameterInspection
   */
  static function getStandardIndexes( $unconstrained, $version = 51917 ) {
    /* these are WordPress's standard indexes for database version 53496 and before.
     * see the end of this file for their definitions */
    return ImfsGetIndexes::$imfsStandardIndexes;
  }

  /**
   *
   * @param number $unconstrained 0: Antelope, prefix indexes   1:Barracuda, no prefix indexes
   * @param float $version The first two levels of the plugin version  (2.3.4 gets 2.3)
   *
   * @return array
   * @throws ImfsException
   */
  static function getHighPerformanceIndexes( $unconstrained, $version = 1.4 ) {
    if ( $version === 1.4 ) {
      return ImfsGetIndexes::getHighPerformanceIndexes1_4( $unconstrained );
    }
    if ( ! isset( $version ) || $version <= 1.3 ) {
      return ImfsGetIndexes::getHighPerformanceIndexes1_3( $unconstrained );
    }

    throw new ImfsException( "unknown plugin version when retrieving indexing instructions" . $version );
  }

  /**
   * @param int unconstrained  1 means barracuda, 0 antelope
   *
   * @return array
   */
  static function getHighPerformanceIndexes1_4( $unconstrained ) {

    /* When changing a PRIMARY KEY,
     * for example to a compound clustered index
     * you also need to add a UNIQUE KEY
     * to hold the autoincrementing column.
     * Put that UNIQUE KEY first in the list of keys to add.
     * That makes sure we always have some sort of
     * no-duplicate constraint on the autoincrementing ID.
     */


    /* these are the indexes not dependent on Antelope or Barracuda */
    $reindexAnyway = [
      "options"  => [
        "option_id"   => "ADD UNIQUE KEY option_id (option_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (option_name)",
        "autoload"    => "ADD KEY autoload (autoload)",
      ],
      "comments" => [
        "comment_ID"                   => "ADD UNIQUE KEY comment_ID (comment_ID)",
        "PRIMARY KEY"                  => "ADD PRIMARY KEY (comment_post_ID, comment_ID)",
        "comment_approved_date_gmt"    => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt, comment_ID)",
        "comment_date_gmt"             => "ADD KEY comment_date_gmt (comment_date_gmt, comment_ID)",
        "comment_parent"               => "ADD KEY comment_parent (comment_parent, comment_ID)",
        "comment_author_email"         => "ADD KEY comment_author_email (comment_author_email, comment_post_ID, comment_ID)",
        "comment_post_parent_approved" => "ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_type, user_id, comment_date_gmt, comment_ID)",
      ],
    ];

    /* These are the Barracuda-dependent (unprefixed) indexes
     * Notice that the indexed columns following prefix-indexed columns
     * [for example post_id in (meta_key, meta_value(32), post_id) ]
     * are intended for covering-index use, as if in an INCLUDE clause. */
    $reindexWithoutConstraint = [
      "postmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_key, meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), post_id, meta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), meta_id)",
      ],

      "usermeta"    => [
        "umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), user_id, umeta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), umeta_id)",
      ],
      "termmeta"    => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key, meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, meta_value(32), term_id, meta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), meta_id)",
      ],
      "posts"       => [
        "PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
        "post_name"        => "ADD KEY post_name (post_name)",
        "post_parent"      => "ADD KEY post_parent (post_parent, post_type, post_status)",
        "type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author)",
        "post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date)",
      ],
      'commentmeta' => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (meta_key, comment_id, meta_id)",
        "comment_id"  => "ADD KEY comment_id (comment_id, meta_key, meta_value(32))",
        "meta_value"  => "ADD KEY meta_value (meta_value(32))",
      ],
      "users"       => [
        "PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
        "user_login_key" => "ADD KEY user_login_key (user_login)",
        "user_nicename"  => "ADD KEY user_nicename (user_nicename)",
        "user_email"     => "ADD KEY user_email (user_email)",
        "display_name"   => "ADD KEY display_name (display_name)",
      ],
    ];

    /* these are the Antelope-dependent (prefixed) indexes */
    /* we can use shorter prefix indexes and still get almost all the value */
    $reindexWithAntelopeConstraint = [
      "postmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_id)",
        "post_id"     => "ADD KEY post_id (post_id, meta_key(32), meta_value(32), meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), meta_id)",
      ],

      "usermeta"    => [
        "umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (user_id, umeta_id)",
        "user_id"     => "ADD KEY user_id (user_id, meta_key(32), meta_value(32), umeta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), umeta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), umeta_id)",
      ],
      "termmeta"    => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_id)",
        "term_id"     => "ADD KEY term_id (term_id, meta_key(32), meta_value(32), meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), meta_id)",
      ],
      "posts"       => [
        "PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
        "post_name"        => "ADD KEY post_name (post_name(32))",
        "post_parent"      => "ADD KEY post_parent (post_parent, post_type, post_status)",
        "type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author)",
        "post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date)",
      ],
      'commentmeta' => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (comment_id, meta_id)",
        "comment_id"  => "ADD KEY comment_id (comment_id, meta_key(32))",
        "meta_key"    => "ADD KEY meta_key (meta_key(32), meta_value(32))",
        "meta_value"  => "ADD KEY meta_value (meta_value(32), meta_key(32))",
      ],
      "users"       => [
        "PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
        "user_login_key" => "ADD KEY user_login_key (user_login)",
        "user_nicename"  => "ADD KEY user_nicename (user_nicename)",
        "user_email"     => "ADD KEY user_email (user_email)",
        "display_name"   => "ADD KEY display_name (display_name(32))",
      ],

    ];
    switch ( $unconstrained ) {
      case 1: /* barracuda */
        $reindexes = array_merge( $reindexWithoutConstraint, $reindexAnyway );
        break;
      case 0: /* antelope */
        $reindexes = array_merge( $reindexWithAntelopeConstraint, $reindexAnyway );
        break;
      default:
        $reindexes = $reindexAnyway;
        break;
    }

    return $reindexes;
  }

  /**
   * @param int unconstrained  1 means barracuda, 0 antelope
   *
   * @return array
   */
  static function getHighPerformanceIndexes1_3( $unconstrained ) {

    /* When changing a PRIMARY KEY,
     * for example to a compound clustered index
     * you also need to add a UNIQUE KEY
     * to hold the autoincrementing column.
     * Put that UNIQUE KEY first in the list of keys to add.
     * That makes sure we always have some sort of
     * no-duplicates constraint on the autoincrementing ID.
     */


    /* these are the indexes not dependent on Antelope or Barracuda */
    $reindexAnyway1_3 = [
      "options" => [
        "option_id"   => "ADD UNIQUE KEY option_id (option_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
        "option_name" => "ADD UNIQUE KEY option_name (option_name)",
      ],
      "posts"   => [
        "PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
        "post_name"        => "ADD KEY post_name (post_name(191))",
        "post_parent"      => "ADD KEY post_parent (post_parent)",
        "type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
        "post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date, ID)",
      ],

      "comments"    => [
        "PRIMARY KEY"                  => "ADD PRIMARY KEY (comment_ID)",
        "comment_post_ID"              => "ADD KEY comment_post_ID (comment_post_ID)",
        "comment_approved_date_gmt"    => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
        "comment_date_gmt"             => "ADD KEY comment_date_gmt (comment_date_gmt)",
        "comment_parent"               => "ADD KEY comment_parent (comment_parent)",
        "comment_author_email"         => "ADD KEY comment_author_email (comment_author_email(10))",
        "comment_post_parent_approved" => "ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_ID)",
      ],
      /* the target indexes for previous version are the same as the standard indexes here,
       * because we did not reindex these tables at all in the previous version. */
      'commentmeta' => ImfsGetIndexes::$imfsStandardIndexes['commentmeta'],
      "users"       => ImfsGetIndexes::$imfsStandardIndexes['users'],
    ];

    /* These are the Barracuda-dependent (unprefixed) indexes
     * Notice that the indexed columns following prefix-indexed columns
     * [for example post_id in (meta_key, meta_value(32), post_id) ]
     * are intended for covering-index use, as if in an INCLUDE clause. */
    $reindexWithoutConstraint1_3 = [
      "postmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_key, meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, post_id)",
      ],
      "termmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key, meta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, term_id)",
      ],
      "usermeta" => [
        "umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
        "meta_key"    => "ADD KEY meta_key (meta_key, user_id)",
      ],
    ];

    /* these are the Antelope-dependent (prefixed) indexes */
    /* we can use shorter prefix indexes and still get almost all the value */
    $reindexWithAntelopeConstraint1_3 = [
      "postmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_id)",
        "post_id"     => "ADD KEY post_id (post_id, meta_key(191))",
        "meta_key"    => "ADD KEY meta_key (meta_key(191), post_id)",
      ],
      "termmeta" => [
        "meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_id)",
        "term_id"     => "ADD KEY term_id (term_id, meta_key(191))",
        "meta_key"    => "ADD KEY meta_key (meta_key(191), term_id)",
      ],
      "usermeta" => [
        "umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
        "PRIMARY KEY" => "ADD PRIMARY KEY (user_id, umeta_id)",
        "user_id"     => "ADD KEY user_id (user_id, meta_key(191))",
        "meta_key"    => "ADD KEY meta_key (meta_key(191), user_id)",
      ],
    ];
    switch ( $unconstrained ) {
      case 1: /* barracuda */
        $reindexes = array_merge( $reindexWithoutConstraint1_3, $reindexAnyway1_3 );
        break;
      case 0: /* antelope */
        $reindexes = array_merge( $reindexWithAntelopeConstraint1_3, $reindexAnyway1_3 );
        break;
      default:
        $reindexes = $reindexAnyway1_3;
        break;
    }

    return $reindexes;
  }
}

ImfsGetIndexes::$imfsStandardIndexes = [
  'postmeta'    => [
    "PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
    "post_id"     => "ADD KEY post_id (post_id)",
    "meta_key"    => "ADD KEY meta_key (meta_key(191))",
  ],
  'usermeta'    => [
    "PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
    "user_id"     => "ADD KEY user_id (user_id)",
    "meta_key"    => "ADD KEY meta_key (meta_key(191))",
  ],
  'termmeta'    => [
    "PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
    "term_id"     => "ADD KEY term_id (term_id)",
    "meta_key"    => "ADD KEY meta_key (meta_key(191))",
  ],
  'options'     => [
    "PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
    "option_name" => "ADD UNIQUE KEY option_name (option_name)",
    "autoload"    => "ADD KEY autoload (autoload)",
  ],
  'posts'       => [
    "PRIMARY KEY"      => "ADD PRIMARY KEY (ID)",
    "post_name"        => "ADD KEY post_name (post_name(191))",
    "post_parent"      => "ADD KEY post_parent (post_parent)",
    "type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
    "post_author"      => "ADD KEY post_author (post_author)",
  ],
  'comments'    => [
    "PRIMARY KEY"               => "ADD PRIMARY KEY (comment_ID)",
    "comment_post_ID"           => "ADD KEY comment_post_ID (comment_post_ID)",
    "comment_approved_date_gmt" => "ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)",
    "comment_date_gmt"          => "ADD KEY comment_date_gmt (comment_date_gmt)",
    "comment_parent"            => "ADD KEY comment_parent (comment_parent)",
    "comment_author_email"      => "ADD KEY comment_author_email (comment_author_email(10))",
  ],
  'commentmeta' => [
    "PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
    "comment_id"  => "ADD KEY comment_id (comment_id)",
    "meta_key"    => "ADD KEY meta_key (meta_key(191))",
  ],
  "users"       => [
    "PRIMARY KEY"    => "ADD PRIMARY KEY (ID)",
    "user_login_key" => "ADD KEY user_login_key (user_login)",
    "user_nicename"  => "ADD KEY user_nicename (user_nicename)",
    "user_email"     => "ADD KEY user_email (user_email)",
  ],
];

