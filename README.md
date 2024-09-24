# Index MySQL For Speed

A plugin to modernize indexes to your WordPress installation's MySQL database.

For more information [see here](https://plumislandmedia.net/index-wp-mysql-for-speed/).

## Internationalization

Use this command to generate the .pot file.

```bash
wp i18n make-pot . languages/index-wp-mysql-for-speed.pot --path=/var/www/ubu2010.plumislandmedia.local
```

## Plugin repo stuff

Here's the info on the repo.

* SVN URL: https://plugins.svn.wordpress.org/index-wp-mysql-for-speed
* Public URL: https://wordpress.org/plugins/index-wp-mysql-for-speed
* GitHub source code URL: https://github.com/OllieJones/index-wp-mysql-for-speed

## Making a one-off zip file

In the plugin's top level directory:

```bash
wp dist-archive .
```

### Repo update notes.

1. Make the changes.
2. Be sure to update the current version number wherever it appears.
3. Commit to GitHub and push.

To automatically release the plugin to the WordPress repo, we're using a GitHub Action with the workflow
called [WordPress Plugin Deploy](https://github.com/marketplace/actions/wordpress-plugin-deploy). The act of publishing
a release on GitHub now deploys the plugin to the WordPress repo. More
information [here](https://www.plumislandmedia.net/wordpress/wordpress-plugin-tools/).

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