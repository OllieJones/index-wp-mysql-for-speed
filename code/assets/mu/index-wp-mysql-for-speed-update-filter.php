<?php
/** Plugin Name: Index WP MySQL For Speed Upgrade Filter for mu-plugins.
 *  Description: Prevents version upgrades from changing database table keys. Installed during activation, removed during deactivation.
 *  Version: 1.4.14
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
  error_log( 'upgrade_filter invoked' );

  /* we want to do nothing here UNLESS WE'RE SURE a core update is in progress. */
  $lock_option = 'core_updater.lock';
  $lock_result = get_option( $lock_option );
  if ( defined( 'INDEX_WP_MYSQL_FOR_SPEED_TEST' ) && INDEX_WP_MYSQL_FOR_SPEED_TEST ) {
    $lock_result = time();
  }
  /* no lock option found? we're not doing a core update, so bail */
  if ( ! $lock_result ) {
    error_log( 'upgrade_filter bailed because no core update is in progress.' );
    return $queries;
  }

  /* Check to see if the lock is still valid. If it isn't, bail. */
  if ( $lock_result <= ( time() - ( 15 * MINUTE_IN_SECONDS ) ) ) {
    error_log( 'upgrade_filter bailed because the core update lock is too old.' );
    return $queries;
  }

  global $wpdb;

  $tablenames     = [ 'termmeta', 'commentmeta', 'comments', 'options', 'postmeta', 'posts', 'users', 'usermeta' ];
  $tablesToHandle = array();
  foreach ( $tablenames as $tablename ) {
    $tablesToHandle[ $wpdb->prefix . $tablename ] = 1;
  }
  if ( is_string( $wpdb->base_prefix ) ) {
    foreach ( $tablenames as $tablename ) {
      $tablesToHandle[ $wpdb->base_prefix . $tablename ] = 1;
    }
  }

  $doSomething = false;
  /* do any of the queries relate to rekeyed tables? If not, bail. */
  foreach ( $queries as $query ) {
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      $table = table_name( $query );
      if ( array_key_exists( $table, $tablesToHandle ) ) {
        $doSomething = true;
        break;
      }
    }
  }

  /* bail unless it's one or more of our tables */
  if ( ! $doSomething ) {
    error_log( 'upgrade_filter bailed because none of our tables are implicated' );
    return $queries;
  }

  /* A core update is in progress (the lock is valid).  */
  error_log( 'upgrade filter running' );
  // TODO add_filter( 'query', 'index_wp_mysql_for_speed\query_filter', 10, 1 );
  // TODO add_action( 'update_option', 'index_wp_mysql_for_speed\update_option', 10, 3 );
  $results = [];
  foreach ( $queries as $query ) {
    $resultQuery = $query;
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      /* Get the name of the table involved here. Strip backticks, extract table name. */
      $table = table_name( $query );
      if ( array_key_exists( $table, $tablesToHandle ) ) {
        error_log( 'original ddl: ' . $query );

        /* get the present table definition without backticks */
        $suppress    = $wpdb->suppress_errors( true );
        $resultQuery = $wpdb->get_row( "SHOW CREATE TABLE $table;", ARRAY_N );
        $wpdb->suppress_errors( $suppress );

        if ( is_array( $resultQuery ) && 2 === count( $resultQuery ) && is_string( $resultQuery[1] ) ) {
          $resultQuery = $resultQuery[1];
          $resultQuery = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $resultQuery );
          $resultQuery = preg_replace( '/[[:space:]]+AUTO_INCREMENT=\d+[[:space:]]+/msS', ' ', $resultQuery );
          error_log( 'modified ddl: ' . $resultQuery );

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

function query_filter( $query ) {
  if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
    error_log( 'update_filter doing query: ' . $query );
  }
  return $query;
}

function update_option( $option, $new, $old) {
  error_log('update_filter updating option: ' . $option );
}

