<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Settings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || exit('Direct access to this location is not allowed.');



	use DB;
	use App\Common\Db\Core\QueryBuilder;

	class Settings
	{
		/**
		 * Экземпляр класса Settings для реализации паттерна Singleton
		 *
		 * @var Settings|null
		 */
		protected static $instance = null;


		/**
		 * Конструктор класса Settings
		 *
		 * Инициализирует настройки системы из базы данных и сохраняет их в реестре.
		 * Выполняет запрос к таблице настроек и кэширует результат для повышения производительности.
		 *
		 * @return void
		 */
		protected function __construct ()
		{
			// SELECT * — устойчиво к схеме с/без столбца type (см. install()).
			$sql = "
				SELECT
					*
				FROM
					" . self::table() . "
			";

			$ttl = defined('SYSTEM_CACHE_LIFETIME') ? SYSTEM_CACHE_LIFETIME : 0;

			$rows = DB::setTtl($ttl)
				->setCache('__settings', '.settings')
				->setTags(array('settings'))
				->Query($sql, 1)
				->getAll();

			// Пивот key-value строк в ассоциативный массив [param => value],
			// приводя значение к типу из столбца type (bool/int/float/json/string).
			$settings = array();
			foreach ((array) $rows as $r) {
				$type = isset($r['type']) ? $r['type'] : 'string';
				$settings[$r['param']] = self::castValue($r['value'], $type);
			}

			Registry::set('settings', $settings);
		}


		/**
		 * Инициализация экземпляра класса Settings
		 *
		 * Реализует паттерн Singleton для обеспечения единственного экземпляра класса.
		 * Если экземпляр еще не создан, создает его, в противном случае возвращает существующий.
		 *
		 * @return Settings Возвращает экземпляр класса Settings
		 */
		public static function init ()
		{
			if (! isset(self::$instance))
			{
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * Получение значения настройки по ключу
		 *
		 * Возвращает все настройки системы или конкретное значение настройки по ключу.
		 * Если ключ не указан, возвращает весь массив настроек.
		 *
		 * @param string $key Ключ настройки (необязательный параметр)
		 * @return mixed Возвращает значение настройки или весь массив настроек
		 */
		public static function get ($key = '', $default = null)
		{
			if ($key === '')
			{
				return  Registry::get('settings');
			}

			$value = Registry::get('settings', $key);
			if ($value !== null) {
				return $value;
			}

			if (class_exists(__NAMESPACE__ . '\\SettingsRegistry')) {
				return SettingsRegistry::defaultValue($key, $default);
			}

			return $default;
		}


		/**
		 * Имя таблицы настроек с префиксом текущего подключения.
		 *
		 * @return string
		 */
		protected static function table ()
		{
			return SystemTables::table('settings');
		}


		/**
		 * Записать одну настройку (key-value upsert) и обновить реестр.
		 *
		 * Тип определяется автоматически по PHP-значению (bool/int/float/array→json),
		 * либо задаётся явно: 'bool', 'int', 'float', 'json', 'string', 'code', 'html'.
		 *
		 * @param string      $key   Имя параметра
		 * @param mixed       $value Значение
		 * @param string|null $type  Тип (null = определить автоматически)
		 * @return void
		 */
		public static function set ($key, $value, $type = null)
		{
			if ($type === null) {
				$type = self::detectType($value);
			}

			$stored = self::encodeValue($value, $type);

			$sql = QueryBuilder::parseQueryParams(
				"INSERT INTO `" . self::table() . "` (param, value, type)
				 VALUES (%s_param, %?_value, %s_type)
				 ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type)",
				array('param' => $key, 'value' => $stored, 'type' => $type)
			);
			DB::Query($sql);
			DB::clearTags(array('settings'));

			$settings = Registry::get('settings');
			if (! is_array($settings)) {
				$settings = array();
			}

			$settings[$key] = self::castValue($stored, $type);
			Registry::set('settings', $settings);
		}


		/** Удалить все настройки с указанным префиксом и обновить runtime-кеш. */
		public static function deletePrefix ($prefix)
		{
			$prefix = (string) $prefix;
			if ($prefix === '') {
				throw new \InvalidArgumentException('Префикс настройки не может быть пустым');
			}

			DB::Query('DELETE FROM `' . self::table() . '` WHERE param LIKE %s', $prefix . '%');
			DB::clearTags(array('settings'));

			$settings = Registry::get('settings');
			if (! is_array($settings)) {
				return;
			}

			foreach (array_keys($settings) as $key) {
				if (strpos((string) $key, $prefix) === 0) {
					unset($settings[$key]);
				}
			}

			Registry::set('settings', $settings);
		}


		/**
		 * Привести хранимое строковое значение к PHP-типу.
		 *
		 * @param string $value Значение из БД
		 * @param string $type  Тип
		 * @return mixed
		 */
		protected static function castValue ($value, $type)
		{
			switch ($type) {
				case 'bool':
					return $value === '1' || $value === 'true' || $value === true;
				case 'int':
					return (int) $value;
				case 'float':
				case 'double':
					return (float) $value;
				case 'json':
					return json_decode((string) $value, true);
				default: // string, code, html, text
					return $value;
			}
		}


		/**
		 * Определить тип по PHP-значению.
		 *
		 * @param mixed $value
		 * @return string
		 */
		protected static function detectType ($value)
		{
			if (is_bool($value))  return 'bool';
			if (is_int($value))   return 'int';
			if (is_float($value)) return 'float';
			if (is_array($value)) return 'json';
			return 'string';
		}


		/**
		 * Подготовить PHP-значение к хранению в строковом столбце.
		 *
		 * @param mixed  $value
		 * @param string $type
		 * @return string
		 */
		protected static function encodeValue ($value, $type)
		{
			if ($type === 'bool') {
				return $value ? '1' : '0';
			}

			if ($type === 'json') {
				return json_encode($value, JSON_UNESCAPED_UNICODE);
			}

			return (string) $value;
		}


		/**
		 * Гарантировать наличие key-value таблицы настроек.
		 *
		 * Используется при установке фреймворка в новом проекте:
		 *   - таблицы нет        → создаёт key-value таблицу;
		 *   - таблица key-value  → ничего не делает;
		 *   - «широкая» таблица  → мигрирует данные (1 строка, N столбцов → N строк),
		 *     сохраняя оригинал как <table>_backup.
		 *
		 * @return string Результат: 'created' | 'exists' | 'migrated'
		 */
		public static function install ()
		{
			$table  = self::table();
			$exists = (bool) DB::Query("SHOW TABLES LIKE '" . $table . "'")->getAssoc();

			if (! $exists) {
				self::createTable($table);
				return 'created';
			}

			$isKeyValue = (bool) DB::Query("SHOW COLUMNS FROM `" . $table . "` LIKE 'param'")->getAssoc();
			if ($isKeyValue) {
				// Таблица key-value, но без столбца type — дорастить (старые установки).
				$hasType = (bool) DB::Query("SHOW COLUMNS FROM `" . $table . "` LIKE 'type'")->getAssoc();
				if (! $hasType) {
					DB::Query("ALTER TABLE `" . $table . "` ADD type VARCHAR(20) NOT NULL DEFAULT 'string' AFTER value");
					return 'upgraded';
				}

				return 'exists';
			}

			return self::migrateFromWide($table);
		}


		/**
		 * Создать пустую key-value таблицу настроек.
		 *
		 * @param string $table Имя таблицы
		 * @return void
		 */
		protected static function createTable ($table)
		{
			DB::Query(
				"CREATE TABLE IF NOT EXISTS `" . $table . "` (
					param VARCHAR(190) NOT NULL,
					value MEDIUMTEXT,
					type VARCHAR(20) NOT NULL DEFAULT 'string',
					PRIMARY KEY (param)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}


		/**
		 * Перенести «широкую» таблицу (1 строка, столбец на настройку) в key-value.
		 * Оригинал сохраняется как <table>_backup.
		 *
		 * @param string $table Имя таблицы
		 * @return string 'migrated'
		 */
		protected static function migrateFromWide ($table)
		{
			$row    = DB::Query("SELECT * FROM `" . $table . "` LIMIT 1")->getAssoc();
			$backup = $table . '_backup';

			DB::Query("DROP TABLE IF EXISTS `" . $backup . "`");
			DB::Query("RENAME TABLE `" . $table . "` TO `" . $backup . "`");

			self::createTable($table);

			if (is_array($row)) {
				foreach ($row as $param => $value) {
					if (strcasecmp($param, 'Id') === 0) {
						continue;
					}

					DB::Query(QueryBuilder::parseQueryParams(
						"INSERT INTO `" . $table . "` (param, value) VALUES (%s_param, %?_value)",
						array('param' => $param, 'value' => $value)
					));
				}
			}

			return 'migrated';
		}
	}
