-- convert 1.3.3 Barracuda indexes to original.
SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');
ALTER TABLE wp_comments
            DROP KEY comment_post_parent_approved,
            ENGINE=MyISAM ROW_FORMAT=COMPACT;

ALTER TABLE wp_options 
            DROP PRIMARY KEY, ADD PRIMARY KEY (option_id), 
            ADD KEY autoload (autoload),
            DROP KEY option_id,
            ENGINE=MYISAM, ROW_FORMAT=COMPACT;

ALTER TABLE wp_posts 
            DROP KEY type_status_date, ADD KEY type_status_date (post_type, post_status, post_date, ID),
            DROP KEY post_author, ADD KEY post_author (post_author),
            ENGINE=MYISAM, ROW_FORMAT=COMPACT;

ALTER TABLE wp_postmeta 
            DROP PRIMARY KEY, ADD PRIMARY KEY (meta_id), 
            DROP KEY meta_key, ADD KEY meta_key( meta_key(191)),
            DROP KEY meta_id,
            ADD KEY post_id(post_id),
            ENGINE=MYISAM, ROW_FORMAT=COMPACT;

ALTER TABLE wp_termmeta
            DROP PRIMARY KEY, ADD PRIMARY KEY (meta_id), 
            DROP KEY meta_key, ADD KEY meta_key( meta_key(191)),
            DROP KEY meta_id,
            ADD KEY term_id (term_id),
            ENGINE=MYISAM, ROW_FORMAT=COMPACT;

ALTER TABLE wp_usermeta
            DROP PRIMARY KEY, ADD PRIMARY KEY (umeta_id), 
            DROP KEY meta_key, ADD KEY meta_key( meta_key(191)),
            DROP KEY umeta_id,
            ADD KEY user_id (user_id),
            ENGINE=MYISAM, ROW_FORMAT=COMPACT;

