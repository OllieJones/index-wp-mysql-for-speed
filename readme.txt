=== Index WP MySQL For Speed ===
Contributors: OllieJones, rjasdfiii
Tags: database, index, key, performance, mysql, wp-cli
Requires at least: 5.2
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: 1.4.6
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://github.com/OllieJones
Plugin URI: https://plumislandmedia.net/index-wp-mysql-for-speed/
Github Plugin URI: https://github.com/OllieJones/index-wp-mysql-for-speed
Primary Branch: main
Text Domain: index-wp-mysql-for-speed
Domain Path: /languages

Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.

== Description ==

<h4>How do I use this plugin?</h4>

After you install and activate this plugin, visit the Index MySQL Tool under the Tools menu. From there you can press the *Add Keys Now* button. If you have large tables, use it with [WP-CLI](https://wp-cli.org/) instead to avoid timeouts. See the WP-CLI section to learn more.


<h4>What does it do for my site?</h4>

This plugin works to make your MySQL database work more efficiently by adding high-performance keys to the tables you choose. On request it monitors your site's use of your MySQL database to detect which database operations are slowest. It is most useful for large sites: sites with many users, posts, pages, and / or products.

You can use it to restore WordPress's default keys if need be.

<h4>What is this all about?</h4>

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL](https://www.mysql.com/) relational database management system. (Many hosting providers and servers use the [MariaDB](https://mariadb.org/) fork of the MySQL software; it works exactly the same way as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. And, bulk imports can take absurd amounts of time. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task. That is not the task of this plugin. You can, if your hosting provider supports it, install and use a [Persistent Object Cache plugin](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-cache-plugins) to reduce traffic to your database. That is not the task of this plugin either.

This plugin adds database [keys](https://dev.mysql.com/doc/refman/8.0/en/mysql-indexes.html) (also called indexes) to your MySQL tables to make it easier for WordPress to find the information it needs. All relational database management systems store your information in long-lived _tables_. For example, WordPress stores your posts and other content in a table called _wp_posts_, and custom post fields in another table called _wp_postmeta_.  A successful site can have thousands of posts and hundreds of thousands of custom post fields. MySQL has two jobs:

1. Keep all that data organized.
2. Find the data it needs quickly.

To do its second job, MySQL uses database keys. Each table has one or more keys. For example, `wp_posts` has a key to let it quickly find posts when you know the author. Without its _post_author_ key MySQL would have to scan every one of your posts looking for matches to the author you want. Our users know what that looks like: slow. With the key, MySQL can jump right to the matching posts.

In a new WordPress site with a couple of users and a dozen posts, the keys don't matter very much. As the site grows the keys start to matter, a lot. Database management systems are designed to have their keys updated, adjusted, and tweaked as their tables grow. They're designed to allow the keys to evolve without changing the content of the underlying tables. In organizations with large databases adding, dropping, or altering keys doesn't change the underlying data. It is a routine maintenance task in many data centers. If changing keys caused databases to lose data, the MySQL and MariaDB developers would hear howling not just from you and me, but from many heavyweight users. (You should still back up your WordPress instance of course.)

Better keys allow WordPress's code to run faster _without any code changes_.  Experience with large sites shows that many MySQL slowdowns can be improved by better keys. Code is poetry, data is treasure, and database keys are grease that makes code and data work together smoothly.

<h4>Which tables does the plugin add keys to?</h4>

This plugin adds and updates keys in these WordPress tables.

* wp_comments
* wp_commentmeta
* wp_posts
* wp_postmeta
* wp_termmeta
* wp_users
* wp_usermeta
* wp_options

You only need run this plugin once to get its benefits.

<h4>How can I monitor my database's operation?</h4>

On the Index MySQL page (from your Tools menu on your dashboard), you will find the "Monitor Database Operations" tab. Use it to request monitoring for a number of minutes you choose.

You can monitor

* either the site (your user-visible pages) or the dashboard, or both.
* all pageviews, or a random sample. (Random samples are useful on very busy sites to reduce monitoring overhead.)

Once you have gathered monitoring information, you can view the captured queries, and sort them by how long they take. Or you can save the monitor information to a file and show it to somebody who knows about database operations. Or you can upload the monitor to the plugin's servers so the authors can look at it.

It's a good idea to monitor for a five-minute interval at a time of day when your site is busy. Once you've completed a monitor, you can examine it to determine which database operations are slowing you down the most.

Please consider uploading your saved monitors to the plugin's servers. It's how we learn from your experience to keep improving. Push the Upload button on the monitor's tab.

<h4>WP-CLI command line operation</h4>

This plugin supports [WP-CLI](https://wp-cli.org/).  When your tables are large this is the best way to add the high-performance keys: it doesn't time out.

Give the command `wp help index-mysql` for details. A few examples:

* `wp index-mysql status` shows the current status of high-performance keys.
* `wp index-mysql enable --all` adds the high-performance keys to all tables that don't have them.
* `wp index-mysql enable wp_postmeta` adds the high-performance keys to the postmeta table.
* `wp index-mysql disable --all` removes the high-performance keys from all tables that have them, restore WordPress's default keys.
* `wp index-mysql enable --all --dryrun` writes out the SQL statements necessary to add the high-performance keys to all tables, but does not run them.
* `wp index-mysql enable --all --dryrun | wp db query` writes out the SQL statements and pipes them to wp db to run them.

Note: avoid saving the --dryrun output statements to run later. The plugin generates them to match the current state of your tables.

<h4>What's new in version 1.4?</h4>

Since the first release, our users have told us about several more opportunities to speed up their WooCommerce and core WordPress operations. We've added keys to the `meta` tables to help with searching for content, and to the `users` table to look people up by their display names. And, you can now upload saved Monitors so we can see your slowest queries. We'll use that information to improve future versions. Thanks, dear users!

WordPress version updates attempt to restore some of WordPress's default keys. This plugin prompts you to add the high-performance keys after updates.

We have added the --dryrun switch to the WP-CLI interface for those who want to see the SQL statements we use.

= Credits =
* Michael Uno for Admin Page Framework.
* Marco Cesarato for LiteSQLParser.
* Allan Jardine for Datatables.net.
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

== Frequently Asked Questions ==

= Should I back up my site before using this? =

**Yes.** You already knew that.

= I don't see any changes to my database speed. Why not? =

* On a modestly sized site (with a few users and a few hundred posts) your database may be fast enough without these keys. The speed improvements are most noticeable on larger sites with many posts and products.
* Just installing and activating the plugin is **not enough to make it work**. Don't forget to visit the Index MySQL Tool under the Tools menu. From there you can press the **Add Keys Now** button.

= I use a nonstandard database table prefix. Will this work ? =

**Yes.** Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

= Which versions of MySQL and MariaDB does this support? =

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB versions 5.5 and above.

= What database Storage Engine does this support? =

**InnoDB only.** If your tables use MyISAM (the older storage engine) or the older COMPACT row format, this plugin offers to upgrade them for you.

= What tables and keys does the plugin change? =

[Please read this](https://www.plumislandmedia.net/index-wp-mysql-for-speed/tables_and_keys/).

= Is this safe? Can I add high-performance keys and revert back to WordPress standard keys safely?

Yes. it is safe to add keys and revert them. Changing keys is a routine database-maintenance operation.

As you know you should still keep backups of your site: other things can cause data loss.

= My site has thousands of registered users. My Users, Posts, and Pages panels in my dashboard are still load slowly even with this plugin.

We have another plugin to handle lots of users, [Index WP Users For Speed](https://wordpress.org/plugins/index-wp-users-for-speed/). Due to the way WordPress handles users, just changing database keys is not enough to solve those performance problems.

= How do I get an answer to another question? ==

Please see more questions and answers [here](https://plumislandmedia.net/index-wp-mysql-for-speed/faq/).

== Changelog ==


= 1.4.4 =
* (No changes to keys.)
* Add support for internationalization. Speed up rendering of dashboard panels.
* In MySQL 5.5, avoid using EXPLAIN on anything except SELECT queries.
* Don't remove settings and monitors on deactivate, only on uninstall.

= 1.4.5 =
* (No changes to keys.)
* Ignore FULLTEXT indexes and indexes from Contextual Related Posts plugin.
* The new --dryrun switch on wp-cli now writes out ALTER TABLE data definition language instead of running it.
   Now can do `wp index-mysql enable --all --dryrun | wp db query` to run the DML.
* Fix some metadata-upload issues.
* Fix a bug when deleting a monitor.
* Update the $wp_db_monitor version to 53496.

= 1.4.6 =
(no changes to keys)
Fix a bug when looking at the stoplist for index names.

== Upgrade Notice ==

We've added the --dryrun option to the wp-cli interface, and corrected some bugs. Notice that no keys change when upgrading from any version 1.4.x to this version 1.4.5.

== Screenshots ==

1. Use Tools > Index MySQL to view the Dashboard panel.
2. Choose tables and add High-Performance Keys.
3. Start Monitoring Database Operations, and see saved monitors.
4. View a saved monitor to see slow database queries.
5. About the plugin.
6. Use WP CLI to run the plugin's operations.
