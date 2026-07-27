<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Url;
	use App\Helpers\Debug;
	use App\Helpers\Request;
	use App\Common\Lifecycle;
	use App\Common\AdminLocation;
	use App\Common\Session;
	use App\Common\StoredPhpRuntime;
	use App\Content\ContentTables;
	use App\Frontend\Debug\PublicLayoutInspector;
	use DB;

	/** Executes and renders a configured public document request. */
	class RequestRenderer
	{
		public function render($id, $params = array())
		{
			$documentsTable = ContentTables::table('documents');
			$documentFieldsTable = ContentTables::table('document_fields');
			$rubricFieldsTable = ContentTables::table('rubric_fields');
			$contentPrefix = ContentTables::prefix();
			$currentDocument = \App\Frontend\PublicPageContext::document();

			// Если id пришёл из тега, берём нужную часть массива
			if (is_array($id))
				$id = $id[1];

			$t = [];
			$a = [];
			$v = [];

			// Получаем информацию о запросе
			$requestData = (new RequestRepository())->find($id);
			if (! $requestData)
				return '';
			$rendering = Lifecycle::event('content.query.rendering', 'query', 'rendering', $id, array(
				'request' => $requestData,
				'params' => $params,
				'document' => $currentDocument,
			), null, array(), 'request_renderer');
			if ($rendering->cancelled()) {
				return $this->inspect((string) $rendering->result(), $requestData, $id);
			}

			$requestData = $rendering->value('request', $requestData);
			$params = $rendering->value('params', $params);
			$request = (object) $requestData;

			// Фиксируем время начала генерации запроса
			Debug::startTime('request_' . $id);

			// Массив для полей SELECT
			$request_select = [];
			// Массив для присоединения таблиц JOIN
			$request_join = [];
			// Массив для добавления условий WHERE
			$request_where = [];
			// Массив для сортировки результатов ORDER BY
			$request_order = [];
			// Массив для сортировки результатов ORDER BY
			$request_order_fields = [];

			$request_order_str = '';
			$request_select_str = '';

			// Сортировка по полям из переданных параметров
			$inputSort = Request::request('requestsort_' . $id);
			$requestPage = Request::request('page');
			$articlePage = Request::request('artpage');
			$archivePage = Request::request('apage');
			if (empty($params['SORT']) && is_string($inputSort) && trim($inputSort) !== '')
			{
				$params['SORT'] = RequestSort::fromInput($inputSort);
			}

			// Сортировка по полям
			// Если пришел параметр SORT
			if (! empty($params['SORT']) && is_array($params['SORT']))
			{

				foreach($params['SORT'] as $fid => $sort)
				{
					$inputKey = (string) $fid;
					if (ctype_digit($inputKey))
						$fid = PublicFieldApi::fieldId($request->rubric_id, $inputKey);

					// Если значение больше 0
					if ((int)$fid > 0)
					{
						// Добавляем условие в SQL
						$request_join[$fid] = "<?php echo (! isset(\$t[$fid])) ? \"LEFT JOIN " . $documentFieldsTable . " AS t$fid ON (t$fid.document_id = a.Id AND t$fid.rubric_field_id='$fid')\" : ''?>";

						$asc_desc = RequestSort::direction($sort);

						$request_order['field-'.$fid] = "t$fid.field_value " . $asc_desc;

						$request_order_fields[] = $fid;
					}
					else
						{
							$expression = RequestSort::documentExpression($inputKey);
							if ($expression !== null)
								$request_order['document-' . $inputKey] = $expression . ' ' . RequestSort::direction($sort);
						}
				}
			}

			// Сохранённые уровни сортировки. Для старых строк helper воспроизводит
			// прежнюю последовательность: поле рубрики, системное поле, финальный ID.
			else
				{
					foreach (RequestSort::rulesForRequest($request) as $sortIndex => $sortRule)
					{
						$source = isset($sortRule['source']) ? (string) $sortRule['source'] : '';
						$direction = RequestSort::direction(isset($sortRule['direction']) ? $sortRule['direction'] : 'ASC');
						if ($source === 'field')
						{
							$fid = (int) $sortRule['key'];
							if ($fid <= 0) { continue; }
							$request_join[$fid] = "<?php echo (! isset(\$t[$fid])) ? \"LEFT JOIN " . $documentFieldsTable . " AS t$fid ON (t$fid.document_id = a.Id AND t$fid.rubric_field_id='$fid')\" : ''?>";
							$request_order['field-' . $fid] = "t$fid.field_value " . $direction;
							$request_order_fields[] = $fid;
						}
						elseif (empty($params['RANDOM']) && $source === 'document')
						{
							$expression = RequestSort::documentExpression(isset($sortRule['key']) ? $sortRule['key'] : '');
							if ($expression !== null) { $request_order['rule-' . $sortIndex] = $expression . ' ' . $direction; }
						}
						elseif (empty($params['RANDOM']) && $source === 'random')
						{
							$request_order['rule-' . $sortIndex] = RequestSort::randomExpression($sortRule) . ' ASC';
							if (RequestSort::randomSeed(isset($sortRule['seed']) ? $sortRule['seed'] : null) !== null)
								$request_order['rule-' . $sortIndex . '-id'] = 'a.Id ASC';
						}
					}
				}

			// Вторичная сортировка по параметру документа - добавляем в конец сортировок
			if (! empty($params['RANDOM']))
			{
				$request_order['sort'] = ($params['RANDOM'] == 1)
					? 'RAND()'
					: '';
			}
			elseif (! empty($params['SORT']) && is_array($params['SORT']))
			{
				$storedRules = isset($request->request_sort_rules) ? trim((string) $request->request_sort_rules) : '';
				if ($storedRules === '')
				{
					$storedOrder = RequestSort::documentExpression($request->request_order_by);
					if (strtoupper((string) $request->request_order_by) === 'RAND()')
						$request_order['sort'] = 'RAND()';
					elseif ($storedOrder !== null)
						$request_order['sort'] = $storedOrder . ' ' . RequestSort::direction($request->request_asc_desc);

					$tieBreaker = RequestSort::tieBreaker(isset($request->request_order_tiebreaker) ? $request->request_order_tiebreaker : '');
					if ($tieBreaker !== '' && RequestSort::documentColumn($request->request_order_by) !== 'Id')
						$request_order['tiebreaker'] = 'a.Id ' . $tieBreaker;
				}
				else
				{
					foreach (RequestSort::rulesForRequest($request) as $sortRule)
					{
						if ($sortRule['source'] === 'document' && (string) $sortRule['key'] === 'Id')
						{
							$request_order['tiebreaker'] = 'a.Id ' . RequestSort::direction($sortRule['direction']);
							break;
						}
					}
				}
			}

			// Заменяем field_value на field_number_value во всех полях для сортировки, если поле числовое
			if (! empty($request_order_fields))
			{
				$sql_numeric = DB::query("
				SELECT
					Id
				FROM
					" . $rubricFieldsTable . "
				WHERE
					Id IN (" . implode(',', $request_order_fields) . ")
				AND
					rubric_field_numeric = '1'
			");

				if ($sql_numeric->numRows() > 0)
				{
					while ($numericRow = $sql_numeric->getObject())
					{
						$fid = (int) $numericRow->Id;
						$request_order['field-' . $fid] = str_replace('field_value','field_number_value', $request_order['field-' . $fid]);
					}
				}
			}

			$executorMode = isset($params['EXECUTOR'])
				? strtolower((string) $params['EXECUTOR'])
				: (isset($request->request_executor_mode) ? strtolower((string) $request->request_executor_mode) : 'legacy');
			$nativePlan = null;
			$nativeCanRun = false;
			$nativeFallbackReason = '';
			if ($executorMode === 'native') {
				$nativePlan = isset($request->request_native_plan)
					? \App\Content\Requests\NativeRequestPlanCompiler::decode((string) $request->request_native_plan)
					: null;
				if (!$nativePlan) {
					$nativePlan = \App\Content\Requests\NativeRequestPlanCompiler::persist((int) $id);
				}

				$verifiedHash = isset($request->request_native_verified_hash) ? (string) $request->request_native_verified_hash : '';
				$unsupported = \App\Content\Requests\NativeRequestExecutor::unsupportedParameter($params);
				if (empty($nativePlan['eligible'])) {
					$nativeFallbackReason = implode(' ', isset($nativePlan['reasons']) ? $nativePlan['reasons'] : array());
				} elseif ($verifiedHash === '' || !hash_equals($verifiedHash, (string) $nativePlan['source_hash'])) {
					$nativeFallbackReason = 'Текущая версия плана не подтверждена теневым сравнением.';
				} elseif ($unsupported !== null) {
					$nativeFallbackReason = 'Параметр ' . $unsupported . ' требует Legacy executor.';
				} elseif (\App\Content\Requests\NativeRequestExecutor::hasRuntimeSelections((int) $id)) {
					$nativeFallbackReason = 'Динамический выбор посетителя требует Legacy executor.';
				} else {
					$nativeCanRun = true;
				}
			}

			// Статус: если в параметрах, то его ставим. Иначе выводим только активные доки
			$request_where[] = "a.document_status = '" . ((isset($params['STATUS']))
				? (int)$params['STATUS']
				: '1') . "'";

			// Не выводить текущий документ
			if ($request->request_hide_current)
				$request_where[] = "a.Id != '" . PublicPageContext::documentId() . "'";

			// Дата публикации документов
			if (PublicSettings::get('use_doctime'))
				$request_where[] = "a.document_published <= UNIX_TIMESTAMP() AND (a.document_expire = 0 OR a.document_expire >= UNIX_TIMESTAMP())";

			// Условия запроса
			// если условия пустые, получаем строку с сохранением её в бд
			$where_cond = array('from' => '', 'where' => '');
			if (!$nativeCanRun) {
				$where_cond = \App\Content\Requests\RequestConditionCompiler::decode((string) $request->request_where_cond);
				if ($where_cond === null)
					$where_cond = \App\Content\Requests\RequestConditionCompiler::compile((int) $request->Id, true);
				$where_cond = \App\Content\Requests\RequestConditionCompiler::applyRuntimeSelections((int) $request->Id, $where_cond);
			}

			$where_cond['from'] = isset($where_cond['from'])
				? str_replace(array('%%CONTENT_PREFIX%%', '%%PREFIX%%'), array($contentPrefix, $contentPrefix), $where_cond['from'])
				: '';
			$where_cond['where'] = isset($where_cond['where'])
				? str_replace(array('%%CONTENT_PREFIX%%', '%%PREFIX%%'), array($contentPrefix, $contentPrefix), $where_cond['where'])
				: '';

			if (! empty($where_cond['where']))
				$request_where[] = $where_cond['where'];

			// Родительский документ
			if (isset($params['PARENT']) && (int)$params['PARENT'] > 0)
				$request_where[] = "a.document_parent = '" . (int)$params['PARENT'] . "'";

			// Автор
			// Если задано в параметрах
			if (isset($params['USER_ID']))
				$user_id = (int)$params['USER_ID'];
			// Если стоит галка, показывать только СВОИ документы в настройках
			// Аноним не увидит ничего, так как 0 юзера нет
			elseif ($request->request_only_owner == '1')
				$user_id = (int) Session::get('user_id');

			// Если что-то добавили, пишем
			if (isset($user_id))
				$request_where[] = "a.document_author_id = '" . $user_id . "'";

			// Произвольные условия WHERE
			if (isset($params['USER_WHERE']) && $params['USER_WHERE'] > '')
			{
				if (is_array($params['USER_WHERE']))
					$request_where = array_merge($request_where,$params['USER_WHERE']);
				else
					$request_where[] = $params['USER_WHERE'];
			}

			// Готовим строку с условиями
			$notFoundId = defined('PAGE_NOT_FOUND_ID') ? (int) PAGE_NOT_FOUND_ID : 2;
			array_unshift($request_where,"
			a.Id != '1' AND a.Id != '" . $notFoundId . "' AND
			a.rubric_id = '" . $request->rubric_id . "' AND
			a.document_deleted = '0'");

			$request_where_str = '(' . implode(') AND (',$request_where) . ')';

			// Количество выводимых доков
			$requestLimiter = Request::request('requestlimiter_' . $id);
			$params['LIMIT'] = (! empty($params['LIMIT'])
				? $params['LIMIT']
				: (! empty($requestLimiter)
					? $requestLimiter
					: (int)$request->request_items_per_page));

			$limit = (isset($params['LIMIT']) && is_numeric($params['LIMIT']) && $params['LIMIT'] > '')
				? (int)$params['LIMIT']
				: (int)$request->request_items_per_page;

			$start = (isset($params['START']))
				? (int)$params['START']
				: (($request->request_show_pagination == 1)
					? PaginationRenderer::currentPage('page') * $limit - $limit
					: 0);

			$limit_str = ($limit > 0)
				? "LIMIT " . $start . "," . $limit
				: '';

			// Готовим строку с сортировкой
			if ($request_order)
				$request_order_str = "ORDER BY " . implode(', ',$request_order);

			if (! empty($params['ORDER']) && is_array($params['ORDER'])) {

				$parts = [];
				foreach ($params['ORDER'] as $expr => $dir) {
					$dir = strtoupper(trim($dir));

					if (!in_array($dir, ['ASC', 'DESC'], true)) {
						$dir = 'ASC';
					}

					$parts[] = $expr . ' ' . $dir;
				}

				$request_order_str = ' ORDER BY ' . implode(', ', $parts);
			}

			// Готовим строку с полями
			if ($request_select)
				$request_select_str = ',' . implode(",\r\n",$request_select);

			unset ($a, $t, $v);

			$needsFoundRows = $request->request_show_pagination == 1
				|| (isset($params['SHOW']) && $params['SHOW'] == 1)
				|| (isset($params['RETURN_SQL']) && $params['RETURN_SQL'] == 1)
				|| (isset($params['RETURN_COUNT']) && $params['RETURN_COUNT'] == 1)
				|| (isset($params['RETURN_DATA']) && $params['RETURN_DATA'] == 1)
				|| ((!isset($params['NO_FOUND_ROWS']) || $params['NO_FOUND_ROWS'] != 1) && $request->request_count_items);
			$foundRowsSelect = $needsFoundRows ? ' SQL_CALC_FOUND_ROWS' : '';

			if ($nativeCanRun)
			{
				$sql_request = '-- Native executor plan #' . $id;
			}
			elseif (! isset($params['SQL_QUERY']))
			{
				// Составляем запрос к БД
				$sql = " ?>
				SELECT STRAIGHT_JOIN" . $foundRowsSelect . "
				#REQUEST = $request->Id
					a.*
					" . $request_select_str . "
				FROM
					" . $where_cond['from'] . "
					" . (isset($params['USER_FROM']) ? $params['USER_FROM'] : '') . "
					" . $documentsTable . " AS a
					" . implode(' ', $request_join) . "
					" . (isset($params['USER_JOIN']) ? $params['USER_JOIN'] : '') . "
				WHERE
					" . $request_where_str . "
				GROUP BY a.Id
				" . $request_order_str . "
				" . $limit_str . "
			<?"."php ";

				$sql_request = \App\Common\StoredPhpRuntime::evaluate($sql, get_defined_vars());

				unset ($sql);

				// Убираем дубли в выборе полей
				foreach (array_keys($request_join) AS $key)
				{
					$search = $documentFieldsTable . ' AS t' . $key . ',';

					if (preg_match('/' . $search . '/', $sql_request) > 0)
					{
						$sql_request = str_replace($search, '', $sql_request);
					}
				}
			}
			else
				{
					$sql_request = $params['SQL_QUERY'];
				}

			Debug::startTime('SQL');

			// Native включается только явно и всегда откатывается на Legacy для
			// неподдерживаемых runtime-параметров или несовместимого плана.
			$usedNative = false;
			if ($nativeCanRun) {
				try {
					$nativeParams = $params;
					$nativeParams['LIMIT'] = $limit;
					$nativeParams['START'] = $start;
					$nativeParams['REQUIRE_VERIFIED'] = 1;
					$native = (new \App\Content\Requests\NativeRequestExecutor())->execute((int) $id, $nativeParams, $nativePlan);
					$sql = new \DB_Result($native['rows']);
					$foundRows = (int) $native['total'];
					$sql_request = (string) $native['sql'];
					$sqlTime = (float) $native['query_time'];
					$usedNative = true;
				}
				catch (\Throwable $e)
				{
					Debug::panel(array(
						'executor' => 'Legacy fallback',
						'reason' => $e->getMessage(),
					), 'Запрос #' . $id . ': Native недоступен');
					$fallbackParams = $params;
					$fallbackParams['EXECUTOR'] = 'legacy';
					return $this->render($id, $fallbackParams);
				}
			}
			elseif ($executorMode === 'native' && $nativeFallbackReason !== '') {
				Debug::panel(array(
					'executor' => 'Legacy fallback',
					'reason' => $nativeFallbackReason,
				), 'Запрос #' . $id . ': Native недоступен');
			}

			if (!$usedNative) {
				$sql = DB::query($sql_request);
				$foundRows = $needsFoundRows ? (int) DB::query('SELECT FOUND_ROWS()')->getValue() : 0;
				$sqlTime = Debug::endTime('SQL');
			} else {
				Debug::endTime('SQL');
			}

			// Диагностический SQL доступен только в защищённой публичной панели.
			if ((isset($params['DEBUG']) && $params['DEBUG'] == 1) || $request->request_show_sql == 1)
			{
				Debug::panel($sql_request, 'SQL запроса #' . $id . ($usedNative ? ' · Native' : ' · Legacy'));
			}

			PublicProfiler::record(array('REQUESTS', $id, 'SQL'), $sqlTime);

			// Если просили просто вернуть резльтат запроса, возвращаем результат
			if (isset($params['RETURN_SQL']) && $params['RETURN_SQL'] == 1)
				return $foundRows;

			// Если есть вывод пагинации, то выполняем запрос на получение кол-ва элементов
			if ($request->request_show_pagination == 1 || (isset($params['SHOW']) && $params['SHOW'] == 1))
				$num_items = $foundRows;
			else
				$num_items = ((isset($params['NO_FOUND_ROWS']) && $params['NO_FOUND_ROWS'] == 1) || ! $request->request_count_items
					? 0
					: $foundRows);

			// Если просили просто вернуть кол-во, возвращаем результат
			if (isset($params['RETURN_COUNT']) && $params['RETURN_COUNT'] == 1)
				return $num_items;

			// Структурированный результат для доверенных внутренних потребителей.
			// Публичный renderer не использует этот режим и продолжает собирать HTML ниже.
			if (isset($params['RETURN_DATA']) && $params['RETURN_DATA'] == 1)
			{
				$rows = array();
				while ($row = $sql->getObject()) {
					$rows[] = $row;
				}

				return array(
					'rows' => $rows,
					'total' => $foundRows,
					'query_time' => $sqlTime,
					'sql' => $sql_request,
					'executor' => $usedNative ? 'native' : 'legacy',
				);
			}

			unset ($sql_request);

			// Приступаем к обработке шаблона
			$main_template = $request->request_template_main;

			//-- Если кол-во элементов больше 0, удалаяем лишнее
			if ($num_items > 0)
			{
				$main_template = preg_replace('/\[tag:if_empty](.*?)\[\/tag:if_empty]/si', '', $main_template);
				$main_template = str_replace (array('[tag:if_notempty]','[/tag:if_notempty]'), '', $main_template);
			}
			else
				{
					$main_template = preg_replace('/\[tag:if_notempty](.*?)\[\/tag:if_notempty]/si', '', $main_template);
					$main_template = str_replace (array('[tag:if_empty]','[/tag:if_empty]'), '', $main_template);
				}

			$pagination = '';

			// Кол-во страниц
			$num_pages = ($limit > 0)
				? ceil($num_items / $limit)
				: 0;

			// Собираем пагинацию, еслиесть указание ее выводить
			if ($request->request_show_pagination == 1 || (isset($params['SHOW']) && $params['SHOW'] == 1))
			{
				// Если в запросе пришел номер страницы и он больше, чем кол-во страниц
				// Делаем перенаправление
				if (is_numeric($requestPage) && $requestPage > $num_pages)
				{
					$redirect_link = Url::rewrite('index.php?id=' . $currentDocument->Id
						. '&amp;doc=' . (empty($currentDocument->document_alias)
							? Url::prepare($currentDocument->document_title)
							: $currentDocument->document_alias)
						. (is_numeric($articlePage)
							? '&amp;artpage=' . $articlePage
							: '')
						. (is_numeric($archivePage)
							? '&amp;apage=' . $archivePage
							: ''));

					header('Location:' . $redirect_link);
					exit;
				}

				// Запоминаем глобально
				\App\Frontend\PublicPageContext::recordPagination((int) $currentDocument->Id, 'page', $num_pages);

				$pagination = '';

				// Если кол-во страниц больше 1й
				if ($num_pages > 1)
				{
					$queries = '';

					// Добавляем GET-запрос в пагинацию если пришло ADD_GET
					// или указанов настройках запроса
					if ($request->request_use_query == 1 || (isset($params['ADD_GET']) && $params['ADD_GET'] == 1))
					{
						$queryParams = Request::getAll();
						foreach (array('id', 'doc', 'page', 'artpage', 'apage') as $reservedQueryKey) {
							unset($queryParams[$reservedQueryKey]);
						}

						$queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
						$queries = $queryString !== ''
							? '&amp;' . str_replace('&', '&amp;', $queryString)
							: '';
					}

					$pagination = 'index.php?id='
						. $currentDocument->Id

						. '&amp;doc=' . (empty($currentDocument->document_alias)
							? Url::prepare($currentDocument->document_title)
							: $currentDocument->document_alias)

						. '&amp;page={s}'

						. (is_numeric($articlePage)
							? '&amp;artpage=' . $articlePage
							: '')

						. (is_numeric($archivePage)
							? '&amp;apage=' . $archivePage
							: '')

						// Добавляем GET-запрос в пагинацию
						. \App\Common\StoredPhpRuntime::removeExecutableMarkers($queries)
					;

					// ID пагинации
					$pagination_id = (isset($params['PAGINATION']) && $params['PAGINATION'] > 0)
						? $params['PAGINATION']
						: $request->request_pagination;

					// Собираем пагинацию
					$pagination = ConfiguredPaginationRenderer::render($num_pages, 'page', $pagination, $pagination_id);

					// Костыли для Главной страницы
					$pagination = str_ireplace('"//"', '"/"', str_ireplace('///', '/', Url::rewrite($pagination)));
					$pagination = str_ireplace('"//' . URL_SUFF . '"', '"/"', $pagination);
				}
			}

			// Элементы запроса
			$rows = [];

			while ($row = $sql->getObject())
			{
				// Собираем оставшуюся информацию
				array_push($rows, $row);
			}

			//-- Обрабатываем шаблоны элементов
			$items = '';
			//-- Счетчик
			$x = 0;
			//-- Общее число элементов
			$items_count = count($rows);

			$itemContext = new RequestItemContext(
				$request->Id,
				0,
				$request->request_cache_elements == 1,
				$request->request_changed_elements
			);

			Debug::startTime('ELEMENTS_ALL');
			$itemRenderer = new RequestItemRenderer();

			if (isset($params['ROWS']) && count($params['ROWS']) > 0)
			{
				$rows = $params['ROWS'];
				$items_count = count($rows);
			}

			$rows = (new RequestListReadModel())->prepare(
				$rows,
				(string) $request->request_template_item,
				$itemContext
			);

		//if (UID == 1) Debug::_echo($rows, true);
			foreach ($rows AS $row)
			{
				$x++;
				$last_item = ($x == $items_count ? true : false);
				$item_num = $x;
				Debug::startTime('ELEMENT_' . $item_num);

				$rowId = is_object($row)
					? (isset($row->Id) ? (int) $row->Id : 0)
					: (isset($row['Id']) ? (int) $row['Id'] : 0);
				$renderSource = is_object($row) && isset($row->rubric_id, $row->document_title)
					? $row
					: $rowId;
				$item = $itemRenderer->render($renderSource, $request->request_template_item, '', $itemContext->forItem($item_num));

				PublicProfiler::record(array('REQUESTS', $id, 'ELEMENTS', $item_num), Debug::endTime('ELEMENT_' . $item_num));

				$item = RequestItemRenderer::decorateSequence($item, $rowId, $item_num, $last_item);
				$items .= $item;
			}

			PublicProfiler::record(array('REQUESTS', $id, 'ELEMENTS', 'ALL'), Debug::endTime('ELEMENTS_ALL'));

			// ============ Обрабатываем теги запроса ============ //

			//-- Совместимый тег block использует единый реестр блоков.
			$blockRenderer = new BlockRenderer();
			$main_template = preg_replace_callback('/\[tag:block:([A-Za-z0-9-_]{1,20}+)\]/', function ($match) use ($blockRenderer) {
				return $blockRenderer->visual($match[1]);
			}, $main_template);

			//-- Парсим теги системных блоков
			$main_template = preg_replace_callback('/\[tag:sysblock:([A-Za-z0-9-_]{1,20}+)(|:\{(.*?)\})\]/', function ($match) use ($blockRenderer) {
				return $blockRenderer->system($match[1], isset($match[2]) ? $match[2] : null);
			}, $main_template);

			//-- Дата
			$main_template = preg_replace_callback('/\[tag:date:([a-zA-Z0-9-. \/]+)\]/',
				function ($match) use ($currentDocument)
				{
					return \App\Helpers\Locales::translateDate(date($match[1], $currentDocument->document_published));
				},
				$main_template
			);

			$str_replace = [
				//-- ID Документа
				'[tag:docid]' => $currentDocument->Id,
				//-- ID Автора
				'[tag:docauthorid]' => $currentDocument->document_author_id,
				//-- Имя автора
				'[tag:docauthor]' => \App\Common\Auth\PublicUserNames::byId($currentDocument->document_author_id),
				//-- Время - 1 день назад
				'[tag:humandate]' => \App\Helpers\Locales::humanDate($currentDocument->document_published),
				//-- Дата создания
				'[tag:docdate]' => \App\Helpers\Locales::prettyDate(strftime(DATE_FORMAT, $currentDocument->document_published)),
				//-- Время создания
				'[tag:doctime]' => \App\Helpers\Locales::prettyDate(strftime(TIME_FORMAT, $currentDocument->document_published)),
				//-- Домен
				'[tag:domain]' => Url::site(),
				//-- Заменяем тег пагинации на пагинацию
				'[tag:pages]' => $pagination,
				//-- Общее число элементов запроса
				'[tag:doctotal]' => $num_items,
				//-- Показано элементов запроса на странице
				'[tag:doconpage]' => $x,
				//-- Номер страницы пагинации
				'[tag:pages:curent]' => PaginationRenderer::currentPage('page'),
				//-- Общее кол-во страниц пагинации
				'[tag:pages:total]' => $num_pages,
				//-- Title
				'[tag:pagetitle]' => stripslashes(htmlspecialchars_decode($currentDocument->document_title)),
				//-- Alias
				'[tag:alias]' => (isset($currentDocument->document_alias) ? $currentDocument->document_alias : '')
			];

			$main_template = str_replace(array_keys($str_replace), array_values($str_replace), $main_template);

			//-- Возвращаем параметр документа из БД
			$main_template = preg_replace_callback('/\[tag:doc:([a-zA-Z0-9-_]+)\]/u',
				function ($match) use ($currentDocument)
				{
					return is_object($currentDocument) && isset($currentDocument->{$match[1]})
						? $currentDocument->{$match[1]}
						: null;
				},
				$main_template
			);

			$main_template = preg_replace('/\[tag:langfile:[a-zA-Z0-9-_]+\]/u', '', $main_template);

			//-- Вставляем элементы запроса
			$return = str_replace('[tag:content]', $items, $main_template);

			unset ($items, $main_template, $str_replace, $pagination);

			//-- Парсим тег [hide]
			$return = (new HiddenContentRenderer())->render($return);

			//-- Абсолютный путь
			$return = str_replace('[tag:path]', ABS_PATH, $return);

			//-- Путь до папки шаблона
			$return = str_replace('[tag:mediapath]', ABS_PATH . 'templates/' . ((defined('THEME_FOLDER') === false) ? DEFAULT_THEME_FOLDER : THEME_FOLDER) . '/', $return);

			//-- Парсим модули
			$moduleTagRenderer = new \App\Frontend\ModuleTagRenderer();
			$return = $moduleTagRenderer->render($return, null, $currentDocument);

			//-- Фиксируем время генерации запроса
			PublicProfiler::record(array('REQUESTS', $id, 'TIME'), Debug::endTime('request_' . $id));

			//	Статистика
			if ($request->request_show_statistic)
				$return .= "<div class=\"request_statistic\"><br>Найдено: $num_items<br>Показано: $items_count<br>Время генерации: " . Debug::endTime('request_' . $id) . " сек<br>Пиковое значение: ".number_format(memory_get_peak_usage()/1024, 0, ',', ' ') . ' Kb</div>';

			$return = StoredPhpRuntime::normalizeDataPrefixes($return);
			$rendered = Lifecycle::event('content.query.rendered', 'query', 'rendered', $id, array(
				'request' => $requestData,
				'document_id' => $currentDocument && isset($currentDocument->Id) ? (int) $currentDocument->Id : 0,
			), $return, array(), 'request_renderer');
			return $this->inspect((string) $rendered->result(), $requestData, $id);
		}

		protected function inspect($output, $request, $identifier)
		{
			$request = is_array($request) ? $request : (array) $request;
			$id = isset($request['Id']) ? (int) $request['Id'] : 0;
			$alias = isset($request['request_alias']) ? (string) $request['request_alias'] : (string) $identifier;
			return PublicLayoutInspector::annotate($output, 'request', array(
				'id' => $id > 0 ? $id : $identifier,
				'title' => isset($request['request_title']) ? (string) $request['request_title'] : 'Запрос ' . $alias,
				'alias' => $alias,
				'tag' => '[tag:request:' . $identifier . ']',
				'edit_url' => $id > 0 ? AdminLocation::url('requests/' . $id) : AdminLocation::url('requests'),
			));
		}
	}
