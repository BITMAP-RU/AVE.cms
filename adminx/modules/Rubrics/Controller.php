<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Adminx\Support\CodeEditor;
	use App\Adminx\Templates\Syntax;
	use App\Adminx\Templates\TagRegistry;
	use App\Helpers\Request;
	use App\Content\Fields\FieldSettings;
	use App\Content\Fields\FieldConditionEvaluator;
	use App\Content\Fields\FieldSettingsForm;
	use App\Content\Documents\DocumentAliasTemplate;
	use App\Content\Rubrics\FieldSetRegistry;
	use DB;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Rubrics/assets/rubrics.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Rubrics/assets/rubrics.js', 50);
			CodeEditor::useCodeMirror('application/x-httpd-php');

			$q = Request::getStr('q', '');
			$state = Request::getStr('state', '');
			$activeTab = Request::getStr('tab', '') === 'types' ? 'types' : 'rubrics';
			$typeUsage = Model::fieldTypeUsage();

			return $this->render('@rubrics/index.twig', array(
				'rubrics' => Model::all($q, $state),
				'stats' => Model::stats(),
				'field_types' => FieldTypes::all(),
				'templates' => Model::templateOptions(),
				'field_type_usage' => $typeUsage,
				'field_type_summary' => FieldTypes::summary($typeUsage),
				'field_presets' => RubricFieldPresets::all(),
				'rubric_alias_tokens' => DocumentAliasTemplate::tokens(),
				'filters' => array('q' => $q, 'state' => $state),
				'active_tab' => $activeTab,
				'trash_count' => RubricTrash::count(),
				'can_manage' => Permission::check('manage_rubrics'),
				'can_view_documents' => Permission::check('view_documents'),
				'can_manage_documents' => Permission::check('manage_documents'),
			));
		}

		public function fieldSets(array $params = array())
		{
			if (!Permission::check('view_rubrics')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/Rubrics/assets/field-sets.css', 55);
			AdminAssets::addScript($this->base() . '/modules/Rubrics/assets/field-sets.js', 55);
			$sets = RubricFieldPresets::all();
			$fields = 0;
			foreach ($sets as $set) { $fields += (int) $set['field_count']; }

			return $this->render('@rubrics/field-sets.twig', array(
				'field_sets' => $sets,
				'rubrics' => Model::all(),
				'stats' => array(
					'total' => count($sets),
					'fields' => $fields,
					'available' => count(array_filter($sets, function ($set) { return !empty($set['available']); })),
				),
				'can_manage' => Permission::check('manage_rubrics'),
			));
		}

		public function templateTags(array $params = array())
		{
			if (!Permission::check('view_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			return $this->success('', array(
				'data' => array('groups' => class_exists(TagRegistry::class) ? TagRegistry::groups() : array()),
			));
		}

		public function showRubric(array $params = array())
		{
			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			return $this->success('', array('data' => $item));
		}

		public function fields(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$rubric = Model::one($id);
			if (!$rubric) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			return $this->success('', array(
				'data' => array(
					'rubric' => $rubric,
					'groups' => Model::fieldGroups($id),
					'grouped_fields' => Model::groupedFields($id),
					'available_fields' => Model::availableFields($id),
					'field_types' => FieldTypes::all(),
					'field_set_links' => RubricFieldSetLinks::forRubric($id),
					'linked_field_sets_available' => RubricFieldSetLinks::available(),
				),
			));
		}

		public function applyFieldSet(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$code = isset($params['set']) ? trim((string) $params['set']) : '';
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }

			DB::startTransaction();
			try {
				$mode = Request::postStr('mode', 'copy') === 'linked' ? 'linked' : 'copy';
				if ($mode === 'linked' && RubricFieldSetLinks::hasLink($rubricId, $code)) {
					throw new \RuntimeException('Этот набор уже связан с рубрикой. Используйте обновление в списке связанных наборов.');
				}

				$preview = RubricFieldPresets::preview($code, $rubricId);
				$this->captureSchemaBefore($rubricId, 'Перед добавлением набора «' . $preview['title'] . '»');
				$result = RubricFieldPresets::apply($code, $rubricId, Auth::id());
				if ($mode === 'linked') {
					$definition = RubricFieldPresets::definition($code);
					RubricFieldSetLinks::attach($rubricId, $definition, $result['field_map'], Auth::id());
				}

				$this->captureSchemaAfter($rubricId, 'field_set', 'Добавлен набор «' . $preview['title'] . '»');
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				return $this->error($e->getMessage(), array(), 422);
			}

			$message = $result['fields'] > 0
				? 'Набор «' . $preview['title'] . '» добавлен'
				: 'Все поля набора уже есть в рубрике';
			return $this->success($message, array('data' => array(
				'preview' => $preview,
				'created' => $result,
			)));
		}

		public function previewFieldSet(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$code = isset($params['set']) ? trim((string) $params['set']) : '';
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }

			try {
				$preview = RubricFieldPresets::preview($code, $rubricId);
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$preview['linked_available'] = RubricFieldSetLinks::available();
			return $this->success('', array('data' => $preview));
		}

		public function exportFieldSet(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				$descriptor = RubricFieldPresets::exportDescriptor($rubricId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Набор полей подготовлен', array('data' => array(
				'descriptor' => $descriptor,
				'filename' => 'rubric-' . $rubricId . '-field-set.json',
			)));
		}

		public function previewImportedFieldSet(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }

			try {
				$descriptor = FieldSetRegistry::decodeDescriptor(Request::postStr('descriptor', ''));
				$preview = RubricFieldPresets::previewDefinition($descriptor['field_set'], $rubricId);
				$preview['linked_available'] = RubricFieldSetLinks::available();
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Набор проверен', array('data' => $preview));
		}

		public function applyImportedFieldSet(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }

			DB::startTransaction();
			try {
				$descriptor = FieldSetRegistry::decodeDescriptor(Request::postStr('descriptor', ''));
				$mode = Request::postStr('mode', 'copy') === 'linked' ? 'linked' : 'copy';
				$fingerprint = trim(Request::postStr('fingerprint', ''));
				if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
					throw new \RuntimeException('Сначала выполните предварительный просмотр набора');
				}

				$preview = RubricFieldPresets::previewDefinition($descriptor['field_set'], $rubricId);
				if (!hash_equals((string) $preview['fingerprint'], $fingerprint)) {
					throw new \RuntimeException('Состав рубрики изменился после проверки. Выполните предварительный просмотр ещё раз.');
				}

				$this->captureSchemaBefore($rubricId, 'Перед импортом набора «' . $preview['title'] . '»');
				$syncingExisting = $mode === 'linked'
					&& RubricFieldSetLinks::hasLink($rubricId, $descriptor['field_set']['code']);
				if ($syncingExisting) {
					RubricFieldSetLinks::saveCustomDefinition($descriptor['field_set'], Auth::id());
					$syncPreview = RubricFieldSetLinks::preview($rubricId, $descriptor['field_set']['code']);
					$result = RubricFieldSetLinks::sync(
						$rubricId,
						$descriptor['field_set']['code'],
						$syncPreview['fingerprint'],
						Auth::id()
					);
					$this->captureSchemaAfter($rubricId, 'field_set_sync', 'Обновлён набор «' . $preview['title'] . '»');
				} else {
					$result = RubricFieldPresets::applyDefinition(
						$descriptor['field_set'],
						$rubricId,
						Auth::id(),
						'import:' . $descriptor['field_set']['code'],
						$fingerprint
					);
					if ($mode === 'linked') {
						$definition = RubricFieldSetLinks::saveCustomDefinition($descriptor['field_set'], Auth::id());
						RubricFieldSetLinks::attach($rubricId, $definition, $result['field_map'], Auth::id());
					}

					$this->captureSchemaAfter($rubricId, 'field_set_import', 'Импортирован набор «' . $preview['title'] . '»');
				}

				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				return $this->error($e->getMessage(), array(), 422);
			}

			$message = !empty($syncingExisting)
				? 'Набор «' . $preview['title'] . '» обновлён и синхронизирован'
				: (!empty($result['fields'])
					? 'Набор «' . $preview['title'] . '» импортирован'
					: 'Все поля набора уже есть в рубрике');
			return $this->success($message, array('data' => array(
				'preview' => $preview,
				'created' => $result,
			)));
		}

		public function previewFieldSetSync(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) { return $this->error('Недостаточно прав', array(), 403); }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$code = isset($params['set']) ? trim((string) $params['set']) : '';
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }

			try {
				$preview = RubricFieldSetLinks::preview($rubricId, $code);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('', array('data' => $preview));
		}

		public function syncFieldSet(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$code = isset($params['set']) ? trim((string) $params['set']) : '';
			$fingerprint = trim(Request::postStr('fingerprint', ''));
			if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) { return $this->error('Сначала проверьте изменения набора', array(), 422); }

			DB::startTransaction();
			try {
				$preview = RubricFieldSetLinks::preview($rubricId, $code);
				$this->captureSchemaBefore($rubricId, 'Перед синхронизацией набора «' . $preview['title'] . '»');
				$result = RubricFieldSetLinks::sync($rubricId, $code, $fingerprint, Auth::id());
				$this->captureSchemaAfter($rubricId, 'field_set_sync', 'Синхронизирован набор «' . $preview['title'] . '»');
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Связанный набор синхронизирован', array('data' => $result));
		}

		public function detachFieldSet(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$code = isset($params['set']) ? trim((string) $params['set']) : '';
			try {
				RubricFieldSetLinks::detach($rubricId, $code, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Связь с набором удалена. Поля рубрики сохранены.');
		}

		public function showField(array $params = array())
		{
			$field = Model::field(isset($params['field']) ? (int) $params['field'] : 0);
			if (!$field) {
				return $this->error('Поле не найдено', array(), 404);
			}

			$settings = FieldSettings::effective($field);
			$field['layout'] = array(
				'placed'   => !array_key_exists('placed', $settings) || !empty($settings['placed']),
				'width'    => isset($settings['width']) ? (string) $settings['width'] : '',
				'required' => !empty($settings['required']),
				'prefix'   => isset($settings['prefix']) ? (string) $settings['prefix'] : '',
				'suffix'   => isset($settings['suffix']) ? (string) $settings['suffix'] : '',
			);
			return $this->success('', array('data' => $field));
		}

		public function showFieldType(array $params = array())
		{
			$type = FieldTypes::one(isset($params['type']) ? (string) $params['type'] : '');
			if (!$type) {
				return $this->error('Тип поля не найден', array(), 404);
			}

			$type['settings_html'] = FieldSettingsForm::render((string) $type['id'], array());
			return $this->success('', array('data' => $type));
		}

		public function toggleFieldType(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$type = FieldTypes::setEnabled(
					isset($params['type']) ? (string) $params['type'] : '',
					Request::postBool('enabled', false),
					Auth::id()
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success(!empty($type['enabled']) ? 'Тип поля включён' : 'Тип поля отключён', array(
				'data' => array(
					'type' => $type,
					'summary' => FieldTypes::summary(Model::fieldTypeUsage()),
				),
			));
		}

		public function fieldPlugin(array $params = array())
		{
			$field = Model::field(isset($params['field']) ? (int) $params['field'] : 0);
			if (!$field) {
				return $this->error('Поле не найдено', array(), 404);
			}

			$type = FieldTypes::one((string) $field['rubric_field_type']);
			if (!$type) {
				return $this->success('', array(
					'data' => array(
						'field' => $field,
						'type' => null,
						'render' => array(
							'status' => 'error',
							'message' => 'Тип поля не зарегистрирован в системном FieldRegistry',
						),
					),
				));
			}

			$type['settings_html'] = FieldSettingsForm::render(
				(string) $field['rubric_field_type'],
				FieldSettings::effective($field)
			);
			return $this->success('', array(
				'data' => array(
					'field' => $field,
					'type' => $type,
					'render' => FieldAdapter::editPreview($field),
				),
			));
		}

		public function templates(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$data = Model::templatesForRubric($id);
			if (!$data) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			return $this->success('', array('data' => $data));
		}

		public function adminView(array $params = array())
		{
			$data = AdminView::payload(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$data) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			return $this->success('', array('data' => $data));
		}

		public function updateAdminView(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			try {
				$view = AdminView::save($rubricId, Request::postAll(), Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Представление документов сохранено', array('data' => array('view' => $view)));
		}

		public function storeRubric(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$errors = $this->validateRubric(Request::postAll(), 0);
			if (!empty($errors)) {
				return $this->error('Проверьте поля рубрики', $errors, 422);
			}

			$preset = Request::postStr('rubric_preset', '');
			$isDirectory = Request::postStr('rubric_purpose', '') === 'directory';
			$presetError = RubricFieldPresets::availabilityError($preset);
			if ($presetError !== '') {
				return $this->error('Стартовый набор недоступен', array('rubric_preset' => $presetError), 422);
			}

			DB::startTransaction();
			try {
				$id = Model::saveRubric(0, Request::postAll(), Auth::id());
				$created = RubricFieldPresets::apply($preset, $id, Auth::id());
				$this->captureSchemaAfter(
					$id,
					'create',
					$isDirectory ? 'Создание справочника и начальной схемы' : 'Создание рубрики и начальной схемы'
				);
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				return $this->error($e->getMessage(), array(), 422);
			}

			$message = $isDirectory
				? ($created['fields'] > 0 ? 'Справочник и стартовый набор полей созданы' : 'Справочник создан')
				: ($created['fields'] > 0 ? 'Рубрика и стартовый набор полей созданы' : 'Рубрика создана');
			return $this->success($message, array(
				'data' => array('id' => $id, 'preset' => $preset, 'created' => $created),
				'redirect' => $this->base() . ($isDirectory ? '/directories' : '/rubrics'),
			));
		}

		public function updateRubric(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($id)) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			$errors = $this->validateRubric(Request::postAll(), $id);
			if (!empty($errors)) {
				return $this->error('Проверьте поля рубрики', $errors, 422);
			}

			$isDirectory = Request::postStr('rubric_purpose', '') === 'directory';
			Model::saveRubric($id, Request::postAll(), Auth::id());
			return $this->success(
				$isDirectory ? 'Справочник сохранён' : 'Рубрика сохранена',
				array('redirect' => $this->base() . ($isDirectory ? '/directories' : '/rubrics'))
			);
		}

		public function destroyRubric(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				Model::deleteRubric(isset($params['id']) ? (int) $params['id'] : 0, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Рубрика перемещена в корзину');
		}

		public function storeField(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			$errors = $this->validateField(Request::postAll(), $rubricId, 0);
			if (!empty($errors)) {
				return $this->error('Проверьте поле', $errors, 422);
			}

			try {
				$this->captureSchemaBefore($rubricId, 'Перед созданием поля');
				$id = Model::saveField(0, $rubricId, Request::postAll());
				$this->captureSchemaAfter($rubricId, 'field_create', 'Создано поле #' . $id);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Поле создано', array('data' => array('id' => $id)));
		}

		public function updateField(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['field']) ? (int) $params['field'] : 0;
			$field = Model::field($id);
			if (!$field) {
				return $this->error('Поле не найдено', array(), 404);
			}

			$errors = $this->validateField(Request::postAll(), (int) $field['rubric_id'], $id);
			if (!empty($errors)) {
				return $this->error('Проверьте поле', $errors, 422);
			}

			try {
				$this->captureSchemaBefore((int) $field['rubric_id'], 'Перед изменением поля #' . $id);
				Model::saveField($id, (int) $field['rubric_id'], Request::postAll());
				$this->captureSchemaAfter((int) $field['rubric_id'], 'field_update', 'Изменено поле #' . $id);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Поле сохранено');
		}

		public function destroyField(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$fieldId = isset($params['field']) ? (int) $params['field'] : 0;
			$field = Model::field($fieldId);
			try {
				if ($field) { $this->captureSchemaBefore((int) $field['rubric_id'], 'Перед удалением поля #' . $fieldId); }
				if (!Model::deleteField($fieldId)) {
					return $this->error('Поле не найдено', array(), 404);
				}

				$this->captureSchemaAfter((int) $field['rubric_id'], 'field_delete', 'Удалено поле #' . $fieldId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Поле удалено');
		}

		public function updateMainTemplate(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			Model::saveMainTemplate($rubricId, Request::postAll());
			return $this->success('Шаблоны рубрики сохранены');
		}

		/** Проверка синтаксиса PHP в коде шаблона рубрики (mixed HTML/PHP). */
		public function lint(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$result = Syntax::check(Request::postStr('code', ''));
			if (!$result['ok']) {
				return $this->error($result['message'], array('code' => $result['output']), 422);
			}

			return $this->success($result['message'], array('data' => $result));
		}

		public function showExtraTemplate(array $params = array())
		{
			$item = Model::extraTemplate(isset($params['template']) ? (int) $params['template'] : 0);
			if (!$item) {
				return $this->error('Шаблон рубрики не найден', array(), 404);
			}

			if (!$this->wantsJson()) {
				$this->redirect($this->base() . '/rubrics?templates=' . (int) $item['rubric_id']
					. '&template=' . (int) $item['id']);
				return null;
			}

			return $this->success('', array('data' => $item));
		}

		public function storeExtraTemplate(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) {
				return $this->error('Рубрика не найдена', array(), 404);
			}

			$errors = $this->validateExtraTemplate(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте шаблон', $errors, 422);
			}

			$id = Model::saveExtraTemplate(0, $rubricId, Request::postAll(), Auth::id());
			return $this->success('Шаблон рубрики создан', array('data' => array('id' => $id)));
		}

		public function updateExtraTemplate(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['template']) ? (int) $params['template'] : 0;
			if (!Model::extraTemplate($id)) {
				return $this->error('Шаблон рубрики не найден', array(), 404);
			}

			$errors = $this->validateExtraTemplate(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте шаблон', $errors, 422);
			}

			Model::saveExtraTemplate($id, 0, Request::postAll(), Auth::id());
			return $this->success('Шаблон рубрики сохранён');
		}

		public function destroyExtraTemplate(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				if (!Model::deleteExtraTemplate(isset($params['template']) ? (int) $params['template'] : 0)) {
					return $this->error('Шаблон рубрики не найден', array(), 404);
				}
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Шаблон рубрики удалён');
		}

		public function storeGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$errors = $this->validateGroup(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте группу', $errors, 422);
			}

			try {
				$this->captureSchemaBefore($rubricId, 'Перед созданием группы');
				$id = Model::saveGroup(0, $rubricId, Request::postAll());
				$this->captureSchemaAfter($rubricId, 'group_create', 'Создана группа #' . $id);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Группа создана', array('data' => array('id' => $id)));
		}

		public function updateGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$errors = $this->validateGroup(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте группу', $errors, 422);
			}

			$groupId = isset($params['group']) ? (int) $params['group'] : 0;
			$group = Model::group($groupId);
			if (!$group) { return $this->error('Группа не найдена', array(), 404); }
			try {
				$input = Request::postAll();
				$condition = array_key_exists('group_condition', $input)
					? Request::postJsonArray('group_condition')
					: FieldConditionEvaluator::groupCondition($group);
				if (!is_array($condition)) { return $this->error('Некорректное условие группы', array(), 422); }
				$impact = FieldConditionImpact::previewGroup($groupId, $condition);
				$fingerprint = trim(Request::postStr('impact_fingerprint'));
				if (!empty($impact['requires_confirmation'])
					&& ($fingerprint === '' || !hash_equals((string) $impact['fingerprint'], $fingerprint))) {
					return $this->error('Предпросмотр условия устарел. Проверьте изменение ещё раз.', array(), 409);
				}

				$this->captureSchemaBefore((int) $group['rubric_id'], 'Перед изменением группы #' . $groupId);
				Model::saveGroup($groupId, 0, Request::postAll());
				$this->captureSchemaAfter((int) $group['rubric_id'], 'group_update', 'Изменена группа #' . $groupId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Группа сохранена', array('data' => array('impact' => $impact)));
		}

		public function previewGroupCondition(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			$condition = Request::postJsonArray('group_condition');
			if (!is_array($condition)) {
				return $this->error('Некорректное условие группы', array(), 422);
			}

			try {
				$impact = FieldConditionImpact::previewGroup(
					isset($params['group']) ? (int) $params['group'] : 0,
					$condition
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('', array('data' => array('impact' => $impact)));
		}

		public function destroyGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$groupId = isset($params['group']) ? (int) $params['group'] : 0;
			$group = Model::group($groupId);
			if ($group) { $this->captureSchemaBefore((int) $group['rubric_id'], 'Перед удалением группы #' . $groupId); }
			if (!Model::deleteGroup($groupId)) {
				return $this->error('Группа не найдена', array(), 404);
			}

			$this->captureSchemaAfter((int) $group['rubric_id'], 'group_delete', 'Удалена группа #' . $groupId);

			return $this->success('Группа удалена');
		}

		public function reorderRubrics(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$order = Request::postJsonArray('order');
			if (!is_array($order)) {
				return $this->error('Некорректный порядок рубрик', array(), 422);
			}

			Model::reorderRubrics($order);
			return $this->success('Порядок рубрик сохранён');
		}

		public function reorderFields(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$order = Request::postJsonArray('order');
			if (!is_array($order)) {
				return $this->error('Некорректный порядок полей', array(), 422);
			}

			try {
				$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
				$this->captureSchemaBefore($rubricId, 'Перед изменением порядка полей');
				Model::reorderFields($rubricId, $order);
				$this->captureSchemaAfter($rubricId, 'reorder', 'Изменён порядок полей');
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Порядок полей сохранён');
		}

		public function saveFieldBuilder(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$items = Request::postJsonArray('layout');
			$drafts = Request::postJsonArray('drafts');
			$conditionsEnabled = Request::postBool('conditions_enabled', false);
			if (!is_array($items) || !is_array($drafts)) {
				return $this->error('Некорректные данные конструктора', array(), 422);
			}

			try {
				$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
				$impact = FieldConditionImpact::preview($rubricId, $drafts, $conditionsEnabled);
				$fingerprint = Request::postStr('impact_fingerprint', '');
				if (!empty($impact['requires_confirmation'])
					&& ($fingerprint === '' || !hash_equals((string) $impact['fingerprint'], $fingerprint))) {
					return $this->error('Предпросмотр условий устарел. Проверьте изменения ещё раз.', array(), 409);
				}

				$this->captureSchemaBefore($rubricId, 'Перед сохранением конструктора');
				Model::saveFieldBuilder($rubricId, $items, $drafts, $conditionsEnabled);
				$this->captureSchemaAfter($rubricId, 'layout', 'Сохранён конструктор формы');
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Раскладка полей сохранена', array('data' => array('impact' => $impact)));
		}

		public function previewFieldBuilder(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$drafts = Request::postJsonArray('drafts');
			if (!is_array($drafts)) {
				return $this->error('Некорректные настройки полей', array(), 422);
			}

			try {
				$impact = FieldConditionImpact::preview(
					isset($params['id']) ? (int) $params['id'] : 0,
					$drafts,
					Request::postBool('conditions_enabled', false)
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('', array('data' => array('impact' => $impact)));
		}

		public function reorderGroups(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$order = Request::postJsonArray('order');
			if (!is_array($order)) {
				return $this->error('Некорректный порядок групп', array(), 422);
			}

			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$this->captureSchemaBefore($rubricId, 'Перед изменением порядка групп');
			Model::reorderGroups($rubricId, $order);
			$this->captureSchemaAfter($rubricId, 'reorder', 'Изменён порядок групп');
			return $this->success('Порядок групп сохранён');
		}

		public function schemaRevisions(array $params = array())
		{
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			$rubric = Model::one($rubricId);
			if (!$rubric) { return $this->error('Рубрика не найдена', array(), 404); }

			return $this->success('', array('data' => array(
				'rubric' => array('id' => $rubricId, 'title' => $rubric['rubric_title']),
				'revisions' => RubricRevisions::listForRubric($rubricId),
			)));
		}

		public function schemaRevision(array $params = array())
		{
			$revisionId = isset($params['revision']) ? (int) $params['revision'] : 0;
			$revision = RubricRevisions::one($revisionId);
			if (!$revision) { return $this->error('Ревизия не найдена', array(), 404); }

			try {
				$impact = RubricRevisions::previewRestore($revisionId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			unset($revision['snapshot']);
			return $this->success('', array('data' => array('revision' => $revision, 'impact' => $impact)));
		}

		public function restoreSchemaRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			try {
				$result = RubricRevisions::restore(
					isset($params['revision']) ? (int) $params['revision'] : 0,
					Request::postStr('fingerprint', ''),
					Auth::id()
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			AuditLog::record('rubric.schema_restored', array(
				'actor_id' => Auth::id(),
				'target_type' => 'rubric',
				'target_id' => isset($result['rubric_id']) ? (int) $result['rubric_id'] : null,
				'meta' => $result,
			));
			return $this->success('Схема рубрики восстановлена', array('data' => $result));
		}

		public function deleteSchemaRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			$rubricId = RubricRevisions::delete(isset($params['revision']) ? (int) $params['revision'] : 0);
			if (!$rubricId) { return $this->error('Ревизия не найдена', array(), 404); }

			return $this->success('Ревизия удалена', array('data' => array('id' => $rubricId)));
		}

		public function deleteSchemaRevisions(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			$rubricId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($rubricId)) { return $this->error('Рубрика не найдена', array(), 404); }
			$count = RubricRevisions::deleteForRubric($rubricId);

			return $this->success('Ревизии удалены', array('data' => array('id' => $rubricId, 'count' => $count)));
		}

		public function trash(array $params = array())
		{
			return $this->success('', array('data' => array(
				'items' => RubricTrash::listing(),
				'count' => RubricTrash::count(),
			)));
		}

		public function restoreTrash(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			try {
				$rubricId = RubricTrash::restore(isset($params['id']) ? (int) $params['id'] : 0);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			AuditLog::record('rubric.restored_from_trash', array(
				'actor_id' => Auth::id(),
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
			));
			return $this->success('Рубрика восстановлена', array('data' => array('id' => (int) $rubricId)));
		}

		public function purgeTrash(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			$trashId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!RubricTrash::purge($trashId)) {
				return $this->error('Запись корзины не найдена', array(), 404);
			}

			AuditLog::record('rubric.purged', array(
				'actor_id' => Auth::id(),
				'target_type' => 'rubric_trash',
				'target_id' => $trashId,
			));
			return $this->success('Рубрика удалена окончательно', array('data' => array('id' => $trashId)));
		}

		protected function captureSchemaBefore($rubricId, $comment)
		{
			RubricRevisions::capture((int) $rubricId, 'baseline', Auth::id(), (string) $comment);
		}

		protected function captureSchemaAfter($rubricId, $action, $comment)
		{
			RubricRevisions::capture((int) $rubricId, (string) $action, Auth::id(), (string) $comment);
		}

		public function rubricAliasCheck(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$alias = trim(Request::getStr('alias', ''));
			$id = Request::getInt('id', 0);
			$result = $this->validateAlias($alias, $id, 'rubric');
			return $this->success($result['message'], array('data' => $result));
		}

		public function fieldAliasCheck(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$alias = trim(Request::getStr('alias', ''));
			$rubricId = Request::getInt('rubric_id', 0);
			$id = Request::getInt('id', 0);
			$result = $this->validateFieldAlias($rubricId, $alias, $id);
			return $this->success($result['message'], array('data' => $result));
		}

		public function documentPicker(array $params = array())
		{
			if (!Permission::check('manage_rubrics')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			return $this->success('', array(
				'data' => array(
					'items' => Model::documentPicker(
						Request::getStr('q', ''),
						Request::getInt('rubric_id', 0),
						Request::getInt('limit', 20)
					),
				),
			));
		}

		protected function guard()
		{
			return $this->guardPermission('manage_rubrics');
		}

		protected function validateRubric(array $input, $id)
		{
			$errors = array();
			$title = trim(isset($input['rubric_title']) ? (string) $input['rubric_title'] : '');
			$alias = trim(isset($input['rubric_alias']) ? (string) $input['rubric_alias'] : '');
			if ($title === '') {
				$errors['rubric_title'] = 'Укажите название';
			}

			$aliasState = $this->validateAlias($alias, (int) $id, 'rubric');
			if (!$aliasState['valid'] || !$aliasState['available']) {
				$errors['rubric_alias'] = $aliasState['message'];
			}

			foreach (array(
				'rubric_code_start' => 'Код до сохранения',
				'rubric_code_end' => 'Код после сохранения',
				'rubric_start_code' => 'Код публичного вывода',
			) as $key => $label) {
				$code = isset($input[$key]) ? (string) $input[$key] : '';
				if (trim($code) === '') {
					continue;
				}

				$syntax = Syntax::check($code);
				if (!$syntax['ok']) {
					$errors[$key] = $label . ': ' . ($syntax['output'] !== '' ? $syntax['output'] : $syntax['message']);
				}
			}

			return $errors;
		}

		protected function validateField(array $input, $rubricId, $id)
		{
			$errors = array();
			$title = trim(isset($input['rubric_field_title']) ? (string) $input['rubric_field_title'] : '');
			$alias = trim(isset($input['rubric_field_alias']) ? (string) $input['rubric_field_alias'] : '');
			$type = trim(isset($input['rubric_field_type']) ? (string) $input['rubric_field_type'] : '');
			if ($title === '') {
				$errors['rubric_field_title'] = 'Укажите название поля';
			}

			if (!FieldTypes::exists($type)) {
				$errors['rubric_field_type'] = 'Выберите тип поля';
			} elseif (!FieldTypes::isEnabled($type)) {
				$existing = (int) $id > 0 ? Model::field((int) $id) : null;
				if (!$existing || (string) $existing['rubric_field_type'] !== $type) {
					$errors['rubric_field_type'] = 'Этот тип поля отключён в реестре';
				}
			}

			$aliasState = $this->validateFieldAlias($rubricId, $alias, (int) $id);
			if (!$aliasState['valid'] || !$aliasState['available']) {
				$errors['rubric_field_alias'] = $aliasState['message'];
			}

			return $errors;
		}

		protected function validateGroup(array $input)
		{
			$title = trim(isset($input['group_title']) ? (string) $input['group_title'] : '');
			return $title === '' ? array('group_title' => 'Укажите название группы') : array();
		}

		protected function validateExtraTemplate(array $input)
		{
			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			return $title === '' ? array('title' => 'Укажите название шаблона') : array();
		}

		protected function validateAlias($alias, $id, $kind)
		{
			$result = array('alias' => $alias, 'valid' => true, 'available' => true, 'message' => 'Алиас свободен');
			if ($alias === '') {
				$result['message'] = 'Без отдельного URL-алиаса';
			} elseif (($templateError = DocumentAliasTemplate::validationError($alias)) !== '') {
				$result['valid'] = false;
				$result['available'] = false;
				$result['message'] = $templateError;
			} elseif ($kind === 'rubric' && Model::rubricAliasExists($alias, (int) $id)) {
				$result['available'] = false;
				$result['message'] = 'Такой алиас уже используется';
			}

			return $result;
		}

		protected function validateFieldAlias($rubricId, $alias, $id)
		{
			$result = array('alias' => $alias, 'valid' => true, 'available' => true, 'message' => 'Алиас свободен');
			if ($alias === '') {
				$result['valid'] = false;
				$result['available'] = false;
				$result['message'] = 'Укажите алиас';
			} elseif (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $alias) !== 1) {
				$result['valid'] = false;
				$result['available'] = false;
				$result['message'] = 'Латинская буква первой, далее буквы, цифры или подчёркивание, до 20 символов';
			} elseif (Model::fieldAliasExists((int) $rubricId, $alias, (int) $id)) {
				$result['available'] = false;
				$result['message'] = 'Такой алиас уже есть в этой рубрике';
			}

			return $result;
		}
	}
