<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentRubricCodeRunner.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/** Executes editable rubric before/after code for non-Adminx writers. */
	class DocumentRubricCodeRunner
	{
		protected $documentId;
		protected $data;
		protected $fields;
		protected $saveRequested = false;

		public function __construct($documentId, array &$data, array &$fields)
		{
			$this->documentId = (int) $documentId;
			$this->data =& $data;
			$this->fields =& $fields;
		}

		public static function before($rubricId, array &$data, array &$fields, $documentId, $actorId, $isNew, $source)
		{
			return self::run('rubric_code_start', DocumentHookContext::BEFORE_SAVE, $rubricId, $data, $fields, $documentId, $actorId, $isNew, $source);
		}

		public static function after($rubricId, array &$data, array &$fields, $documentId, $actorId, $isNew, $source)
		{
			return self::run('rubric_code_end', DocumentHookContext::AFTER_SAVE, $rubricId, $data, $fields, $documentId, $actorId, $isNew, $source);
		}

		public function documentSave($rubricId, $documentId, array $data)
		{
			$this->data = $data;
			if (isset($data['field']) && is_array($data['field'])) { $this->fields = $data['field']; }
			elseif (isset($data['feld']) && is_array($data['feld'])) { $this->fields = $data['feld']; }
			$this->saveRequested = true;
			return $this->documentId > 0 ? $this->documentId : (int) $documentId;
		}

		protected static function run($column, $phase, $rubricId, array &$data, array &$fields, $documentId, $actorId, $isNew, $source)
		{
			$code = (string) DB::query('SELECT `' . $column . '` FROM ' . ContentTables::table('rubrics') . ' WHERE Id=%i LIMIT 1', (int) $rubricId)->getValue();
			if (trim($code) === '') { return array('executed' => false, 'save_requested' => false); }
			$aliases = array();
			$rows = DB::query('SELECT Id,rubric_field_alias FROM ' . ContentTables::table('rubric_fields') . ' WHERE rubric_id=%i', (int) $rubricId)->getAll();
			foreach ($rows ?: array() as $row) { if (trim((string) $row['rubric_field_alias']) !== '') { $aliases[(string) $row['rubric_field_alias']] = (int) $row['Id']; } }
			$originalTitle = isset($data['document_title']) ? (string) $data['document_title'] : '';
			$data['doc_title'] = $originalTitle;
			$data['field'] =& $fields;
			$data['feld'] =& $fields;
			$logs = array();
			$hook = new DocumentHookContext($phase, (int) $rubricId, (int) $documentId, $data, $fields, $logs, array(
				'actor_id' => (int) $actorId, 'is_new' => (bool) $isNew, 'source' => (string) $source, 'field_aliases' => $aliases,
			));
			$context = array('data' => &$data, 'fields' => &$fields, 'document_logs' => &$logs, 'document_id' => (int) $documentId, 'rubric_id' => (int) $rubricId);
			$runner = new self($documentId, $data, $fields);
			$result = DocumentHookRuntime::execute($code, $hook, $context, $runner);
			if (isset($data['doc_title']) && (string) $data['doc_title'] !== $originalTitle) { $data['document_title'] = (string) $data['doc_title']; }
			$result['save_requested'] = $runner->saveRequested;
			$result['logs'] = $logs;
			unset($data['field'], $data['feld'], $data['doc_title']);
			return $result;
		}
	}
