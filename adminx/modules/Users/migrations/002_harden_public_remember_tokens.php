<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/migrations/002_harden_public_remember_tokens.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\PublicUserTables;

	return function (array $context = array()) {
		$table = PublicUserTables::table('users_session');
		if (!DatabaseSchema::tableExists($table)) {
			return 0;
		}

		$operations = 0;
		$columns = array(
			'token_version' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `hash`',
			'created_at' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_active`',
			'expires_at' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `created_at`',
		);
		foreach ($columns as $name => $definition) {
			if (!DatabaseSchema::columnExists($table, $name)) {
				DatabaseSchema::alter('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $definition);
				$operations++;
			}
		}

		$now = time();
		$ttl = defined('COOKIE_LIFETIME') ? max(3600, (int) COOKIE_LIFETIME) : 1209600;
		DB::query(
			'UPDATE `' . $table . '` SET'
				. ' `hash`=SHA2(`hash`,256),`token_version`=2,'
				. ' `created_at`=IF(`last_active`>0,`last_active`,%i),'
				. ' `expires_at`=IF(`last_active`>0,`last_active`,%i)+%i'
				. ' WHERE `token_version`<2',
			$now,
			$now,
			$ttl
		);
		if (DB::affectedRows() > 0) { $operations++; }

		DB::query(
			'DELETE old_token FROM `' . $table . '` old_token'
				. ' INNER JOIN `' . $table . '` keep_token'
				. ' ON keep_token.`hash`=old_token.`hash` AND keep_token.`id`>old_token.`id`'
		);
		if (DB::affectedRows() > 0) { $operations++; }

		DatabaseSchema::alter(
			'ALTER TABLE `' . $table . '` MODIFY `hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
		);
		$operations++;

		if (!DatabaseSchema::indexExists($table, 'uniq_remember_hash')) {
			DatabaseSchema::alter('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uniq_remember_hash` (`hash`)');
			$operations++;
		}

		if (!DatabaseSchema::indexExists($table, 'idx_remember_expiry')) {
			DatabaseSchema::alter('ALTER TABLE `' . $table . '` ADD KEY `idx_remember_expiry` (`expires_at`)');
			$operations++;
		}

		return $operations;
	};
