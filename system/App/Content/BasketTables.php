<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/BasketTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves basket-owned tables independently from content and other modules. */
	class BasketTables
	{
		public static function table($suffix)
		{
			if (!preg_match('/^module_basket(?:_[a-z0-9_]+)?$/', (string) $suffix)) {
				throw new \InvalidArgumentException('Некорректная таблица корзины');
			}

			return PublicConfiguration::prefix('basket') . '_' . $suffix;
		}

		public static function prefix()
		{
			$table = self::table('module_basket');
			return substr($table, 0, -strlen('_module_basket'));
		}

	}
