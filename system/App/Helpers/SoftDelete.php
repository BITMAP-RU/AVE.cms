<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/SoftDelete.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * SQL helpers for the two soft-delete conventions used by the migrated app:
	 * boolean flags (`is_deleted`/legacy `deleted`) and nullable `deleted_at`.
	 */
	class SoftDelete
	{
		public static function active($alias = null, $column = 'deleted_at')
		{
			return self::condition($alias, $column, false);
		}

		public static function deleted($alias = null, $column = 'deleted_at')
		{
			return self::condition($alias, $column, true);
		}

		public static function documentActive($alias = null)
		{
			return self::active($alias, 'is_deleted');
		}

		public static function documentDeleted($alias = null)
		{
			return self::deleted($alias, 'is_deleted');
		}

		public static function condition($alias, $column, $deleted)
		{
			$expr = self::identifier($alias, $column);
			if (in_array((string) $column, array('is_deleted', 'deleted'), true)) {
				return $expr . ' = ' . ($deleted ? '1' : '0');
			}

			return $expr . ($deleted ? ' IS NOT NULL' : ' IS NULL');
		}

		protected static function identifier($alias, $column)
		{
			$column = self::safeIdentifier($column ?: 'deleted_at');
			$alias = self::safeIdentifier($alias);
			return $alias !== '' ? $alias . '.' . $column : $column;
		}

		protected static function safeIdentifier($value)
		{
			$value = trim((string) $value);
			return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) ? $value : '';
		}
	}
