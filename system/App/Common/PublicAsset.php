<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicAsset.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class PublicAsset
	{
		protected static $revisions = array();

		public static function module($code, $file)
		{
			$code = strtolower(trim((string) $code));
			$file = ltrim(str_replace('\\', '/', (string) $file), '/');
			if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code)
				|| !preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]{0,190}$#', $file)
				|| strpos($file, '..') !== false) {
				throw new \InvalidArgumentException('Некорректный путь публичного ассета модуля');
			}

			$key = $code . '/' . $file;
			if (!isset(self::$revisions[$key])) {
				$path = BASEPATH . '/modules/' . $code . '/app/assets/' . $file;
				$checksum = is_file($path) ? sha1_file($path) : false;
				self::$revisions[$key] = is_string($checksum) && $checksum !== '' ? substr($checksum, 0, 12) : 'missing';
			}

			$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
			return $base . '/modules/' . $code . '/app/assets/' . $file . '?v=' . self::$revisions[$key];
		}
	}
