<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/RubricFieldPresets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Content\Fields\FieldSettings;
	use App\Content\Rubrics\FieldSetRegistry;

	/** Optional starter field sets for newly created content rubrics. */
	class RubricFieldPresets
	{
		public static function all()
		{
			$out = array();
			foreach (self::registeredDefinitions() as $code => $preset) {
				$types = self::fieldTypes($preset);
				$missing = array();
				foreach ($types as $type) {
					if (!FieldTypes::isEnabled($type)) {
						$missing[] = $type;
					}
				}

				$preset['code'] = $code;
				$preset['field_count'] = self::fieldCount($preset);
				$preset['available'] = !empty($preset['available']) && empty($missing);
				$preset['missing_types'] = $missing;
				$out[] = $preset;
			}

			return $out;
		}

		public static function exists($code)
		{
			$definitions = self::registeredDefinitions();
			return $code === '' || isset($definitions[(string) $code]);
		}

		public static function definition($code)
		{
			$definitions = self::registeredDefinitions();
			$code = strtolower(trim((string) $code));
			return isset($definitions[$code]) ? $definitions[$code] : null;
		}

		public static function availabilityError($code)
		{
			$code = trim((string) $code);
			if ($code === '') {
				return '';
			}

			$definitions = self::registeredDefinitions();
			if (!isset($definitions[$code])) {
				return 'Неизвестный стартовый набор полей';
			}

			if (empty($definitions[$code]['available'])) {
				return 'Набор полей принадлежит отключённому модулю';
			}

			$missing = array();
			foreach (self::fieldTypes($definitions[$code]) as $type) {
				if (!FieldTypes::isEnabled($type)) {
					$missing[] = $type;
				}
			}

			return empty($missing)
				? ''
				: 'Для набора включите типы полей: ' . implode(', ', $missing);
		}

		public static function apply($code, $rubricId, $actorId = 0)
		{
			$code = trim((string) $code);
			if ($code === '') {
				return array('groups' => 0, 'fields' => 0);
			}

			$error = self::availabilityError($code);
			if ($error !== '') {
				throw new \RuntimeException($error);
			}

			$definitions = self::registeredDefinitions();
			return self::applyDefinition($definitions[$code], $rubricId, $actorId, $code);
		}

		/** Применить уже проверенное переносимое определение в режиме независимой копии. */
		public static function applyDefinition(array $preset, $rubricId, $actorId = 0, $source = 'import', $fingerprint = '')
		{
			$preset = FieldSetRegistry::normalizeDefinition(isset($preset['code']) ? $preset['code'] : '', $preset);
			self::assertDefinitionAvailable($preset);
			$preview = self::previewDefinition($preset, $rubricId);
			if ($fingerprint !== '' && !hash_equals((string) $preview['fingerprint'], (string) $fingerprint)) {
				throw new \RuntimeException('Состав рубрики изменился после проверки. Выполните предварительный просмотр ещё раз.');
			}

			if (!empty($preview['conflicts'])) {
				throw new \RuntimeException('Набор конфликтует с полями рубрики: ' . implode(', ', $preview['conflicts']));
			}

			$groups = 0;
			$fields = 0;
			$skipped = 0;
			$fieldMap = array();
			$existingFields = array();
			$existingById = array();
			$existingRows = Model::fieldsForRubric((int) $rubricId);
			foreach ($existingRows as $field) {
				$existingFields[strtolower((string) $field['rubric_field_alias'])] = $field;
				$existingById[(int) $field['Id']] = $field;
			}

			$sameSourceSchema = self::sourceSchemaMatches($preset, $existingRows);

			$existingGroups = array();
			foreach (Model::fieldGroups((int) $rubricId) as $group) {
				$key = self::groupKey($group['title']);
				if ($key !== '' && !isset($existingGroups[$key])) { $existingGroups[$key] = (int) $group['id']; }
			}

			foreach ($preset['groups'] as $group) {
				$pending = array();
				foreach ($group['fields'] as $field) {
					$sourceId = isset($field['source_field_id']) ? (int) $field['source_field_id'] : 0;
					if (!empty($field['generated_alias']) && $sameSourceSchema && isset($existingById[$sourceId])) {
						$fieldMap[strtolower((string) $field['alias'])] = (int) $existingById[$sourceId]['Id'];
						$skipped++;
						continue;
					}

					$key = strtolower((string) $field['alias']);
					if (isset($existingFields[$key])) {
						$fieldMap[$key] = (int) $existingFields[$key]['Id'];
						$skipped++;
						continue;
					}

					$pending[] = $field;
				}

				if (!$pending) { continue; }
				$ungrouped = !empty($group['ungrouped']);
				$groupKey = self::groupKey($group['title']);
				$groupId = $ungrouped ? 0 : (isset($existingGroups[$groupKey]) ? (int) $existingGroups[$groupKey] : 0);
				if (!$ungrouped && $groupId <= 0) {
					$groupId = Model::saveGroup(0, (int) $rubricId, array(
						'group_title' => $group['title'],
						'group_description' => isset($group['description']) ? $group['description'] : '',
					));
					$existingGroups[$groupKey] = $groupId;
					$groups++;
				}

				foreach ($pending as $field) {
					$fieldId = Model::saveField(0, (int) $rubricId, array(
						'rubric_field_group' => $groupId,
						'rubric_field_title' => $field['title'],
						'rubric_field_alias' => $field['alias'],
						'rubric_field_type' => $field['type'],
						'rubric_field_search' => !empty($field['search']) ? '1' : '0',
						'rubric_field_numeric' => !empty($field['numeric']) ? '1' : '0',
						'rubric_field_default' => isset($field['default']) ? (string) $field['default'] : '',
						'rubric_field_description' => isset($field['description']) ? $field['description'] : '',
						'rubric_field_settings_canonical' => isset($field['settings']) ? $field['settings'] : array(),
						'rubric_field_layout' => array(
							'width' => isset($field['width']) ? $field['width'] : 'full',
							'placed' => !isset($field['settings']['placed']) || !empty($field['settings']['placed']),
							'prefix' => isset($field['settings']['prefix']) ? (string) $field['settings']['prefix'] : '',
							'suffix' => isset($field['settings']['suffix']) ? (string) $field['settings']['suffix'] : '',
						),
					));
					$field['Id'] = $fieldId;
					$existingFields[strtolower((string) $field['alias'])] = $field;
					$fieldMap[strtolower((string) $field['alias'])] = $fieldId;
					$fields++;
				}
			}

			AuditLog::record('rubric.field_set_applied', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'rubric',
				'target_id' => (int) $rubricId,
				'meta' => array('preset' => (string) $source, 'groups' => $groups, 'fields' => $fields, 'skipped' => $skipped),
			));
			return array('groups' => $groups, 'fields' => $fields, 'skipped' => $skipped, 'field_map' => $fieldMap);
		}

		public static function preview($code, $rubricId)
		{
			$code = trim((string) $code);
			$error = self::availabilityError($code);
			if ($error !== '') { throw new \RuntimeException($error); }
			$definitions = self::registeredDefinitions();
			return self::previewDefinition($definitions[$code], $rubricId);
		}

		public static function previewDefinition(array $preset, $rubricId)
		{
			$preset = FieldSetRegistry::normalizeDefinition(isset($preset['code']) ? $preset['code'] : '', $preset);
			self::assertDefinitionAvailable($preset);
			$existing = array();
			$existingById = array();
			$existingRows = Model::fieldsForRubric((int) $rubricId);
			foreach ($existingRows as $field) {
				$existing[strtolower((string) $field['rubric_field_alias'])] = $field;
				$existingById[(int) $field['Id']] = $field;
			}

			$sameSourceSchema = self::sourceSchemaMatches($preset, $existingRows);

			$create = 0;
			$reuse = 0;
			$conflicts = array();
			$fieldCount = 0;
			$generatedAliases = 0;
			foreach ($preset['groups'] as $group) {
				foreach ($group['fields'] as $field) {
					$fieldCount++;
					if (!empty($field['generated_alias'])) { $generatedAliases++; }
					$sourceId = isset($field['source_field_id']) ? (int) $field['source_field_id'] : 0;
					if (!empty($field['generated_alias']) && $sameSourceSchema && isset($existingById[$sourceId])) {
						if ((string) $existingById[$sourceId]['rubric_field_type'] === (string) $field['type']) { $reuse++; }
						else { $conflicts[] = $field['alias'] . ' (исходное поле имеет тип ' . $existingById[$sourceId]['rubric_field_type'] . ')'; }
						continue;
					}

					$key = strtolower((string) $field['alias']);
					if (!isset($existing[$key])) { $create++; continue; }
					if ((string) $existing[$key]['rubric_field_type'] !== (string) $field['type']) {
						$conflicts[] = $field['alias'] . ' (' . $existing[$key]['rubric_field_type'] . ' вместо ' . $field['type'] . ')';
					} else {
						$reuse++;
					}
				}
			}

			$existingGroups = array();
			foreach (Model::fieldGroups((int) $rubricId) as $group) {
				$existingGroups[self::groupKey($group['title'])] = true;
			}

			$groupsCreate = 0;
			$groupsReuse = 0;
			foreach ($preset['groups'] as $group) {
				if (!empty($group['ungrouped'])) { continue; }
				if (isset($existingGroups[self::groupKey($group['title'])])) { $groupsReuse++; }
				else { $groupsCreate++; }
			}

			$state = array();
			foreach ($existing as $alias => $field) {
				$state[$alias] = (string) $field['rubric_field_type'];
			}

			ksort($state);
			$stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$fingerprint = hash('sha256', FieldSetRegistry::fingerprint($preset) . '|' . (string) $stateJson);

			return array(
				'code' => $preset['code'],
				'title' => $preset['title'],
				'description' => $preset['description'],
				'field_count' => $fieldCount,
				'generated_aliases' => $generatedAliases,
				'source_schema_match' => $sameSourceSchema,
				'create' => $create,
				'reuse' => $reuse,
				'groups_create' => $groupsCreate,
				'groups_reuse' => $groupsReuse,
				'conflicts' => $conflicts,
				'fingerprint' => $fingerprint,
			);
		}

		/** Сформировать descriptor текущей рубрики без документов, PHP и шаблонов. */
		public static function exportDescriptor($rubricId)
		{
			$rubricId = (int) $rubricId;
			$rubric = Model::one($rubricId);
			if (!$rubric) { throw new \RuntimeException('Рубрика не найдена'); }

			$groups = array(0 => array(
				'title' => 'Основные поля',
				'description' => 'Поля без отдельной группы.',
				'ungrouped' => true,
				'fields' => array(),
			));
			$sourceState = array();
			foreach (Model::fieldGroups($rubricId) as $group) {
				$groups[(int) $group['id']] = array(
					'title' => (string) $group['title'],
					'description' => (string) $group['description'],
					'fields' => array(),
				);
			}

			foreach (Model::fieldsForRubric($rubricId) as $field) {
				$sourceState[] = array(
					'id' => (int) $field['Id'],
					'alias' => (string) $field['rubric_field_alias'],
					'type' => (string) $field['rubric_field_type'],
				);
				$groupId = isset($groups[(int) $field['rubric_field_group']]) ? (int) $field['rubric_field_group'] : 0;
				$settings = FieldSettings::effective($field);
				// Условия ссылаются на ID полей текущей рубрики и не переносимы между проектами.
				unset($settings['form_condition']);
				$type = (string) $field['rubric_field_type'];
				$default = FieldSettings::legacyDefaultIsConfiguration($type) ? '' : (string) $field['rubric_field_default'];
				$sourceAlias = trim((string) $field['rubric_field_alias']);
				$generatedAlias = !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $sourceAlias);
				$portableAlias = $generatedAlias ? 'field_' . (int) $field['Id'] : $sourceAlias;
				$groups[$groupId]['fields'][] = array(
					'title' => (string) $field['rubric_field_title'],
					'alias' => $portableAlias,
					'type' => $type,
					'source_field_id' => (int) $field['Id'],
					'generated_alias' => $generatedAlias,
					'search' => (string) $field['rubric_field_search'] !== '0',
					'numeric' => (string) $field['rubric_field_numeric'] === '1',
					'default' => $default,
					'width' => !empty($field['layout']['width']) ? (string) $field['layout']['width'] : 'full',
					'description' => (string) $field['rubric_field_description'],
					'settings' => $settings,
				);
			}

			$groups = array_values(array_filter($groups, function ($group) {
				return !empty($group['fields']);
			}));
			$definition = array(
				'title' => 'Поля: ' . (string) $rubric['rubric_title'],
				'description' => 'Переносимая схема формы рубрики «' . (string) $rubric['rubric_title'] . '».',
				'icon' => 'ti-forms',
				'source_schema_fingerprint' => self::sourceSchemaFingerprint($sourceState),
				'groups' => $groups,
			);

			return FieldSetRegistry::descriptor('rubric_' . $rubricId, $definition, array(
				'rubric_id' => $rubricId,
				'title' => (string) $rubric['rubric_title'],
				'alias' => isset($rubric['rubric_alias']) ? (string) $rubric['rubric_alias'] : '',
			));
		}

		protected static function assertDefinitionAvailable(array $preset)
		{
			$missing = array();
			foreach (self::fieldTypes($preset) as $type) {
				if (!FieldTypes::isEnabled($type)) { $missing[] = $type; }
			}

			if ($missing) {
				throw new \RuntimeException('Для набора включите типы полей: ' . implode(', ', $missing));
			}
		}

		protected static function sourceSchemaMatches(array $preset, array $fields)
		{
			$expected = isset($preset['source_schema_fingerprint']) ? (string) $preset['source_schema_fingerprint'] : '';
			if (!preg_match('/^[a-f0-9]{64}$/', $expected)) { return false; }
			$state = array();
			foreach ($fields as $field) {
				$state[] = array(
					'id' => (int) $field['Id'],
					'alias' => (string) $field['rubric_field_alias'],
					'type' => (string) $field['rubric_field_type'],
				);
			}

			return hash_equals($expected, self::sourceSchemaFingerprint($state));
		}

		protected static function sourceSchemaFingerprint(array $state)
		{
			usort($state, function ($left, $right) {
				return (int) $left['id'] <=> (int) $right['id'];
			});
			$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			return hash('sha256', (string) $json);
		}

		protected static function fieldTypes(array $preset)
		{
			$types = array();
			foreach ($preset['groups'] as $group) {
				foreach ($group['fields'] as $field) {
					$types[] = (string) $field['type'];
				}
			}

			return array_values(array_unique($types));
		}

		protected static function fieldCount(array $preset)
		{
			$count = 0;
			foreach ($preset['groups'] as $group) {
				$count += count($group['fields']);
			}

			return $count;
		}

		public static function definitions()
		{
			$body = array(
				'title' => 'Основной текст',
				'alias' => 'body',
				'type' => 'content',
				'search' => true,
				'width' => 'full',
				'description' => 'Основное содержимое документа с визуальным редактором.',
				'settings' => array('mode' => 'rich', 'height' => 560, 'required' => true),
			);
			$cover = array(
				'title' => 'Обложка',
				'alias' => 'cover',
				'type' => 'image_single',
				'width' => 'half',
				'description' => 'Основное изображение для страницы и карточки материала.',
			);
			$gallery = array(
				'title' => 'Галерея',
				'alias' => 'gallery',
				'type' => 'image_multi',
				'width' => 'full',
				'description' => 'Дополнительные изображения материала.',
			);

			return array(
				'news' => array(
					'title' => 'Новость',
					'description' => 'Текст, обложка, галерея и ссылка на первоисточник.',
					'icon' => 'ti-news',
					'groups' => array(
						array('title' => 'Содержание', 'description' => 'Основной материал новости.', 'fields' => array($body, $cover)),
						array('title' => 'Медиа и источник', 'description' => 'Дополнительные материалы новости.', 'fields' => array(
							$gallery,
							array(
								'title' => 'Источник',
								'alias' => 'source',
								'type' => 'contact',
								'width' => 'half',
								'description' => 'Ссылка на первоисточник новости, если она требуется.',
								'settings' => array('mode' => 'url', 'clickable' => true, 'new_window' => true),
							),
						)),
					),
				),
				'article' => array(
					'title' => 'Статья',
					'description' => 'Большой текст, обложка, галерея и прикреплённые файлы.',
					'icon' => 'ti-file-description',
					'groups' => array(
						array('title' => 'Содержание', 'description' => 'Основной материал статьи.', 'fields' => array($body, $cover)),
						array('title' => 'Материалы', 'description' => 'Иллюстрации и файлы статьи.', 'fields' => array(
							$gallery,
							array(
								'title' => 'Прикреплённые файлы',
								'alias' => 'attachments',
								'type' => 'doc_files',
								'width' => 'full',
								'description' => 'Документы и другие файлы для скачивания.',
							),
						)),
					),
				),
				'blog' => array(
					'title' => 'Запись блога',
					'description' => 'Текст, обложка, галерея и видео.',
					'icon' => 'ti-pencil',
					'groups' => array(
						array('title' => 'Публикация', 'description' => 'Основное содержимое записи.', 'fields' => array($body, $cover)),
						array('title' => 'Медиа', 'description' => 'Иллюстрации и видео записи.', 'fields' => array(
							$gallery,
							array(
								'title' => 'Видео',
								'alias' => 'video',
								'type' => 'youtube',
								'width' => 'full',
								'description' => 'YouTube-видео с дополнительными параметрами.',
							),
						)),
					),
				),
			);
		}

		protected static function registeredDefinitions()
		{
			if (!FieldSetRegistry::get('news')) {
				FieldSetRegistry::registerMany('module:rubrics', self::definitions(), true);
			}

			$out = array();
			foreach (FieldSetRegistry::all(true) as $preset) { $out[(string) $preset['code']] = $preset; }
			foreach (RubricFieldSetLinks::customDefinitions() as $code => $preset) {
				if (!isset($out[$code])) { $out[$code] = $preset; }
			}

			return $out;
		}

		protected static function groupKey($title)
		{
			$title = trim((string) $title);
			return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
		}
	}
