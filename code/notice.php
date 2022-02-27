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
