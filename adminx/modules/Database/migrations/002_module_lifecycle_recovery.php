<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/migrations/002_module_lifecycle_recovery.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;

	return function (array $context) {
		$attempts = SystemTables::table('module_migration_attempts');
		$modules = SystemTables::table('modules');
		$queries = 0;
		\DB::query(
			'CREATE TABLE IF NOT EXISTS ' . $attempts . " (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              module VARCHAR(80) CHARACTER SET ascii NOT NULL,
              migration VARCHAR(190) CHARACTER SET ascii NOT NULL,
              checksum CHAR(40) NOT NULL,
              operation VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT '',
              status VARCHAR(20) CHARACTER SET ascii NOT NULL,
              queries_count INT UNSIGNED NOT NULL DEFAULT 0,
              error_message TEXT NULL,
              actor_id INT UNSIGNED NULL,
              started_at DATETIME NOT NULL,
              completed_at DATETIME NULL,
              PRIMARY KEY (id),
              INDEX idx_module_started (module, started_at),
              INDEX idx_status (status)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4"
		);
		$queries++;

		$columns = array(
			'previous_status' => "VARCHAR(30) NOT NULL DEFAULT '' AFTER disabled_at",
			'operation' => "VARCHAR(20) NOT NULL DEFAULT '' AFTER previous_status",
			'operation_started_at' => 'DATETIME NULL AFTER operation',
			'failed_at' => 'DATETIME NULL AFTER operation_started_at',
			'last_error' => 'TEXT NULL AFTER failed_at',
		);
		foreach ($columns as $name => $definition) {
			$exists = \DB::query(
				'SELECT 1 FROM information_schema.COLUMNS'
					. ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s LIMIT 1',
				$modules,
				$name
			)->getValue();
			if ($exists) { continue; }
			\DB::query('ALTER TABLE ' . $modules . ' ADD COLUMN ' . $name . ' ' . $definition);
			$queries++;
		}

		return $queries;
	};
