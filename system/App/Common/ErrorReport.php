<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ErrorReport.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Logs an internal exception and exposes only a stable support reference. */
	class ErrorReport
	{
		public static function capture(\Throwable $error, $scope = 'APP', array $context = array())
		{
			$reference = self::reference($error, $scope);
			$details = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
			error_log('[' . $reference . '] ' . get_class($error) . ': ' . $error->getMessage()
				. ' in ' . $error->getFile() . ':' . $error->getLine() . $details);

			return $reference;
		}

		public static function publicMessage($message, \Throwable $error, $scope = 'APP', array $context = array())
		{
			return rtrim((string) $message, " .\t\n\r\0\x0B") . '. Код обращения: '
				. self::capture($error, $scope, $context);
		}

		public static function reference(\Throwable $error, $scope = 'APP')
		{
			$current = $error;
			while ($current instanceof \Throwable) {
				if (preg_match('/\[SQL\s+([a-f0-9]{16})\]/i', $current->getMessage(), $match)) {
					return 'DB-' . strtoupper($match[1]);
				}

				if ($current instanceof \DB_Exception
					|| (class_exists('mysqli_sql_exception', false) && $current instanceof \mysqli_sql_exception)
					|| preg_match('/(?:\bMySQL\s*#|\bSQLSTATE\b)/i', $current->getMessage())) {
					return 'DB-' . strtoupper(substr(hash('sha256', self::fingerprint($current)), 0, 16));
				}

				$current = $current->getPrevious();
			}

			$scope = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $scope));
			if ($scope === '') { $scope = 'APP'; }

			return substr($scope, 0, 12) . '-' . strtoupper(substr(hash('sha256', self::fingerprint($error)), 0, 16));
		}

		public static function isDatabase(\Throwable $error)
		{
			return strpos(self::reference($error), 'DB-') === 0;
		}

		protected static function fingerprint(\Throwable $error)
		{
			return get_class($error) . '|' . $error->getMessage() . '|'
				. $error->getFile() . '|' . $error->getLine();
		}
	}
