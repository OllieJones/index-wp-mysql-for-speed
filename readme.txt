=== Index WP MySQL For Speed ===
Contributors: OllieJones, rjasdfiii
Tags: index, key, performance, mysql, wp-cli
Requires at least: 4.2
Tested up to: 6.5
Requires PHP: 5.6
Stable tag: 1.4.18
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://github.com/OllieJones/
Plugin URI: https://plumislandmedia.net/index-wp-mysql-for-speed/
Github Plugin URI: https://github.com/OllieJones/index-wp-mysql-for-speed/
Primary Branch: main
Text Domain: index-wp-mysql-for-speed
Domain Path: /languages

Speed up your WordPress site by adding high-performance keys (database indexes) to your MariaDB / MySQL database tables.

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
* `wp index-mysql disable --all` removes the high-performance keys from all tables that have them, restoring WordPress's default keys.
* `wp index-mysql enable --all --dryrun` writes out the SQL statements necessary to add the high-performance keys to all tables, but does not run them.
* `wp index-mysql enable --all --dryrun | wp db query` writes out the SQL statements and pipes them to wp db to run them.

Note: avoid saving the --dryrun output statements to run later. The plugin generates them to match the current state of your tables.

<h4>What's new in the latest version?</h4>

Since the first release, our users have told us about several more opportunities to speed up their WooCommerce and core WordPress operations. We've added keys to the `meta` tables to help with searching for content, and to the `users` table to look people up by their display names. And, you can now upload saved Monitors so we can see your slowest queries. We'll use that information to improve future versions. Thanks, dear users!

The plugin now handles WordPress version updates correctly: they don't change your high-performance keys.

We have added the --dryrun switch to the WP-CLI interface for those who want to see the SQL statements we use.

<h4>Why use this plugin?</h4>

Three reasons (maybe four):

1. to save carbon footprint.
1. to save carbon footprint.
1. to save carbon footprint.
1. to save people time.

Seriously, the microwatt hours of electricity saved by faster web site technologies add up fast, especially at WordPress's global scale.


= Credits =
* Michael Uno for Admin Page Framework.
* Marco Cesarato for LiteSQLParser.
* Allan Jardine for Datatables.net.
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.
* Jetbrains for their IDE tools, especially PhpStorm. It's hard to imagine trying to navigate an epic code base without their tools.

== Installation ==

You may install this plugin by visiting Plugins > Add New on your site's Dashboard, then searching for *Index WP MySQL For Speed* and following the usual installation workflow.

