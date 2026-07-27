<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Rubrics/RubricFieldMutationService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Content\ContentTables;
	use App\Content\Fields\FieldRegistry;
	use App\Content\Fields\FieldSettings;
	use DB;

	/** Domain writer for rubric field definitions. */
	class RubricFieldMutationService
	{
		public function create($rubricId, array $payload)
		{
			$rubricId = (int) $rubricId;
			if (!DB::query('SELECT Id FROM ' . $this->table('rubrics') . ' WHERE Id = %i LIMIT 1', $rubricId)->getValue()) {
				throw new \InvalidArgumentException('Рубрика не найдена');
			}

			$type = trim(isset($payload['type']) ? (string) $payload['type'] : '');
			if ($type === '' || !FieldRegistry::has($type)) {
				throw new \InvalidArgumentException('Тип поля не зарегистрирован: ' . $type);
			}

			$title = trim(isset($payload['title']) ? (string) $payload['title'] : '');
			$alias = trim(isset($payload['alias']) ? (string) $payload['alias'] : '');
			if ($title === '') {
				throw new \InvalidArgumentException('Укажите название поля');
			}

			if ($alias !== '' && !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $alias)) {
				throw new \InvalidArgumentException('Системное имя поля указано неверно');
			}

			if ($alias !== '' && DB::query(
				'SELECT Id FROM ' . $this->table('rubric_fields') . ' WHERE rubric_id = %i AND rubric_field_alias = %s LIMIT 1',
				$rubricId,
				$alias
			)->getValue()) {
				throw new \InvalidArgumentException('Системное имя поля уже используется в рубрике');
			}

			$settings = isset($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : array();
			if (!array_key_exists('placed', $settings)) {
				$settings['placed'] = true;
			}

			if (!isset($settings['width'])) {
				$settings['width'] = 'full';
			}

			$default = isset($payload['default']) ? (string) $payload['default'] : '';
			$data = array(
				'rubric_id' => $rubricId,
				'rubric_field_group' => isset($payload['group_id']) ? (int) $payload['group_id'] : 0,
				'rubric_field_alias' => $alias,
				'rubric_field_title' => $title,
				'rubric_field_type' => $type,
				'rubric_field_numeric' => FieldRegistry::get($type)->isNumeric() ? '1' : '0',
				'rubric_field_position' => (int) DB::query('SELECT COALESCE(MAX(rubric_field_position), 0) + 1 FROM ' . $this->table('rubric_fields') . ' WHERE rubric_id = %i', $rubricId)->getValue(),
				'rubric_field_default' => $default,
				'rubric_field_settings' => FieldSettings::encode($settings),
				'rubric_field_search' => !isset($payload['search']) || !empty($payload['search']) ? '1' : '0',
				'rubric_field_template' => isset($payload['template']) ? (string) $payload['template'] : '',
				'rubric_field_template_request' => isset($payload['request_template']) ? (string) $payload['request_template'] : '',
				'rubric_field_description' => isset($payload['description']) ? (string) $payload['description'] : '',
			);
			DB::Insert($this->table('rubric_fields'), $data);
			$fieldId = (int) DB::insertId();
			$this->touch($rubricId);
			return $fieldId;
		}

		public function delete($fieldId)
		{
			$fieldId = (int) $fieldId;
			$field = DB::query('SELECT * FROM ' . $this->table('rubric_fields') . ' WHERE Id = %i LIMIT 1', $fieldId)->getAssoc();
			if (!$field) {
				return false;
			}

			DB::Delete($this->table('document_fields_text'), 'rubric_field_id = %i', $fieldId);
			DB::Delete($this->table('document_fields'), 'rubric_field_id = %i', $fieldId);
			\App\Content\Fields\DocumentRelationIndex::removeField($fieldId);
			DB::Delete($this->table('rubric_fields'), 'Id = %i', $fieldId);
			$this->touch((int) $field['rubric_id']);
			return true;
		}

		protected function touch($rubricId)
		{
			DB::Update($this->table('rubrics'), array(
				'rubric_changed' => time(),
				'rubric_changed_fields' => time(),
			), 'Id = %i', (int) $rubricId);
			FileCacheInvalidator::rubric((int) $rubricId);
		}

		protected function table($suffix)
		{
			return ContentTables::table((string) $suffix);
		}
	}
