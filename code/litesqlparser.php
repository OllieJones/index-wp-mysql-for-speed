<?php

/**
 * Light SQL Parser Class
 * @author Marco Cesarato <cesarato.developer@gmail.com>, Ollie JOnes <olliejones@gmail.com>
 * @copyright Copyright (c) 2021
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link https://github.com/marcocesarato/PHP-Light-SQL-Parser-Class
 */
class LightSQLParser {
  // Public
  protected static $connectors = [
    'OR',
    'AND',
    'ON',
    'LIMIT',
    'WHERE',
    'JOIN',
    'GROUP',
    'ORDER',
    'OPTION',
    'LEFT',
    'INNER',
    'RIGHT',
    'OUTER',
    'SET',
    'HAVING',
    'VALUES',
    'SELECT',
    '\(',
    '\)',
  ];
  // Private
  protected static $connectors_imploded = '';
  public $query = '';
  protected $queries = [];
  private $stash;
  private $symbolizedStringDelimiter = "\e\036\e";
  private $quotedStringRe = <<<'END'
/'(?:.*?[^\\])??(?:(?:\\\\)+)?'/
END;
  private $stringLengthThreshold = 64;

  /**
   * Constructor
   */
  public function __construct( $query = '' ) {
    $this->setQuery( $query );
    if ( empty( self::$connectors_imploded ) ) {
      self::$connectors_imploded = implode( '|', self::$connectors );
    }
  }

  /**
   * Get Query fields (at the moment only SELECT/INSERT/UPDATE)
   *
   * @return array
   */
  public function getFields() {
    $fields  = [];
    $queries = $this->getAllQueries();
    foreach ( $queries as $query ) {
      $method = $this->getMethod();
      switch ( $method ) {
        case 'SELECT':
          preg_match( '#SELECT[\s]+([\S\s]*)[\s]+FROM#i', $query, $matches );
          if ( ! empty( $matches[1] ) ) {
            $match = trim( $matches[1] );
            $match = explode( ',', $match );
            foreach ( $match as $field ) {
              $field    = preg_replace( '#([\s]+(AS[\s]+)?[\w.]+)#i', '', trim( $field ) );
              $fields[] = $field;
            }
          }
          break;
        case 'INSERT':
          preg_match( '#INSERT[\s]+INTO[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)\(([\S\s]*)\)[\s]+VALUES#i', $query, $matches );
          if ( ! empty( $matches[4] ) ) {
            $match = trim( $matches[4] );
            $match = explode( ',', $match );
            foreach ( $match as $field ) {
              $field    = preg_replace( '#([\s]+(AS[\s]+)?[\w.]+)#i', '', trim( $field ) );
              $fields[] = $field;
            }
          } else {
            preg_match( '#INSERT[\s]+INTO[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)SET([\S\s]*)([;])?#i', $query, $matches );
            if ( ! empty( $matches[4] ) ) {
              $match = trim( $matches[4] );
              $match = explode( ',', $match );
              foreach ( $match as $field ) {
                $field    = preg_replace( '#([\s]*=[\s]*[\S\s]+)#i', '', trim( $field ) );
                $fields[] = $field;
              }
            }
          }
          break;
        case 'UPDATE':
          preg_match( '#UPDATE[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)SET([\S\s]*)([\s]+WHERE|[;])?#i', $query, $matches );
          if ( ! empty( $matches[4] ) ) {
            $match = trim( $matches[4] );
            $match = explode( ',', $match );
            foreach ( $match as $field ) {
              $field    = preg_replace( '#([\s]*=[\s]*[\S\s]+)#i', '', trim( $field ) );
              $fields[] = $field;
            }
          }
          break;
        case 'CREATE TABLE':
          preg_match( '#CREATE[\s]+TABLE[\s]+\w+[\s]+\(([\S\s]*)\)#i', $query, $matches );
          if ( ! empty( $matches[1] ) ) {
            $match = trim( $matches[1] );
            $match = explode( ',', $match );
            foreach ( $match as $_field ) {
              preg_match( '#^w+#', trim( $_field ), $field );
              if ( ! empty( $field[0] ) ) {
                $fields[] = $field[0];
              }
            }
          }
          break;
      }
    }

    return array_unique( $fields );
  }

  /**
   * Get all queries
   * @return array
   */
  public function getAllQueries() {
    if ( empty( $this->queries ) ) {
      // TODO: fix issues when on a subquery exists a UNION expression
      $query   = $this->getQuery();
      $query   = preg_replace( '#/\*[\s\S]*?\*/#', '', $query );
      $query   = preg_replace( '#;(?:(?<=["\'];)|(?=["\']))#', '', $query );
      $query   = preg_replace( '#[\s]*UNION([\s]+ALL)?[\s]*#', ';', $query );
      $queries = explode( ';', $query );
      foreach ( $queries as $key => $query ) {
        $this->queries[ $key ] = str_replace( [ '`', '"', "'" ], '', $query );
      }
    }

    return $this->queries;
  }

  /**
   * Get SQL Query string
   * @return string
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Set SQL Query string
   */
  public function setQuery( $query ) {
    $this->query   = $query;
    $this->queries = [];
    $this->stash   = [];

    return $this;
  }

