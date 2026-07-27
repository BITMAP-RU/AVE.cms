<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/009_native_executor.php
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
		$changed = 0;
		if (!DatabaseSchema::columnExists($table, 'request_executor_mode')) {
			\DB::query(
				"ALTER TABLE %b ADD COLUMN `request_executor_mode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'legacy' AFTER `request_preview_renderer`",
				$table
			);
			$changed++;
		}

		if (!DatabaseSchema::columnExists($table, 'request_native_plan')) {
			\DB::query(
				'ALTER TABLE %b ADD COLUMN `request_native_plan` MEDIUMTEXT NULL AFTER `request_executor_mode`',
				$table
			);
			$changed++;
		}

		if (!DatabaseSchema::columnExists($table, 'request_native_verified_hash')) {
			\DB::query(
				'ALTER TABLE %b ADD COLUMN `request_native_verified_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NULL AFTER `request_native_plan`',
				$table
			);
			$changed++;
		}

		return $changed;
	};
