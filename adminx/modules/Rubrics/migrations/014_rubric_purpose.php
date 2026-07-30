<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/migrations/014_rubric_purpose.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function () {
		$table = ContentTables::table('rubrics');
		$operations = 0;

		if (!DatabaseSchema::columnExists($table, 'rubric_purpose')) {
			DB::query(
				"ALTER TABLE {$table} ADD COLUMN rubric_purpose VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT 'content' AFTER rubric_description"
			);
			$operations++;
		}

		$index = DB::query("SHOW INDEX FROM {$table} WHERE Key_name = 'idx_rubric_purpose'")->getAssoc();
		if (!$index) {
			DB::query("ALTER TABLE {$table} ADD KEY idx_rubric_purpose (rubric_purpose)");
			$operations++;
		}

		return $operations;
	};
