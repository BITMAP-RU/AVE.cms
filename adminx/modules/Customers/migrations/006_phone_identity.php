<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/migrations/006_phone_identity.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\PublicUserTables;
	use App\Helpers\Phone;

	return function (array $context) {
		$table = PublicUserTables::table('users');
		$changed = 0;
		if (!DatabaseSchema::tableExists($table)) {
			return $changed;
		}

		DB::query('ALTER TABLE `' . $table . '` MODIFY `email` VARCHAR(190) NULL DEFAULT NULL');
		$changed++;
		if (!DatabaseSchema::columnExists($table, 'phone_normalized')) {
			DB::query(
				'ALTER TABLE `' . $table . '`'
					. ' ADD COLUMN `phone_normalized` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER `phone`'
			);
			$changed++;
		}

		if (!DatabaseSchema::indexExists($table, 'uniq_phone_normalized')) {
			DB::query('UPDATE `' . $table . '` SET phone_normalized=NULL');
			$cursor = 0;
			do {
				$rows = DB::query(
					"SELECT Id,phone FROM `" . $table . "`"
						. " WHERE Id>%i AND deleted!='1' AND phone!='' ORDER BY Id LIMIT 500",
					$cursor
				)->getAll() ?: array();
				if (!$rows) {
					break;
				}

				$sql = 'UPDATE `' . $table . '` SET phone_normalized=CASE Id';
				$args = array();
				$ids = array();
				foreach ($rows as $row) {
					$id = (int) $row['Id'];
					$sql .= ' WHEN %i THEN %s';
					$args[] = $id;
					$args[] = Phone::normalize(isset($row['phone']) ? $row['phone'] : '') ?: null;
					$ids[] = $id;
					$cursor = $id;
				}

				$sql .= ' ELSE phone_normalized END WHERE Id IN %li';
				$args[] = $ids;
				call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args));
			} while (count($rows) === 500);

			DB::query(
				'UPDATE `' . $table . '` AS target'
					. ' JOIN (SELECT phone_normalized FROM ('
					. ' SELECT phone_normalized FROM `' . $table . '`'
					. ' WHERE phone_normalized IS NOT NULL'
					. ' GROUP BY phone_normalized HAVING COUNT(*)>1'
					. ' ) AS grouped) AS duplicates'
					. ' ON duplicates.phone_normalized=target.phone_normalized'
					. ' SET target.phone_normalized=NULL'
			);
			DB::query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uniq_phone_normalized` (`phone_normalized`)');
			$changed++;
		}

		return $changed;
	};
