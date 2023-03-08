<?php
/** Plugin Name: Index WP MySQL For Speed Upgrade Filter for mu-plugins.
 *  Description: Prevents version upgrades from changing database table keys. Installed during activation, removed during deactivation.
 *  Version: 1.4.12
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
 * @see WP_Upgrader::create_lock()
 *
 */
function index_mysql_for_speed_upgrade_filter( $queries ) {
  global $wpdb;

  $tablesToHandle = [ 'termmeta', 'commentmeta', 'comments', 'options', 'postmeta', 'posts', 'users', 'usermeta' ];
  $prefix         = $wpdb->prefix;
  if ( is_string( $wpdb->base_prefix ) ) {
    $prefix = $wpdb->base_prefix;
  }

  $doSomething = false;
  /* do any of the queries relate to rekeyed tables? If not, bail. */
  foreach ( $queries as $query ) {
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      /* Get the name of the table involved here. Strip backticks, extract table name. */
      $query = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $query );
      $table = preg_replace( '/^[[:space:]]*CREATE TABLE[[:space:]]+([0-9a-zA-Z_]+).*$/msS', '$1', $query );

      /* make sure the table name we have matches one of the tables we expect */
      $startsOK = 0 === substr_compare( $table, $prefix, 0, strlen( $prefix ) );
      $endsOK   = false;
      foreach ( $tablesToHandle as $tableToHandle ) {
        if ( 0 === substr_compare( $table, $tableToHandle, - strlen( $tableToHandle ) ) ) {
          $endsOK = true;
          break;
        }
      }
      if ( $startsOK && $endsOK ) {
        $doSomething = true;
      }
    }
  }

  /* bail unless it's one or more of our tables */
  if ( ! $doSomething ) {
    return $queries;
  }

  /* we want to do nothing here UNLESS WE'RE SURE a core update is in progress. */
  $lock_option = 'core_updater.lock';
  $lock_result = get_option( $lock_option );
  /* no lock option found? we'e not doing a core update, so bail */
  if ( ! $lock_result ) {
    return $queries;
  }

  // Check to see if the lock is still valid. If it is, bail.
  if ( $lock_result > ( time() - ( 15 * MINUTE_IN_SECONDS ) ) ) {
    return $queries;
  }

  /* A core update is in progress (the lock is valid).  */

  $results = [];
  foreach ( $queries as $query ) {
    $resultQuery = $query;
    if ( preg_match( '/CREATE TABLE[[:space:]]+/S', $query ) ) {
      /* Get the name of the table involved here. Strip backticks, extract table name. */
      $query = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $query );
      $table = preg_replace( '/^[[:space:]]*CREATE TABLE[[:space:]]+([0-9a-zA-Z_]+).*$/msS', '$1', $query );

      /* make sure the table name we have matches one of the tables we expect */
      $startsOK = 0 === substr_compare( $table, $prefix, 0, strlen( $prefix ) );
      $endsOK   = false;
      foreach ( $tablesToHandle as $tableToHandle ) {
        if ( 0 === substr_compare( $table, $tableToHandle, - strlen( $tableToHandle ) ) ) {
          $endsOK = true;
          break;
        }
      }
      if ( $startsOK && $endsOK ) {
        /* get the present table definition without backticks */
        $resultQuery = $wpdb->get_row( "SHOW CREATE TABLE $table;", ARRAY_N );
        if ( is_array( $resultQuery ) && 2 === count( $resultQuery ) && is_string( $resultQuery[1] ) ) {
          $resultQuery = $resultQuery[1];
          $resultQuery = preg_replace( '/`([0-9a-zA-Z_]+)`/msS', '$1', $resultQuery );
        }
      }
    }
    $results [] = $resultQuery;
  }
  return $results;
}