  /**
   * Get SQL Query method
   * @return string
   */
  public function getMethod() {
    $methods = [
      'SELECT',
      'INSERT',
      'UPDATE',
      'DELETE',
      'RENAME',
      'SHOW',
      'SET',
      'DROP',
      'CREATE INDEX',
      'CREATE TABLE',
      'EXPLAIN',
      'DESCRIBE',
      'TRUNCATE',
      'ALTER',
    ];
    $queries = $this->getAllQueries();
    foreach ( $queries as $query ) {
      foreach ( $methods as $method ) {
        $_method = str_replace( ' ', '[\s]+', $method );
        if ( preg_match( '#^[\s]*' . $_method . '[\s]+#i', $query ) ) {
          return $method;
        }
      }
    }

    return '';
  }

  /**
   * Get SQL Query First Table
   *
   * @return string
   */
  public function getTable() {
    $tables = $this->getAllTables();

    return ( isset( $tables[0] ) ) ? $tables[0] : null;
  }

  /**
   * Get SQL Query Tables
   * @return array
   */
  function getAllTables() {
    $results = [];
    $queries = $this->getAllQueries();
    foreach ( $queries as $query ) {
      $patterns = [
        '#[\s]+FROM[\s]+(([\s]*(?!' . self::$connectors_imploded . ')[\w]+([\s]+(AS[\s]+)?(?!' . self::$connectors_imploded . ')[\w]+)?[\s]*[,]?)+)#i',
        '#[\s]*INSERT[\s]+INTO[\s]+([\w]+)#i',
        '#[\s]*UPDATE[\s]+([\w]+)#i',
        '#[\s]+JOIN[\s]+([\w]+)#i',
        '#[\s]+TABLE[\s]+([\w]+)#i',
        '#[\s]+TABLESPACE[\s]+([\w]+)#i',
      ];
      foreach ( $patterns as $pattern ) {
        preg_match_all( $pattern, $query, $matches, PREG_SET_ORDER );
        foreach ( $matches as $val ) {
          $tables = explode( ',', $val[1] );
          foreach ( $tables as $table ) {
            $table     = trim( preg_replace( '#[\s]+(AS[\s]+)[\w.]+#i', '', $table ) );
            $results[] = $table;
          }
        }
      }
    }

    return array_unique( $results );
  }

  /**
   * Join tables.
   * @return array
   */
  function getJoinTables() {
    $results = [];
    $queries = $this->getAllQueries();
    foreach ( $queries as $query ) {
      preg_match_all( '#[\s]+JOIN[\s]+([\w]+)#i', $query, $matches, PREG_SET_ORDER );
      foreach ( $matches as $val ) {
        $tables = explode( ',', $val[1] );
        foreach ( $tables as $table ) {
          $table     = trim( preg_replace( '#[\s]+(AS[\s]+)[\w.]+#i', '', $table ) );
          $results[] = $table;
        }
      }
    }

    return array_unique( $results );
  }

