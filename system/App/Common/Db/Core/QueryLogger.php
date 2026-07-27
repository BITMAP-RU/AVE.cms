<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/QueryLogger.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use App\Common\Logs;
	use App\Helpers\Debug;
	use App\Helpers\Number;

	/**
	 * Логгер SQL-запросов
	 * Собирает информацию о выполненных запросах для profiling и отладки
	 */
	class QueryLogger
	{
		/** @var array Список всех выполненных запросов */
		protected static $_query_list = [];

		/** @var string Последний выполненный запрос */
		protected static $_last_query = null;

		/** @var bool Включен ли сбор списка запросов */
		protected static $_list_query = true;

		/** @var bool Нужен ли дорогой стек вызовов для каждого SQL. */
		protected static $_caller_trace = false;

		/**
		 * Установить последний выполненный запрос
		 * @param string $query SQL-запрос
		 */
		public static function setLastQuery(string $query)
		{
			self::$_last_query = $query;
		}

		/**
		 * Получить последний выполненный запрос
		 * @return string|null Последний запрос
		 */
		public static function getLastQuery()
		{
			return self::$_last_query;
		}

		/**
		 * Получить список всех запросов
		 * @return array Список запросов
		 */
		public static function getQueries()
		{
			return self::$_query_list;
		}

		/**
		 * Записать запрос в лог
		 * @param string $query SQL-запрос
		 * @param float $time Время выполнения в секундах
		 * @param int $affected_rows Количество затронутых строк
		 * @param int|null $ttl Время жизни кеша
		 * @param array $caller Информация о вызывающем коде
		 */
		public static function logQuery(string $query, float $time, int $affected_rows, $ttl, array $caller)
		{
			if (!self::isCollecting()) {
				return;
			}

			// Trim query for logging
			$trimmed_query = self::queryTrim($query);

			self::$_query_list[] = [
				'caller' => $caller,
				'query' => $trimmed_query,
				'ttl' => $ttl,
				'time' => Number::numFormat($time * 1, 6, ',', ''),
				'affected' => $affected_rows
			];
		}

		public static function isCollecting()
		{
			return defined('SQL_PROFILING') && SQL_PROFILING && self::$_list_query;
		}

		public static function enableCallerTrace($enabled = true)
		{
			self::$_caller_trace = (bool) $enabled;
		}

		public static function needsCallerTrace()
		{
			return self::isCollecting() && self::$_caller_trace;
		}

		/**
		 * Укоротить запрос для логирования (удаляет лишние пробелы)
		 * @param string $string Исходный SQL
		 * @return string Укороченный SQL
		 */
		public static function queryTrim($string)
		{
			$search = array(
				"/[\t]/",
				'/(\s)+/s',
				'/(GROUP BY|STRAIGHT_JOIN|UNION|FROM|WHERE|LIMIT|ORDER BY|LEFT JOIN|INNER JOIN|RIGHT JOIN|JOIN|ON|AND|OR|SET)/s'
			);

			$replace = array(
				" ",
				'\\1',
				"\r\n$1"
			);

			$sql = trim(preg_replace($search, $replace, $string));
			$sql = preg_replace("/\s*\r+/", "", $sql);

			return $sql;
		}

		/**
		 * Получить стек вызовов для текущего запроса
		 * @return array Массив с информацией о вызывающем коде
		 */
		public static function getCaller()
		{
			if (!function_exists('debug_backtrace')) {
				return '';
			}

			$stack = debug_backtrace();
			$stack = array_reverse($stack);

			$caller = [];

			foreach ($stack as $call) {
				$function = $call['function'];

				if ($function == 'getCaller' || $function == 'query' || $function == 'queryRaw' || $function == 'queryReal') {
					continue;
				}

				if (isset($call['class'])) {
					$function = $call['class'] . "->$function";
				}

				$caller[] = [
					'call_file' => ($call['file'] ?? 'Unknown'),
					'call_func' => $function,
					'call_line' => ($call['line'] ?? 'Unknown')
				];
			}

			return $caller;
		}
	}
