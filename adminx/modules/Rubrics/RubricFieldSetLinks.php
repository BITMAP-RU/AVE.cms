<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/RubricFieldSetLinks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use App\Content\Fields\FieldSettings;
	use App\Content\Rubrics\FieldSetMerge;
	use App\Content\Rubrics\FieldSetRegistry;
	use App\Helpers\Json;
	use DB;

	/** Persistent definitions and safe links between reusable field sets and rubrics. */
	class RubricFieldSetLinks
	{
		public static function definitionsTable() { return ContentTables::table('rubric_field_sets'); }
		public static function linksTable() { return ContentTables::table('rubric_field_set_links'); }

		public static function available()
		{
			return DatabaseSchema::tableExists(self::definitionsTable())
				&& DatabaseSchema::tableExists(self::linksTable());
		}

		public static function customDefinitions()
		{
			if (!self::available()) { return array(); }
			$rows = DB::query('SELECT definition_json FROM ' . self::definitionsTable() . ' WHERE active=1 ORDER BY title,code')->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$definition = Json::toArray((string) $row['definition_json']);
				if (!$definition) { continue; }
				try {
					$definition = FieldSetRegistry::normalizeDefinition(isset($definition['code']) ? $definition['code'] : '', $definition);
					$definition['source'] = 'database';
					$definition['available'] = true;
					$out[(string) $definition['code']] = $definition;
				} catch (\Throwable $e) {
					continue;
				}
			}

			return $out;
		}

		public static function saveCustomDefinition(array $definition, $actorId = 0)
		{
			self::assertAvailable();
			$definition = FieldSetRegistry::normalizeDefinition(isset($definition['code']) ? $definition['code'] : '', $definition);
			$fingerprint = FieldSetRegistry::fingerprint($definition);
			$existing = RubricFieldPresets::definition($definition['code']);
			if ($existing && isset($existing['source']) && (string) $existing['source'] !== 'database'
				&& !hash_equals(FieldSetRegistry::fingerprint($existing), $fingerprint)) {
				throw new \RuntimeException('Код набора уже занят ядром или установленным модулем: ' . $definition['code']);
			}

			$data = array(
				'title' => $definition['title'],
				'description' => $definition['description'],
				'definition_json' => self::encode($definition),
				'fingerprint' => $fingerprint,
				'source' => 'import',
				'active' => 1,
				'updated_by' => (int) $actorId,
				'updated_at' => time(),
			);
			$exists = DB::query('SELECT code FROM ' . self::definitionsTable() . ' WHERE code=%s LIMIT 1', $definition['code'])->getValue();
			if ($exists) {
				DB::Update(self::definitionsTable(), $data, 'code=%s', $definition['code']);
			} else {
				$data['code'] = $definition['code'];
				$data['created_at'] = time();
				DB::Insert(self::definitionsTable(), $data);
			}

			return $definition;
		}

		public static function attach($rubricId, array $definition, array $fieldMap, $actorId = 0)
		{
			self::assertAvailable();
			$definition = FieldSetRegistry::normalizeDefinition(isset($definition['code']) ? $definition['code'] : '', $definition);
			$code = (string) $definition['code'];
			$data = array(
				'applied_fingerprint' => FieldSetRegistry::fingerprint($definition),
				'applied_definition_json' => self::encode($definition),
				'field_map_json' => self::encode(self::cleanFieldMap($fieldMap)),
				'updated_by' => (int) $actorId,
				'updated_at' => time(),
			);
			$id = (int) DB::query(
				'SELECT id FROM ' . self::linksTable() . ' WHERE rubric_id=%i AND set_code=%s LIMIT 1',
				(int) $rubricId,
				$code
			)->getValue();
			if ($id > 0) {
				DB::Update(self::linksTable(), $data, 'id=%i', $id);
			} else {
				$data['rubric_id'] = (int) $rubricId;
				$data['set_code'] = $code;
				$data['created_at'] = time();
				DB::Insert(self::linksTable(), $data);
			}

			AuditLog::record('rubric.field_set_linked', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
				'meta' => array('set' => $code, 'fields' => count($fieldMap)),
			));
		}

		public static function forRubric($rubricId)
		{
			if (!self::available()) { return array(); }
			$rows = DB::query(
				'SELECT * FROM ' . self::linksTable() . ' WHERE rubric_id=%i ORDER BY set_code',
				(int) $rubricId
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$definition = RubricFieldPresets::definition((string) $row['set_code']);
				$currentFingerprint = $definition ? FieldSetRegistry::fingerprint($definition) : '';
				$out[] = array(
					'code' => (string) $row['set_code'],
					'title' => $definition ? (string) $definition['title'] : (string) $row['set_code'],
					'available' => (bool) $definition,
					'update_available' => $definition && !hash_equals((string) $row['applied_fingerprint'], $currentFingerprint),
					'applied_fingerprint' => (string) $row['applied_fingerprint'],
					'current_fingerprint' => $currentFingerprint,
					'updated_at' => (int) $row['updated_at'],
				);
			}

			return $out;
		}

		public static function hasLink($rubricId, $code)
		{
			if (!self::available()) { return false; }

			return (bool) DB::query(
				'SELECT id FROM ' . self::linksTable() . ' WHERE rubric_id=%i AND set_code=%s LIMIT 1',
				(int) $rubricId,
				strtolower(trim((string) $code))
			)->getValue();
		}

		public static function preview($rubricId, $code)
		{
			$state = self::syncState($rubricId, $code);
			return $state['preview'];
		}

		public static function sync($rubricId, $code, $fingerprint, $actorId = 0)
		{
			$state = self::syncState($rubricId, $code);
			if (!hash_equals((string) $state['preview']['fingerprint'], (string) $fingerprint)) {
				throw new \RuntimeException('Схема или поля рубрики изменились после проверки. Повторите предварительный просмотр.');
			}

			if (!empty($state['preview']['conflicts'])) {
				throw new \RuntimeException('Синхронизация требует ручного решения: ' . implode(', ', $state['preview']['conflicts']));
			}

			$groupIds = self::groupIds((int) $rubricId);
			$fieldMap = $state['field_map'];
			$updated = 0;
			$created = 0;
			$detached = 0;
			foreach ($state['plan'] as $item) {
				if ($item['action'] === 'create') {
					$groupId = self::ensureGroup($rubricId, $item['group'], $groupIds);
					$fieldId = Model::saveField(0, $rubricId, self::fieldInput($item['next'], $groupId));
					$fieldMap[$item['alias']] = $fieldId;
					$created++;
					continue;
				}

				$field = $item['current'];
				if ($item['action'] === 'detach') {
					$settings = Json::toArray((string) $field['rubric_field_settings']);
					$settings['placed'] = false;
					Model::saveField((int) $field['Id'], $rubricId, self::currentFieldInput($field, $settings));
					unset($fieldMap[$item['alias']]);
					$detached++;
					continue;
				}

				$merged = $item['merged'];
				$groupId = self::ensureGroup($rubricId, $merged['group'], $groupIds);
				Model::saveField((int) $field['Id'], $rubricId, self::fieldInput($merged['field'], $groupId, $field));
				$fieldMap[$item['alias']] = (int) $field['Id'];
				$updated++;
			}

			self::attach($rubricId, $state['definition'], $fieldMap, $actorId);
			AuditLog::record('rubric.field_set_synced', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
				'meta' => array('set' => (string) $code, 'created' => $created, 'updated' => $updated, 'detached' => $detached),
			));

			return array('created' => $created, 'updated' => $updated, 'detached' => $detached);
		}

		public static function detach($rubricId, $code, $actorId = 0)
		{
			if (!self::available()) { return false; }
			DB::Delete(self::linksTable(), 'rubric_id=%i AND set_code=%s', (int) $rubricId, (string) $code);
			AuditLog::record('rubric.field_set_unlinked', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
				'meta' => array('set' => (string) $code),
			));
			return true;
		}

		public static function deleteForRubric($rubricId)
		{
			if (self::available()) { DB::Delete(self::linksTable(), 'rubric_id=%i', (int) $rubricId); }
		}

		protected static function syncState($rubricId, $code)
		{
			self::assertAvailable();
			$link = DB::query(
				'SELECT * FROM ' . self::linksTable() . ' WHERE rubric_id=%i AND set_code=%s LIMIT 1',
				(int) $rubricId,
				(string) $code
			)->getAssoc();
			if (!$link) { throw new \RuntimeException('Набор не связан с этой рубрикой'); }
			$definition = RubricFieldPresets::definition((string) $code);
			if (!$definition) { throw new \RuntimeException('Источник связанного набора недоступен'); }

			$previous = Json::toArray((string) $link['applied_definition_json']);
			$previous = FieldSetRegistry::normalizeDefinition(isset($previous['code']) ? $previous['code'] : '', $previous);
			$definition = FieldSetRegistry::normalizeDefinition(isset($definition['code']) ? $definition['code'] : '', $definition);
			$fieldMap = self::cleanFieldMap(Json::toArray((string) $link['field_map_json']));
			$current = array();
			foreach (Model::fieldsForRubric((int) $rubricId) as $field) { $current[(int) $field['Id']] = $field; }
			$oldFields = self::flatten($previous);
			$newFields = self::flatten($definition);
			$plan = array();
			$conflicts = array();
			$created = 0;
			$updated = 0;
			$detached = 0;

			foreach ($newFields as $alias => $next) {
				$fieldId = isset($fieldMap[$alias]) ? (int) $fieldMap[$alias] : 0;
				$field = isset($current[$fieldId]) ? $current[$fieldId] : null;
				if (!$field) {
					$field = self::findCurrentByAlias($current, $alias);
					if ($field && (string) $field['rubric_field_type'] !== (string) $next['field']['type']) {
						$conflicts[] = $alias . ': alias занят типом ' . $field['rubric_field_type'];
						continue;
					}
				}

				if (!$field) {
					$plan[] = array('action' => 'create', 'alias' => $alias, 'next' => $next['field'], 'group' => $next['group']);
					$created++;
					continue;
				}

				$old = isset($oldFields[$alias]) ? $oldFields[$alias] : $next;
				if ((string) $old['field']['type'] !== (string) $next['field']['type']
					&& ((string) $field['rubric_field_type'] !== (string) $old['field']['type'] || self::fieldHasValues((int) $field['Id']))) {
					$conflicts[] = $alias . ': нельзя автоматически изменить тип заполненного или локально изменённого поля';
					continue;
				}

				$plan[] = array(
					'action' => 'update',
					'alias' => $alias,
					'current' => $field,
					'merged' => self::mergeField($field, $old, $next),
				);
				$updated++;
			}

			foreach ($oldFields as $alias => $old) {
				if (isset($newFields[$alias]) || !isset($fieldMap[$alias]) || !isset($current[(int) $fieldMap[$alias]])) { continue; }
				$plan[] = array('action' => 'detach', 'alias' => $alias, 'current' => $current[(int) $fieldMap[$alias]]);
				$detached++;
			}

			$state = array(
				'set' => FieldSetRegistry::fingerprint($definition),
				'fields' => self::currentFingerprintState($current),
				'map' => $fieldMap,
			);
			return array(
				'definition' => $definition,
				'field_map' => $fieldMap,
				'plan' => $plan,
				'preview' => array(
					'code' => (string) $code,
					'title' => (string) $definition['title'],
					'created' => $created,
					'updated' => $updated,
					'detached' => $detached,
					'conflicts' => $conflicts,
					'fingerprint' => hash('sha256', self::encode($state)),
				),
			);
		}

		protected static function mergeField(array $current, array $previous, array $next)
		{
			$old = $previous['field'];
			$new = $next['field'];
			$currentSettings = Json::toArray((string) $current['rubric_field_settings']);
			$oldSettings = isset($old['settings']) && is_array($old['settings']) ? $old['settings'] : array();
			$newSettings = isset($new['settings']) && is_array($new['settings']) ? $new['settings'] : array();
			$currentWidth = isset($current['layout']['width']) && $current['layout']['width'] !== '' ? $current['layout']['width'] : 'full';
			$oldWidth = isset($old['width']) ? $old['width'] : 'full';
			$newWidth = isset($new['width']) ? $new['width'] : 'full';
			$merged = $new;
			$merged['title'] = FieldSetMerge::value((string) $current['rubric_field_title'], (string) $old['title'], (string) $new['title']);
			$merged['type'] = FieldSetMerge::value((string) $current['rubric_field_type'], (string) $old['type'], (string) $new['type']);
			$merged['search'] = FieldSetMerge::value((string) $current['rubric_field_search'] !== '0', !empty($old['search']), !empty($new['search']));
			$merged['numeric'] = FieldSetMerge::value((string) $current['rubric_field_numeric'] === '1', !empty($old['numeric']), !empty($new['numeric']));
			$merged['default'] = FieldSetMerge::value((string) $current['rubric_field_default'], (string) $old['default'], (string) $new['default']);
			$merged['description'] = FieldSetMerge::value((string) $current['rubric_field_description'], (string) $old['description'], (string) $new['description']);
			$merged['width'] = FieldSetMerge::value((string) $currentWidth, (string) $oldWidth, (string) $newWidth);
			$merged['settings'] = FieldSetMerge::settings($currentSettings, $oldSettings, $newSettings);
			$currentGroup = isset($current['group_title']) ? (string) $current['group_title'] : '';
			$oldGroup = !empty($previous['group']['ungrouped']) ? '' : (string) $previous['group']['title'];
			$newGroup = !empty($next['group']['ungrouped']) ? '' : (string) $next['group']['title'];

			return array(
				'field' => $merged,
				'group' => FieldSetMerge::value($currentGroup, $oldGroup, $newGroup) === $newGroup ? $next['group'] : array(
					'title' => $currentGroup,
					'description' => isset($current['group_description']) ? (string) $current['group_description'] : '',
					'ungrouped' => $currentGroup === '',
				),
			);
		}

		protected static function flatten(array $definition)
		{
			$out = array();
			foreach ($definition['groups'] as $group) {
				foreach ($group['fields'] as $field) { $out[strtolower((string) $field['alias'])] = array('field' => $field, 'group' => $group); }
			}

			return $out;
		}

		protected static function fieldInput(array $field, $groupId, array $current = array())
		{
			$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
			$placed = !array_key_exists('placed', $settings) || !empty($settings['placed']);
			return array(
				'rubric_field_group' => (int) $groupId,
				'rubric_field_title' => (string) $field['title'],
				'rubric_field_alias' => (string) $field['alias'],
				'rubric_field_type' => (string) $field['type'],
				'rubric_field_search' => !empty($field['search']) ? '1' : '0',
				'rubric_field_numeric' => !empty($field['numeric']) ? '1' : '0',
				'rubric_field_default' => isset($field['default']) ? (string) $field['default'] : '',
				'rubric_field_description' => isset($field['description']) ? (string) $field['description'] : '',
				'rubric_field_template' => isset($current['rubric_field_template']) ? (string) $current['rubric_field_template'] : '',
				'rubric_field_template_request' => isset($current['rubric_field_template_request']) ? (string) $current['rubric_field_template_request'] : '',
				'rubric_field_settings_canonical' => $settings,
				'rubric_field_layout' => array(
					'placed' => $placed,
					'width' => isset($field['width']) ? (string) $field['width'] : 'full',
					'prefix' => isset($settings['prefix']) ? (string) $settings['prefix'] : '',
					'suffix' => isset($settings['suffix']) ? (string) $settings['suffix'] : '',
				),
			);
		}

		protected static function currentFieldInput(array $field, array $settings)
		{
			$definition = array(
				'title' => (string) $field['rubric_field_title'],
				'alias' => (string) $field['rubric_field_alias'],
				'type' => (string) $field['rubric_field_type'],
				'search' => (string) $field['rubric_field_search'] !== '0',
				'numeric' => (string) $field['rubric_field_numeric'] === '1',
				'default' => (string) $field['rubric_field_default'],
				'description' => (string) $field['rubric_field_description'],
				'width' => isset($field['layout']['width']) ? (string) $field['layout']['width'] : 'full',
				'settings' => $settings,
			);
			return self::fieldInput($definition, (int) $field['rubric_field_group'], $field);
		}

		protected static function groupIds($rubricId)
		{
			$out = array('' => 0);
			foreach (Model::fieldGroups((int) $rubricId) as $group) { $out[self::groupKey($group['title'])] = (int) $group['id']; }
			return $out;
		}

		protected static function ensureGroup($rubricId, array $group, array &$groupIds)
		{
			if (!empty($group['ungrouped']) || trim((string) $group['title']) === '') { return 0; }
			$key = self::groupKey($group['title']);
			if (isset($groupIds[$key])) { return (int) $groupIds[$key]; }
			$id = Model::saveGroup(0, (int) $rubricId, array(
				'group_title' => (string) $group['title'],
				'group_description' => isset($group['description']) ? (string) $group['description'] : '',
			));
			$groupIds[$key] = $id;
			return $id;
		}

		protected static function findCurrentByAlias(array $fields, $alias)
		{
			foreach ($fields as $field) {
				if (strcasecmp((string) $field['rubric_field_alias'], (string) $alias) === 0) { return $field; }
			}

			return null;
		}

		protected static function fieldHasValues($fieldId)
		{
			return Model::fieldHasStoredValues((int) $fieldId);
		}

		protected static function currentFingerprintState(array $fields)
		{
			$out = array();
			foreach ($fields as $id => $field) {
				$out[(int) $id] = array(
					'alias' => (string) $field['rubric_field_alias'],
					'type' => (string) $field['rubric_field_type'],
					'group' => (int) $field['rubric_field_group'],
					'title' => (string) $field['rubric_field_title'],
					'settings' => (string) $field['rubric_field_settings'],
				);
			}

			ksort($out);
			return $out;
		}

		protected static function cleanFieldMap(array $fieldMap)
		{
			$out = array();
			foreach ($fieldMap as $alias => $id) {
				$alias = strtolower(trim((string) $alias));
				if (preg_match('/^[a-z][a-z0-9_]{0,19}$/', $alias) && (int) $id > 0) { $out[$alias] = (int) $id; }
			}

			ksort($out);
			return $out;
		}

		protected static function groupKey($title)
		{
			$title = trim((string) $title);
			return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
		}

		protected static function encode(array $value)
		{
			$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) { throw new \RuntimeException('Не удалось сериализовать набор полей'); }
			return $json;
		}

		protected static function assertAvailable()
		{
			if (!self::available()) { throw new \RuntimeException('Примените миграцию связанных наборов полей'); }
		}
	}
