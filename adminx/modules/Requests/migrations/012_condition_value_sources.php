<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/012_condition_value_sources.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('request_conditions');
		$changes = 0;
		$columns = array(
			'condition_value_source' => "VARCHAR(16) NOT NULL DEFAULT '' AFTER `condition_value`",
			'condition_value_key' => "VARCHAR(64) NOT NULL DEFAULT '' AFTER `condition_value_source`",
			'condition_value_config' => "TEXT NULL AFTER `condition_value_key`",
		);

		foreach ($columns as $column => $definition) {
			if (DatabaseSchema::columnExists($table, $column)) { continue; }
			\DB::query('ALTER TABLE %b ADD COLUMN `' . $column . '` ' . $definition, $table);
			$changes++;
		}

		return $changes;
	};