When you activate it, it will copy [a php source file](https://www.plumislandmedia.net/reference/filtering-database-changes-during-wordpress-updates/) into the [must-use plugins directory](https://wordpress.org/support/article/must-use-plugins/), `wp-content/mu-plugins`. Some sites' configurations prevent the web server from writing files into that directory. In that case the plugin will still work correctly. But, after WordPress core version upgrades you may have to revisit the Tools > Index MySQL page and correct the keying on some tables. Why? The mu-plugin prevents core version updates from trying to change keys.

= Composer =

If you configure your WordPress installation using composer, you may install this plugin into your WordPress top level configuration with the command

`composer require "wpackagist-plugin/index-wp-mysql-for-speed":"^1.4"`

During composer installation the plugin can automatically copy the necessary source file (see the previous section) into the must-use plugins directory. If you want that to happen, you should include these scripts in your top-level `composer.json` file.

` "scripts": {
         "install-wp-mysql-mu-module": [
                 "@composer --working-dir=wordpress/wp-content/plugins/index-wp-mysql-for-speed install-mu-module"
         ],
         "post-install-cmd": [
                 "@install-wp-mysql-mu-module"
         ],
         "post-update-cmd": [
                 "@install-wp-mysql-mu-module"
         ]
     },
`

== Frequently Asked Questions ==

= Should I back up my site before using this? =

**Yes.** You already knew that.

= I don't see any changes to my database speed. Why not? =

* Just installing and activating the plugin is **not enough to make it work**. Don't forget to visit the Index MySQL Tool under the Tools menu. From there you can press the **Add Keys Now** button.
* On a modestly sized site (with a few users and a few hundred posts) your database may be fast enough without these keys. The speed improvements are most noticeable on larger sites with many posts and products.

= I use a nonstandard database table prefix. Will this work ? =

**Yes.** Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

= Which versions of MySQL and MariaDB does this support? =

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB versions 5.5.62 and above.

= What database Storage Engine does this support? =

**InnoDB only.** If your tables use MyISAM (the older storage engine) or the older COMPACT row format, this plugin offers to upgrade them for you.

= What tables and keys does the plugin change? =

[Please read this](https://www.plumislandmedia.net/index-wp-mysql-for-speed/tables_and_keys/).

= Is this safe? Can I add high-performance keys and revert back to WordPress standard keys safely?

Yes. it is safe to add keys and revert them. Changing keys is a routine database-maintenance operation.

As you know you should still keep backups of your site: other things can cause data loss.

= My site uses WooCommerce HPOS (High Performance Order Storage). Is this plugin still helpful?

**Yes.** WooCommerce still uses core WordPress tables for your shop's products, posts, pages, and users. This plugin adds high-performance keys to those tables.

High Performance Order Storage, true to its name, stores your shop's orders in a more efficient way. Formerly orders were stored in those same core WordPress tables.

= Is this plugin compatible with some other specific plugin? =

This plugin only changes database indexes. If the other plugin does not change database indexes, it is very likely compatible with this one.

Of course, if you find an incompatibility please open a support topic.

= I got a fatal error trying to add keys. How can I fix that? =

Sometimes the Index WP MySQL For Speed plugin for WordPress generates errors when you use it to add keys. These can look like this or similar:

    Fatal error: Uncaught ImfsException: [0]: Index for table 'wp_postmeta' is corrupt; try to repair it

First, don't panic. This (usually) does not mean your site has been corrupted. It simply means your MariaDB or MySQL server was not able to add the keys to that particular table. Your site will still function, but you won’t get the benefit of high-performance keys on the particular table. Very large tables are usually the ones causing this kind of error. Very likely you ran out of temporary disk space on your MariaDB or MySQL database server machine. The database server makes a temporary copy of a table when you add keys to it; that allows it to add the keys without blocking your users.

It’s possible to correct this problem by changing your MariaDB or MySQL configuration. [Instructions are here](https://wordpress.org/support/topic/fatal-error-uncaught-exception-29/).

= What happens to my tables and keys during a WordPress version update? =

If the plugin is activated during a WordPress version update, it prevents the update workflow from removing your high-performance keys (Version 1.4.7).

= My site has thousands of registered users. My Users, Posts, and Pages panels in my dashboard are still load slowly even with this plugin.

We have another plugin to handle lots of users, [Index WP Users For Speed](https://wordpress.org/plugins/index-wp-users-for-speed/). Due to the way WordPress handles users, just changing database keys is not enough to solve those performance problems.

= How can I enable persistent object caching on my site? =

Persistent object caching can help your site's database performance by reducing its workload. You can read about it [here](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-cache-plugins). If your hosting provider doesn't offer redis or memcached cache-server technology you can try using our [SQLite Object Cache](https://wordpress.org/plugins/sqlite-object-cache/) plugin for the purpose.

= Why did the size of my tables grow when I added high-performance keys? =

Database keying works by making copies of your table’s data organized in ways that are easy to randomly access. Your MariaDB or MySQL server automatically maintains the copies of your data as you insert or update rows to each table.  And, the keying task adjusts the amount of free space in each block of your table’s data in preparation for the insertion of new rows. When free space is available, inserting new rows doesn’t require relatively slow block splits. Tables that have been in use for a long time often need new free space in many blocks. When adding keys, it is normal for table sizes to increase. It’s the oldest tradeoff in computer science: time vs. space.

= Will the new keys be valid for new data in the tables? =

**Yes**. Once the high-performance keys are in place MariaDB and MySQL automatically maintain them as you update,  delete, or insert rows of data to your tables. There is no need to do anything to apply the keys to new data: the DBMS software does that for you.

= How do I revert to WordPress's standard keys, undoing the action of this plugin? =

You can revert the keys from the Index MySQL Tool under the Tools menu, or use the wp-cli command `wp index-mysql disable --all`. *Notice* that if you deactivate or delete the plugin without doing this, the high-performance keys *remain*.

= How do I get an answer to another question? =

Please see more questions and answers [here](https://plumislandmedia.net/index-wp-mysql-for-speed/faq/).

== Changelog ==

= 1.4.18 =
Security update.

= 1.4.17 =
Back out a miscellaneous bug fix from the previous version. It was an attempt to avoid a warning from Query Monitor's hooks display.
Upload the full MariaDB / MySQL version information with monitors as well as metadata.

= 1.4.16 =
(no changes to keys)
WordPress 6.5 compatibility.
Support WordPress versions back to 4.2 (At MDDHosting's request).
Avoid attempting to upgrade from storage engines except MyISAM and Aria.
WP-CLI upgrade, enable, and disable commands are idempotent now. They don't generate errors when they find no tables to process.
Miscellaneous bug fixes


== Upgrade Notice ==

We've added support for versions of WordPress back to 4.2 (the version when utf8mb4 burst on the scene and required prefix indexes).

We've added a Database Health section to the About tab. It shows some current performance measurements made from your MySQL / MariaDB database server, similar to the stored ones shown in monitor displays.

We've removed various programming-language incompatibilities with php 8.2.

We now use backticks to delimit table names, giving compatibility with some strange plugins.

We've fixed some bugs.

== Screenshots ==

1. Use Tools > Index MySQL to view the Dashboard panel.
2. Choose tables and add High-Performance Keys.
3. Start Monitoring Database Operations, and see saved monitors.
4. View a saved monitor to see slow database queries.
5. About the plugin.
6. Use WP CLI to run the plugin's operations.
