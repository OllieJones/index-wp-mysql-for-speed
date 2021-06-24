# Index MySQL For Speed

A plugin to add useful indexes to your WordPress installation's MySQL database.

For more information [see here](https://www.plumislandmedia.net/wordpress/speeding-up-wordpress-database-operations/).

## Plugin repo stuff

See [this](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).

Here's the info on the repo.

* SVN URL: https://plugins.svn.wordpress.org/index-wp-mysql-for-speed
* Public URL: https://wordpress.org/plugins/index-wp-mysql-for-speed

### Repo update notes.

1. Make the changes.
2. Be sure to update the current version number whereever it appears.
3. Commit to github and push.
4. If it isn't done already, do 
   ```bash
   svn co https://plugins.svn.wordpress.org/index-wp-mysql-for-speed svn
   ```
5. Copy the files to be released into the `svn/trunk` directory.
6. From the `svn` directory do
   ```bash
   svn cp trunk tags/xx.yy.zz
   ```
   where `xx.yy.zz` is the version to release
7. Do this
   ```bash
   svn ci -m "vxx.yy.zz commit message"
   ```
   `svn ci` may prompt you on a web browser. If it seems to hang, look at a browser.

## Description

* Version: 0.9.1
* Author: Ollie Jones
* Author URI: https://github.com/OllieJones
* Requires at least: 5.2
* Requires PHP:      7.2
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       index-mysql-for-speed
* Domain Path:       /languages

[Rick James](http://mysql.rjweb.org/") and I are cooking up a plugin to help optimize the way WordPress uses its MySQL database.


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

The wp_options table has one of those automatically incrementing primary keys, where each row has a number. It's called option_id. And, it has a key on the "autoload" column of data to help speed up filtering by autoload = 'yes'.  That's a competently designed table (of course! WordPress's developers are poets). But we can do better, especially considering how often we must get all the autoload rows. We can change the table's primary key so it includes two columns rather than one: autoload and option_id. It still serves the uniqueness purpose: the option_ids are unique. But putting autoload first in the primary key means MySQL can retrieve the autoloaded rows directly from the clustered index, rather than looking in a secondary key to find the primary key. The saved milliseconds and milliwatts add up, especially on a busy site. So we change the primary key like this.

```sql
ALTER TABLE wp_options ADD PRIMARY KEY (autoload, option_id)
```

The actual changes are a little more involved than that, but you get the idea.