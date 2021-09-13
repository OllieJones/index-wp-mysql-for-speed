=== Index WP MySQL For Speed ===
Contributors: OllieJones
Tags: database, optimize, index, key, mysql, wp-cli
Requires at least: 5.2
Tested up to: 5.8.1
Requires PHP: 5.5
Stable tag: 1.2.2
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: http://mysql.rjweb.org/
Plugin URI: https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/
Text Domain: index-wp-mysql-for-speed
Domain Path: /languages

Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.

== Description ==

Use this plugin with the Index MySQL Tool under the Tools menu. Or, give the shell command _wp help index-mysql_ to learn how to use it with WP-CLI.

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL](https://www.mysql.com/) relational database management system. (Some hosting providers and servers use the [MariaDB](https://mariadb.org/) fork of WordPress; it works exactly the same as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task.

That is not the task of this plugin.

This plugin adds database [keys](https://dev.mysql.com/doc/refman/8.0/en/mysql-indexes.html) (also called indexes) to your MySQL tables to make it easier for WordPress to find the information it needs. All relational database management systems store your information in long-lived _tables_. For example, WordPress stores your posts and other content in a table called _wp_posts_, and custom post fields in another table called _wp_postmeta_.  A successful site can have thousands of posts and hundreds of thousands of custom post fields. MySQL has two jobs:

1. Keep all that data organized.
2. Find the data it needs quickly.

To do its second job, MySQL uses database keys. Each table has one or more keys. For example, `wp_posts` has a key to let it quickly find posts when you know the author. Without its _post_author_ key MySQL would have to scan the entire table looking for posts matching the author you want. We all know what that looks like: slow.

In a new WordPress site with a couple of users and a dozen posts, the keys don't matter very much. As the site grows the keys start to matter, a lot. Database management systems are designed to have their keys updated, adjusted, and tweaked as their tables grow. They're designed to allow the keys to evolve without changing the content of the underlying tables. In organizations with large databases adding, dropping, or altering keys doesn't change the underlying data. It is a routine maintenance task in many data centers. If changing keys caused databases to lose data, the MySQL and MariaDB developers would hear howling not just from you and me, but from many heavyweight users. (You should still back up your WordPress instance of course.)

Better keys allow WordPress's code to run faster _without any code changes_.  Code is poetry, data is treasure, and database keys are grease that makes code and data work together smoothly.

This plugin updates those keys. It works on six tables found in all WordPress installations.

* wp_options
* wp_posts
* wp_postmeta
* wp_comments
* wp_usermeta
* wp_termmeta

Experience with large sites shows that many MySQL slowdowns can be improved by better keys. You only need run this plugin once to get its benefits.

== Frequently Asked Questions ==

= Should I back up my site before using this? =

Yes. You already knew that.

= It takes a long time to display the plugin's settings page. Why? =

This plugin examines your MySQL database as it renders its settings page. On shared hosts that can take a while. Please be patient, and please avoid clicking the Index MySQL link more than once.

= I use a nonstandard database table prefix. Will this work ? =

Yes. Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

Yes.

= Which versions of MySQL and MariaDB does this support? =

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB version 5.5 and above.

= What database Storage Engine does this support? =

InnoDB only. If your tables use MyISAM (the older storage engine) this plugin offers to upgrade them for you.

= Which versions of MySQL and MariaDB work best? =

If at all possible upgrade to Version 8 or later of MySQL.  For MariaDB upgrade to Version 10.3 or later. The MySQL and MariaDB developers have made many performance improvements over the past few years. They have the mission of making things better for WordPress site operators: we are by far their biggest user base.

Avoid Versions 5.5 of both MySQL and MariaDB if you can. They use the older Antelope version of InnnoDB. It has a limitation on index lengths that requires WordPress to use [prefix keys](https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix). Those have reduced performance.

If you have the later _Barracuda_ version of InnoDB, this plugin uses its capability to build efficient [covering](https://dev.mysql.com/doc/refman/8.0/en/glossary.html#glos_covering_index) keys. If you have the older Antelope version it still builds keys, but they are less efficient. It must use prefix keys on that version. Those cannot be covering keys.

= Does this plugin generate any overhead when my site is busy? =

No. Some plugins' code runs when your visitors view pages. All this plugin's work happens from the WordPress Dashboard or WP-CLI. It sets up the keys in your database and then gets out of the way. You can even deactivate and delete the plugin once you've run it.

= What happens when I deactivate this plugin? =

Its high-performance keys remain in place. You can always re-add it and reactivate it if you need to revert your keys to the WordPress standard.

= Does this work on my multisite (network) WordPress instance?

Yes. On multisite instances, you must activate the plugin from the Network Admin dashboard. The *Index MySQL* tool is available for use by the administrator on each site.

= Can I upgrade my WordPress instance to multisite after using this plugin?

**No**. if you upgrade your WordPress instance to multisite (a network) following [these instructions](https://wordpress.org/support/article/create-a-network/), **revert your high-performance keys first.** After you complete your upgrade you can add back the high-performance keys.

= How can I learn more about this business of database keys? =

It's a large topic. Some people (often called Database Administrators--DBAs) make entire careers out of this kind of work. Where can you look to get started?

* Marcus Winand's great book [Use The Index, Luke](https://use-the-index-luke.com).
* Rick James, a contributor to this plugin, has a good article [Building the best INDEX for a given SELECT](http://mysql.rjweb.org/doc.php/index_cookbook_mysql).
* StackOverflow's [Why are references to wp_postmeta so slow?](https://stackoverflow.com/questions/43859351/why-are-references-to-wp-postmeta-so-slow) is useful.
* [wordpress.stackexchange.com](https://wordpress.stackexchange.com)'s article [Simple SQL query on wp_postmeta very slow](https://wordpress.stackexchange.com/questions/248207/simple-sql-query-on-wp-postmeta-very-slow).
* Good [advice about the wp_options table](https://10up.com/blog/2017/wp-options-table/) from web agency [10up.com](https://10up.com/). This plugin puts a key on that table to optimize options loading.
* The [proposal](https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/) for this plugin.

== Changelog ==
= 0.1.2 =
First publication.

= 0.1.4 =
Minor updates.

= 0.9.1 =
More complete diagnostic information upload, minor usability and documentation improvements.

= 1.0.1 =
Works for multisite, add more user choices

= 1.0.2 =
Do not upgrade the storage engine for views or for non-WordPress tables.

= 1.2.0 =
Add WP-CLI support. Add selective storage-enging upgrades. Add the Reset option to put back WordPress standard keys on tables with unrecognized combinations of keys.

= 1.2.1 =
Fix require_once defect exposed by wp-cli workflow.

= 1.2.2 =
Fix engine-upgrade defect, stop counting rows because it's too slow..

== Upgrade Notice ==
This version supports [WP-CLI](https://make.wordpress.org/cli/handbook/); you can run it from a shell command line. It supports a way to reset your tables' keys to the WordPress standard
if it doesn't recognize the existing keys. If you have MyISAM tables, you can choose which tables to upgrade to InnoDB instead of upgrading them all.

== Screenshots ==

1. The settings page.

2. WP-CLI terminal.