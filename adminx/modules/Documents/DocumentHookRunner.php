<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/DocumentHookRunner.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\Documents\DocumentHookContext;
	use App\Content\Documents\DocumentHookRuntime;

	class DocumentHookRunner
	{
		protected $documentId;
		protected $runtimeData;
		protected $runtimeFields;
		protected $saveRequested = false;

		public function __construct($documentId, &$runtimeData = null, &$runtimeFields = null)
		{
			$this->documentId = (int) $documentId;
			$this->runtimeData =& $runtimeData;
			$this->runtimeFields =& $runtimeFields;
		}

		public static function before($rubricId, array &$input, array &$fields, $documentId = 0, $actorId = 0, $isNew = null, $source = 'adminx')
		{
			return self::run(
				'rubric_code_start',
				DocumentHookContext::BEFORE_SAVE,
				$rubricId,
				$input,
				$fields,
				$documentId,
				$actorId,
				$isNew === null ? (int) $documentId <= 0 : (bool) $isNew,
				$source
			);
		}

		public static function after($rubricId, array &$input, array &$fields, $documentId, $actorId = 0, $isNew = false, $source = 'adminx')
		{
			return self::run(
				'rubric_code_end',
				DocumentHookContext::AFTER_SAVE,
				$rubricId,
				$input,
				$fields,
				$documentId,
				$actorId,
				(bool) $isNew,
				$source
			);
		}

		public function documentSave($rubricId, $documentId, array $data)
		{
			// Historical hooks recursively called documentSave() after changing
			// $data. Native persistence is performed once by the caller, but the
			// supplied values must still be returned to that save pipeline.
			if (is_array($this->runtimeData)) {
				$this->runtimeData = $data;
				if (isset($data['field']) && is_array($data['field'])) {
					$this->runtimeFields = $data['field'];
				} elseif (isset($data['feld']) && is_array($data['feld'])) {
					$this->runtimeFields = $data['feld'];
				}

				$this->runtimeData['field'] =& $this->runtimeFields;
				$this->runtimeData['feld'] =& $this->runtimeFields;
			}

			$this->saveRequested = true;
			return $this->documentId > 0 ? $this->documentId : (int) $documentId;
		}

		protected static function run($column, $phase, $rubricId, array &$input, array &$fields, $documentId, $actorId, $isNew, $source)
		{
			$code = (string) DB::query(
				'SELECT `' . $column . '` FROM ' . Model::rubricsTable() . ' WHERE Id = %i LIMIT 1',
				(int) $rubricId
			)->getValue();
			if (trim($code) === '') {
				return self::emptyResult();
			}

			$inputBefore = $input;
			$fieldsBefore = $fields;
			$field = self::legacyFields($fields);
			$data = $input;
			$originalTitle = isset($input['document_title']) ? (string) $input['document_title'] : '';
			$data['doc_title'] = $originalTitle;
			$data['field'] =& $field;
			$data['feld'] =& $field;
			$logs = array();
			$hook = new DocumentHookContext(
				$phase,
				(int) $rubricId,
				(int) $documentId,
				$data,
				$field,
				$logs,
				array(
					'actor_id' => (int) $actorId,
					'is_new' => (bool) $isNew,
					'source' => (string) $source,
					'field_aliases' => self::fieldAliases((int) $rubricId),
				)
			);
			$context = array(
				'data' => &$data,
				'fields' => &$field,
				'document_logs' => &$logs,
				'document_id' => (int) $documentId,
				'rubric_id' => (int) $rubricId,
			);
			$runner = new self($documentId, $data, $field);
			$runtime = DocumentHookRuntime::execute($code, $hook, $context, $runner);
			self::syncTitleAliases($data, $originalTitle);
			self::syncInput($input, $data);
			self::syncFields($fields, $field);
			foreach ($hook->skippedFieldIds() as $skippedFieldId) {
				unset($fields[(int) $skippedFieldId]);
			}

			return array_merge($runtime, array(
				'document_changed' => self::fingerprint($inputBefore) !== self::fingerprint($input),
				'fields_changed' => self::fingerprint($fieldsBefore) !== self::fingerprint($fields),
				'save_requested' => $runner->saveRequested,
				'logs' => $logs,
			));
		}

		protected static function emptyResult()
		{
			return array(
				'executed' => false,
				'duration' => 0.0,
				'output' => '',
				'document_changed' => false,
				'fields_changed' => false,
				'save_requested' => false,
				'logs' => array(),
			);
		}

		protected static function fieldAliases($rubricId)
		{
			$rows = DB::query(
				'SELECT Id, rubric_field_alias FROM ' . Model::fieldsTable() . ' WHERE rubric_id = %i',
				(int) $rubricId
			)->getAll();
			$aliases = array();
			foreach ($rows as $row) {
				$alias = trim(isset($row['rubric_field_alias']) ? (string) $row['rubric_field_alias'] : '');
				if ($alias !== '') {
					$aliases[$alias] = (int) $row['Id'];
				}
			}

			return $aliases;
		}

		protected static function fingerprint(array $value)
		{
			return md5(serialize($value));
		}

		protected static function syncTitleAliases(array &$data, $originalTitle)
		{
			$documentTitle = isset($data['document_title']) ? (string) $data['document_title'] : '';
			$legacyTitle = isset($data['doc_title']) ? (string) $data['doc_title'] : '';
			if ($documentTitle !== (string) $originalTitle) {
				$data['doc_title'] = $documentTitle;
			} elseif ($legacyTitle !== (string) $originalTitle) {
				$data['document_title'] = $legacyTitle;
			}
		}

		protected static function legacyFields(array $fields)
		{
			$legacy = array();
			foreach ($fields as $id => $value) {
				if (!is_array($value)) {
					$legacy[$id] = $value;
				} elseif (array_key_exists('items', $value)) {
					$legacy[$id] = $value['items'];
				} elseif (array_key_exists('raw', $value)) {
					$legacy[$id] = $value['raw'];
				} elseif (array_key_exists('url', $value)) {
					$legacy[$id] = array(
						'img' => $value['url'], 'url' => $value['url'],
						'descr' => isset($value['description']) ? $value['description'] : '',
						'description' => isset($value['description']) ? $value['description'] : '',
					);
				} else {
					$legacy[$id] = $value;
				}
			}

			return $legacy;
		}

		protected static function syncInput(array &$input, array $data)
		{
			if (!array_key_exists('document_breadcrumb_title', $data) && array_key_exists('document_breadcrum_title', $data)) {
				$data['document_breadcrumb_title'] = $data['document_breadcrum_title'];
			}

			if (!array_key_exists('document_excerpt', $data) && array_key_exists('document_teaser', $data)) {
				$data['document_excerpt'] = $data['document_teaser'];
			}

			foreach (array(
				'document_title', 'document_alias', 'document_alias_header', 'document_alias_history',
				'document_short_alias', 'document_breadcrumb_title', 'document_excerpt',
				'document_meta_keywords', 'document_meta_description', 'document_meta_robots',
				'document_sitemap_freq', 'document_sitemap_pr', 'document_in_sitemap', 'document_tags', 'document_property',
				'guid', 'document_status', 'document_in_search', 'document_is_technical', 'document_parent', 'rubric_tmpl_id',
				'document_linked_navi_id', 'document_position', 'document_published', 'document_expire',
				'document_author_id',
			) as $key) {
				if (array_key_exists($key, $data)) {
					$input[$key] = $data[$key];
				}
			}

			if (isset($data['doc_title'])) {
				$input['document_title'] = $data['doc_title'];
			}
		}

		protected static function syncFields(array &$fields, array $legacy)
		{
			foreach ($legacy as $id => $value) {
				if (!array_key_exists($id, $fields)) {
					continue;
				}

				if (is_array($fields[$id]) && array_key_exists('items', $fields[$id])) {
					$fields[$id]['items'] = is_array($value) ? $value : array();
				} elseif (is_array($fields[$id]) && array_key_exists('raw', $fields[$id])) {
					$fields[$id]['raw'] = is_array($value) ? '' : (string) $value;
				} elseif (is_array($fields[$id]) && array_key_exists('url', $fields[$id]) && is_array($value)) {
					$fields[$id]['url'] = isset($value['url']) ? $value['url'] : (isset($value['img']) ? $value['img'] : '');
					$fields[$id]['description'] = isset($value['description']) ? $value['description'] : (isset($value['descr']) ? $value['descr'] : '');
				} else {
					$fields[$id] = $value;
				}
			}
		}
	}
