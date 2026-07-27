<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/migrations/010_form_conditions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('rubrics');
		$exists = (bool) \DB::query('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'rubric_form_conditions')->getValue();
		if ($exists) {
			return 1;
		}

		\DB::query(
			'ALTER TABLE ' . $table
			. ' ADD COLUMN rubric_form_conditions TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER rubric_meta_gen'
		);
		return 2;
	};
