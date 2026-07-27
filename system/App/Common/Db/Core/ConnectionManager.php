<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/ConnectionManager.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use DB;
	use Exception;

	/**
	 * Менеджер подключений к базе данных
	 * Управляет созданием, хранением и закрытием подключений к MySQL
	 */
	class ConnectionManager
	{
		/** @var array Конфигурации подключений по именам */
		protected static $connections = [];

		/** @var array Экземпляры MysqliDriver для каждого подключения */
		protected static $instances = [];

		/** @var string Имя подключения по умолчанию */
		public static $defaultConnection = 'default';

		/** @var string Префикс таблиц для текущего подключения */
		public static $prefix = '';

		/**
		 * Добавить новое подключение к базе данных
		 * @param string $name Имя подключения
		 * @param array $params Параметры подключения (dbhost, dbuser, dbpass, dbname, dbport, dbsock, dbchar, dbpref)
		 */
		public static function addConnection($name, array $params)
		{
			self::$connections[$name] = [];

			foreach (array('dbhost', 'dbuser', 'dbpass', 'dbname', 'dbport', 'dbsock', 'dbchar', 'dbpref') as $k) {
				$prm = $params[$k] ?? null;

				if ($k === 'dbhost') {
					if (is_object($prm)) {
						// Handling existing object passing
						// self::$_mysqli[$name] = $prm; // This logic might need adaptation if we wrap it
					}

					if (!is_string($prm)) {
						$prm = null;
					}
				}

				self::$connections[$name][$k] = $prm;
			}
		}

		/**
		 * Установить соединение с базой данных
		 * @param string $connectionName Имя подключения (по умолчанию 'default')
		 * @return MysqliDriver Экземпляр драйвера MySQLi
		 * @throws Exception Если профиль подключения не найден
		 */
		public static function connect($connectionName = 'default')
		{
			if (!isset(self::$connections[$connectionName])) {
				throw new Exception('Connection profile not set');
			}

			if (isset(self::$instances[$connectionName])) {
				return self::$instances[$connectionName];
			}

			$config = self::$connections[$connectionName];

			// Handle charset logic from original DB
			if (isset($config['dbchar']) && empty($config['dbchar'])) {
				// Logic from original: $charset = array_pop($params); if params was array_values($pro)
				// But we are using assoc array here. Original code logic was a bit weird with array_pop
				// $params = array_values($pro); $charset = array_pop($params);
				// 'dbpref' is last in key list, so 'dbchar' is second to last.
				// Let's assume passed config has it correct.
			}

			try {
				$driver = new MysqliDriver($config);
				self::$instances[$connectionName] = $driver;

				// Keep an explicitly supplied prefix for isolated migration tools,
				// but do not publish the connection prefix as a global constant.
				self::$prefix = defined('PREFIX')
					? (string) constant('PREFIX')
					: (isset($config['dbpref']) ? (string) $config['dbpref'] : '');

				return $driver;
			} catch (Exception $e) {
				throw $e;
			}
		}

		/**
		 * Получить экземпляр драйвера для указанного подключения
		 * @param string|null $name Имя подключения (по умолчанию использует defaultConnection)
		 * @return MysqliDriver|null Экземпляр драйвера или null если не найден
		 */
		public static function getDriver($name = null): ?MysqliDriver
		{
			$name = $name ?: self::$defaultConnection;

			if (!isset(self::$instances[$name])) {
				// Auto connect if default
				if ($name === 'default' && isset(self::$connections['default'])) {
					return self::connect('default');
				}

				return null;
			}

			return self::$instances[$name];
		}

		/**
		 * Установить префикс таблиц для текущего подключения
		 * @param string $prefix Префикс таблиц
		 */
		public static function setPrefix($prefix)
		{
			self::$prefix = $prefix;
		}

		/**
		 * Получить текущий префикс таблиц
		 * @return string Префикс таблиц
		 */
		public static function getPrefix()
		{
			return self::$prefix;
		}

		/**
		 * Закрыть указанное подключение
		 * @param string $connection Имя подключения (по умолчанию 'default')
		 */
		public static function disconnect($connection = 'default')
		{
			if (isset(self::$instances[$connection])) {
				self::$instances[$connection]->close();
				unset(self::$instances[$connection]);
			}
		}

		/**
		 * Закрыть все активные подключения
		 */
		public static function disconnectAll()
		{
			foreach (array_keys(self::$instances) as $k) {
				self::disconnect($k);
			}
		}
	}
