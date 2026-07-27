<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/migrations/007_result_contract.php
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
		if (DatabaseSchema::columnExists($table, 'request_result_contract')) { return 0; }

		\DB::query(
			'ALTER TABLE %b ADD COLUMN `request_result_contract` MEDIUMTEXT NULL AFTER `request_description`',
			$table
		);

		return 1;
	};
