<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Rubrics/RubricMutationService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use App\Content\PublicUserTables;
	use DB;

	/** Domain writer for rubrics and their core-owned child rows. */
	class RubricMutationService
	{
		public function save($rubricId, array $payload, $actorId)
		{
			$rubricId = (int) $rubricId;
			$title = trim(isset($payload['title']) ? (string) $payload['title'] : (isset($payload['rubric_title']) ? (string) $payload['rubric_title'] : ''));
			$alias = trim(isset($payload['alias']) ? (string) $payload['alias'] : (isset($payload['rubric_alias']) ? (string) $payload['rubric_alias'] : ''));
			if ($title === '') {
				throw new \InvalidArgumentException('Укажите название рубрики');
			}

			if ($alias !== '' && !preg_match('/^[A-Za-z0-9_\-\.\/{}:]+$/', $alias)) {
				throw new \InvalidArgumentException('Шаблон URL рубрики содержит недопустимые символы');
			}

			if (DB::query(
				'SELECT Id FROM ' . $this->table('rubrics') . ' WHERE rubric_alias = %s AND Id != %i LIMIT 1',
				$alias,
				$rubricId
			)->getValue()) {
				throw new \InvalidArgumentException('Такой alias рубрики уже используется');
			}

			$templateId = max(1, (int) (isset($payload['template_id']) ? $payload['template_id'] : (isset($payload['rubric_template_id']) ? $payload['rubric_template_id'] : 1)));
			if (!DB::query('SELECT Id FROM ' . $this->table('templates') . ' WHERE Id = %i LIMIT 1', $templateId)->getValue()) {
				throw new \InvalidArgumentException('Шаблон рубрики не найден');
			}

			$now = time();
			$data = array(
				'rubric_title' => $title,
				'rubric_alias' => $alias,
				'rubric_template_id' => $templateId,
				'rubric_docs_active' => !isset($payload['active']) || !empty($payload['active']) ? 1 : 0,
				'rubric_meta_gen' => !empty($payload['meta_generate']) ? '1' : '0',
				'rubric_alias_history' => !empty($payload['alias_history']) ? '1' : '0',
				'rubric_description' => isset($payload['description']) ? (string) $payload['description'] : '',
				'rubric_template' => isset($payload['document_template']) ? (string) $payload['document_template'] : '',
				'rubric_header_template' => isset($payload['header_template']) ? (string) $payload['header_template'] : '',
				'rubric_og_template' => isset($payload['og_template']) ? (string) $payload['og_template'] : '',
				'rubric_footer_template' => isset($payload['footer_template']) ? (string) $payload['footer_template'] : '',
				'rubric_teaser_template' => isset($payload['teaser_template']) ? (string) $payload['teaser_template'] : '',
				'rubric_code_start' => isset($payload['code_before']) ? (string) $payload['code_before'] : '',
				'rubric_code_end' => isset($payload['code_after']) ? (string) $payload['code_after'] : '',
				'rubric_start_code' => isset($payload['code_start']) ? (string) $payload['code_start'] : '',
				'rubric_changed' => $now,
				'rubric_changed_fields' => $now,
			);
			if (array_key_exists('form_conditions_enabled', $payload)
				&& DatabaseSchema::columnExists($this->table('rubrics'), 'rubric_form_conditions')) {
				$data['rubric_form_conditions'] = !empty($payload['form_conditions_enabled']) ? 1 : 0;
			}

			if ($rubricId > 0) {
				if (!$this->exists($rubricId)) {
					throw new \RuntimeException('Рубрика не найдена');
				}

				DB::Update($this->table('rubrics'), $data, 'Id = %i', $rubricId);
			} else {
				$data['rubric_author_id'] = (int) $actorId > 0 ? (int) $actorId : 1;
				$data['rubric_created'] = $now;
				$data['rubric_position'] = (int) DB::query('SELECT COALESCE(MAX(rubric_position), 0) + 1 FROM ' . $this->table('rubrics'))->getValue();
				$data['rubric_linked_rubric'] = '0';
				DB::Insert($this->table('rubrics'), $data);
				$rubricId = (int) DB::insertId();
				$this->seedPermissions($rubricId);
			}

			FileCacheInvalidator::rubric($rubricId);
			return $rubricId;
		}

		public function delete($rubricId)
		{
			$rubricId = (int) $rubricId;
			if ($rubricId <= 1) {
				throw new \RuntimeException('Рубрика #1 является системной и не может быть удалена');
			}

			if (!$this->exists($rubricId)) {
				return false;
			}

			if ((int) DB::query('SELECT COUNT(*) FROM ' . $this->table('documents') . ' WHERE rubric_id = %i', $rubricId)->getValue() > 0) {
				throw new \RuntimeException('В рубрике есть документы');
			}

			DB::Delete($this->table('rubric_fields'), 'rubric_id = %i', $rubricId);
			DB::Delete($this->table('rubric_fields_group'), 'rubric_id = %i', $rubricId);
			DB::Delete($this->table('rubric_templates'), 'rubric_id = %i', $rubricId);
			DB::Delete($this->table('rubric_permissions'), 'rubric_id = %i', $rubricId);
			if ($this->tableExists($this->table('rubric_admin_views'))) {
				DB::Delete($this->table('rubric_admin_views'), 'rubric_id = %i', $rubricId);
			}

			if ($this->tableExists($this->table('document_creation_presets'))) {
				DB::Delete($this->table('document_creation_presets'), 'rubric_id = %i', $rubricId);
			}

			DB::Delete($this->table('rubrics'), 'Id = %i', $rubricId);
			FileCacheInvalidator::rubric($rubricId);
			return true;
		}

		public function exists($rubricId)
		{
			return (bool) DB::query('SELECT Id FROM ' . $this->table('rubrics') . ' WHERE Id = %i LIMIT 1', (int) $rubricId)->getValue();
		}

		protected function seedPermissions($rubricId)
		{
			$groupsTable = PublicUserTables::table('user_groups');
			if (!$this->tableExists($groupsTable)) {
				return;
			}

			foreach (DB::query('SELECT user_group FROM ' . $groupsTable)->getAll() ?: array() as $group) {
				$groupId = (int) $group['user_group'];
				DB::Insert($this->table('rubric_permissions'), array(
					'rubric_id' => (int) $rubricId,
					'user_group_id' => $groupId,
					'rubric_permission' => $groupId === 1 ? 'alles|docread|new|newnow|editown|editall|delrev' : 'docread',
				));
			}
		}

		protected function table($suffix)
		{
			return ContentTables::table((string) $suffix);
		}

		protected function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}
	}
