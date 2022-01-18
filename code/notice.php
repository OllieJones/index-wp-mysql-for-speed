<?php

class ImfsNotice {

  private $notice;

  public function __construct( $notice = false ) {
    if ( $notice ) {
      add_action( 'admin_notices', [ $this, 'display_update_notice' ] );
    }
    $this->notice = $notice;
  }

  /** hook to display the nag.
   * @noinspection HtmlUnknownTarget
   */
  public function display_update_notice() {
    global $_REQUEST;
    if ( is_array( $_REQUEST ) && array_key_exists( 'page', $_REQUEST ) && $_REQUEST['page'] === 'imfs_settings' ) {
      return;
    }

    if ( $this->notice ) {
      switch ( $this->notice ) {
        case 'add':
          $remind = __( 'add', index_wp_mysql_for_speed_domain );
          break;
        case 'update':
          $remind = __( 'update', index_wp_mysql_for_speed_domain );
          break;
        default:
          break;
      }
      $notice = __( 'Use the Index WP MySQL For Speed plugin <a href="/wp-admin/tools.php?page=imfs_settings">to %s your high-performance keys</a>.', index_wp_mysql_for_speed_domain );
      $notice = sprintf( $notice, $remind );
      $notice = '<div class="notice notice-info is-dismissible"><p>' . $notice . '</p></div>';
      echo $notice;
    }
  }
}
