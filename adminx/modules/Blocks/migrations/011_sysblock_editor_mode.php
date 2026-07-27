<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/migrations/011_sysblock_editor_mode.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	$table = ContentTables::table('sysblocks');
	if (DatabaseSchema::tableExists($table) && !DatabaseSchema::columnExists($table, 'sysblock_editor')) {
		DatabaseSchema::alter(
			'ALTER TABLE `' . $table . '` ADD COLUMN `sysblock_editor` VARCHAR(16) NOT NULL DEFAULT \'php\' AFTER `sysblock_visual`'
		);
		DB::query(
			'UPDATE `' . $table . '` SET `sysblock_editor` = CASE'
				. ' WHEN `sysblock_eval` = %s THEN %s'
				. ' WHEN `sysblock_visual` = %s THEN %s'
				. ' ELSE %s END',
			'1',
			'php',
			'1',
			'rich',
			'html'
		);
	}
