<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/010_native_audit_result.php
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
		if (!DatabaseSchema::columnExists($table, 'request_native_audit_status')) {
			\DB::query(
				"ALTER TABLE %b ADD COLUMN `request_native_audit_status` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_general_ci NULL AFTER `request_native_verified_hash`",
				$table
			);
			$changed++;
		}

		if (!DatabaseSchema::columnExists($table, 'request_native_audit_details')) {
			\DB::query(
				'ALTER TABLE %b ADD COLUMN `request_native_audit_details` MEDIUMTEXT NULL AFTER `request_native_audit_status`',
				$table
			);
			$changed++;
		}

		return $changed;
	};
