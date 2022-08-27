<?php
/** Plugin Name: Index WP MySQL For Speed Upgrade Filter for mu-plugins.
 *  Description: Prevents version upgrades from changing database table keys. Installed during activation, removed during deactivation.
 *  Version: 1.4.7
 *  License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
  die( 'We\'re sorry, but you can not directly access this file.' );
}

/* this filter never gets called except during version upgrades, when ordinary plugins aren't loaded. */
add_filter( 'dbdelta_queries', 'index_mysql_for_speed_upgrade_filter', 10, 1 );

/** Filters the dbDelta SQL queries.
 *
 * This replaces WordPress's standard table definition, including keys,
 * with the actual definition in place. That prevents the upgrade from
 * attempting to change keys that might have been added.
 *
 * This is crude: if WordPress attempts to add new columns or keys,
 * this will ignore them. It needs work.
 *
 * @param string[] $queries An array of dbDelta SQL queries.
 * @since 3.3.0
 *
 */
function index_mysql_for_speed_upgrade_filter( $queries ) {
  global $wpdb;

  $results = [];
  foreach ( $queries as $query ) {
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      /* Get the name of the table involved here. Strip backticks, extract table name. */
      $query = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $query );
      $table = preg_replace( '/^[[:space:]]*CREATE TABLE[[:space:]]+([0-9a-zA-Z_]+).*$/msS', '$1', $query );

      /* get the present table definition without backticks */
      $actual     = $wpdb->get_row( "SHOW CREATE TABLE $table;", ARRAY_N );
      $actual     = $actual[1];
      $actual     = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $actual );
      $results [] = $actual;
    } else {
      $results [] = $query;
    }
  }
  return $results;
}
