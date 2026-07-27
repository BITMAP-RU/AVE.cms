<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/008_preview_renderer.php
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
		if (DatabaseSchema::columnExists($table, 'request_preview_renderer')) { return 0; }

		\DB::query(
			"ALTER TABLE %b ADD COLUMN `request_preview_renderer` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'data_cards' AFTER `request_result_contract`",
			$table
		);

		return 1;
	};
