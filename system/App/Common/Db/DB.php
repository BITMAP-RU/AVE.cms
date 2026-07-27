<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/DB.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	use App\Common\Db\Core\ConnectionManager;
	use App\Common\Db\Core\MysqliDriver;
	use App\Common\Db\Core\QueryBuilder;
	use App\Common\Db\Core\QueryExecutor;
	use App\Common\Db\Core\QueryCache;
	use App\Common\Db\Core\TransactionManager;
	use App\Common\Db\Core\ErrorHandler;
	use App\Common\Db\Core\QueryLogger;
	use App\Common\Db\Core\QueryDebug;
	use App\Helpers\Arr;
	use App\Helpers\Debug;

	/**
	 * Фасад для работы с базой данных
	 */
	class DB
	{
		protected static $_mysqli = [];
		protected static $instance = null;

		public static $prefix = '';
		public static $defaultConnection = 'default';

		// Config options
		public static $param_char = '%';
		public static $named_param_seperator = '_';
		public static $success_handler = false;
		public static $error_handler = true;
		public static $throw_exception_on_error = false;
		public static $nonsql_error_handler = null;
		public static $throw_exception_on_nonsql_error = false;
		public static $nested_transactions = false;
		public static $usenull = true;

		// State
		protected static $internal_mysql = null;
		public static $server_info = null;
		public static $insert_id = 0;
		public static $num_rows = 0;
		public static $affected_rows = 0;
		public static $current_db = null;
		public static $nested_transactions_count = 0;
		public static $transaction_in_progress = false;

		/** @var callable[] Действия, выполняемые после успешного внешнего commit */
		protected static $after_commit_callbacks = array();

		// Cache config
		protected static $_ttl = null;
		protected static $_list_query = true;
		protected static $_cache_id = null;
		protected static $_cache_dir = BASEPATH . '/tmp/cache/sql/';
		protected static $_logstatus = false;
		protected static $_extension = null;

		public function __construct($db)
		{
			self::addConnection('default', [
				'dbhost' => $db['dbhost'],
				'dbuser' => $db['dbuser'],
				'dbpass' => $db['dbpass'],
				'dbname' => $db['dbname'],
				'dbport' => $db['dbport'],
				'dbsock' => $db['dbsock'],
				'dbchar' => $db['dbchar'],
				'dbpref' => $db['dbpref']
			]);

			if (isset($db['dbpref'])) {
				self::setPrefix($db['dbpref']);
			}

			self::$instance = $this;
		}

		public static function getInstance($config)
		{
			if (is_null(self::$instance)) {
				self::$instance = new DB($config);
			}

			return self::$instance;
		}

		//-- Connection Delegations

		public static function addConnection($name, array $params): ?DB
		{
			ConnectionManager::addConnection($name, $params);
			return self::$instance;
		}

		public static function connect($connectionName = 'default'): void
		{
			ConnectionManager::connect($connectionName);
			self::$prefix = ConnectionManager::getPrefix();
		}

		public static function connection($name)
		{
			ConnectionManager::connect($name);
			self::$defaultConnection = $name;
			self::$prefix = ConnectionManager::getPrefix();
			return self::$instance;
		}

		public static function disconnect($connection = 'default')
		{
			ConnectionManager::disconnect($connection);
		}

		public static function disconnectAll()
		{
			ConnectionManager::disconnectAll();
		}

		public static function default()
		{
			self::connection('default');
		}

		public static function mysqli()
		{
			$driver = ConnectionManager::getDriver(self::$defaultConnection);
			if (!$driver) {
				$driver = ConnectionManager::connect(self::$defaultConnection);
			}

			return $driver->getMysqli();
		}

		public static function setPrefix($prefix = ''): ?DB
		{
			self::$prefix = $prefix;
			ConnectionManager::setPrefix($prefix);
			return self::$instance;
		}

		//-- Query Methods

		public static function query(...$args)
		{
			return self::queryHelper('query', ...$args);
		}

		public static function queryAssoc(...$args)
		{
			return self::queryHelper('assoc', ...$args);
		}

		public static function queryObjects(...$args)
		{
			return self::queryHelper('object', ...$args);
		}

		public static function queryAllLists(...$args)
		{
			return self::queryHelper('list', ...$args);
		}

		public static function queryFullColumns(...$args)
		{
			return self::queryHelper('full', ...$args);
		}

		public static function queryRaw(...$args)
		{
			return self::queryHelper('raw_buf', ...$args);
		}

		public static function queryRawUnbuf(...$args)
		{
			return self::queryHelper('raw_unbuf', ...$args);
		}

		public static function queryOneColumn($column = null, ...$args)
		{
			$results = self::query(...$args)->getAll();
			$ret = [];

			if (!$results || !count($results)) {
				return $ret;
			}

			if ($column === null) {
				$keys = array_keys(is_array($results[0]) ? $results[0] : (array) $results[0]);
				$column = $keys[0];
			}

			foreach ($results as $row) {
				$row = (array) $row;
				if (isset($row[$column])) {
					$ret[] = $row[$column];
				}
			}

			return $ret;
		}

		public static function queryFirstColumn(...$args)
		{
			$results = self::queryAllLists(...$args);
			$ret = [];
			foreach ($results as $row) {
				if (isset($row[0]))
					$ret[] = $row[0];
			}

			return $ret;
		}

		protected static function queryHelper(string $type, ...$args)
		{
			self::syncConfig();

			$is_buffered = true;
			$row_type    = 'query';
			$full_names  = false;

			switch ($type) {
				case 'query':
					$row_type = 'raw';
					break;
				case 'object':
					$row_type = 'object';
					break;
				case 'assoc':
					$row_type = 'assoc';
					break;
				case 'list':
					$row_type = 'list';
					break;
				case 'full':
					$row_type   = 'assoc';
					$full_names = true;
					break;
				case 'raw_buf':
					$row_type    = 'raw';
					$is_buffered = true;
					break;
				case 'raw_unbuf':
					$row_type    = 'raw';
					$is_buffered = false;
					break;
				default:
					ErrorHandler::msgError('Error -- invalid argument to queryHelper!');
			}

			$sql = QueryBuilder::parseQueryParams(...$args);

			$cached_result = QueryCache::get($sql);

			if ($cached_result) {
				$result = $cached_result;
			} else {
				Debug::startTime('query');
				$result     = QueryExecutor::query($sql, $is_buffered);
				$query_time = Debug::endTime('query');

				$ttl = QueryCache::getTtl();
				if ($ttl && $result instanceof DB_Result) {
					$data = $result->getAll();
					Debug::startTime('cache_save');
					QueryCache::save($sql, $data);
					$save_time = Debug::endTime('cache_save');
					QueryDebug::logCacheSave($sql, $save_time, $ttl);
					$result = new DB_Result($data);
				}

				$driver = ConnectionManager::getDriver();
				QueryDebug::logDirectQuery($sql, $query_time, $driver ? $driver->affectedRows() : 0);
			}

			$driver = ConnectionManager::getDriver(self::$defaultConnection);
			if ($driver) {
				self::$insert_id     = $driver->insertId();
				self::$affected_rows = $driver->affectedRows();
			}

			self::$num_rows = ($result instanceof DB_Result) ? $result->numRows() : null;

			QueryCache::setTtl();
			QueryCache::setCache();
			self::$_ttl = 0;

			if ($row_type == 'raw' || !($result instanceof DB_Result)) {
				return $result;
			}

			$return = [];

			if ($full_names) {
				$info_array = $result->fetchFields();
				$infos = [];
				foreach ($info_array as $info) {
					$infos[] = ($info->table != '' ? $info->table . '.' . $info->name : $info->name);
				}
			}

			while ($row = ($row_type == 'assoc' || $row_type == 'list' ? $result->getAssoc() : $result->getObject())) {
				if ($full_names && is_array($row)) {
					$row = array_combine($infos, $row);
				}

				$return[] = ($row_type == 'list' ? array_values((array) $row) : $row);
			}

			return $return;
		}

		//-- Update, Insert, Delete Helpers

		public static function Update($table, $params, ...$where_args)
		{
			self::syncConfig();

			$update_part = QueryBuilder::parseQueryParams(str_replace('%', self::$param_char, 'UPDATE %b SET %hc'), $table, $params);
			$where_part  = QueryBuilder::parseQueryParams(...$where_args);

			return self::Query($update_part . ' WHERE ' . $where_part);
		}

		public static function Insert($table, $data)
		{
			return self::insertOrReplace('INSERT', $table, $data);
		}

		public static function insertIgnore($table, $data)
		{
			return self::insertOrReplace('INSERT', $table, $data, ['ignore' => true]);
		}

		public static function Replace($table, $data)
		{
			return self::insertOrReplace('REPLACE', $table, $data);
		}

		public static function Delete($table, ...$where_args)
		{
			$table = QueryBuilder::formatTableName($table);
			$where = QueryBuilder::parseQueryParams(...$where_args);
			return self::Query("DELETE FROM {$table} WHERE {$where}");
		}

		public static function insertOrReplace($which, $table, $datas, $options = [])
		{
			$datas = Arr::unserialToArray(Arr::arrayToSerial($datas));
			$keys  = $values = [];

			self::syncConfig();

			if (isset($datas[0]) && is_array($datas[0])) {
				$var = '%ll?';
				foreach ($datas as $datum) {
					ksort($datum);
					if (!$keys)
						$keys = array_keys($datum);
					$values[] = array_values($datum);
				}
			} else {
				$var    = '%l?';
				$keys   = array_keys($datas);
				$values = array_values($datas);
			}

			if ($which != 'INSERT' && $which != 'INSERT IGNORE' && $which != 'REPLACE') {
				ErrorHandler::msgError('insertOrReplace() must be called with one of: INSERT, INSERT IGNORE, REPLACE');
			}

			if (isset($options['update']) && is_array($options['update']) && $options['update'] && $which == 'INSERT') {
				if (array_values($options['update']) !== $options['update']) {
					return self::Query(str_replace('%', self::$param_char, "INSERT INTO %b %lb VALUES $var ON DUPLICATE KEY UPDATE %hc"), $table, $keys, $values, $options['update']);
				}

				$update_str  = array_shift($options['update']);
				$query_param = [str_replace('%', self::$param_char, "INSERT INTO %b %lb VALUES $var ON DUPLICATE KEY UPDATE ") . $update_str, $table, $keys, $values];
				$query_param = array_merge($query_param, $options['update']);
				return self::Query(...$query_param);
			}

			return self::Query(str_replace('%', self::$param_char, "%l INTO %b %lb VALUES $var"), $which, $table, $keys, $values);
		}

		public static function insertUpdate()
		{
			$args  = func_get_args();
			$table = array_shift($args);
			$data  = array_shift($args);

			if (!isset($args[0])) {
				$args[0] = $data;
			}

			return self::insertOrReplace('INSERT', $table, $data, ['update' => $args[0]]);
		}

		//-- Transaction Methods

		public static function startTransaction()
		{
			self::syncConfig();
			$wasInProgress = self::$transaction_in_progress;
			TransactionManager::$nested_transactions = self::$nested_transactions;
			TransactionManager::startTransaction();
			self::$transaction_in_progress = TransactionManager::$transaction_in_progress;
			if (!$wasInProgress && self::$transaction_in_progress) {
				self::$after_commit_callbacks = array();
			}
		}

		public static function commit()
		{
			$result = TransactionManager::commit();
			self::$transaction_in_progress = TransactionManager::$transaction_in_progress;
			if (!self::$transaction_in_progress) {
				$callbacks = self::$after_commit_callbacks;
				self::$after_commit_callbacks = array();
				if ($result) {
					foreach ($callbacks as $callback) {
						self::runAfterCommit($callback);
					}
				}
			}

			return $result;
		}

		public static function rollback()
		{
			$result = TransactionManager::rollback();
			self::$transaction_in_progress = TransactionManager::$transaction_in_progress;
			if (!self::$transaction_in_progress) {
				self::$after_commit_callbacks = array();
			}

			return $result;
		}

		public static function afterCommit(callable $callback)
		{
			if (!self::$transaction_in_progress) {
				self::runAfterCommit($callback);
				return;
			}

			self::$after_commit_callbacks[] = $callback;
		}

		protected static function runAfterCommit(callable $callback)
		{
			try {
				call_user_func($callback);
			} catch (\Throwable $e) {
				error_log('Database after-commit callback: ' . $e->getMessage());
			}
		}

		//-- Utils

		public static function quote($value)
		{
			return QueryBuilder::quote($value);
		}

		public static function escape($value)
		{
			return QueryBuilder::escape($value);
		}

		public static function safe($value)
		{
			return QueryBuilder::safe($value);
		}

		public static function sqlEval(...$args)
		{
			$text = QueryBuilder::parseQueryParams(...$args);
			return new DB_Eval($text);
		}

		public static function debugQuery(...$args)
		{
			return QueryBuilder::parseQueryParams(...$args);
		}

		public static function getCaller()
		{
			return QueryLogger::getCaller();
		}

		public static function getLastQuery()
		{
			return QueryLogger::getLastQuery();
		}

		public static function getQueries()
		{
			return QueryLogger::getQueries();
		}

		public static function getDebugInfo()
		{
			return QueryDebug::getDebugList();
		}

		public static function getDebugReport()
		{
			return QueryDebug::getHtmlReport();
		}

		public static function setTtl($ttl = 0)
		{
			self::$_ttl = $ttl;
			QueryCache::setTtl($ttl);
			return self::$instance;
		}

		public static function setCache($cache_id = '', $extension = '')
		{
			self::$_cache_id  = $cache_id;
			self::$_extension = $extension;
			QueryCache::setCache($cache_id, $extension);
			return self::$instance;
		}

		public static function setTags($tags)
		{
			QueryCache::setTags($tags);
			return self::$instance;
		}

		public static function clearTags($tags)
		{
			QueryCache::clearTags($tags);
		}

		public static function version()
		{
			$driver = ConnectionManager::getDriver();
			return $driver ? $driver->getServerInfo() : null;
		}

		public static function getDatabase()
		{
			$res = self::query('SELECT DATABASE()')->getArray();
			return isset($res[0]) ? $res[0] : null;
		}

		public static function getTables($condition = null)
		{
			$query  = is_null($condition) ? 'SHOW TABLES' : 'SHOW TABLES LIKE ' . self::quote($condition);
			$res    = self::query($query);
			$tables = [];
			while ($row = $res->getArray()) {
				$tables[] = $row[0];
			}

			return $tables;
		}

		public static function columnList($table)
		{
			return self::queryOneColumn('Field', "SHOW COLUMNS FROM %b", $table);
		}

		public static function affectedRows()
		{
			return self::$affected_rows;
		}

		public static function numRows()
		{
			return self::$num_rows;
		}

		public static function numAllRows()
		{
			$result = self::query('SELECT FOUND_ROWS()');
			$row    = $result->getArray();
			return (int) $row[0];
		}

		public static function insertId()
		{
			return self::$insert_id;
		}

		public static function getInsertId()
		{
			return self::$insert_id;
		}

		protected static function syncConfig()
		{
			QueryBuilder::$param_char              = self::$param_char;
			QueryBuilder::$named_param_seperator   = self::$named_param_seperator;
			QueryBuilder::$usenull                 = self::$usenull;

			ErrorHandler::$throw_exception_on_error        = self::$throw_exception_on_error;
			ErrorHandler::$throw_exception_on_nonsql_error = self::$throw_exception_on_nonsql_error;
			ErrorHandler::$nonsql_error_handler            = self::$nonsql_error_handler;

			TransactionManager::$nested_transactions = self::$nested_transactions;

			QueryCache::setTtl(self::$_ttl);
			QueryCache::setCache(self::$_cache_id, self::$_extension);
		}

		public static function ping()
		{
			$driver = ConnectionManager::getDriver();
			return $driver ? $driver->ping() : false;
		}

		public static function shutDown($error = '')
		{
			ob_start();
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 3600');
			die($error);
		}

		public function __call($name, $args)
		{
			if (method_exists(self::$instance, $name)) {
				return call_user_func_array([self::$instance, $name], $args);
			}

			trigger_error('Unknown Method ' . $name . '()', E_USER_WARNING);
			return false;
		}
	}

	if (!function_exists('db_error_handler')) {
		function db_error_handler($params)
		{
			if (PHP_SAPI == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
				$out = [];
				if (isset($params['query']))
					$out[] = "QUERY: " . $params['query'];
				if (isset($params['error']))
					$out[] = "ERROR: " . $params['error'];
				$out[] = "";
				echo implode("\n", $out);
				die;
			}

			$reference = ErrorHandler::publicReference(is_array($params) ? $params : array());
			$message = 'Внутренняя ошибка базы данных. Код обращения: ' . $reference;
			if (!headers_sent()) {
				header('HTTP/1.1 500 Internal Server Error', true, 500);
				header('Cache-Control: no-store', true);
			}

			$accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';
			$ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
				&& strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
			if ($ajax || strpos($accept, 'application/json') !== false) {
				if (!headers_sent()) {
					header('Content-Type: application/json; charset=UTF-8', true);
				}

				echo json_encode(array(
					'success' => false,
					'message' => $message,
					'data' => array('reference' => $reference),
					'html' => new \stdClass(),
					'redirect' => null,
					'errors' => new \stdClass(),
					'error' => $message,
					'code' => $reference,
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				if (!headers_sent()) {
					header('Content-Type: text/html; charset=UTF-8', true);
				}

				echo '<!doctype html><meta charset="utf-8"><title>Ошибка базы данных</title>'
					. '<div style="font:15px/1.5 sans-serif;padding:48px;text-align:center;color:#475569">'
					. htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
			}

			die;
		}
	}

	if (!function_exists('db_debugmode_handler')) {
		function db_debugmode_handler($params)
		{
			echo "QUERY: " . $params['query'] . " [" . $params['runtime'] . " ms]";
			if (PHP_SAPI == 'cli' && empty($_SERVER['REMOTE_ADDR']))
				echo "\n";
			else
				echo "<br>\n";
		}
	}
