<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/013_sort_rules.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('request');
		if (DatabaseSchema::columnExists($table, 'request_sort_rules')) {
			return 0;
		}

		\DB::query(
			'ALTER TABLE %b ADD COLUMN `request_sort_rules` MEDIUMTEXT NULL AFTER `request_order_tiebreaker`',
			$table
		);
		return 1;
	};
