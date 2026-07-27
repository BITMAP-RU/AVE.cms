<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/CatalogTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves catalog-owned tables independently from module and content data. */
	class CatalogTables
	{
		public static function table($suffix)
		{
			if (!preg_match('/^[a-z0-9_]+$/', (string) $suffix)) { throw new \InvalidArgumentException('Некорректная таблица каталога'); }
			return PublicConfiguration::prefix('catalog') . '_' . $suffix;
		}
	}
