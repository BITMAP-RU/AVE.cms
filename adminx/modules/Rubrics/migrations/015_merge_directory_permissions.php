<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/migrations/015_merge_directory_permissions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;

	return function () {
		$permissions = SystemTables::table('permissions');
		$rolePermissions = SystemTables::table('role_permissions');
		$operations = 0;

		if (DatabaseSchema::tableExists($rolePermissions)) {
			DB::query(
				'INSERT IGNORE INTO `' . $rolePermissions . '` (role_id, permission_code)'
					. ' SELECT role_id, %s FROM `' . $rolePermissions . '` WHERE permission_code = %s',
				'view_rubrics',
				'view_directories'
			);
			DB::query('DELETE FROM `' . $rolePermissions . '` WHERE permission_code = %s', 'view_directories');
			$operations += 2;
		}

		if (DatabaseSchema::tableExists($permissions)) {
			DB::query('DELETE FROM `' . $permissions . '` WHERE code = %s', 'view_directories');
			$operations++;
		}

		return $operations;
	};
