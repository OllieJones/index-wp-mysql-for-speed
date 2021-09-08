<?php

/**
 * Light SQL Parser Class
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright Copyright (c) 2021
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link https://github.com/marcocesarato/PHP-Light-SQL-Parser-Class
 */
class LightSQLParser
{
	// Public
	public $query = '';
	// Private
	protected static $connectors = array('OR', 'AND', 'ON', 'LIMIT', 'WHERE', 'JOIN', 'GROUP', 'ORDER', 'OPTION', 'LEFT', 'INNER', 'RIGHT', 'OUTER', 'SET', 'HAVING', 'VALUES', 'SELECT', '\(', '\)');
	protected static $connectors_imploded = '';
	protected $queries = [];

	/**
	 * Constructor
	 */
	public function __construct($query = '') {
		$this->setQuery($query);
		if (empty(self::$connectors_imploded)) {
			self::$connectors_imploded = implode('|', self::$connectors);
		}
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
	public function setQuery($query) {
		$this->query = $query;
		$this->queries = [];
		return $this;
	}

	/**
	 * Get all queries
	 * @return array
	 */
	public function getAllQueries() {
		if(empty($this->queries)) {
			// TODO: fix issues when on a subquery exists a UNION expression
			$query = $this->getQuery();
			$query = preg_replace('#/\*[\s\S]*?\*/#', '', $query);
			$query = preg_replace('#;(?:(?<=["\'];)|(?=["\']))#', '', $query);
			$query = preg_replace('#[\s]*UNION([\s]+ALL)?[\s]*#', ';', $query);
			$queries = explode(';', $query);
			foreach ($queries as $key => $query) {
				$this->queries[$key] = str_replace(array('`', '"', "'"), '', $query);
			}
		}
		return $this->queries;
	}

	/**
	 * Get SQL Query method
	 * @return string
	 */
	public function getMethod() {
		$methods = array('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'RENAME', 'SHOW', 'SET', 'DROP', 'CREATE INDEX', 'CREATE TABLE', 'EXPLAIN', 'DESCRIBE', 'TRUNCATE', 'ALTER');
		$queries = $this->getAllQueries();
		foreach ($queries as $query) {
			foreach ($methods as $method) {
				$_method = str_replace(' ', '[\s]+', $method);
				if (preg_match('#^[\s]*' . $_method . '[\s]+#i', $query)) {
					return $method;
				}
			}
		}
		return '';
	}

	/**
	 * Get Query fields (at the moment only SELECT/INSERT/UPDATE)
	 * @param $query
	 * @return array
	 */
	public function getFields() {
		$fields = array();
		$queries = $this->getAllQueries();
		foreach ($queries as $query) {
			$method = $this->getMethod();
			switch ($method) {
				case 'SELECT':
					preg_match('#SELECT[\s]+([\S\s]*)[\s]+FROM#i', $query, $matches);
					if (!empty($matches[1])) {
						$match = trim($matches[1]);
						$match = explode(',', $match);
						foreach ($match as $field) {
							$field = preg_replace('#([\s]+(AS[\s]+)?[\w.]+)#i', '', trim($field));
							$fields[] = $field;
						}
					}
					break;
				case 'INSERT':
					preg_match('#INSERT[\s]+INTO[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)\(([\S\s]*)\)[\s]+VALUES#i', $query, $matches);
					if (!empty($matches[4])) {
						$match = trim($matches[4]);
						$match = explode(',', $match);
						foreach ($match as $field) {
							$field = preg_replace('#([\s]+(AS[\s]+)?[\w.]+)#i', '', trim($field));
							$fields[] = $field;
						}
					} else {
						preg_match('#INSERT[\s]+INTO[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)SET([\S\s]*)([;])?#i', $query, $matches);
						if (!empty($matches[4])) {
							$match = trim($matches[4]);
							$match = explode(',', $match);
							foreach ($match as $field) {
								$field = preg_replace('#([\s]*=[\s]*[\S\s]+)#i', '', trim($field));
								$fields[] = $field;
							}
						}
					}
					break;
				case 'UPDATE':
					preg_match('#UPDATE[\s]+([\w.]+([\s]+(AS[\s]+)?[\w.]+)?[\s]*)SET([\S\s]*)([\s]+WHERE|[;])?#i', $query, $matches);
					if (!empty($matches[4])) {
						$match = trim($matches[4]);
						$match = explode(',', $match);
						foreach ($match as $field) {
							$field = preg_replace('#([\s]*=[\s]*[\S\s]+)#i', '', trim($field));
							$fields[] = $field;
						}
					}
					break;
				case 'CREATE TABLE':
					preg_match('#CREATE[\s]+TABLE[\s]+\w+[\s]+\(([\S\s]*)\)#i', $query, $matches);
					if (!empty($matches[1])) {
						$match = trim($matches[1]);
						$match = explode(',', $match);
						foreach ($match as $_field) {
							preg_match('#^w+#', trim($_field), $field);
							if (!empty($field[0])) {
								$fields[] = $field[0];
							}
						}
					}
					break;
			}
		}
		return array_unique($fields);
	}

	/**
	 * Get SQL Query First Table
	 * @param $query
	 * @return string
	 */
	public function getTable() {
		$tables = $this->getAllTables();
		return (isset($tables[0])) ? $tables[0] : null;
	}

	/**
	 * Get SQL Query Tables
	 * @return array
	 */
	function getAllTables() {
		$results = array();
		$queries = $this->getAllQueries();
		foreach ($queries as $query) {
			$patterns = array(
				'#[\s]+FROM[\s]+(([\s]*(?!' . self::$connectors_imploded . ')[\w]+([\s]+(AS[\s]+)?(?!' . self::$connectors_imploded . ')[\w]+)?[\s]*[,]?)+)#i',
				'#[\s]*INSERT[\s]+INTO[\s]+([\w]+)#i',
				'#[\s]*UPDATE[\s]+([\w]+)#i',
				'#[\s]+JOIN[\s]+([\w]+)#i',
				'#[\s]+TABLE[\s]+([\w]+)#i',
				'#[\s]+TABLESPACE[\s]+([\w]+)#i',
			);
			foreach ($patterns as $pattern) {
				preg_match_all($pattern, $query, $matches, PREG_SET_ORDER);
				foreach ($matches as $val) {
					$tables = explode(',', $val[1]);
					foreach ($tables as $table) {
						$table = trim(preg_replace('#[\s]+(AS[\s]+)[\w.]+#i', '', $table));
						$results[] = $table;
					}
				}
			}
		}
		return array_unique($results);
	}

	/**
	 * Join tables.
	 * @return array
	 */
	function getJoinTables() {
		$results = array();
		$queries = $this->getAllQueries();
		foreach ($queries as $query) {
			preg_match_all('#[\s]+JOIN[\s]+([\w]+)#i', $query, $matches, PREG_SET_ORDER);
			foreach ($matches as $val) {
				$tables = explode(',', $val[1]);
				foreach ($tables as $table) {
					$table = trim(preg_replace('#[\s]+(AS[\s]+)[\w.]+#i', '', $table));
					$results[] = $table;
				}
			}
		}
		return array_unique($results);
	}

	/**
	 * Has join tables.
	 * @return bool
	 */
	function hasJoin() {
		$queries = $this->getAllQueries();
		foreach ($queries as $query) {
			preg_match('#[\s]+JOIN[\s]+([\w]+)#i', $query, $matches);
			if(!empty($matches[1])) {
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
		preg_match('#\([\s]*(SELECT[^)]+)\)#i', $query, $matches);
		if(!empty($matches[1])) {
			return true;
		}
		return false;
	}

	/**
	 * Join tables.
	 * @return array
	 */
	function getSubQueries() {
		$results = array();
		$query = $this->getQuery();
		preg_match_all('#\([\s]*(SELECT[^)]+)\)#i', $query, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$results[] = $match[0];
		}
		return array_unique($results);
	}
}