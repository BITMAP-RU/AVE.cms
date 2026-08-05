<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Updates/NotificationProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Updates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\CoreUpdate\UpdateRepository;

	/**
	 * Доступные патчи ядра.
	 *
	 * Читаем только прогретый кеш каталога: сетевой запрос на отрисовке
	 * страницы недопустим. Кеш обновляет проверка «Обновления ядра» модуля
	 * мониторинга, а также открытие самого раздела обновлений.
	 */
	class NotificationProvider
	{
		public static function items()
		{
			$available = self::available();
			if ($available < 1) { return array(); }

			return array(array(
				'key' => 'core_updates',
				'icon' => 'ti ti-download',
				'bg' => '#dbeafe',
				'fg' => '#1d4ed8',
				'count' => $available,
				'title' => 'Обновление ядра',
				'text' => $available > 1 ? 'Доступно патчей: ' . $available : 'Доступен новый патч',
				'url' => '/system/updates',
			));
		}

		protected static function available()
		{
			try {
				$catalog = UpdateRepository::cachedCatalog();
				if (!is_array($catalog) || !empty($catalog['error'])) { return 0; }
				return isset($catalog['available']) ? (int) $catalog['available'] : 0;
			} catch (\Throwable $e) {
				return 0;
			}
		}
	}
