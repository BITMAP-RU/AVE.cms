<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/migrations/013_group_form_conditions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('rubric_fields_group');
		if (DatabaseSchema::columnExists($table, 'group_settings')) {
			return 1;
		}

		\DB::query('ALTER TABLE ' . $table . ' ADD COLUMN group_settings MEDIUMTEXT NULL AFTER group_position');
		return 2;
	};
