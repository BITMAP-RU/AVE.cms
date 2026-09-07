<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Catalog/migrations/006_add_item_sources.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\CatalogTables;

	return function () {
		$table = CatalogTables::table('module_catalog_items');
		if (!DatabaseSchema::tableExists($table) || DatabaseSchema::columnExists($table, 'source_item_ids')) {
			return 0;
		}

		DB::query('ALTER TABLE %b ADD COLUMN `source_item_ids` TEXT NULL AFTER `filters_settings`', $table);
		return 1;
	};
