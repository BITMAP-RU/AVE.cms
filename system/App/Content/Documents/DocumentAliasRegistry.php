<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentAliasRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicRouteRegistry;
	use App\Content\ContentTables;
	use DB;

	/** One URL namespace shared by document aliases, short aliases and redirects. */
	class DocumentAliasRegistry
	{
		public static function normalize($alias)
		{
			$alias = trim((string) $alias);
			if ($alias === '/') { return '/'; }

			return trim($alias, '/ ');
		}

		public static function conflict($alias, $exceptId = 0)
		{
			$alias = self::normalize($alias);
			if ($alias === '') { return null; }

			$path = $alias === '/' ? '' : $alias;
			$sql = 'SELECT Id, document_alias, document_short_alias FROM ' . ContentTables::table('documents')
				. " WHERE Id != %i AND (TRIM(BOTH '/' FROM document_alias) = %s";
			$args = array($sql, (int) $exceptId, $path);
			if ($path !== '') {
				$args[0] .= ' OR document_short_alias = %s';
				$args[] = $path;
			}

			$args[0] .= ') LIMIT 1';
			$document = call_user_func_array(array('DB', 'query'), $args)->getAssoc();
			if (is_array($document) && !empty($document['Id'])) {
				$type = $path !== '' && (string) $document['document_short_alias'] === $path ? 'short_alias' : 'document';
				return array(
					'type' => $type,
					'label' => $type === 'short_alias' ? 'Короткий alias документа' : 'Документы',
					'id' => (int) $document['Id'],
				);
			}

			if ($path !== '') {
				$historyId = (int) DB::query(
					'SELECT id FROM ' . ContentTables::table('document_alias_history')
					. " WHERE TRIM(BOTH '/' FROM document_alias) = %s AND document_id != %i LIMIT 1",
					$path,
					(int) $exceptId
				)->getValue();
				if ($historyId > 0) {
					return array('type' => 'history', 'label' => 'Редиректы документов', 'id' => $historyId);
				}
			}

			if ($alias === '/') { return null; }

			return PublicRouteRegistry::conflict($alias);
		}

		public static function available($alias, $exceptId = 0)
		{
			return self::conflict($alias, $exceptId) === null;
		}
	}
