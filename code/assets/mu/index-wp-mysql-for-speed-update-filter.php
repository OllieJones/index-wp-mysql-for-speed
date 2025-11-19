<?php
/** Plugin Name: Index WP MySQL For Speed Upgrade Filter for mu-plugins.
 *  Description: Prevents version upgrades from changing database table keys. Installed during activation, removed during deactivation.
 *  Version: 1.5.6
 *  License: GPL v2 or later
 */

namespace index_wp_mysql_for_speed;

if ( ! defined( 'ABSPATH' ) ) {
  die( 'We\'re sorry, but you can not directly access this file.' );
}

/* this filter needs to be called during version upgrades, when ordinary plugins aren't loaded. */
add_filter( 'dbdelta_queries', 'index_wp_mysql_for_speed\upgrade_filter', 10, 1 );

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
 * @see WP_Upgrader::create_lock()
 *
 */
function upgrade_filter( $queries ) {

  global $wpdb;

  $core_tablenames     = [ 'termmeta', 'commentmeta', 'comments', 'options', 'postmeta', 'posts', 'users', 'usermeta' ];
  $extra_tablenames     = [ 'woocommerce_order_itemmeta','wc_orders_meta','automatewoo_log_meta' ];
  $tablesToHandle = array();
  foreach ( $core_tablenames as $tablename ) {
    $tablesToHandle[ $wpdb->prefix . $tablename ] = 1;
  }
  if ( is_string( $wpdb->base_prefix ) ) {
    foreach ( $core_tablenames as $tablename ) {
      $tablesToHandle[ $wpdb->base_prefix . $tablename ] = 1;
    }
  }
  $extraTablesToHandle = array();
  foreach ( $extra_tablenames as $tablename ) {
    $extraTablesToHandle[ $wpdb->prefix . $tablename ] = 1;
  }


  $doCore = false;
  $doExtra = false;
  /* do any of the queries relate to rekeyed core tables?  */
  foreach ( $queries as $query ) {
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      $table = table_name( $query );
      if ( array_key_exists( $table, $tablesToHandle ) ) {
        $doCore = true;
        break;
      }
    }
  }
  /* do any of the queries relate to rekeyed extra tables?  */
  foreach ( $queries as $query ) {
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      $table = table_name( $query );
      if ( array_key_exists( $table, $extraTablesToHandle ) ) {
        $doExtra = true;
        break;
      }
    }
  }

  /* bail unless it's one or more of our tables */
  if ( false === $doCore && false === $doExtra ) {
    return $queries;
  }

  /* we want to do nothing to core tables UNLESS WE'RE SURE a core update is in progress. */
  $lock_result = get_option( 'core_updater.lock' );
  if ( ! $lock_result ) {
    $tablesToHandle = array ();
  }

  $tablesToHandle = array_merge( $tablesToHandle, $extraTablesToHandle );

  $results = [];
  foreach ( $queries as $query ) {
    $resultQuery = $query;
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      $table = table_name( $query );
      if ( array_key_exists( $table, $tablesToHandle ) ) {
        /* get the present table definition without backticks */
        $suppress    = $wpdb->suppress_errors( true );
        $resultQuery = $wpdb->get_row( "SHOW CREATE TABLE $table;", ARRAY_N );
        $wpdb->suppress_errors( $suppress );

        if ( is_array( $resultQuery ) && 2 === count( $resultQuery ) && is_string( $resultQuery[1] ) ) {
          $resultQuery = $resultQuery[1];
          $resultQuery = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $resultQuery );
          $resultQuery = preg_replace( '/[[:space:]]+AUTO_INCREMENT=\d+[[:space:]]+/msS', ' ', $resultQuery );
        }
      }
    }
    $results [] = $resultQuery;
  }
  return $results;
}

function table_name( $query ) {
  /* Get the name of the table involved here. Strip backticks, extract table name. */
  $query = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $query );
  return preg_replace( '/^[[:space:]]*CREATE TABLE[[:space:]]+([0-9a-zA-Z_]+).*$/msS', '$1', $query );
}
