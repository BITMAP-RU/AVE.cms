<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/PublicShellTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves public rendering settings and sessions without colliding with Adminx. */
	class PublicShellTables
	{
		protected static $allowed = array('settings', 'sessions');

		public static function table($suffix)
		{
			if (!in_array((string) $suffix, self::$allowed, true)) {
				throw new \InvalidArgumentException('Некорректная таблица публичного shell');
			}

			return PublicConfiguration::prefix('public_shell') . '_' . $suffix;
		}

		public static function compatibilityPrefix()
		{
			return substr(self::table('settings'), 0, -strlen('_settings'));
		}

	}
