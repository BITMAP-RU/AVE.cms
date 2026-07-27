<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/CatalogMenuRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\Cache;
	use App\Common\CacheKey;
	use App\Content\Fields\FieldValueCodec;

	/** Read-only public tree for menus backed by a native catalog. */
	class CatalogMenuRepository
	{
		public static function tree($purpose = 'commerce', $ttl = 600)
		{
			$purpose = self::purpose($purpose);
			$itemsTable = CatalogTables::table('module_catalog_items');
			$settingsTable = CatalogTables::table('module_catalog_settings');
			$key = CacheKey::make('catalog-menu', array('purpose' => $purpose));
			return Cache::rememberTagged($key, max(0, (int) $ttl), array(CacheKey::tag('catalog')), function () use ($purpose, $itemsTable, $settingsTable) {
				return self::buildTree(self::rows($purpose, $itemsTable, $settingsTable));
			});
		}

		public static function buildTree(array $rows)
		{
			$grouped = array();
			foreach ($rows as $row) {
				$item = self::normalize($row);
				if ($item['catalog_item_id'] <= 0) {
					continue;
				}

				$parentId = max(0, (int) $item['parent']);
				if (!isset($grouped[$parentId])) {
					$grouped[$parentId] = array();
				}

				$grouped[$parentId][] = $item;
			}

			return self::branch(0, $grouped, array());
		}

		protected static function rows($purpose, $itemsTable, $settingsTable)
		{
			$documentsTable = ContentTables::table('documents');
			$fieldsTable = ContentTables::table('rubric_fields');
			$valuesTable = ContentTables::table('document_fields');
			$textValuesTable = ContentTables::table('document_fields_text');

			return DB::query(
				'SELECT i.id AS catalog_item_id,i.parent_id,i.position,i.name,i.document_id,'
					. ' COALESCE(NULLIF(d.document_alias,\'\'),i.document_alias) AS alias,'
					. ' COALESCE(NULLIF(i.name,\'\'),NULLIF(d.document_breadcrumb_title,\'\'),d.document_title) AS bread,'
					. ' CONCAT(COALESCE(df.field_value,\'\'),COALESCE(dft.field_value,\'\')) AS image'
					. ' FROM ' . $itemsTable . ' i'
					. ' INNER JOIN ' . $settingsTable . ' s ON s.rubric_id=i.rubric_id AND s.field_id=i.field_id'
					. ' LEFT JOIN ' . $documentsTable . ' d ON d.Id=i.document_id'
					. ' LEFT JOIN ' . $fieldsTable . ' rf ON rf.Id=('
						. 'SELECT MIN(rfi.Id) FROM ' . $fieldsTable . ' rfi'
						. ' WHERE rfi.rubric_id=d.rubric_id AND rfi.rubric_field_alias=%s)'
					. ' LEFT JOIN ' . $valuesTable . ' df ON df.document_id=d.Id AND df.rubric_field_id=rf.Id'
					. ' LEFT JOIN ' . $textValuesTable . ' dft ON dft.document_id=d.Id AND dft.rubric_field_id=rf.Id'
					. ' WHERE s.purpose=%s AND i.status=1'
					. ' AND ((COALESCE(i.document_id,0)=0 AND i.document_alias!=\'\')'
						. ' OR (d.Id IS NOT NULL AND d.document_status=\'1\' AND d.document_deleted=\'0\'))'
					. ' ORDER BY i.parent_id ASC,i.position ASC,i.id ASC',
				'image',
				$purpose
			)->getAll() ?: array();
		}

		protected static function normalize(array $row)
		{
			$alias = '/' . trim(isset($row['alias']) ? (string) $row['alias'] : '', '/');
			$image = '';
			foreach (FieldValueCodec::templateParts(isset($row['image']) ? $row['image'] : '') as $part) {
				if (trim((string) $part) !== '') {
					$image = trim((string) $part);
					break;
				}
			}

			$title = trim(isset($row['bread']) ? (string) $row['bread'] : '');
			if ($title === '') {
				$title = trim(isset($row['name']) ? (string) $row['name'] : '');
			}

			$catalogItemId = isset($row['catalog_item_id']) ? (int) $row['catalog_item_id'] : 0;
			$documentId = isset($row['document_id']) ? (int) $row['document_id'] : 0;
			return array(
				'id' => $documentId > 0 ? $documentId : $catalogItemId,
				'catalog_item_id' => $catalogItemId,
				'document_id' => $documentId,
				'parent' => isset($row['parent_id']) ? (int) $row['parent_id'] : 0,
				'position' => isset($row['position']) ? (int) $row['position'] : 0,
				'alias' => $alias === '/' ? '' : $alias,
				'bread' => $title,
				'title' => trim(isset($row['name']) ? (string) $row['name'] : $title),
				'image' => $image,
				'children' => array(),
				'childs' => array(),
			);
		}

		protected static function branch($parentId, array $grouped, array $visited)
		{
			$items = isset($grouped[(int) $parentId]) ? $grouped[(int) $parentId] : array();
			$out = array();
			foreach ($items as $item) {
				$itemId = (int) $item['catalog_item_id'];
				if (isset($visited[$itemId])) {
					continue;
				}

				$nextVisited = $visited;
				$nextVisited[$itemId] = true;
				$item['children'] = self::branch($itemId, $grouped, $nextVisited);
				$item['childs'] = $item['children'];
				$out[$itemId] = $item;
			}

			return $out;
		}

		protected static function purpose($purpose)
		{
			$purpose = strtolower(trim((string) $purpose));
			return preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $purpose) ? $purpose : 'commerce';
		}
	}
