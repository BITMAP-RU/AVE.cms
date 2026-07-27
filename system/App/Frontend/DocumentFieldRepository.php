<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentFieldRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Documents\DocumentSnapshotRepository;
	use DB;

	/** Loads the complete EAV field context required by public renderers. */
	class DocumentFieldRepository
	{
		protected static $cache = array();
		protected static $revisionOverrides = array();

		public function all($documentId, array $overrides = null)
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0) {
				return array();
			}

			if ($overrides === null && array_key_exists($documentId, self::$revisionOverrides)) {
				$overrides = self::$revisionOverrides[$documentId];
			}

			if ($overrides === null && isset(self::$cache[$documentId])) {
				return self::$cache[$documentId];
			}

			if ($overrides === null) {
				$snapshots = new DocumentSnapshotRepository();
				$snapshot = $snapshots->find($documentId);
				$schema = is_array($snapshot) ? $snapshots->schema($snapshot) : null;
				if (is_array($snapshot) && is_array($schema)) {
					$fields = $this->fromSnapshot($snapshot, $schema);
					self::$cache[$documentId] = $fields;
					return $fields;
				}
			}

			$rows = DB::query(
				'SELECT doc.document_author_id, doc_field.Id, doc_field.document_id,'
				. ' doc_field.rubric_field_id, doc_field.field_value,'
				. ' text_field.field_value AS field_value_more, rub_field.rubric_id,'
				. ' rub_field.rubric_field_alias, rub_field.rubric_field_type,'
				. ' rub_field.rubric_field_default, rub_field.rubric_field_numeric,'
				. ' rub_field.rubric_field_settings,'
				. ' rub_field.rubric_field_title, rub_field.rubric_field_template,'
				. ' rub_field.rubric_field_template_request'
				. ' FROM %b doc_field'
				. ' JOIN %b rub_field ON doc_field.rubric_field_id = rub_field.Id'
				. ' LEFT JOIN %b text_field ON doc_field.rubric_field_id = text_field.rubric_field_id'
				. ' AND doc_field.document_id = text_field.document_id'
				. ' JOIN %b doc ON doc.Id = doc_field.document_id'
				. ' WHERE doc_field.document_id = %i',
				ContentTables::table('document_fields'),
				ContentTables::table('rubric_fields'),
				ContentTables::table('document_fields_text'),
				ContentTables::table('documents'),
				$documentId
			)->getAll();

			$fields = array();
			foreach ($rows ?: array() as $row) {
				$fieldId = (int) $row['rubric_field_id'];
				$value = (string) $row['field_value'] . (string) $row['field_value_more'];
				if ($overrides !== null && array_key_exists($fieldId, $overrides)) {
					$value = $overrides[$fieldId];
				}

				$requestTemplate = self::resolveEmptyBlocks((string) $row['rubric_field_template_request'], $value);
				$documentTemplate = self::resolveEmptyBlocks((string) $row['rubric_field_template'], $value);
				$fields[$fieldId] = array(
					'Id' => $row['Id'],
					'document_id' => $row['document_id'],
					'document_author_id' => $row['document_author_id'],
					'rubric_field_id' => $fieldId,
					'field_value' => $value,
					'field_value_more' => $row['field_value_more'],
					'tpl_req_empty' => trim((string) $row['rubric_field_template_request']) === '',
					'tpl_field_empty' => trim((string) $row['rubric_field_template']) === '',
					'rubric_id' => $row['rubric_id'],
					'rubric_field_alias' => $row['rubric_field_alias'],
					'rubric_field_type' => $row['rubric_field_type'],
					'rubric_field_default' => $row['rubric_field_default'],
					'rubric_field_numeric' => $row['rubric_field_numeric'],
					'rubric_field_settings' => $row['rubric_field_settings'],
					'rubric_field_title' => $row['rubric_field_title'],
					'rubric_field_template' => $documentTemplate,
					'rubric_field_template_request' => $requestTemplate,
				);
				$alias = trim((string) $row['rubric_field_alias']);
				if ($alias !== '') {
					$fields[$alias] = $fieldId;
				}
			}

			if ($overrides === null) {
				self::$cache[$documentId] = $fields;
			}

			return $fields;
		}

		public function field($documentId, $identifier, array $overrides = null)
		{
			$fields = $this->all($documentId, $overrides);
			if (isset($fields[$identifier]) && !is_array($fields[$identifier])) {
				$identifier = (int) $fields[$identifier];
			}

			return isset($fields[$identifier]) && is_array($fields[$identifier])
				? $fields[$identifier]
				: null;
		}

		public function preload(array $documentIds)
		{
			$ids = array_values(array_unique(array_filter(array_map('intval', $documentIds), function ($id) {
				return $id > 0;
			})));
			$missing = array();
			foreach ($ids as $documentId) {
				if (!isset(self::$cache[$documentId]) && !array_key_exists($documentId, self::$revisionOverrides)) {
					$missing[] = $documentId;
				}
			}

			if ($missing) {
				$snapshots = new DocumentSnapshotRepository();
				foreach ($snapshots->findMany($missing) as $documentId => $snapshot) {
					$schema = is_array($snapshot) ? $snapshots->schema($snapshot) : null;
					if (is_array($snapshot) && is_array($schema)) {
						self::$cache[(int) $documentId] = $this->fromSnapshot($snapshot, $schema);
					}
				}
			}

			$result = array();
			foreach ($ids as $documentId) {
				if (isset(self::$cache[$documentId])) {
					$result[$documentId] = self::$cache[$documentId];
				}
			}

			return $result;
		}

		public static function reset($documentId = null)
		{
			if ($documentId === null) {
				self::$cache = array();
				DocumentSnapshotRepository::reset();
				return;
			}

			unset(self::$cache[(int) $documentId]);
			DocumentSnapshotRepository::reset((int) $documentId);
		}

		public static function useRevision($documentId, array $values)
		{
			$documentId = (int) $documentId;
			if ($documentId > 0) {
				self::$revisionOverrides[$documentId] = $values;
				unset(self::$cache[$documentId]);
			}
		}

		public static function hasRevision($documentId)
		{
			return array_key_exists((int) $documentId, self::$revisionOverrides);
		}

		public static function clearRevision($documentId = null)
		{
			if ($documentId === null) {
				self::$revisionOverrides = array();
				return;
			}

			unset(self::$revisionOverrides[(int) $documentId]);
		}

		protected function fromSnapshot(array $snapshot,array $schema)
		{
			$fields=array();$record=isset($snapshot['record'])&&is_array($snapshot['record'])?$snapshot['record']:array();
			foreach(isset($snapshot['fields'])&&is_array($snapshot['fields'])?$snapshot['fields']:array() as $id=>$value){$fieldId=(int)$id;$definition=isset($schema['fields'][(string)$fieldId])&&is_array($schema['fields'][(string)$fieldId])?$schema['fields'][(string)$fieldId]:array();if(!$definition){continue;}$raw=isset($value['raw'])?(string)$value['raw']:'';$requestTemplate=self::resolveEmptyBlocks(isset($definition['rubric_field_template_request'])?(string)$definition['rubric_field_template_request']:'',$raw);$documentTemplate=self::resolveEmptyBlocks(isset($definition['rubric_field_template'])?(string)$definition['rubric_field_template']:'',$raw);$fields[$fieldId]=array('Id'=>isset($value['row_id'])?(int)$value['row_id']:0,'document_id'=>isset($record['Id'])?(int)$record['Id']:0,'document_author_id'=>isset($record['document_author_id'])?(int)$record['document_author_id']:0,'rubric_field_id'=>$fieldId,'field_value'=>$raw,'field_value_more'=>isset($value['text_value'])?(string)$value['text_value']:'','normalized_value'=>isset($value['value'])?$value['value']:null,'tpl_req_empty'=>trim(isset($definition['rubric_field_template_request'])?(string)$definition['rubric_field_template_request']:'')==='','tpl_field_empty'=>trim(isset($definition['rubric_field_template'])?(string)$definition['rubric_field_template']:'')==='','rubric_id'=>isset($definition['rubric_id'])?(int)$definition['rubric_id']:0,'rubric_field_alias'=>isset($definition['rubric_field_alias'])?(string)$definition['rubric_field_alias']:'','rubric_field_type'=>isset($definition['rubric_field_type'])?(string)$definition['rubric_field_type']:'','rubric_field_default'=>isset($definition['rubric_field_default'])?(string)$definition['rubric_field_default']:'','rubric_field_numeric'=>isset($definition['rubric_field_numeric'])?(int)$definition['rubric_field_numeric']:0,'rubric_field_settings'=>isset($definition['rubric_field_settings'])?$definition['rubric_field_settings']:'','rubric_field_title'=>isset($definition['rubric_field_title'])?(string)$definition['rubric_field_title']:'','rubric_field_template'=>$documentTemplate,'rubric_field_template_request'=>$requestTemplate);$alias=trim((string)$fields[$fieldId]['rubric_field_alias']);if($alias!==''){$fields[$alias]=$fieldId;}}
			return $fields;
		}

		protected static function resolveEmptyBlocks($template, $value)
		{
			if ((string) $value === '') {
				$template = preg_replace('/\[tag:if_notempty](.*?)\[\/tag:if_notempty]/si', '', $template);
				return trim(str_replace(array('[tag:if_empty]', '[/tag:if_empty]'), '', $template));
			}

			$template = preg_replace('/\[tag:if_empty](.*?)\[\/tag:if_empty]/si', '', $template);
			return trim(str_replace(array('[tag:if_notempty]', '[/tag:if_notempty]'), '', $template));
		}
	}
