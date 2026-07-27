<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/migrations/003_document_fields_text_composite_index.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('document_fields_text');
		if (!in_array($table, DB::getTables($table), true)) {
			return 0;
		}

		$exists = (bool) DB::query(
			'SELECT 1 FROM information_schema.STATISTICS'
				. ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s LIMIT 1',
			$table,
			'idx_document_field'
		)->getValue();
		if ($exists) {
			return 0;
		}

		DB::query('ALTER TABLE ' . $table . ' ADD INDEX idx_document_field (document_id,rubric_field_id)');
		return 1;
	};
