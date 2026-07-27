<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CsvLogWriter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Writes the historical CSV event formats consumed by the events section. */
	class CsvLogWriter
	{
		public static function event($message, $type = 0, $rubric = 0)
		{
			self::write('log.csv', array(
				time(), self::server('REMOTE_ADDR'), self::server('REQUEST_URI'),
				isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0,
				isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Anonymous',
				(string) $message, (int) $type, (int) $rubric,
			));
		}

		public static function sql($message)
		{
			self::write('sql.csv', array(
				time(), self::server('REMOTE_ADDR'), self::server('REQUEST_URI'),
				isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0,
				isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Anonymous',
				base64_encode(serialize($message)),
			));
		}

		public static function notFound()
		{
			$uri = self::server('REQUEST_URI');
			self::write('404.csv', array(
				time(), self::server('REMOTE_ADDR'), $uri,
				self::server('HTTP_USER_AGENT'), self::server('HTTP_REFERER'), $uri,
			));
		}

		protected static function write($name, array $row)
		{
			$directory = BASEPATH . '/tmp/logs';
			if (!is_dir($directory)) {
				@mkdir($directory, 0775, true);
			}

			$handle = @fopen($directory . '/' . $name, 'ab');
			if (!$handle) {
				return false;
			}

			if (flock($handle, LOCK_EX)) {
				fputcsv($handle, $row);
				flock($handle, LOCK_UN);
			}

			fclose($handle);
			return true;
		}

		protected static function server($key)
		{
			return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
		}
	}
