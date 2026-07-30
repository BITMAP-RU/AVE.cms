<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ContentPlacementMap.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\CacheKey;
	use App\Common\DatabaseSchema;
	use App\Common\ModuleManager;
	use DB;

	/**
	 * Read-only index of components embedded in existing content templates.
	 *
	 * It does not affect public rendering. The map is rebuilt after the regular
	 * template/content cache tags are invalidated or by an explicit admin action.
	 */
	class ContentPlacementMap
	{
		const CACHE_TTL = 900;
		const CACHE_VERSION = 2;

		public static function all($refresh = false)
		{
			$key = self::cacheKey();
			if ($refresh) {
				Cache::forget($key);
			}

			return Cache::rememberTagged(
				$key,
				self::CACHE_TTL,
				array('template', 'rubric', 'request', 'sysblock', 'navigation'),
				function () {
					return self::build();
				}
			);
		}

		public static function rebuild()
		{
			return self::all(true);
		}

		/**
		 * Build a current placement map without reading or writing the persistent cache.
		 *
		 * Introspection uses this method so a diagnostic read never changes runtime state.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		public static function snapshot()
		{
			return self::build();
		}

		/**
		 * Extract native component references from one saved fragment.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		public static function extract($content, $area = 'main')
		{
			$content = (string) $content;
			if ($content === '') {
				return array();
			}

			$pattern = '#'
				. '\[tag:(sysblock|block|request|navigation):([^\]:\]]+)(?:[^\]]*)\]'
				. '|'
				. '\[mod_([a-z][a-z0-9_]*)(?::[^\]]*)?\]'
				. '#i';
			if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
				return array();
			}

			$items = array();
			foreach ($matches as $match) {
				$tag = isset($match[0][0]) ? trim((string) $match[0][0]) : '';
				if ($tag === '') {
					continue;
				}

				if (!empty($match[3][0])) {
					$type = 'module';
					$key = strtolower(trim((string) $match[3][0]));
				} else {
					$tagType = strtolower(trim(isset($match[1][0]) ? (string) $match[1][0] : ''));
					$type = $tagType === 'sysblock' ? 'block' : $tagType;
					$key = trim(isset($match[2][0]) ? (string) $match[2][0] : '');
				}

				if ($type === '' || $key === '') {
					continue;
				}

				$items[] = array(
					'area' => self::areaCode($area),
					'component_type' => $type,
					'component_key' => $key,
					'tag' => $tag,
					'offset' => isset($match[0][1]) ? (int) $match[0][1] : 0,
				);
			}

			return $items;
		}

		protected static function build()
		{
			$components = self::componentIndex();
			$placements = array();

			foreach (self::templateSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			foreach (self::rubricSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			foreach (self::rubricTemplateSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			foreach (self::requestSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			foreach (self::blockSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			foreach (self::navigationSources() as $source) {
				self::appendSource($placements, $source, $components);
			}

			$placements = self::mergeDuplicates($placements);
			usort($placements, function ($left, $right) {
				$order = array('before_content' => 10, 'main' => 20, 'after_content' => 30, 'metadata' => 40, 'list_item' => 50);
				$leftArea = isset($order[$left['area']]) ? $order[$left['area']] : 100;
				$rightArea = isset($order[$right['area']]) ? $order[$right['area']] : 100;
				return array(
					$leftArea,
					$left['source_title'],
					$left['source_id'],
					$left['component_title'],
				) <=> array(
					$rightArea,
					$right['source_title'],
					$right['source_id'],
					$right['component_title'],
				);
			});

			return $placements;
		}

		protected static function appendSource(array &$placements, array $source, array $components)
		{
			$fragments = isset($source['fragments']) && is_array($source['fragments'])
				? $source['fragments']
				: array();
			foreach ($fragments as $fragment) {
				$content = isset($fragment['content']) ? (string) $fragment['content'] : '';
				$area = isset($fragment['area']) ? (string) $fragment['area'] : 'main';
				foreach (self::extract($content, $area) as $reference) {
					$component = self::resolveComponent(
						$reference['component_type'],
						$reference['component_key'],
						$components,
						$reference['tag']
					);
					$placements[] = array_merge($reference, $component, array(
						'source_type' => $source['type'],
						'source_type_title' => self::sourceTypeTitle($source['type']),
						'source_id' => (int) $source['id'],
						'source_title' => self::decode($source['title']),
						'source_part' => isset($fragment['part']) ? (string) $fragment['part'] : '',
						'source_part_title' => isset($fragment['title']) ? (string) $fragment['title'] : '',
						'source_url' => self::sourceUrl($source),
						'area_title' => self::areaTitle($area),
						'occurrences' => 1,
					));
				}
			}
		}

		protected static function templateSources()
		{
			$table = ContentTables::table('templates');
			if (!self::available($table)) {
				return array();
			}

			$sources = array();
			foreach (DB::query(
				'SELECT Id,template_title,template_text FROM ' . $table . ' ORDER BY Id'
			)->getAll() ?: array() as $row) {
				$content = (string) $row['template_text'];
				$marker = stripos($content, '[tag:maincontent]');
				$fragments = array();
				if ($marker === false) {
					$fragments[] = self::fragment('body', 'Шаблон целиком', 'main', $content);
				} else {
					$fragments[] = self::fragment(
						'before_maincontent',
						'До основного содержимого',
						'before_content',
						substr($content, 0, $marker)
					);
					$fragments[] = self::fragment(
						'after_maincontent',
						'После основного содержимого',
						'after_content',
						substr($content, $marker + strlen('[tag:maincontent]'))
					);
				}

				$sources[] = array(
					'type' => 'template',
					'id' => (int) $row['Id'],
					'title' => $row['template_title'],
					'fragments' => $fragments,
				);
			}

			return $sources;
		}

		protected static function rubricSources()
		{
			$table = ContentTables::table('rubrics');
			if (!self::available($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT Id,rubric_title,rubric_template,rubric_header_template,rubric_og_template,'
					. 'rubric_footer_template,rubric_teaser_template FROM ' . $table . ' ORDER BY Id'
			)->getAll() ?: array();
			$sources = array();
			foreach ($rows as $row) {
				$sources[] = array(
					'type' => 'rubric',
					'id' => (int) $row['Id'],
					'title' => $row['rubric_title'],
					'fragments' => array(
						self::fragment('header', 'Шаблон до документа', 'before_content', $row['rubric_header_template']),
						self::fragment('main', 'Основной шаблон', 'main', $row['rubric_template']),
						self::fragment('footer', 'Шаблон после документа', 'after_content', $row['rubric_footer_template']),
						self::fragment('og', 'Метаданные Open Graph', 'metadata', $row['rubric_og_template']),
						self::fragment('teaser', 'Шаблон анонса', 'list_item', $row['rubric_teaser_template']),
					),
				);
			}

			return $sources;
		}

		protected static function rubricTemplateSources()
		{
			$table = ContentTables::table('rubric_templates');
			if (!self::available($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT id,rubric_id,title,template FROM ' . $table . ' ORDER BY rubric_id,id'
			)->getAll() ?: array();
			$sources = array();
			foreach ($rows as $row) {
				$sources[] = array(
					'type' => 'rubric_template',
					'id' => (int) $row['id'],
					'rubric_id' => (int) $row['rubric_id'],
					'title' => $row['title'],
					'fragments' => array(
						self::fragment('main', 'Дополнительный шаблон', 'main', $row['template']),
					),
				);
			}

			return $sources;
		}

		protected static function requestSources()
		{
			$table = ContentTables::table('request');
			if (!self::available($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT Id,request_title,request_template_main,request_template_item FROM ' . $table . ' ORDER BY Id'
			)->getAll() ?: array();
			$sources = array();
			foreach ($rows as $row) {
				$sources[] = array(
					'type' => 'request',
					'id' => (int) $row['Id'],
					'title' => $row['request_title'],
					'fragments' => array(
						self::fragment('wrapper', 'Общий шаблон результата', 'main', $row['request_template_main']),
						self::fragment('item', 'Шаблон элемента', 'list_item', $row['request_template_item']),
					),
				);
			}

			return $sources;
		}

		protected static function blockSources()
		{
			$table = ContentTables::table('sysblocks');
			if (!self::available($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT id,sysblock_name,sysblock_text FROM ' . $table . ' ORDER BY id'
			)->getAll() ?: array();
			$sources = array();
			foreach ($rows as $row) {
				$sources[] = array(
					'type' => 'block',
					'id' => (int) $row['id'],
					'title' => $row['sysblock_name'],
					'fragments' => array(
						self::fragment('body', 'Содержимое блока', 'main', $row['sysblock_text']),
					),
				);
			}

			return $sources;
		}

		protected static function navigationSources()
		{
			$table = ContentTables::table('navigation');
			if (!self::available($table)) {
				return array();
			}

			$columns = array(
				'begin' => 'Обёртка: начало',
				'level1_begin' => 'Уровень 1: начало',
				'level1' => 'Уровень 1',
				'level1_active' => 'Уровень 1: активный пункт',
				'level1_end' => 'Уровень 1: конец',
				'level2_begin' => 'Уровень 2: начало',
				'level2' => 'Уровень 2',
				'level2_active' => 'Уровень 2: активный пункт',
				'level2_end' => 'Уровень 2: конец',
				'level3_begin' => 'Уровень 3: начало',
				'level3' => 'Уровень 3',
				'level3_active' => 'Уровень 3: активный пункт',
				'level3_end' => 'Уровень 3: конец',
				'end' => 'Обёртка: конец',
			);
			$rows = DB::query(
				'SELECT navigation_id,title,' . implode(',', array_keys($columns)) . ' FROM ' . $table
					. ' ORDER BY navigation_id'
			)->getAll() ?: array();
			$sources = array();
			foreach ($rows as $row) {
				$fragments = array();
				foreach ($columns as $column => $title) {
					$fragments[] = self::fragment($column, $title, 'main', $row[$column]);
				}

				$sources[] = array(
					'type' => 'navigation',
					'id' => (int) $row['navigation_id'],
					'title' => $row['title'],
					'fragments' => $fragments,
				);
			}

			return $sources;
		}

		protected static function componentIndex()
		{
			return array(
				'block' => self::indexedComponents(
					ContentTables::table('sysblocks'),
					'SELECT id,sysblock_alias alias,sysblock_name title,sysblock_active active FROM %s ORDER BY id'
				),
				'request' => self::indexedComponents(
					ContentTables::table('request'),
					'SELECT Id id,request_alias alias,request_title title,1 active FROM %s ORDER BY Id'
				),
				'navigation' => self::indexedComponents(
					ContentTables::table('navigation'),
					'SELECT navigation_id id,alias,title,1 active FROM %s ORDER BY navigation_id'
				),
			);
		}

		protected static function indexedComponents($table, $sql)
		{
			$index = array();
			if (!self::available($table)) {
				return $index;
			}

			foreach (DB::query(sprintf($sql, $table))->getAll() ?: array() as $row) {
				$item = array(
					'id' => (int) $row['id'],
					'alias' => trim((string) $row['alias']),
					'title' => self::decode($row['title']),
					'active' => (int) $row['active'] === 1,
				);
				$index[(string) $item['id']] = $item;
				if ($item['alias'] !== '') {
					$index[strtolower($item['alias'])] = $item;
				}
			}

			return $index;
		}

		protected static function resolveComponent($type, $key, array $components, $tag = '')
		{
			$type = (string) $type;
			$key = trim((string) $key);
			if ($type === 'module') {
				return self::resolveModuleComponent($key, $tag);
			}

			$lookup = ctype_digit($key) ? (string) (int) $key : strtolower($key);
			$item = isset($components[$type][$lookup]) ? $components[$type][$lookup] : null;
			if (!$item) {
				return array(
					'component_id' => 0,
					'component_alias' => ctype_digit($key) ? '' : $key,
					'component_title' => self::componentTypeTitle($type) . ' «' . $key . '»',
					'component_status' => 'broken',
					'component_status_title' => 'ссылка не найдена',
					'component_url' => '',
				);
			}

			return array(
				'component_id' => $item['id'],
				'component_alias' => $item['alias'],
				'component_title' => $item['title'] !== '' ? $item['title'] : self::componentTypeTitle($type) . ' #' . $item['id'],
				'component_status' => $item['active'] ? 'active' : 'disabled',
				'component_status_title' => $item['active'] ? 'ссылка работает' : 'компонент выключен',
				'component_url' => self::componentUrl($type, $item),
			);
		}

		protected static function resolveModuleComponent($key, $tag)
		{
			$key = strtolower(trim((string) $key));
			$tag = trim((string) $tag);
			if ($key === 'content_list') {
				$requestId = 0;
				if (preg_match('/^\[mod_content_list:(\d+)/i', $tag, $match)) {
					$requestId = (int) $match[1];
				}

				return self::moduleComponentResult(
					$key,
					'Список материалов' . ($requestId > 0 ? ' · подборка #' . $requestId : ''),
					$requestId > 0 ? '/requests/' . $requestId : '/requests'
				);
			}

			if ($key === 'login') {
				return self::moduleComponentResult(
					$key,
					'Вход и личный кабинет',
					'/system/customers?tab=auth'
				);
			}

			$aliases = array(
				'basket' => 'commerce',
				'catalog_categories' => 'products',
				'catalog_menu' => 'products',
				'catalog_page' => 'products',
				'catalog_root' => 'products',
				'product' => 'products',
				'product_archive' => 'products',
				'product_list' => 'products',
				'poll' => 'polls',
				'gallery' => 'galleries',
				'rating' => 'ratings',
				'banner' => 'banners',
				'experiment' => 'experiments',
				'review' => 'reviews',
				'comment' => 'comments',
				'moredoc' => 'related',
			);
			$moduleCode = isset($aliases[$key]) ? $aliases[$key] : $key;
			$module = ModuleManager::get($moduleCode);
			if (!$module) {
				return self::moduleComponentResult(
					$key,
					'Модульный тег mod_' . $key,
					'/modules',
					'Модуль не определён, открыт общий реестр'
				);
			}

			$extension = isset($module['admin_extension']) && is_array($module['admin_extension'])
				? $module['admin_extension']
				: array();
			$url = isset($extension['url']) ? trim((string) $extension['url']) : '';
			$title = isset($module['name']) && trim((string) $module['name']) !== ''
				? trim((string) $module['name'])
				: $moduleCode;

			return self::moduleComponentResult(
				$key,
				$title . ' · mod_' . $key,
				$url !== '' ? $url : '/modules',
				$url !== '' ? 'обрабатывается модулем' : 'у модуля нет отдельного раздела'
			);
		}

		protected static function moduleComponentResult($key, $title, $url, $statusTitle = 'обрабатывается модулем')
		{
			return array(
				'component_id' => 0,
				'component_alias' => (string) $key,
				'component_title' => (string) $title,
				'component_status' => 'module',
				'component_status_title' => (string) $statusTitle,
				'component_url' => (string) $url,
			);
		}

		protected static function mergeDuplicates(array $placements)
		{
			$merged = array();
			foreach ($placements as $placement) {
				$key = implode(':', array(
					$placement['source_type'],
					$placement['source_id'],
					$placement['source_part'],
					$placement['area'],
					$placement['component_type'],
					strtolower($placement['component_key']),
					$placement['tag'],
				));
				if (!isset($merged[$key])) {
					$merged[$key] = $placement;
					continue;
				}

				$merged[$key]['occurrences']++;
			}

			return array_values($merged);
		}

		protected static function fragment($part, $title, $area, $content)
		{
			return array(
				'part' => (string) $part,
				'title' => (string) $title,
				'area' => self::areaCode($area),
				'content' => (string) $content,
			);
		}

		protected static function sourceUrl(array $source)
		{
			switch ($source['type']) {
				case 'template':
					return '/templates?edit=' . (int) $source['id'];
				case 'rubric':
					return '/rubrics?templates=' . (int) $source['id'];
				case 'rubric_template':
					return '/rubrics?templates=' . (int) $source['rubric_id'] . '&template=' . (int) $source['id'];
				case 'request':
					return '/requests/' . (int) $source['id'];
				case 'block':
					return '/blocks?edit=' . (int) $source['id'];
				case 'navigation':
					return '/navigation?edit=' . (int) $source['id'];
			}

			return '';
		}

		protected static function componentUrl($type, array $item)
		{
			switch ($type) {
				case 'block':
					return '/blocks?edit=' . (int) $item['id'];
				case 'request':
					return '/requests/' . (int) $item['id'];
				case 'navigation':
					return '/navigation?edit=' . (int) $item['id'];
			}

			return '';
		}

		protected static function sourceTypeTitle($type)
		{
			$titles = array(
				'template' => 'Шаблон сайта',
				'rubric' => 'Тип контента',
				'rubric_template' => 'Шаблон типа контента',
				'request' => 'Подборка',
				'block' => 'Блок',
				'navigation' => 'Навигация',
			);
			return isset($titles[$type]) ? $titles[$type] : (string) $type;
		}

		protected static function componentTypeTitle($type)
		{
			$titles = array(
				'block' => 'Блок',
				'request' => 'Подборка',
				'navigation' => 'Навигация',
				'module' => 'Модуль',
			);
			return isset($titles[$type]) ? $titles[$type] : (string) $type;
		}

		protected static function areaCode($area)
		{
			$area = trim((string) $area);
			return preg_match('/^[a-z][a-z0-9_]{1,31}$/', $area) ? $area : 'main';
		}

		protected static function areaTitle($area)
		{
			$titles = array(
				'before_content' => 'До содержимого',
				'main' => 'Основное содержимое',
				'after_content' => 'После содержимого',
				'metadata' => 'Метаданные',
				'list_item' => 'Элемент списка',
			);
			$area = self::areaCode($area);
			return isset($titles[$area]) ? $titles[$area] : $area;
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
			return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
		}

		protected static function cacheKey()
		{
			return CacheKey::make('content-placement-map', array(
				'prefix' => ContentTables::prefix(),
				'version' => self::CACHE_VERSION,
			));
		}
	}
