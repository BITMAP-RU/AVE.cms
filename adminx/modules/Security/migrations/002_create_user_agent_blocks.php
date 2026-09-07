<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Security/migrations/002_create_user_agent_blocks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;

	return function () {
		$table = SystemTables::table('user_agent_blocks');
		if (DatabaseSchema::tableExists($table)) {
			return 0;
		}

		DB::query(
			'CREATE TABLE `' . $table . '` ('
				. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
				. '`pattern_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
				. '`pattern` VARCHAR(500) NOT NULL,'
				. "`match_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'contains',"
				. '`reason` VARCHAR(500) NOT NULL DEFAULT \'\','
				. '`expires_at` INT UNSIGNED NULL,`actor_id` INT UNSIGNED NOT NULL DEFAULT 0,'
				. '`created_at` INT UNSIGNED NOT NULL,PRIMARY KEY (`id`),'
				. 'UNIQUE KEY `uq_user_agent_pattern` (`pattern_hash`),KEY `idx_user_agent_expires` (`expires_at`)'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		);
		return 1;
	};
