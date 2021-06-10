=== Index WP MySQL For Speed ===
Contributors: OllieJones
Tags: database, optimize, index, key, mysql
Requires at least: 5.2
Tested up to: 5.7.2
Requires PHP: 5.5
Stable tag: 0.0.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: index-wp-mysql-for-speed
Domain Path: /languages

Add high-performance indexes (keys) to your WordPress installation MySQL database tables.

== Description ==

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL][https://www.mysql.com/] relational database management system. (Some hosting providers and servers use the [MariaDB][https://mariadb.org/] fork of WordPress; it works exactly the same as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task.

That is not the task of this plugin.

This plugin adds database [keys][https://dev.mysql.com/doc/refman/8.0/en/mysql-indexes.html] (also called indexes) to your MySQL tables to make it easier for WordPress to find the information it needs. All relational database management systems store your information in long-lived _tables_. For example, WordPress stores your posts and other content in a table called _wp_posts_, and custom post fields in another table called _wp_postmeta_.  A successful site can have thousands of posts and hundreds of thousands of custom post fields. MySQL has two jobs.

1. Keep all that data organized.
2. Find the data it needs quickly.

To do its second job, MySQL uses database keys. Each table has one or more keys. For example, `wp_posts` has a key to let it quickly find posts when you know the author. Without its _post_author_ key MySQL would have to scan the entire table looking for posts matching the author you want. We all know what that looks like: slow.

In a new WordPress site with a couple of users and a dozen posts, the keys don't matter very much. As the site grows the keys start to matter, a lot. Database management systems are designed to have their keys updated, adjusted, and tweaked as their tables grow. They're designed to allow the keys to evolve without changing the content of the underlying tables. In organizations with large databases Adding, dropping, or altering keys doesn't change the underlying data. It is a routine maintenance task in many data centers. If changing keys caused databases to lose data, the MySQL and MariaDB developers would hear howling not just from you and me, but from many heavyweight users. (You should still back up your WordPress instance of course.)

Better keys allow WordPress's code to run faster _without any code changes_.  Code is poetry, data is treasure, and database keys are grease that makes code and data work together smoothly.

This plugin updates those keys. It works on six tables found in all WordPress installations.

* wp_options
* wp_posts
* wp_postmeta
* wp_comments
* wp_usermeta
* wp_termmeta

Experience with large sites shows that many MySQL slowdowns can be improved by better keys.  When you install and activate this plugin, you'll find a Tool (under the Tools menu) to add high-performance keys to those tables. You only need run it once to get its benefit.

== Frequently Asked Questions ==

= Should I back up my site before using this? =

Yes. You already knew that.

= I use a nonstandard database prefix. Will this work ? =

Yes. Some WordPress databases have tables named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

= Which versions of MySQL and MariaDB does this support? =

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB version 10 and above.

= What database Storage Engine does this support? =

InnoDB only. If your tables use MyISAM (the older storage engine) this plugin offers to upgrade them for you. If you have the _Barracuda_ version of InnoDB, this plugin uses its capability to build efficient keys. If you have the older version it still builds keys, but they are less efficient. It must use [Index Prefixes][https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix] on that version.

= Does this plugin generate any overhead when my site is busy? =

No. All this plugin's work happens from the WordPress Dashboard. Only a tiny bit of its code runs when your visitors view your site.

= How can I learn more about this business of database keys? =

It's a large topic. Some people (often called Database Administrators--DBSs) make entire careers out of this work. Where can you look?

* Marcus Winand's great book [Use The Index, Luke][https://use-the-index-luke.com].
* Rick James, a contributor to this plugin, has a good article [Building the best INDEX for a given SELECT][http://mysql.rjweb.org/doc.php/index_cookbook_mysql].
* StackOverflow's [Why are references to wp_postmeta so slow?[https://stackoverflow.com/questions/43859351/why-are-references-to-wp-postmeta-so-slow] is useful.
* [wordpress.stackexchange.com][https://wordpress.stackexchange.com]'s article [Simple SQL query on wp_postmeta very slow][https://wordpress.stackexchange.com/questions/248207/simple-sql-query-on-wp-postmeta-very-slow].
* The [proposal][https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/] for this plugin.

== Changelog ==

= 0.0.5 =
* First release

== Upgrade Notice ==
The first release.

== Screenshots ==

1. The settings page.