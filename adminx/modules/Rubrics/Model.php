<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use App\Content\PublicUserTables;
	use App\Common\AuditLog;
	use App\Common\FileCacheInvalidator;
	use App\Content\Fields\FieldConditionEvaluator;
	use App\Content\Fields\FieldRegistry;
	use App\Content\Fields\FieldSettings;
	use App\Content\Fields\FieldSettingsForm;
	use App\Helpers\Json;

	class Model
	{
		public static function rubricsTable() { return ContentTables::table('rubrics'); }
		public static function fieldsTable() { return ContentTables::table('rubric_fields'); }
		public static function groupsTable() { return ContentTables::table('rubric_fields_group'); }
		public static function templatesTable() { return ContentTables::table('rubric_templates'); }
		public static function docsTable() { return ContentTables::table('documents'); }
		public static function docFieldsTable() { return ContentTables::table('document_fields'); }
		public static function docFieldsTextTable() { return ContentTables::table('document_fields_text'); }
		public static function permissionsTable() { return ContentTables::table('rubric_permissions'); }
		public static function userGroupsTable() { return PublicUserTables::table('user_groups'); }

		/** Зарегистрированные шаблоны сайта (Id + название) для выпадающего выбора. */
		public static function templateOptions()
		{
			try {
				return DB::query('SELECT Id, template_title FROM ' . ContentTables::table('templates') . ' ORDER BY template_title ASC')->getAll() ?: array();
			} catch (\Throwable $e) {
				return array();
			}
		}

		public static function all($q = '', $state = '')
		{
			$sql = 'SELECT r.*,'
				. ' (SELECT COUNT(*) FROM ' . self::fieldsTable() . ' f WHERE f.rubric_id=r.Id) AS fields_count,'
				. ' (SELECT COUNT(*) FROM ' . self::docsTable() . ' d WHERE d.rubric_id=r.Id) AS docs_count,'
				. ' (SELECT COUNT(*) FROM ' . self::templatesTable() . ' t WHERE t.rubric_id=r.Id) AS templates_count,'
				. ' (SELECT st.template_title FROM ' . ContentTables::table('templates') . ' st WHERE st.Id=r.rubric_template_id LIMIT 1) AS site_template_title'
				. ' FROM ' . self::rubricsTable() . ' r WHERE 1=1';
			$args = array();
			$q = trim((string) $q);
			if ($q !== '') {
				$sql .= ' AND (r.rubric_title LIKE %ss OR r.rubric_alias LIKE %ss OR r.rubric_description LIKE %ss OR r.Id = %i)';
				$args[] = $q;
				$args[] = $q;
				$args[] = $q;
				$args[] = (int) $q;
			}

			if ($state === 'active') {
				$sql .= ' AND r.rubric_docs_active = 1';
			} elseif ($state === 'inactive') {
				$sql .= ' AND r.rubric_docs_active = 0';
			} elseif ($state === 'meta') {
				$sql .= ' AND r.rubric_meta_gen = %s';
				$args[] = '1';
			}

			$sql .= ' ORDER BY r.rubric_position ASC, r.Id ASC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::rubricRow($row);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . self::rubricsTable() . ' WHERE Id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::rubricRow($row, true) : null;
		}

		public static function stats()
		{
			return array(
				'rubrics' => (int) DB::query('SELECT COUNT(*) FROM ' . self::rubricsTable())->getValue(),
				'active' => (int) DB::query('SELECT COUNT(*) FROM ' . self::rubricsTable() . ' WHERE rubric_docs_active = 1')->getValue(),
				'fields' => (int) DB::query('SELECT COUNT(*) FROM ' . self::fieldsTable())->getValue(),
				'groups' => (int) DB::query('SELECT COUNT(*) FROM ' . self::groupsTable())->getValue(),
			);
		}

		public static function fieldTypeUsage()
		{
			$rows = DB::query(
				'SELECT rubric_field_type, COUNT(*) AS cnt FROM ' . self::fieldsTable() . ' GROUP BY rubric_field_type'
			)->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[(string) $row['rubric_field_type']] = (int) $row['cnt'];
			}

			return $out;
		}

		public static function saveRubric($id, array $input, $authorId)
		{
			$data = self::rubricInput($input);
			$now = time();
			if ((int) $id > 0) {
				$before = DB::query(
					'SELECT rubric_code_start, rubric_code_end, rubric_start_code FROM ' . self::rubricsTable() . ' WHERE Id = %i LIMIT 1',
					(int) $id
				)->getAssoc();
				$data['rubric_changed'] = $now;
				$data['rubric_changed_fields'] = $now;
				DB::Update(self::rubricsTable(), $data, 'Id = %i', (int) $id);
				self::clearRubricCache((int) $id);
				self::auditRubricSave('rubric.updated', (int) $id, (int) $authorId, $before, $data);
				return (int) $id;
			}

			$data['rubric_author_id'] = (int) $authorId > 0 ? (int) $authorId : 1;
			$data['rubric_created'] = $now;
			$data['rubric_changed'] = $now;
			$data['rubric_changed_fields'] = $now;
			$data['rubric_position'] = self::nextPosition(self::rubricsTable(), 'rubric_position');
			$data['rubric_template'] = '[tag:maincontent]';
			$data['rubric_teaser_template'] = '';
			$data['rubric_header_template'] = '';
			$data['rubric_og_template'] = '';
			$data['rubric_footer_template'] = '';
			$data['rubric_linked_rubric'] = '0';
			DB::Insert(self::rubricsTable(), $data);
			$newId = (int) DB::insertId();
			self::seedPermissions($newId);
			self::clearRubricCache($newId);
			self::auditRubricSave('rubric.created', $newId, (int) $authorId, array(), $data);
			return $newId;
		}

		protected static function auditRubricSave($action, $rubricId, $authorId, array $before, array $after)
		{
			$changed = array();
			foreach (array('rubric_code_start', 'rubric_code_end', 'rubric_start_code') as $key) {
				$changed[$key] = (string) (isset($before[$key]) ? $before[$key] : '') !== (string) (isset($after[$key]) ? $after[$key] : '');
			}

			AuditLog::record((string) $action, array(
				'actor_id' => (int) $authorId > 0 ? (int) $authorId : null,
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
				'meta' => array('managed_code_changed' => $changed),
			));
		}

		public static function deleteRubric($id)
		{
			$id = (int) $id;
			if ($id <= 1) {
				throw new \RuntimeException('Рубрика #1 является системной и не может быть удалена');
			}

			$docs = self::documentCount($id);
			if ($docs > 0) {
				throw new \RuntimeException('В рубрике есть документы, удаление недоступно');
			}

			$dependencies = self::rubricDependencies($id);
			if (!empty($dependencies)) {
				throw new \RuntimeException('Рубрика используется: ' . implode(', ', $dependencies) . '. Сначала уберите эти связи.');
			}

			$fields = DB::query('SELECT Id FROM ' . self::fieldsTable() . ' WHERE rubric_id = %i', $id)->getAll() ?: array();
			foreach ($fields as $field) {
				$fieldDependencies = self::fieldDependencies((int) $field['Id'], false);
				if (!empty($fieldDependencies)) {
					throw new \RuntimeException(
						'Поле #' . (int) $field['Id'] . ' используется: ' . implode(', ', $fieldDependencies)
						. '. Сначала уберите эти связи.'
					);
				}
			}

			DB::Delete(self::fieldsTable(), 'rubric_id = %i', $id);
			DB::Delete(self::groupsTable(), 'rubric_id = %i', $id);
			DB::Delete(self::templatesTable(), 'rubric_id = %i', $id);
			DB::Delete(self::permissionsTable(), 'rubric_id = %i', $id);
			DB::Delete(AdminView::table(), 'rubric_id = %i', $id);
			RubricFieldSetLinks::deleteForRubric($id);
			DB::Delete(self::rubricsTable(), 'Id = %i', $id);
			RubricRevisions::deleteForRubric($id);
			self::clearRubricCache($id);
			return true;
		}

		public static function fieldsForRubric($rubricId)
		{
			$groupSettings = self::hasGroupSettingsColumn() ? ', g.group_settings' : ', NULL AS group_settings';
			$rows = DB::query(
				'SELECT f.*, g.group_title, g.group_description, g.group_position' . $groupSettings
				. ' FROM ' . self::fieldsTable() . ' f'
				. ' LEFT JOIN ' . self::groupsTable() . ' g ON g.Id = f.rubric_field_group'
				. ' WHERE f.rubric_id = %i'
				. ' ORDER BY COALESCE(g.group_position, 999999) ASC, f.rubric_field_position ASC, f.Id ASC',
				(int) $rubricId
			)->getAll();
			$typeNames = FieldTypes::names();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::fieldRow($row, $typeNames);
			}

			return $out;
		}

		public static function fieldGroups($rubricId)
		{
			$rows = DB::query(
				'SELECT g.*, (SELECT COUNT(*) FROM ' . self::fieldsTable() . ' f WHERE f.rubric_field_group = g.Id) AS fields_count'
				. ' FROM ' . self::groupsTable() . ' g WHERE g.rubric_id = %i ORDER BY g.group_position ASC, g.Id ASC',
				(int) $rubricId
			)->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::groupRow($row);
			}

			return $out;
		}

		public static function group($id)
		{
			return DB::query('SELECT * FROM ' . self::groupsTable() . ' WHERE Id=%i LIMIT 1', (int) $id)->getAssoc() ?: null;
		}

		public static function groupedFields($rubricId)
		{
			$groups = array(0 => array('id' => 0, 'title' => 'Без группы', 'description' => '', 'position' => 999999, 'items' => array()));
			foreach (self::fieldGroups($rubricId) as $group) {
				$group['items'] = array();
				$groups[$group['id']] = $group;
			}

			foreach (self::fieldsForRubric($rubricId) as $field) {
				if (isset($field['layout']['placed']) && !$field['layout']['placed']) {
					continue;
				}

				$groupId = (int) $field['rubric_field_group'];
				if (!isset($groups[$groupId])) {
					$groupId = 0;
				}

				$groups[$groupId]['items'][] = $field;
			}

			return array_values($groups);
		}

		public static function availableFields($rubricId)
		{
			$available = array();
			foreach (self::fieldsForRubric($rubricId) as $field) {
				if (isset($field['layout']['placed']) && !$field['layout']['placed']) {
					$available[] = $field;
				}
			}

			return $available;
		}

		/** Полный, но не содержащий значения документов снимок схемы формы рубрики. */
		public static function schemaSnapshot($rubricId)
		{
			$rubricId = (int) $rubricId;
			$rubric = DB::query('SELECT * FROM ' . self::rubricsTable() . ' WHERE Id=%i LIMIT 1', $rubricId)->getAssoc();
			if (!$rubric) { return array(); }
			$groupSettings = self::hasGroupSettingsColumn() ? ',group_settings' : ',NULL AS group_settings';
			$groups = DB::query(
				'SELECT Id,rubric_id,group_title,group_description,group_position' . $groupSettings . ' FROM ' . self::groupsTable()
				. ' WHERE rubric_id=%i ORDER BY Id',
				$rubricId
			)->getAll() ?: array();
			$fields = DB::query('SELECT * FROM ' . self::fieldsTable() . ' WHERE rubric_id=%i ORDER BY Id', $rubricId)->getAll() ?: array();

			return self::normalizeSchemaSnapshot(array(
				'version' => 1,
				'rubric' => array(
					'id' => $rubricId,
					'form_conditions_enabled' => !empty($rubric['rubric_form_conditions']),
				),
				'groups' => $groups,
				'fields' => $fields,
			));
		}

		public static function normalizeSchemaSnapshot(array $snapshot)
		{
			$groupKeys = array('Id', 'rubric_id', 'group_title', 'group_description', 'group_position', 'group_settings');
			$fieldKeys = array(
				'Id', 'rubric_id', 'rubric_field_group', 'rubric_field_alias', 'rubric_field_title',
				'rubric_field_type', 'rubric_field_numeric', 'rubric_field_position', 'rubric_field_default',
				'rubric_field_settings', 'rubric_field_search', 'rubric_field_template',
				'rubric_field_template_request', 'rubric_field_description',
			);
			$out = array(
				'version' => 1,
				'rubric' => array(
					'id' => isset($snapshot['rubric']['id']) ? (int) $snapshot['rubric']['id'] : 0,
					'form_conditions_enabled' => !empty($snapshot['rubric']['form_conditions_enabled']),
				),
				'groups' => array(),
				'fields' => array(),
			);
			foreach (isset($snapshot['groups']) && is_array($snapshot['groups']) ? $snapshot['groups'] : array() as $group) {
				if (!is_array($group) || empty($group['Id'])) { continue; }
				$row = array();
				foreach ($groupKeys as $key) { $row[$key] = isset($group[$key]) ? $group[$key] : ''; }
				$row['Id'] = (int) $row['Id'];
				$row['rubric_id'] = (int) $row['rubric_id'];
				$row['group_position'] = (int) $row['group_position'];
				$out['groups'][] = $row;
			}

			foreach (isset($snapshot['fields']) && is_array($snapshot['fields']) ? $snapshot['fields'] : array() as $field) {
				if (!is_array($field) || empty($field['Id'])) { continue; }
				$row = array();
				foreach ($fieldKeys as $key) { $row[$key] = isset($field[$key]) ? $field[$key] : ''; }
				foreach (array('Id', 'rubric_id', 'rubric_field_group', 'rubric_field_position') as $key) { $row[$key] = (int) $row[$key]; }
				$out['fields'][] = $row;
			}

			usort($out['groups'], function ($a, $b) { return $a['Id'] <=> $b['Id']; });
			usort($out['fields'], function ($a, $b) { return $a['Id'] <=> $b['Id']; });

			return $out;
		}

		/** Восстанавливает схему без удаления полей, появившихся после снимка. */
		public static function applySchemaSnapshot($rubricId, array $snapshot)
		{
			$rubricId = (int) $rubricId;
			$snapshot = self::normalizeSchemaSnapshot($snapshot);
			if (!self::one($rubricId)) { throw new \RuntimeException('Рубрика не найдена'); }

			$currentGroups = DB::query('SELECT * FROM ' . self::groupsTable() . ' WHERE rubric_id=%i', $rubricId)->getAll() ?: array();
			$currentFields = DB::query('SELECT * FROM ' . self::fieldsTable() . ' WHERE rubric_id=%i', $rubricId)->getAll() ?: array();
			$groupMap = array();
			$currentGroupIds = array();
			foreach ($currentGroups as $group) { $currentGroupIds[(int) $group['Id']] = true; }
			foreach ($snapshot['groups'] as $group) {
				$sourceId = (int) $group['Id'];
				$data = array(
					'rubric_id' => $rubricId,
					'group_title' => (string) $group['group_title'],
					'group_description' => (string) $group['group_description'],
					'group_position' => (int) $group['group_position'],
				);
				if (self::hasGroupSettingsColumn()) {
					$data['group_settings'] = trim((string) $group['group_settings']) !== '' ? (string) $group['group_settings'] : null;
				}

				if (isset($currentGroupIds[$sourceId])) {
					DB::Update(self::groupsTable(), $data, 'Id=%i AND rubric_id=%i', $sourceId, $rubricId);
					$groupMap[$sourceId] = $sourceId;
					continue;
				}

				$owner = DB::query('SELECT rubric_id FROM ' . self::groupsTable() . ' WHERE Id=%i LIMIT 1', $sourceId)->getValue();
				if (!$owner) { $data['Id'] = $sourceId; }
				DB::Insert(self::groupsTable(), $data);
				$groupMap[$sourceId] = !$owner ? $sourceId : (int) DB::insertId();
			}

			$currentFieldIds = array();
			foreach ($currentFields as $field) { $currentFieldIds[(int) $field['Id']] = $field; }
			$targetFieldIds = array();
			$restored = 0;
			$updated = 0;
			foreach ($snapshot['fields'] as $field) {
				$sourceId = (int) $field['Id'];
				$targetFieldIds[$sourceId] = true;
				$data = self::schemaFieldData($field, $rubricId, $groupMap);
				if (isset($currentFieldIds[$sourceId])) {
					DB::Update(self::fieldsTable(), $data, 'Id=%i AND rubric_id=%i', $sourceId, $rubricId);
					$updated++;
					continue;
				}

				$owner = DB::query('SELECT rubric_id FROM ' . self::fieldsTable() . ' WHERE Id=%i LIMIT 1', $sourceId)->getValue();
				if ($owner) {
					throw new \RuntimeException('ID поля #' . $sourceId . ' уже занят другой рубрикой');
				}

				$data['Id'] = $sourceId;
				DB::Insert(self::fieldsTable(), $data);
				$newId = $sourceId;
				self::seedDocumentFields($rubricId, $newId);
				$restored++;
			}

			$preserved = 0;
			foreach ($currentFieldIds as $fieldId => $field) {
				if (isset($targetFieldIds[$fieldId])) { continue; }
				$settings = FieldSettings::effective($field);
				$settings['placed'] = false;
				DB::Update(self::fieldsTable(), array('rubric_field_settings' => FieldSettings::encode($settings)), 'Id=%i', $fieldId);
				$preserved++;
			}

			if (self::hasRubricFormConditionsColumn()) {
				DB::Update(self::rubricsTable(), array(
					'rubric_form_conditions' => !empty($snapshot['rubric']['form_conditions_enabled']) ? 1 : 0,
				), 'Id=%i', $rubricId);
			}

			self::touchFields($rubricId);
			return array('rubric_id' => $rubricId, 'fields_updated' => $updated, 'fields_restored' => $restored, 'fields_preserved' => $preserved);
		}

		protected static function schemaFieldData(array $field, $rubricId, array $groupMap)
		{
			$groupId = (int) $field['rubric_field_group'];
			return array(
				'rubric_id' => (int) $rubricId,
				'rubric_field_group' => isset($groupMap[$groupId]) ? (int) $groupMap[$groupId] : 0,
				'rubric_field_alias' => (string) $field['rubric_field_alias'],
				'rubric_field_title' => (string) $field['rubric_field_title'],
				'rubric_field_type' => (string) $field['rubric_field_type'],
				'rubric_field_numeric' => (string) $field['rubric_field_numeric'] === '1' ? '1' : '0',
				'rubric_field_position' => max(1, (int) $field['rubric_field_position']),
				'rubric_field_default' => (string) $field['rubric_field_default'],
				'rubric_field_settings' => (string) $field['rubric_field_settings'],
				'rubric_field_search' => (string) $field['rubric_field_search'] === '0' ? '0' : '1',
				'rubric_field_template' => (string) $field['rubric_field_template'],
				'rubric_field_template_request' => (string) $field['rubric_field_template_request'],
				'rubric_field_description' => (string) $field['rubric_field_description'],
			);
		}

		public static function field($id)
		{
			$row = DB::query(
				'SELECT f.*, g.group_title, g.group_description, g.group_position FROM ' . self::fieldsTable() . ' f'
				. ' LEFT JOIN ' . self::groupsTable() . ' g ON g.Id = f.rubric_field_group'
				. ' WHERE f.Id = %i LIMIT 1',
				(int) $id
			)->getAssoc();
			return $row ? self::fieldRow($row, FieldTypes::names(), true) : null;
		}

		public static function saveField($id, $rubricId, array $input)
		{
			$rubricId = (int) $rubricId;
			$data = self::fieldInput($input);
			if ((int) $id > 0) {
				$field = self::field($id);
				if (!$field) {
					throw new \RuntimeException('Поле не найдено');
				}

				if ((string) $field['rubric_field_type'] !== (string) $data['rubric_field_type'] && self::fieldHasValues((int) $id)) {
					throw new \RuntimeException('Нельзя изменить тип заполненного поля. Сначала перенесите или очистите его значения.');
				}

				if (self::hasFieldSettingsColumn() && isset($data['rubric_field_settings'])) {
					$oldSettings = FieldSettings::effective($field);
					$newSettings = Json::toArray((string) $data['rubric_field_settings']);
					$explicitLayout = isset($input['rubric_field_layout']) && is_array($input['rubric_field_layout'])
						? $input['rubric_field_layout']
						: array();
					if (array_key_exists('placed', $oldSettings) && !array_key_exists('placed', $explicitLayout)) {
						$newSettings['placed'] = !empty($oldSettings['placed']);
					}

					if (isset($oldSettings['form_condition']) && !isset($newSettings['form_condition'])) {
						$newSettings['form_condition'] = $oldSettings['form_condition'];
					}

					$data['rubric_field_settings'] = FieldSettings::encode($newSettings);
				}

				self::assertGroupBelongsToRubric((int) $data['rubric_field_group'], (int) $field['rubric_id']);
				DB::Update(self::fieldsTable(), $data, 'Id = %i', (int) $id);
				self::touchFields((int) $field['rubric_id']);
				return (int) $id;
			}

			self::assertGroupBelongsToRubric((int) $data['rubric_field_group'], $rubricId);
			$data['rubric_id'] = $rubricId;
			$data['rubric_field_position'] = self::nextFieldPosition($rubricId, (int) $data['rubric_field_group']);
			DB::Insert(self::fieldsTable(), $data);
			$newId = (int) DB::insertId();
			self::seedDocumentFields($rubricId, $newId);
			self::touchFields($rubricId);
			return $newId;
		}

		public static function deleteField($id)
		{
			$field = self::field($id);
			if (!$field) {
				return false;
			}

			$dependencies = self::fieldDependencies((int) $id);
			if (!empty($dependencies)) {
				throw new \RuntimeException('Поле используется: ' . implode(', ', $dependencies) . '. Сначала уберите эти связи.');
			}

			DB::Delete(self::fieldsTable(), 'Id = %i', (int) $id);
			DB::Delete(self::docFieldsTable(), 'rubric_field_id = %i', (int) $id);
			if (self::tableExists(self::docFieldsTextTable())) {
				DB::Delete(self::docFieldsTextTable(), 'rubric_field_id = %i', (int) $id);
			}

			\App\Content\Fields\DocumentRelationIndex::removeField((int) $id);
			self::deleteDerivedFieldData((int) $id);
			self::touchFields((int) $field['rubric_id']);
			return true;
		}

		public static function saveGroup($id, $rubricId, array $input)
		{
			$group = null;
			if ((int) $id > 0) {
				$group = DB::query('SELECT * FROM ' . self::groupsTable() . ' WHERE Id = %i LIMIT 1', (int) $id)->getAssoc();
				if (!$group) { throw new \RuntimeException('Группа не найдена'); }
				$rubricId = (int) $group['rubric_id'];
			}

			$data = array(
				'group_title' => trim(isset($input['group_title']) ? (string) $input['group_title'] : ''),
				'group_description' => isset($input['group_description']) ? (string) $input['group_description'] : '',
			);
			if (self::hasGroupSettingsColumn()) {
				$settings = $group ? Json::toArray(isset($group['group_settings']) ? $group['group_settings'] : '') : array();
				if (array_key_exists('group_condition', $input)) {
					$rawCondition = Json::toArray($input['group_condition']);
					$condition = FieldConditionEvaluator::normalize($rawCondition, self::fieldsForRubric((int) $rubricId));
					if ($condition) { $settings['form_condition'] = $condition; }
					else { unset($settings['form_condition']); }
				}

				$data['group_settings'] = Json::payload($settings);
			} elseif (isset($input['group_condition']) && trim((string) $input['group_condition']) !== '' && trim((string) $input['group_condition']) !== '{}') {
				throw new \RuntimeException('Сначала примените миграции рубрик для условий групп');
			}

			if ((int) $id > 0) {
				DB::Update(self::groupsTable(), $data, 'Id = %i', (int) $id);
				self::touchFields((int) $group['rubric_id']);
				return (int) $id;
			}

			if (!self::one((int) $rubricId)) {
				throw new \RuntimeException('Рубрика не найдена');
			}

			$data['rubric_id'] = (int) $rubricId;
			$data['group_position'] = self::nextGroupPosition((int) $rubricId);
			DB::Insert(self::groupsTable(), $data);
			$newId = (int) DB::insertId();
			self::touchFields((int) $rubricId);
			return $newId;
		}

		public static function deleteGroup($id)
		{
			$group = DB::query('SELECT * FROM ' . self::groupsTable() . ' WHERE Id = %i LIMIT 1', (int) $id)->getAssoc();
			if (!$group) {
				return false;
			}

			DB::Update(self::fieldsTable(), array('rubric_field_group' => 0), 'rubric_field_group = %i', (int) $id);
			DB::Delete(self::groupsTable(), 'Id = %i', (int) $id);
			self::touchFields((int) $group['rubric_id']);
			return true;
		}

		public static function reorderRubrics(array $ids)
		{
			foreach ($ids as $position => $id) {
				DB::Update(self::rubricsTable(), array('rubric_position' => (int) $position + 1), 'Id = %i', (int) $id);
			}
		}

		public static function reorderFields($rubricId, array $items)
		{
			$validGroups = array(0 => true);
			foreach (self::fieldGroups((int) $rubricId) as $group) {
				$validGroups[(int) $group['id']] = true;
			}

			foreach ($items as $position => $item) {
				if (!is_array($item) || empty($item['id'])) {
					continue;
				}

				$groupId = isset($item['group_id']) ? (int) $item['group_id'] : 0;
				if (!isset($validGroups[$groupId])) {
					throw new \RuntimeException('Группа поля не принадлежит рубрике');
				}

				DB::Update(self::fieldsTable(), array(
					'rubric_field_position' => (int) $position + 1,
					'rubric_field_group' => $groupId,
				), 'Id = %i AND rubric_id = %i', (int) $item['id'], (int) $rubricId);
			}

			self::touchFields((int) $rubricId);
		}

		/** Атомарно сохраняет раскладку, быстрые настройки и состояние размещения полей. */
		public static function saveFieldBuilder($rubricId, array $items, array $drafts, $conditionsEnabled = false)
		{
			$rubricId = (int) $rubricId;
			if (!self::one($rubricId)) {
				throw new \RuntimeException('Рубрика не найдена');
			}

			$fields = self::fieldsForRubric($rubricId);
			$fieldMap = array();
			foreach ($fields as $field) {
				$fieldMap[(int) $field['Id']] = $field;
			}

			$validGroups = array(0 => true);
			foreach (self::fieldGroups($rubricId) as $group) {
				$validGroups[(int) $group['id']] = true;
			}

			$ordered = array();
			foreach ($items as $item) {
				if (!is_array($item) || empty($item['id'])) {
					throw new \RuntimeException('Некорректный элемент раскладки');
				}

				$fieldId = (int) $item['id'];
				$groupId = isset($item['group_id']) ? (int) $item['group_id'] : 0;
				if (!isset($fieldMap[$fieldId])) {
					throw new \RuntimeException('Поле #' . $fieldId . ' не принадлежит рубрике');
				}

				if (isset($ordered[$fieldId])) {
					throw new \RuntimeException('Поле #' . $fieldId . ' повторяется в раскладке');
				}

				if (!isset($validGroups[$groupId])) {
					throw new \RuntimeException('Группа поля не принадлежит рубрике');
				}

				$item['group_id'] = $groupId;
				$ordered[$fieldId] = $item;
			}

			$draftMap = array();
			foreach ($drafts as $draft) {
				if (!is_array($draft) || empty($draft['id'])) {
					continue;
				}

				$fieldId = (int) $draft['id'];
				if (!isset($fieldMap[$fieldId])) {
					throw new \RuntimeException('Настройки поля не принадлежат рубрике');
				}

				$draftMap[$fieldId] = $draft;
			}

			DB::startTransaction();
			try {
				if (self::hasRubricFormConditionsColumn()) {
					DB::Update(
						self::rubricsTable(),
						array('rubric_form_conditions' => $conditionsEnabled ? 1 : 0),
						'Id = %i',
						$rubricId
					);
				} elseif ($conditionsEnabled) {
					throw new \RuntimeException('Примените миграцию рубрик перед включением условий формы');
				}

				$position = 0;
				$saveOrder = array_keys($ordered);
				foreach ($fieldMap as $fieldId => $unused) {
					if (!isset($ordered[$fieldId])) {
						$saveOrder[] = $fieldId;
					}
				}

				foreach ($saveOrder as $fieldId) {
					$field = $fieldMap[$fieldId];
					$isPlaced = isset($ordered[$fieldId]);
					$item = $isPlaced ? $ordered[$fieldId] : array();
					$draft = isset($draftMap[$fieldId]) ? $draftMap[$fieldId] : array();
					$layout = array(
						'placed' => $isPlaced,
					);
					$settings = FieldSettings::effective($field);
					if ($isPlaced) {
						$layout['width'] = isset($item['width']) ? (string) $item['width'] : '';
						$layout['prefix'] = isset($item['prefix']) ? (string) $item['prefix'] : '';
						$layout['suffix'] = isset($item['suffix']) ? (string) $item['suffix'] : '';
					} else {
						$layout['width'] = isset($settings['width']) ? (string) $settings['width'] : '';
						$layout['prefix'] = isset($settings['prefix']) ? (string) $settings['prefix'] : '';
						$layout['suffix'] = isset($settings['suffix']) ? (string) $settings['suffix'] : '';
					}

					if (array_key_exists('settings', $draft) && is_array($draft['settings'])) {
						foreach (FieldSettingsForm::keys((string) $field['rubric_field_type']) as $key) {
							unset($settings[$key]);
						}

						$settings = array_replace(
							$settings,
							FieldSettingsForm::collect((string) $field['rubric_field_type'], $draft['settings'])
						);
					}

					$settings = self::mergeFieldLayout($settings, $layout);
					if (array_key_exists('condition', $draft)) {
						$conditionFields = $fieldMap;
						$conditionFields[$fieldId]['rubric_field_settings'] = FieldSettings::encode($settings);
						$condition = FieldConditionEvaluator::normalize($draft['condition'], array_values($conditionFields), $fieldId);
						if ($condition) { $settings['form_condition'] = $condition; }
						else { unset($settings['form_condition']); }
					}

					$data = array(
						'rubric_field_position' => ++$position,
						'rubric_field_group' => $isPlaced ? (int) $item['group_id'] : (int) $field['rubric_field_group'],
					);
					if (self::hasFieldSettingsColumn()) {
						$data['rubric_field_settings'] = FieldSettings::encode($settings);
					}

					if (!empty($draft)) {
						if (array_key_exists('title', $draft)) {
							$title = trim((string) $draft['title']);
							if ($title === '') {
								throw new \RuntimeException('Укажите наименование поля #' . $fieldId);
							}

							$data['rubric_field_title'] = $title;
						}

						$data['rubric_field_default'] = self::normalizeFieldDefault(
							(string) $field['rubric_field_type'],
							isset($draft['default']) ? (string) $draft['default'] : (string) $field['rubric_field_default']
						);
						$data['rubric_field_description'] = isset($draft['description'])
							? (string) $draft['description']
							: (string) $field['rubric_field_description'];
						$data['rubric_field_search'] = !empty($draft['search']) ? '1' : '0';
						$nativeNumeric = self::fieldTypeIsNumeric((string) $field['rubric_field_type']);
						$data['rubric_field_numeric'] = $nativeNumeric || !empty($draft['numeric']) ? '1' : '0';
					}

					DB::Update(self::fieldsTable(), $data, 'Id = %i AND rubric_id = %i', $fieldId, $rubricId);
				}

				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				throw $e;
			}

			self::touchFields($rubricId);
		}

		public static function reorderGroups($rubricId, array $ids)
		{
			foreach ($ids as $position => $id) {
				DB::Update(self::groupsTable(), array('group_position' => (int) $position + 1), 'Id = %i AND rubric_id = %i', (int) $id, (int) $rubricId);
			}

			self::touchFields((int) $rubricId);
		}

		public static function rubricAliasExists($alias, $excludeId = 0)
		{
			return (bool) DB::query('SELECT Id FROM ' . self::rubricsTable() . ' WHERE rubric_alias = %s AND Id != %i LIMIT 1', (string) $alias, (int) $excludeId)->getValue();
		}

		public static function fieldAliasExists($rubricId, $alias, $excludeId = 0)
		{
			return (bool) DB::query(
				'SELECT Id FROM ' . self::fieldsTable() . ' WHERE rubric_id = %i AND rubric_field_alias = %s AND Id != %i LIMIT 1',
				(int) $rubricId,
				(string) $alias,
				(int) $excludeId
			)->getValue();
		}

		public static function documentCount($rubricId)
		{
			return (int) DB::query('SELECT COUNT(*) FROM ' . self::docsTable() . ' WHERE rubric_id = %i', (int) $rubricId)->getValue();
		}

		public static function templatesForRubric($rubricId)
		{
			$rubric = self::one($rubricId);
			if (!$rubric) {
				return null;
			}

			$rows = DB::query(
				'SELECT t.*, (SELECT COUNT(*) FROM ' . self::docsTable() . ' d WHERE d.rubric_id = t.rubric_id AND d.rubric_tmpl_id = t.id) AS docs_count'
				. ' FROM ' . self::templatesTable() . ' t WHERE t.rubric_id = %i ORDER BY t.id ASC',
				(int) $rubricId
			)->getAll();
			$templates = array();
			foreach ($rows as $row) {
				$templates[] = self::extraTemplateRow($row);
			}

			return array(
				'rubric' => array(
					'Id' => (int) $rubric['Id'],
					'rubric_title' => (string) $rubric['rubric_title'],
					'rubric_template' => (string) $rubric['rubric_template'],
					'rubric_header_template' => (string) $rubric['rubric_header_template'],
					'rubric_og_template' => isset($rubric['rubric_og_template']) ? (string) $rubric['rubric_og_template'] : '',
					'rubric_footer_template' => (string) $rubric['rubric_footer_template'],
					'rubric_teaser_template' => (string) $rubric['rubric_teaser_template'],
				),
				'fields' => self::templateTags($rubricId),
				'templates' => $templates,
			);
		}

		public static function saveMainTemplate($rubricId, array $input)
		{
			$data = array(
				'rubric_template' => isset($input['rubric_template']) ? (string) $input['rubric_template'] : '',
				'rubric_header_template' => isset($input['rubric_header_template']) ? (string) $input['rubric_header_template'] : '',
				'rubric_og_template' => isset($input['rubric_og_template']) ? (string) $input['rubric_og_template'] : '',
				'rubric_footer_template' => isset($input['rubric_footer_template']) ? (string) $input['rubric_footer_template'] : '',
				'rubric_teaser_template' => isset($input['rubric_teaser_template']) ? (string) $input['rubric_teaser_template'] : '',
				'rubric_changed' => time(),
			);
			DB::Update(self::rubricsTable(), $data, 'Id = %i', (int) $rubricId);
			self::clearRubricCache((int) $rubricId);
			return true;
		}

		public static function extraTemplate($id)
		{
			$row = DB::query(
				'SELECT t.*, (SELECT COUNT(*) FROM ' . self::docsTable() . ' d WHERE d.rubric_id = t.rubric_id AND d.rubric_tmpl_id = t.id) AS docs_count'
				. ' FROM ' . self::templatesTable() . ' t WHERE t.id = %i LIMIT 1',
				(int) $id
			)->getAssoc();
			return $row ? self::extraTemplateRow($row) : null;
		}

		public static function saveExtraTemplate($id, $rubricId, array $input, $authorId)
		{
			$data = array(
				'title' => trim(isset($input['title']) ? (string) $input['title'] : ''),
				'template' => isset($input['template']) ? (string) $input['template'] : '',
			);
			if ((int) $id > 0) {
				$current = self::extraTemplate($id);
				if (!$current) {
					throw new \RuntimeException('Шаблон рубрики не найден');
				}

				DB::Update(self::templatesTable(), $data, 'id = %i', (int) $id);
				self::touchTemplate((int) $current['rubric_id']);
				return (int) $id;
			}

			$data['rubric_id'] = (int) $rubricId;
			$data['author_id'] = (int) $authorId > 0 ? (int) $authorId : 1;
			$data['created'] = time();
			DB::Insert(self::templatesTable(), $data);
			$newId = (int) DB::insertId();
			self::touchTemplate((int) $rubricId);
			return $newId;
		}

		public static function deleteExtraTemplate($id)
		{
			$template = self::extraTemplate($id);
			if (!$template) {
				return false;
			}

			if ((int) $template['docs_count'] > 0) {
				throw new \RuntimeException('Шаблон используется документами и не может быть удалён');
			}

			DB::Delete(self::templatesTable(), 'id = %i', (int) $id);
			self::touchTemplate((int) $template['rubric_id']);
			return true;
		}

		public static function templateTags($rubricId)
		{
			$fields = self::fieldsForRubric($rubricId);
			$out = array();
			foreach ($fields as $field) {
				$out[] = array(
					'id' => (int) $field['Id'],
					'title' => (string) $field['rubric_field_title'],
					'alias' => (string) $field['rubric_field_alias'],
					'tag' => (string) $field['tag'],
					'type_label' => (string) $field['type_label'],
				);
			}

			return $out;
		}

		public static function documentPicker($q = '', $rubricId = 0, $limit = 20)
		{
			return (new \App\Content\Documents\DocumentPickerRepository())->search($q, $rubricId, $limit);
		}

		protected static function rubricInput(array $input)
		{
			$purpose = isset($input['rubric_purpose']) ? trim((string) $input['rubric_purpose']) : 'content';
			if (!in_array($purpose, array('content', 'directory'), true)) {
				$purpose = 'content';
			}

			$data = array(
				'rubric_title' => trim(isset($input['rubric_title']) ? (string) $input['rubric_title'] : ''),
				'rubric_alias' => trim(isset($input['rubric_alias']) ? (string) $input['rubric_alias'] : ''),
				'rubric_template_id' => max(1, (int) (isset($input['rubric_template_id']) ? $input['rubric_template_id'] : 1)),
				'rubric_docs_active' => !empty($input['rubric_docs_active']) ? 1 : 0,
				'rubric_meta_gen' => !empty($input['rubric_meta_gen']) ? '1' : '0',
				'rubric_alias_history' => !empty($input['rubric_alias_history']) ? '1' : '0',
				'rubric_description' => isset($input['rubric_description']) ? (string) $input['rubric_description'] : '',
				'rubric_code_start' => isset($input['rubric_code_start']) ? (string) $input['rubric_code_start'] : '',
				'rubric_code_end' => isset($input['rubric_code_end']) ? (string) $input['rubric_code_end'] : '',
				'rubric_start_code' => isset($input['rubric_start_code']) ? (string) $input['rubric_start_code'] : '',
			);
			if (self::hasRubricPurposeColumn()) {
				$data['rubric_purpose'] = $purpose;
			}

			return $data;
		}

		protected static function fieldInput(array $input)
		{
			$type = trim(isset($input['rubric_field_type']) ? (string) $input['rubric_field_type'] : '');
			$normalizedDefault = self::normalizeFieldDefault($type, isset($input['rubric_field_default']) ? (string) $input['rubric_field_default'] : '');
			$data = array(
				'rubric_field_group' => isset($input['rubric_field_group']) ? (int) $input['rubric_field_group'] : 0,
				'rubric_field_title' => trim(isset($input['rubric_field_title']) ? (string) $input['rubric_field_title'] : ''),
				'rubric_field_alias' => trim(isset($input['rubric_field_alias']) ? (string) $input['rubric_field_alias'] : ''),
				'rubric_field_type' => $type,
				'rubric_field_numeric' => self::fieldTypeIsNumeric($type) || !empty($input['rubric_field_numeric']) ? '1' : '0',
				'rubric_field_search' => !empty($input['rubric_field_search']) ? '1' : '0',
				'rubric_field_default' => $normalizedDefault,
				'rubric_field_template' => isset($input['rubric_field_template']) ? (string) $input['rubric_field_template'] : '',
				'rubric_field_template_request' => isset($input['rubric_field_template_request']) ? (string) $input['rubric_field_template_request'] : '',
				'rubric_field_description' => isset($input['rubric_field_description']) ? (string) $input['rubric_field_description'] : '',
			);
			// JSON-настройки типа (миграция 002). Пишем только если колонка есть —
			// иначе сохранение поля продолжает работать на не-мигрированной БД.
			if (self::hasFieldSettingsColumn()) {
				if (array_key_exists('rubric_field_settings_canonical', $input) && is_array($input['rubric_field_settings_canonical'])) {
					$settings = $input['rubric_field_settings_canonical'];
				} elseif (array_key_exists('rubric_field_settings', $input)) {
					$settings = FieldSettingsForm::collect($type, $input['rubric_field_settings']);
				} else {
					$settings = FieldSettings::fromLegacy($type, $normalizedDefault);
				}

				$settings = self::mergeFieldLayout($settings, isset($input['rubric_field_layout']) && is_array($input['rubric_field_layout']) ? $input['rubric_field_layout'] : array());
				$data['rubric_field_settings'] = FieldSettings::encode($settings);
			}

			return $data;
		}

		protected static function fieldTypeIsNumeric($type)
		{
			$fieldType = FieldRegistry::get((string) $type);
			return $fieldType ? $fieldType->isNumeric() : false;
		}

		protected static function normalizeFieldDefault($type, $value)
		{
			return FieldAdminEditors::normalizeDefault($type, $value);
		}

		/** Persist type configuration that older AVE versions kept in default. */
		public static function migrateFieldSettings()
		{
			static $done = false;
			if ($done || !self::hasFieldSettingsColumn()) {
				return 0;
			}

			$done = true;
			$updated = 0;
			$rows = DB::query(
				'SELECT Id, rubric_field_type, rubric_field_default, rubric_field_settings FROM ' . self::fieldsTable()
			)->getAll() ?: array();
			foreach ($rows as $row) {
				$current = Json::toArray((string) $row['rubric_field_settings']);
				$effective = FieldSettings::effective($row);
				if ($effective === $current) {
					continue;
				}

				DB::Update(
					self::fieldsTable(),
					array('rubric_field_settings' => FieldSettings::encode($effective)),
					'Id = %i',
					(int) $row['Id']
				);
				$updated++;
			}

			return $updated;
		}

		protected static function assertGroupBelongsToRubric($groupId, $rubricId)
		{
			if ((int) $groupId === 0) {
				return;
			}

			$exists = DB::query(
				'SELECT Id FROM ' . self::groupsTable() . ' WHERE Id = %i AND rubric_id = %i LIMIT 1',
				(int) $groupId,
				(int) $rubricId
			)->getValue();
			if (!$exists) {
				throw new \RuntimeException('Выбранная группа не принадлежит рубрике');
			}
		}

		protected static function fieldHasValues($fieldId)
		{
			$textTable = self::docFieldsTextTable();
			$textJoin = self::tableExists($textTable)
				? ' LEFT JOIN ' . $textTable . ' t ON t.document_id=d.document_id AND t.rubric_field_id=d.rubric_field_id'
				: '';
			$textWhere = self::tableExists($textTable) ? " OR COALESCE(t.field_value, '') != ''" : '';
			return (bool) DB::query(
				'SELECT d.document_id FROM ' . self::docFieldsTable() . ' d' . $textJoin
				. " WHERE d.rubric_field_id = %i AND (COALESCE(d.field_value, '') != '' OR COALESCE(d.field_number_value, 0) != 0"
				. $textWhere . ') LIMIT 1',
				(int) $fieldId
			)->getValue();
		}

		public static function fieldHasStoredValues($fieldId)
		{
			return self::fieldHasValues((int) $fieldId);
		}

		protected static function fieldDependencies($fieldId, $includeLocalSchema = true)
		{
			$field = DB::query(
				'SELECT Id,rubric_id,rubric_field_alias,rubric_field_title FROM ' . self::fieldsTable()
					. ' WHERE Id=%i LIMIT 1',
				(int) $fieldId
			)->getAssoc();
			if (!$field) { return array(); }

			$checks = array(
				array(ContentTables::table('request_conditions'), 'condition_field_id', 'условия запросов'),
				array(CatalogTables::table('module_catalog_settings'), 'field_id', 'настройки каталога'),
				array(CatalogTables::table('module_catalog_items'), 'field_id', 'разделы каталога'),
				array(CatalogTables::table('catalog_variant_attributes'), 'field_id', 'варианты товаров'),
				array(ContentTables::table('feed_definitions'), 'catalog_field_id', 'товарные фиды'),
			);
			$out = array();
			foreach ($checks as $check) {
				if (self::tableExists($check[0]) && DB::query(
					'SELECT 1 FROM ' . $check[0] . ' WHERE ' . $check[1] . ' = %i LIMIT 1',
					(int) $fieldId
				)->getValue()) {
					$out[] = $check[2];
				}
			}

			if (self::tableExists(CatalogTables::table('module_catalog_settings'))) {
				$columns = array(
					'product_title_field_id', 'product_article_field_id', 'product_price_field_id',
					'product_old_price_field_id', 'product_stock_field_id', 'product_images_field_id'
				);
				foreach ($columns as $column) {
					if (DB::query('SELECT 1 FROM ' . CatalogTables::table('module_catalog_settings') . ' WHERE ' . $column . ' = %i LIMIT 1', (int) $fieldId)->getValue()) {
						$out[] = 'товарное представление каталога';
						break;
					}
				}
			}

			if (self::tableExists(CatalogTables::table('module_catalog_items')) && DB::query(
				'SELECT 1 FROM ' . CatalogTables::table('module_catalog_items')
				. ' WHERE FIND_IN_SET(%s, REPLACE(fields_use, %s, %s)) > 0'
				. ' OR FIND_IN_SET(%s, REPLACE(filters_use, %s, %s)) > 0 LIMIT 1',
				(string) (int) $fieldId,
				' ',
				'',
				(string) (int) $fieldId,
				' ',
				''
			)->getValue()) {
				$out[] = 'поля или фильтры разделов каталога';
			}

			if (self::feedUsesField((int) $fieldId)) {
				$out[] = 'параметры товарных фидов';
			}

			$out = array_merge($out, self::requestFieldDependencies($field));
			if ($includeLocalSchema) {
				$out = array_merge($out, self::localFieldDependencies($field));
			}

			return array_values(array_unique($out));
		}

		protected static function requestFieldDependencies(array $field)
		{
			$table = ContentTables::table('request');
			if (!self::tableExists($table)) { return array(); }
			$rows = DB::query(
				'SELECT Id,request_order_by_nat,request_sort_rules,request_template_item,request_template_main,request_result_contract'
					. ' FROM ' . $table . ' WHERE rubric_id=%i',
				(int) $field['rubric_id']
			)->getAll() ?: array();
			$reference = self::fieldReference($field);
			$out = array();
			foreach ($rows as $request) {
				foreach (\App\Frontend\RequestSort::rulesForRequest($request) as $rule) {
					if ((string) $rule['source'] === 'field' && (int) $rule['key'] === (int) $field['Id']) {
						$out[] = 'сортировка запросов';
						break;
					}
				}

				if (RubricSchemaImpact::templateReferences(
					(string) $request['request_template_item'] . "\n" . (string) $request['request_template_main'],
					array($reference)
				)) {
					$out[] = 'шаблоны запросов';
				}

				$contract = \App\Content\Requests\RequestResultContract::decode((string) $request['request_result_contract']);
				if (in_array((int) $field['Id'], array_map('intval', $contract['fields']), true)) {
					$out[] = 'контракты результатов запросов';
				}
			}

			return array_values(array_unique($out));
		}

		protected static function localFieldDependencies(array $field)
		{
			$rubricId = (int) $field['rubric_id'];
			$fieldId = (int) $field['Id'];
			$alias = strtolower(trim((string) $field['rubric_field_alias']));
			$out = array();
			foreach (self::fieldsForRubric($rubricId) as $candidate) {
				if ((int) $candidate['Id'] !== $fieldId
					&& self::conditionUsesField(FieldConditionEvaluator::condition($candidate), $fieldId, $alias)) {
					$out[] = 'условия видимости полей';
				}

				if (self::conditionUsesField(FieldConditionEvaluator::groupCondition($candidate), $fieldId, $alias)) {
					$out[] = 'условия видимости групп';
				}
			}

			$reference = self::fieldReference($field);
			$rubric = DB::query(
				'SELECT rubric_template,rubric_teaser_template,rubric_header_template,rubric_og_template,rubric_footer_template'
					. ' FROM ' . self::rubricsTable() . ' WHERE Id=%i LIMIT 1',
				$rubricId
			)->getAssoc();
			if ($rubric && RubricSchemaImpact::templateReferences(implode("\n", $rubric), array($reference))) {
				$out[] = 'шаблоны рубрики';
			}

			if (self::tableExists(self::templatesTable())) {
				foreach (DB::query(
					'SELECT template FROM ' . self::templatesTable() . ' WHERE rubric_id=%i',
					$rubricId
				)->getAll() ?: array() as $template) {
					if (RubricSchemaImpact::templateReferences((string) $template['template'], array($reference))) {
						$out[] = 'дополнительные шаблоны рубрики';
						break;
					}
				}
			}

			return array_values(array_unique($out));
		}

		protected static function conditionUsesField(array $condition, $fieldId, $alias)
		{
			$walk = function (array $node) use (&$walk, $fieldId, $alias) {
				if (isset($node['items']) && is_array($node['items'])) {
					foreach ($node['items'] as $item) {
						if (is_array($item) && $walk($item)) { return true; }
					}

					return false;
				}

				$reference = strtolower(trim(isset($node['field']) ? (string) $node['field'] : ''));
				return $reference === 'id:' . (int) $fieldId
					|| $reference === (string) (int) $fieldId
					|| ($alias !== '' && ($reference === 'alias:' . $alias || $reference === $alias));
			};

			return !empty($condition['tree']) && $walk($condition['tree']);
		}

		protected static function fieldReference(array $field)
		{
			return array(
				'id' => (int) $field['Id'],
				'title' => isset($field['rubric_field_title']) ? (string) $field['rubric_field_title'] : '',
				'alias' => isset($field['rubric_field_alias']) ? (string) $field['rubric_field_alias'] : '',
			);
		}

		protected static function rubricDependencies($rubricId)
		{
			$checks = array(
				array(ContentTables::table('request'), 'rubric_id', 'запросы'),
				array(CatalogTables::table('module_catalog_settings'), 'rubric_id', 'настройки каталога'),
				array(CatalogTables::table('module_catalog_items'), 'rubric_id', 'разделы каталога'),
				array(ContentTables::table('feed_definitions'), 'rubric_id', 'товарные фиды'),
			);
			$out = array();
			foreach ($checks as $check) {
				if (self::tableExists($check[0]) && DB::query(
					'SELECT 1 FROM ' . $check[0] . ' WHERE ' . $check[1] . ' = %i LIMIT 1',
					(int) $rubricId
				)->getValue()) {
					$out[] = $check[2];
				}
			}

			return array_values(array_unique($out));
		}

		protected static function feedUsesField($fieldId)
		{
			$table = ContentTables::table('feed_definitions');
			if (!self::tableExists($table)) {
				return false;
			}

			$rows = DB::query('SELECT mappings_json, params_json, conditions_json FROM ' . $table)->getAll() ?: array();
			foreach ($rows as $row) {
				foreach (array('mappings_json', 'params_json', 'conditions_json') as $column) {
					$data = Json::toArray((string) $row[$column]);
					if (is_array($data) && self::structuredDataUsesField($data, (int) $fieldId)) {
						return true;
					}
				}
			}

			return false;
		}

		protected static function structuredDataUsesField(array $data, $fieldId)
		{
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					if (self::structuredDataUsesField($value, (int) $fieldId)) {
						return true;
					}

					continue;
				}

				if ((in_array((string) $key, array('field_id', 'fieldId'), true) && (int) $value === (int) $fieldId)
					|| (string) $value === 'field:' . (int) $fieldId) {
					return true;
				}
			}

			return false;
		}

		protected static function deleteDerivedFieldData($fieldId)
		{
			foreach (array(
				CatalogTables::table('catalog_filter_index'),
				CatalogTables::table('catalog_variant_attributes')
			) as $table) {
				if (self::tableExists($table)) {
					DB::Delete($table, 'field_id = %i', (int) $fieldId);
				}
			}
		}

		/**
		 * Домешивает оформление поля в документе (ширина/обязательность/аффиксы)
		 * в JSON-настройки. required хранится как флаг — его подхватывает
		 * App\Content\Fields\FieldValidator; width/prefix/suffix читает редактор
		 * документа. Пустые значения удаляются, чтобы не засорять настройки.
		 */
		protected static function mergeFieldLayout(array $settings, array $layout)
		{
			$settings['placed'] = !array_key_exists('placed', $layout) || !empty($layout['placed']);

			$width = isset($layout['width']) ? (string) $layout['width'] : '';
			if (in_array($width, array('full', 'half', 'third', 'quarter'), true)) {
				$settings['width'] = $width;
			} else {
				unset($settings['width']);
			}

			// required не трогаем: он приходит из валидации типа (FieldSettingsForm),
			// здесь только раскладка. Иначе перетёрли бы флаг обязательности.

			foreach (array('prefix', 'suffix') as $key) {
				$val = isset($layout[$key]) ? trim((string) $layout[$key]) : '';
				if ($val !== '') {
					$settings[$key] = mb_substr($val, 0, 12);
				} else {
					unset($settings[$key]);
				}
			}

			return $settings;
		}

		protected static function rubricRow(array $row)
		{
			$row['Id'] = (int) $row['Id'];
			$row['id'] = (int) $row['Id'];
			$row['fields_count'] = isset($row['fields_count']) ? (int) $row['fields_count'] : 0;
			$row['docs_count'] = isset($row['docs_count']) ? (int) $row['docs_count'] : 0;
			$row['templates_count'] = isset($row['templates_count']) ? (int) $row['templates_count'] : 0;
			$row['site_template_title'] = isset($row['site_template_title']) ? trim((string) $row['site_template_title']) : '';
			$row['form_conditions_enabled'] = !empty($row['rubric_form_conditions']);
			$row['rubric_purpose'] = isset($row['rubric_purpose']) && (string) $row['rubric_purpose'] === 'directory'
				? 'directory'
				: 'content';
			$row['purpose_label'] = $row['rubric_purpose'] === 'directory' ? 'справочник' : 'контент';
			$row['can_delete'] = $row['Id'] > 1 && $row['docs_count'] === 0;
			$row['status_label'] = (string) $row['rubric_docs_active'] === '1' ? 'активна' : 'выкл';
			return $row;
		}

		protected static function fieldRow(array $row, array $typeNames)
		{
			$row['Id'] = (int) $row['Id'];
			$row['id'] = (int) $row['Id'];
			$row['rubric_id'] = (int) $row['rubric_id'];
			$row['rubric_field_group'] = (int) $row['rubric_field_group'];
			$row['type_label'] = isset($typeNames[$row['rubric_field_type']]) ? $typeNames[$row['rubric_field_type']] : $row['rubric_field_type'];
			$row['tag'] = '[tag:fld:' . ($row['rubric_field_alias'] !== '' ? $row['rubric_field_alias'] : $row['Id']) . ']';
			$settings = FieldSettings::effective($row);
			$row['layout'] = array(
				'placed' => !array_key_exists('placed', $settings) || !empty($settings['placed']),
				'width' => isset($settings['width']) ? (string) $settings['width'] : '',
				'required' => !empty($settings['required']),
				'prefix' => isset($settings['prefix']) ? (string) $settings['prefix'] : '',
				'suffix' => isset($settings['suffix']) ? (string) $settings['suffix'] : '',
			);
			$row['condition'] = FieldConditionEvaluator::condition($row);
			$row['condition_options'] = self::conditionOptions($settings);
			$row['condition_value_action'] = FieldConditionEvaluator::supportsValueAction($row);
			$row['condition_action_options'] = $row['condition_options'];
			if ((string) $row['rubric_field_type'] === 'checkbox') {
				$row['condition_action_options'] = array(
					array('value' => '1', 'label' => 'Да / включено'),
					array('value' => '0', 'label' => 'Нет / выключено'),
				);
			}

			return $row;
		}

		protected static function conditionOptions(array $settings)
		{
			$raw = isset($settings['options']) && is_array($settings['options']) ? $settings['options'] : array();
			$options = array();
			foreach ($raw as $key => $item) {
				if (is_array($item)) {
					$value = isset($item['value']) ? (string) $item['value'] : (isset($item['key']) ? (string) $item['key'] : (string) $key);
					$label = isset($item['label']) ? (string) $item['label'] : (isset($item['title']) ? (string) $item['title'] : $value);
				} else {
					$label = trim((string) $item);
					$value = is_string($key) ? (string) $key : $label;
				}

				if ($value === '' || $label === '') { continue; }
				$options[] = array('value' => $value, 'label' => $label);
			}

			return $options;
		}

		protected static function groupRow(array $row)
		{
			$condition = FieldConditionEvaluator::groupCondition($row);
			return array(
				'id' => (int) $row['Id'],
				'rubric_id' => (int) $row['rubric_id'],
				'title' => (string) $row['group_title'],
				'description' => (string) $row['group_description'],
				'position' => (int) $row['group_position'],
				'fields_count' => isset($row['fields_count']) ? (int) $row['fields_count'] : 0,
				'condition' => $condition,
			);
		}

		protected static function extraTemplateRow(array $row)
		{
			$row['id'] = (int) $row['id'];
			$row['rubric_id'] = (int) $row['rubric_id'];
			$row['author_id'] = (int) $row['author_id'];
			$row['created'] = (int) $row['created'];
			$row['docs_count'] = isset($row['docs_count']) ? (int) $row['docs_count'] : 0;
			$row['created_label'] = $row['created'] > 0 ? date('d.m.Y H:i', $row['created']) : 'не задано';
			$row['size_label'] = self::formatBytes(strlen((string) $row['template']));
			$row['can_delete'] = $row['docs_count'] === 0;
			return $row;
		}

		protected static function seedPermissions($rubricId)
		{
			$groups = DB::query('SELECT user_group FROM ' . self::userGroupsTable())->getAll();
			foreach ($groups as $group) {
				$groupId = (int) $group['user_group'];
				DB::Insert(self::permissionsTable(), array(
					'rubric_id' => (int) $rubricId,
					'user_group_id' => $groupId,
					'rubric_permission' => $groupId === 1 ? 'alles|docread|new|newnow|editown|editall|delrev' : 'docread',
				));
			}
		}

		protected static function seedDocumentFields($rubricId, $fieldId)
		{
			$docs = DB::query('SELECT Id FROM ' . self::docsTable() . ' WHERE rubric_id = %i', (int) $rubricId)->getAll();
			foreach ($docs as $doc) {
				DB::Insert(self::docFieldsTable(), array(
					'rubric_field_id' => (int) $fieldId,
					'document_id' => (int) $doc['Id'],
				));
			}
		}

		protected static function touchFields($rubricId)
		{
			DB::Update(self::rubricsTable(), array('rubric_changed' => time(), 'rubric_changed_fields' => time()), 'Id = %i', (int) $rubricId);
			self::clearRubricCache((int) $rubricId);
		}

		protected static function touchTemplate($rubricId)
		{
			DB::Update(self::rubricsTable(), array('rubric_changed' => time()), 'Id = %i', (int) $rubricId);
			self::clearRubricCache((int) $rubricId);
		}

		protected static function formatBytes($bytes)
		{
			$bytes = (int) $bytes;
			if ($bytes >= 1048576) {
				return round($bytes / 1048576, 1) . ' MB';
			}

			if ($bytes >= 1024) {
				return round($bytes / 1024, 1) . ' KB';
			}

			return $bytes . ' B';
		}

		protected static function clearRubricCache($rubricId)
		{
			FileCacheInvalidator::rubric($rubricId);
		}

		protected static function nextPosition($table, $field)
		{
			return (int) DB::query('SELECT COALESCE(MAX(' . $field . '), 0) + 1 FROM ' . $table)->getValue();
		}

		protected static function nextFieldPosition($rubricId, $groupId)
		{
			return (int) DB::query(
				'SELECT COALESCE(MAX(rubric_field_position), 0) + 1 FROM ' . self::fieldsTable() . ' WHERE rubric_id = %i AND rubric_field_group = %i',
				(int) $rubricId,
				(int) $groupId
			)->getValue();
		}

		protected static function nextGroupPosition($rubricId)
		{
			return (int) DB::query('SELECT COALESCE(MAX(group_position), 0) + 1 FROM ' . self::groupsTable() . ' WHERE rubric_id = %i', (int) $rubricId)->getValue();
		}

		protected static function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}

		/** Есть ли колонка rubric_field_settings (миграция 002). Кешируется на запрос. */
		protected static function hasFieldSettingsColumn()
		{
			static $has = null;
			if ($has === null) {
				$has = (bool) DB::query('SHOW COLUMNS FROM ' . self::fieldsTable() . ' LIKE %s', 'rubric_field_settings')->getValue();
			}

			return $has;
		}

		protected static function hasRubricFormConditionsColumn()
		{
			static $has = null;
			if ($has === null) {
				$has = (bool) DB::query('SHOW COLUMNS FROM ' . self::rubricsTable() . ' LIKE %s', 'rubric_form_conditions')->getValue();
			}

			return $has;
		}

		public static function hasRubricPurposeColumn()
		{
			static $has = null;
			if ($has === null) {
				$has = (bool) DB::query('SHOW COLUMNS FROM ' . self::rubricsTable() . ' LIKE %s', 'rubric_purpose')->getValue();
			}

			return $has;
		}

		protected static function hasGroupSettingsColumn()
		{
			static $has = null;
			if ($has === null) {
				$has = (bool) DB::query('SHOW COLUMNS FROM ' . self::groupsTable() . ' LIKE %s', 'group_settings')->getValue();
			}

			return $has;
		}
	}
