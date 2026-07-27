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
	}
