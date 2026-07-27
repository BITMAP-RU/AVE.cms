<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/009_deduplicate_document_short_aliases.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Documents\DocumentAliasRegistry;

	return function (array $context) {
		$table = ContentTables::table('documents');
		$keyOf = function ($value) {
			$value = trim((string) $value);
			return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
		};
		$rows = \DB::query(
			'SELECT Id, document_alias, document_short_alias FROM ' . $table . ' ORDER BY Id ASC'
		)->getAll() ?: array();
		$mainAliases = array();
		foreach ($rows as $row) {
			$alias = DocumentAliasRegistry::normalize($row['document_alias']);
			if ($alias !== '') { $mainAliases[$keyOf($alias)] = (int) $row['Id']; }
		}

		$seen = array();
		$queries = 1;
		foreach ($rows as $row) {
			$alias = trim((string) $row['document_short_alias']);
			if ($alias === '') { continue; }
			$key = $keyOf($alias);
			$id = (int) $row['Id'];
			$conflictsMain = isset($mainAliases[$key]) && (int) $mainAliases[$key] !== $id;
			if (isset($seen[$key]) || $conflictsMain) {
				\DB::Update($table, array('document_short_alias' => ''), 'Id = %i', $id);
				$queries++;
				continue;
			}

			$seen[$key] = $id;
		}

		return $queries;
	};
