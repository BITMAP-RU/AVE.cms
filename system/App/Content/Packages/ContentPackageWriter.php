<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Packages/ContentPackageWriter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Packages;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Documents\ContentCacheInvalidator;
	use App\Content\Documents\DocumentMutationService;
	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Content\Fields\FieldSettings;
	use App\Content\PublicUserTables;
	use App\Content\Rubrics\RubricFieldMutationService;
	use App\Content\Rubrics\RubricMutationService;
	use App\Content\Templates\TemplateMutationService;
	use App\Common\DatabaseSchema;
	use App\Common\FileCacheInvalidator;
	use App\Helpers\Json;
	use DB;

	/** Trusted domain facade used by content/theme package migrations. */
	class ContentPackageWriter
	{
		protected $templates;
		protected $rubrics;
		protected $fields;
		protected $documents;

		public function __construct()
		{
			$this->templates = new TemplateMutationService();
			$this->rubrics = new RubricMutationService();
			$this->fields = new RubricFieldMutationService();
			$this->documents = new DocumentMutationService();
		}

		public function createTemplate(array $payload, $actorId)
		{
			$text = isset($payload['text']) ? (string) $payload['text'] : (isset($payload['template_text']) ? (string) $payload['template_text'] : '');
			if (strpos($text, '[tag:maincontent]') === false) {
				throw new \InvalidArgumentException('Шаблон пакета должен содержать [tag:maincontent]');
			}

			return $this->templates->save(0, $payload, $actorId);
		}

		public function updateTemplate($templateId, array $payload, $actorId)
		{
			$text = isset($payload['text']) ? (string) $payload['text'] : (isset($payload['template_text']) ? (string) $payload['template_text'] : '');
			if (strpos($text, '[tag:maincontent]') === false) {
				throw new \InvalidArgumentException('Шаблон пакета должен содержать [tag:maincontent]');
			}

			return $this->templates->save((int) $templateId, $payload, $actorId);
		}

		public function createRubric(array $payload, $actorId)
		{
			return $this->rubrics->save(0, $payload, $actorId);
		}

		public function updateRubric($rubricId, array $payload, $actorId)
		{
			return $this->rubrics->save((int) $rubricId, $payload, $actorId);
		}

		/**
		 * Update only the public presentation of an existing rubric.
		 *
		 * Existing documents, field definitions and stored values remain intact.
		 */
		public function updateRubricPresentation($rubricId, array $payload)
		{
			$rubricId = (int) $rubricId;
			if (!$this->rubrics->exists($rubricId)) {
				throw new \InvalidArgumentException('Рубрика представления не найдена');
			}

			$map = array(
				'template_id' => 'rubric_template_id',
				'document_template' => 'rubric_template',
				'header_template' => 'rubric_header_template',
				'og_template' => 'rubric_og_template',
				'footer_template' => 'rubric_footer_template',
			);
			$data = array();
			foreach ($map as $key => $column) {
				if (!array_key_exists($key, $payload)) { continue; }
				$data[$column] = $key === 'template_id'
					? max(1, (int) $payload[$key])
					: (string) $payload[$key];
			}

			if ($data) {
				$data['rubric_changed'] = time();
				DB::Update(ContentTables::table('rubrics'), $data, 'Id = %i', $rubricId);
				FileCacheInvalidator::rubric($rubricId);
			}

			return $rubricId;
		}

		public function createField($rubricId, array $payload)
		{
			return $this->fields->create($rubricId, $payload);
		}

		public function updateFieldPackageData($fieldId, array $payload)
		{
			$fieldId = (int) $fieldId;
			$field = DB::query('SELECT rubric_id FROM ' . ContentTables::table('rubric_fields') . ' WHERE Id = %i LIMIT 1', $fieldId)->getAssoc();
			if (!$field) { throw new \InvalidArgumentException('Поле рубрики не найдено'); }
			$data = array();
			if (array_key_exists('settings', $payload) && is_array($payload['settings'])) { $data['rubric_field_settings'] = FieldSettings::encode($payload['settings']); }
			if (array_key_exists('template', $payload)) { $data['rubric_field_template'] = (string) $payload['template']; }
			if (array_key_exists('request_template', $payload)) { $data['rubric_field_template_request'] = (string) $payload['request_template']; }
			if ($data) {
				DB::Update(ContentTables::table('rubric_fields'), $data, 'Id = %i', $fieldId);
				FileCacheInvalidator::rubric((int) $field['rubric_id']);
			}

			return $fieldId;
		}

		public function createFieldGroup($rubricId, array $payload)
		{
			$rubricId = (int) $rubricId;
			if (!$this->rubrics->exists($rubricId)) { throw new \InvalidArgumentException('Рубрика группы полей не найдена'); }
			$title = trim(isset($payload['title']) ? (string) $payload['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('Укажите название группы полей'); }
			$data = array(
				'rubric_id' => $rubricId,
				'group_title' => $title,
				'group_description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'group_position' => isset($payload['position']) ? (int) $payload['position'] : 100,
			);
			if (DatabaseSchema::columnExists(ContentTables::table('rubric_fields_group'), 'group_settings')) {
				$data['group_settings'] = !empty($payload['settings']) && is_array($payload['settings'])
					? Json::encode($payload['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
					: null;
			}

			DB::Insert(ContentTables::table('rubric_fields_group'), $data);
			FileCacheInvalidator::rubric($rubricId);
			return (int) DB::insertId();
		}

		public function updateFieldGroupPackageData($groupId, array $payload)
		{
			$group = DB::query(
				'SELECT rubric_id FROM ' . ContentTables::table('rubric_fields_group') . ' WHERE Id = %i LIMIT 1',
				(int) $groupId
			)->getAssoc();
			if (!$group) { throw new \InvalidArgumentException('Группа полей не найдена'); }
			if (!DatabaseSchema::columnExists(ContentTables::table('rubric_fields_group'), 'group_settings')) {
				return (int) $groupId;
			}

			$settings = isset($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : array();
			DB::Update(ContentTables::table('rubric_fields_group'), array(
				'group_settings' => $settings ? Json::encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
			), 'Id = %i', (int) $groupId);
			FileCacheInvalidator::rubric((int) $group['rubric_id']);
			return (int) $groupId;
		}

		public function createRubricTemplate($rubricId, array $payload, $actorId)
		{
			$rubricId = (int) $rubricId;
			if (!$this->rubrics->exists($rubricId)) { throw new \InvalidArgumentException('Рубрика шаблона не найдена'); }
			$title = trim(isset($payload['title']) ? (string) $payload['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('Укажите название шаблона рубрики'); }
			DB::Insert(ContentTables::table('rubric_templates'), array(
				'rubric_id' => $rubricId,
				'title' => $title,
				'template' => isset($payload['template']) ? (string) $payload['template'] : '',
				'author_id' => max(1, (int) $actorId),
				'created' => time(),
			));
			FileCacheInvalidator::rubric($rubricId);
			return (int) DB::insertId();
		}

		/** Create or update an additional rubric template identified by its title. */
		public function upsertRubricTemplate($rubricId, array $payload, $actorId)
		{
			$rubricId = (int) $rubricId;
			if (!$this->rubrics->exists($rubricId)) {
				throw new \InvalidArgumentException('Рубрика шаблона не найдена');
			}

			$title = trim(isset($payload['title']) ? (string) $payload['title'] : '');
			if ($title === '') {
				throw new \InvalidArgumentException('Укажите название шаблона рубрики');
			}

			$current = DB::query(
				'SELECT id FROM ' . ContentTables::table('rubric_templates')
					. ' WHERE rubric_id = %i AND title = %s LIMIT 1',
				$rubricId,
				$title
			)->getAssoc();
			if (!$current) {
				return $this->createRubricTemplate($rubricId, $payload, $actorId);
			}

			DB::Update(ContentTables::table('rubric_templates'), array(
				'title' => $title,
				'template' => isset($payload['template']) ? (string) $payload['template'] : '',
				'author_id' => max(1, (int) $actorId),
			), 'id = %i', (int) $current['id']);
			FileCacheInvalidator::rubric($rubricId);
			return (int) $current['id'];
		}

		public function replaceRubricPermissions($rubricId, array $permissions)
		{
			$rubricId = (int) $rubricId;
			if (!$this->rubrics->exists($rubricId)) { throw new \InvalidArgumentException('Рубрика прав не найдена'); }
			$groups = array();
			$groupsByName = array();
			foreach (DB::query('SELECT user_group,user_group_name FROM ' . PublicUserTables::table('user_groups'))->getAll() ?: array() as $row) {
				$groupId = (int) $row['user_group'];
				$groups[$groupId] = true;
				$name = mb_strtolower(trim((string) $row['user_group_name']), 'UTF-8');
				if ($name !== '') { $groupsByName[$name] = $groupId; }
			}

			DB::Delete(ContentTables::table('rubric_permissions'), 'rubric_id = %i', $rubricId);
			$count = 0;
			foreach ($permissions as $permission) {
				$groupId = 0;
				$groupName = isset($permission['group_name']) ? mb_strtolower(trim((string) $permission['group_name']), 'UTF-8') : '';
				if ($groupName !== '' && isset($groupsByName[$groupName])) {
					$groupId = $groupsByName[$groupName];
				} elseif (isset($permission['group_id'])) {
					$groupId = (int) $permission['group_id'];
				}

				if ($groupId <= 0 || !isset($groups[$groupId])) { continue; }
				DB::Insert(ContentTables::table('rubric_permissions'), array(
					'rubric_id' => $rubricId,
					'user_group_id' => $groupId,
					'rubric_permission' => isset($permission['permission']) ? (string) $permission['permission'] : 'docread',
				));
				$count++;
			}

			FileCacheInvalidator::rubric($rubricId);
			return $count;
		}

		public function createDocument(array $payload, $actorId)
		{
			return $this->documents->save(0, $payload, $actorId, 'content_package');
		}

		public function updateDocument($documentId, array $payload, $actorId)
		{
			return $this->documents->save((int) $documentId, $payload, $actorId, 'content_package');
		}

		/** Assign presentation metadata without rewriting document data or field values. */
		public function assignDocumentRubricTemplate($documentId, $templateId)
		{
			$documentId = (int) $documentId;
			$templateId = max(0, (int) $templateId);
			if (!DB::query(
				'SELECT Id FROM ' . ContentTables::table('documents') . ' WHERE Id = %i LIMIT 1',
				$documentId
			)->getValue()) {
				throw new \InvalidArgumentException('Документ представления не найден');
			}

			DB::Update(ContentTables::table('documents'), array(
				'rubric_tmpl_id' => $templateId,
			), 'Id = %i', $documentId);
			ContentCacheInvalidator::document($documentId, false);
			return $documentId;
		}

		public function replaceDocumentFieldTokens(array $documentIds, array $replacements)
		{
			$documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds))));
			if (!$documentIds || !$replacements) {
				return 0;
			}

			$search = array();
			$replace = array();
			foreach ($replacements as $token => $value) {
				$token = (string) $token;
				if ($token === '') {
					continue;
				}

				$search[] = $token;
				$replace[] = (string) $value;
			}

			if (!$search) {
				return 0;
			}

			$changed = 0;
			$changedDocuments = array();
			$idList = implode(',', $documentIds);
			foreach (array('document_fields' => 'field_value', 'document_fields_text' => 'field_value') as $table => $column) {
				$rows = DB::query(
					'SELECT Id,document_id,' . $column . ' FROM ' . ContentTables::table($table) . ' WHERE document_id IN (' . $idList . ')'
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$current = (string) $row[$column];
					$next = str_replace($search, $replace, $current);
					if ($next === $current) {
						continue;
					}

					DB::Update(ContentTables::table($table), array($column => $next), 'Id = %i', (int) $row['Id']);
					$changedDocuments[(int) $row['document_id']] = (int) $row['document_id'];
					$changed++;
				}
			}

			if ($changedDocuments) {
				foreach ($changedDocuments as $documentId) {
					ContentCacheInvalidator::document($documentId, false);
				}

				(new DocumentSnapshotRepository())->findMany(array_values($changedDocuments));
			}

			return $changed;
		}

		public function replaceRubricTemplateTokens(array $rubricIds, array $replacements)
		{
			$rubricIds = array_values(array_unique(array_filter(array_map('intval', $rubricIds))));
			if (!$rubricIds || !$replacements) {
				return 0;
			}

			$search = array_keys($replacements);
			$replace = array_map('strval', array_values($replacements));
			$changed = 0;
			$rows = DB::query(
				'SELECT Id,rubric_template FROM ' . ContentTables::table('rubrics') . ' WHERE Id IN (' . implode(',', $rubricIds) . ')'
			)->getAll() ?: array();
			foreach ($rows as $row) {
				$current = (string) $row['rubric_template'];
				$next = str_replace($search, $replace, $current);
				if ($next === $current) { continue; }
				DB::Update(ContentTables::table('rubrics'), array('rubric_template' => $next), 'Id = %i', (int) $row['Id']);
				FileCacheInvalidator::rubric((int) $row['Id']);
				$changed++;
			}

			return $changed;
		}

		public function createNavigation(array $payload)
		{
			$alias = $this->alias(isset($payload['alias']) ? $payload['alias'] : '', 20);
			if ($alias === '') {
				throw new \InvalidArgumentException('Укажите alias навигации');
			}

			if (DB::query('SELECT navigation_id FROM ' . ContentTables::table('navigation') . ' WHERE alias = %s LIMIT 1', $alias)->getValue()) {
				throw new \InvalidArgumentException('Навигация с таким alias уже существует');
			}

			$defaults = array(
				'title' => '', 'level1' => '', 'level2' => '', 'level3' => '',
				'level1_active' => '', 'level2_active' => '', 'level3_active' => '',
				'level1_begin' => '', 'level1_end' => '', 'level2_begin' => '', 'level2_end' => '',
				'level3_begin' => '', 'level3_end' => '', 'begin' => '', 'end' => '',
				'user_group' => '1,2,3,4', 'expand_ext' => '1',
			);
			$data = array('alias' => $alias);
			foreach ($defaults as $key => $default) {
				$data[$key] = isset($payload[$key]) ? (string) $payload[$key] : $default;
			}

			DB::Insert(ContentTables::table('navigation'), $data);
			return (int) DB::insertId();
		}

		public function updateNavigation($navigationId, array $payload)
		{
			$navigationId = (int) $navigationId;
			$current = DB::query(
				'SELECT alias FROM ' . ContentTables::table('navigation') . ' WHERE navigation_id = %i LIMIT 1',
				$navigationId
			)->getAssoc();
			if (!$current) {
				throw new \RuntimeException('Навигация пакета не найдена');
			}

			$allowed = array(
				'title', 'level1', 'level2', 'level3', 'level1_active', 'level2_active', 'level3_active',
				'level1_begin', 'level1_end', 'level2_begin', 'level2_end', 'level3_begin', 'level3_end',
				'begin', 'end', 'user_group', 'expand_ext',
			);
			$data = array();
			foreach ($allowed as $key) {
				if (array_key_exists($key, $payload)) {
					$data[$key] = (string) $payload[$key];
				}
			}

			if ($data) {
				DB::Update(ContentTables::table('navigation'), $data, 'navigation_id = %i', $navigationId);
				FileCacheInvalidator::navigation($navigationId, isset($current['alias']) ? $current['alias'] : '');
			}

			return $navigationId;
		}

		public function createNavigationItem($navigationId, array $payload)
		{
			$navigationId = (int) $navigationId;
			if (!DB::query('SELECT navigation_id FROM ' . ContentTables::table('navigation') . ' WHERE navigation_id = %i LIMIT 1', $navigationId)->getValue()) {
				throw new \InvalidArgumentException('Навигация не найдена');
			}

			$data = array(
				'navigation_id' => $navigationId,
				'document_id' => !empty($payload['document_id']) ? (int) $payload['document_id'] : null,
				'alias' => isset($payload['alias']) ? (string) $payload['alias'] : '',
				'title' => isset($payload['title']) ? (string) $payload['title'] : '',
				'description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'target' => isset($payload['target']) && in_array($payload['target'], array('_blank', '_self', '_parent', '_top'), true) ? $payload['target'] : '_self',
				'image' => isset($payload['image']) ? (string) $payload['image'] : '',
				'css_style' => isset($payload['css_style']) ? (string) $payload['css_style'] : null,
				'css_id' => isset($payload['css_id']) ? (string) $payload['css_id'] : null,
				'css_class' => isset($payload['css_class']) ? (string) $payload['css_class'] : null,
				'parent_id' => isset($payload['parent_id']) ? (int) $payload['parent_id'] : 0,
				'level' => isset($payload['level']) && in_array((int) $payload['level'], array(1, 2, 3), true) ? (string) (int) $payload['level'] : '1',
				'position' => max(1, isset($payload['position']) ? (int) $payload['position'] : 1),
				'status' => !isset($payload['active']) || !empty($payload['active']) ? '1' : '0',
			);
			if ($data['title'] === '') {
				throw new \InvalidArgumentException('Укажите название пункта навигации');
			}

			DB::Insert(ContentTables::table('navigation_items'), $data);
			return (int) DB::insertId();
		}

		public function createSysblock(array $payload, $actorId)
		{
			$alias = $this->alias(isset($payload['alias']) ? $payload['alias'] : '', 20);
			if ($alias === '') {
				throw new \InvalidArgumentException('Укажите alias системного блока');
			}

			if (DB::query('SELECT id FROM ' . ContentTables::table('sysblocks') . ' WHERE sysblock_alias = %s LIMIT 1', $alias)->getValue()) {
				throw new \InvalidArgumentException('Системный блок с таким alias уже существует');
			}

			$editor = isset($payload['editor']) && in_array($payload['editor'], array('plain', 'rich', 'code'), true) ? $payload['editor'] : 'rich';
			DB::Insert(ContentTables::table('sysblocks'), array(
				'sysblock_group_id' => isset($payload['group_id']) ? (int) $payload['group_id'] : 0,
				'sysblock_name' => isset($payload['title']) ? (string) $payload['title'] : $alias,
				'sysblock_description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'sysblock_alias' => $alias,
				'sysblock_text' => isset($payload['text']) ? (string) $payload['text'] : '',
				'sysblock_active' => !isset($payload['active']) || !empty($payload['active']) ? '1' : '0',
				'sysblock_eval' => !empty($payload['evaluate']) ? '1' : '0',
				'sysblock_external' => !empty($payload['external']) ? '1' : '0',
				'sysblock_ajax' => !empty($payload['ajax']) ? '1' : '0',
				'sysblock_visual' => !isset($payload['visual']) || !empty($payload['visual']) ? '1' : '0',
				'sysblock_editor' => $editor,
				'sysblock_author_id' => (int) $actorId > 0 ? (int) $actorId : 1,
				'sysblock_created' => time(),
			));
			return (int) DB::insertId();
		}

		/** Create a block by alias or update only its editable presentation fields. */
		public function upsertSysblock(array $payload, $actorId)
		{
			$alias = $this->alias(isset($payload['alias']) ? $payload['alias'] : '', 20);
			if ($alias === '') {
				throw new \InvalidArgumentException('Укажите alias системного блока');
			}

			$current = DB::query(
				'SELECT id FROM ' . ContentTables::table('sysblocks') . ' WHERE sysblock_alias = %s LIMIT 1',
				$alias
			)->getAssoc();
			if (!$current) {
				$payload['alias'] = $alias;
				return $this->createSysblock($payload, $actorId);
			}

			$editor = isset($payload['editor']) && in_array($payload['editor'], array('plain', 'rich', 'code'), true)
				? $payload['editor']
				: 'rich';
			$data = array(
				'sysblock_name' => isset($payload['title']) ? (string) $payload['title'] : $alias,
				'sysblock_description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'sysblock_text' => isset($payload['text']) ? (string) $payload['text'] : '',
				'sysblock_active' => !isset($payload['active']) || !empty($payload['active']) ? '1' : '0',
				'sysblock_eval' => !empty($payload['evaluate']) ? '1' : '0',
				'sysblock_external' => !empty($payload['external']) ? '1' : '0',
				'sysblock_ajax' => !empty($payload['ajax']) ? '1' : '0',
				'sysblock_visual' => !isset($payload['visual']) || !empty($payload['visual']) ? '1' : '0',
				'sysblock_editor' => $editor,
				'sysblock_author_id' => (int) $actorId > 0 ? (int) $actorId : 1,
			);
			if (array_key_exists('group_id', $payload)) {
				$data['sysblock_group_id'] = (int) $payload['group_id'];
			}

			DB::Update(ContentTables::table('sysblocks'), $data, 'id = %i', (int) $current['id']);
			FileCacheInvalidator::sysblock((int) $current['id'], $alias);
			return (int) $current['id'];
		}

		public function createRequest(array $payload, $actorId)
		{
			$alias = $this->alias(isset($payload['alias']) ? $payload['alias'] : '', 20);
			$rubricId = isset($payload['rubric_id']) ? (int) $payload['rubric_id'] : 0;
			if ($alias === '') {
				throw new \InvalidArgumentException('Укажите alias запроса');
			}

			if (!DB::query('SELECT Id FROM ' . ContentTables::table('rubrics') . ' WHERE Id = %i LIMIT 1', $rubricId)->getValue()) {
				throw new \InvalidArgumentException('Рубрика запроса не найдена');
			}

			if (DB::query('SELECT Id FROM ' . ContentTables::table('request') . ' WHERE request_alias = %s LIMIT 1', $alias)->getValue()) {
				throw new \InvalidArgumentException('Запрос с таким alias уже существует');
			}

			$now = time();
			$sortInput = isset($payload['sort_rules']) && is_array($payload['sort_rules']) ? $payload['sort_rules'] : array();
			if (!$sortInput) {
				$fieldOrder = max(0, isset($payload['field_order']) ? (int) $payload['field_order'] : (isset($payload['natural_order']) ? (int) $payload['natural_order'] : 0));
				$direction = isset($payload['direction']) && strtoupper((string) $payload['direction']) === 'ASC' ? 'ASC' : 'DESC';
				if ($fieldOrder > 0) { $sortInput[] = array('source' => 'field', 'key' => $fieldOrder, 'direction' => $direction); }
				$order = isset($payload['order_by']) ? (string) $payload['order_by'] : 'document_published';
				if (strtoupper($order) === 'RAND()') { $sortInput = array(array('source' => 'random', 'key' => 'RAND()', 'direction' => 'ASC')); }
				elseif ($order !== '') { $sortInput[] = array('source' => 'document', 'key' => $order, 'direction' => $direction); }
				$tie = isset($payload['order_tiebreaker']) ? \App\Frontend\RequestSort::tieBreaker($payload['order_tiebreaker']) : '';
				if ($tie !== '') { $sortInput[] = array('source' => 'document', 'key' => 'Id', 'direction' => $tie); }
			}

			$sortInspection = \App\Frontend\RequestSort::inspectRules($sortInput);
			if ($sortInspection['errors']) { throw new \InvalidArgumentException(implode(' ', $sortInspection['errors'])); }
			$legacySort = \App\Frontend\RequestSort::legacyStorage($sortInspection['rules']);
			DB::Insert(ContentTables::table('request'), array(
				'rubric_id' => $rubricId,
				'request_alias' => $alias,
				'request_items_per_page' => max(1, min(500, isset($payload['limit']) ? (int) $payload['limit'] : 10)),
				'request_title' => isset($payload['title']) ? (string) $payload['title'] : $alias,
				'request_template_item' => isset($payload['item_template']) ? (string) $payload['item_template'] : '[tag:doctitle]',
				'request_template_main' => isset($payload['main_template']) ? (string) $payload['main_template'] : '[tag:content]',
				'request_order_by' => $legacySort['request_order_by'],
				'request_order_by_nat' => $legacySort['request_order_by_nat'],
				'request_author_id' => max(1, (int) $actorId),
				'request_created' => $now,
				'request_description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'request_result_contract' => \App\Content\Requests\RequestResultContract::encode(
					isset($payload['result_contract']) && is_array($payload['result_contract']) ? $payload['result_contract'] : array()
				),
				'request_preview_renderer' => \App\Content\Requests\RequestRendererRegistry::normalizePreviewCode(
					isset($payload['preview_renderer']) ? $payload['preview_renderer'] : 'data_cards'
				),
				'request_executor_mode' => isset($payload['executor_mode']) && in_array($payload['executor_mode'], array('legacy', 'shadow', 'native'), true)
					? $payload['executor_mode']
					: 'legacy',
				'request_native_plan' => null,
				'request_asc_desc' => $legacySort['request_asc_desc'],
				'request_order_tiebreaker' => $legacySort['request_order_tiebreaker'],
				'request_sort_rules' => \App\Frontend\RequestSort::encodeRules($sortInspection['rules']),
				'request_show_pagination' => !empty($payload['pagination']) ? '1' : '0',
				'request_pagination' => max(1, isset($payload['pagination_style']) ? (int) $payload['pagination_style'] : 1),
				'request_use_query' => !empty($payload['use_query']) ? '1' : '0',
				'request_count_items' => !empty($payload['count_items']) ? '1' : '0', 'request_where_cond' => '',
				'request_hide_current' => !isset($payload['hide_current']) || !empty($payload['hide_current']) ? '1' : '0',
				'request_only_owner' => !empty($payload['only_owner']) ? '1' : '0',
				'request_cache_lifetime' => max(0, isset($payload['cache_lifetime']) ? (int) $payload['cache_lifetime'] : 0),
				'request_cache_elements' => !empty($payload['cache_elements']) ? '1' : '0',
				'request_show_statistic' => !empty($payload['show_statistic']) ? '1' : '0',
				'request_external' => !empty($payload['external']) ? '1' : '0',
				'request_ajax' => !empty($payload['ajax']) ? '1' : '0',
				'request_show_sql' => !empty($payload['show_sql']) ? '1' : '0',
				'request_changed' => $now, 'request_changed_elements' => $now,
			));
			$requestId = (int) DB::insertId();
			DB::Insert(ContentTables::table('request_condition_groups'), array(
				'request_id' => $requestId, 'parent_id' => 0, 'group_title' => 'Основная группа',
				'group_operator' => 'AND', 'group_position' => 0,
			));
			return $requestId;
		}

		/** Update templates and display options without changing a request condition tree. */
		public function updateRequestPresentation($requestId, array $payload)
		{
			$requestId = (int) $requestId;
			$current = DB::query(
				'SELECT request_alias FROM ' . ContentTables::table('request') . ' WHERE Id = %i LIMIT 1',
				$requestId
			)->getAssoc();
			if (!$current) {
				throw new \InvalidArgumentException('Запрос представления не найден');
			}

			$map = array(
				'title' => 'request_title',
				'description' => 'request_description',
				'item_template' => 'request_template_item',
				'main_template' => 'request_template_main',
			);
			$data = array();
			foreach ($map as $key => $column) {
				if (array_key_exists($key, $payload)) {
					$data[$column] = (string) $payload[$key];
				}
			}

			if (array_key_exists('items_per_page', $payload)) {
				$data['request_items_per_page'] = max(1, min(100, (int) $payload['items_per_page']));
			}

			if (array_key_exists('show_pagination', $payload)) {
				$data['request_show_pagination'] = !empty($payload['show_pagination']) ? '1' : '0';
			}

			if (array_key_exists('pagination_id', $payload)) {
				$data['request_pagination'] = max(1, (int) $payload['pagination_id']);
			}

			if (array_key_exists('hide_current', $payload)) {
				$data['request_hide_current'] = !empty($payload['hide_current']) ? '1' : '0';
			}

			if ($data) {
				$data['request_changed'] = time();
				$data['request_changed_elements'] = time();
				DB::Update(ContentTables::table('request'), $data, 'Id = %i', $requestId);
				\App\Frontend\RequestRepository::reset();
				FileCacheInvalidator::request(
					$requestId,
					isset($current['request_alias']) ? $current['request_alias'] : ''
				);
			}

			return $requestId;
		}

		public function replaceRequestTree($requestId, array $groups, array $conditions, array $fieldMap)
		{
			$requestId = (int) $requestId;
			if (!DB::query('SELECT Id FROM ' . ContentTables::table('request') . ' WHERE Id = %i LIMIT 1', $requestId)->getValue()) {
				throw new \InvalidArgumentException('Запрос не найден');
			}

			DB::Delete(ContentTables::table('request_conditions'), 'request_id = %i', $requestId);
			DB::Delete(ContentTables::table('request_condition_groups'), 'request_id = %i', $requestId);
			$pending = array_values($groups);
			$groupMap = array();
			$guard = count($pending) + 1;
			while ($pending && $guard-- > 0) {
				$next = array();
				foreach ($pending as $group) {
					$sourceId = isset($group['source_id']) ? (int) $group['source_id'] : 0;
					$parentSource = isset($group['parent_source_id']) ? (int) $group['parent_source_id'] : 0;
					if ($parentSource > 0 && !isset($groupMap[$parentSource])) { $next[] = $group; continue; }
					DB::Insert(ContentTables::table('request_condition_groups'), array(
						'request_id' => $requestId,
						'parent_id' => $parentSource > 0 ? $groupMap[$parentSource] : 0,
						'group_title' => isset($group['title']) ? substr((string) $group['title'], 0, 100) : '',
						'group_operator' => isset($group['operator']) && $group['operator'] === 'OR' ? 'OR' : 'AND',
						'group_position' => max(0, isset($group['position']) ? (int) $group['position'] : 0),
					));
					$groupMap[$sourceId] = (int) DB::insertId();
				}

				if (count($next) === count($pending)) { throw new \InvalidArgumentException('Дерево групп условий содержит разорванные связи'); }
				$pending = $next;
			}

			if (!$groupMap) {
				DB::Insert(ContentTables::table('request_condition_groups'), array(
					'request_id' => $requestId, 'parent_id' => 0, 'group_title' => '', 'group_operator' => 'AND', 'group_position' => 0,
				));
				$groupMap[0] = (int) DB::insertId();
			}

			$root = reset($groupMap);
			foreach ($conditions as $condition) {
				$sourceField = isset($condition['field_source_id']) ? (int) $condition['field_source_id'] : 0;
				$fieldId = isset($condition['field_id']) ? (int) $condition['field_id'] : 0;
				if ($sourceField > 0) {
					if (!isset($fieldMap[$sourceField])) { throw new \InvalidArgumentException('Условие ссылается на неизвестное поле'); }
					$fieldId = (int) $fieldMap[$sourceField];
				}

				$groupSource = isset($condition['group_source_id']) ? (int) $condition['group_source_id'] : 0;
				DB::Insert(ContentTables::table('request_conditions'), array(
					'request_id' => $requestId,
					'condition_group_id' => isset($groupMap[$groupSource]) ? $groupMap[$groupSource] : $root,
					'condition_compare' => isset($condition['compare']) ? substr((string) $condition['compare'], 0, 30) : '==',
					'condition_field_id' => $fieldId,
					'condition_value' => isset($condition['value']) ? substr((string) $condition['value'], 0, 1000) : '',
					'condition_value_source' => isset($condition['value_source']) ? substr((string) $condition['value_source'], 0, 16) : '',
					'condition_value_key' => isset($condition['value_key']) ? substr((string) $condition['value_key'], 0, 64) : '',
					'condition_value_config' => isset($condition['value_config']) ? (string) $condition['value_config'] : null,
					'condition_join' => isset($condition['join']) && $condition['join'] === 'OR' ? 'OR' : 'AND',
					'condition_position' => max(0, isset($condition['position']) ? (int) $condition['position'] : 0),
					'condition_status' => !isset($condition['active']) || !empty($condition['active']) ? '1' : '0',
				));
			}

			\App\Content\Requests\NativeRequestPlanCompiler::persist($requestId);
			FileCacheInvalidator::request($requestId);
			return array('groups' => count($groupMap), 'conditions' => count($conditions));
		}

		public function deleteDocument($documentId)
		{
			return $this->documents->hardDelete($documentId, 'content_package');
		}

		public function deleteField($fieldId)
		{
			return $this->fields->delete($fieldId);
		}

		public function deleteRubric($rubricId)
		{
			return $this->rubrics->delete($rubricId);
		}

		public function deleteTemplate($templateId)
		{
			return $this->templates->delete($templateId);
		}

		public function deleteNavigationItem($itemId)
		{
			DB::Delete(ContentTables::table('navigation_items'), 'navigation_item_id = %i', (int) $itemId);
		}

		public function deleteNavigation($navigationId)
		{
			$navigationId = (int) $navigationId;
			DB::Delete(ContentTables::table('navigation_items'), 'navigation_id = %i', $navigationId);
			DB::Delete(ContentTables::table('navigation'), 'navigation_id = %i', $navigationId);
		}

		public function deleteSysblock($blockId)
		{
			$blockId = (int) $blockId;
			DB::Delete(ContentTables::table('sysblock_revisions'), 'block_id = %i', $blockId);
			DB::Delete(ContentTables::table('sysblocks'), 'id = %i', $blockId);
		}

		public function deleteRequest($requestId)
		{
			$requestId = (int) $requestId;
			DB::Delete(ContentTables::table('request_conditions'), 'request_id = %i', $requestId);
			DB::Delete(ContentTables::table('request_condition_groups'), 'request_id = %i', $requestId);
			DB::Delete(ContentTables::table('request'), 'Id = %i', $requestId);
		}

		public function moveTemplateReferences($templateId, $fallbackTemplateId = 1)
		{
			$templateId = (int) $templateId;
			$fallbackTemplateId = (int) $fallbackTemplateId;
			if (!$this->templates->exists($fallbackTemplateId)) {
				throw new \RuntimeException('Резервный шаблон #' . $fallbackTemplateId . ' не найден');
			}

			$rubricsTable = ContentTables::table('rubrics');
			$documentsTable = ContentTables::table('documents');
			$documents = (int) DB::query(
				'SELECT COUNT(*) FROM ' . $documentsTable . ' d INNER JOIN ' . $rubricsTable . ' r ON r.Id = d.rubric_id WHERE r.rubric_template_id = %i',
				$templateId
			)->getValue();
			$rubrics = (int) DB::query('SELECT COUNT(*) FROM ' . $rubricsTable . ' WHERE rubric_template_id = %i', $templateId)->getValue();
			DB::Update($rubricsTable, array('rubric_template_id' => $fallbackTemplateId, 'rubric_changed' => time()), 'rubric_template_id = %i', $templateId);
			return array('rubrics' => $rubrics, 'documents' => $documents);
		}

		protected function alias($value, $length)
		{
			$value = strtolower(trim((string) $value));
			if ($value === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $value)) {
				return '';
			}

			return substr($value, 0, (int) $length);
		}
	}
