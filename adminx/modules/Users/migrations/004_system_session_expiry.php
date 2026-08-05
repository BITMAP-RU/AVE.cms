<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/migrations/004_system_session_expiry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;

	return function (array $context) {
		$table = SystemTables::table('users_session');
		if (!DB::query('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'expires_at')->getAssoc()) {
			DB::query('ALTER TABLE ' . $table . ' ADD expires_at DATETIME NULL AFTER last_active');
		}

		$ttl = defined('COOKIE_LIFETIME') ? max(3600, (int) COOKIE_LIFETIME) : 1209600;
		DB::query(
			'UPDATE ' . $table . ' SET expires_at=DATE_ADD(created_at,INTERVAL %i SECOND) WHERE expires_at IS NULL',
			$ttl
		);
		if (!DB::query('SHOW INDEX FROM ' . $table . ' WHERE Key_name=%s', 'idx_expiry')->getAssoc()) {
			DB::query('ALTER TABLE ' . $table . ' ADD KEY idx_expiry (expires_at)');
		}

		DB::query('ALTER TABLE ' . $table . ' MODIFY expires_at DATETIME NOT NULL');
		return 1;
	};
