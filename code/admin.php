<?php

require_once( 'rendermonitor.php' );

class ImfsPage extends Imfs_AdminPageFramework {

  public $pluginName;
  public $pluginSlug;
  public $monitors;
  /**
   * @var bool true if the dbms allows reindexing at all.
   */
  public $canReindex = false;
  /**
   * @var bool true if reindexing does not have the 191 prefix index limitation.
   */
  public $unconstrained = false;
  private $wpDbUpgrading = false;
  private $db;
  private $dontNavigate;
  private $tabSuffix;

  public function __construct() {
    parent::__construct();
    $this->pluginName   = __( 'Index WP MySQL For Speed', 'index-wp-mysql-for-speed' );
    $this->db           = new ImfsDb( index_mysql_for_speed_major_version, index_mysql_for_speed_inception_major_version );
    $this->dontNavigate = __( 'This may take a few minutes. <em>Please do not navigate away from this page while you wait</em>.', 'index-wp-mysql-for-speed' );
    $this->tabSuffix    = "_m";
  }

  // https://admin-page-framework.michaeluno.jp/tutorials/01-create-a-wordpress-admin-page/

  public function setUp() {
    $this->setRootMenuPage( 'Tools' );

    $pageName = $this->pluginName;
    /* translators: settings page menu text */
    $menuName = __( 'Index MySQL', 'index-wp-mysql-for-speed' );
    $this->addSubMenuItems(
      [
        'title'      => $pageName,
        'menu_title' => $menuName,
        'page_slug'  => 'imfs_settings',
        'order'      => 31,
        'capability' => 'activate_plugins',

      ]
    );
    $tabs           = [];
    $tabs[]         = [
      'tab_slug' => 'high_performance_keys',
      'title'    => __( 'High-Performance Keys', 'index-wp-mysql-for-speed' ),
    ];
    $tabs[]         = [
      'tab_slug' => 'monitor_database_operations',
      'title'    => __( 'Monitor Database Operations', 'index-wp-mysql-for-speed' ),
    ];
    $this->monitors = RenderMonitor::getMonitors();
    foreach ( $this->monitors as $monitor ) {
      $tabs[] = [
        'tab_slug' => $monitor . $this->tabSuffix,
        'title'    => $monitor,
      ];
    }
    $tabs[] = [
      'tab_slug' => 'about',
      'title'    => __( 'About', 'index-wp-mysql-for-speed' ),
    ];
    $this->addInPageTabs( 'imfs_settings', ...$tabs );
    $this->setPageHeadingTabsVisibility( false );

  }

  /** Render stuff at the top as needed. if the current tab is a monitor, render the header information
   *
   * @param string $sHTML
   *
   * @return string
   * @callback  action content_{position}_{page slug}
   */
  public function content_top_imfs_settings( $sHTML ) {
    $this->enqueueStyles(
      [
        plugins_url( 'assets/imfs.css', __FILE__ ),
      ], 'imfs_settings' );

    $s       = '';
    $monitor = $this->getMonitorName();

    $sHTML = $this->insertHelpTab( $monitor, $sHTML );

    /* renderMonitor doesn't return anything unless we're on a monitor tab */
    if ( $monitor !== false ) {
      $s .= $this->renderMonitor( $monitor, 'top' );
    }

    return $sHTML . $s;
  }

  /** retrieve the current monitor name from the active tab name.
   * @return false|string
   */
  private function getMonitorName() {
    /* See https://wordpress.org/support/topic/when-naming-inpagetabs-with-variables-how-can-i-use-content_pageslug/#post-14924022 */
    $tab = $this->oProp->getCurrentTabSlug();
    $pos = strrpos( $tab, $this->tabSuffix );
    if ( $pos !== strlen( $tab ) - strlen( $this->tabSuffix ) ) {
      return false;
    }
    $monitor = substr( $tab, 0, $pos );
    if ( ! in_array( $monitor, $this->monitors ) ) {
      return false;
    }

    return $monitor;
  }

  /** Edit the APF header HTML to stick in a HELP tab.
   *
   * @param $monitor
   * @param string $sHTML
   *
   * @return string
   */
  private function insertHelpTab( $monitor, $sHTML ) {
    $tabSlug = $monitor ? 'monitor' : $this->oProp->getCurrentTabSlug();
    $helpUrl = index_wp_mysql_for_speed_help_site . $tabSlug;
    $help    = __( 'Help', 'index-wp-mysql-for-speed' );
    /** @noinspection HtmlUnknownTarget */
    $helpTag = '<a class="helpbutton nav-tab" target="_blank" href="%s">%s</a>';
    $helpTag = sprintf( $helpTag, $helpUrl, $help );

    $delimiter = '<a class=';
    $splits    = explode( $delimiter, $sHTML, 2 );

    return $splits[0] . $helpTag . $delimiter . $splits[1];
  }

  /**
   * present a saved monitor
   *
   * @param string $monitor
   * @param string $part 'top'   or 'bottom'
   *
   * @return string
   */
  private function renderMonitor( $monitor, $part ) {
    $this->enqueueStyles(
      [
        plugins_url( 'assets/datatables/datatables.min.css', __FILE__ ),
      ], 'imfs_settings' );
    $this->enqueueScripts(
      [
        plugins_url( 'assets/datatables/datatables.min.js', __FILE__ ),
        plugins_url( 'assets/imfs.js', __FILE__ ),
      ], 'imfs_settings' );

    return RenderMonitor::renderMonitors( $monitor, $part, $this->db );
  }

  /** Render top of panel
   *
   * @param string $sHTML
   *
   * @return string
   * @callback  action content_{position}_{page slug}
   */
  public function content_top_imfs_settings_high_performance_keys( $sHTML ) {

    return $sHTML . '<div class="index-wp-mysql-for-speed-content-container">' . $this->wpCliAdmonition() . '</div>';
  }

