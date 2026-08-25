<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/TemplateMap.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use App\Frontend\ThemeAssets;
	use DB;

	/** Read-only description of the active public template chain. */
	class TemplateMap
	{
		public static function build()
		{
			$theme = self::theme();
			$siteTemplates = self::siteTemplates();
			$rubrics = self::rubrics();
			$components = self::components();
			$product = self::productLayer();

			return array(
				'theme' => $theme,
				'summary' => array(
					'site_templates' => count($siteTemplates),
					'rubrics' => count($rubrics),
					'components' => $components['count'],
					'active_overrides' => $components['active_count'],
				),
				'rules' => self::rules($theme),
				'scenarios' => self::scenarios($theme, $components, $product),
				'site_templates' => $siteTemplates,
				'rubrics' => $rubrics,
				'component_groups' => $components['groups'],
				'product' => $product,
			);
		}

		protected static function theme()
		{
			$code = ThemeAssets::currentTheme();
			$manifest = ThemeAssets::manifest($code);
			$mode = isset($manifest['presentation_mode']) && $manifest['presentation_mode'] === 'theme'
				? 'theme' : 'native';
			$pageShell = isset($manifest['page_shell']) ? trim((string) $manifest['page_shell']) : '';

			return array(
				'code' => $code,
				'name' => isset($manifest['name']) && trim((string) $manifest['name']) !== ''
					? (string) $manifest['name'] : $code,
				'version' => isset($manifest['version']) ? (string) $manifest['version'] : '',
				'mode' => $mode,
				'mode_label' => $mode === 'theme' ? 'Оболочка темы' : 'Штатные шаблоны AVE.cms',
				'mode_note' => $mode === 'theme'
					? 'Тема формирует внешний каркас страницы; наследование шаблонов рубрики зависит от настроек темы.'
					: 'Общий шаблон сайта и шаблон рубрики собирают страницу. Тема подключает стили и переоформляет отдельные компоненты.',
				'page_shell' => $pageShell,
				'page_shell_active' => $mode === 'theme' && $pageShell !== '',
				'inherit_rubric_templates' => !empty($manifest['inherit_rubric_templates']),
				'overrides' => isset($manifest['view_overrides']) && is_array($manifest['view_overrides'])
					? array_values($manifest['view_overrides']) : array(),
				'url' => '/themes?theme=' . rawurlencode($code),
			);
		}

		protected static function siteTemplates()
		{
			$table = ContentTables::table('templates');
			$rubrics = ContentTables::table('rubrics');
			if (!self::tableExists($table) || !self::tableExists($rubrics)) { return array(); }

			$rows = DB::query(
				'SELECT t.Id id,t.template_title title,COUNT(r.Id) usage_count'
					. ' FROM ' . $table . ' t LEFT JOIN ' . $rubrics . ' r ON r.rubric_template_id=t.Id'
					. ' GROUP BY t.Id,t.template_title ORDER BY usage_count DESC,t.template_title,t.Id'
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = array(
					'id' => (int) $row['id'],
					'title' => self::decode($row['title']),
					'usage_count' => (int) $row['usage_count'],
					'url' => '/templates/' . (int) $row['id'],
				);
			}

			return $out;
		}

		protected static function rubrics()
		{
			$out = array();
			foreach (Model::structure() as $row) {
				$purpose = 'Обычные документы';
				if (!empty($row['catalogs_count'])) {
					$purpose = 'Каталог';
				} elseif (!empty($row['requests_count'])) {
					$purpose = 'Документы и подборки';
				}

				$out[] = array(
					'id' => (int) $row['id'],
					'title' => (string) $row['title'],
					'alias' => (string) $row['alias'],
					'purpose' => $purpose,
					'template_id' => (int) $row['template_id'],
					'template_title' => (string) $row['template_title'],
					'has_main_template' => (int) $row['template_length'] > 0,
					'extra_templates_count' => (int) $row['rubric_templates_count'],
					'documents_count' => (int) $row['documents_count'],
					'url' => '/rubrics/' . (int) $row['id'] . '/templates',
				);
			}

			return $out;
		}

		protected static function components()
		{
			$items = array();
			try {
				foreach (PublicViewTemplates::all() as $item) {
					$items[] = self::component($item, 'site', '/public-site/templates');
				}
			} catch (\Throwable $e) {
				// A broken optional theme must not make the read-only map unavailable.
			}

			$productClass = '\\App\\Adminx\\Packages\\Products\\PublicViewTemplates';
			if (class_exists($productClass) && method_exists($productClass, 'all')) {
				try {
					foreach ($productClass::all() as $item) {
						$items[] = self::component($item, 'products', '/catalog/public-templates');
					}
				} catch (\Throwable $e) {
					// The products module can be installed without its latest schema during an update.
				}
			}

			$groups = array();
			$active = 0;
			foreach ($items as $item) {
				$group = $item['group'] !== '' ? $item['group'] : 'Другие компоненты';
				if (!isset($groups[$group])) {
					$groups[$group] = array('title' => $group, 'items' => array());
				}

				$groups[$group]['items'][] = $item;
				$active += $item['active'] ? 1 : 0;
			}

			return array('count' => count($items), 'active_count' => $active, 'items' => $items, 'groups' => array_values($groups));
		}

		protected static function component(array $item, $owner, $route)
		{
			$source = isset($item['source']) ? (string) $item['source'] : 'missing';
			return array(
				'code' => isset($item['code']) ? (string) $item['code'] : '',
				'namespace' => isset($item['namespace']) ? (string) $item['namespace'] : '',
				'file' => isset($item['file']) ? (string) $item['file'] : '',
				'path' => isset($item['theme_path']) ? (string) $item['theme_path'] : '',
				'group' => isset($item['group']) ? (string) $item['group'] : '',
				'title' => isset($item['title']) ? (string) $item['title'] : '',
				'description' => isset($item['description']) ? (string) $item['description'] : '',
				'icon' => isset($item['icon']) ? (string) $item['icon'] : 'ti ti-template',
				'owner' => $owner,
				'active' => !empty($item['active_override']) || $source === 'fallback',
				'source' => $source,
				'source_label' => isset($item['source_label']) ? (string) $item['source_label'] : 'Не определён',
				'state_class' => $source === 'theme' ? 'badge-green' : ($source === 'fallback' ? 'badge-blue' : 'badge-amber'),
				'url' => $route . '?template=' . rawurlencode(isset($item['code']) ? (string) $item['code'] : ''),
			);
		}

		protected static function productLayer()
		{
			$result = array(
				'available' => false,
				'cards' => array('total' => 0, 'published' => 0, 'used' => 0, 'url' => '/catalog/card-templates'),
				'filters' => array('total' => 0, 'published' => 0, 'used' => 0, 'url' => '/catalog/filter-templates'),
			);
			$classes = array(
				'cards' => '\\App\\Adminx\\Packages\\Products\\CardTemplates',
				'filters' => '\\App\\Adminx\\Packages\\Products\\FilterTemplates',
			);
			foreach ($classes as $key => $class) {
				if (!class_exists($class) || !method_exists($class, 'stats')) { continue; }
				try {
					$stats = $class::stats();
					$result[$key] = array_merge($result[$key], is_array($stats) ? $stats : array());
					$result['available'] = true;
				} catch (\Throwable $e) {
					// Keep the map usable while an optional module waits for migrations.
				}
			}

			return $result;
		}

		protected static function rules(array $theme)
		{
			return array(
				array('icon' => 'ti ti-database', 'title' => 'Данные остаются в AVE.cms', 'text' => 'Документы, поля, рубрики и условия запросов не переносятся в файлы темы.'),
				array('icon' => 'ti ti-template', 'title' => 'Шаблон задаёт место', 'text' => 'Общий шаблон создаёт каркас, а шаблон рубрики размещает содержимое конкретного типа документа.'),
				array('icon' => 'ti ti-palette', 'title' => 'Тема оформляет результат', 'text' => $theme['mode'] === 'native'
					? 'Сейчас тема подключает стили и заменяет только перечисленные публичные компоненты.'
					: 'Сейчас активна оболочка темы; её файл формирует внешний каркас страницы.'),
			);
		}

		protected static function scenarios(array $theme, array $components, array $product)
		{
			$componentItems = isset($components['items']) ? $components['items'] : array();
			$productUrl = $product['available'] ? '/catalog/products' : '/modules';
			$productCardsUrl = $product['available'] ? '/catalog/card-templates' : '/modules';
			return array(
				array(
					'icon' => 'ti ti-browser', 'title' => 'Общий каркас сайта',
					'description' => 'Шапка, подвал и место, куда AVE.cms вставляет содержимое текущего документа.',
					'steps' => array(
						self::step('Шаблон сайта', 'Хранит общий HTML и тег [tag:maincontent].', '/templates', 'Основной слой', 'blue'),
						self::step('Назначение рубрике', 'Каждая рубрика выбирает нужный общий шаблон.', '/rubrics', 'Настройка', 'gray'),
						self::step('Активная тема', $theme['mode_note'], $theme['url'], $theme['mode_label'], $theme['mode'] === 'native' ? 'green' : 'violet'),
					),
				),
				array(
					'icon' => 'ti ti-file-text', 'title' => 'Обычная текстовая страница',
					'description' => 'Например: «О компании», «Доставка», статья или новость.',
					'steps' => array(
						self::step('Документ', 'Заголовок, текст, изображения и остальные значения полей.', '/documents', 'Данные', 'gray'),
						self::step('Шаблон рубрики', 'Решает, какие поля и блоки вывести и в каком порядке.', '/rubrics', 'Сборка содержимого', 'blue'),
						self::componentStep($componentItems, 'storefront', 'info.twig', 'Компонент темы', 'Дополнительное оформление, только если его вызывает шаблон.'),
						self::step('Шаблон сайта', 'Оборачивает готовое содержимое общей шапкой и подвалом.', '/templates', 'Каркас', 'green'),
					),
				),
				array(
					'icon' => 'ti ti-list-search', 'title' => 'Список новостей или материалов',
					'description' => 'Запрос выбирает документы, а его шаблоны или публичное представление оформляют список.',
					'steps' => array(
						self::step('Запрос', 'Условия, сортировка, количество и пагинация.', '/requests', 'Выбор данных', 'gray'),
						self::step('Шаблоны запроса', 'Шаблон элемента и общая обёртка результата.', '/requests', 'Основной вывод', 'blue'),
						self::componentStep($componentItems, 'content_public', 'list.twig', 'Резервный список', 'Используется для нативных коллекций без отдельного представления.'),
						self::step('Рубрика и сайт', 'Определяют место списка на странице и общий каркас.', '/rubrics', 'Размещение', 'green'),
					),
				),
				array(
					'icon' => 'ti ti-category-2', 'title' => 'Раздел товарного каталога',
					'description' => 'Категория остаётся документом, а каталог добавляет подразделы, фильтры, сортировку и выдачу.',
					'steps' => array(
						self::step('Раздел каталога', 'Связь с документом, полями, условиями и фильтрами.', '/catalog', 'Данные и правила', 'gray'),
						self::step('Шаблон рубрики', 'Размещает теги подразделов, фильтров и товарной выдачи.', '/rubrics', 'Сборка страницы', 'blue'),
						self::componentStep($componentItems, 'products_public', 'filter.twig', 'Фильтры', 'Оформляет готовые параметры фильтрации.'),
						self::componentStep($componentItems, 'products_public', 'listing.twig', 'Товарная выдача', 'Оформляет список и пагинацию.'),
					),
				),
				array(
					'icon' => 'ti ti-package', 'title' => 'Карточка и страница товара',
					'description' => 'Один товар может выглядеть по-разному в списке и на собственной странице.',
					'steps' => array(
						self::step('Товар', 'Документ, цены, изображения и нативные характеристики.', $productUrl, 'Данные', 'gray'),
						self::step('Карточки товара', $product['available']
							? 'Опубликованные карточки назначаются разделам и контекстам.'
							: 'Слой появляется после установки модуля «Товары».', $productCardsUrl, 'Списки', 'blue'),
						self::componentStep($componentItems, 'products_public', 'product-card.twig', 'Резервная карточка', 'Работает, если отдельная опубликованная карточка не назначена.'),
						self::componentStep($componentItems, 'products_public', 'product-detail.twig', 'Страница товара', 'Галерея, цена, покупка, описание и характеристики.'),
					),
				),
			);
		}

		protected static function componentStep(array $items, $namespace, $file, $title, $description)
		{
			foreach ($items as $item) {
				if ($item['namespace'] !== $namespace || $item['file'] !== $file) { continue; }
				return self::step($title, $description, $item['url'], $item['source_label'],
					$item['source'] === 'theme' ? 'green' : ($item['source'] === 'fallback' ? 'blue' : 'amber'));
			}

			return self::step($title, $description, '', 'Не найден', 'amber');
		}

		protected static function step($title, $text, $url, $state, $tone)
		{
			return array(
				'title' => (string) $title,
				'text' => (string) $text,
				'url' => (string) $url,
				'state' => (string) $state,
				'tone' => in_array($tone, array('gray', 'blue', 'green', 'violet', 'amber'), true) ? $tone : 'gray',
			);
		}

		protected static function tableExists($table)
		{
			try { return DatabaseSchema::tableExists($table); }
			catch (\Throwable $e) { return false; }
		}

		protected static function decode($value)
		{
			return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
		}
	}
