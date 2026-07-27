<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/016_reconcile_document_content_columns.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;

	return function (array $context) {
		$table = ContentTables::table('documents');
		$queries = 0;

		if (!DatabaseSchema::columnExists($table, 'document_breadcrumb_title')) {
			if (DatabaseSchema::columnExists($table, 'document_breadcrum_title')) {
				\DB::query(
					'ALTER TABLE %b CHANGE COLUMN `document_breadcrum_title` `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT %s',
					$table,
					''
				);
			} else {
				\DB::query(
					'ALTER TABLE %b ADD COLUMN `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT %s AFTER `document_title`',
					$table,
					''
				);
			}

			$queries++;
		}

		if (DatabaseSchema::columnExists($table, 'document_breadcrum_title')) {
			\DB::query(
				'UPDATE %b SET `document_breadcrumb_title`=`document_breadcrum_title`'
					. ' WHERE `document_breadcrumb_title`=%s AND `document_breadcrum_title`!=%s',
				$table,
				'',
				''
			);
			\DB::query('ALTER TABLE %b DROP COLUMN `document_breadcrum_title`', $table);
			$queries += 2;
		}

		if (!DatabaseSchema::columnExists($table, 'document_excerpt')) {
			if (DatabaseSchema::columnExists($table, 'document_teaser')) {
				\DB::query(
					'ALTER TABLE %b CHANGE COLUMN `document_teaser` `document_excerpt` TEXT NOT NULL',
					$table
				);
			} else {
				\DB::query(
					'ALTER TABLE %b ADD COLUMN `document_excerpt` TEXT NOT NULL AFTER `document_linked_navi_id`',
					$table
				);
			}

			$queries++;
		}

		if (DatabaseSchema::columnExists($table, 'document_teaser')) {
			\DB::query(
				'UPDATE %b SET `document_excerpt`=`document_teaser`'
					. ' WHERE `document_excerpt`=%s AND `document_teaser`!=%s',
				$table,
				'',
				''
			);
			\DB::query('ALTER TABLE %b DROP COLUMN `document_teaser`', $table);
			$queries += 2;
		}

		$columns = array(
			'document_short_alias' => 'VARCHAR(10) NOT NULL DEFAULT \'\' AFTER `document_alias_history`',
			'document_tags' => 'TEXT NOT NULL AFTER `document_excerpt`',
			'document_property' => 'TEXT NOT NULL AFTER `document_tags`',
			'module_catalog' => 'LONGTEXT NOT NULL AFTER `document_position`',
			'guid' => 'VARCHAR(100) NOT NULL DEFAULT \'\' AFTER `module_catalog`',
		);
		foreach ($columns as $column => $definition) {
			if (DatabaseSchema::columnExists($table, $column)) { continue; }
			\DB::query('ALTER TABLE %b ADD COLUMN %b ' . $definition, $table, $column);
			$queries++;
		}

		return $queries;
	};
