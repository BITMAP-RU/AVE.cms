<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/RubricRevisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\Fields\FieldRegistry;
	use App\Helpers\Json;
	use DB;

	class RubricRevisions
	{
		public static function table()
		{
			return ContentTables::table('rubric_schema_revisions');
		}

		public static function available()
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', self::table())->getValue();
		}

		public static function labels()
		{
			return array(
				'baseline' => array('label' => 'Исходная схема', 'badge' => 'badge-gray'),
				'create' => array('label' => 'Создание', 'badge' => 'badge-green'),
				'field_create' => array('label' => 'Новое поле', 'badge' => 'badge-green'),
				'field_update' => array('label' => 'Поле изменено', 'badge' => 'badge-blue'),
				'field_delete' => array('label' => 'Поле удалено', 'badge' => 'badge-red'),
				'group_create' => array('label' => 'Новая группа', 'badge' => 'badge-green'),
				'group_update' => array('label' => 'Группа изменена', 'badge' => 'badge-blue'),
				'group_delete' => array('label' => 'Группа удалена', 'badge' => 'badge-red'),
				'layout' => array('label' => 'Конструктор', 'badge' => 'badge-blue'),
				'reorder' => array('label' => 'Порядок', 'badge' => 'badge-cyan'),
				'field_set' => array('label' => 'Набор полей', 'badge' => 'badge-violet'),
				'restore' => array('label' => 'Восстановление', 'badge' => 'badge-cyan'),
				'restore_backup' => array('label' => 'Перед восстановлением', 'badge' => 'badge-amber'),
			);
		}

		public static function listForRubric($rubricId, $limit = 80)
		{
			if (!self::available()) { return array(); }
			$rows = DB::query(
				'SELECT * FROM ' . self::table() . ' WHERE rubric_id=%i ORDER BY created_at DESC,id DESC LIMIT %i',
				(int) $rubricId,
				max(1, min(200, (int) $limit))
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) { $out[] = self::format($row, false); }

			return $out;
		}

		public static function one($id)
		{
			if (!self::available()) { return null; }
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::format($row, true) : null;
		}

		public static function capture($rubricId, $action, $authorId = 0, $comment = '', array $snapshot = null, $sourceRevisionId = 0)
		{
			if (!self::available()) { return 0; }
			$rubricId = (int) $rubricId;
			$snapshot = $snapshot === null ? Model::schemaSnapshot($rubricId) : Model::normalizeSchemaSnapshot($snapshot);
			if (!$snapshot) { return 0; }

			$json = Json::encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$hash = sha1((string) $json);
			$last = DB::query(
				'SELECT snapshot_hash FROM ' . self::table() . ' WHERE rubric_id=%i ORDER BY created_at DESC,id DESC LIMIT 1',
				$rubricId
			)->getValue();
			if ($last && hash_equals((string) $last, $hash)) { return 0; }

			DB::Insert(self::table(), array(
				'rubric_id' => $rubricId,
				'action' => (string) $action,
				'snapshot_hash' => $hash,
				'snapshot_json' => $json,
				'comment' => mb_substr(trim((string) $comment), 0, 500, 'UTF-8'),
				'author_id' => (int) $authorId,
				'author_name' => self::authorName((int) $authorId),
				'source_revision_id' => (int) $sourceRevisionId,
				'created_at' => time(),
			));

			return (int) DB::insertId();
		}

		public static function previewRestore($revisionId)
		{
			$revision = self::one((int) $revisionId);
			if (!$revision || empty($revision['snapshot'])) {
				throw new \RuntimeException('Ревизия не найдена');
			}

			$current = Model::schemaSnapshot((int) $revision['rubric_id']);
			if (!$current) { throw new \RuntimeException('Рубрика не найдена'); }
			$impact = self::compareSnapshots($current, $revision['snapshot']);
			$impact['documents_total'] = Model::documentCount((int) $revision['rubric_id']);
			$impact['documents_with_values'] = self::documentsWithValues(
				(int) $revision['rubric_id'],
				$impact['affected_field_ids']
			);
			$impact['dependencies'] = RubricSchemaImpact::analyze(
				(int) $revision['rubric_id'],
				self::fieldReferences($current, $revision['snapshot'], $impact['affected_field_ids'])
			);
			$impact['fingerprint'] = self::restoreFingerprint(
				$revision,
				$current,
				(string) $impact['dependencies']['fingerprint']
			);
			unset($impact['affected_field_ids']);

			return $impact;
		}

		public static function restore($revisionId, $fingerprint, $authorId = 0)
		{
			$revision = self::one((int) $revisionId);
			if (!$revision || empty($revision['snapshot'])) {
				throw new \RuntimeException('Ревизия не найдена');
			}

			$current = Model::schemaSnapshot((int) $revision['rubric_id']);
			$impact = self::compareSnapshots($current, $revision['snapshot']);
			$dependencies = RubricSchemaImpact::analyze(
				(int) $revision['rubric_id'],
				self::fieldReferences($current, $revision['snapshot'], $impact['affected_field_ids'])
			);
			$expected = self::restoreFingerprint($revision, $current, (string) $dependencies['fingerprint']);
			if ($fingerprint === '' || !hash_equals($expected, (string) $fingerprint)) {
				throw new \RuntimeException('Схема изменилась после предпросмотра. Проверьте ревизию ещё раз.');
			}

			if (!empty($impact['blockers'])) {
				throw new \RuntimeException('Восстановление заблокировано: ' . implode('; ', $impact['blockers']));
			}

			DB::startTransaction();
			try {
				self::capture(
					(int) $revision['rubric_id'],
					'restore_backup',
					(int) $authorId,
					'Снимок перед восстановлением ревизии #' . (int) $revisionId,
					$current,
					(int) $revisionId
				);
				$result = Model::applySchemaSnapshot((int) $revision['rubric_id'], $revision['snapshot']);
				self::capture(
					(int) $revision['rubric_id'],
					'restore',
					(int) $authorId,
					'Восстановлено из ревизии #' . (int) $revisionId,
					null,
					(int) $revisionId
				);
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				throw $e;
			}

			return $result;
		}

		public static function delete($revisionId)
		{
			$revision = self::one((int) $revisionId);
			if (!$revision) { return 0; }
			DB::Delete(self::table(), 'id=%i', (int) $revisionId);

			return (int) $revision['rubric_id'];
		}

		public static function deleteForRubric($rubricId)
		{
			if (!self::available()) { return 0; }
			$count = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE rubric_id=%i', (int) $rubricId)->getValue();
			DB::Delete(self::table(), 'rubric_id=%i', (int) $rubricId);

			return $count;
		}

		public static function compareSnapshots(array $current, array $target)
		{
			$current = Model::normalizeSchemaSnapshot($current);
			$target = Model::normalizeSchemaSnapshot($target);
			$currentGroups = self::mapById($current['groups']);
			$targetGroups = self::mapById($target['groups']);
			$currentFields = self::mapById($current['fields']);
			$targetFields = self::mapById($target['fields']);
			$groups = self::describeGroupDiff(
				self::entityDiff($currentGroups, $targetGroups, 'group_title', 'Группа'),
				$currentGroups,
				$targetGroups
			);
			$fields = self::describeFieldDiff(
				self::entityDiff($currentFields, $targetFields, 'rubric_field_title', 'Поле'),
				$currentFields,
				$targetFields,
				$currentGroups,
				$targetGroups
			);
			$blockers = self::restoreBlockers(
				$currentFields,
				$targetFields,
				isset($current['rubric']['id']) ? (int) $current['rubric']['id'] : 0
			);
			$affected = array_values(array_unique(array_merge($fields['restore_ids'], $fields['changed_ids'])));

			return array(
				'conditions_before' => !empty($current['rubric']['form_conditions_enabled']),
				'conditions_after' => !empty($target['rubric']['form_conditions_enabled']),
				'groups' => $groups,
				'fields' => $fields,
				'summary' => array(
					'groups_restore' => count($groups['restore']),
					'groups_change' => count($groups['change']),
					'groups_preserve' => count($groups['preserve']),
					'fields_restore' => count($fields['restore']),
					'fields_change' => count($fields['change']),
					'fields_preserve' => count($fields['preserve']),
				),
				'blockers' => $blockers,
				'can_restore' => empty($blockers),
				'affected_field_ids' => $affected,
			);
		}

		protected static function entityDiff(array $current, array $target, $titleKey, $fallback)
		{
			$out = array(
				'restore' => array(), 'change' => array(), 'preserve' => array(),
				'restore_ids' => array(), 'changed_ids' => array(),
			);
			foreach ($target as $id => $item) {
				$title = trim((string) (isset($item[$titleKey]) ? $item[$titleKey] : ''));
				$entry = array('id' => (int) $id, 'title' => $title !== '' ? $title : $fallback . ' #' . (int) $id);
				if (!isset($current[$id])) {
					$out['restore'][] = $entry;
					$out['restore_ids'][] = (int) $id;
					continue;
				}

				$changed = self::changedKeys($current[$id], $item);
				if ($changed) {
					$entry['changed_keys'] = $changed;
					$out['change'][] = $entry;
					$out['changed_ids'][] = (int) $id;
				}
			}

			foreach ($current as $id => $item) {
				if (isset($target[$id])) { continue; }
				$title = trim((string) (isset($item[$titleKey]) ? $item[$titleKey] : ''));
				$out['preserve'][] = array(
					'id' => (int) $id,
					'title' => $title !== '' ? $title : $fallback . ' #' . (int) $id,
				);
			}

			return $out;
		}

		protected static function changedKeys(array $current, array $target)
		{
			$ignore = array('rubric_id');
			$keys = array_unique(array_merge(array_keys($current), array_keys($target)));
			$out = array();
			foreach ($keys as $key) {
				if (in_array($key, $ignore, true)) { continue; }
				$before = isset($current[$key]) ? $current[$key] : null;
				$after = isset($target[$key]) ? $target[$key] : null;
				if (in_array($key, array('rubric_field_settings', 'group_settings'), true)
					&& self::sameValue(Json::toArray($before), Json::toArray($after))) { continue; }
				if ((string) $before !== (string) $after) { $out[] = (string) $key; }
			}

			return $out;
		}

		protected static function describeGroupDiff(array $diff, array $current, array $target)
		{
			foreach ($diff['change'] as &$entry) {
				$id = (int) $entry['id'];
				$entry['changes'] = self::groupChanges($current[$id], $target[$id]);
			}

			unset($entry);
			return $diff;
		}

		protected static function describeFieldDiff(array $diff, array $current, array $target, array $currentGroups, array $targetGroups)
		{
			foreach (array('restore', 'change', 'preserve') as $section) {
				foreach ($diff[$section] as &$entry) {
					$id = (int) $entry['id'];
					$field = isset($target[$id]) ? $target[$id] : $current[$id];
					$entry['meta'] = self::fieldTypeLabel(isset($field['rubric_field_type']) ? $field['rubric_field_type'] : '');
					$alias = trim((string) (isset($field['rubric_field_alias']) ? $field['rubric_field_alias'] : ''));
					if ($alias !== '') { $entry['meta'] .= ' · ' . $alias; }
					if ($section === 'change') {
						$entry['changes'] = self::fieldChanges($current[$id], $target[$id], $currentGroups, $targetGroups);
					}
				}

				unset($entry);
			}

			return $diff;
		}

		protected static function groupChanges(array $current, array $target)
		{
			$changes = array();
			self::addChange($changes, 'Название', $current['group_title'], $target['group_title']);
			self::addChange($changes, 'Описание', $current['group_description'], $target['group_description']);
			self::addChange($changes, 'Порядок', $current['group_position'], $target['group_position'], 'number');
			$before = Json::toArray(isset($current['group_settings']) ? $current['group_settings'] : '');
			$after = Json::toArray(isset($target['group_settings']) ? $target['group_settings'] : '');
			self::addSettingsChanges($changes, $before, $after, '', '');

			return $changes;
		}

		protected static function fieldChanges(array $current, array $target, array $currentGroups, array $targetGroups)
		{
			$changes = array();
			self::addChange($changes, 'Название', $current['rubric_field_title'], $target['rubric_field_title']);
			self::addChange($changes, 'Системное имя', $current['rubric_field_alias'], $target['rubric_field_alias']);
			self::addChange(
				$changes,
				'Тип поля',
				self::fieldTypeLabel($current['rubric_field_type']),
				self::fieldTypeLabel($target['rubric_field_type'])
			);
			self::addChange(
				$changes,
				'Группа',
				self::groupLabel((int) $current['rubric_field_group'], $currentGroups),
				self::groupLabel((int) $target['rubric_field_group'], $targetGroups)
			);
			self::addChange($changes, 'Порядок', $current['rubric_field_position'], $target['rubric_field_position'], 'number');
			self::addChange($changes, 'Значение по умолчанию', $current['rubric_field_default'], $target['rubric_field_default']);
			self::addChange($changes, 'Числовое значение', $current['rubric_field_numeric'], $target['rubric_field_numeric'], 'bool');
			self::addChange($changes, 'Участвует в поиске', $current['rubric_field_search'], $target['rubric_field_search'], 'bool');
			self::addChange($changes, 'Подсказка редактору', $current['rubric_field_description'], $target['rubric_field_description']);
			self::addChange($changes, 'Шаблон в документе', $current['rubric_field_template'], $target['rubric_field_template'], 'template');
			self::addChange($changes, 'Шаблон в запросе', $current['rubric_field_template_request'], $target['rubric_field_template_request'], 'template');
			self::addSettingsChanges(
				$changes,
				Json::toArray($current['rubric_field_settings']),
				Json::toArray($target['rubric_field_settings']),
				(string) $current['rubric_field_type'],
				(string) $target['rubric_field_type']
			);

			return $changes;
		}

		protected static function addSettingsChanges(array &$changes, array $before, array $after, $beforeType, $afterType)
		{
			$descriptors = array_replace(
				self::settingsDescriptors($beforeType),
				self::settingsDescriptors($afterType),
				array(
					'placed' => array('label' => 'Размещено на форме', 'type' => 'bool'),
					'width' => array('label' => 'Ширина поля', 'type' => 'width'),
					'prefix' => array('label' => 'Префикс'),
					'suffix' => array('label' => 'Суффикс'),
					'form_condition' => array('label' => 'Условие формы', 'type' => 'condition'),
				)
			);
			$keys = array_unique(array_merge(array_keys($before), array_keys($after)));
			foreach ($keys as $key) {
				$old = array_key_exists($key, $before) ? $before[$key] : null;
				$new = array_key_exists($key, $after) ? $after[$key] : null;
				if (self::sameValue($old, $new)) { continue; }
				$descriptor = isset($descriptors[$key]) ? $descriptors[$key] : array();
				$label = isset($descriptor['label']) ? (string) $descriptor['label'] : self::settingLabel($key);
				$type = isset($descriptor['type']) ? (string) $descriptor['type'] : '';
				$options = isset($descriptor['options']) && is_array($descriptor['options']) ? $descriptor['options'] : array();
				$changes[] = array(
					'label' => $label,
					'before' => self::displayValue($old, $type, $options),
					'after' => self::displayValue($new, $type, $options),
				);
			}
		}

		protected static function settingsDescriptors($type)
		{
			$field = FieldRegistry::get((string) $type);
			if (!$field) { return array(); }
			$out = array();
			foreach (array_merge((array) $field->settingsSchema(), (array) $field->validationSchema()) as $descriptor) {
				if (!is_array($descriptor) || empty($descriptor['key'])) { continue; }
				$out[(string) $descriptor['key']] = $descriptor;
			}

			return $out;
		}

		protected static function addChange(array &$changes, $label, $before, $after, $type = '')
		{
			if (self::sameValue($before, $after)) { return; }
			$changes[] = array(
				'label' => (string) $label,
				'before' => self::displayValue($before, $type),
				'after' => self::displayValue($after, $type),
			);
		}

		protected static function sameValue($before, $after)
		{
			if (is_array($before) || is_array($after)) {
				return Json::encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
					=== Json::encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			return (string) $before === (string) $after;
		}

		protected static function displayValue($value, $type = '', array $options = array())
		{
			if ($type === 'condition') { return self::conditionLabel(is_array($value) ? $value : array()); }
			if ($type === 'bool') { return !empty($value) && (string) $value !== '0' ? 'Да' : 'Нет'; }
			if ($type === 'width') {
				$widths = array('full' => '12 колонок', 'half' => '6 колонок', 'third' => '4 колонки', 'quarter' => '3 колонки');
				return isset($widths[(string) $value]) ? $widths[(string) $value] : self::plainValue($value);
			}

			if ($type === 'template') {
				$value = trim((string) $value);
				return $value === '' ? 'Не задан' : 'Задан · ' . mb_strlen($value, 'UTF-8') . ' симв.';
			}

			if ($type === 'select' && !is_array($value)) {
				foreach ($options as $key => $label) {
					$optionValue = is_int($key) ? $label : $key;
					if ((string) $optionValue === (string) $value) { return (string) $label; }
				}
			}

			if (is_array($value)) { return self::listValue($value); }

			return self::plainValue($value);
		}

		protected static function plainValue($value)
		{
			$value = trim((string) $value);
			if ($value === '') { return 'Не задано'; }
			$value = preg_replace('/\s+/u', ' ', $value);
			return mb_strlen($value, 'UTF-8') > 90 ? mb_substr($value, 0, 87, 'UTF-8') . '...' : $value;
		}

		protected static function listValue(array $value)
		{
			$labels = array();
			foreach ($value as $key => $item) {
				if (is_array($item)) {
					$label = isset($item['label']) ? $item['label'] : (isset($item['title']) ? $item['title'] : (isset($item['value']) ? $item['value'] : ''));
				} else {
					$label = is_int($key) ? $item : $item;
				}

				$label = trim((string) $label);
				if ($label !== '') { $labels[] = $label; }
			}

			if (!$labels) { return 'Не задано'; }
			$shown = array_slice($labels, 0, 3);
			return count($labels) . ' знач.: ' . implode(', ', $shown) . (count($labels) > 3 ? '...' : '');
		}

		protected static function conditionLabel(array $condition)
		{
			if (!$condition) { return 'Нет'; }
			$modes = array('show' => 'Показать', 'hide' => 'Скрыть', 'lock' => 'Запретить изменение');
			$mode = isset($condition['mode']) && isset($modes[$condition['mode']]) ? $modes[$condition['mode']] : 'Применить';
			$tree = isset($condition['tree']) ? $condition['tree'] : array();
			$ruleCount = self::conditionRuleCount($tree);
			$parts = array($mode, $ruleCount . ' ' . ($ruleCount === 1 ? 'правило' : 'правил'));
			$rules = array();
			self::conditionRules($tree, $rules);
			if ($rules) {
				$parts[] = implode('; ', array_slice($rules, 0, 2)) . (count($rules) > 2 ? '; ...' : '');
			}

			if (!empty($condition['required'])) { $parts[] = 'обязательное'; }
			if (!empty($condition['allowed_values']) && is_array($condition['allowed_values'])) {
				$parts[] = count($condition['allowed_values']) . ' доступных вариантов';
			}

			if (isset($condition['value_action']) && $condition['value_action'] === 'set') { $parts[] = 'установить значение'; }
			if (isset($condition['value_action']) && $condition['value_action'] === 'clear') { $parts[] = 'очистить значение'; }

			return implode(' · ', $parts);
		}

		protected static function conditionRuleCount(array $node)
		{
			if (isset($node['items']) && is_array($node['items'])) {
				$count = 0;
				foreach ($node['items'] as $item) { $count += is_array($item) ? self::conditionRuleCount($item) : 0; }
				return $count;
			}

			return isset($node['field']) ? 1 : 0;
		}

		protected static function conditionRules(array $node, array &$out)
		{
			if (isset($node['items']) && is_array($node['items'])) {
				foreach ($node['items'] as $item) {
					if (is_array($item)) { self::conditionRules($item, $out); }
				}

				return;
			}

			if (empty($node['field'])) { return; }
			$operators = array(
				'empty' => 'не заполнено', 'not_empty' => 'заполнено', 'equals' => '=', 'not_equals' => '≠',
				'contains' => 'содержит', 'not_contains' => 'не содержит', 'greater' => '>',
				'greater_or_equal' => '≥', 'less' => '<', 'less_or_equal' => '≤',
				'in' => 'одно из', 'not_in' => 'не входит в',
			);
			$field = preg_replace('/^(?:id|alias):/', '', (string) $node['field']);
			$operator = isset($node['operator']) && isset($operators[$node['operator']]) ? $operators[$node['operator']] : '=';
			$value = isset($node['value']) ? self::plainValue(is_array($node['value']) ? implode(', ', $node['value']) : $node['value']) : '';
			$out[] = trim($field . ' ' . $operator . ' ' . ($value === 'Не задано' ? '' : $value));
		}

		protected static function fieldTypeLabel($type)
		{
			$field = FieldRegistry::get((string) $type);
			return $field ? (string) $field->name() : ((string) $type !== '' ? (string) $type : 'Тип не задан');
		}

		protected static function groupLabel($id, array $groups)
		{
			if ($id <= 0) { return 'Без группы'; }
			return isset($groups[$id]) && trim((string) $groups[$id]['group_title']) !== ''
				? (string) $groups[$id]['group_title']
				: 'Группа #' . $id;
		}

		protected static function settingLabel($key)
		{
			$known = array(
				'upload_dir' => 'Папка загрузки', 'rubric' => 'Рубрика-источник',
				'options' => 'Список значений', 'height' => 'Высота редактора',
				'editor' => 'Режим редактора', 'language' => 'Язык кода',
			);
			if (isset($known[$key])) { return $known[$key]; }
			return mb_convert_case(str_replace('_', ' ', (string) $key), MB_CASE_TITLE, 'UTF-8');
		}

		protected static function restoreBlockers(array $current, array $target, $rubricId)
		{
			$blockers = array();
			$aliases = array();
			foreach ($target as $id => $field) {
				$alias = strtolower(trim((string) $field['rubric_field_alias']));
				if ($alias !== '' && isset($aliases[$alias]) && $aliases[$alias] !== (int) $id) {
					$blockers[] = 'В ревизии повторяется alias поля «' . $alias . '»';
				}

				if ($alias !== '') { $aliases[$alias] = (int) $id; }
				$type = (string) $field['rubric_field_type'];
				if (!FieldTypes::exists($type)) { $blockers[] = 'Тип поля «' . $type . '» не установлен'; }
			}

			foreach ($current as $id => $field) {
				if (isset($target[$id])) { continue; }
				$alias = strtolower(trim((string) $field['rubric_field_alias']));
				if ($alias !== '' && isset($aliases[$alias])) {
					$blockers[] = 'Сохранённое новое поле «' . $field['rubric_field_title'] . '» использует alias «' . $alias . '» из ревизии';
				}
			}

			foreach ($target as $id => $field) {
				if (isset($current[$id])) { continue; }
				$owner = DB::query('SELECT rubric_id FROM ' . Model::fieldsTable() . ' WHERE Id=%i LIMIT 1', (int) $id)->getValue();
				if ($owner && (int) $owner !== (int) $rubricId) {
					$blockers[] = 'ID поля #' . (int) $id . ' уже занят другой рубрикой';
				}
			}

			return array_values(array_unique($blockers));
		}

		protected static function restoreFingerprint(array $revision, array $current, $dependencyFingerprint)
		{
			$state = DB::query(
				"SELECT COUNT(*) count,COALESCE(MAX(Id),0) max_id,COALESCE(MAX(document_changed),0) changed"
				. " FROM " . Model::docsTable() . " WHERE rubric_id=%i AND document_deleted!='1'",
				(int) $revision['rubric_id']
			)->getAssoc() ?: array();
			$payload = array(
				'revision_id' => (int) $revision['id'],
				'target_hash' => (string) $revision['snapshot_hash'],
				'current_hash' => sha1(Json::encode(Model::normalizeSchemaSnapshot($current), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
				'documents' => $state,
				'dependencies' => (string) $dependencyFingerprint,
			);

			return hash('sha256', Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}

		protected static function fieldReferences(array $current, array $target, array $fieldIds)
		{
			$wanted = array_fill_keys(array_map('intval', $fieldIds), true);
			$references = array();
			foreach (array($current, $target) as $snapshot) {
				foreach (isset($snapshot['fields']) && is_array($snapshot['fields']) ? $snapshot['fields'] : array() as $field) {
					$id = isset($field['Id']) ? (int) $field['Id'] : 0;
					if ($id <= 0 || !isset($wanted[$id])) { continue; }
					if (!isset($references[$id])) {
						$references[$id] = array('id' => $id, 'title' => '', 'aliases' => array());
					}

					$title = trim((string) (isset($field['rubric_field_title']) ? $field['rubric_field_title'] : ''));
					$alias = strtolower(trim((string) (isset($field['rubric_field_alias']) ? $field['rubric_field_alias'] : '')));
					if ($title !== '') { $references[$id]['title'] = $title; }
					if ($alias !== '' && !in_array($alias, $references[$id]['aliases'], true)) {
						$references[$id]['aliases'][] = $alias;
					}
				}
			}

			return $references;
		}

		protected static function documentsWithValues($rubricId, array $fieldIds)
		{
			if (!$fieldIds) { return 0; }
			return (int) DB::query(
				'SELECT COUNT(DISTINCT df.document_id) FROM ' . Model::docFieldsTable() . ' df'
				. ' LEFT JOIN ' . Model::docFieldsTextTable() . ' dft ON dft.document_id=df.document_id AND dft.rubric_field_id=df.rubric_field_id'
				. ' INNER JOIN ' . Model::docsTable() . ' d ON d.Id=df.document_id'
				. " WHERE d.rubric_id=%i AND d.document_deleted!='1' AND df.rubric_field_id IN %li"
				. " AND (df.field_value!='' OR COALESCE(dft.field_value,'')!='')",
				(int) $rubricId,
				$fieldIds
			)->getValue();
		}

		protected static function mapById(array $items)
		{
			$out = array();
			foreach ($items as $item) {
				if (is_array($item) && !empty($item['Id'])) { $out[(int) $item['Id']] = $item; }
			}

			return $out;
		}

		protected static function format(array $row, $withSnapshot)
		{
			$labels = self::labels();
			$action = (string) $row['action'];
			$meta = isset($labels[$action]) ? $labels[$action] : array('label' => $action, 'badge' => 'badge-gray');
			$decoded = Json::toArray((string) $row['snapshot_json']);
			$snapshot = $withSnapshot ? $decoded : null;
			$created = (int) $row['created_at'];
			$groups = isset($decoded['groups']) ? count($decoded['groups']) : 0;
			$fields = isset($decoded['fields']) ? count($decoded['fields']) : 0;

			return array(
				'id' => (int) $row['id'],
				'rubric_id' => (int) $row['rubric_id'],
				'action' => $action,
				'action_label' => $meta['label'],
				'badge' => $meta['badge'],
				'comment' => (string) $row['comment'],
				'author_id' => (int) $row['author_id'],
				'author_name' => (string) $row['author_name'],
				'source_revision_id' => (int) $row['source_revision_id'],
				'created_at' => $created,
				'created_label' => $created ? date('d.m.Y H:i:s', $created) : '-',
				'snapshot_hash' => (string) $row['snapshot_hash'],
				'groups_count' => $groups,
				'fields_count' => $fields,
				'snapshot' => $snapshot,
			);
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) { return ''; }
			$row = DB::query('SELECT name,login,email FROM ' . SystemTables::table('users') . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) { return '#' . (int) $id; }
			if (!empty($row['name'])) { return (string) $row['name']; }
			if (!empty($row['login'])) { return '@' . (string) $row['login']; }

			return !empty($row['email']) ? (string) $row['email'] : '#' . (int) $id;
		}
	}
