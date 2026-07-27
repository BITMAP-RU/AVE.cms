<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/migrations/001_mysql_legacy_index_compatibility.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\PublicUserTables;

	return function (array $context) {
		$tableExists = function ($table) {
			return in_array((string) $table, DB::getTables((string) $table), true);
		};
		$indexExists = function ($table, $index) {
			return (bool) DB::query(
				'SELECT 1 FROM information_schema.STATISTICS'
					. ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s LIMIT 1',
				(string) $table,
				(string) $index
			)->getValue();
		};

		$operations = 0;
		$migrations = SystemTables::table('module_migrations');
		if ($tableExists($migrations)) {
			DB::query(
				'ALTER TABLE ' . $migrations
					. ' MODIFY module VARCHAR(80) CHARACTER SET ascii NOT NULL,'
					. ' MODIFY migration VARCHAR(190) CHARACTER SET ascii NOT NULL'
			);
			$operations++;
		}

		$users = SystemTables::table('users');
		if ($tableExists($users)) {
			DB::query('ALTER TABLE ' . $users . ' MODIFY email VARCHAR(255) CHARACTER SET ascii NOT NULL');
			$operations++;
		}

		$identities = PublicUserTables::table('user_identities');
		if ($tableExists($identities)) {
			DB::query(
				'ALTER TABLE ' . $identities
					. ' MODIFY provider VARCHAR(24) CHARACTER SET ascii NOT NULL,'
					. ' MODIFY provider_user_id VARCHAR(190) CHARACTER SET ascii NOT NULL'
			);
			$operations++;
		}

		$referrers = SystemTables::table('referrer_log');
		if ($tableExists($referrers) && $indexExists($referrers, 'idx_source')) {
			DB::query(
				'ALTER TABLE ' . $referrers
					. ' DROP INDEX idx_source, ADD INDEX idx_source (source_type,source_name(167))'
			);
			$operations++;
		}

		$documentFields = ContentTables::table('document_fields');
		if ($tableExists($documentFields)) {
			$legacyIndex = $indexExists($documentFields, 'field_value') ? 'field_value' : '';
			$currentIndex = $indexExists($documentFields, 'idx_field_value') ? 'idx_field_value' : '';
			if ($legacyIndex !== '' || $currentIndex !== '') {
				$drop = array();
				if ($legacyIndex !== '') { $drop[] = 'DROP INDEX field_value'; }
				if ($currentIndex !== '') { $drop[] = 'DROP INDEX idx_field_value'; }
				$drop[] = 'ADD INDEX idx_field_value (field_value(190))';
				DB::query('ALTER TABLE ' . $documentFields . ' ' . implode(',', $drop));
				$operations++;
			}
		}

		return $operations;
	};
