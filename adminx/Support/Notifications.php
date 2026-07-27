<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Notifications.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Permission;

	/**
	 * Пустая системная сводка. Прикладные источники подключаются владельцами
	 * через ModuleExtensions и исчезают вместе с модулем.
	 */
	class Notifications
	{
		/** @return array{total:int,orders:int,messages:int,items:array} */
		public static function summary($base)
		{
			$summary = array(
				'total'    => 0,
				'orders'   => 0,
				'messages' => 0,
				'items'    => array(),
			);

			$sqlErrors = SystemHealth::sqlErrorCount();
			if ($sqlErrors > 0 && Permission::check('view_events')) {
				$summary['total'] += $sqlErrors;
				$summary['database_errors'] = $sqlErrors;
				$summary['items'][] = array(
					'key' => 'database_errors',
					'title' => AdminLocale::text('notification_database_errors', 'Ошибки базы данных'),
					'text' => AdminLocale::text(
						'notification_database_errors_hint',
						'Проверьте SQL-журнал системных событий.'
					),
					'url' => rtrim((string) $base, '/') . '/events?source=sql',
					'count' => $sqlErrors,
					'icon' => 'ti ti-database-exclamation',
					'bg' => '#feecec',
					'fg' => '#dc2626',
				);
			}

			return $summary;
		}
	}
