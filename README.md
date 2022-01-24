# Index MySQL For Speed

A plugin to add useful indexes to your WordPress installation's MySQL database.

For more information [see here](https://plumislandmedia.net/index-wp-mysql-for-speed/).

## Plugin repo stuff

Here's the info on the repo.

* SVN URL: https://plugins.svn.wordpress.org/index-wp-mysql-for-speed
* Public URL: https://wordpress.org/plugins/index-wp-mysql-for-speed
* Github source code URL: https://github.com/OllieJones/index-wp-mysql-for-speed

### Repo update notes.

1. Make the changes.
2. Be sure to update the current version number whereever it appears.
3. Commit to GitHub and push.

To automatically release the plugin to the WordPress repo, we're using a Github Action with the workflow called [WordPress Plugin Deploy](https://github.com/marketplace/actions/wordpress-plugin-deploy). The act of publishing a release on Github now deploys the plugin to the WordPress repo. More information [here](https://www.plumislandmedia.net/wordpress/wordpress-plugin-tools/).

### Pre-automation plugin release

See [this](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).

This is here for archive purposes only. Use the automated process, please.

1. If it isn't done already, do 
   ```bash
   svn co https://plugins.svn.wordpress.org/index-wp-mysql-for-speed svn
   ```
2. Copy the files to be released into the `svn/trunk` directory.
3. From the `svn` directory add any new files:
   ```bash
   svn add svn/trunk/code/whatever.ext
    ```
4. From the `svn` directory do
   ```bash
   svn cp trunk tags/xx.yy.zz
   ```
   where `xx.yy.zz` is the version to release
5. Do this
   ```bash
   svn ci -m "vxx.yy.zz commit message"
   ```
   `svn ci` may prompt you on a web browser. If it seems to hang, look at a browser.

# readme.txt

**Stable tag:** 1.4.2 \
**Contributors:** OllieJones, rjasdfiii \
**Tags:** database, index, key, mysql, wp-cli \
**Requires at least:** 5.2 \
**Tested up to:** 5.9 \
**Requires PHP:** 7.2 \
**Stable tag:** 1.3.3 \
**Network**: true \
**License:** GPL v2 or later \
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html \
**Author URI**: https://github.com/OllieJones \
**Plugin URI**: https://plumislandmedia.net/index-wp-mysql-for-speed/ \
**Text Domain**: index-wp-mysql-for-speed \
**Domain Path**: /languages

Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.

## Description

<h4>How do I use this plugin?</h4>

Use this plugin with the Index MySQL Tool under the Tools menu. Or, give the shell command _wp help index-mysql_ to learn how to use it with [WP-CLI](https://wp-cli.org/).

<h4>What does it do for my site?</h4>

This plugin works to make your MySQL database work more efficiently by adding high-performance keys to its tables. It also monitors your site's use of your MySQL database to detect which database operations are slowest. It is most useful for large sites: sites with many users, posts, pages, and / or products.

<h4>What is this all about?</h4>

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL](https://www.mysql.com/) relational database management system. (Many hosting providers and servers use the [MariaDB](https://mariadb.org/) fork of the MySQL software; it works exactly the same as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task. That is not the task of this plugin. You can, if your hosting provider supports it, install and use a [Persistent Object Cache plugin](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-cache-plugins) to reduce traffic to your database. That is not the task of this plugin either.

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

### Credits

* Michael Uno for Admin Page Framework.
* Marco Cesarato for LiteSQLParser.
* Allan Jardine for Datatables.net.
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

## Frequently Asked Questions

### Should I back up my site before using this?

**Yes.** You already knew that.

### I use a nonstandard database table prefix. Will this work ?

**Yes.** Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

### My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

### Which versions of MySQL and MariaDB does this support?

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB versions 5.5 and above.

### What database Storage Engine does this support?

**InnoDB only.** If your tables use MyISAM (the older storage engine) or the older COMPACT row format, this plugin offers to upgrade them for you.

### How do I get an answer to another question?

Please see more questions and answers [here](https://plumislandmedia.net/index-wp-mysql-for-speed/faq/).



## Changelog

### 1.3.3

When upgrading tables, change ROW_FORMAT to DYNAMIC as well as ENGINE to InnoDB. Add monitors.

### 1.3.4

Support MariaDB 10.1, make indexes work a little better, miscellaneous bugfixes.

### 1.4.1

* WordPress 5.9 and database version 51917 version compatibility tested.
* Rekeys tables in one go: allows the plugin to work more quickly, and when sql_require_primary_key

### 1.4.2
* 
* (No changes to indexes from 1.4.1)
* Add support for legacy php versions back to 5.6.
* Avoid attempting to read `INNODB_METRICS` when user lacks `PROCESS` privilege.
* Correct nag hyperlink on multisite.



### ON (typically at managed service providers).

* Adds high-performance keys to wp_users and wp_commentmeta tables.
* Adds high-performance key for looking up meta values quickly in wp_postmeta, wp_termmeta, and wp_usermeta.
* Handles upgrades to high-performance keys, from previous plugin versions.
* Checks $wp_db_version number to ensure schema compatibility.
* Monitor captures include overall database server metrics, and can be uploaded.
* Help pages for each tab of the plugin's Dashboard panel.
* Clearer Dashboard panel displays.

## Upgrade Notice

Many performance improvements, especially for larger WooCommerce sites. Better help pages. Several bug fixes.

## Screenshots

1. Use Tools > Index MySQL to view the Dashboard panel.
2. Choose tables to get High-Performance Keys.
3. Start Monitoring Database Operations, and see saved monitors.
4. View a saved monitor to see slow database queries.
5. About the plugin.
6. Use WP CLI to run the plugin's operations.
: /languages 

Speed up your WordPress site by adding high-performance keys (database indexes) to your MySQL database tables.

## Description

<h4>How do I use this plugin?</h4>

Use this plugin with the Index MySQL Tool under the Tools menu. Or, give the shell command _wp help index-mysql_ to learn how to use it with [WP-CLI](https://wp-cli.org/).

<h4>What does it do for my site?</h4>

This plugin works to make your MySQL database work more efficiently by adding high-performance keys to its tables. It also monitors your site's use of your MySQL database to detect which database operations are slowest. It is most useful for large sites: sites with many users, posts, pages, and / or products.

<h4>What is this all about?</h4>

Where does WordPress store all that stuff that makes your site great? Where are your pages, posts, products, media, users, custom fields, metadata, and all your valuable content? All that data is in the [MySQL](https://www.mysql.com/) relational database management system. (Many hosting providers and servers use the [MariaDB](https://mariadb.org/) fork of the MySQL software; it works exactly the same as MySQL itself.)

As your site grows, your MySQL tables grow. Giant tables can make your page loads slow down, frustrate your users, and even hurt your search-engine rankings. What can you do about this?

You can install and use a database cleaner plugin to get rid of old unwanted data and reorganize your tables. That makes them smaller, and therefore faster. That is a good and necessary task. That is not the task of this plugin. You can, if your hosting provider supports it, install and use a [Persistent Object Cache plugin](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-cache-plugins) to reduce traffic to your database. That is not the task of this plugin either.

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

### Credits

* Michael Uno for Admin Page Framework.
* Marco Cesarato for LiteSQLParser.
* Allan Jardine for Datatables.net.
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

## Frequently Asked Questions

### Should I back up my site before using this?

**Yes.** You already knew that.

### I use a nonstandard database table prefix. Will this work ?

**Yes.** Some WordPress databases have [nonstandard prefixes](https://codex.wordpress.org/Creating_Tables_with_Plugins#Database_Table_Prefix). That is, their tables are named _something_posts_, _something_postmeta_, and so forth instead of _wp_posts_ and _wp_postmeta_. This works with those databases.

### My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

### Which versions of MySQL and MariaDB does this support?

MySQL versions 5.5.62 and above, 5.6.4 and above, 8 and above. MariaDB versions 5.5 and above.

### What database Storage Engine does this support?

**InnoDB only.** If your tables use MyISAM (the older storage engine) or the older COMPACT row format, this plugin offers to upgrade them for you.

### How do I get an answer to another question?

## Please see more questions and answers [here](https://plumislandmedia.net/index-wp-mysql-for-speed/faq/).



## Changelog

### 1.3.3

When upgrading tables, change ROW_FORMAT to DYNAMIC as well as ENGINE to InnoDB. Add monitors.

### 1.3.4

Support MariaDB 10.1, make indexes work a little better, miscellaneous bugfixes.

### 1.4.1

* WordPress 5.9 and database version 51917 version compatibility tested.
* Rekeys tables in one go: allows the plugin to work more quickly, and when sql_require_primary_key

### ON (typically at managed service providers).

* Adds high-performance keys to wp_users and wp_commentmeta tables.
* Adds high-performance key for looking up meta values quickly in wp_postmeta, wp_termmeta, and wp_usermeta.
* Handles upgrades to high-performance keys, from previous plugin versions.
* Checks $wp_db_version number to ensure schema compatibility.
* Monitor captures include overall database server metrics, and can be uploaded.
* Help pages for each tab of the plugin's Dashboard panel.
* Clearer Dashboard panel displays.

## Upgrade Notice

Many performance improvements, especially for larger WooCommerce sites. Better help pages. Several bug fixes.

## Screenshots

1. Use Tools > Index MySQL to view the Dashboard panel.
2. Choose tables to get High-Performance Keys.
3. Start Monitoring Database Operations, and see saved monitors.
4. View a saved monitor to see slow database queries.
5. About the plugin.
6. Use WP CLI to run the plugin's operations.


## The basic idea

* It's an ordinary [WordPress plugin](https://developer.wordpress.org/plugins/intro/) downloadable from the plugin repository.
* Upon [activation](https://developer.wordpress.org/reference/functions/activate_plugin/), it examines ...
   * the WordPress version and maybe the php version,
   * the MySQL / MariaDB version,
   * the character set used in the database (possibly utf8, utf8mb4, or a one-byte character set like latin1), and
   * the sizes of various tables.
  
If the versions are too old (or too new), the plugin announces that it can't help and refuses to activate.

It then shows a Settings screen to the site administrator suggesting appropriate data definition language changes to make to the MySQL database. Most of these changes are additional indexes.The WordPress site admin may choose individual changes, or choose all changes. The plugin makes the changes upon command from the admin.

Upon deactivation, the plugin restores the database to the condition it was in when activated (dropping additional indexes and similar changes). It's vital that the plugin be able to remove itself cleanly: *no irreversible DDL changes!*

## Possible additional features

With the admin's explicit permission, we post an anonymized description of the instance back to our server, so we can learn about users' needs. Knowing software versions, table sizes, and similar information can help with future versions.

Again with the admin's explicit permission, intercept some slow and/or frequent queries, capture EXPLAIN data for them, and post that back.

## Freemium

If this looks like it can become a viable dba-in-a-plugin service, we can provide license keys to paying customers and offer extra features. Maybe specific support for WooCommerce tables is a viable paid add-on service.

## Specific configuration detection

Allowable index prefix sizes depend on database character sets and specific database version software. The plugin will, likely, need internal lists of allowable DDL by configuration. It's probably best to store that in some simple text file, like a .json or .config file, rather than hardcoding it into the plugin's php code.

## Security

Nobody except site administrators will see anything about this plugin in a site.

We may choose to hash configuration files to slow down malicious attempts to modify them.

## Multisite support

Good question.  Maybe multisite support is a premium feature. If this plugin turns out well, big WordPress hosting services like wpengine might want support.

## Updates

WordPress evolves quickly. Updates to this plugin will be required to keep up with WordPress changes, and changes in the capabilities of the underlying software. Security issues may also force updates.

It's possible for plugins to auto-update themselves. This seems risky when the updates cause the application of additional DDL changes.

Major-version WordPress updates sometimes change the core DDL. If this happens updating the plugin should probably go through a deactivate / reactivate cycle. The good news: the core team makes new beta versions available well before their general availability.

## Localization

WordPress supports localization fairly easily. It's worth doing.

## Related plugins

There are a mess of [database cleaner plugins](https://wordpress.org/plugins/search/database+cleaner/) available. These work by DELETEing no-longer-used rows from various tables and performing OPTIMIZE TABLE operations.  A good example is [Advanced Database Cleaner](https://wordpress.org/plugins/advanced-database-cleaner/).

[Query Monitor](https://wordpress.org/plugins/query-monitor/) is a mature and sophisticated WordPress developer tool. It intercepts ("[hooks](https://developer.wordpress.org/plugins/hooks/)", in WordPress lingo) database queries and captures information about them.

## Specifics

The first version of the plugin changes the [indexing](https://dev.mysql.com/doc/refman/8.0/en/mysql-indexes.html) (keying) of some tables in a WordPress installation's [MySQL](https://dev.mysql.com/) or [MariaDB](https://mariadb.org/) open-source database management sysem. These tables have a name-value design, making it possible to add all sorts of custom fields and other items. Their flexibility has allowed WordPress to develop a vast ecosystem of themes and plugins. But flexibility comes with a cost: querying it can be slow. This plugin adds keys to these tables to help speed up common queries. (The words _index_ and _key_ are synonyms in the world of database management: WordPress's designers prefer the word _key_.)

Database management systems are designed to have their keys updated, adjusted, and tweaked as they grow. In a new WordPress instance with a couple of users and a dozen posts, the keys don't matter. But as a site (especially a WooCommerce site) grows and becomes successful, the keys start to matter a lot. The keys are there to help find the data quickly. For example, if you have ten thousand customers and you want to look somebody up by their billing postcode, it helps to have a key to do that. Without the key the database still finds the customer, but it scans through all the customers. We all know what that looks like: it's slow.

Adding, dropping, or altering keys doesn't change the underlying data. It is a routine maintenance task in many data centers. If changing keys caused systems to lose data, the MySQL and MariaDB developers would hear howling not just from you and me, but from [FB](https://facebook.com), [AMZN](https://amazon.com), and many other heavyweight users. (You should still back up your WordPress instance.)

### The target tables are these

*   wp_options: data for the WordPress instance, containing such things as default settings and configurations for themes and plugins.
*   wp_postmeta: metadata for pages, posts, attachments, WooCommerce products, and similar items.
*   wp_usermeta: metadata describing WordPress users and WooCommerce customers. It has the same characteristics as wp_postmeta.
*   wp_termmeta: also similar to wp_postmeta, this table contains metadata describing tags, categories, and various WooCommerce data.

### How can changing keys make a difference? (wonky)

All modern MySQL databases store their data in tables using a storage engine called [InnoDB](https://dev.mysql.com/doc/refman/8.0/en/innodb-introduction.html).  (Early versions of MySQL used a different, simpler, storage engine, called MyISAM. If you are still using that, it is time to upgrade. Seriously.) WordPress's database tables all have a _primary key_. Think of the primary key as a book's catalog number in a library. You look up, for example Sheeri Cabral and Keith Murphy's excellent [MySQL Administrator's Bible](https://www.worldcat.org/title/mysql-administrators-bible/oclc/44194604), in your library's online catalog by searching for "MySQL" or "Database Administration." Your online lookup gives you the book's catalog number. You then wander around your library looking for the shelf containing books with numbers like that. When you find it, you take out the book.  InnoDB primary keys work like that (but without all the wandering around). Once it knows the primary key, it can grab the data very quickly. In the world of database management, this is called [_clustered_ indexing](https://dev.mysql.com/doc/refman/8.0/en/innodb-index-types.html). It follows that a good choice of primary key can make it very fast for InnoDB to find data.

InnoDB also offers _secondary keys_. A secondary key holds search terms like the "MySQL" or "Database Administration" we used to find Sheeri's book.  Those secondary keys lead us to the primary key. We can think of InnoDB's keys as if they were sorted in alphabetical order. (Technically speaking they use the [B-tree](https://en.wikipedia.org/wiki/B-tree) data structure.) For example, the author key might contain "Cabral, Sheeri" and "Murphy, Keith." If I looked up "Cabral" in the author key I'd find Sheeri right away, get the primary key, and grab her book. (This takes fractions of milliseconds in InnoDB.) But, if I looked up "Sheeri" I would have to scan _every_ author's name to find her: There might be authors named "Aardvark, Sheeri" and "Zyzygy, Sheeri". I know there aren't, but the software doesn't. That takes time. WordPress gets slow when it uses its keys that way. To make this lookup faster we add a new secondary key on authors' first names.

So, we can adjust the primary and secondary keys to make it faster for WordPress to get what it needs from its database tables. This plugin does that.

### What specific key changes do we make? (even wonkier)

Primary keys serve two purposes. They _uniquely identify _their data, and they handle the rapid-lookup _clustered indexing_. Their _unique identification_ purpose means that database designers often set up tables to give each item -- each _row_ of data -- an automatically incrementing serial number for a primary key. Once you know the serial number you can rapidly grab the item from the clustered index. But if you come at the data with some other way of identifying the item, you get an extra lookup step and that slows you down.

For example, the wp_options table contains dozens of rows that WordPress retrieves every time somebody views a page. The row with the option_name of "home", for example, contains https://plumislandmedia.net for this WordPress instance. To get this information, WordPress says this to its database.

```sql
SELECT option_name, option_value FROM wp_options WHERE autoload = 'yes'
```

The wp_options table has one of those automatically incrementing primary keys, where each row has a number. It's called option_id. And, it has a key on the "autoload" column of data to help speed up filtering by autoload = 'yes'.  That's a competently designed table (of course! WordPress's developers are poets). But we can do better, especially considering how often we must get all the autoload rows. We can change the table's primary key to include two columns rather than one: autoload and option_id. It still serves the uniqueness purpose: the option_ids are unique. But putting autoload first in the primary key means MySQL can retrieve the autoloaded rows directly from the clustered index, rather than looking in a secondary key to find the primary key. The saved milliseconds and milliwatts add up, especially on a busy site. So we change the primary key like this.

```sql
ALTER TABLE wp_options ADD PRIMARY KEY (autoload, option_id)
```

The actual changes are a little more involved than that, but you get the idea.