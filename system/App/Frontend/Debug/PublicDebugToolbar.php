<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Debug/PublicDebugToolbar.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Debug;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\DebugChannel;
	use App\Common\HookCatalog;
	use App\Common\PublicConfiguration;
	use App\Common\PublicModuleRuntime;
	use App\Common\Registry;
	use App\Frontend\PublicPageContext;
	use App\Frontend\PublicProfiler;
	use App\Helpers\Hooks;
	use DB;

	/** Protected, dependency-free profiler injected into public HTML responses. */
	class PublicDebugToolbar
	{
		public static function mode()
		{
			$mode = defined('PROFILING') ? strtolower(trim((string) PROFILING)) : 'off';
			return in_array($mode, array('light', 'full', 'dev'), true) ? $mode : 'off';
		}

		public static function enabled()
		{
			return self::mode() !== 'off';
		}

		public static function boot()
		{
			if (!self::allowed()) {
				return;
			}

			PublicLayoutInspector::boot();
			Hooks::enableTrace(true);
			if (class_exists('App\\Common\\Db\\Core\\QueryDebug')) {
				\App\Common\Db\Core\QueryLogger::enableCallerTrace(true);
				\App\Common\Db\Core\QueryDebug::init(true);
				PublicDebugCollector::boot();
			}
		}

		public static function inject($html, $core = null)
		{
			$html = (string) $html;
			$allowed = self::allowed();
			$decorate = $allowed && !defined('ONLYCONTENT') && !self::isAjax() && stripos($html, '</body>') !== false;
			$html = PublicLayoutInspector::finalize($html, $decorate);
			if (!$decorate) {
				return $html;
			}

			$panel = self::render($core);
			return preg_replace('/<\/body>/i', $panel . '</body>', $html, 1);
		}

		protected static function allowed()
		{
			if (!self::enabled()) {
				return false;
			}

			$config = PublicConfiguration::all();
			$debug = isset($config['debug']) && is_array($config['debug']) ? $config['debug'] : array();
			$groups = isset($debug['groups']) && is_array($debug['groups']) ? array_map('intval', $debug['groups']) : array();
			$user = Auth::publicUser();
			if (is_array($user) && in_array((int) $user['group'], $groups, true)) {
				return true;
			}

			return Auth::systemUserCan('view_public_debug');
		}

		protected static function render($core)
		{
			$queries = self::queries();
			$timeline = PublicProfiler::timeline();
			$document = is_object($core) && isset($core->curentdoc) ? $core->curentdoc : null;
			$elapsed = defined('START_MICROTIME') ? microtime(true) - self::timestamp(START_MICROTIME) : 0.0;
			$status = (int) (http_response_code() ?: 200);
			$method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
			$uri = self::safeUri(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
			$systemUser = Auth::systemUser();
			$publicUser = Auth::publicUser();
			$queryTime = 0.0;
			foreach ($queries as $query) { $queryTime += (float) $query['time']; }

			$overview = self::cards(array(
				array('Время ответа', number_format($elapsed * 1000, 1, ',', ' ') . ' ms', self::metricTone($elapsed * 1000, 300, 800)),
				array('Пиковая память', self::bytes(memory_get_peak_usage(true)), 'blue'),
				array('SQL-запросы', count($queries) . ' · ' . number_format($queryTime * 1000, 1, ',', ' ') . ' ms', self::metricTone($queryTime * 1000, 80, 250)),
				array('HTTP-статус', (string) $status, $status >= 500 ? 'red' : ($status >= 400 ? 'amber' : 'green')),
			));
			$overview .= self::definitionList(array(
				'Метод' => $method,
				'Адрес' => $uri,
				'PHP runtime' => PHP_VERSION,
				'Системный пользователь' => self::systemUserLabel($systemUser),
				'Публичный пользователь' => self::publicUserLabel($publicUser),
				'Public-модули' => implode(', ', array_keys(PublicModuleRuntime::loaded())) ?: '—',
				'Данные документа' => Registry::get('document_snapshot_source', 'не запрашивались') === 'snapshot' ? 'JSON-снимок' : (Registry::get('document_snapshot_source', '') === 'database' ? 'БД + пересборка' : 'не запрашивались'),
				'Отложенная страница' => PublicPageContext::hasDeferredPage() ? 'Да' : 'Нет',
			));
			$overview = self::sectionHead('Сводка запроса', $method . ' · ' . $uri, self::statusBadge($status)) . $overview;

			$documentHtml = self::documentPanel($document);
			$debugEntries = DebugChannel::all();
			$debugHtml = self::sectionHead('Данные приложения', 'Структурированные значения из Debug::panel()', self::countBadge(count($debugEntries), $debugEntries ? 'violet' : 'slate'))
				. self::debugEntries($debugEntries);

			$sqlHtml = self::sectionHead('SQL-запросы', number_format($queryTime * 1000, 2, ',', ' ') . ' ms суммарно', self::countBadge(count($queries)))
				. self::queryTable($queries);
			$timelineHtml = self::sectionHead('Timeline', 'Этапы формирования текущего ответа', self::countBadge(self::leafCount($timeline)))
				. self::timeline($timeline);
			$cacheItems = method_exists('DB', 'getDebugInfo') ? DB::getDebugInfo() : array();
			$cacheItems = is_array($cacheItems) ? $cacheItems : array();
			$cacheHtml = self::sectionHead('Кеш', 'События кеширования текущего запроса', self::countBadge(self::leafCount($cacheItems)))
				. self::dataView($cacheItems);

			$httpData = self::masked(array(
				'GET' => $_GET,
				'POST' => $_POST,
				'COOKIE' => $_COOKIE,
				'HEADERS' => self::headers(),
			));
			$httpHtml = self::sectionHead('HTTP', $method . ' · ' . $uri, self::countBadge(self::leafCount($httpData)))
				. self::httpGroups($httpData);
			$sessionData = self::masked(isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
			$sessionHtml = self::sectionHead('Контексты авторизации', 'Публичный профиль и роль панели управления связаны в единую учётную запись', self::countBadge(($systemUser ? 1 : 0) + ($publicUser ? 1 : 0), $systemUser ? 'green' : 'slate'))
				. self::identityPanel($systemUser, $publicUser)
				. self::sectionHead('PHP Session', session_id() !== '' ? 'ID ' . self::shortId(session_id()) : 'Сессия не запущена', self::countBadge(self::leafCount($sessionData)))
				. self::dataView($sessionData);
			$errors = PublicDebugCollector::errors();
			$errorsHtml = self::sectionHead('Ошибки PHP', $errors ? 'Зафиксированы в текущем запросе' : 'Текущий запрос выполнен без предупреждений', self::countBadge(count($errors), $errors ? 'red' : 'green'))
				. self::errorList($errors);
			$hookTrace = Hooks::trace();
			$hookEntries = isset($hookTrace['entries']) && is_array($hookTrace['entries']) ? $hookTrace['entries'] : array();
			$hooksHtml = self::sectionHead('Хуки', 'События и обработчики текущего публичного запроса', self::countBadge(count($hookEntries), $hookEntries ? 'blue' : 'slate'))
				. self::hookList($hookEntries, isset($hookTrace['dropped']) ? (int) $hookTrace['dropped'] : 0);
			$files = array_map(function ($file) { return str_replace(BASEPATH . '/', '', $file); }, get_included_files());
			sort($files, SORT_NATURAL | SORT_FLAG_CASE);
			$filesHtml = self::sectionHead('Подключённые файлы', 'PHP-файлы текущего запроса', self::countBadge(count($files)))
				. self::fileList($files);
			$inspectorEntries = PublicLayoutInspector::entries();
			$inspectorHtml = self::sectionHead(
				'Состав страницы',
				'Управляемые элементы в порядке фактического вывода',
				self::countBadge(count($inspectorEntries), $inspectorEntries ? 'blue' : 'slate')
			) . self::inspectorPanel($inspectorEntries);

			$tabs = array(
				'overview' => array('Обзор', null),
				'document' => array('Документ', $document && isset($document->Id) ? (int) $document->Id : 0),
				'debug' => array('Данные', count($debugEntries)),
				'timeline' => array('Timeline', self::leafCount($timeline)),
				'sql' => array('SQL', count($queries)),
				'cache' => array('Кеш', self::leafCount($cacheItems)),
				'http' => array('HTTP', self::leafCount($httpData)),
				'session' => array('Session', self::leafCount($sessionData)),
				'errors' => array('Ошибки', count($errors)),
				'hooks' => array('Хуки', count($hookEntries)),
				'elements' => array('Элементы', count($inspectorEntries)),
				'files' => array('Файлы', count($files)),
			);
			$contents = array('overview' => $overview, 'document' => $documentHtml, 'debug' => $debugHtml, 'timeline' => $timelineHtml, 'sql' => $sqlHtml, 'cache' => $cacheHtml, 'http' => $httpHtml, 'session' => $sessionHtml, 'errors' => $errorsHtml, 'hooks' => $hooksHtml, 'elements' => $inspectorHtml, 'files' => $filesHtml);
			$nav = ''; $sections = '';
			foreach ($tabs as $id => $tab) {
				$active = $id === 'overview';
				$nav .= '<button type="button" role="tab" aria-selected="' . ($active ? 'true' : 'false') . '" aria-controls="pdt-tab-' . $id . '" data-pdt-tab="' . $id . '"' . ($active ? ' class="is-active"' : '') . '><span>' . self::e($tab[0]) . '</span>' . ($tab[1] !== null ? '<em' . ($id === 'errors' && $tab[1] > 0 ? ' class="is-alert"' : '') . '>' . (int) $tab[1] . '</em>' : '') . '</button>';
				$sections .= '<section id="pdt-tab-' . $id . '" role="tabpanel" data-pdt-panel="' . $id . '"' . (!$active ? ' hidden' : '') . '>' . $contents[$id] . '</section>';
			}

			$inspectorJson = json_encode($inspectorEntries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			if ($inspectorJson === false) { $inspectorJson = '[]'; }

			return '<div id="pdt-root"><button id="pdt-toggle" type="button" aria-label="Открыть публичную отладку"><b>AVE</b><span>' . number_format($elapsed * 1000, 0, ',', ' ') . ' ms</span><span>' . count($queries) . ' SQL</span><span>' . self::bytes(memory_get_peak_usage(true)) . '</span>' . ($errors ? '<i>' . count($errors) . '</i>' : '') . '</button><div id="pdt-panel" hidden><header data-pdt-drag-handle><div class="pdt-brand"><strong>AVE Public Debug</strong><span class="pdt-method">' . self::e($method) . '</span>' . self::statusBadge($status) . '</div><code>' . self::e($uri) . '</code><div class="pdt-head-actions"><button id="pdt-reset-position" type="button" aria-label="Сбросить положение" title="Сбросить положение">↺</button><button id="pdt-close" type="button" aria-label="Закрыть" title="Закрыть">×</button></div></header><nav role="tablist" aria-label="Разделы отладки">' . $nav . '</nav><main>' . $sections . '</main></div></div><script type="application/json" id="pdt-inspector-data">' . $inspectorJson . '</script>' . self::assets();
		}

		protected static function queries()
		{
			$result = array();
			if (method_exists('DB', 'getQueries')) {
				foreach ((array) DB::getQueries() as $item) {
					$item = (array) $item;
					$result[] = array('source' => 'Native', 'query' => isset($item['query']) ? $item['query'] : '', 'time' => self::number(isset($item['time']) ? $item['time'] : 0), 'caller' => self::caller(isset($item['caller']) ? $item['caller'] : array()));
				}
			}

			return $result;
		}

		protected static function queryTable(array $queries)
		{
			if (!$queries) { return self::emptyState('SQL-запросы не собраны', 'В текущем запросе обращения к базе данных не зафиксированы.'); }
			$html = self::filterBar('sql', 'Найти SQL или файл вызова', count($queries));
			$html .= '<div class="pdt-query-list" data-pdt-filter-list="sql">';
			foreach ($queries as $index => $query) {
				$milliseconds = (float) $query['time'] * 1000;
				$tone = self::metricTone($milliseconds, 15, 50);
				$filter = self::lower($query['query'] . ' ' . $query['caller'] . ' ' . $query['source']);
				$html .= '<details class="pdt-query" data-pdt-filter-item data-pdt-filter-text="' . self::e($filter) . '"><summary>'
					. '<span class="pdt-query-index">#' . ($index + 1) . '</span>'
					. '<span class="pdt-badge pdt-badge-blue">' . self::e($query['source']) . '</span>'
					. '<code>' . self::e($query['caller'] ?: 'Источник не определён') . '</code>'
					. '<strong class="pdt-value-' . $tone . '">' . number_format($milliseconds, 2, ',', ' ') . ' ms</strong>'
					. '</summary><pre><code>' . self::e(self::mask($query['query'])) . '</code></pre></details>';
			}

			return $html . '<div class="pdt-filter-empty" data-pdt-filter-empty hidden>Совпадений не найдено.</div></div>';
		}

		protected static function hookList(array $entries, $dropped)
		{
			if (!$entries) {
				return self::emptyState('Хуки не вызывались', 'Текущий маршрут не прошел через зарегистрированные lifecycle-точки.');
			}

			$html = self::filterBar('hooks', 'Найти событие или обработчик', count($entries));
			if ((int) $dropped > 0) {
				$html .= '<div class="pdt-notice">Не показано записей: ' . (int) $dropped . '. Ограничение защищает от переполнения отладчика.</div>';
			}

			$html .= '<div class="pdt-query-list" data-pdt-filter-list="hooks">';
			foreach ($entries as $index => $entry) {
				$name = isset($entry['name']) ? (string) $entry['name'] : '';
				$handler = isset($entry['handler']) && $entry['handler'] !== '' ? (string) $entry['handler'] : 'Нет подписчиков';
				$status = isset($entry['status']) ? (string) $entry['status'] : 'ok';
				$duration = isset($entry['duration']) ? (float) $entry['duration'] * 1000 : 0.0;
				$definition = HookCatalog::get($name);
				$description = is_array($definition) && isset($definition['description']) ? (string) $definition['description'] : '';
				$tone = $status === 'error' ? 'red' : ($status === 'unhandled' ? 'slate' : self::metricTone($duration, 5, 20));
				$filter = self::lower($name . ' ' . $handler . ' ' . $description . ' ' . $status);
				$priority = isset($entry['priority']) && $entry['priority'] !== null ? 'P' . (int) $entry['priority'] : '—';
				$details = array(
					'Описание' => $description !== '' ? $description : 'Событие не описано в каталоге',
					'Тип' => isset($entry['kind']) ? (string) $entry['kind'] : 'action',
					'Приоритет' => $priority,
					'Глубина' => isset($entry['depth']) ? (int) $entry['depth'] : 0,
					'Состояние' => $status,
				);
				if (!empty($entry['exception'])) { $details['Ошибка'] = (string) $entry['exception']; }
				$html .= '<details class="pdt-query" data-pdt-filter-item data-pdt-filter-text="' . self::e($filter) . '"><summary>'
					. '<span class="pdt-query-index">#' . ($index + 1) . '</span>'
					. '<span class="pdt-badge pdt-badge-' . $tone . '">' . self::e($priority) . '</span>'
					. '<code>' . self::e($name) . '</code>'
					. '<strong class="pdt-value-' . $tone . '">' . number_format($duration, 2, ',', ' ') . ' ms</strong>'
					. '</summary>' . self::definitionList(array_merge(array('Обработчик' => $handler), $details)) . '</details>';
			}

			return $html . '<div class="pdt-filter-empty" data-pdt-filter-empty hidden>Совпадений не найдено.</div></div>';
		}

		protected static function cards(array $cards)
		{
			$html = '<div class="pdt-cards">';
			foreach ($cards as $card) {
				$tone = isset($card[2]) ? preg_replace('/[^a-z]/', '', (string) $card[2]) : 'blue';
				$html .= '<div class="pdt-metric pdt-tone-' . self::e($tone) . '"><small>' . self::e($card[0]) . '</small><strong>' . self::e($card[1]) . '</strong></div>';
			}

			return $html . '</div>';
		}

		protected static function definitionList(array $items)
		{
			$html = '<dl class="pdt-dl">';
			foreach ($items as $key => $value) {
				$html .= '<div><dt>' . self::e($key) . '</dt><dd>' . self::e($value) . '</dd></div>';
			}

			return $html . '</dl>';
		}

		protected static function tree($value)
		{
			return self::dataView($value);
		}

		protected static function errorList(array $errors)
		{
			if (!$errors) { return self::emptyState('Ошибок нет', 'PHP-предупреждения и ошибки в текущем запросе не зафиксированы.', 'success'); }
			$html = '<div class="pdt-errors">';
			foreach ($errors as $error) {
				$type = isset($error['type']) ? (string) $error['type'] : 'PHP';
				$tone = stripos($type, 'notice') !== false || stripos($type, 'deprecated') !== false ? 'amber' : 'red';
				$html .= '<article><div><span class="pdt-badge pdt-badge-' . $tone . '">' . self::e($type) . '</span>'
					. (isset($error['code']) ? '<small>Код ' . (int) $error['code'] . '</small>' : '') . '</div>'
					. '<p>' . self::e(isset($error['message']) ? $error['message'] : '') . '</p>'
					. '<code>' . self::e((isset($error['file']) ? $error['file'] : '') . ':' . (isset($error['line']) ? $error['line'] : 0)) . '</code></article>';
			}

			return $html . '</div>';
		}

		protected static function debugEntries(array $entries)
		{
			if (!$entries) {
				return self::emptyState('Записей нет', 'В текущем запросе Debug::panel() не вызывался.');
			}

			$html = '<div class="pdt-debug-entries">';
			foreach ($entries as $index => $entry) {
				$source = (isset($entry['file']) ? $entry['file'] : '') . (isset($entry['line']) ? ':' . (int) $entry['line'] : '');
				$html .= '<article><header><div><span>' . ($index + 1) . '</span><div><strong>' . self::e(isset($entry['label']) ? $entry['label'] : 'Данные') . '</strong>'
					. '<code>' . self::e($source) . '</code></div></div><span class="pdt-badge pdt-badge-violet">' . self::e(isset($entry['type']) ? $entry['type'] : 'value') . '</span></header>'
					. self::dataView(self::masked(isset($entry['value']) ? $entry['value'] : null)) . '</article>';
			}

			return $html . '</div>';
		}

		protected static function documentPanel($document)
		{
			if (!is_object($document)) {
				return self::sectionHead('Документ', 'Маршрут не связан с документом', self::countBadge(0))
					. self::emptyState('Документ не определён', 'Ответ сформирован системным маршрутом, модулем или страницей ошибки.');
			}

			$id = self::objectValue($document, array('Id', 'id'), 0);
			$rubricId = self::objectValue($document, array('rubric_id', 'rubricId'), 0);
			$templateId = self::objectValue($document, array('document_template_id', 'template_id', 'rubric_template_id'), 0);
			$status = (int) self::objectValue($document, array('document_status', 'status'), 0);
			$title = self::objectValue($document, array('document_title', 'title'), 'Без названия');

			$html = self::sectionHead('Документ', (string) $title, self::countBadge((int) $id, 'blue'));
			$html .= self::cards(array(
				array('ID документа', $id ?: '—', 'blue'),
				array('Рубрика', $rubricId ? '#' . $rubricId : '—', 'violet'),
				array('Шаблон', $templateId ? '#' . $templateId : '—', 'amber'),
				array('Состояние', $status === 1 ? 'Опубликован' : 'Не опубликован', $status === 1 ? 'green' : 'red'),
			));
			$html .= self::definitionList(array(
				'Заголовок' => $title,
				'Алиас' => self::objectValue($document, array('document_alias', 'alias'), '—'),
				'Рубрика' => self::objectValue($document, array('rubric_title'), $rubricId ? '#' . $rubricId : '—'),
				'Опубликован' => self::objectValue($document, array('document_published'), '—'),
				'Изменён' => self::objectValue($document, array('document_changed'), '—'),
				'Кеш документа' => self::objectValue($document, array('document_cache'), '—'),
			));
			return $html;
		}

		protected static function objectValue($object, array $keys, $fallback = null)
		{
			foreach ($keys as $key) {
				if (is_object($object) && isset($object->{$key})) { return $object->{$key}; }
			}

			return $fallback;
		}

		protected static function sectionHead($title, $meta = '', $badge = '')
		{
			return '<div class="pdt-section-head"><div><h2>' . self::e($title) . '</h2>'
				. ($meta !== '' ? '<p>' . self::e($meta) . '</p>' : '') . '</div>' . $badge . '</div>';
		}

		protected static function countBadge($count, $tone = 'slate')
		{
			$tone = preg_replace('/[^a-z]/', '', (string) $tone);
			return '<span class="pdt-count pdt-count-' . self::e($tone) . '">' . (int) $count . '</span>';
		}

		protected static function statusBadge($status)
		{
			$status = (int) $status;
			$tone = $status >= 500 ? 'red' : ($status >= 400 ? 'amber' : ($status >= 300 ? 'violet' : 'green'));
			return '<span class="pdt-badge pdt-badge-' . $tone . '">HTTP ' . $status . '</span>';
		}

		protected static function metricTone($value, $warning, $danger)
		{
			if ((float) $value >= (float) $danger) { return 'red'; }
			if ((float) $value >= (float) $warning) { return 'amber'; }
			return 'green';
		}

		protected static function timeline(array $timeline)
		{
			$rows = array();
			self::flattenTimeline($timeline, array(), $rows);
			if (!$rows) { return self::emptyState('Timeline пуст', 'Этапы выполнения текущего запроса не зафиксированы.'); }

			$max = 0.0;
			foreach ($rows as $row) {
				if ($row['seconds'] !== null) { $max = max($max, $row['seconds']); }
			}

			$html = '<div class="pdt-timeline">';
			foreach ($rows as $row) {
				$width = $row['seconds'] !== null && $max > 0 ? max(2, min(100, ($row['seconds'] / $max) * 100)) : 0;
				$tone = $row['seconds'] !== null ? self::metricTone($row['seconds'] * 1000, 15, 50) : 'blue';
				$html .= '<div class="pdt-timeline-row"><code>' . self::e(implode(' › ', $row['path'])) . '</code>'
					. '<div class="pdt-timeline-track">' . ($width > 0 ? '<i class="pdt-bg-' . $tone . '" style="width:' . number_format($width, 2, '.', '') . '%"></i>' : '') . '</div>'
					. '<strong class="pdt-value-' . $tone . '">' . self::e($row['label']) . '</strong></div>';
			}

			return $html . '</div>';
		}

		protected static function flattenTimeline($value, array $path, array &$rows)
		{
			if (is_array($value)) {
				foreach ($value as $key => $child) {
					$childPath = $path;
					$childPath[] = (string) $key;
					self::flattenTimeline($child, $childPath, $rows);
				}

				return;
			}

			$seconds = null;
			$label = self::scalarLabel($value);
			if (is_numeric($value)) {
				$seconds = (float) $value;
				$label = number_format($seconds * 1000, 2, ',', ' ') . ' ms';
			} elseif (is_string($value) && preg_match('/^([0-9\s,.]+)\s*sec$/i', trim($value), $match)) {
				$seconds = self::number(str_replace(' ', '', $match[1]));
				$label = number_format($seconds * 1000, 2, ',', ' ') . ' ms';
			}

			$rows[] = array('path' => $path, 'seconds' => $seconds, 'label' => $label);
		}

		protected static function leafCount($value)
		{
			if (!is_array($value)) { return $value === null ? 0 : 1; }
			$count = 0;
			foreach ($value as $child) { $count += self::leafCount($child); }
			return $count;
		}

		protected static function dataView($value, $depth = 0)
		{
			if ($depth === 0 && is_array($value) && !$value) {
				return self::emptyState('Данные не собраны', 'Для текущего запроса значения отсутствуют.');
			}

			if ($depth >= 7) { return '<div class="pdt-depth-limit">Вложенность ограничена</div>'; }
			if (!is_array($value)) { return '<div class="pdt-data-scalar">' . self::scalarValue($value) . '</div>'; }

			$html = '<div class="pdt-data' . ($depth > 0 ? ' is-nested' : '') . '">';
			foreach ($value as $key => $child) {
				$label = is_int($key) ? '#' . $key : (string) $key;
				if (is_array($child)) {
					$html .= '<details class="pdt-data-node"' . ($depth < 1 ? ' open' : '') . '><summary><code>' . self::e($label) . '</code>'
						. '<span>' . count($child) . ' ' . self::plural(count($child), 'элемент', 'элемента', 'элементов') . '</span></summary>'
						. self::dataView($child, $depth + 1) . '</details>';
				} else {
					$html .= '<div class="pdt-data-row"><code>' . self::e($label) . '</code><div>' . self::scalarValue($child) . '</div></div>';
				}
			}

			return $html . '</div>';
		}

		protected static function scalarValue($value)
		{
			if ($value === null) { return '<span class="pdt-scalar pdt-scalar-null">null</span>'; }
			if (is_bool($value)) { return '<span class="pdt-scalar pdt-scalar-bool">' . ($value ? 'true' : 'false') . '</span>'; }
			if (is_int($value) || is_float($value)) { return '<span class="pdt-scalar pdt-scalar-number">' . self::e($value) . '</span>'; }
			if ($value === '') { return '<span class="pdt-scalar pdt-scalar-empty">пустая строка</span>'; }
			return '<span class="pdt-scalar pdt-scalar-string">' . self::e($value) . '</span>';
		}

		protected static function identityPanel($systemUser, $publicUser)
		{
			$system = is_array($systemUser) ? array(
				'ID' => isset($systemUser['id']) ? (int) $systemUser['id'] : 0,
				'Имя' => isset($systemUser['name']) ? (string) $systemUser['name'] : '',
				'Email' => isset($systemUser['email']) ? (string) $systemUser['email'] : '',
				'Роль' => isset($systemUser['role']) ? (string) $systemUser['role'] : '',
			) : array();
			$public = is_array($publicUser) ? array(
				'ID' => isset($publicUser['Id']) ? (int) $publicUser['Id'] : (isset($publicUser['id']) ? (int) $publicUser['id'] : 0),
				'Имя' => self::publicUserLabel($publicUser),
				'Email' => isset($publicUser['email']) ? (string) $publicUser['email'] : '',
				'Группа' => isset($publicUser['group']) ? (int) $publicUser['group'] : 0,
			) : array();

			return '<div class="pdt-identities">'
				. '<article><header><span class="pdt-badge ' . ($system ? 'pdt-badge-green' : 'pdt-count-slate') . '">Система</span><strong>' . ($system ? 'Авторизован' : 'Не авторизован') . '</strong></header>'
				. ($system ? self::dataView($system) : self::emptyState('Системного входа нет', 'Общий auth_token не найден.')) . '</article>'
				. '<article><header><span class="pdt-badge ' . ($public ? 'pdt-badge-blue' : 'pdt-count-slate') . '">Публичный сайт</span><strong>' . ($public ? 'Авторизован' : 'Гостевая сессия') . '</strong></header>'
				. ($public ? self::dataView($public) : self::emptyState('Публичный пользователь не вошел', 'Legacy-поля Anonymous относятся только к гостевой сессии сайта.')) . '</article>'
				. '</div>';
		}

		protected static function systemUserLabel($user)
		{
			if (!is_array($user)) { return 'Не авторизован'; }
			$name = trim((string) (isset($user['name']) ? $user['name'] : ''));
			if ($name === '') { $name = isset($user['email']) ? (string) $user['email'] : 'Системный пользователь'; }
			return $name . ' (#' . (isset($user['id']) ? (int) $user['id'] : 0) . ')';
		}

		protected static function publicUserLabel($user)
		{
			if (!is_array($user)) { return 'Гость'; }
			$parts = array();
			foreach (array('firstname', 'lastname', 'name') as $key) {
				if (!empty($user[$key])) { $parts[] = trim((string) $user[$key]); }
			}

			$name = trim(implode(' ', array_unique($parts)));
			if ($name === '') { $name = isset($user['email']) ? (string) $user['email'] : 'Публичный пользователь'; }
			$id = isset($user['Id']) ? (int) $user['Id'] : (isset($user['id']) ? (int) $user['id'] : 0);
			return $name . ' (#' . $id . ')';
		}

		protected static function scalarLabel($value)
		{
			if (is_bool($value)) { return $value ? 'Да' : 'Нет'; }
			if ($value === null) { return 'null'; }
			if (is_array($value)) { return count($value) . ' элементов'; }
			return (string) $value;
		}

		protected static function httpGroups(array $groups)
		{
			$html = '<div class="pdt-http-groups">';
			foreach ($groups as $name => $values) {
				$count = self::leafCount($values);
				$tone = $name === 'POST' ? 'violet' : ($name === 'COOKIE' ? 'amber' : ($name === 'HEADERS' ? 'green' : 'blue'));
				$html .= '<section><header><span class="pdt-badge pdt-badge-' . $tone . '">' . self::e($name) . '</span>' . self::countBadge($count, $tone) . '</header>'
					. self::dataView(is_array($values) ? $values : array($values)) . '</section>';
			}

			return $html . '</div>';
		}

		protected static function fileList(array $files)
		{
			if (!$files) { return self::emptyState('Файлы не собраны', 'Список подключённых PHP-файлов пуст.'); }
			$html = self::filterBar('files', 'Найти подключённый файл', count($files));
			$html .= '<div class="pdt-file-list" data-pdt-filter-list="files">';
			foreach ($files as $index => $file) {
				$directory = dirname($file);
				$name = basename($file);
				$html .= '<div data-pdt-filter-item data-pdt-filter-text="' . self::e(self::lower($file)) . '"><span>' . ($index + 1) . '</span><div><strong>' . self::e($name) . '</strong>'
					. '<code>' . self::e($directory === '.' ? '/' : $directory . '/') . '</code></div></div>';
			}

			return $html . '<div class="pdt-filter-empty" data-pdt-filter-empty hidden>Совпадений не найдено.</div></div>';
		}

		protected static function inspectorPanel(array $entries)
		{
			if (!$entries) {
				return self::emptyState('Элементы не найдены', 'Страница не содержит поддерживаемых границ вывода.');
			}

			$available = count(array_filter($entries, function ($entry) { return !empty($entry['has_boundary']); }));
			$html = '<div class="pdt-inspector-actions"><button type="button" class="pdt-command" data-pdt-inspector-toggle>Показать на странице</button><span>Доступно областей: <b>' . (int) $available . '</b></span></div>';
			$html .= self::filterBar('elements', 'Тип, название, ID, alias или тег', count($entries));
			$html .= '<div class="pdt-inspector-list" data-pdt-filter-list="elements">';
			foreach ($entries as $entry) {
				$type = isset($entry['type']) ? (string) $entry['type'] : 'component';
				$token = isset($entry['token']) ? (int) $entry['token'] : 0;
				$title = isset($entry['title']) && $entry['title'] !== '' ? (string) $entry['title'] : self::inspectorLabel($type);
				$identity = isset($entry['entity_id']) && $entry['entity_id'] !== '' ? '#' . $entry['entity_id'] : '';
				$alias = isset($entry['alias']) && $entry['alias'] !== '' ? (string) $entry['alias'] : '';
				$tag = isset($entry['tag']) ? (string) $entry['tag'] : '';
				$search = self::lower(self::inspectorLabel($type) . ' ' . $title . ' ' . $identity . ' ' . $alias . ' ' . $tag);
				$depth = isset($entry['depth']) ? max(0, min(8, (int) $entry['depth'])) : 0;
				$html .= '<article class="pdt-inspector-row pdt-inspector-' . self::e($type) . (!empty($entry['has_boundary']) ? '' : ' is-unmapped') . '" data-pdt-filter-item data-pdt-filter-text="' . self::e($search) . '" data-pdt-inspector-row="' . $token . '" style="--pdt-depth:' . $depth . '">';
				$html .= '<span class="pdt-inspector-dot" aria-hidden="true"></span><div class="pdt-inspector-info"><small>' . self::e(self::inspectorLabel($type)) . ($identity !== '' ? ' · ' . self::e($identity) : '') . '</small><strong>' . self::e($title) . '</strong>';
				if ($alias !== '' || $tag !== '') { $html .= '<code>' . self::e($tag !== '' ? $tag : $alias) . '</code>'; }
				$html .= '</div><div class="pdt-inspector-row-actions">';
				if (!empty($entry['has_boundary'])) { $html .= '<button type="button" data-pdt-inspector-find="' . $token . '">Найти</button>'; }
				if ($tag !== '') { $html .= '<button type="button" data-pdt-inspector-copy="' . $token . '">Копировать тег</button>'; }
				if (!empty($entry['edit_url'])) { $html .= '<a href="' . self::e($entry['edit_url']) . '" target="_blank" rel="noopener">Редактировать</a>'; }
				$html .= '</div></article>';
			}

			return $html . '<div class="pdt-filter-empty" data-pdt-filter-empty hidden>Совпадений не найдено.</div></div>';
		}

		protected static function inspectorLabel($type)
		{
			$labels = array(
				'document' => 'Документ',
				'block' => 'Блок',
				'request' => 'Запрос',
				'navigation' => 'Навигация',
				'module' => 'Модуль',
			);

			return isset($labels[$type]) ? $labels[$type] : 'Компонент';
		}

		protected static function filterBar($id, $placeholder, $count)
		{
			return '<div class="pdt-filter"><span aria-hidden="true">⌕</span><input type="search" data-pdt-filter="' . self::e($id) . '" placeholder="' . self::e($placeholder) . '" autocomplete="off"><small>' . (int) $count . '</small></div>';
		}

		protected static function emptyState($title, $text, $tone = 'neutral')
		{
			return '<div class="pdt-empty pdt-empty-' . self::e($tone) . '"><strong>' . self::e($title) . '</strong><span>' . self::e($text) . '</span></div>';
		}

		protected static function shortId($value)
		{
			$value = (string) $value;
			if (strlen($value) <= 16) { return $value; }
			return substr($value, 0, 8) . '…' . substr($value, -6);
		}

		protected static function lower($value)
		{
			return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
		}

		protected static function plural($count, $one, $few, $many)
		{
			$count = abs((int) $count) % 100;
			$last = $count % 10;
			if ($count > 10 && $count < 20) { return $many; }
			if ($last > 1 && $last < 5) { return $few; }
			if ($last === 1) { return $one; }
			return $many;
		}

		protected static function headers()
		{
			$headers = array();
			foreach ($_SERVER as $key => $value) {
				if (strpos($key, 'HTTP_') !== 0 && !in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH'), true)) { continue; }
				$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', preg_replace('/^HTTP_/', '', $key)))));
				$headers[$name] = $value;
			}

			return $headers;
		}

		protected static function masked($value, $key = '')
		{
			if (is_array($value)) {
				$result = array();
				foreach ($value as $itemKey => $itemValue) { $result[$itemKey] = self::masked($itemValue, (string) $itemKey); }
				return $result;
			}

			if (preg_match('/pass|password|passwd|secret|token|csrf|auth|cookie|api.?key|session|sessid|phpsessid/i', $key)) { return '***'; }
			return is_scalar($value) || $value === null ? $value : gettype($value);
		}

		protected static function safeUri($uri)
		{
			$uri = (string) $uri;
			$parts = parse_url($uri);
			if (!is_array($parts) || empty($parts['query'])) { return $uri; }
			parse_str($parts['query'], $query);
			$query = self::masked($query);
			$path = isset($parts['path']) ? $parts['path'] : '/';
			return $path . ($query ? '?' . http_build_query($query) : '');
		}

		protected static function caller($caller)
		{
			if (!is_array($caller) || !$caller) { return ''; }
			for ($index = count($caller) - 1; $index >= 0; $index--) {
				$frame = $caller[$index];
				if (!is_array($frame) || empty($frame['call_file'])) { continue; }
				$file = str_replace('\\', '/', (string) $frame['call_file']);
				if (strpos($file, '/system/App/Common/Db/') !== false) { continue; }
				$file = str_replace(BASEPATH . '/', '', $file);
				return $file . (isset($frame['call_line']) ? ':' . $frame['call_line'] : '');
			}

			return '';
		}

		protected static function mask($value)
		{
			return preg_replace('/((?:password|passwd|secret|token|api[_-]?key)\s*=\s*)([\'\"][^\'\"]*[\'\"]|[^\s,;]+)/i', '$1***', (string) $value);
		}

		protected static function number($value)
		{
			return (float) str_replace(',', '.', (string) $value);
		}

		protected static function timestamp($value)
		{
			$parts = explode(' ', trim((string) $value), 2);
			return count($parts) === 2 ? (float) $parts[1] + (float) $parts[0] : (float) $value;
		}

		protected static function bytes($bytes)
		{
			return number_format((float) $bytes / 1048576, 1, ',', ' ') . ' MB';
		}

		protected static function isAjax()
		{
			return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		}

		protected static function e($value)
		{
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		}

		protected static function assets()
		{
			return <<<'HTML'
<style>
#pdt-root,#pdt-root *,#pdt-panel,#pdt-panel *,#pdi-layer,#pdi-layer *{box-sizing:border-box;letter-spacing:0}
#pdt-root,#pdt-panel,#pdi-layer{font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#182033;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
#pdt-root{position:fixed;z-index:2147483646;right:12px;top:12px}
#pdt-root.is-header-mounted{position:relative;right:auto;top:auto;flex:0 0 118px;width:118px;margin-right:10px}
#pdt-root.is-header-mounted #pdt-toggle{width:100%;justify-content:center}
#pdt-root.is-header-mounted #pdt-toggle span:nth-of-type(n+2){display:none}
#pdt-root button,#pdt-root input,#pdt-panel button,#pdt-panel input{font:inherit}
#pdt-root button:focus-visible,#pdt-root input:focus-visible,#pdt-root summary:focus-visible,#pdt-panel button:focus-visible,#pdt-panel input:focus-visible,#pdt-panel a:focus-visible,#pdt-panel summary:focus-visible,#pdi-layer button:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
#pdt-root [hidden],#pdt-panel [hidden],#pdi-layer [hidden]{display:none!important}
#pdt-toggle{display:flex;align-items:center;gap:10px;min-height:40px;padding:0 13px;border:1px solid #334155;border-radius:6px;background:#111827;color:#e5e7eb;box-shadow:0 8px 24px rgba(15,23,42,.28);cursor:pointer;transition:transform .15s ease,background-color .15s ease,box-shadow .15s ease}
#pdt-toggle:hover{background:#1e293b;box-shadow:0 10px 28px rgba(15,23,42,.34)}
#pdt-toggle:active{transform:scale(.96)}
#pdt-toggle b{color:#60a5fa;font-size:12px}
#pdt-toggle span,#pdt-toggle i{}
#pdt-toggle i{display:grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#dc2626;color:#fff;font-size:11px;font-style:normal;font-weight:700}
#pdt-panel{position:fixed;z-index:2147483647;right:12px;bottom:12px;width:min(1240px,calc(100vw - 24px));height:min(78vh,820px);border:1px solid #334155;border-radius:8px;background:#f5f7fa;box-shadow:0 24px 70px rgba(15,23,42,.4);overflow:hidden}
#pdt-panel>header{height:56px;display:flex;align-items:center;gap:12px;padding:0 8px 0 16px;background:#111827;color:#fff;cursor:grab;touch-action:none;user-select:none}
#pdt-panel.is-dragging>header{cursor:grabbing}
.pdt-brand{display:flex;align-items:center;gap:8px;white-space:nowrap}
.pdt-brand strong{font-size:14px}
.pdt-method{display:inline-flex;align-items:center;height:24px;padding:0 7px;border:1px solid #475569;border-radius:4px;color:#cbd5e1;font:700 11px/1 ui-monospace,SFMono-Regular,Consolas,monospace}
#pdt-panel>header>code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#94a3b8;font:12px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace;user-select:text}
.pdt-head-actions{display:flex;align-items:center;flex:0 0 auto;gap:2px;margin-left:auto}
#pdt-reset-position,#pdt-close{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border:0;border-radius:5px;background:transparent;color:#cbd5e1;line-height:1;cursor:pointer;transition:transform .15s ease,background-color .15s ease,color .15s ease}
#pdt-reset-position{font-size:20px}#pdt-close{font-size:25px}
#pdt-reset-position:hover,#pdt-close:hover{background:#334155;color:#fff}
#pdt-reset-position:active,#pdt-close:active{transform:scale(.96)}
#pdt-panel>nav{height:52px;display:flex;align-items:center;gap:3px;padding:5px 10px;border-bottom:1px solid #dce3eb;background:#fff;overflow-x:auto;overflow-y:hidden;scrollbar-width:thin}
#pdt-panel>nav button{display:flex;align-items:center;gap:7px;min-height:40px;padding:0 10px;border:0;border-radius:5px;background:transparent;color:#526074;font-weight:650;cursor:pointer;white-space:nowrap;transition:transform .15s ease,background-color .15s ease,color .15s ease}
#pdt-panel>nav button:hover{background:#f1f5f9;color:#1e293b}
#pdt-panel>nav button:active{transform:scale(.96)}
#pdt-panel>nav button.is-active{background:#dbeafe;color:#1d4ed8}
#pdt-panel>nav button em{display:grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#e8edf3;color:#64748b;font-size:10px;font-style:normal;font-weight:750;}
#pdt-panel>nav button.is-active em{background:#fff;color:#1d4ed8}
#pdt-panel>nav button em.is-alert{background:#fee2e2;color:#b91c1c}
#pdt-panel>main{height:calc(100% - 108px);overflow:auto;padding:16px;overscroll-behavior:contain}
#pdt-panel section[role=tabpanel]{max-width:100%}
.pdt-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0 0 12px;padding:0 2px}
.pdt-section-head h2{margin:0;color:#172033;font-size:17px;line-height:1.3;font-weight:720;text-wrap:balance}
.pdt-section-head p{margin:3px 0 0;color:#6b778a;font-size:12px;overflow-wrap:anywhere;text-wrap:pretty}
.pdt-badge,.pdt-count{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;border-radius:4px;font-weight:700;}
.pdt-badge{min-height:24px;padding:0 7px;font-size:10px;text-transform:uppercase}
.pdt-count{min-width:26px;height:26px;padding:0 7px;font-size:11px}
.pdt-badge-blue,.pdt-count-blue{background:#dbeafe;color:#1d4ed8}.pdt-badge-green,.pdt-count-green{background:#dcfce7;color:#15803d}.pdt-badge-amber,.pdt-count-amber{background:#fef3c7;color:#a16207}.pdt-badge-red,.pdt-count-red{background:#fee2e2;color:#b91c1c}.pdt-badge-violet,.pdt-count-violet{background:#ede9fe;color:#6d28d9}.pdt-badge-slate,.pdt-count-slate{background:#e8edf3;color:#526074}
.pdt-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
.pdt-metric{position:relative;min-width:0;padding:13px 14px 14px;border:0;border-radius:7px;background:#2563eb;box-shadow:0 1px 2px rgba(15,23,42,.08);overflow:hidden}
.pdt-metric.pdt-tone-green{background:#15803d}.pdt-metric.pdt-tone-amber{background:#b45309}.pdt-metric.pdt-tone-red{background:#dc2626}.pdt-metric.pdt-tone-violet{background:#7c3aed}.pdt-metric.pdt-tone-blue{background:#2563eb}
.pdt-metric small,.pdt-metric strong{display:block;overflow-wrap:anywhere}
.pdt-metric small{color:rgba(255,255,255,.82);font-size:11px}
.pdt-metric strong{margin-top:5px;color:#fff;font-size:18px;line-height:1.25;}
.pdt-dl{margin:0;border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-dl>div{display:grid;grid-template-columns:180px minmax(0,1fr);border-bottom:1px solid #edf1f5}
.pdt-dl>div:last-child{border-bottom:0}
.pdt-dl dt,.pdt-dl dd{margin:0;padding:10px 12px}
.pdt-dl dt{background:#fafbfc;color:#526074;font-weight:650}
.pdt-dl dd{min-width:0;color:#273449;overflow-wrap:anywhere}
.pdt-identities{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:18px}
.pdt-identities>article{min-width:0;border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-identities>article>header{display:flex;align-items:center;gap:9px;min-height:46px;padding:8px 11px;border-bottom:1px solid #edf1f5;background:#fafbfc}
.pdt-identities>article>header strong{color:#273449;font-size:12px}
.pdt-identities .pdt-data{border:0;border-radius:0}
.pdt-identities .pdt-empty{min-height:132px;border:0;border-radius:0}
.pdt-filter{display:grid;grid-template-columns:24px minmax(0,1fr) auto;align-items:center;gap:4px;min-height:44px;margin-bottom:10px;padding:0 10px;border:1px solid #ccd6e2;border-radius:6px;background:#fff}
.pdt-filter:focus-within{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.pdt-filter>span{color:#8491a3;font-size:20px;line-height:1}
.pdt-filter input{min-width:0;height:40px;border:0;outline:0;background:transparent;color:#172033}
.pdt-filter input::placeholder{color:#8a97a8}
.pdt-filter small{padding:2px 6px;border-radius:4px;background:#edf1f5;color:#64748b;font-weight:700;}
.pdt-query-list{display:grid;gap:8px}
.pdt-query{border:1px solid #dce3eb;border-radius:6px;background:#fff;overflow:hidden}
.pdt-query summary{display:grid;grid-template-columns:42px 62px minmax(0,1fr) 90px;align-items:center;gap:9px;min-height:44px;padding:7px 11px;cursor:pointer;list-style:none}
.pdt-query summary::-webkit-details-marker{display:none}
.pdt-query[open]{border-color:#b8c5d4}
.pdt-query-index{color:#8491a3;font-size:11px;}
.pdt-query summary code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6b778a;font:11px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-query summary strong{justify-self:end;font-size:11px;}
.pdt-query pre{max-height:330px;margin:0;padding:13px 14px;border-top:1px solid #26344a;background:#101827;color:#dbeafe;white-space:pre-wrap;overflow:auto;overflow-wrap:anywhere;font:12px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-value-green{color:#15803d}.pdt-value-amber{color:#a16207}.pdt-value-red{color:#b91c1c}.pdt-value-blue{color:#1d4ed8}.pdt-value-slate{color:#64748b}.pdt-notice{margin:0 0 10px;padding:9px 11px;border:1px solid #f5d38a;border-radius:6px;background:#fffbeb;color:#8a5a00;font-size:11px}
.pdt-debug-entries{display:grid;gap:10px}
.pdt-debug-entries>article{min-width:0;border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-debug-entries>article>header{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:52px;padding:7px 11px;border-bottom:1px solid #edf1f5;background:#fafbfc}
.pdt-debug-entries>article>header>div{display:flex;align-items:center;min-width:0;gap:10px}
.pdt-debug-entries>article>header>div>span{display:grid;place-items:center;flex:0 0 25px;width:25px;height:25px;border-radius:5px;background:#ede9fe;color:#6d28d9;font-size:10px;font-weight:750;}
.pdt-debug-entries>article>header>div>div{min-width:0}
.pdt-debug-entries>article>header strong,.pdt-debug-entries>article>header code{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pdt-debug-entries>article>header strong{color:#273449;font-size:12px}.pdt-debug-entries>article>header code{margin-top:2px;color:#8491a3;font:10px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-debug-entries>article>.pdt-data{border:0;border-radius:0}
.pdt-timeline{border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-timeline-row{display:grid;grid-template-columns:minmax(190px,1.3fr) minmax(120px,2fr) 88px;align-items:center;gap:12px;min-height:43px;padding:7px 12px;border-bottom:1px solid #edf1f5}
.pdt-timeline-row:last-child{border-bottom:0}
.pdt-timeline-row>code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#435269;font:11px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-timeline-track{height:6px;border-radius:3px;background:#edf1f5;overflow:hidden}
.pdt-timeline-track i{display:block;height:100%;border-radius:3px}.pdt-bg-green{background:#22c55e}.pdt-bg-amber{background:#f59e0b}.pdt-bg-red{background:#ef4444}.pdt-bg-blue{background:#3b82f6}
.pdt-timeline-row>strong{text-align:right;font-size:11px;}
.pdt-data{border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-data.is-nested{margin:0;border:0;border-top:1px solid #edf1f5;border-radius:0;background:#fafbfc}
.pdt-data-row{display:grid;grid-template-columns:minmax(150px,32%) minmax(0,1fr);border-bottom:1px solid #edf1f5}
.pdt-data-row:last-child{border-bottom:0}
.pdt-data-row>code,.pdt-data-row>div{min-width:0;padding:9px 11px}
.pdt-data-row>code{color:#475569;font:600 11px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-data-row>div{overflow-wrap:anywhere}
.pdt-data-node{border-bottom:1px solid #edf1f5}.pdt-data-node:last-child{border-bottom:0}
.pdt-data-node>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:40px;padding:6px 11px;cursor:pointer;list-style:none}
.pdt-data-node>summary::-webkit-details-marker{display:none}
.pdt-data-node>summary:before{content:"›";flex:0 0 auto;color:#8491a3;font-size:17px;transition:transform .15s ease}
.pdt-data-node[open]>summary:before{transform:rotate(90deg)}
.pdt-data-node>summary code{min-width:0;margin-right:auto;overflow:hidden;text-overflow:ellipsis;color:#475569;font:600 11px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-data-node>summary span{color:#8491a3;font-size:10px;white-space:nowrap}
.pdt-data-node>.pdt-data{padding-left:18px}
.pdt-scalar{font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace}.pdt-scalar-number{color:#1d4ed8}.pdt-scalar-bool{color:#6d28d9;font-weight:700}.pdt-scalar-null,.pdt-scalar-empty{color:#8491a3;font-style:italic}.pdt-scalar-string{color:#273449}
.pdt-depth-limit,.pdt-data-scalar{padding:10px 12px;color:#8491a3}
.pdt-http-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start}
.pdt-http-groups>section{min-width:0}
.pdt-http-groups>section>header{display:flex;align-items:center;justify-content:space-between;min-height:36px;padding:0 2px 6px}
.pdt-errors{display:grid;gap:8px}
.pdt-errors article{display:grid;grid-template-columns:130px minmax(0,1fr);gap:6px 12px;padding:12px;border:1px solid #fecaca;border-left:3px solid #ef4444;border-radius:6px;background:#fff}
.pdt-errors article>div{display:flex;align-items:flex-start;gap:7px}.pdt-errors article small{color:#8491a3;font-size:10px;white-space:nowrap}
.pdt-errors p{margin:1px 0;color:#273449;overflow-wrap:anywhere;text-wrap:pretty}
.pdt-errors code{grid-column:2;color:#6b778a;overflow-wrap:anywhere;font:11px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-file-list{border:1px solid #dce3eb;border-radius:7px;background:#fff;overflow:hidden}
.pdt-file-list>[data-pdt-filter-item]{display:grid;grid-template-columns:44px minmax(0,1fr);align-items:center;min-height:46px;border-bottom:1px solid #edf1f5}
.pdt-file-list>[data-pdt-filter-item]:last-of-type{border-bottom:0}
.pdt-file-list>[data-pdt-filter-item]>span{padding:0 10px;color:#94a3b8;text-align:right;font-size:10px;}
.pdt-file-list>[data-pdt-filter-item]>div{min-width:0;padding:6px 11px;border-left:1px solid #edf1f5}
.pdt-file-list strong,.pdt-file-list code{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pdt-file-list strong{color:#273449;font-size:12px}.pdt-file-list code{margin-top:2px;color:#8491a3;font:10px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-empty{display:grid;place-items:center;min-height:150px;padding:24px;border:1px dashed #cbd5e1;border-radius:7px;background:#fff;color:#6b778a;text-align:center}
.pdt-empty strong{color:#435269;font-size:14px}.pdt-empty span{max-width:520px;margin-top:4px;text-wrap:pretty}.pdt-empty-success{border-color:#bbf7d0;background:#f7fef9}.pdt-empty-success strong{color:#15803d}
.pdt-filter-empty{padding:22px;color:#6b778a;text-align:center}
.pdt-inspector-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;padding:10px 12px;border-radius:7px;background:#eaf2ff;color:#334155;box-shadow:inset 0 0 0 1px rgba(37,99,235,.1)}
.pdt-inspector-actions span{font-size:11px}.pdt-inspector-actions b{}
.pdt-command{min-height:40px;padding:0 14px;border:0;border-radius:5px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;transition:transform .15s ease,background-color .15s ease}
.pdt-command:hover{background:#1d4ed8}.pdt-command:active{transform:scale(.96)}.pdt-command.is-active{background:#0f766e}
.pdt-inspector-list{display:grid;gap:7px}
.pdt-inspector-row{--pdt-tone:#64748b;display:grid;grid-template-columns:12px minmax(0,1fr) auto;align-items:center;gap:11px;min-height:64px;margin-left:calc(var(--pdt-depth) * 14px);padding:9px 10px;border-radius:7px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.08),inset 0 0 0 1px rgba(15,23,42,.07)}
.pdt-inspector-row.is-unmapped{opacity:.58}.pdt-inspector-document{--pdt-tone:#16a34a}.pdt-inspector-block{--pdt-tone:#2563eb}.pdt-inspector-request{--pdt-tone:#7c3aed}.pdt-inspector-navigation{--pdt-tone:#d97706}.pdt-inspector-module{--pdt-tone:#0f766e}
.pdt-inspector-dot{width:10px;height:10px;border-radius:50%;background:var(--pdt-tone);box-shadow:0 0 0 3px rgba(100,116,139,.14)}
.pdt-inspector-info{min-width:0}.pdt-inspector-info small,.pdt-inspector-info strong,.pdt-inspector-info code{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pdt-inspector-info small{color:var(--pdt-tone);font-size:10px;font-weight:750;text-transform:uppercase}.pdt-inspector-info strong{margin-top:2px;color:#273449;font-size:12px}.pdt-inspector-info code{margin-top:2px;color:#7a8798;font:10px/1.35 ui-monospace,SFMono-Regular,Consolas,monospace}
.pdt-inspector-row-actions{display:flex;align-items:center;justify-content:flex-end;gap:4px}
.pdt-inspector-row-actions button,.pdt-inspector-row-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 10px;border:0;border-radius:5px;background:#eef2f7;color:#435269;font-size:11px;font-weight:700;text-decoration:none;cursor:pointer;transition:transform .15s ease,background-color .15s ease,color .15s ease}
.pdt-inspector-row-actions button:hover,.pdt-inspector-row-actions a:hover{background:#dbeafe;color:#1d4ed8}.pdt-inspector-row-actions button:active,.pdt-inspector-row-actions a:active{transform:scale(.96)}
#pdi-layer{position:fixed;z-index:2147483644;inset:0;pointer-events:none}
.pdi-box{--pdi-tone:#64748b;position:fixed;border:2px solid var(--pdi-tone);border-radius:4px;background:rgba(100,116,139,.07);box-shadow:0 0 0 1px rgba(255,255,255,.72),0 4px 18px rgba(15,23,42,.12);pointer-events:none}
.pdi-document{--pdi-tone:#16a34a;background:rgba(22,163,74,.07)}.pdi-block{--pdi-tone:#2563eb;background:rgba(37,99,235,.07)}.pdi-request{--pdi-tone:#7c3aed;background:rgba(124,58,237,.07)}.pdi-navigation{--pdi-tone:#d97706;background:rgba(217,119,6,.07)}.pdi-module{--pdi-tone:#0f766e;background:rgba(15,118,110,.07)}
.pdi-box.is-selected{box-shadow:0 0 0 3px rgba(37,99,235,.2),0 6px 24px rgba(15,23,42,.18)}
.pdi-label{position:absolute;left:-2px;top:-28px;display:flex;align-items:center;max-width:min(360px,80vw);height:26px;padding:0 9px;border:0;border-radius:4px;background:var(--pdi-tone);color:#fff;box-shadow:0 4px 14px rgba(15,23,42,.24);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px;font-weight:750;cursor:pointer;pointer-events:auto}
.pdi-box.is-label-inside .pdi-label{top:2px;left:2px}
#pdi-control{position:fixed;z-index:2147483645;left:12px;top:12px;display:flex;align-items:center;gap:9px;min-height:42px;padding:4px 5px 4px 12px;border-radius:6px;background:#111827;color:#e5e7eb;box-shadow:0 10px 30px rgba(15,23,42,.34);pointer-events:auto}
#pdi-control strong{font-size:11px}#pdi-control span{color:#93c5fd;font-size:10px;}
#pdi-control button{min-width:34px;height:34px;border:0;border-radius:4px;background:#334155;color:#fff;font-size:20px;line-height:1;cursor:pointer;transition:transform .15s ease,background-color .15s ease}
#pdi-control button:hover{background:#475569}#pdi-control button:active{transform:scale(.96)}
@media(max-width:760px){
  #pdt-root{right:8px;top:8px}
  #pdt-root.is-header-mounted{flex-basis:64px;width:64px;margin-right:6px}
  #pdt-root.is-header-mounted #pdt-toggle span{display:none}
  #pdt-toggle span:nth-of-type(n+2){display:none}
  #pdt-panel{right:6px;bottom:6px;left:auto!important;top:auto!important;width:calc(100vw - 12px);height:calc(100dvh - 12px);max-height:none}
  #pdt-panel>header{height:52px;padding-left:11px}.pdt-brand strong{font-size:12px}#pdt-panel>header>code{display:none}
  #pdt-reset-position{display:none}
  #pdt-panel>nav{height:50px;padding:4px 7px}
  #pdt-panel>main{height:calc(100% - 102px);padding:11px}
  .pdt-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.pdt-metric{padding:11px}.pdt-metric strong{font-size:15px}
  .pdt-dl>div{grid-template-columns:112px minmax(0,1fr)}.pdt-dl dt,.pdt-dl dd{padding:9px}
  .pdt-query summary{grid-template-columns:36px minmax(0,1fr) 76px}.pdt-query summary .pdt-badge{display:none}.pdt-query summary code{grid-column:2}
  .pdt-timeline-row{grid-template-columns:minmax(130px,1.4fr) 74px}.pdt-timeline-track{display:none}
  .pdt-http-groups{grid-template-columns:1fr}.pdt-data-row{grid-template-columns:minmax(100px,38%) minmax(0,1fr)}
  .pdt-identities{grid-template-columns:1fr}
  .pdt-errors article{grid-template-columns:1fr}.pdt-errors code{grid-column:1}
  .pdt-inspector-row{grid-template-columns:12px minmax(0,1fr);margin-left:0}.pdt-inspector-row-actions{grid-column:2;justify-content:flex-start;flex-wrap:wrap}.pdt-inspector-actions{align-items:flex-start;flex-direction:column}.pdt-inspector-actions .pdt-command{width:100%}
  #pdi-control{left:6px;top:6px}
}
@media(prefers-reduced-motion:reduce){#pdt-root *,#pdt-panel *,#pdi-layer *{transition-duration:0s!important;scroll-behavior:auto!important}}
</style>
<script>
(function(window,document){
  'use strict';
  var AvePublicDebug={
    initialized:false,
    storageKey:'ave-public-debug-tab',
    positionKey:'ave-public-debug-position',
    previousOverflow:'',
    inspectorActive:false,
    inspectorFrame:0,
    drag:null,
    init:function(){
      if(this.initialized){return;}
      this.root=document.getElementById('pdt-root');
      this.panel=document.getElementById('pdt-panel');
      this.toggleButton=document.getElementById('pdt-toggle');
      this.closeButton=document.getElementById('pdt-close');
      this.resetButton=document.getElementById('pdt-reset-position');
      this.dragHandle=this.panel?this.panel.querySelector('[data-pdt-drag-handle]'):null;
      if(!this.root||!this.panel||!this.toggleButton||!this.closeButton){return;}
      this.inspectorEntries=this.readInspectorData();
      this.inspectorByToken={};
      for(var index=0;index<this.inspectorEntries.length;index++){
        this.inspectorByToken[String(this.inspectorEntries[index].token)]=this.inspectorEntries[index];
      }
	  this.captureInspectorAnchors();
      this.initialized=true;
      this.build();
      this.events();
      this.restoreState();
    },
    build:function(){
      document.body.appendChild(this.panel);
      var headerUser=document.querySelector('.pre_header_user');
      if(headerUser){
        headerUser.insertBefore(this.root,headerUser.firstChild);
        this.root.classList.add('is-header-mounted');
      }
      this.applyStoredPosition();
    },
    events:function(){
      var self=this;
      this.toggleButton.addEventListener('click',function(){self.setOpen(true);});
      this.closeButton.addEventListener('click',function(){self.setOpen(false);});
      if(this.resetButton){this.resetButton.addEventListener('click',function(){self.resetPosition();});}
      this.panel.addEventListener('click',function(event){self.onPanelClick(event);});
      this.panel.addEventListener('keydown',function(event){self.onTabKeydown(event);});
      if(this.dragHandle){
        this.dragHandle.addEventListener('pointerdown',function(event){self.startDrag(event);});
        this.dragHandle.addEventListener('pointermove',function(event){self.moveDrag(event);});
        this.dragHandle.addEventListener('pointerup',function(event){self.endDrag(event);});
        this.dragHandle.addEventListener('pointercancel',function(event){self.endDrag(event);});
      }
      document.addEventListener('keydown',function(event){
        if(event.key!=='Escape'){return;}
        if(self.inspectorActive){self.disableInspector();return;}
        if(!self.panel.hidden){self.setOpen(false);}
      });
      window.addEventListener('resize',function(){self.clampCurrentPosition();self.scheduleInspectorLayout();});
      window.addEventListener('scroll',function(){self.scheduleInspectorLayout();},{passive:true,capture:true});
      this.each(this.panel.querySelectorAll('[data-pdt-filter]'),function(input){
        input.addEventListener('input',function(){self.filter(input);});
      });
    },
    onPanelClick:function(event){
      var button=this.closest(event.target,'[data-pdt-tab]');
      if(button){this.activateTab(button,false);}
      var toggle=this.closest(event.target,'[data-pdt-inspector-toggle]');
      if(toggle){event.preventDefault();this.toggleInspector();return;}
      var find=this.closest(event.target,'[data-pdt-inspector-find]');
      if(find){event.preventDefault();this.focusInspector(find.getAttribute('data-pdt-inspector-find'));return;}
      var copy=this.closest(event.target,'[data-pdt-inspector-copy]');
      if(copy){event.preventDefault();this.copyInspectorTag(copy,copy.getAttribute('data-pdt-inspector-copy'));}
    },
    onTabKeydown:function(event){
      var button=this.closest(event.target,'[data-pdt-tab]');
      var keys=['ArrowLeft','ArrowRight','Home','End'];
      if(!button||keys.indexOf(event.key)===-1){return;}
      event.preventDefault();
      var tabs=Array.prototype.slice.call(this.panel.querySelectorAll('[data-pdt-tab]'));
      var index=tabs.indexOf(button);
      if(event.key==='Home'){index=0;}
      else if(event.key==='End'){index=tabs.length-1;}
      else{index=(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;}
      this.activateTab(tabs[index],true);
    },
    setOpen:function(open){
      this.panel.hidden=!open;
      this.toggleButton.hidden=open;
      if(open){
        this.previousOverflow=document.documentElement.style.overflow;
        document.documentElement.style.overflow='hidden';
        this.clampCurrentPosition();
        this.closeButton.focus();
        return;
      }
      document.documentElement.style.overflow=this.previousOverflow;
      this.toggleButton.focus();
    },
    activateTab:function(button,focus){
      if(!button){return;}
      var self=this;
      var id=button.getAttribute('data-pdt-tab');
      this.each(this.panel.querySelectorAll('[data-pdt-tab]'),function(item){
        var active=item===button;
        item.classList.toggle('is-active',active);
        item.setAttribute('aria-selected',active?'true':'false');
        item.tabIndex=active?0:-1;
      });
      this.each(this.panel.querySelectorAll('[data-pdt-panel]'),function(item){
        item.hidden=item.getAttribute('data-pdt-panel')!==id;
      });
      self.store(id);
      if(focus){button.focus();}
    },
    filter:function(input){
      var id=input.getAttribute('data-pdt-filter');
      var list=this.panel.querySelector('[data-pdt-filter-list="'+id+'"]');
      if(!list){return;}
      var query=input.value.toLocaleLowerCase();
      var visible=0;
      this.each(list.querySelectorAll('[data-pdt-filter-item]'),function(item){
        var show=(item.getAttribute('data-pdt-filter-text')||'').indexOf(query)!==-1;
        item.hidden=!show;
        if(show){visible++;}
      });
      var empty=list.querySelector('[data-pdt-filter-empty]');
      if(empty){empty.hidden=visible!==0;}
    },
    restoreState:function(){
      var saved=this.restore()||'overview';
      var button=this.panel.querySelector('[data-pdt-tab="'+saved+'"]')||this.panel.querySelector('[data-pdt-tab]');
      this.activateTab(button,false);
    },
    startDrag:function(event){
      if(!this.canDrag(event)){return;}
      var rect=this.panel.getBoundingClientRect();
      this.drag={pointer:event.pointerId,startX:event.clientX,startY:event.clientY,left:rect.left,top:rect.top};
      this.panel.style.left=rect.left+'px';
      this.panel.style.top=rect.top+'px';
      this.panel.style.right='auto';
      this.panel.style.bottom='auto';
      this.panel.classList.add('is-dragging');
      this.dragHandle.setPointerCapture(event.pointerId);
      event.preventDefault();
    },
    moveDrag:function(event){
      if(!this.drag||this.drag.pointer!==event.pointerId){return;}
      var position=this.clampPosition(this.drag.left+event.clientX-this.drag.startX,this.drag.top+event.clientY-this.drag.startY);
      this.panel.style.left=position.x+'px';
      this.panel.style.top=position.y+'px';
      event.preventDefault();
    },
    endDrag:function(event){
      if(!this.drag||this.drag.pointer!==event.pointerId){return;}
      this.panel.classList.remove('is-dragging');
      if(this.dragHandle.hasPointerCapture&&this.dragHandle.hasPointerCapture(event.pointerId)){this.dragHandle.releasePointerCapture(event.pointerId);}
      this.drag=null;
      this.savePosition();
    },
    canDrag:function(event){
      if(event.button!==undefined&&event.button!==0){return false;}
      if(window.matchMedia&&window.matchMedia('(max-width:760px)').matches){return false;}
      return !this.closest(event.target,'button,a,input,select,textarea,summary,code,[contenteditable="true"]');
    },
    clampPosition:function(x,y){
      var margin=6;
      var width=this.panel.offsetWidth||this.panel.getBoundingClientRect().width;
      var height=this.panel.offsetHeight||this.panel.getBoundingClientRect().height;
      return {
        x:Math.max(margin,Math.min(Number(x)||margin,Math.max(margin,window.innerWidth-width-margin))),
        y:Math.max(margin,Math.min(Number(y)||margin,Math.max(margin,window.innerHeight-height-margin)))
      };
    },
    clampCurrentPosition:function(){
      if(window.matchMedia&&window.matchMedia('(max-width:760px)').matches){return;}
      if(!this.panel.style.left||!this.panel.style.top){return;}
      var position=this.clampPosition(parseFloat(this.panel.style.left),parseFloat(this.panel.style.top));
      this.panel.style.left=position.x+'px';
      this.panel.style.top=position.y+'px';
      this.storePosition(position);
    },
    savePosition:function(){
      if(!this.panel.style.left||!this.panel.style.top){return;}
      this.storePosition({x:parseFloat(this.panel.style.left),y:parseFloat(this.panel.style.top)});
    },
    storePosition:function(position){
      try{window.sessionStorage.setItem(this.positionKey,JSON.stringify(position));}catch(error){}
    },
    applyStoredPosition:function(){
      if(window.matchMedia&&window.matchMedia('(max-width:760px)').matches){return;}
      var position=null;
      try{position=JSON.parse(window.sessionStorage.getItem(this.positionKey)||'null');}catch(error){}
      if(!position||!isFinite(position.x)||!isFinite(position.y)){return;}
      position=this.clampPosition(position.x,position.y);
      this.panel.style.left=position.x+'px';
      this.panel.style.top=position.y+'px';
      this.panel.style.right='auto';
      this.panel.style.bottom='auto';
    },
    resetPosition:function(){
      try{window.sessionStorage.removeItem(this.positionKey);}catch(error){}
      this.panel.style.removeProperty('left');
      this.panel.style.removeProperty('top');
      this.panel.style.removeProperty('right');
      this.panel.style.removeProperty('bottom');
    },
    readInspectorData:function(){
      var node=document.getElementById('pdt-inspector-data');
      if(!node){return [];}
      try{var value=JSON.parse(node.textContent||'[]');return Array.isArray(value)?value:[];}catch(error){return [];}
    },
    toggleInspector:function(){
      if(this.inspectorActive){this.disableInspector();return;}
      this.enableInspector();
    },
    enableInspector:function(){
      if(this.inspectorActive){return;}
      this.inspectorMarkers=this.findInspectorMarkers();
      this.inspectorLayer=document.createElement('div');
      this.inspectorLayer.id='pdi-layer';
      this.inspectorControl=document.createElement('div');
      this.inspectorControl.id='pdi-control';
      this.inspectorControl.innerHTML='<strong>Инспектор страницы</strong><span></span><button type="button" aria-label="Выключить инспектор" title="Выключить">×</button>';
      var self=this;
      var areaCount=Object.keys(this.inspectorMarkers).reduce(function(total,token){return total+self.inspectorMarkers[token].length;},0);
      this.inspectorControl.querySelector('span').textContent=areaCount+' областей';
      this.inspectorControl.querySelector('button').addEventListener('click',function(){self.disableInspector();});
      this.inspectorBoxes={};
      this.each(this.inspectorEntries,function(entry){
        var token=String(entry.token);
        if(!self.inspectorMarkers[token]){return;}
        var type=/^(document|block|request|navigation|module)$/.test(entry.type)?entry.type:'component';
        self.inspectorBoxes[token]=[];
        self.inspectorMarkers[token].forEach(function(pair,pairIndex){
          var box=document.createElement('div');
          box.className='pdi-box pdi-'+type;
          box.setAttribute('data-pdi-token',token);
          box.setAttribute('data-pdi-pair',String(pairIndex));
          var label=document.createElement('button');
          label.type='button';
          label.className='pdi-label';
          label.textContent=self.inspectorTitle(entry)+(pairIndex>0?' · '+(pairIndex+1):'');
          label.addEventListener('click',function(){self.showInspectorRow(token);});
          box.appendChild(label);
          self.inspectorLayer.appendChild(box);
          self.inspectorBoxes[token].push(box);
        });
      });
      document.body.appendChild(this.inspectorLayer);
      document.body.appendChild(this.inspectorControl);
      this.inspectorActive=true;
      this.syncInspectorButtons();
      this.setOpen(false);
      this.scheduleInspectorLayout();
    },
    disableInspector:function(){
      if(this.inspectorLayer&&this.inspectorLayer.parentNode){this.inspectorLayer.parentNode.removeChild(this.inspectorLayer);}
      if(this.inspectorControl&&this.inspectorControl.parentNode){this.inspectorControl.parentNode.removeChild(this.inspectorControl);}
      this.inspectorLayer=null;
      this.inspectorControl=null;
      this.inspectorBoxes={};
      this.inspectorActive=false;
      this.syncInspectorButtons();
    },
	captureInspectorAnchors:function(){
	  var markers=this.findInspectorCommentMarkers();
	  Object.keys(markers).forEach(function(token){
		markers[token].forEach(function(pair,pairIndex){
		  if(!pair.begin||!pair.end||pair.begin.parentNode!==pair.end.parentNode){return;}
		  var anchor=token+'-'+pairIndex;
		  var node=pair.begin.nextSibling;
		  while(node&&node!==pair.end){
			if(node.nodeType===1){
			  var values=(node.getAttribute('data-ave-inspect-anchor')||'').split(/\s+/).filter(Boolean);
			  if(values.indexOf(anchor)===-1){values.push(anchor);node.setAttribute('data-ave-inspect-anchor',values.join(' '));}
			}
			node=node.nextSibling;
		  }
		});
	  });
	},
	findInspectorMarkers:function(){
	  var markers=this.findInspectorCommentMarkers();
	  var anchorGroups={};
	  this.each(document.querySelectorAll('[data-ave-inspect-anchor]'),function(element){
		(element.getAttribute('data-ave-inspect-anchor')||'').split(/\s+/).forEach(function(anchor){
		  if(!/^\d+-\d+$/.test(anchor)){return;}
		  anchorGroups[anchor]=anchorGroups[anchor]||[];
		  anchorGroups[anchor].push(element);
		});
	  });
	  var anchored={};
	  Object.keys(anchorGroups).forEach(function(anchor){
		var token=anchor.split('-')[0];
		anchored[token]=anchored[token]||[];
		anchored[token].push({elements:anchorGroups[anchor]});
	  });
	  Object.keys(anchored).forEach(function(token){if(anchored[token].length){markers[token]=anchored[token];}});
	  return markers;
	},
	findInspectorCommentMarkers:function(){
      var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_COMMENT,null,false);
      var markers={},stacks={};
      while(walker.nextNode()){
        var match=/^ave-inspect:(begin|end):(\d+)$/.exec((walker.currentNode.nodeValue||'').trim());
        if(!match){continue;}
        var token=match[2];
        markers[token]=markers[token]||[];
        stacks[token]=stacks[token]||[];
        if(match[1]==='begin'){stacks[token].push(walker.currentNode);continue;}
        var begin=stacks[token].length?stacks[token].pop():null;
        if(begin){markers[token].push({begin:begin,end:walker.currentNode});}
      }
      Object.keys(markers).forEach(function(token){if(!markers[token].length){delete markers[token];}});
      return markers;
    },
    inspectorPairRect:function(markers){
	  if(markers&&markers.elements){
		var elementRects=[];
		markers.elements.forEach(function(element){
		  if(!element||!element.isConnected){return;}
		  Array.prototype.push.apply(elementRects,Array.prototype.slice.call(element.getClientRects()));
		});
		var visible=elementRects.filter(function(rect){return rect.width>.5&&rect.height>.5;});
		if(!visible.length){return null;}
		var elementLeft=visible[0].left,elementTop=visible[0].top,elementRight=visible[0].right,elementBottom=visible[0].bottom;
		for(var elementIndex=1;elementIndex<visible.length;elementIndex++){
		  elementLeft=Math.min(elementLeft,visible[elementIndex].left);elementTop=Math.min(elementTop,visible[elementIndex].top);
		  elementRight=Math.max(elementRight,visible[elementIndex].right);elementBottom=Math.max(elementBottom,visible[elementIndex].bottom);
		}
		return {left:elementLeft,top:elementTop,right:elementRight,bottom:elementBottom,width:elementRight-elementLeft,height:elementBottom-elementTop};
	  }
      if(!markers||!markers.begin||!markers.end){return null;}
      var range=document.createRange();
      try{range.setStartAfter(markers.begin);range.setEndBefore(markers.end);}catch(error){return null;}
      var rects=Array.prototype.slice.call(range.getClientRects()).filter(function(rect){return rect.width>.5&&rect.height>.5;});
      if(!rects.length){return null;}
      var left=rects[0].left,top=rects[0].top,right=rects[0].right,bottom=rects[0].bottom;
      for(var index=1;index<rects.length;index++){
        left=Math.min(left,rects[index].left);top=Math.min(top,rects[index].top);
        right=Math.max(right,rects[index].right);bottom=Math.max(bottom,rects[index].bottom);
      }
      return {left:left,top:top,right:right,bottom:bottom,width:right-left,height:bottom-top};
    },
    inspectorRect:function(token){
      var pairs=this.inspectorMarkers&&this.inspectorMarkers[String(token)];
      if(!pairs){return null;}
      for(var index=0;index<pairs.length;index++){
        var rect=this.inspectorPairRect(pairs[index]);
        if(rect){return rect;}
      }
      return null;
    },
    scheduleInspectorLayout:function(){
      if(!this.inspectorActive||this.inspectorFrame){return;}
      var self=this;
      this.inspectorFrame=window.requestAnimationFrame(function(){self.inspectorFrame=0;self.layoutInspector();});
    },
    layoutInspector:function(){
      if(!this.inspectorActive){return;}
      var self=this;
      Object.keys(this.inspectorBoxes||{}).forEach(function(token){
        self.inspectorBoxes[token].forEach(function(box,pairIndex){
          var rect=self.inspectorPairRect(self.inspectorMarkers[token][pairIndex]);
          if(!rect){box.hidden=true;return;}
          box.hidden=false;
          box.style.left=Math.max(0,rect.left-2)+'px';
          box.style.top=Math.max(0,rect.top-2)+'px';
          box.style.width=Math.max(4,Math.min(window.innerWidth-Math.max(0,rect.left-2),rect.width+4))+'px';
          box.style.height=Math.max(4,rect.height+4)+'px';
          box.classList.toggle('is-label-inside',rect.top<32);
        });
      });
    },
    focusInspector:function(token){
      if(!this.inspectorActive){this.enableInspector();}
      this.setOpen(false);
      var rect=this.inspectorRect(token);
      if(rect){window.scrollBy({top:rect.top-110,left:0,behavior:'smooth'});}
      var self=this;
      window.setTimeout(function(){
        self.each(document.querySelectorAll('.pdi-box.is-selected'),function(box){box.classList.remove('is-selected');});
        var boxes=self.inspectorBoxes&&self.inspectorBoxes[String(token)];
        if(boxes){self.each(boxes,function(box){box.classList.add('is-selected');});}
        self.scheduleInspectorLayout();
      },250);
    },
    showInspectorRow:function(token){
      this.setOpen(true);
      var tab=this.panel.querySelector('[data-pdt-tab="elements"]');
      this.activateTab(tab,false);
      var row=this.panel.querySelector('[data-pdt-inspector-row="'+String(token)+'"]');
      if(row){row.scrollIntoView({block:'center'});}
    },
    inspectorTitle:function(entry){
      var labels={document:'Документ',block:'Блок',request:'Запрос',navigation:'Навигация',module:'Модуль'};
      var title=labels[entry.type]||'Компонент';
      if(entry.entity_id){title+=' #'+entry.entity_id;}
      if(entry.title){title+=' · '+entry.title;}
      return title;
    },
    syncInspectorButtons:function(){
      var active=this.inspectorActive;
      this.each(this.panel.querySelectorAll('[data-pdt-inspector-toggle]'),function(button){
        button.classList.toggle('is-active',active);
        button.textContent=active?'Скрыть на странице':'Показать на странице';
      });
    },
    copyInspectorTag:function(button,token){
      var entry=this.inspectorByToken[String(token)];
      if(!entry||!entry.tag){return;}
      var original=button.textContent;
      var done=function(){button.textContent='Скопировано';window.setTimeout(function(){button.textContent=original;},1200);};
      if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(entry.tag).then(done).catch(function(){});return;}
      var area=document.createElement('textarea');area.value=entry.tag;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();
      try{document.execCommand('copy');done();}catch(error){}document.body.removeChild(area);
    },
    store:function(value){
      try{window.sessionStorage.setItem(this.storageKey,value);}catch(error){}
    },
    restore:function(){
      try{return window.sessionStorage.getItem(this.storageKey);}catch(error){return null;}
    },
    closest:function(element,selector){
      if(!element){return null;}
      if(element.closest){return element.closest(selector);}
      while(element){
        if(element.matches&&element.matches(selector)){return element;}
        element=element.parentElement;
      }
      return null;
    },
    each:function(items,callback){
      Array.prototype.forEach.call(items,callback);
    }
  };
  window.AvePublicDebug=AvePublicDebug;
	if(document.body){
	  AvePublicDebug.init();
	}else if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',function(){AvePublicDebug.init();});
  }else{
    AvePublicDebug.init();
  }
})(window,document);
</script>
HTML;
		}
	}
