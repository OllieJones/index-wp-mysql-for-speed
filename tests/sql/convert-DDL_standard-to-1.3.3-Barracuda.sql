-- convert original indexes to 1.3.3 Barracuda
SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');
ALTER TABLE wp_comments
            ADD KEY comment_post_parent_approved (comment_post_ID ,comment_parent, comment_approved, comment_ID),
            ENGINE=INNODB ROW_FORMAT=DYNAMIC;

ALTER TABLE wp_options 
            DROP PRIMARY KEY, ADD PRIMARY KEY (autoload, option_id), 
            ADD UNIQUE KEY option_id (option_id),
            DROP KEY autoload,
            ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;

ALTER TABLE wp_posts 
            DROP KEY type_status_date, ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID),
            DROP KEY post_author, ADD KEY post_author (post_author, post_type, post_status, post_date, ID),
            ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;

ALTER TABLE wp_postmeta 
            ADD UNIQUE KEY meta_id (meta_id),
            DROP PRIMARY KEY, ADD PRIMARY KEY (post_id, meta_key, meta_id), 
            DROP KEY meta_key, ADD KEY meta_key(meta_key, post_id),
            DROP KEY post_id,
            ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;
            
ALTER TABLE wp_termmeta 
            ADD UNIQUE KEY meta_id (meta_id),
            DROP PRIMARY KEY, ADD PRIMARY KEY (term_id, meta_key, meta_id), 
            DROP KEY meta_key, ADD KEY meta_key (meta_key, term_id),
            DROP KEY term_id,
            ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;
            
ALTER TABLE wp_usermeta 
            ADD UNIQUE KEY umeta_id (umeta_id),
            DROP PRIMARY KEY, ADD PRIMARY KEY (user_id, meta_key, umeta_id), 
            DROP KEY meta_key, ADD KEY meta_key (meta_key, user_id),
            DROP KEY user_id,
            ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;