  /** Get header information about wp-cli
   *
   * @return string
   */
  public function wpCliAdmonition() {
    /** @noinspection HtmlUnknownTarget */
    $wpCliUrl = '<a href="https://make.wordpress.org/cli/handbook/">WP-CLI</a>';

    /* translators: 1: hyperlink to https://make.wordpress.org/cli/handbook/ with text WP-CLI */
    $wpCliString = '<p class="topinfo">' . __( 'This plugin supports %1$s. <em>Please use it if possible</em>: it avoids web server timeouts when changing keys on large tables.', 'index-wp-mysql-for-speed' );
    $wpCliString = sprintf( $wpCliString, $wpCliUrl );
    $wpCliString .= ' ' . __( 'To learn more, type', 'index-wp-mysql-for-speed' ) . ' ' . '<code>wp help index-mysql</code>' . __( 'into your command shell.', 'index-wp-mysql-for-speed' ) . '</p>';

    return $wpCliString;
  }

  /** Render stuff at the bottom as needed. if the current tab is a monitor, render the data
   *
   * @param string $sHTML
   *
   * @return string
   * @callback  action content_{position}_{page slug}_{tab_slug}
   */
  public
  function content_bottom_imfs_settings(
    $sHTML
  ) {
    $s = '';
    /* renderMointor doesn't return anything unless we're on a monitor tab */
    $monitor = $this->getMonitorName();
    if ( $monitor !== false ) {
      $s .= $this->renderMonitor( $monitor, 'bottom' );
    }

    return $sHTML . $s;
  }

  /** render informational content at the top of the About tab
   *
   * @param string $sHTML
   *
   * @return string
   * @callback  action content_{position}_{page slug}_{tab_slug}
   */
  public
  function content_top_imfs_settings_about(
    $sHTML
  ) {
    /** @noinspection HtmlUnknownTarget */
    $hyperlink    = '<a href="%s" target="_blank">%s</a>';
    $supportUrl   = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/";
    $helpUrl      = index_wp_mysql_for_speed_help_site;
    $reviewUrl    = "https://wordpress.org/support/plugin/index-wp-mysql-for-speed/reviews/";
    $detailsUrl   = index_wp_mysql_for_speed_help_site . "tables_and_keys/";
    $clickHere    = __( 'click here', 'index-wp-mysql-for-speed' );
    $orUseHelpTab = __( 'or use the Help tab in the upper left corner of this page.' );
    $help         = sprintf( $hyperlink, $helpUrl, $clickHere ) . ' ' . $orUseHelpTab;
    $support      = sprintf( $hyperlink, $supportUrl, $clickHere );
    $review       = sprintf( $hyperlink, $reviewUrl, $clickHere );
    $details      = sprintf( $hyperlink, $detailsUrl, $clickHere );
    /* translators: 1: how to get help: made from translatable 'click here'  and 'or use the Help tab...' strings, */
    $helpString = '<p class="topinfo">' . __( 'For help please %1$s.', 'index-wp-mysql-for-speed' ) . '</p>';
    $helpString = sprintf( $helpString, $help );
    /* translators: 1: embeds "For help please ..."  2: hyperlink to review page on wp.org */
    $supportString = '<p class="topinfo">' . __( 'For support please %1$s. If you create an issue in the support forum, please upload your diagnostic metadata, and mention the id of your upload.  Please %2$s to rate this plugin.', 'index-wp-mysql-for-speed' ) . '</p>';
    $supportString = sprintf( $supportString, $support, $review );
    /* translators: 1: hyperlink to online details page, including the Click Here text. */
    $detailsString = '<p class="topinfo">' . __( 'For detailed information about this plugin\'s actions on your database, please %1$s.', 'index-wp-mysql-for-speed' ) . '</p>';
    $detailsString = sprintf( $detailsString, $details );
    $wpCliString   = $this->wpCliAdmonition();

    return $sHTML . '<div class="index-wp-mysql-for-speed-content-container">' . $helpString . $supportString . $detailsString . $wpCliString . '</div>';
  }

  /** Render the form in the rekey tab
   *
   * @param object $oAdminPage
   *
   * @callback  action validation_{page slug}_{tab_slug}
   * @noinspection PhpUnusedParameterInspection
   */
  public function load_imfs_settings_high_performance_keys( $oAdminPage ) {
    global $wp_version, $wp_db_version;

    if ( $this->checkVersionInfo() ) {

      $this->upgrading( $oAdminPage );
      $rekeying = $this->db->getRekeying();

      $this->showIndexStatus( $rekeying );

      $this->addSettingFields(
        [
          'field_id' => 'actionmessage',
          'title'    => __( 'Actions', 'index-wp-mysql-for-speed' ),
          'default'  => __( 'Actions you can take on your tables.', 'index-wp-mysql-for-speed' ),
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'header' ],
          ],
        ] );


      $this->addSettingFields(

        [
          'field_id' => 'backup',
          'title'    => __( 'Backup', 'index-wp-mysql-for-speed' ),
          'label'    => __( 'This plugin modifies your WordPress database. Make a backup before you proceed.', 'index-wp-mysql-for-speed' ),
          'save'     => false,
          'class'    => [
            'fieldrow' => 'info',
          ],
          [
            'field_id' => 'backup_done',
            'type'     => 'checkbox',
            'label'    => __( 'I have made a backup', 'index-wp-mysql-for-speed' ),
            'default'  => 0,
            'save'     => false,
            'class'    => [
              'fieldrow' => 'major',
            ],

          ],
        ]
      );
      /* engine upgrade ***************************/
      $this->upgradeIndex();

