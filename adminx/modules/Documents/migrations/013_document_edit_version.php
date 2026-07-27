<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/013_document_edit_version.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('documents');
		if (DatabaseSchema::columnExists($table, 'document_version')) { return 0; }

		\DB::query(
			'ALTER TABLE %b ADD COLUMN `document_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `document_changed`',
			$table
		);

		return 1;
	};
