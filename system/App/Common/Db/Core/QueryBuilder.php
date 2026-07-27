<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/QueryBuilder.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use DB_Where;
	use DB_Eval;
	use DB_Exception;
	use Exception;
	use DateTime;

	/**
	 * Построитель SQL-запросов
	 * Обрабатывает параметризованные запросы и преобразует их в безопасный SQL
	 *
	 * Поддерживаемые типы параметров:
	 * %s - Экранированная строка
	 * %i - Целое число
	 * %d - Дробное число
	 * %b - Имя таблицы (с обратными кавычками)
	 * %l - Сырое значение (без экранирования)
	 * %ss - LIKE-строка с обеих сторон (%value%)
	 * %ssb - LIKE-строка с конца (value%)
	 * %sse - LIKE-строка с начала (%value)
	 * %t - Timestamp (преобразуется в 'Y-m-d H:i:s')
	 * %ls - Массив строк (экранированных)
	 * %li - Массив целых чисел
	 * %ld - Массив дробных чисел
	 * %lb - Массив имён таблиц
	 * %ll - Массив сырых значений
	 * %lt - Массив timestamps
	 * %? - Санитизированное значение (basic mode)
	 * %l? - Санитизированный список
	 * %ll? - Двойной список массивов
	 * %hc - Hash (ключ=значение) через запятую
	 * %ha - Hash с AND
	 * %ho - Hash с OR
	 */
	class QueryBuilder
	{
		/** @var string Символ параметра (по умолчанию '%') */
		public static $param_char = '%';

		/** @var string Разделитель именованных параметров */
		public static $named_param_seperator = '_';

		/** @var bool Использовать NULL для null-значений или пустую строку */
		public static $usenull = true;

		/**
		 * Предварительный разбор параметров запроса
		 * Возвращает массив чанков (строк и объектов с типом/значением)
		 * @param string $sql SQL-строка с параметрами
		 * @param mixed ...$args Аргументы для подстановки
		 * @return array Массив чанков запроса
		 */
		public static function preparseQueryParams(...$args)
		{
			$sql = (string) array_shift($args);
			$args_all = $args;

			if (count($args_all) == 0) {
				return [$sql];
			}

			$param_char_length = strlen(self::$param_char);
			$named_seperator_length = strlen(self::$named_param_seperator);

			$types = [
				self::$param_char . 'll',
				self::$param_char . 'ls',
				self::$param_char . 'l',
				self::$param_char . 'li',
				self::$param_char . 'ld',
				self::$param_char . 'lb',
				self::$param_char . 'lt',
				self::$param_char . 's',
				self::$param_char . 'i',
				self::$param_char . 'd',
				self::$param_char . 'b',
				self::$param_char . 't',
				self::$param_char . '?',
				self::$param_char . 'l?',
				self::$param_char . 'll?',
				self::$param_char . 'hc',
				self::$param_char . 'ha',
				self::$param_char . 'ho',
				self::$param_char . 'ss',
				self::$param_char . 'ssb',
				self::$param_char . 'sse',
			];

			$posList = [];

			foreach ($types as $type) {
				$lastPos = 0;
				while (($pos = strpos($sql, $type, $lastPos)) !== false) {
					$lastPos = $pos + 1;
					if (isset($posList[$pos]) && strlen($posList[$pos]) > strlen($type)) {
						continue;
					}

					$posList[$pos] = $type;
				}
			}

			ksort($posList);

			$chunkyQuery = [];
			$pos_adj = 0;

			foreach ($posList as $pos => $type) {
				$type = substr($type, $param_char_length);
				$length_type = strlen($type) + $param_char_length;
				$new_pos = $pos + $pos_adj;
				$new_pos_back = $new_pos + $length_type;

				if ($arg_number_length = strspn($sql, '1234567890', $new_pos_back)) {
					$arg_number = substr($sql, $new_pos_back, $arg_number_length);
					if (!array_key_exists($arg_number, $args_all)) {
						ErrorHandler::msgError("Non existent argument reference (arg $arg_number): $sql");
					}

					$arg = $args_all[$arg_number];
				} else if (substr($sql, $new_pos_back, $named_seperator_length) == self::$named_param_seperator) {
					// Named params logic
					// Simplified for brevity in this thought trace, but must be robust
					$arg_number_length = strspn($sql, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_', $new_pos_back + $named_seperator_length) + $named_seperator_length;
					$arg_number = substr($sql, $new_pos_back + $named_seperator_length, $arg_number_length - $named_seperator_length);

					if (count($args_all) != 1 || !is_array($args_all[0])) {
						ErrorHandler::msgError("If you use named parameters, the second argument must be an array of parameters");
					}

					if (!array_key_exists($arg_number, $args_all[0])) {
						ErrorHandler::msgError("Non existent argument reference (arg $arg_number): $sql");
					}

					$arg = $args_all[0][$arg_number];
				} else {
					$arg = array_shift($args);
				}

				if ($new_pos > 0) {
					$chunkyQuery[] = substr($sql, 0, $new_pos);
				}

				if (is_object($arg) && ($arg instanceof DB_Where)) {
					[$clause_sql, $clause_args] = $arg->textAndArgs();
					array_unshift($clause_args, $clause_sql);
					$preparsed_sql = self::preparseQueryParams(...$clause_args);
					$chunkyQuery = array_merge($chunkyQuery, $preparsed_sql);
				} else {
					$chunkyQuery[] = [
						'type' => $type,
						'value' => $arg
					];
				}

				$sql = substr($sql, $new_pos_back + $arg_number_length);
				$pos_adj -= $new_pos_back + $arg_number_length;
			}

			if ($sql != '') {
				$chunkyQuery[] = $sql;
			}

			return $chunkyQuery;
		}

		/**
		 * Преобразовать параметризованный запрос в итоговый SQL-строку
		 * @param string $sql SQL-строка с параметрами
		 * @param mixed ...$args Аргументы для подстановки
		 * @return string Готовый SQL-запрос
		 */
		public static function parseQueryParams(...$args)
		{
			$chunkyQuery = self::preparseQueryParams(...$args);
			$query = '';
			$array_types = ['ls', 'li', 'ld', 'lb', 'll', 'lt', 'l?', 'll?', 'hc', 'ha', 'ho'];

			foreach ($chunkyQuery as $chunk) {
				if (is_string($chunk)) {
					$query .= $chunk;
					continue;
				}

				$type = $chunk['type'];
				$arg = $chunk['value'];
				$result = '';

				if ($type != '?') {
					$is_array_type = in_array($type, $array_types, true);
					if ($is_array_type && !is_array($arg)) {
						ErrorHandler::msgError("Badly formatted SQL query: Expected array, got scalar instead!");
					} else if (!$is_array_type && is_array($arg)) {
						ErrorHandler::msgError("Badly formatted SQL query: Expected scalar, got array instead!");
					}
				}

				switch ($type) {
					case 's':
						$result = self::escape($arg);
						break;
					case 'i':
						$result = self::intval($arg);
						break;
					case 'd':
						$result = (float) $arg;
						break;
					case 'b':
						$result = self::formatTableName($arg);
						break;
					case 'l':
						$result = $arg;
						break;
					case 'ss':
						$result = self::escape("%" . str_replace(['%', '_'], ['\%', '\_'], $arg) . "%");
						break;
					case 'ssb':
						$result = self::escape(str_replace(['%', '_'], ['\%', '\_'], $arg) . "%");
						break;
					case 'sse':
						$result = self::escape("%" . str_replace(['%', '_'], ['\%', '\_'], $arg));
						break;
					case 't':
						$result = self::escape(self::parseTS($arg));
						break;
					case 'ls':
						$result = array_map([self::class, 'escape'], $arg);
						break;
					case 'li':
						$result = array_map([self::class, 'intval'], $arg);
						break;
					case 'ld':
						$result = array_map('doubleval', $arg);
						break;
					case 'lb':
						$result = array_map([self::class, 'formatTableName'], $arg);
						break;
					case 'll':
						$result = $arg;
						break;
					case 'lt':
						$result = array_map([self::class, 'escape'], array_map([self::class, 'parseTS'], $arg));
						break;
					case '?':
						$result = self::sanitize($arg);
						break;
					case 'l?':
						$result = self::sanitize($arg, 'list');
						break;
					case 'll?':
						$result = self::sanitize($arg, 'doublelist');
						break;
					case 'hc':
						$result = self::sanitize($arg, 'hash');
						break;
					case 'ha':
						$result = self::sanitize($arg, 'hash', ' AND ');
						break;
					case 'ho':
						$result = self::sanitize($arg, 'hash', ' OR ');
						break;
					default:
						ErrorHandler::msgError("Badly formatted SQL query: Invalid DB param $type");
				}

				if (is_array($result)) {
					$result = '(' . implode(',', $result) . ')';
				}

				$query .= $result;
			}

			return $query;
		}

		/**
		 * Экранировать значение для безопасного использования в SQL
		 * Оборачивает строки в кавычки
		 * @param mixed $value Значение для экранирования
		 * @return string Экранированное значение
		 */
		public static function escape($value)
		{
			$driver = ConnectionManager::getDriver();
			if (!$driver) {
				$driver = ConnectionManager::connect();
			}

			// Реальные числовые типы (int/float) — без кавычек.
			// Строки оборачиваем всегда, даже числовые: иначе теряются ведущие нули
			// (штрихкоды, SKU) и точность на длинных числах. Для MySQL '5' и 5
			// в сравнении эквивалентны, так что корректность не страдает.
			if (is_int($value) || is_float($value)) {
				return $value;
			}

			return "'" . $driver->escape((string) $value) . "'";
		}

		/**
		 * Санитизировать значение (без обертки в кавычки)
		 * @param string $value Значение для санитизации
		 * @return string Санитизированное значение
		 */
		public static function safe($value)
		{
			if (is_numeric($value))
				return $value;
			$driver = ConnectionManager::getDriver() ?? ConnectionManager::connect();
			return $driver->escape((string) $value);
		}

		/**
		 * Преобразовать значение в SQL-литерал
		 * @param mixed $value Значение любого типа
		 * @return string SQL-литерал
		 * @throws Exception Если тип значения не поддерживается
		 */
		public static function quote($value)
		{
			if (is_string($value))
				return self::escape($value);
			if ($value === true)
				return "'1'";
			if ($value === false)
				return "'0'";
			if (is_null($value))
				return 'NULL';
			if (is_int($value))
				return (int) $value;
			if (is_float($value))
				return sprintf("%F", $value);

			if (is_array($value)) {
				$val = [];
				foreach ($value as $k => $v) {
					$val[$k] = self::quote($v);
				}

				return '(' . implode(',', $val) . ')';
			}

			throw new Exception("Wrong argument type '" . gettype($value) . "' (expected string) for quote()");
		}

		/**
		 * Санитизировать значение с учетом типа
		 * @param mixed $value Значение для санитизации
		 * @param string $type Тип санитизации (basic, list, doublelist, hash)
		 * @param string $hashjoin Строка соединения для hash-типа (по умолчанию ', ')
		 * @return string Санитизированное значение
		 */
		public static function sanitize($value, $type = 'basic', $hashjoin = ', ')
		{
			if ($type == 'basic') {
				if (is_object($value)) {
					if ($value instanceof DB_Eval)
						return $value->text;
					if ($value instanceof DateTime)
						return self::escape($value->format('Y-m-d H:i:s'));
					return self::escape($value);
				}

				if (is_null($value))
					return self::$usenull ? 'NULL' : "''";
				if (is_bool($value))
					return ($value ? 1 : 0);
				if (is_int($value) || is_float($value))
					return $value;
				if (is_array($value))
					return "''";

				return self::escape($value);
			}

			if ($type == 'list') {
				if (is_array($value)) {
					return '(' . implode(', ', array_map([self::class, 'sanitize'], array_values($value))) . ')';
				}

				ErrorHandler::msgError("Expected array parameter, got something different!");
			}

			if ($type == 'doublelist') {
				if (is_array($value) && is_array(current($value))) {
					$clean = [];
					foreach ($value as $sub) {
						$clean[] = self::sanitize($sub, 'list');
					}

					return implode(', ', $clean);
				}

				ErrorHandler::msgError("Expected double array parameter, got something different!");
			}

			if ($type == 'hash') {
				if (is_array($value)) {
					$pairs = [];
					foreach ($value as $k => $v) {
						$pairs[] = self::formatTableName($k) . '=' . self::sanitize($v);
					}

					return implode($hashjoin, $pairs);
				}

				ErrorHandler::msgError("Expected hash parameter!");
			}

			return false;
		}

		/**
		 * Форматировать имя таблицы с обратными кавычками
		 * Поддерживает составные имена (database.table)
		 * @param string $table Имя таблицы
		 * @return string Отформатированное имя таблицы
		 */
		public static function formatTableName($table)
		{
			$table = trim($table, '`');
			if (strpos($table, '.')) {
				return implode('.', array_map([self::class, 'formatTableName'], explode('.', $table)));
			}

			return '`' . str_replace('`', '``', $table) . '`';
		}

		/**
		 * Преобразовать значение в целое число с учетом разрядности платформы
		 * @param mixed $var Значение для преобразования
		 * @return int Целое число
		 */
		public static function intval($var)
		{
			return (PHP_INT_SIZE == 8) ? (int) $var : floor((float) $var);
		}

		/**
		 * Преобразовать timestamp в формат MySQL DATETIME
		 * @param mixed $ts Timestamp (строка, DateTime или число)
		 * @return string Форматированная дата в формате 'Y-m-d H:i:s'
		 */
		public static function parseTS($ts)
		{
			if (is_string($ts))
				return date('Y-m-d H:i:s', strtotime($ts));
			if ($ts instanceof DateTime)
				return $ts->format('Y-m-d H:i:s');
			if (is_numeric($ts))
				return date('Y-m-d H:i:s', $ts);
			return $ts;
		}
	}
