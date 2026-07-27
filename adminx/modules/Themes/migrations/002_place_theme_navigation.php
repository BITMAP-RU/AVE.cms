<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Themes/migrations/002_place_theme_navigation.php
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
		$row = DB::query('SELECT value FROM ' . $table . ' WHERE param=%s LIMIT 1', 'adminx_interface.navigation')->getAssoc();
		if (!$row) { return 0; }

		$items = Json::toArray((string) $row['value']);
		if (!is_array($items)) { return 0; }
		foreach ($items as $item) {
			if (is_array($item) && isset($item['code']) && (string) $item['code'] === 'themes') { return 0; }
		}

		$insertAt = count($items);
		foreach ($items as $index => $item) {
			if (is_array($item) && isset($item['code']) && (string) $item['code'] === 'templates') {
				$insertAt = $index;
				break;
			}
		}

		array_splice($items, $insertAt, 0, array(array('code' => 'themes', 'visible' => true)));
		DB::Update($table, array('value' => Json::encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'param=%s', 'adminx_interface.navigation');
		return 1;
	};
