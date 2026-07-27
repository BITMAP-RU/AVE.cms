<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ContentTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves content-domain tables independently from the global module prefix. */
	class ContentTables
	{
		public static function table($suffix)
		{
			$suffix = (string) $suffix;
			if (!preg_match('/^[a-z0-9_]+$/', $suffix)) {
				throw new \InvalidArgumentException('Некорректное имя контентной таблицы');
			}

			return self::prefix() . '_' . $suffix;
		}

		public static function prefix() { return PublicConfiguration::prefix('content'); }
		public static function mode() { return 'native'; }
		public static function reset() { /* PublicConfiguration is request-scoped. */ }
	}
