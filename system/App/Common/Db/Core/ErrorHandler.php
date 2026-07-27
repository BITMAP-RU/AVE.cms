<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/ErrorHandler.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use App\Common\CsvLogWriter;
	use DB_Exception;

	/**
	 * Обработчик ошибок базы данных
	 */
	class ErrorHandler
	{
		public static $throw_exception_on_error = false;
		public static $throw_exception_on_nonsql_error = false;
		public static $nonsql_error_handler = null;

		/**
		 * Обработать ошибку SQL-запроса, читая реальную ошибку из драйвера
		 * @param string $sql Запрос, вызвавший ошибку
		 */
		public static function typeError(string $sql = ''): void
		{
			$driver = ConnectionManager::getDriver();
			$errno  = $driver ? $driver->errno() : 0;
			$error  = $driver ? $driver->error() : 'unknown';

			$message = $errno ? "MySQL #{$errno}: " . self::safeErrorMessage($error) : 'MySQL query error';
			$query = self::safeQueryDetails($sql);

			try {
				CsvLogWriter::sql(array(
					'sql_error' => $message,
					'query' => $query['query'],
					'query_fingerprint' => $query['fingerprint'],
					'errno' => $errno,
					'type' => 'sql',
				));
			} catch (\Throwable $e) {
				// A logging failure must not hide the original database error.
			}

			if (self::$throw_exception_on_error) {
				throw new DB_Exception($message . ' [SQL ' . $query['fingerprint'] . ']');
			}

			$handler = is_callable(self::$nonsql_error_handler)
				? self::$nonsql_error_handler
				: 'db_error_handler';

			if (is_callable($handler)) {
				$handler([
					'type'  => 'sql',
					'error' => $message,
					'query' => $query['query'],
					'query_fingerprint' => $query['fingerprint'],
					'errno' => $errno,
				]);
			}
		}

		/** Return a diagnostic SQL shape without literal values or credentials. */
		public static function safeQueryDetails($sql)
		{
			$sql = trim((string) $sql);
			$safe = preg_replace('/\b(?:x|b)\'[^\']*\'/i', '?', $sql);
			$safe = preg_replace('/\'(?:\'\'|\\\\.|[^\'\\\\])*\'/s', '?', $safe);
			$safe = preg_replace('/"(?:""|\\\\.|[^"\\\\])*"/s', '?', $safe);
			$safe = preg_replace('/\b0x[0-9a-f]+\b/i', '?', $safe);
			$safe = preg_replace('/(?<![A-Za-z0-9_])[-+]?\d+(?:\.\d+)?(?:e[-+]?\d+)?(?![A-Za-z0-9_])/i', '?', $safe);
			$safe = trim((string) preg_replace('/\s+/', ' ', $safe));
			if (strlen($safe) > 2000) { $safe = substr($safe, 0, 1997) . '...'; }

			return array(
				'query' => $safe,
				'fingerprint' => substr(hash('sha256', strtolower($safe)), 0, 16),
			);
		}

		public static function safeErrorMessage($message)
		{
			$message = preg_replace('/\'(?:\'\'|\\\\.|[^\'\\\\])*\'/s', '?', (string) $message);
			$message = preg_replace('/"(?:""|\\\\.|[^"\\\\])*"/s', '?', $message);
			$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $message);
			return mb_substr(trim((string) $message), 0, 1000, 'UTF-8');
		}

		/** Return a stable public reference without exposing SQL or driver details. */
		public static function publicReference(array $params)
		{
			$fingerprint = isset($params['query_fingerprint'])
				? strtolower(trim((string) $params['query_fingerprint']))
				: '';
			if (preg_match('/^[a-f0-9]{16}$/', $fingerprint) !== 1) {
				$fingerprint = substr(hash('sha256', (string) (isset($params['error']) ? $params['error'] : 'database')), 0, 16);
			}

			return 'DB-' . strtoupper($fingerprint);
		}

		/**
		 * Обработать не-SQL ошибку (неправильный запрос, отсутствующий аргумент и т.д.)
		 */
		public static function msgError(string $message): void
		{
			if (self::$throw_exception_on_nonsql_error) {
				throw new DB_Exception($message);
			}

			$handler = is_callable(self::$nonsql_error_handler)
				? self::$nonsql_error_handler
				: 'db_error_handler';

			if (is_callable($handler)) {
				$handler([
					'type'  => 'nonsql',
					'error' => $message,
				]);
			}
		}
	}
