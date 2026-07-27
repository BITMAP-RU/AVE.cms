<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/012_default_legacy_language_columns.php
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
		$queries = 0;

		if (DatabaseSchema::columnExists($table, 'document_lang')) {
			\DB::query('ALTER TABLE %b MODIFY COLUMN `document_lang` VARCHAR(5) NOT NULL DEFAULT %s', $table, '');
			$queries++;
		}

		if (DatabaseSchema::columnExists($table, 'document_lang_group')) {
			\DB::query('ALTER TABLE %b MODIFY COLUMN `document_lang_group` INT NOT NULL DEFAULT 0', $table);
			$queries++;
		}

		return $queries;
	};