  /**
   * Has join tables.
   * @return bool
   */
  function hasJoin() {
    $queries = $this->getAllQueries();
    foreach ( $queries as $query ) {
      preg_match( '#[\s]+JOIN[\s]+([\w]+)#i', $query, $matches );
      if ( ! empty( $matches[1] ) ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Has SubQueries.
   * @return bool
   */
  function hasSubQuery() {
    $query = $this->getQuery();
    preg_match( '#\([\s]*(SELECT[^)]+)\)#i', $query, $matches );
    if ( ! empty( $matches[1] ) ) {
      return true;
    }

    return false;
  }
  /* quoted strings, with escapes processed correctly */

  /**
   * Join tables.
   * @return array
   */
  function getSubQueries() {
    $results = [];
    $query   = $this->getQuery();
    preg_match_all( '#\([\s]*(SELECT[^)]+)\)#i', $query, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) {
      $results[] = $match[0];
    }

    return array_unique( $results );
  }

  /** @noinspection PhpUnnecessaryLocalVariableInspection */
  function getShortened() {
    $query = $this->getQuery();
    if ( strlen( $query ) <= 200 ) {
      return $query;
    }
    $result = $query;
    /* quoted strings, with escapes processed correctly */
    $result = preg_replace_callback( $this->quotedStringRe, function ( $matches ) {
      $s = $matches[0];
      if ( strlen( $s ) > 32 ) {
        $s = substr( $s, 0, 20 ) . '...' . substr( $s, - 9 );
      }

      return $s;
    }, $result );

    /* extra white space */
    $result = preg_replace( '/\s+/', ' ', $result );

    return $result;
  }

  /** get the fingerprinted query.
   * @return array|string|string[]|null
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  function getFingerprint() {
    $query = $this->getQuery();

    $result = $query;
    $result = preg_replace_callback( $this->quotedStringRe, [ $this, 'stripStrings' ], $result );

    /* backticks */
    $result = preg_replace( '/\`([_$A-Za-z0-9]+)\`/', '$1', $result );

    /* take off LIMIT and OFFSET -- we need to see pagination details */
    $limitp      = strripos( $result, ' LIMIT ' );
    $offsetp     = strripos( $result, ' OFFSET ' );
    $limitClause = '';
    if ( $limitp > 0 || $offsetp > 0 ) {
      $limitp      = $limitp === false ? PHP_INT_MAX : $limitp;
      $offsetp     = $offsetp === false ? PHP_INT_MAX : $offsetp;
      $p           = min( $limitp, $offsetp );
      $limitClause = substr( $result, $p );
      $result      = substr( $result, 0, $p );
    }

    $stringNumRe = '/' . $this->symbolizedStringDelimiter . '\d+' . $this->symbolizedStringDelimiter . '/';
    $result      = preg_replace_callback( $stringNumRe, [ $this, 'restoreStrings' ], $result );


    $result .= ' ';

    /* date and time constants */
    $result = preg_replace( '/\'\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d\'/', '?datetime?', $result );
    $result = preg_replace( '/\'\d\d\d\d-\d\d-\d\d\'/', '?date?', $result );
    $result = preg_replace( '/\'[0-9]{10}\'/', '?t?', $result );

    /* special case for autoload = 'yes' */
    $result = preg_replace( '/\s+autoload\s*=\s*\'yes\'/', ' ?autoloadyes? ', $result );

    /* integers */
    for ( $i = 0; $i < 5; $i ++ ) {
      $result = preg_replace( '/([^\d_])0([^\d])/', '$1?izero?$2', $result );
      $result = preg_replace( '/([^\d_])1([^\d])/', '$1?ione?$2', $result );
    }

    $result = preg_replace( '/= +\d+/', '= ?i?', $result );
    $result = preg_replace( '/= +\'\d+\'/', '= ?qi?', $result );
    $result = preg_replace( '/IN +\( *\d+ *\)/', 'IN (?i?)', $result );
    $result = preg_replace( '/IN +\( *\d+ *, *\d+ *\)/', 'IN (?i?, ?i?)', $result );
    /* This is a workaround for an apparent
     *  regex bug capturing {2,19} and {20,} with lots of numbers */
    $result = preg_replace( '/[0-9, ]{64,}/', '?ilonglist?', $result );
    $result = preg_replace( '/IN\s*\((?:\s*(?:\?izero\?|\?ione\?|\d+)\s*,*?){2,19}\s*\)/', 'IN (?ilist?)', $result );
    $result = preg_replace( '/IN\s*\((?:\s*(?:\?izero\?|\?ione\?|\d+)\s*,*?){20,}\s*\)/', 'IN (?ilonglist?)', $result );
    $result = preg_replace( '/([^_])\d+/', '$1?i?', $result );

    $result = preg_replace( $this->quotedStringRe, '?s?', $result );

    /* giant inserts */
    $result = preg_replace( "/(INSERT +[^\\(]+\\([^\\)]+\\) *VALUES *)(?:.{150,}+)/", '$1 (?valuelist?)', $result );

    /* replace special cases */
    $result = preg_replace( '/\?izero\?/', '0', $result );
    $result = preg_replace( '/\?ione\?/', '1', $result );
    $result = preg_replace( '/\?autoloadyes\?/', 'autoload = \'yes\' ', $result );

    /* Process and put back LIMIT and OFFSET */
    if ( strlen( $limitClause ) > 0 ) {
      $fixedLimit = preg_replace( '/\s+0\s*,\s*/', ' ?izero?, ', $limitClause );
      $fixedLimit = preg_replace( '/\s+\d{2,}\s*,\s*/', ' ?i?, ', $fixedLimit );
      $fixedLimit = preg_replace( '/\?izero\?/', '0', $fixedLimit );
      $result     = $result . ' ' . $fixedLimit;
    }
    /* extra white space */
    $result = preg_replace( '/\s+/', ' ', $result );

    return $result;
  }

  /** match callback function for symbolizing strings
   *
   * @param array $matches
   *
   * @return string
   */
  private function stripStrings( array $matches ) {
    $s             = $matches[0];
    $stashNum      = count( $this->stash );
    $this->stash[] = $s;

    return $this->symbolizedStringDelimiter . $stashNum . $this->symbolizedStringDelimiter;
  }

  /** restore symbolized strings
   *
   * @param array $matches
   *
   * @return string
   */
  private function restoreStrings( array $matches ) {
    $s = $matches[0];
    if ( ! is_string( $s ) ) {
      return '';
    }
    $s        = substr( $s, 3 );
    $stashNum = substr( $s, 0, strlen( $s ) - 3 ) + 0;
    if ( $stashNum >= count( $this->stash ) ) {
      return '';
    }
    $s = $this->stash[ $stashNum ];

    return $this->shortenString( $s );
  }

  /** shorten a long string
   *
   * @param string $s
   *
   * @return string
   */
  private function shortenString( $s ) {
    if ( $this->stringLengthThreshold > 0 && strlen( $s ) > $this->stringLengthThreshold ) {
      $s = substr( $s, 0, 20 ) . '... --original string length ' . strlen( $s ) . '-- ...' . substr( $s, - 20 );
    }

    return $s;
  }
}