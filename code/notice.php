<?php

class ImfsNotice {

  private $monval;
  private $notice;

  public function __construct( $notice = false ) {
    $this->monval = get_option( index_wp_mysql_for_speed_monitor );
    if ( $this->monval ) {
      add_action( 'admin_notices', [ $this, 'display_monitor_notice' ] );
    }

    if ( $notice ) {
      add_action( 'admin_notices', [ $this, 'display_update_notice' ] );
    }
    $this->notice = $notice;
  }

  /** Put monitor notice on dashboard.
   * @return void
   */
  public function display_monitor_notice() {
    $monval = $this->monval;
    $url    = admin_url( 'tools.php?page=imfs_settings&tab=monitor_database_operations' );
    /* translators: 1: name of monitor  2: date / time */
    $description = __( 'Monitoring is in progress until %2$s. Monitoring output saved into <i>%1$s</i>.', 'index-wp-mysql-for-speed' );
    $description = sprintf( $description, $monval->name, wp_date( 'g:i:s a T', $monval->stoptime ) );
    $text        = "<A HREF=\"{$url}\">Index WP MySql For Speed</A>";
    ?>
      <div class="notice notice-info">
          <p><?php echo $text ?>: <?php echo $description ?></p>
      </div>
    <?php
  }

  /** hook to display the nag.
   * @noinspection HtmlUnknownTarget
   */
  public function display_update_notice() {
    global $_REQUEST;
    if ( is_array( $_REQUEST ) && array_key_exists( 'page', $_REQUEST ) && $_REQUEST['page'] === 'imfs_settings' ) {
      return;
    }

    $url = admin_url( 'tools.php?page=imfs_settings' );
    /* translators: 1: hyperlink to tools page for this plugin. 2: the translated word "add" or "update" */
    $notice = __( 'Use the Index WP MySQL For Speed plugin <a href="%1$s">to %2$s your high-performance keys</a>.', 'index-wp-mysql-for-speed' );
    if ( $this->notice ) {
      switch ( $this->notice ) {
        case 'add':
          $remind = __( 'add', 'index-wp-mysql-for-speed' );
          $notice = sprintf( $notice, $url, $remind );
          break;
        case 'version_update':
          /* translators: 1: hyperlink to tools page for this plugin. */
          $notice = __( 'Use the Index WP MySQL For Speed plugin <a href="%1$s">to update your high-performance keys</a> for the latest WordPress version.', 'index-wp-mysql-for-speed' );
          $notice = sprintf( $notice, $url );
          break;
        default:
          $remind = __( 'update', 'index-wp-mysql-for-speed' );
          $notice = sprintf( $notice, $url, $remind );
          break;
      }

      $notice = '<div class="notice notice-info is-dismissible"><p>' . $notice . '</p></div>';
      echo $notice;
    }
  }
}