      /* rekeying ***************************/
      $action = 'enable';
      if ( count( $rekeying[ $action ] ) > 0 ) {
        $title        = __( 'Add keys', 'index-wp-mysql-for-speed' );
        $caption      = __( 'Add high-performance keys', 'index-wp-mysql-for-speed' );
        $callToAction = __( 'Add Keys Now', 'index-wp-mysql-for-speed' );
        $this->renderListOfTables( $rekeying[ $action ], false, $action, $action, $title, $caption, $callToAction, true );
      }
      /* updating old versions of keys  ***************************/
      $action = 'old';
      if ( count( $rekeying[ $action ] ) > 0 ) {

        $title        = __( 'Update keys', 'index-wp-mysql-for-speed' );
        $caption      = __( 'Update keys to this plugin\'s latest version', 'index-wp-mysql-for-speed' );
        $callToAction = __( 'Update Keys Now', 'index-wp-mysql-for-speed' );
        $this->renderListOfTables( $rekeying[ $action ], false, $action, 'enable', $title, $caption, $callToAction, true );
      }
      /* converting nonstandard keys  ***************************/
      $action = 'nonstandard';
      if ( count( $rekeying[ $action ] ) > 0 ) {

        $title        = __( 'Convert keys', 'index-wp-mysql-for-speed' );
        $caption      = __( 'Convert to this plugin\'s high-performance keys', 'index-wp-mysql-for-speed' );
        $callToAction = __( 'Convert Keys Now', 'index-wp-mysql-for-speed' );
        $this->renderListOfTables( $rekeying[ $action ], false, $action, 'enable', $title, $caption, $callToAction, true );
      }
      /* disabling  ***************************/
      $action = 'disable';
      if ( count( $rekeying[ $action ] ) > 0 ) {

        $title        = __( 'Revert keys', 'index-wp-mysql-for-speed' );
        $caption      = __( 'Revert to WordPress\'s default keys', 'index-wp-mysql-for-speed' );
        $callToAction = __( 'Revert Keys Now', 'index-wp-mysql-for-speed' );
        $this->renderListOfTables( $rekeying[ $action ], false, $action, $action, $title, $caption, $callToAction, false );
      }
    }
    $this->showVersionInfo();
  }

  /** Make sure our MySQL version is sufficient to do all this.
   * @return bool
   */
  private
  function checkVersionInfo() {

    if ( ! $this->db->canReindex ) {
      $this->addSettingFields(
        [
          'field_id'    => 'version_error',
          'title'       => 'Notice',
          'default'     => __( 'Sorry, you cannot use this plugin with your version of MySQL.', 'index-wp-mysql-for-speed' ),
          'description' => __( 'Your MySQL version is outdated. Please consider upgrading,', 'index-wp-mysql-for-speed' ),
          'save'        => false,
          'class'       => [
            'fieldrow' => 'failure',
          ],
        ] );
    } else {
      if ( ! $this->db->unconstrained ) {
        $this->addSettingFields(
          [
            'field_id' => 'constraint_notice',
            'title'    => 'Notice',
            'default'  => __( 'Upgrading your MySQL server version will give you better performance when you add high-performance keys. Please consider doing that before you add these keys.', 'index-wp-mysql-for-speed' ),
            'save'     => false,
            'class'    => [
              'fieldrow' => 'warning',
            ],
          ] );
      }
    }

    return $this->db->canReindex;
  }

  /**  check whether upgrading
   *
   * @param $oAdminPage
   *
   * @return void
   */
  private function upgrading( $oAdminPage ) {
    global $wp_version, $wp_db_version;
    /* stash the current versions of things in the options key. */
    $optName = $oAdminPage->oProp->sOptionKey;
    $opts    = get_option( $optName );
    if ( ! $opts ) {
      $opts = [];
    }
    $previousMajorVersion = ( isset( $opts['majorVersion'] ) && is_numeric( $opts['majorVersion'] ) )
      ? floatval( $opts['majorVersion'] ) : index_mysql_for_speed_inception_major_version;
    $previousWpVersion    = ( isset( $opts['wp_version'] ) ) ? $opts['wp_version'] : index_mysql_for_speed_inception_wp_version;
    $previousDbVersion    = ( isset( $opts['wp_db_version'] ) ) ? $opts['wp_db_version'] : index_mysql_for_speed_inception_wp_db_version;

    $this->pluginUpgrading = $previousMajorVersion !== index_mysql_for_speed_major_version;
    $this->wpDbUpgrading   = $previousDbVersion !== $wp_db_version;

    $opts['majorVersion']  = index_mysql_for_speed_major_version;
    $opts['wp_version']    = $wp_version;
    $opts['wp_db_version'] = $wp_db_version;

    update_option( $optName, $opts );
    /* stash the versions to help with updates */
    $this->addSettingFields(
      [
        'field_id' => 'majorVersion',
        'value'    => index_mysql_for_speed_major_version,
        'type'     => 'hidden',
        'save'     => true,
      ] );
    $this->addSettingFields(
      [
        'field_id' => 'wp_version',
        'value'    => $wp_version,
        'type'     => 'hidden',
        'save'     => true,
      ] );
    $this->addSettingFields(
      [
        'field_id' => 'wp_db_version',
        'value'    => $wp_db_version,
        'type'     => 'hidden',
        'save'     => true,
      ] );
  }

  /** present a list of tables with their indexing status.
   *
   * @param array $rekeying
   */
  private
  function showIndexStatus(
    array $rekeying
  ) {
    global $wpdb;
    $messageNumber = 0;
    /* display current status */
    if ( is_array( $rekeying['upgrade'] ) && count( $rekeying['upgrade'] ) > 0 ) {
      $list  = implode( ', ', $rekeying['upgrade'] );
      $label = __( 'These database tables need upgrading to MySQL\'s latest table storage format, InnoDB with dynamic rows.', 'index-wp-mysql-for-speed' );
      $label .= '<p class="tablelist">' . $list . '</p>';
      $this->addSettingFields(
        [
          'field_id' => 'message' . $messageNumber ++,
          'title'    => __( 'Tables to upgrade', 'index-wp-mysql-for-speed' ),
          'default'  => $label,
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'warning' ],
          ],
        ] );
    }
    if ( count( $rekeying['fast'] ) > 0 ) {
      $list = [];
      foreach ( $rekeying['fast'] as $tbl ) {
        $list[] = $wpdb->prefix . $tbl;
      }
      $list  = implode( ', ', $list );
      $label = __( 'You have added high-performance keys to these tables. You can revert them to WordPress\'s standard keys.', 'index-wp-mysql-for-speed' );
      $label .= '<p class="tablelist">' . $list . '</p>';
      $this->addSettingFields(
        [
          'field_id' => 'message' . $messageNumber ++,
          'title'    => __( 'Success', 'index-wp-mysql-for-speed' ),
          'default'  => $label,
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'success', 'header' ],
          ],
        ] );
    }
    if ( count( $rekeying['old'] ) > 0 ) {
      $list = [];
      foreach ( $rekeying['old'] as $tbl ) {
        $list[] = $wpdb->prefix . $tbl;
      }
      $list  = implode( ', ', $list );
      $label = __( 'You have added high-performance keys to your tables using an earlier version of this plugin. You can revert them to WordPress\'s standard keys, or update them to the latest high-performance keys.', 'index-wp-mysql-for-speed' );
      $label .= '<p class="tablelist">' . $list . '</p>';

      $this->addSettingFields(
        [
          'field_id' => 'message' . $messageNumber ++,
          'title'    => __( 'Keys to update', 'index-wp-mysql-for-speed' ),
          'default'  => $label,
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'header' ],
          ],
        ] );
    }
    if ( count( $rekeying['enable'] ) > 0 ) {
      $list = [];
      foreach ( $rekeying['enable'] as $tbl ) {
        $list[] = $wpdb->prefix . $tbl;
      }
      $list  = implode( ', ', $list );
      $label = __( 'These tables have WordPress\'s standard keys. You can add high-performance keys to these tables to make your WordPress database faster.', 'index-wp-mysql-for-speed' );
      $label .= '<p class="tablelist">' . $list . '</p>';

      /** @noinspection PhpUnusedLocalVariableInspection */
      $this->addSettingFields(
        [
          'field_id' => 'message' . $messageNumber ++,
          'title'    => __( 'Keys to add', 'index-wp-mysql-for-speed' ),
          'default'  => $label,
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'header' ],
          ],
        ] );
    }

    if ( count( $rekeying['nonstandard'] ) > 0 ) {
      $list = [];
      foreach ( $rekeying['nonstandard'] as $tbl ) {
        $list[] = $wpdb->prefix . $tbl;
      }
      $list  = implode( ', ', $list );
      $label = $this->wpDbUpgrading
        ? __( 'A recent WordPress update changed some keys in some tables.', 'index-wp-mysql-for-speed' )
        : __( 'These tables have keys set some way other than this plugin.', 'index-wp-mysql-for-speed' );
      $label .= ' ' . __( 'You can convert those tables to this plugin\'s latest high-performance keys or revert them to WordPress\'s standard keys.', 'index-wp-mysql-for-speed' );
      $label .= '<p class="tablelist">' . $list . '</p>';

      /** @noinspection PhpUnusedLocalVariableInspection */
      $this->addSettingFields(
        [
          'field_id' => 'message' . $messageNumber ++,
          'title'    => __( 'Keys to convert', 'index-wp-mysql-for-speed' ),
          'default'  => $label,
          'save'     => false,
          'class'    => [
            'fieldrow' => [ 'major', 'header' ],
          ],
        ] );
    }

  }

  /**
   * form for upgrading tables to InnoDB
   */
  private
  function upgradeIndex() {
    if ( count( $this->db->oldEngineTables ) > 0 ) {
      $action       = 'upgrade';
      $title        = '<span class="warning header">' . __( 'Upgrade tables', 'index-wp-mysql-for-speed' ) . '</span>';
      $caption      = __( 'Upgrade table storage format', 'index-wp-mysql-for-speed' );
      $callToAction = __( 'Upgrade Storage Now', 'index-wp-mysql-for-speed' );
      $this->renderListOfTables( $this->db->oldEngineTables, true, $action, $action, $title, $caption, $callToAction, true );
    }
  }

  /** draw a list of tables with checkboxes and controls
   *
   * @param array $tablesToRekey
   * @param bool $prefixed true if $tablesToRekey contains wp_foometa not just foometa
   * @param string $action "enable", "disable", "old" or "revert"
   * @param string $actionToDisplay "enable", "disable", "revert"
   * @param string $title
   * @param string $caption
   * @param string $callToAction button caption
   * @param bool $prechecked items should be prechecked
   */
  private
  function renderListOfTables(
    array $tablesToRekey, $prefixed, $action, $actionToDisplay, $title,
    $caption, $callToAction, $prechecked
  ) {

    global $wpdb;
    $this->addSettingFields(
      [
        'field_id' => $action . 'caption',
        'title'    => $title,
        'default'  => $caption,
        'save'     => false,
        'class'    => [
          'fieldrow' => 'major',
        ],
      ]
    );

    $labels    = [];
    $defaults  = [];
    $tableList = [];
    $prefix    = $prefixed ? '' : $wpdb->prefix;
    foreach ( $tablesToRekey as $tbl ) {
      $unprefixed = ImfsQueries::stripPrefix( $tbl );
      $rowcount   = - 1;
      if ( array_key_exists( $tbl, $this->db->tableCounts ) ) {
        $rowcount = $this->db->tableCounts[ $tbl ]->count;
      } else if ( array_key_exists( $unprefixed, $this->db->tableCounts ) ) {
        $rowcount = $this->db->tableCounts[ $unprefixed ]->count;
      }
      if ( $rowcount > 1 ) {
        $rowcount   = number_format_i18n( $rowcount );
        $itemString = $rowcount . ' ' . __( 'rows, approximately', 'index-wp-mysql-for-speed' );
      } else if ( $rowcount == 1 ) {
        $itemString = $rowcount . ' ' . __( 'row, approximately', 'index-wp-mysql-for-speed' );
      } else if ( $rowcount == 0 ) {
        $itemString = __( 'no rows', 'index-wp-mysql-for-speed' );
      } else {
        $itemString = '';
      }
      if ( strlen( $itemString ) > 0 ) {
        $itemString = ' (' . $itemString . ')';
      }
      $tableList[]      = $prefix . $tbl;
      $labels[ $tbl ]   = $prefix . $tbl . $itemString;
      $defaults[ $tbl ] = $prechecked;
    }

    $this->addSettingFields(
      [
        'field_id'           => $action,
        'type'               => 'checkbox',
        'label'              => $labels,
        'default'            => $defaults,
        'save'               => false,
        'after_label'        => '<br />',
        'select_all_button'  => true,
        'select_none_button' => true,
      ]
    );

    $this->addSettingFields(
      [
        'field_id'    => $action . '_now',
        'type'        => 'submit',
        'save'        => false,
        'value'       => $callToAction,
        'description' => $this->dontNavigate,
        'class'       => [
          'fieldrow' => 'action',
        ],
      ],
      [
        'field_id' => $action . '_wp',
        'label'    => $this->cliMessage(
          $actionToDisplay . ' ' . implode( ' ', $tableList ),
          __( $title, 'index-wp-mysql-for-speed' ) ),
        'save'     => false,
        'class'    => [
          'fieldrow' => 'info',
        ],
      ]
    );
  }

  /** text string with wp cli instrutions
   *
   * @param string $command cli command string
   * @param string $function description of function to carry out
   *
   * @return string
   */
  private
  function cliMessage(
    $command, $function
  ) {
    //$cliLink = ' <a href="https://make.wordpress.org/cli/handbook/" target="_blank">WP-CLI</a>';
    $cliLink = ' WP-CLI';
    $wp      = 'wp index-mysql';
    $blogid  = get_current_blog_id();
    if ( $blogid > 1 ) {
      $wp .= ' ' . '--blogid=' . $blogid;
    }
    /* translators: %1$s is WP-CLI hyperlink, %2s is 'wp index-mysql',  %3$s describes the function, %4$s is the cli commmand */
    $fmt = __( 'Using %1$s, %2$s: <code>%3$s %4$s</code>', 'index-wp-mysql-for-speed' );

    return sprintf( $fmt, $cliLink, $function, $wp, $command );
  }

  /**
   * text field showing versions
   */
  private
  function showVersionInfo() {
    global $wp_version;
    global $wp_db_version;
    $versionString = 'Plugin:' . index_wp_mysql_for_speed_VERSION_NUM
                     . '&ensp;MySQL:' . htmlspecialchars( $this->db->semver->version )
                     . '&ensp;WordPress:' . $wp_version
                     . '&ensp;WordPress database:' . $wp_db_version
                     . '&ensp;php:' . phpversion();
    $this->addSettingFields(
      [
        'field_id' => 'version',
        'title'    => __( 'Versions', 'index-wp-mysql-for-speed' ),
        'default'  => $versionString,
        'save'     => false,
        'class'    => [
          'fieldrow' => 'info',
        ],
      ]
    );
  }

  /** @noinspection PhpUnused */

  /** Render the Monitor Database Operations form
   *
   * @param $oAdminPage
   *
   * @callback  action validation_{page slug}_{tab_slug}
   * @noinspection PhpUnusedParameterInspection
   */
  public
  function load_imfs_settings_monitor_database_operations(
    $oAdminPage
  ) {

    /* translators:  1: a percentage number--50,20,10,5,2,1 */
    $sampleText  = __( 'sampling %1$d%% of pageviews.', 'index-wp-mysql-for-speed' );
    $labelText   = [];
    $labelText[] = '<p class="longlabel">' . __( 'We can monitor your site\'s use of MySQL for a few minutes to help you understand what runs slowly.', 'index-wp-mysql-for-speed' );
    $labelText[] = __( 'To capture monitoring from your site, push the', 'index-wp-mysql-for-speed' );
    $labelText[] = __( 'Start Monitoring', 'index-wp-mysql-for-speed' );
    $labelText[] = __( 'button after choosing  a name for your monitor and the options you need.', 'index-wp-mysql-for-speed' ) . '</p>';
    $labelText[] = '<p class="longlabel">' . __( 'Then use your site and dashboard to do things that may be slow so the plugin can capture them.', 'index-wp-mysql-for-speed' );
    $labelText[] = __( 'While your monitor is active, the plugin captures database activity on your site,', 'index-wp-mysql-for-speed' );
    $labelText[] = __( 'both yours and other users\'.', 'index-wp-mysql-for-speed' ) . '</p>';
    $labelText[] = '<p class="longlabel">' . __( 'When the monitoring time ends, view your saved monitor to see your site\'s MySQL traffic and identify the slowest operations.', 'index-wp-mysql-for-speed' ) . '</p>';
    $labelText   = implode( ' ', $labelText );

    $this->addSettingFields(
      [
        'field_id' => 'monitoring_parameters',
        'title'    => __( 'Monitoring', 'index-wp-mysql-for-speed' ),
        'label'    => $labelText,
        'class'    => [
          'fieldrow' => 'info',
        ],
      ] );
    $this->addSettingFields(
      [
        'field_id' => 'monitor_specs',
        'type'     => 'inline_mixed',
        'content'  => [
          [
            'field_id' => 'targets',
            'type'     => 'select',
            'save'     => true,
            'default'  => 3,
            'label'    => [
              3 => __( 'Monitor Dashboard and Site', 'index-wp-mysql-for-speed' ),
              2 => __( 'Monitor Site Only', 'index-wp-mysql-for-speed' ),
              1 => __( 'Monitor Dashboard Only', 'index-wp-mysql-for-speed' ),
            ],
          ],
          [
            'field_id'        => 'duration',
            'type'            => 'number',
            'label_min_width' => '',
            'label'           => __( 'for', 'index-wp-mysql-for-speed' ),
            'save'            => true,
            'default'         => 5,
            'attributes'      => [
              'min' => 1,
            ],
            'class'           => [
              'fieldset' => 'inline',
              'fieldrow' => 'number',
            ],
          ],
          [
            'field_id' => 'duration_text_minutes',
            'label'    => __( 'minutes', 'index-wp-mysql-for-speed' ),
            'save'     => false,
          ],
          [
            'field_id'   => 'samplerate',
            'type'       => 'select',
            'save'       => true,
            'default'    => 100,
            'label'      => [
              100 => __( 'capturing all pageviews.', 'index-wp-mysql-for-speed' ),
              50  => sprintf( $sampleText, 50 ),
              20  => sprintf( $sampleText, 20 ),
              10  => sprintf( $sampleText, 10 ),
              5   => sprintf( $sampleText, 5 ),
              2   => sprintf( $sampleText, 2 ),
              1   => sprintf( $sampleText, 1 ),
            ],
            'attributes' => [
              'title' => __( 'If your site is very busy, chooose a lower sample rate.', 'index-wp-mysql-for-speed' ),
            ],
          ],
          [
            'field_id' => 'name',
            'type'     => 'text',
            'label'    => __( 'Save into', 'index-wp-mysql-for-speed' ),
            'save'     => true,
            'default'  => 'monitor',
            'class'    => [
              'fieldset' => 'inline',
              'fieldrow' => 'name',
            ],
          ],
        ],
      ] );

    $this->addSettingFields(
      [
        'field_id' => 'monitoring_starter',
        'label'    => __( 'Monitoring stops automatically.', 'index-wp-mysql-for-speed' ),
        'save'     => false,
        'class'    => [
          'fieldrow' => 'info',
        ],
        [
          'field_id' => 'start_monitoring_now',
          'type'     => 'submit',
          'save'     => false,
          'value'    => __( 'Start Monitoring', 'index-wp-mysql-for-speed' ),
          'class'    => [
            'fieldrow' => 'action',
          ],
        ],
//	 TODO add wp cli for monitoring
//              array(
//					'label' => $this->cliMessage( 'monitor --minutes=n', __( 'Monitor', 'index-wp-mysql-for-speed' ) ),
//					'type'  => 'label',
//					'save'  => false,
//					'class' => array(
//						'fieldrow' => 'info',
//					),
//				),

      ]
    );

    $monLabel = count( $this->monitors ) > 0
      ? __( 'Saved monitors', 'index-wp-mysql-for-speed' )
      : __( 'No monitors are saved. ', 'index-wp-mysql-for-speed' );

    $this->addSettingFields(
      [
        'field_id' => 'monitor_headers',
        'title'    => __( 'Monitors', 'index-wp-mysql-for-speed' ),
        'label'    => $monLabel,
        'save'     => false,
        'class'    => [
          'fieldrow' => 'info',
        ],
      ] );


    foreach ( $this->monitors as $monitor ) {
      $log     = new RenderMonitor( $monitor, $this->db );
      $summary = $log->load()->capturedQuerySummary();
      /** @noinspection HtmlUnknownTarget */
      $monitorText = sprintf( "<a href=\"%s&tab=%s%s\">%s</a> %s",
        admin_url( 'tools.php?page=imfs_settings' ), $monitor, $this->tabSuffix, $monitor, $summary );
      $this->addSettingFields(
        [
          'field_id' => 'monitor_row_' . $monitor,
          'type'     => 'inline_mixed',
          'content'  => [
            [
              'field_id'   => 'delete_' . $monitor . '_now',
              'type'       => 'submit',
              'save'       => false,
              'value'      => 'X',
              'tip'        => __( 'Delete', 'index-wp-mysql-for-speed' ) . ' ' . $monitor,
              'attributes' => [
                'class' => 'button button_secondary button_delete button_round',
                'title' => __( 'Delete', 'index-wp-mysql-for-speed' ) . ' ' . $monitor,
              ],
            ],

            [
              'field_id' => $monitor . '_title',
              'default'  => $monitorText,
              'save'     => false,
              'class'    => [
                'fieldrow' => 'info',
              ],
            ],
          ],
        ] );
    }
    $this->showVersionInfo();
  }

  /** @noinspection PhpUnused */

  /** Render the About form (info tab)
   *
   * @param $oAdminPage
   *
   * @callback  action load_{page slug}_{tab_slug}
   * @noinspection PhpUnusedParameterInspection
   */
  public
  function load_imfs_settings_about(
    $oAdminPage
  ) {
    if ( ! $this->db->unconstrained ) {
      $this->addSettingFields(
        [
          'field_id' => 'constraint_notice',
          'title'    => 'Notice',
          'default'  => __( 'Upgrading your MySQL server version will give you better performance when you add high-performance keys.', 'index-wp-mysql-for-speed' ),
          'save'     => false,
          'class'    => [
            'fieldrow' => 'warning',
          ],
        ] );
    }

    $this->showIndexStatus( $this->db->getRekeying() );
    $this->uploadMetadata();
    $this->showVersionInfo();
  }

  /** @noinspection PhpUnused */

  /**
   * render the upload-metadata form fields.
   */
  function uploadMetadata() {
    $this->addSettingFields(
      [
        'field_id' => 'metadata',
        'title'    => __( 'Diagnostic data', 'index-wp-mysql-for-speed' ),
        'label'    => __( 'With your permission we upload metadata about your WordPress site to our plugin\'s servers. We cannot identify you or your website from it, and we never sell nor give it to any third party. We use it only to improve this plugin.', 'index-wp-mysql-for-speed' ),
        'save'     => false,
        'class'    => [
          'fieldrow' => 'info',
        ],
      ],
      [
        'field_id' => 'uploadId',
        'title'    => __( 'Upload id', 'index-wp-mysql-for-speed' ),
        'label'    => __( 'If you create an issue or contact the authors, please mention this upload id.', 'index-wp-mysql-for-speed' ),
        'type'     => 'text',
        'save'     => true,
        'default'  => ImfsQueries::getRandomString( 8 ),
        'class'    => [
          'fieldrow' => 'randomid',
        ],
      ],
      [
        'field_id'    => 'upload_metadata_now',
        'type'        => 'submit',
        'save'        => false,
        'value'       => __( 'Upload metadata', 'index-wp-mysql-for-speed' ),
        'description' => $this->dontNavigate,
        'class'       => [
          'fieldrow' => 'action',
        ],
      ],
      [
        'label' => $this->cliMessage( 'upload_metadata', __( 'Upload metadata', 'index-wp-mysql-for-speed' ) ),
        'type'  => 'label',
        'save'  => false,
        'class' => [
          'fieldrow' => 'info',
        ],
      ]
    );
  }

  /** load overall page, used to load monitor items (with variable slug names)
   *
   * @param $oAdminPage
   *
   * @callback  action load_{page slug}
   * @noinspection PhpUnusedParameterInspection
   */
  public
  function load_imfs_settings(
    $oAdminPage
  ) {
    try {
      $this->populate();
    } catch ( ImfsException $ex ) {
      $msg = __( 'Something went wrong inspecting your database', 'index-wp-mysql-for-speed' ) . ': ' . $ex->getMessage();
      $this->setSettingNotice( $msg );

      return;
    }

    $monitor = $this->getMonitorName();
    if ( $monitor === false ) {
      return;
    }
    $this->populate_monitor_fields( $monitor );
  }

  /**
   * @throws ImfsException
   */
  private
  function populate() {

    $this->db->init();
    $this->canReindex    = $this->db->canReindex;
    $this->unconstrained = $this->db->unconstrained;
  }

  private function populate_monitor_fields( $monitor ) {

    $uploadId = Imfs_AdminPageFramework::getOption( get_class( $this ), 'uploadId', ImfsQueries::getRandomString( 8 ) );
    $this->addSettingFields(
      [
        'field_id' => 'monitor_actions',
        'type'     => 'inline_mixed',
        'content'  => [
          [
            'field_id'   => 'upload_' . $monitor . '_now',
            'type'       => 'submit',
            'save'       => false,
            'value'      => __( 'Upload ', 'index-wp-mysql-for-speed' ),
            'attributes' => [
              'class' => 'button button_secondary',
              'title' => __( 'Upload this monitor to the plugin\'s servers', 'index-wp-mysql-for-speed' ),
            ],
            'class'      => [
              'fieldset' => 'inline-buttons-and-text',
            ],
          ],
          [
            'field_id' => 'uploadId',
            'type'     => 'text',
            'save'     => true,
            'label'    => __( 'this saved monitor to the plugin\'s servers using upload id', 'index-wp-mysql-for-speed' ),
            'default'  => $uploadId,
            'class'    => [
              'fieldset' => 'inline-buttons-and-text',
            ],
          ],
        ],
      ] );
  }

  /** Generic validation routine, only used for monitor tabs with varying names
   *
   * @param $inputs
   * @param $oldInputs
   * @param $oAdminPage
   * @param $submitInfo
   *
   * @return mixed  updated $inputs
   * @callback  filter validation_{page slug}
   * @noinspection PhpUnusedParameterInspection
   */
  function validation_imfs_settings( $inputs, $oldInputs, $oAdminPage, $submitInfo ) {
    $errors  = [];
    $monitor = $this->getMonitorName();

    /* submit from monitor tab? */
    if ( $monitor !== false && isset( $inputs['monitor_actions'] ) ) {
      $button = $submitInfo ['field_id'];
      if ( $button === 'upload_' . $monitor . '_now' ) {
        /* It's the upload button. Check the uploadId */
        if ( ! isset( $inputs['monitor_actions']['uploadId'] ) || strlen( $inputs['monitor_actions']['uploadId'] ) === 0 ) {
          /* reject the bogus uploadId */
          $errors['monitor_actions']['uploadId'] = __( "Please provide an upload id.", 'index-wp-mysql-for-speed' );
          $this->setFieldErrors( $errors );
          $this->setSettingNotice( __( 'Make corrections and try again.', 'index-wp-mysql-for-speed' ) );

          return $oldInputs;
        }
        /* put the uploadId at the top level of the stored options */
        $uploadId = $inputs['monitor_actions']['uploadId'];
        unset ( $inputs['monitor_actions'] );
        $inputs ['uploadId'] = $uploadId;

        return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $oAdminPage, $submitInfo );

      }
    }

    return $inputs;
  }

  /** @noinspection PhpUnusedParameterInspection */

  private
  function action(
    $button, $inputs, $oldInputs, $factory, $submitInfo
  ) {

    $monitor = $this->getMonitorName();
    if ( $monitor !== false && $button === 'upload_' . $monitor . '_now' ) {
      $button = 'upload_monitor_now';
    }
    try {
      switch ( $button ) {
        case 'start_monitoring_now':
          $qmc     = new QueryMonControl();
          $message = $qmc->start( $inputs['monitor_specs'], $this->db );
          $this->setSettingNotice( $message, 'updated' );
          break;
        case 'upgrade_now':
          $msg = $this->db->upgradeTableStorageEngines( $this->listFromCheckboxes( $inputs['upgrade'] ) );
          $this->setSettingNotice( $msg, 'updated' );
          break;
        case 'enable_now':
          $msg = $this->db->rekeyTables( 1, $this->listFromCheckboxes( $inputs['enable'] ), index_mysql_for_speed_major_version );
          $this->setSettingNotice( $msg, 'updated' );
          break;
        case 'old_now':
          $msg = $this->db->rekeyTables( 1, $this->listFromCheckboxes( $inputs['old'] ), index_mysql_for_speed_major_version );
          $this->setSettingNotice( $msg, 'updated' );
          break;
        case 'nonstandard_now':
          $msg = $this->db->rekeyTables( 1, $this->listFromCheckboxes( $inputs['nonstandard'] ), index_mysql_for_speed_major_version );
          $this->setSettingNotice( $msg, 'updated' );
          break;
        case 'disable_now':
          $msg = $this->db->rekeyTables( 0, $this->listFromCheckboxes( $inputs['disable'] ), index_mysql_for_speed_major_version );
          $this->setSettingNotice( $msg, 'updated' );
          break;
        case 'upload_metadata_now':
          $id = imfs_upload_stats( $this->db, $inputs['uploadId'] );
          $this->setSettingNotice( __( 'Metadata uploaded to id ', 'index-wp-mysql-for-speed' ) . $id, 'updated' );
          break;
        case 'upload_monitor_now':
          $mon  = new renderMonitor( $monitor, $this->db );
          $data = $mon->load()->makeUpload();
          $id   = imfs_upload_monitor( $this->db, $inputs['uploadId'], $monitor, $data );
          /* translators: 1: name of captured monitor.  2: upload id */
          $msg = __( 'Monitor %1$s uploaded to id %2$s', 'index-wp-mysql-for-speed' );
          $msg = sprintf( $msg, $monitor, $id );
          $this->setSettingNotice( $msg, 'updated' );
          break;
      }

      return $inputs;
    } catch ( ImfsException $ex ) {
      $msg = $ex->getMessage();
      $this->setSettingNotice( $msg );

      return $oldInputs;
    }
  }

  private
  function listFromCheckboxes(
    $cbs
  ) {
    $result = [];
    foreach ( $cbs as $name => $val ) {
      if ( $val ) {
        $result[] = $name;
      }
    }

    return $result;
  }

  /** Admin Page Framework validation for rekey tab
   *
   * @param $inputs
   * @param $oldInputs
   * @param $factory
   * @param $submitInfo
   *
   * @return mixed
   * @callback  action validation_{page slug}_{tab_slug}
   */
  function validation_imfs_settings_high_performance_keys( $inputs, $oldInputs, $factory, $submitInfo ) {

    $valid  = true;
    $errors = [];

    if ( ! isset ( $inputs['backup']['1'] ) || ! $inputs['backup']['1'] ) {
      $valid            = false;
      $errors['backup'] = __( 'Please acknowledge that you have made a backup.', 'index-wp-mysql-for-speed' );
    }

    $action = $submitInfo['field_id'];
    $err    = __( 'Please select at least one table.', 'index-wp-mysql-for-speed' );
    if ( $action === 'enable_now' ) {
      if ( count( $this->listFromCheckboxes( $inputs['enable'] ) ) === 0 ) {
        $valid            = false;
        $errors['enable'] = $err;
      }
    }
    if ( $action === 'disable_now' ) {
      if ( count( $this->listFromCheckboxes( $inputs['disable'] ) ) === 0 ) {
        $valid             = false;
        $errors['disable'] = $err;
      }
    }
    if ( $action === 'upgrade_now' ) {
      if ( count( $this->listFromCheckboxes( $inputs['upgrade'] ) ) === 0 ) {
        $valid             = false;
        $errors['upgrade'] = $err;
      }
    }

    if ( ! $valid ) {
      $this->setFieldErrors( $errors );
      $this->setSettingNotice( __( 'Make corrections and try again.', 'index-wp-mysql-for-speed' ) );

      return $oldInputs;
    }

    return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
  }

  /**
   * @param $inputs
   * @param $oldInputs
   * @param $factory
   * @param $submitInfo
   *
   * @return mixed
   * @noinspection PhpUnused
   * @callback  action validation_{page slug}_{tab_slug}
   */
  function validation_imfs_settings_monitor_database_operations( $inputs, $oldInputs, $factory, $submitInfo ) {
    $valid  = true;
    $errors = [];

    foreach ( $inputs as $key => $value ) {
      if ( 0 === strpos( $key, "monitor_row_" ) ) {
        foreach ( $value as $rowkey => $button ) {
          $monitor = preg_replace( "/^delete_(.+)_now$/", "$1", $rowkey );
          if ( in_array( $monitor, $this->monitors ) ) {
            RenderMonitor::deleteMonitor( $monitor );
            $this->monitors = RenderMonitor::getMonitors();
            /* translators: name of a captured monitor object */
            $message = __( 'Monitor %1$s deleted.', 'index-wp-mysql-for-speed' );
            $message = sprintf( $message, $monitor );
            $this->setSettingNotice( $message, 'updated' );

            return $oldInputs;
          }
        }
      }
    }

    if ( is_array( $submitInfo ) && $submitInfo['field_id'] === 'start_monitoring_now' ) {
      $monitor = $inputs['monitor_specs']['name'];
      if ( ctype_alnum( $monitor ) === false ) {
        $valid                   = false;
        $errors['monitor_specs'] = __( "Letters and numbers only for your monitor name, please.", 'index-wp-mysql-for-speed' );
      }
    }

    if ( ! $valid ) {
      $this->setFieldErrors( $errors );
      $this->setSettingNotice( __( 'Make corrections and try again.', 'index-wp-mysql-for-speed' ) );

      return $oldInputs;
    }

    return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
  }

  /**
   * @param $inputs
   * @param $oldInputs
   * @param $factory
   * @param $submitInfo
   *
   * @return mixed
   * @callback  action validation_{page slug}_{tab_slug}
   */
  function validation_imfs_settings_about( $inputs, $oldInputs, $factory, $submitInfo ) {
    $errors = [];
    if ( isset( $inputs['uploadId'] ) && strlen( $inputs['uploadId'] ) > 0 ) {
      $valid = true;
    } else {
      $errors['uploadId'] = __( "Please provide an upload id.", 'index-wp-mysql-for-speed' );
      $valid              = false;
    }
    if ( ! $valid ) {
      $this->setFieldErrors( $errors );
      $this->setSettingNotice( __( 'Make corrections and try again.', 'index-wp-mysql-for-speed' ) );

      return $oldInputs;
    }

    return $this->action( $submitInfo['field_id'], $inputs, $oldInputs, $factory, $submitInfo );
  }


}

new ImfsPage;