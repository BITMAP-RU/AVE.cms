<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/AdminLocation.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Resolves the renameable administration directory for shared runtime code. */
	class AdminLocation
	{
		protected static $directory;

		protected function __construct()
		{
			//
		}

		public static function directory()
		{
			if (defined('ADMINX_PATH')) {
				return basename(str_replace('\\', '/', (string) ADMINX_PATH));
			}

			if (self::$directory !== null) {
				return self::$directory;
			}

			$directory = trim((string) PublicConfiguration::value('admin_directory', 'adminx'));
			if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,47}$/', $directory)) {
				$directory = 'adminx';
			}

			self::$directory = $directory;
			return self::$directory;
		}

		public static function path($suffix = '')
		{
			$base = defined('ADMINX_PATH')
				? rtrim(str_replace('\\', '/', (string) ADMINX_PATH), '/')
				: rtrim(str_replace('\\', '/', BASEPATH), '/') . '/' . self::directory();

			return $base . self::suffix($suffix);
		}

		public static function url($suffix = '')
		{
			$base = defined('ADMINX_BASE')
				? rtrim((string) ADMINX_BASE, '/')
				: '/' . rawurlencode(self::directory());

			return $base . self::suffix($suffix);
		}

		protected static function suffix($suffix)
		{
			$suffix = trim(str_replace('\\', '/', (string) $suffix));
			return $suffix === '' ? '' : '/' . ltrim($suffix, '/');
		}
	}
