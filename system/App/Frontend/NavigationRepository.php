<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/NavigationRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\File;

	use App\Content\ContentTables;
	use DB;

	/** Reads public navigation definitions and resolves their active branch. */
	class NavigationRepository
	{
		public function find($key)
		{
			$navigation = DB::query(
				'SELECT * FROM %b WHERE navigation_id = %i OR alias = %s LIMIT 1',
				ContentTables::table('navigation'),
				(int) $key,
				(string) $key
			)->getObject();

			if (!$navigation) {
				return null;
			}

			$navigation->user_group = array_values(array_filter(array_map(
				'intval',
				explode(',', (string) $navigation->user_group)
			)));

			return $navigation;
		}

		public function activePath($navigationId, $document)
		{
			$documentId = is_object($document) && isset($document->Id)
				? (int) $document->Id
				: (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 1);
			if ($documentId <= 0) {
				$documentId = 1;
			}

			$alias = is_object($document) && isset($document->document_alias)
				? ltrim((string) $document->document_alias)
				: '';
			$aliasConditions = '';

			if (is_object($document) && isset($document->Id) && (int) $document->Id === $documentId) {
				$aliases = array($alias, '/' . $alias);
				if (defined('URL_SUFF') && URL_SUFF) {
					$aliases[] = $alias . URL_SUFF;
					$aliases[] = '/' . $alias . URL_SUFF;
				}

				foreach (array_unique($aliases) as $candidate) {
					$aliasConditions .= " OR nav.alias = '" . DB::safe($candidate) . "'";
				}
			}

			$value = DB::query(
				'SELECT CONCAT_WS(\';\','
					. ' CONCAT_WS(\',\', nav.navigation_item_id, nav.parent_id, nav2.parent_id),'
					. ' CONCAT_WS(\',\', nav.level), nav.navigation_item_id)'
					. ' FROM ' . ContentTables::table('navigation_items') . ' AS nav'
					. ' JOIN ' . ContentTables::table('documents') . ' AS doc'
					. ' LEFT JOIN ' . ContentTables::table('navigation_items') . ' AS nav2'
					. ' ON nav2.navigation_item_id = nav.parent_id'
					. ' WHERE nav.status = 1'
					. ' AND nav.navigation_id = %i'
					. ' AND doc.Id = %i'
					. ' AND (nav.document_id = %i' . $aliasConditions
					. ' OR nav.navigation_item_id = doc.document_linked_navi_id)',
				(int) $navigationId,
				$documentId,
				$documentId
			)->getValue();

			$parts = explode(';', (string) $value);
			$way = !empty($parts[0])
				? array_values(array_filter(array_map('intval', explode(',', $parts[0]))))
				: array();
			$way[] = 0;

			$levels = isset($parts[1])
				? array_values(array_filter(array_map('intval', explode(',', $parts[1]))))
				: array();

			return array(
				'way' => $way,
				'level' => ($levels ? max($levels) : 0) + 1,
				'item_id' => isset($parts[2]) ? (int) $parts[2] : 0,
			);
		}

		public function items($cacheKey, $navigation, array $active, array $levels)
		{
			$levelSql = '';
			$activeSql = '';
			$parent = 0;

			if ($levels) {
				$levelSql = ' AND level IN (' . implode(',', $levels) . ')';
			} else {
				switch ((int) $navigation->expand_ext) {
					case 0:
						$activeSql = ' AND parent_id IN (' . implode(',', $active['way']) . ')';
						break;
					case 2:
						$levelSql = ' AND level = ' . (int) $active['level'];
						$parent = (int) $active['item_id'];
						break;
				}
			}

			$cacheFile = null;
			if (!$levels && (int) $navigation->expand_ext === 1 && !(defined('DEV_MODE') && DEV_MODE)) {
				$cacheFile = BASEPATH . '/tmp/cache/sql/navigations/' . trim((string) $cacheKey, '/') . '/items.cache';
			}

			$items = $cacheFile ? $this->readCache($cacheFile) : null;
			if (!is_array($items)) {
				$items = $this->queryItems((int) $navigation->navigation_id, $levelSql, $activeSql);
				if ($cacheFile) {
					$this->writeCache($cacheFile, $items);
				}
			}

			if ($levels && $items) {
				$keys = array_keys($items);
				$parent = (int) reset($keys);
			}

			return array('items' => $items, 'parent' => $parent);
		}

		protected function queryItems($navigationId, $levelSql, $activeSql)
		{
			$result = DB::query(
				'SELECT * FROM ' . ContentTables::table('navigation_items')
					. ' WHERE status = 1 AND navigation_id = %i'
					. $levelSql . $activeSql
					. ' ORDER BY position ASC',
				(int) $navigationId
			);

			$items = array();
			while ($row = $result->getAssoc()) {
				$items[(int) $row['parent_id']][] = $row;
			}

			return $items;
		}

		protected function readCache($file)
		{
			if (!is_file($file)) {
				return null;
			}

			$raw = File::getContent($file);
			if ($raw === false || $raw === '') {
				return null;
			}

			$value = @unserialize($raw, array('allowed_classes' => false));
			return is_array($value) ? $value : null;
		}

		protected function writeCache($file, array $items)
		{
			File::putAtomic($file, serialize($items));
		}
	}
