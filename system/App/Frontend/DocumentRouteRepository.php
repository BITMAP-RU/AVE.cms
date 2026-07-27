<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentRouteRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/** Resolves document IDs, aliases and historical redirects. */
	class DocumentRouteRepository
	{
		public function normalizeAlias($alias)
		{
			$alias = trim((string) $alias);
			$alias = preg_replace('/[\s,]+/u', '', $alias);
			$alias = str_replace(array('<', ';', '|', '&', '>', "'", '"', ')', '(', '{', '}', '$', '='), '', $alias);
			$alias = str_ireplace('%3Cscript', '', $alias);
			return trim(strip_tags(html_entity_decode($alias, ENT_QUOTES, 'UTF-8')));
		}

		public function idByAlias($alias)
		{
			return (int) DB::query(
				'SELECT Id FROM %b WHERE document_alias = %s OR document_short_alias = %s LIMIT 1',
				ContentTables::table('documents'),
				(string) $alias,
				(string) $alias
			)->getValue();
		}

		public function aliasById($documentId)
		{
			$alias = DB::query(
				'SELECT document_alias FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getValue();
			return $alias === null || $alias === false ? null : (string) $alias;
		}

		public function historicalRedirect($alias)
		{
			$row = DB::query(
				'SELECT doc.document_alias, history.document_alias_header'
					. ' FROM %b AS history JOIN %b AS doc ON history.document_id = doc.Id'
					. ' WHERE history.document_alias = %s LIMIT 1',
				ContentTables::table('document_alias_history'),
				ContentTables::table('documents'),
				(string) $alias
			)->getObject();

			return $row ?: false;
		}
	}
