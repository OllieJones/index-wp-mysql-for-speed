=== Index WP MySQL For Speed ===
Contributors: OllieJones
Tags: database, index, key, mysql, wp-cli
Requires at least: 5.2
Tested up to: 5.9
Requires PHP: 7.2
Stable tag: 1.4.1
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: http://mysql.rjweb.org/
Plugin URI: https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/
Text Domain: index-wp-mysql-for-speed
Domain Path: /languages

Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.

== Description ==

<h4>How do I use this plugin?</h4>

Use this plugin with the Index MySQL Tool under the Tools menu. Or, give the shell command _wp help index-mysql_ to learn how to use it with [WP-CLI](https://wp-cli.org/).

<h4>What does it do for my site?</h4>

This plugin works to make your MySQL database work more efficiently by adding high-performance keys to its tables. It also monitors your site's use of your MySQL database to detect which database operations are slowest. It is most useful for large sites: sites with many users, posts, pages, and / or products.

<h4>What is this all about?</h4>

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL](https://www.mysql.com/) relational database management system. (Many hosting providers and servers use the [MariaDB](https://mariadb.org/) fork of the MySQL software; it works exactly the same as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task.

That is not the task of this plugin.

This plugin adds database [keys](https://dev.mysql.com/doc/refman/8.0/en/mysql-indexes.html) (also called indexes) to your MySQL tables to make it easier for WordPress to find the information it needs. All relational database management systems store your information in long-lived _tables_. For example, WordPress stores your posts and other content in a table called _wp_posts_, and custom post fields in another table called _wp_postmeta_.  A successful site can have thousands of posts and hundreds of thousands of custom post fields. MySQL has two jobs:

1. Keep all that data organized.
2. Find the data it needs quickly.

To do its second job, MySQL uses database keys. Each table has one or more keys. For example, `wp_posts` has a key to let it quickly find posts when you know the author. Without its _post_author_ key MySQL would have to scan every one of your posts looking for matches to the author you want. Our users know what that looks like: slow. With the key, MySQL can jump right to the matching posts.

In a new WordPress site with a couple of users and a dozen posts, the keys don't matter very much. As the site grows the keys start to matter, a lot. Database management systems are designed to have their keys updated, adjusted, and tweaked as their tables grow. They're designed to allow the keys to evolve without changing the content of the underlying tables. In organizations with large databases adding, dropping, or altering keys doesn't change the underlying data. It is a routine maintenance task in many data centers. If changing keys caused databases to lose data, the MySQL and MariaDB developers would hear howling not just from you and me, but from many heavyweight users. (You should still back up your WordPress instance of course.)

Better keys allow WordPress's code to run faster _without any code changes_.  Experience with large sites shows that many MySQL slowdowns can be improved by better keys. Code is poetry, data is treasure, and database keys are grease that makes code and data work together smoothly.

<h4>Which tables does the plugin add keys to?</h4>

This plugin adds and updates keys in these WordPress tables.

* wp_options
* wp_posts
* wp_postmeta
* wp_users
* wp_usermeta
* wp_comments
* wp_commentmeta
* wp_termmeta

You only need run this plugin once to get its benefits.

<h4>How can I monitor my database's operation?</h4>

On the Index MySQL page (from your Tools menu on your dashboard), you will find the "Monitor Database Operations" tab. Use it to request monitoring for a number of minutes you choose.

You can monitor

* either the site (your user-visible pages) or the dashboard, or both.
* all pageviews, or a random sample. (Random samples are useful on very busy sites to reduce monitoring overhead.)

Once you have gathered monitoring information, you can view the queries, and sort them by how long they take. Or you can save the monitor information to a file and show it to somebody who knows about database operations.

It's a good idea to monitor for a five-minute interval at a time of day when your site is busy. Once you've completed a monitor, you can examine it to determine which database operations are slowing you down the most.

= Credits =
* Michael Uno for Admin Page Framework.
* Marco Cesarato for LiteSQLParser.
* Allan Jardine for Datatables.net.
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

== Frequently Asked Questions ==

= Should I back up my site before using this? =

**Yes.** You already knew that.

= I use a nonstandard database table prefix. Will this work ? =

**Yes.** Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

= Which versions of MySQL and MariaDB does this support? =

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB version 5.5 and above.

= What database Storage Engine does this support? =

**InnoDB only.** If your tables use MyISAM (the older storage engine) or the older COMPACT row format, this plugin offers to upgrade them for you.

= Which versions of MySQL and MariaDB work best? =

If at all possible upgrade to Version 8 or later of MySQL.  For MariaDB upgrade to Version 10.3 or later. The MySQL and MariaDB developers have made many performance improvements over the past few years. They have the mission of making things better for WordPress site operators: we are by far their biggest user base. So, we have a lot to gain by using their latest versions.

Avoid Versions 5.5 of both MySQL and MariaDB if you can. And, avoid MariaDB 10.1. They use the older [Antelope](https://dev.mysql.com/doc/refman/5.7/en/glossary.html#glos_antelope) version of InnoDB. It has a limitation on index lengths that requires WordPress to use [prefix keys](https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix). Those have reduced performance.

If your server uses the later [Barracuda](https://dev.mysql.com/doc/refman/5.7/en/glossary.html#glos_barracuda) version of InnoDB, this plugin uses its capability to build efficient [covering](https://dev.mysql.com/doc/refman/8.0/en/glossary.html#glos_covering_index) keys. If you have the older Antelope version it still builds keys, but they are less efficient. The prefix keys it uses cannot be covering keys.

= Is this plugin compatible with WordPress Object Cache plugins for redis and memcached? =

**Yes.** This plugin only affects WordPress's queries to the database. The Object Cache plugins reduce the number of those queries, and so reduce your database's workload. Still, this plugin helps your performance.

If you have trouble with your Object Cache plugin, try flushing (removing all entries from) the cache immediately after you activate this plugin.

= Does this plugin generate any overhead when my site is busy? =

**No,** not unless you are using it to monitor database operations, and that is for limited periods of time.

Some plugins' code runs whenever your visitors view pages. All this plugin's rekeying work happens from the WordPress Dashboard or WP-CLI. It sets up the keys in your database and then gets out of the way. You can even deactivate and delete the plugin once you've run it.

= What happens when I deactivate this plugin? =

Its high-performance keys remain in place. You can always re-add it and reactivate the plugin if you need to revert your keys to the WordPress standard.

Your saved monitors are removed when you deactivate the plugin.

= Does this work on my multisite (network) WordPress instance?

**Yes.** On multisite instances, you must activate the plugin from the Network Admin dashboard. The *Index MySQL* tool is available for use by the administrator on each site.

= Can I upgrade my WordPress instance to multisite after using this plugin?

**No**. if you upgrade your WordPress instance to multisite (a network) following [these instructions](https://wordpress.org/support/article/create-a-network/), **revert your high-performance keys first.** After you complete your upgrade you can add back the high-performance keys.

= Can I restore a backup or duplicate to another server after using this plugin?

Yes. But if you restore it to a server with an older version of MySQL (looking at you, GoDaddy) you should revert your keys to the WordPress standard before creating your backup or duplicate.

= Will this plugin fix misconfigurations in my MySQL or MariaDB server? =

**No.** This plugin only upgrades your tables to InnoDB if necessary, and updates their keys.  It does not handle any other configuration issues.

Database servers have many configuration settings. Occasionally some of them are wrong and the database server software performs poorly. The [Percona Toolkit](https://www.percona.com/doc/percona-toolkit/LATEST/index.html) offers a utility called [pt-variable-advisor](https://www.percona.com/doc/percona-toolkit/LATEST/pt-variable-advisor.html). If you have command-line access to your server you can run it. It will make suggestions for better settings.

= I get a "Temporary file write failure" error. What do I do about this?

Your database server machine has a file system partition for [temporary files](https://dev.mysql.com/doc/refman/8.0/en/temporary-files.html). If that partition is not big enough to hold a copy of the largest of your tables, the rekeying operation won't work.  This is most often the case on Linux or FreeBSD file systems with a separate `/tmp` partition. You can ask the person who operates your database server to change the startup option from `tmpdir = /tmp` to `tmpdir = /var/tmp` to resolve this problem.

= How can I learn more about this business of database keys? =

It's a large topic. Some people (often called Database Administrators--DBAs) make entire careers out of this kind of work. Where can you look to get started?

* Marcus Winand's great book [Use The Index, Luke](https://use-the-index-luke.com).
* Rick James, a contributor to this plugin, has a good article [Building the best INDEX for a given SELECT](http://mysql.rjweb.org/doc.php/index_cookbook_mysql).
* StackOverflow's [Why are references to wp_postmeta so slow?](https://stackoverflow.com/questions/43859351/why-are-references-to-wp-postmeta-so-slow) is useful.
* [wordpress.stackexchange.com](https://wordpress.stackexchange.com)'s article [Simple SQL query on wp_postmeta very slow](https://wordpress.stackexchange.com/questions/248207/simple-sql-query-on-wp-postmeta-very-slow).
* Good [advice about the wp_options table](https://10up.com/blog/2017/wp-options-table/) from web agency [10up.com](https://10up.com/). This plugin puts a key on that table to optimize options loading.
* The [proposal](https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/) for this plugin.

== Changelog ==
= 0.9.1 =
First release.

= 1.0.1 =
Works for multisite, add more user choices

= 1.0.2 =
Do not upgrade the storage engine for views or for non-WordPress tables.

= 1.2.0 =
Add WP-CLI support. Add selective storage-enging upgrades. Add the Reset option to put back WordPress standard keys on tables with unrecognized combinations of keys.

= 1.2.1 =
Fix require_once defect exposed by wp-cli workflow.

= 1.2.2 =
Fix engine-upgrade defect, stop counting rows because it's too slow.

= 1.2.3 =
Fix cli defect.

= 1.3.3 =
When upgrading tables, change ROW_FORMAT to DYNAMIC as well as ENGINE to InnoDB. Add monitors.

= 1.3.4 =
Support MariaDB 10.1, make indexes work a little better, miscellaneous bugfixes.

= 1.4.1 =
* 5.9 compatibility
* Rekeys tables in one go: allows the plugin to work when `sql_require_primary_key=ON` (typically at managed service providers).
* Handles upgrades to high-performance keys
* Adds prefix keys to meta_value columns to allow searching by value.
* Adds display_name key to wp_users, and keys to wp_commentmeta.
* Checks `$wp_db_version` number to ensure schema compatibility.

== Upgrade Notice ==
5.9 support, keys on user display names and metadata values, better support for managed service providers and larger WooCommerce sites, bug fixes.

== Screenshots ==

01 Adding high-performance keys.

02 Monitoring database operations.

03 Viewing a database monitor.

04 Using WP CLI.