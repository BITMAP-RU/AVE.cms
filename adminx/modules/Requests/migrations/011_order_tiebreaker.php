<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/011_order_tiebreaker.php
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
		if (DatabaseSchema::columnExists($table, 'request_order_tiebreaker')) {
			return 0;
		}

		\DB::query(
			"ALTER TABLE %b ADD COLUMN `request_order_tiebreaker` VARCHAR(4) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' AFTER `request_asc_desc`",
			$table
		);
		return 1;
	};
