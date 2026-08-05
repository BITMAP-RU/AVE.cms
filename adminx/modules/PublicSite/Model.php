<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\CatalogTables;
	use App\Content\ContentPlacementMap;
	use App\Content\ContentTables;
	use DB;

	class Model
	{
		public static function stats()
		{
			return array(
				'rubrics' => self::count(ContentTables::table('rubrics')),
				'documents' => self::count(ContentTables::table('documents'), "document_deleted!='1'"),
				'requests' => self::count(ContentTables::table('request')),
				'components' => self::count(ContentTables::table('sysblocks'))
					+ self::count(ContentTables::table('navigation')),
				'catalogs' => self::count(CatalogTables::table('module_catalog_settings')),
				'presentations' => self::count(CatalogTables::table('catalog_card_templates'))
					+ self::count(CatalogTables::table('catalog_filter_templates')),
			);
		}

		public static function structure($query = '')
		{
			$query = trim((string) $query);
			$sql = 'SELECT r.Id id,r.rubric_title title,r.rubric_alias alias,r.rubric_template_id template_id,'
				. ' r.rubric_docs_active documents_enabled,CHAR_LENGTH(TRIM(r.rubric_template)) template_length,'
				. ' COALESCE(t.template_title,\'\') template_title'
				. ' FROM ' . ContentTables::table('rubrics') . ' r'
				. ' LEFT JOIN ' . ContentTables::table('templates') . ' t ON t.Id=r.rubric_template_id';
			$args = array();
			if ($query !== '') {
				$sql .= ' WHERE r.rubric_title LIKE %s OR r.rubric_alias LIKE %s OR r.Id=%i';
				$args[] = '%' . $query . '%';
				$args[] = '%' . $query . '%';
				$args[] = (int) $query;
			}

			$sql .= ' ORDER BY r.rubric_position,r.rubric_title,r.Id';
			$rows = self::queryAll($sql, $args);
			$documents = self::documentCounts();
			$fields = self::groupedCount(ContentTables::table('rubric_fields'), 'rubric_id');
			$requests = self::groupedCount(ContentTables::table('request'), 'rubric_id');
			$rubricTemplates = self::groupedCount(ContentTables::table('rubric_templates'), 'rubric_id');
			$catalogs = self::groupedCount(CatalogTables::table('module_catalog_settings'), 'rubric_id');

			foreach ($rows as &$row) {
				$id = (int) $row['id'];
				$row['id'] = $id;
				$row['title'] = self::decode($row['title']);
				$row['alias'] = (string) $row['alias'];
				$row['template_id'] = (int) $row['template_id'];
				$row['template_title'] = self::decode($row['template_title']);
				$row['documents_enabled'] = (int) $row['documents_enabled'] === 1;
				$row['documents_count'] = isset($documents[$id]) ? (int) $documents[$id]['total'] : 0;
				$row['published_count'] = isset($documents[$id]) ? (int) $documents[$id]['published'] : 0;
				$row['fields_count'] = isset($fields[$id]) ? (int) $fields[$id] : 0;
				$row['requests_count'] = isset($requests[$id]) ? (int) $requests[$id] : 0;
				$row['rubric_templates_count'] = isset($rubricTemplates[$id]) ? (int) $rubricTemplates[$id] : 0;
				$row['catalogs_count'] = isset($catalogs[$id]) ? (int) $catalogs[$id] : 0;
				$row['issues'] = self::rubricIssues($row);
			}

			unset($row);
			return $rows;
		}

		public static function diagnostics(array $structure)
		{
			$items = array();
			foreach ($structure as $rubric) {
				foreach ($rubric['issues'] as $issue) {
					$items[] = array(
						'level' => $issue['level'],
						'title' => $rubric['title'],
						'message' => $issue['message'],
						'url' => '/rubrics?q=' . rawurlencode($rubric['title']),
					);
				}
			}

			$requestTable = ContentTables::table('request');
			if (self::available($requestTable)) {
				$rows = DB::query(
					'SELECT Id,request_title,request_alias,request_template_item FROM ' . $requestTable
						. " WHERE TRIM(request_template_item)=''"
						. ' ORDER BY request_title,Id LIMIT 50'
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$items[] = array(
						'level' => 'warning',
						'title' => self::decode($row['request_title']),
						'message' => 'У подборки не заполнен шаблон элемента',
						'url' => '/requests',
					);
				}
			}

			return array(
				'items' => $items,
				'errors' => count(array_filter($items, function ($item) { return $item['level'] === 'error'; })),
				'warnings' => count(array_filter($items, function ($item) { return $item['level'] === 'warning'; })),
			);
		}

		public static function placements(array $filters = array(), $refresh = false)
		{
			$all = ContentPlacementMap::all((bool) $refresh);
			$query = self::lower(isset($filters['q']) ? trim((string) $filters['q']) : '');
			$type = isset($filters['type']) ? trim((string) $filters['type']) : '';
			$state = isset($filters['state']) ? trim((string) $filters['state']) : '';
			$area = isset($filters['area']) ? trim((string) $filters['area']) : '';
			$view = isset($filters['view']) && in_array($filters['view'], array('usage', 'dependencies', 'unused'), true)
				? $filters['view'] : 'placements';
			$page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
			$rows = array_values(array_filter($all, function ($item) use ($query, $type, $state, $area) {
				if ($type !== '' && $item['component_type'] !== $type) {
					return false;
				}

				if ($state !== '' && $item['component_status'] !== $state) {
					return false;
				}

				if ($area !== '' && $item['area'] !== $area) {
					return false;
				}

				if ($query === '') {
					return true;
				}

				$haystack = self::lower(implode(' ', array(
					$item['source_title'],
					$item['source_type_title'],
					$item['source_part_title'],
					$item['component_title'],
					$item['component_alias'],
					$item['component_key'],
					$item['tag'],
				)));
				return strpos($haystack, $query) !== false;
			}));
			$filteredCount = count($rows);
			$usage = self::placementUsage($rows);
			$dependencies = self::placementDependencies($rows);
			$unusedAll = ContentPlacementMap::inventory($all);
			$unused = array_values(array_filter($unusedAll, function ($item) use ($query, $type) {
				if ($type !== '' && $item['type'] !== $type) { return false; }
				if ($query === '') { return true; }
				return strpos(self::lower($item['title'] . ' ' . $item['alias'] . ' ' . $item['type_title']), $query) !== false;
			}));
			$viewItems = $view === 'usage' ? $usage : ($view === 'dependencies' ? $dependencies : ($view === 'unused' ? $unused : $rows));
			$pagination = self::paginate($viewItems, $page, 25);
			if ($view === 'usage') {
				$usage = $pagination['items'];
			} elseif ($view === 'dependencies') {
				$dependencies = $pagination['items'];
			} elseif ($view === 'unused') {
				$unused = $pagination['items'];
			} else {
				$rows = $pagination['items'];
			}

			return array(
				'rows' => $rows,
				'usage' => $usage,
				'dependencies' => $dependencies,
				'unused' => $unused,
				'stats' => self::placementStats($all, $filteredCount, count($unusedAll)),
				'options' => self::placementOptions($all),
				'pagination' => $pagination,
			);
		}

		public static function siteMap($query = '')
		{
			$query = self::lower(trim((string) $query));
			$navigationTable = ContentTables::table('navigation');
			$itemsTable = ContentTables::table('navigation_items');
			$documentsTable = ContentTables::table('documents');
			$menus = self::available($navigationTable)
				? DB::query('SELECT navigation_id id,title,alias FROM ' . $navigationTable . ' ORDER BY title,navigation_id')->getAll() ?: array()
				: array();
			$rows = self::available($itemsTable)
				? DB::query(
					'SELECT i.navigation_item_id id,i.navigation_id,i.parent_id,i.level,i.position,i.status,i.title,i.alias,i.document_id,'
						. ' d.document_title,d.document_alias,d.document_status,d.document_deleted'
						. ' FROM ' . $itemsTable . ' i LEFT JOIN ' . $documentsTable . ' d ON d.Id=i.document_id'
						. ' ORDER BY i.navigation_id,i.parent_id,i.position,i.navigation_item_id'
				)->getAll() ?: array()
				: array();

			$itemsByMenu = array();
			$linkedDocuments = array();
			foreach ($rows as $row) {
				$row = self::siteMapItem($row);
				if ($query !== '' && strpos(self::lower($row['title'] . ' ' . $row['alias'] . ' ' . $row['document_title']), $query) === false) {
					$row['query_match'] = false;
				} else {
					$row['query_match'] = true;
				}

				$itemsByMenu[$row['navigation_id']][] = $row;
				if ($row['document_id'] > 0) { $linkedDocuments[$row['document_id']] = true; }
			}

			$totalItems = 0;
			foreach ($menus as &$menu) {
				$menu['id'] = (int) $menu['id'];
				$menu['title'] = self::decode($menu['title']);
				$menu['alias'] = (string) $menu['alias'];
				$menuItems = isset($itemsByMenu[$menu['id']]) ? $itemsByMenu[$menu['id']] : array();
				$menu['items_count'] = count($menuItems);
				$menu['tree'] = self::siteMapTree($menuItems, 0, $query !== '');
				$totalItems += $menu['items_count'];
			}

			unset($menu);

			$orphanSql = 'SELECT d.Id id,d.document_title title,d.document_alias alias,d.document_status status,r.rubric_title rubric_title'
				. ' FROM ' . $documentsTable . ' d'
				. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id=d.rubric_id'
				. " WHERE d.document_deleted!='1'";
			$args = array();
			if ($linkedDocuments) {
				$orphanSql .= ' AND d.Id NOT IN %li';
				$args[] = array_keys($linkedDocuments);
			}

			if ($query !== '') {
				$orphanSql .= ' AND (LOWER(d.document_title) LIKE %s OR LOWER(d.document_alias) LIKE %s)';
				$args[] = '%' . $query . '%';
				$args[] = '%' . $query . '%';
			}

			$orphanSql .= ' ORDER BY d.document_status DESC,d.document_title,d.Id LIMIT 100';
			$orphans = self::queryAll($orphanSql, $args);
			foreach ($orphans as &$orphan) {
				$orphan['id'] = (int) $orphan['id'];
				$orphan['title'] = self::decode($orphan['title']);
				$orphan['rubric_title'] = self::decode($orphan['rubric_title']);
				$orphan['active'] = (string) $orphan['status'] === '1';
			}

			unset($orphan);

			return array(
				'menus' => $menus,
				'orphans' => $orphans,
				'stats' => array(
					'menus' => count($menus),
					'items' => $totalItems,
					'linked_documents' => count($linkedDocuments),
					'orphans' => count($orphans),
				),
			);
		}

		protected static function siteMapItem(array $row)
		{
			return array(
				'id' => (int) $row['id'],
				'navigation_id' => (int) $row['navigation_id'],
				'parent_id' => (int) $row['parent_id'],
				'level' => (int) $row['level'],
				'position' => (int) $row['position'],
				'active' => (string) $row['status'] === '1',
				'title' => self::decode($row['title']),
				'alias' => (string) $row['alias'],
				'document_id' => isset($row['document_id']) ? (int) $row['document_id'] : 0,
				'document_title' => self::decode(isset($row['document_title']) ? $row['document_title'] : ''),
				'document_alias' => isset($row['document_alias']) ? (string) $row['document_alias'] : '',
				'document_active' => isset($row['document_status']) && (string) $row['document_status'] === '1' && (string) $row['document_deleted'] !== '1',
			);
		}

		protected static function siteMapTree(array $items, $parentId, $filtering, array $visited = array())
		{
			$tree = array();
			foreach ($items as $item) {
				if ((int) $item['parent_id'] !== (int) $parentId || isset($visited[$item['id']])) { continue; }
				$nextVisited = $visited;
				$nextVisited[$item['id']] = true;
				$item['children'] = self::siteMapTree($items, $item['id'], $filtering, $nextVisited);
				if (!$filtering || !empty($item['query_match']) || !empty($item['children'])) {
					$tree[] = $item;
				}
			}

			return $tree;
		}

		protected static function rubricIssues(array $row)
		{
			$issues = array();
			if ((int) $row['template_id'] <= 0 || trim((string) $row['template_title']) === '') {
				$issues[] = array('level' => 'error', 'message' => 'Не назначен существующий шаблон сайта');
			}

			if ((int) $row['template_length'] <= 0) {
				$issues[] = array('level' => 'warning', 'message' => 'Пустой основной шаблон рубрики');
			}

			if ((int) $row['fields_count'] <= 0) {
				$issues[] = array('level' => 'warning', 'message' => 'У типа контента нет полей');
			}

			return $issues;
		}

		protected static function placementStats(array $rows, $filtered, $unused = 0)
		{
			$sources = array();
			$total = 0;
			$broken = 0;
			$modules = 0;
			foreach ($rows as $row) {
				$sources[$row['source_type'] . ':' . $row['source_id']] = true;
				$total += max(1, (int) $row['occurrences']);
				$broken += $row['component_status'] === 'broken' ? 1 : 0;
				$modules += $row['component_type'] === 'module' ? 1 : 0;
			}

			return array(
				'total' => $total,
				'rows' => count($rows),
				'filtered' => (int) $filtered,
				'sources' => count($sources),
				'broken' => $broken,
				'modules' => $modules,
				'unused' => (int) $unused,
			);
		}

		protected static function placementDependencies(array $rows)
		{
			$dependencies = array();
			foreach ($rows as $row) {
				$key = $row['source_type'] . ':' . $row['source_id'];
				if (!isset($dependencies[$key])) {
					$dependencies[$key] = array(
						'source_type' => $row['source_type'], 'source_type_title' => $row['source_type_title'],
						'source_id' => $row['source_id'], 'source_title' => $row['source_title'], 'source_url' => $row['source_url'],
						'components' => array(), 'references' => 0, 'broken' => 0,
					);
				}

				$componentKey = $row['component_type'] . ':' . strtolower((string) $row['component_key']);
				$dependencies[$key]['components'][$componentKey] = array(
					'type' => $row['component_type'], 'title' => $row['component_title'], 'tag' => $row['tag'],
					'status' => $row['component_status'], 'url' => $row['component_url'],
				);
				$dependencies[$key]['references'] += max(1, (int) $row['occurrences']);
				if ($row['component_status'] === 'broken') { $dependencies[$key]['broken']++; }
			}

			foreach ($dependencies as &$item) { $item['components'] = array_values($item['components']); }
			unset($item);
			$dependencies = array_values($dependencies);
			usort($dependencies, function ($left, $right) {
				return array($left['broken'] > 0 ? 0 : 1, $left['source_type_title'], $left['source_title'])
					<=> array($right['broken'] > 0 ? 0 : 1, $right['source_type_title'], $right['source_title']);
			});
			return $dependencies;
		}

		protected static function placementUsage(array $rows)
		{
			$usage = array();
			foreach ($rows as $row) {
				$key = $row['component_type'] . ':' . strtolower((string) $row['component_key']);
				if (!isset($usage[$key])) {
					$usage[$key] = array(
						'component_type' => $row['component_type'],
						'component_title' => $row['component_title'],
						'component_alias' => $row['component_alias'],
						'component_key' => $row['component_key'],
						'component_status' => $row['component_status'],
						'component_status_title' => $row['component_status_title'],
						'component_url' => $row['component_url'],
						'placements' => 0,
						'sources' => array(),
					);
				}

				$usage[$key]['placements'] += max(1, (int) $row['occurrences']);
				$usage[$key]['sources'][$row['source_type'] . ':' . $row['source_id']] = true;
				if ($row['component_status'] === 'broken') {
					$usage[$key]['component_status'] = 'broken';
					$usage[$key]['component_status_title'] = $row['component_status_title'];
				}
			}

			foreach ($usage as &$item) {
				$item['sources'] = count($item['sources']);
			}

			unset($item);
			$usage = array_values($usage);
			usort($usage, function ($left, $right) {
				return array(
					$left['component_status'] === 'broken' ? 0 : 1,
					-$left['placements'],
					$left['component_title'],
				) <=> array(
					$right['component_status'] === 'broken' ? 0 : 1,
					-$right['placements'],
					$right['component_title'],
				);
			});

			return $usage;
		}

		protected static function paginate(array $items, $page, $limit)
		{
			$total = count($items);
			$limit = max(1, (int) $limit);
			$pages = max(1, (int) ceil($total / $limit));
			$page = min(max(1, (int) $page), $pages);
			$offset = ($page - 1) * $limit;

			return array(
				'items' => array_slice($items, $offset, $limit),
				'page' => $page,
				'pages' => $pages,
				'total' => $total,
				'from' => $total > 0 ? $offset + 1 : 0,
				'to' => min($total, $offset + $limit),
			);
		}

		protected static function placementOptions(array $rows)
		{
			$options = array(
				'types' => array(),
				'states' => array(),
				'areas' => array(),
			);
			$typeTitles = array(
				'block' => 'Блоки',
				'request' => 'Подборки',
				'navigation' => 'Навигации',
					'module' => 'Модульные теги',
					'field' => 'Поля',
					'template' => 'Шаблоны сайта',
					'rubric_template' => 'Шаблоны типов контента',
			);
			foreach ($rows as $row) {
				$options['types'][$row['component_type']] = isset($typeTitles[$row['component_type']])
					? $typeTitles[$row['component_type']]
					: $row['component_type'];
				$options['states'][$row['component_status']] = $row['component_status_title'];
				$options['areas'][$row['area']] = $row['area_title'];
			}

			foreach ($options as &$values) {
				asort($values, SORT_NATURAL | SORT_FLAG_CASE);
			}

			unset($values);
			return $options;
		}

		protected static function documentCounts()
		{
			$table = ContentTables::table('documents');
			if (!self::available($table)) { return array(); }
			$rows = DB::query(
				'SELECT rubric_id,COUNT(*) total,'
					. " SUM(CASE WHEN document_status='1' AND document_deleted!='1' THEN 1 ELSE 0 END) published"
					. ' FROM ' . $table . " WHERE document_deleted!='1' GROUP BY rubric_id"
			)->getAll() ?: array();
			$counts = array();
			foreach ($rows as $row) {
				$counts[(int) $row['rubric_id']] = array(
					'total' => (int) $row['total'],
					'published' => (int) $row['published'],
				);
			}

			return $counts;
		}

		protected static function groupedCount($table, $column)
		{
			if (!self::available($table) || !preg_match('/^[a-z0-9_]+$/', (string) $column)) {
				return array();
			}

			$rows = DB::query(
				'SELECT `' . $column . '` group_id,COUNT(*) amount FROM ' . $table
					. ' GROUP BY `' . $column . '`'
			)->getAll() ?: array();
			$counts = array();
			foreach ($rows as $row) { $counts[(int) $row['group_id']] = (int) $row['amount']; }
			return $counts;
		}

		protected static function count($table, $where = '')
		{
			if (!self::available($table)) { return 0; }
			return (int) DB::query('SELECT COUNT(*) FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : ''))->getValue();
		}

		protected static function queryAll($sql, array $args)
		{
			return call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
		}

		protected static function available($table)
		{
			try {
				return DatabaseSchema::tableExists($table);
			} catch (\Throwable $e) {
				return false;
			}
		}

		protected static function decode($value)
		{
			return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
		}

		protected static function lower($value)
		{
			$value = (string) $value;
			return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
		}
	}
