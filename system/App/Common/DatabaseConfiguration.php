<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/DatabaseConfiguration.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Read-only access to the database connection configuration. */
	class DatabaseConfiguration
	{
		protected static $values;

		public static function all()
		{
			if (self::$values !== null) {
				return self::$values;
			}

			$config = array();
			$file = BASEPATH . '/configs/db.config.php';
			if (is_file($file)) {
				include $file;
			}

			self::$values = is_array($config) ? $config : array();
			return self::$values;
		}

		public static function value($key, $default = null)
		{
			$config = self::all();
			return array_key_exists((string) $key, $config) ? $config[(string) $key] : $default;
		}

		public static function prefix()
		{
			$prefix = trim((string) self::value('dbpref', ''));
			if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
				throw new \RuntimeException('Invalid database table prefix');
			}

			return $prefix;
		}
	}
