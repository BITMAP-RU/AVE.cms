<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Settings/migrations/005_order_system_navigation.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Helpers\Json;

	return function (array $context) {
		$table = SystemTables::table('settings');
		$row = DB::query(
			'SELECT value FROM ' . $table . ' WHERE param = %s LIMIT 1',
			'adminx_interface.navigation'
		)->getAssoc();
		if (!$row) {
			return 0;
		}

		$items = Json::toArray((string) $row['value']);
		if (!is_array($items)) {
			return 0;
		}

		$preferred = array('roles', 'users', 'customers', 'settings', 'database', 'events');
		$selected = array();
		$remaining = array();
		$insertAt = null;
		foreach ($items as $item) {
			$code = is_array($item) && isset($item['code']) ? (string) $item['code'] : '';
			if (in_array($code, $preferred, true)) {
				if ($insertAt === null) {
					$insertAt = count($remaining);
				}

				$selected[$code] = $item;
				continue;
			}

			$remaining[] = $item;
		}

		$ordered = array();
		foreach ($preferred as $code) {
			if (isset($selected[$code])) {
				$ordered[] = $selected[$code];
			}
		}

		if (!$ordered) {
			return 0;
		}

		array_splice($remaining, $insertAt === null ? count($remaining) : $insertAt, 0, $ordered);
		DB::Update($table, array(
			'value' => Json::encode($remaining, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		), 'param = %s', 'adminx_interface.navigation');

		return 1;
	};
