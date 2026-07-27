<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\PublicShellTables;

	/** Read-only access to the public wide settings row during schema migration. */
	class PublicSettings
	{
		protected static $values;

		public static function all()
		{
			if (self::$values === null) {
				$row = DB::query(
					'SELECT * FROM %b LIMIT 1',
					PublicShellTables::table('settings')
				)->getAssoc();
				self::$values = is_array($row) ? $row : array();
				self::normalizeBreadcrumbKeys();
			}

			return self::$values;
		}

		public static function get($key = '', $default = null)
		{
			$values = self::all();
			if ($key === '') {
				return $values;
			}

			return array_key_exists($key, $values) ? $values[$key] : $default;
		}

		public static function reset()
		{
			self::$values = null;
		}

		/** Keeps public rendering available while breadcrumb columns are renamed. */
		protected static function normalizeBreadcrumbKeys()
		{
			foreach (array('bread_separator' => 'bread_sepparator', 'bread_separator_use' => 'bread_sepparator_use') as $canonical => $legacy) {
				if (!array_key_exists($canonical, self::$values) && array_key_exists($legacy, self::$values)) {
					self::$values[$canonical] = self::$values[$legacy];
				}

				if (!array_key_exists($legacy, self::$values) && array_key_exists($canonical, self::$values)) {
					self::$values[$legacy] = self::$values[$canonical];
				}
			}
		}
	}
