-- add trash indexes to test nonstandard revert / upgrade
SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');
ALTER TABLE wp_comments
            ADD KEY testing_trash (comment_approved, comment_post_ID);

ALTER TABLE wp_options
            ADD KEY testing_trash (option_value(37),option_id);

ALTER TABLE wp_posts
            ADD KEY testing_trash (post_status, post_type, post_date_gmt, ID);

ALTER TABLE wp_postmeta
            ADD KEY testing_trash (meta_key(7), meta_value(3), post_id);

ALTER TABLE wp_termmeta
            ADD KEY testing_trash (meta_key(7), meta_value(3), term_id);

ALTER TABLE wp_usermeta
            ADD KEY testing_trash (meta_key(7), meta_value(3), user_id);

