<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/migrations/003_system_user_security.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;

	return function (array $context = array()) {
		$users = SystemTables::table('users');
		$sessions = SystemTables::table('users_session');
		$operations = 0;

		if (!DatabaseSchema::columnExists($users, 'password_changed_at')) {
			DatabaseSchema::alter('ALTER TABLE `' . $users . '` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `last_login_at`');
			$operations++;
		}

		if (!DatabaseSchema::columnExists($users, 'must_change_password')) {
			DatabaseSchema::alter('ALTER TABLE `' . $users . '` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_changed_at`');
			$operations++;
		}

		DB::query(
			'UPDATE `' . $users . '` SET `password_changed_at`=COALESCE(`updated_at`,`created_at`)'
				. ' WHERE `password_changed_at` IS NULL AND `password_hash`<>%s',
			''
		);

		if (DatabaseSchema::tableExists($sessions) && !DatabaseSchema::indexExists($sessions, 'idx_user_active')) {
			DatabaseSchema::alter('ALTER TABLE `' . $sessions . '` ADD KEY `idx_user_active` (`user_id`,`last_active`)');
			$operations++;
		}

		$audit = AuditLog::table();
		if (DatabaseSchema::tableExists($audit) && !DatabaseSchema::indexExists($audit, 'idx_target_action')) {
			DatabaseSchema::alter('ALTER TABLE `' . $audit . '` ADD KEY `idx_target_action` (`target_id`,`action`,`created_at`)');
			$operations++;
		}

		return $operations;
	};
