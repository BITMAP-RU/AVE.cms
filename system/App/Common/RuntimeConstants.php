<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/RuntimeConstants.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Loads typed runtime constants from the native system table. */
	class RuntimeConstants
	{
		protected static $loaded = false;

		public static function load()
		{
			if (self::$loaded) {
				return;
			}

			$release = is_file(BASEPATH . '/system/release.php')
				? require BASEPATH . '/system/release.php'
				: array();
			self::define('APP_NAME', isset($release['product']) ? $release['product'] : 'AVE.cms');
			self::define('APP_VERSION', isset($release['version']) ? $release['version'] : '3.3');
			self::define('APP_BUILD', isset($release['build']) ? $release['build'] : '0.11');
			self::define('APP_SITE', 'https://ave-cms.ru');
			self::define('APP_INFO', '<a target="_blank" href="https://ave-cms.ru/">Ave-Cms.Ru</a> &copy; 2007-' . date('Y'));
			self::define('PLUGINS_DIR', BASEPATH . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR);
			self::define('SESSION_DIR', '/tmp/sessions');

			foreach (self::storedValues() as $name => $value) {
				self::define($name, $value);
			}

			foreach (self::defaults() as $name => $value) {
				self::define($name, $value);
			}

			self::$loaded = true;
		}

		protected static function storedValues()
		{
			try {
				$rows = DB::query(
					'SELECT name, value, type FROM ' . SystemTables::table('constants') . ' ORDER BY name ASC'
				)->getAll();
			} catch (\Throwable $e) {
				return array();
			}

			$values = array();
			foreach ($rows ?: array() as $row) {
				$row = (array) $row;
				$name = isset($row['name']) ? strtoupper(trim((string) $row['name'])) : '';
				if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
					continue;
				}

				$type = isset($row['type']) ? strtolower((string) $row['type']) : 'string';
				if ($type === 'bool') {
					$values[$name] = (string) $row['value'] === '1';
				} elseif ($type === 'int' || $type === 'integer') {
					$values[$name] = (int) $row['value'];
				} else {
					$values[$name] = (string) $row['value'];
				}
			}

			return $values;
		}

		protected static function define($name, $value)
		{
			if (!defined($name)) {
				define($name, $value);
			}
		}

		protected static function defaults()
		{
			return RuntimeConstantSchema::defaults();
		}
	}
