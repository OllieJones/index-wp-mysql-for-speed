-- convert multiple tables to MyISAM can compat
SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');
ALTER TABLE wp_comments
    ENGINE =MyISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_commentmeta
    ENGINE =MyISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_options
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_posts
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_postmeta
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_termmeta
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_usermeta
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_terms
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_term_relationships
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_term_taxonomy
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
ALTER TABLE wp_links
    ENGINE =MYISAM,
    ROW_FORMAT = COMPACT;
