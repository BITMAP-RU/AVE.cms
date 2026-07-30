<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/DocumentRelationIndex.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use DB;

	/** Materialized edge index for fast outgoing and reverse document relations. */
	class DocumentRelationIndex
	{
		protected static $available;

		public static function sync($documentId, array $fields)
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0 || !self::available()) { return false; }
			try {
				DB::Delete(self::table(), 'source_document_id=%i', $documentId);
				foreach ($fields as $field) {
					if (!is_array($field) || empty($field['relation']['items'])) { continue; }
					$fieldId = isset($field['id']) ? (int) $field['id'] : 0;
					$type = isset($field['type']) ? (string) $field['type'] : '';
					foreach ((array) $field['relation']['items'] as $item) {
						$targetId = is_array($item) && isset($item['document_id']) ? (int) $item['document_id'] : 0;
						if ($fieldId <= 0 || $targetId <= 0) { continue; }
						DB::Insert(self::table(), array(
							'source_document_id' => $documentId,
							'source_field_id' => $fieldId,
							'target_document_id' => $targetId,
							'relation_type' => $type,
							'position' => isset($item['position']) ? max(0, (int) $item['position']) : 0,
							'updated_at' => time(),
						));
					}
				}
			} catch (\Throwable $e) {
				error_log('Document relation index #' . $documentId . ': ' . $e->getMessage());
				return false;
			}

			return true;
		}

		public static function removeSource($documentId)
		{
			return self::remove('source_document_id', $documentId);
		}

		public static function removeDocument($documentId)
		{
			if (!self::available()) { return false; }
			$documentId = (int) $documentId;
			DB::Delete(self::table(), 'source_document_id=%i OR target_document_id=%i', $documentId, $documentId);
			return true;
		}

		public static function removeField($fieldId)
		{
			return self::remove('source_field_id', $fieldId);
		}

		public static function outgoing($documentId)
		{
			return self::edges('source_document_id', $documentId, 'position ASC,id ASC');
		}

		public static function incoming($documentId)
		{
			return self::edges('target_document_id', $documentId, 'source_document_id ASC,position ASC,id ASC');
		}

		/**
		 * Human-readable relation lists for administration and API consumers.
		 * Reverse relations are derived from the edge index and are never written
		 * back into document fields.
		 */
		public static function describe($documentId)
		{
			$documentId = (int) $documentId;
			if (!self::available() || $documentId <= 0) {
				return array('outgoing' => array(), 'incoming' => array());
			}

			$edges = self::table();
			$documents = ContentTables::table('documents');
			$rubrics = ContentTables::table('rubrics');
			$fields = ContentTables::table('rubric_fields');
			$select = 'SELECT e.id,e.source_document_id,e.source_field_id,e.target_document_id,e.relation_type,e.position,'
				. ' f.rubric_field_title field_title,f.rubric_field_alias field_alias,'
				. ' source.document_title source_title,source.document_alias source_alias,source.rubric_id source_rubric_id,'
				. ' target.document_title target_title,target.document_alias target_alias,target.rubric_id target_rubric_id,'
				. ' source_rubric.rubric_title source_rubric_title,target_rubric.rubric_title target_rubric_title'
				. ' FROM ' . $edges . ' e'
				. ' LEFT JOIN ' . $fields . ' f ON f.Id=e.source_field_id'
				. ' LEFT JOIN ' . $documents . ' source ON source.Id=e.source_document_id'
				. ' LEFT JOIN ' . $documents . ' target ON target.Id=e.target_document_id'
				. ' LEFT JOIN ' . $rubrics . ' source_rubric ON source_rubric.Id=source.rubric_id'
				. ' LEFT JOIN ' . $rubrics . ' target_rubric ON target_rubric.Id=target.rubric_id';

			$outgoing = DB::query(
				$select . ' WHERE e.source_document_id=%i ORDER BY e.position ASC,e.id ASC',
				$documentId
			)->getAll() ?: array();
			$incoming = DB::query(
				$select . ' WHERE e.target_document_id=%i ORDER BY source_rubric.rubric_title ASC,source.document_title ASC,e.id ASC',
				$documentId
			)->getAll() ?: array();

			return array(
				'outgoing' => self::normalizeDescriptions($outgoing),
				'incoming' => self::normalizeDescriptions($incoming),
			);
		}

		public static function resetAvailability()
		{
			self::$available = null;
		}

		protected static function edges($column, $documentId, $order)
		{
			if (!self::available() || (int) $documentId <= 0) { return array(); }
			return DB::query(
				'SELECT * FROM %b WHERE ' . $column . '=%i ORDER BY ' . $order,
				self::table(), (int) $documentId
			)->getAll() ?: array();
		}

		protected static function remove($column, $documentId)
		{
			if (!self::available() || (int) $documentId <= 0) { return false; }
			DB::Delete(self::table(), $column . '=%i', (int) $documentId);
			return true;
		}

		protected static function available()
		{
			if (self::$available === null) {
				try { self::$available = DatabaseSchema::tableExists(self::table()); }
				catch (\Throwable $e) { self::$available = false; }
			}

			return self::$available;
		}

		protected static function table()
		{
			return ContentTables::table('document_relation_edges');
		}

		protected static function normalizeDescriptions(array $rows)
		{
			foreach ($rows as &$row) {
				foreach (array('id', 'source_document_id', 'source_field_id', 'target_document_id', 'position', 'source_rubric_id', 'target_rubric_id') as $key) {
					$row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
				}

				foreach (array('field_title', 'field_alias', 'source_title', 'source_alias', 'target_title', 'target_alias', 'source_rubric_title', 'target_rubric_title') as $key) {
					$row[$key] = isset($row[$key]) ? trim(htmlspecialchars_decode(stripcslashes((string) $row[$key]), ENT_QUOTES)) : '';
				}
			}

			unset($row);

			return $rows;
		}
	}
