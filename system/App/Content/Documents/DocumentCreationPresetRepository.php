<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentCreationPresetRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Common\DatabaseSchema;
	use DB;

	/** Stores reusable, data-only defaults for creating documents in one rubric. */
	class DocumentCreationPresetRepository
	{
		protected static $documentKeys = array(
			'document_excerpt',
			'document_meta_keywords',
			'document_meta_description',
			'document_meta_robots',
			'document_sitemap_freq',
			'document_sitemap_pr',
			'document_tags',
			'document_status',
			'document_in_search',
		);

		protected static $assetFieldTypes = array(
			'image_single', 'image_multi', 'image_mega', 'doc_files', 'download',
			'doc_from_rub', 'doc_from_rub_multi', 'doc_from_rub_check',
			'doc_from_rub_search', 'doc_from_rub_all', 'analoque', 'teasers',
			'catalog',
		);

		public function all($rubricId = 0, $activeOnly = true)
		{
			if (!$this->available()) { return array(); }
			$sql = 'SELECT p.*,r.rubric_title FROM ' . $this->table() . ' p'
				. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id=p.rubric_id WHERE 1=1';
			$args = array();
			if ((int) $rubricId > 0) {
				$sql .= ' AND p.rubric_id=%i';
				$args[] = (int) $rubricId;
			}

			if ($activeOnly) {
				$sql .= ' AND p.is_active=1';
			}

			$sql .= ' ORDER BY p.rubric_id ASC,p.sort_order ASC,p.title ASC,p.id ASC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			return array_map(array($this, 'hydrate'), $rows);
		}

		public function find($id, $activeOnly = true)
		{
			if (!$this->available()) { return null; }
			$sql = 'SELECT p.*,r.rubric_title FROM ' . $this->table() . ' p'
				. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id=p.rubric_id WHERE p.id=%i';
			if ($activeOnly) { $sql .= ' AND p.is_active=1'; }
			$row = DB::query($sql . ' LIMIT 1', (int) $id)->getAssoc();
			return $row ? $this->hydrate($row) : null;
		}

		public function createFromDocument($documentId, array $input, $actorId)
		{
			if (!$this->available()) { throw new \RuntimeException('Обновите схему базы данных перед созданием пресетов'); }
			$document = DB::query(
				'SELECT * FROM ' . ContentTables::table('documents') . ' WHERE Id=%i LIMIT 1',
				(int) $documentId
			)->getAssoc();
			if (!$document || !empty($document['document_deleted'])) {
				throw new \RuntimeException('Документ для пресета не найден');
			}

			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('Укажите название пресета'); }
			if (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 191) {
				throw new \InvalidArgumentException('Название пресета не должно быть длиннее 191 символа');
			}

			$rubricId = (int) $document['rubric_id'];
			$includeFields = !empty($input['include_fields']);
			$includeAssets = $includeFields && !empty($input['include_assets']);
			$documentDefaults = array(
				'document_status' => !empty($input['create_published']) ? 1 : 0,
				'document_in_search' => !empty($input['include_search']) ? (int) $document['document_in_search'] : 1,
			);
			if (!empty($input['include_excerpt'])) {
				$documentDefaults['document_excerpt'] = (string) $document['document_excerpt'];
			}

			if (!empty($input['include_seo'])) {
				foreach (array(
					'document_meta_keywords', 'document_meta_description', 'document_meta_robots',
					'document_sitemap_freq', 'document_sitemap_pr', 'document_tags',
				) as $key) {
					$documentDefaults[$key] = $document[$key];
				}
			}

			$fieldDefaults = $includeFields ? $this->documentFields($documentId, $rubricId, $includeAssets) : array();
			$now = time();
			DB::Insert($this->table(), array(
				'rubric_id' => $rubricId,
				'title' => $title,
				'description' => trim(isset($input['description']) ? (string) $input['description'] : ''),
				'document_json' => $this->encode($this->documentDefaults($documentDefaults)),
				'fields_json' => $this->encode($fieldDefaults),
				'source_document_id' => (int) $documentId,
				'is_active' => 1,
				'sort_order' => $this->nextPosition($rubricId),
				'created_by' => max(0, (int) $actorId),
				'created_at' => $now,
				'updated_at' => $now,
			));

			return $this->find((int) DB::insertId(), false);
		}

		public function delete($id)
		{
			if (!$this->available()) { return null; }
			$preset = $this->find((int) $id, false);
			if (!$preset) { return null; }
			DB::Delete($this->table(), 'id=%i', (int) $id);
			return $preset;
		}

		public function applyToDocument(array $document, array $preset)
		{
			if ((int) $document['rubric_id'] !== (int) $preset['rubric_id']) {
				throw new \InvalidArgumentException('Пресет принадлежит другой рубрике');
			}

			foreach ($this->documentDefaults(isset($preset['document']) ? $preset['document'] : array()) as $key => $value) {
				$document[$key] = $value;
			}

			return $document;
		}

		public function fieldDefaults(array $preset)
		{
			return isset($preset['fields']) && is_array($preset['fields']) ? $preset['fields'] : array();
		}

		public function available()
		{
			static $available;
			if ($available === null) { $available = DatabaseSchema::tableExists($this->table()); }
			return $available;
		}

		public function documentDefaults(array $values)
		{
			$out = array();
			foreach (self::$documentKeys as $key) {
				if (array_key_exists($key, $values) && (is_scalar($values[$key]) || $values[$key] === null)) {
					$out[$key] = $values[$key];
				}
			}

			return $out;
		}

		protected function documentFields($documentId, $rubricId, $includeAssets)
		{
			$rows = DB::query(
				'SELECT f.rubric_field_alias,f.rubric_field_type,df.field_value,dft.field_value AS field_value_more'
				. ' FROM ' . ContentTables::table('rubric_fields') . ' f'
				. ' LEFT JOIN ' . ContentTables::table('document_fields') . ' df'
				. ' ON df.rubric_field_id=f.Id AND df.document_id=%i'
				. ' LEFT JOIN ' . ContentTables::table('document_fields_text') . ' dft'
				. ' ON dft.rubric_field_id=f.Id AND dft.document_id=%i'
				. ' WHERE f.rubric_id=%i ORDER BY f.Id ASC',
				(int) $documentId,
				(int) $documentId,
				(int) $rubricId
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$alias = trim((string) $row['rubric_field_alias']);
				if ($alias === '' || (!$includeAssets && in_array((string) $row['rubric_field_type'], self::$assetFieldTypes, true))) {
					continue;
				}

				$value = (string) $row['field_value'] . (string) $row['field_value_more'];
				if ($value !== '') { $out[$alias] = $value; }
			}

			return $out;
		}

		protected function hydrate(array $row)
		{
			$document = $this->decode($row['document_json']);
			$fields = $this->decode($row['fields_json']);
			return array(
				'id' => (int) $row['id'],
				'rubric_id' => (int) $row['rubric_id'],
				'rubric_title' => isset($row['rubric_title']) ? (string) $row['rubric_title'] : '',
				'title' => (string) $row['title'],
				'description' => (string) $row['description'],
				'document' => $document,
				'fields' => $fields,
				'source_document_id' => (int) $row['source_document_id'],
				'is_active' => !empty($row['is_active']),
				'sort_order' => (int) $row['sort_order'],
				'created_by' => (int) $row['created_by'],
				'created_at' => (int) $row['created_at'],
				'updated_at' => (int) $row['updated_at'],
				'fields_count' => count($fields),
			);
		}

		protected function nextPosition($rubricId)
		{
			return (int) DB::query(
				'SELECT COALESCE(MAX(sort_order),0)+10 FROM ' . $this->table() . ' WHERE rubric_id=%i',
				(int) $rubricId
			)->getValue();
		}

		protected function encode(array $value)
		{
			$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (!is_string($json)) { throw new \RuntimeException('Не удалось подготовить данные пресета'); }
			return $json;
		}

		protected function decode($value)
		{
			$decoded = json_decode((string) $value, true);
			return is_array($decoded) ? $decoded : array();
		}

		protected function table()
		{
			return ContentTables::table('document_creation_presets');
		}
	}
