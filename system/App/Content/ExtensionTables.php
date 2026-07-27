<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ExtensionTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves data tables owned by installed extensions. */
	class ExtensionTables
	{
		protected static $allowed = array(
			'module_rss',
		);

		public static function table($suffix)
		{
			if (!in_array((string) $suffix, self::$allowed, true)) {
				throw new \InvalidArgumentException('Некорректная таблица расширений');
			}

			return PublicConfiguration::prefix('module') . '_' . $suffix;
		}

	}
