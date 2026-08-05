<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/NotificationProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Modules;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Установленные модули, для которых в каталоге лежит версия новее. */
	class NotificationProvider
	{
		public static function items()
		{
			$count = self::updatable();
			if ($count < 1) { return array(); }

			return array(array(
				'key' => 'module_updates',
				'icon' => 'ti ti-package-import',
				'bg' => '#ede9fe',
				'fg' => '#6d28d9',
				'count' => $count,
				'title' => 'Обновления модулей',
				'text' => 'Готовы к установке: ' . $count,
				'url' => '/modules?filter=update',
			));
		}

		protected static function updatable()
		{
			try {
				$catalog = ModuleRepository::cachedCatalog();
				if (!is_array($catalog) || !empty($catalog['error']) || empty($catalog['items'])) { return 0; }
				$count = 0;
				foreach ($catalog['items'] as $item) {
					if (isset($item['status']) && (string) $item['status'] === 'update') { $count++; }
				}

				return $count;
			} catch (\Throwable $e) {
				return 0;
			}
		}
	}
