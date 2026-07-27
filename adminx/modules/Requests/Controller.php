<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Common\ErrorReport;
	use App\Adminx\Support\CodeEditor;
	use App\Content\Requests\RequestViewPreview;
	use App\Content\Requests\RequestMatchExplainer;
	use App\Content\Requests\RequestRendererRegistry;
	use App\Content\Requests\RequestExecutorComparator;
	use App\Helpers\Request;

	/**
	 * Раздел «Запросы»: список + редактор запроса (поля, шаблоны item/main в
	 * CodeMirror, условия). Управление данными; теги/PHP не исполняются.
	 */
	class Controller extends BaseController
	{
		/** GET /requests — список. */
		public function index(array $params = [])
		{
			AdminAssets::addStyle($this->base() . '/modules/Requests/assets/requests.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Requests/assets/requests.js', 50);

			return $this->render('@requests/index.twig', [
				'requests'   => Model::all(),
				'stats'      => Model::stats(),
				'can_manage' => Permission::check('manage_requests'),
			]);
		}

		/** GET /requests/native-audit — массовая безопасная сверка executors. */
		public function nativeAudit(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Requests/assets/requests.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Requests/assets/requests.js', 50);
			$rows = Model::nativeAuditRows();

			return $this->render('@requests/native-audit.twig', array(
				'rows' => $rows,
				'stats' => Model::nativeAuditStats($rows),
				'can_manage' => Permission::check('manage_requests'),
			));
		}

		/** POST /requests/native-audit/prepare — Shadow не меняет публичный результат. */
		public function nativeAuditPrepare(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$staged = Model::stageLegacyConditionValues();
			Model::setAllExecutorMode('shadow');
			$ids = array();
			foreach (Model::nativeAuditRows() as $row) { $ids[] = (int) $row['id']; }
			$message = 'Все запросы переведены в безопасный режим Shadow';
			if (!empty($staged['conditions'])) {
				$message .= '. Подготовлено декларативных условий: ' . (int) $staged['conditions'];
			}

			return $this->success($message, array('data' => array('ids' => $ids, 'staged' => $staged)));
		}

		/** POST /requests/{id}/native-audit — сверка и фиксация хеша плана. */
		public function nativeAuditOne(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::find($id)) { return $this->error('Запрос не найден', array(), 404); }

			try {
				$comparison = (new RequestExecutorComparator())->compare($id, 20);
				if (Model::conditionValuesEquivalent($comparison)) {
					$finalized = Model::finalizeStagedConditionValues(
						$id,
						isset($comparison['runtime_verification']) ? $comparison['runtime_verification'] : array()
					);
					if (is_array($finalized)) {
						$comparison = $finalized;
						$comparison['condition_tags_finalized'] = true;
					}
				}

				Model::recordNativeVerification($id, $comparison);
			} catch (\Throwable $e) {
				return $this->error(ErrorReport::publicMessage(
					'Теневое сравнение не выполнено',
					$e,
					'REQUEST_NATIVE_AUDIT',
					array('request_id' => $id)
				), array(), 422);
			}

			$message = !empty($comparison['condition_tags_finalized'])
				? 'Запрос проверен, PHP-условия заменены декларативными тегами'
				: 'Запрос проверен';
			return $this->success($message, array('data' => array('comparison' => $comparison)));
		}

		/** POST /requests/native-audit/activate — только подтверждённые планы. */
		public function nativeAuditActivate(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$count = Model::activateVerifiedNative();
			return $this->success('Native включён только для подтверждённых запросов', array('data' => array('count' => $count)));
		}

		/** POST /requests/native-audit/rollback — мгновенный общий откат. */
		public function nativeAuditRollback(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$count = Model::setAllExecutorMode('legacy');
			return $this->success('Все запросы возвращены в Legacy', array('data' => array('count' => $count)));
		}

		/** POST /requests/{id}/native-stabilize — safe ID tiebreaker with rollback. */
		public function nativeAuditStabilize(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::find($id)) { return $this->error('Запрос не найден', array(), 404); }

			try {
				$result = Model::stabilizeNativeOrder($id);
			} catch (\Throwable $e) {
				return $this->error(ErrorReport::publicMessage(
					'Не удалось безопасно закрепить порядок',
					$e,
					'REQUEST_NATIVE_STABILIZE',
					array('request_id' => $id)
				), array(), 422);
			}

			return $this->success((string) $result['message'], array('data' => $result));
		}

		/** GET /requests/new — форма создания. */
		public function createForm(array $params = [])
		{
			return $this->editView(null);
		}

		/** GET /requests/{id} — форма редактирования. */
		public function edit(array $params = [])
		{
			$request = Model::find($params['id'] ?? 0);
			if (!$request) {
				return $this->editNotFound();
			}

			return $this->editView($request);
		}

		protected function editView($request)
		{
			CodeEditor::useCodeMirror('application/x-httpd-php');
			AdminAssets::addStyle($this->base() . '/modules/Requests/assets/requests.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Requests/assets/requests.js', 50);

			$id = $request ? (int) $request->Id : 0;
			$rubricFields = $id ? Model::rubricFields((int) $request->rubric_id) : array();

			//-- Палитра тегов из общего реестра модуля Templates (если он есть).
			$tagGroups = array();
			if (class_exists('App\\Adminx\\Templates\\TagRegistry')) {
				$tagGroups = \App\Adminx\Templates\TagRegistry::groups();
			}

			if ($rubricFields) {
				array_unshift($tagGroups, $this->rubricFieldTagGroup($rubricFields));
			}

			$nativePlan = $id ? Model::nativePlan($id) : array(
				'eligible' => false,
				'reasons' => array('Сначала сохраните запрос.'),
			);

			$paginationOptions = Model::paginationOptions();
			return $this->render('@requests/edit.twig', [
				'request'    => $request,
				'condition_tree' => $id ? Model::conditionTree($id) : [],
				'compares'   => Model::compareOptions(),
				'condition_value_modes' => Model::conditionValueModes(),
				'rubrics'    => Model::rubricOptions(),
				'rubric_fields' => $rubricFields,
				'order_options' => \App\Frontend\RequestSort::storedOptions(),
				'sort_rules' => Model::sortRules($request),
				'pagination_options' => $paginationOptions,
				'pagination_ids' => array_map(function ($item) { return (int) $item['id']; }, $paginationOptions),
				'result_contract' => Model::resultContract($request),
				'result_system_options' => \App\Content\Requests\RequestResultContract::systemOptions(),
				'preview_renderer' => Model::previewRenderer($request),
				'preview_renderers' => RequestRendererRegistry::previewOptions(),
				'executor_mode' => Model::executorMode($request),
				'native_plan' => $nativePlan,
				'native_verified' => $request ? Model::nativeVerified($request, $nativePlan) : false,
				'flags'      => Model::$flags,
				'tag_groups' => $tagGroups,
				'saved'      => Request::getInt('saved') === 1,
				'can_manage' => Permission::check('manage_requests'),
			]);
		}

		/** Tags available only while editing one request result item. */
		protected function rubricFieldTagGroup(array $fields)
		{
			$items = array();
			foreach ($fields as $field) {
				$id = isset($field->Id) ? (int) $field->Id : 0;
				$alias = isset($field->rubric_field_alias) ? trim((string) $field->rubric_field_alias) : '';
				$key = $alias !== '' ? $alias : (string) $id;
				if ($key === '' || $key === '0') { continue; }

				$title = isset($field->rubric_field_title) ? trim((string) $field->rubric_field_title) : '';
				$type = isset($field->condition_type_label)
					? trim((string) $field->condition_type_label)
					: trim(isset($field->rubric_field_type) ? (string) $field->rubric_field_type : '');
				$tag = '[tag:rfld:' . $key . '][0]';
				$description = $title !== '' ? $title : ('Поле #' . $id);
				if ($type !== '') { $description .= ' · ' . $type; }

				$items[] = array(
					'value' => $tag,
					'label' => $tag,
					'description' => $description,
					'select' => '',
				);
			}

			return array(
				'title' => 'Поля рубрики',
				'scope' => 'item',
				'items' => $items,
			);
		}

		protected function editNotFound()
		{
			\App\Helpers\Response::setStatus(404);
			return $this->render('@adminx/404.twig', []);
		}

		/** POST /requests — создать. */
		public function store(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$data = $this->input();
			if ($errors = $this->validate($data, 0)) {
				return $this->error('Проверьте поля', $errors);
			}

			$id = Model::create($data);
			return $this->success('Запрос создан', ['redirect' => $this->base() . '/requests/' . $id . '?saved=1']);
		}

		/** POST /requests/{id} — сохранить. */
		public function update(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			$request = Model::find($id);
			if (!$request) {
				return $this->error('Запрос не найден', [], 404);
			}

			$data = $this->input();
			if ($errors = $this->validate($data, $id)) {
				return $this->error('Проверьте поля', $errors);
			}

			Model::update($id, $data);
			if ((int) $request->rubric_id !== (int) $data['rubric_id']) {
				return $this->success('Сохранено', array(
					'redirect' => $this->base() . '/requests/' . $id . '?saved=1',
				));
			}

			return $this->success('Сохранено');
		}

		/** POST /requests/{id}/copy — копия. */
		public function copy(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Запрос не найден', [], 404);
			}

			$newId = Model::copy($id);
			return $this->success('Создана копия', ['redirect' => $this->base() . '/requests/' . $newId]);
		}

		/** POST /requests/{id}/delete — удалить. */
		public function destroy(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Запрос не найден', [], 404);
			}

			Model::delete($id);
			return $this->success('Запрос удалён');
		}

		/** POST /requests/lint — проверка PHP-синтаксиса кода шаблона (ajax). */
		public function lint(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$code = (string) Request::post('code', '');
			if (!class_exists('App\\Adminx\\Templates\\Syntax')) {
				return $this->error('Проверка недоступна', [], 501);
			}

			$r = \App\Adminx\Templates\Syntax::check($code);
			if (!empty($r['ok'])) {
				return $this->success($r['message'], ['data' => ['checked' => !empty($r['checked'])]]);
			}

			return $this->error($r['message'], ['code' => $r['output']]);
		}

		/** POST /requests/{id}/conditions/reorder — порядок условий (drag-and-drop). */
		public function conditionReorder(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Запрос не найден', [], 404);
			}

			$order = Request::post('items', Request::post('order', []));
			Model::reorderConditions($id, is_array($order) ? $order : []);
			return $this->success('Порядок сохранён');
		}

		/** GET /requests/alias — проверка алиаса (ajax). */
		public function aliasCheck(array $params = [])
		{
			$alias = strtolower(trim(Request::getStr('alias')));
			$id = Request::getInt('id');
			$valid = (bool) preg_match('/^[a-z0-9_-]{1,20}$/', $alias);
			return $this->success('', ['data' => [
				'available' => $valid && !Model::aliasExists($alias, $id),
				'valid'     => $valid,
			]]);
		}

		/** GET /requests/documents/picker — выбор документа для relation-условия. */
		public function documentPicker(array $params = array())
		{
			return $this->success('', array('data' => array(
				'items' => Model::documentPicker(
					Request::getStr('q', ''),
					Request::getStr('rubric_id', ''),
					Request::getInt('limit', 30)
				),
			)));
		}

		/** POST /requests/{id}/conditions — сохранить условие. */
		public function conditionSave(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Запрос не найден', [], 404);
			}

			try {
				$condId = Model::conditionSave($id, [
					'id'                 => Request::postInt('cond_id'),
					'condition_field_id' => Request::postInt('condition_field_id'),
					'condition_compare'  => Request::postStr('condition_compare'),
					'condition_value'    => (string) Request::post('condition_value', ''),
					'condition_value_source' => Request::postStr('condition_value_source'),
					'condition_value_key' => Request::postStr('condition_value_key'),
					'condition_value_mode' => Request::postStr('condition_value_mode'),
					'condition_value_constant' => (string) Request::post('condition_value_constant', ''),
					'condition_join'     => Request::postStr('condition_join'),
					'condition_status'   => Request::postBool('condition_status', false),
					'condition_group_id' => Request::postInt('condition_group_id'),
				]);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Условие сохранено', ['data' => ['id' => $condId]]);
		}

		/** POST /requests/conditions/{id}/delete — удалить условие. */
		public function conditionDelete(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			Model::conditionDelete((int) ($params['id'] ?? 0));
			return $this->success('Условие удалено');
		}

		public function groupSave(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$requestId = (int) ($params['id'] ?? 0);
			if (!Model::find($requestId)) {
				return $this->error('Запрос не найден', [], 404);
			}

			$groupId = Model::groupSave($requestId, array(
				'id' => Request::postInt('group_id'),
				'parent_id' => Request::postInt('parent_id'),
				'group_title' => Request::postStr('group_title'),
				'group_operator' => Request::postStr('group_operator'),
			));
			if ($groupId <= 0) {
				return $this->error('Не удалось сохранить группу', [], 422);
			}

			return $this->success('Группа сохранена', array('data' => array('id' => $groupId)));
		}

		public function groupDelete(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$requestId = (int) ($params['id'] ?? 0);
			$groupId = (int) ($params['groupId'] ?? 0);
			if (!Model::groupDelete($requestId, $groupId)) {
				return $this->error('Корневую или чужую группу удалить нельзя', [], 422);
			}

			return $this->success('Группа удалена. Ее содержимое перенесено выше.');
		}

		/** POST /requests/{id}/preview — первые строки через текущий executor. */
		public function preview(array $params = [])
		{
			if (($resp = $this->guardPermission('view_requests')) !== null) {
				return $resp;
			}

			$request = Model::find(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$request) {
				return $this->error('Запрос не найден', array(), 404);
			}

			$contract = \App\Content\Requests\RequestResultContract::normalize(array(
				'system' => Request::post('result_system', array()),
				'fields' => Request::post('result_fields', array()),
			), array_map(function ($field) { return (int) $field->Id; }, Model::rubricFields((int) $request->rubric_id)));

			try {
				$preview = (new RequestViewPreview())->run(
					(int) $request->Id,
					$contract,
					Request::postInt('limit', 10),
					RequestRendererRegistry::normalizePreviewCode(Request::postStr('renderer', Model::previewRenderer($request)))
				);
			} catch (\Throwable $e) {
				return $this->error(ErrorReport::publicMessage(
					'Предпросмотр запроса не выполнен',
					$e,
					'REQUEST_PREVIEW',
					array('request_id' => (int) $request->Id)
				), array(), 422);
			}

			$preview['plan'] = Model::previewPlan((int) $request->Id);
			try {
				$preview['executor_comparison'] = (new RequestExecutorComparator())->compare(
					(int) $request->Id,
					Request::postInt('limit', 10)
				);
				Model::recordNativeVerification((int) $request->Id, $preview['executor_comparison']);
			} catch (\Throwable $e) {
				$preview['executor_comparison'] = array(
					'status' => 'error',
					'matched' => false,
					'reasons' => array(ErrorReport::publicMessage(
						'Теневое сравнение не выполнено',
						$e,
						'REQUEST_EXECUTOR_COMPARE',
						array('request_id' => (int) $request->Id)
					)),
				);
			}

			$preview['contract'] = $contract;
			$preview['system_options'] = \App\Content\Requests\RequestResultContract::systemOptions();
			$preview['field_options'] = array();
			foreach (Model::rubricFields((int) $request->rubric_id) as $field) {
				$preview['field_options'][(string) $field->Id] = array(
					'title' => (string) $field->rubric_field_title,
					'alias' => (string) $field->rubric_field_alias,
					'type' => (string) $field->rubric_field_type,
				);
			}

			if (!Permission::check('manage_requests')) {
				unset($preview['sql']);
			}

			return $this->success('Предпросмотр обновлён', array('data' => $preview));
		}

		public function explainDocuments(array $params = array())
		{
			if (($resp = $this->guardPermission('view_requests')) !== null) { return $resp; }
			$requestId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::find($requestId)) { return $this->error('Запрос не найден', array(), 404); }

			return $this->success('', array('data' => array(
				'items' => Model::explainDocuments($requestId, Request::getStr('q', ''), Request::getInt('limit', 12)),
			)));
		}

		public function explain(array $params = array())
		{
			if (($resp = $this->guardPermission('view_requests')) !== null) { return $resp; }
			$requestId = isset($params['id']) ? (int) $params['id'] : 0;
			$documentId = Request::postInt('document_id');
			if ($requestId <= 0 || $documentId <= 0) {
				return $this->error('Выберите документ для проверки', array('document_id' => 'Документ не выбран'), 422);
			}

			try {
				$result = (new RequestMatchExplainer())->explain($requestId, $documentId);
			} catch (\Throwable $e) {
				return $this->error(ErrorReport::publicMessage(
					'Не удалось объяснить результат запроса',
					$e,
					'REQUEST_EXPLAIN',
					array('request_id' => $requestId, 'document_id' => $documentId)
				), array(), 422);
			}

			return $this->success($result['matched'] ? 'Документ входит в запрос' : 'Документ исключён запросом', array('data' => $result));
		}

		// ------------------------------------------------------------------ //

		protected function guard()
		{
			return $this->guardPermission('manage_requests');
		}

		protected function input()
		{
			$rubricId = Request::postInt('rubric_id');
			$fieldIds = array_map(function ($field) { return (int) $field->Id; }, Model::rubricFields($rubricId));
			$sortInput = Request::post('request_sort_rules', null);
			if ($sortInput === null) {
				$sortInput = array();
				$fieldOrder = Request::postInt('request_order_by_nat');
				$direction = Request::postStr('request_asc_desc') === 'DESC' ? 'DESC' : 'ASC';
				if ($fieldOrder > 0) { $sortInput[] = array('source' => 'field', 'key' => $fieldOrder, 'direction' => $direction); }
				$order = trim((string) Request::post('request_order_by', ''));
				if (strtoupper($order) === 'RAND()') { $sortInput = array(array('source' => 'random', 'key' => 'RAND()', 'direction' => 'ASC')); }
				elseif ($order !== '') { $sortInput[] = array('source' => 'document', 'key' => $order, 'direction' => $direction); }
				$tie = \App\Frontend\RequestSort::tieBreaker(Request::postStr('request_order_tiebreaker'));
				if ($tie !== '') { $sortInput[] = array('source' => 'document', 'key' => 'Id', 'direction' => $tie); }
			}

			$sortInspection = \App\Frontend\RequestSort::inspectRules($sortInput, $fieldIds);
			$legacySort = \App\Frontend\RequestSort::legacyStorage($sortInspection['rules']);
			$d = [
				'request_alias'          => strtolower(trim(Request::postStr('request_alias'))),
				'rubric_id'              => $rubricId,
				'request_title'          => trim((string) Request::post('request_title', '')),
				'request_description'    => trim((string) Request::post('request_description', '')),
				'request_order_by'       => $legacySort['request_order_by'],
				'request_order_by_nat'   => $legacySort['request_order_by_nat'],
				'request_asc_desc'       => $legacySort['request_asc_desc'],
				'request_order_tiebreaker' => $legacySort['request_order_tiebreaker'],
				'request_sort_rules'     => \App\Frontend\RequestSort::encodeRules($sortInspection['rules']),
				'request_items_per_page' => Request::postInt('request_items_per_page'),
				'request_pagination'     => Request::postInt('request_pagination'),
				'request_cache_lifetime' => Request::postInt('request_cache_lifetime'),
				'request_template_item'  => (string) Request::post('request_template_item', ''),
				'request_template_main'  => (string) Request::post('request_template_main', ''),
				'request_result_contract' => Model::encodeResultContract($rubricId, array(
					'system' => Request::post('result_system', array()),
					'fields' => Request::post('result_fields', array()),
				)),
				'request_preview_renderer' => RequestRendererRegistry::normalizePreviewCode(Request::postStr('request_preview_renderer', 'data_cards')),
				'request_executor_mode' => in_array(Request::postStr('request_executor_mode', 'legacy'), array('legacy', 'shadow', 'native'), true)
					? Request::postStr('request_executor_mode', 'legacy')
					: 'legacy',
			];
			$d['_sort_errors'] = $sortInspection['errors'];
			foreach (Model::$flags as $f) {
				$d[$f] = Request::postBool($f, false) ? 1 : 0;
			}

			return $d;
		}

		protected function validate(array $data, $id)
		{
			$errors = [];
			if ($data['request_alias'] === '') {
				$errors['request_alias'] = 'Укажите алиас.';
			} elseif (!preg_match('/^[a-z0-9_-]{1,20}$/', $data['request_alias'])) {
				$errors['request_alias'] = 'Алиас: латиница/цифры/-/_, до 20 символов.';
			} elseif (Model::aliasExists($data['request_alias'], (int) $id)) {
				$errors['request_alias'] = 'Такой алиас уже используется.';
			}

			if ($data['request_title'] === '') {
				$errors['request_title'] = 'Укажите название.';
			}

			if (!empty($data['_sort_errors'])) {
				$errors['request_sort_rules'] = implode(' ', $data['_sort_errors']);
			}

			$paginationIds = array_map(function ($item) { return (int) $item['id']; }, Model::paginationOptions());
			if (!$paginationIds) {
				$errors['request_pagination'] = 'Сначала создайте шаблон пагинации в настройках.';
			} elseif (!in_array((int) $data['request_pagination'], $paginationIds, true)) {
				$errors['request_pagination'] = 'Выберите существующий шаблон пагинации.';
			}

			if ($data['request_executor_mode'] === 'native') {
				if ((int) $id <= 0) {
					$errors['request_executor_mode'] = 'Сначала сохраните запрос в режиме Legacy или Shadow.';
				} else {
					$plan = \App\Content\Requests\NativeRequestPlanCompiler::compile((int) $id, $data);
					if (empty($plan['eligible'])) {
						$errors['request_executor_mode'] = implode(' ', $plan['reasons']);
					} elseif (!Model::nativeVerified(Model::find((int) $id), $plan)) {
						$errors['request_executor_mode'] = 'Сначала сохраните изменения и получите точное совпадение Legacy/Native в предпросмотре.';
					}
				}
			}

			return $errors;
		}
	}
