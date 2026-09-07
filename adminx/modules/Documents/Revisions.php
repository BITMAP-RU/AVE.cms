<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/Revisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	use App\Content\Documents\DocumentRevisionPayload;
	use App\Content\Revisions\JsonRevisionStore;

	class Revisions
	{
		const FORMAT = 'ave.document-revision';
		const VERSION = 2;

		public static function listForDocument($documentId, $limit = 50)
		{
			$rows = DB::query(
				'SELECT * FROM ' . Model::revisionsTable() . ' WHERE doc_id = %i ORDER BY doc_revision DESC, Id DESC LIMIT %i',
				(int) $documentId,
				max(1, min(200, (int) $limit))
			)->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::format($row, false);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . Model::revisionsTable() . ' WHERE Id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::format($row, true) : null;
		}

		public static function capture($documentId, $authorId = 0)
		{
			$documentId = (int) $documentId;
			$document = Model::one($documentId);
			if (!$document) { return 0; }
			$values = Model::currentFieldValues($documentId);
			$payload = self::payload($document, $values);

			$last = DB::query(
				'SELECT doc_data FROM ' . Model::revisionsTable() . ' WHERE doc_id = %i ORDER BY doc_revision DESC, Id DESC LIMIT 1',
				$documentId
			)->getValue();
			$lastPayload = self::decodePayload((string) $last);
			if (!empty($lastPayload['full']) && self::sameValues($payload, $lastPayload['raw'])) {
				return 0;
			}

			$revisionTime = !empty($document['document_changed']) ? (int) $document['document_changed'] : time();
			DB::Insert(Model::revisionsTable(), array(
				'doc_id' => $documentId,
				'doc_revision' => $revisionTime,
				'doc_data' => serialize($payload),
				'user_id' => (int) $authorId,
			));
			return (int) DB::insertId();
		}

		public static function restore($revisionId, $authorId = 0)
		{
			return self::restoreData($revisionId, (int) $authorId, null, null);
		}

		public static function restoreSelected($revisionId, array $documentKeys, array $fieldIds, $authorId = 0)
		{
			$documentKeys = array_values(array_unique(array_filter(array_map('strval', $documentKeys), 'strlen')));
			$fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), function ($id) { return $id > 0; })));
			if (count($documentKeys) > 64 || count($fieldIds) > 1000) {
				throw new \RuntimeException('В ревизии выбрано слишком много элементов');
			}

			if (empty($documentKeys) && empty($fieldIds)) {
				throw new \RuntimeException('Выберите хотя бы один параметр или поле');
			}

			return self::restoreData($revisionId, (int) $authorId, $documentKeys, $fieldIds);
		}

		protected static function restoreData($revisionId, $authorId, $documentKeys, $fieldIds)
		{
			$revision = self::one($revisionId);
			if (!$revision || !is_array($revision['values'])) {
				throw new \RuntimeException('Ревизия не найдена');
			}

			$partial = is_array($documentKeys) || is_array($fieldIds);
			$documentState = !empty($revision['document']) ? $revision['document'] : array();
			$fieldValues = is_array($revision['values']) ? $revision['values'] : array();
			if ($partial) {
				$selection = DocumentRevisionPayload::select(
					$documentState,
					$fieldValues,
					is_array($documentKeys) ? $documentKeys : array(),
					is_array($fieldIds) ? $fieldIds : array()
				);
				$documentState = $selection['document'];
				$fieldValues = $selection['fields'];
				if (empty($documentState) && empty($fieldValues)) {
					throw new \RuntimeException('Выбранных данных нет в этой ревизии');
				}
			}

			$documentId = (int) $revision['doc_id'];
			$ownsTransaction = !DB::$transaction_in_progress;
			if ($ownsTransaction) { DB::startTransaction(); }
			try {
				self::capture($documentId, (int) $authorId);
				if (!empty($documentState)) {
					Model::restoreDocumentState($documentId, $documentState, (int) $authorId);
				}

				if (!empty($fieldValues)) {
					Model::restoreFieldValues($documentId, $fieldValues);
				}

				if ($ownsTransaction) { DB::commit(); }
			} catch (\Throwable $e) {
				if ($ownsTransaction) { DB::rollback(); }
				throw $e;
			}

			return $documentId;
		}

		public static function delete($revisionId)
		{
			$revision = self::one($revisionId);
			if (!$revision) {
				return false;
			}

			DB::Delete(Model::revisionsTable(), 'Id = %i', (int) $revisionId);
			return (int) $revision['doc_id'];
		}

		public static function deleteForDocument($documentId)
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0) {
				return 0;
			}

			$count = (int) DB::query('SELECT COUNT(*) FROM ' . Model::revisionsTable() . ' WHERE doc_id = %i', $documentId)->getValue();
			DB::Delete(Model::revisionsTable(), 'doc_id = %i', $documentId);
			return $count;
		}

		protected static function format(array $row, $withData)
		{
			$created = isset($row['doc_revision']) ? (int) $row['doc_revision'] : 0;
			$payload = self::decodePayload((string) $row['doc_data']);
			$values = $withData ? $payload['fields'] : null;
			$document = $withData ? $payload['document'] : null;
			$documentPreview = $withData ? self::documentPreview($payload['document']) : array();
			$fieldPreview = $withData && is_array($values) ? self::preview($values) : array();
			$comparison = array('document' => array(), 'fields' => array());
			if ($withData) {
				$currentDocument = Model::one((int) $row['doc_id']);
				$currentFields = Model::currentFieldValues((int) $row['doc_id']);
				$comparison['document'] = JsonRevisionStore::compareSnapshots($currentDocument ?: array(), $payload['document']);
				$comparison['fields'] = JsonRevisionStore::compareSnapshots($currentFields, $payload['fields']);
				foreach ($documentPreview as &$item) {
					$item['changed'] = !empty($comparison['document']['items'][$item['key']]['changed']);
				}

				unset($item);
				foreach ($fieldPreview as &$item) {
					$key = (string) $item['field_id'];
					$item['changed'] = !empty($comparison['fields']['items'][$key]['changed']);
				}

				unset($item);
			}

			return array(
				'id' => (int) $row['Id'],
				'doc_id' => (int) $row['doc_id'],
				'doc_revision' => $created,
				'created_label' => $created > 0 ? date('d.m.Y H:i:s', $created) : '-',
				'user_id' => (int) $row['user_id'],
				'author_name' => self::authorName((int) $row['user_id']),
				'fields_count' => count($payload['fields']),
				'format_version' => $payload['full'] ? self::VERSION : 1,
				'format_label' => $payload['full'] ? 'Полный снимок' : 'Только поля',
				'size_label' => self::formatBytes(strlen((string) $row['doc_data'])),
				'values' => $values,
				'document' => $document,
				'document_preview' => $documentPreview,
				'preview' => $fieldPreview,
				'comparison' => $comparison,
			);
		}

		protected static function payload(array $document, array $fields)
		{
			return DocumentRevisionPayload::create($document, $fields);
		}

		protected static function decodePayload($serialized)
		{
			return DocumentRevisionPayload::decodeSerialized($serialized);
		}

		protected static function documentPreview(array $document)
		{
			$labels = array(
				'document_title' => 'Название', 'document_alias' => 'Alias (URL)', 'document_short_alias' => 'Короткий alias',
				'document_breadcrumb_title' => 'Хлебная крошка', 'document_excerpt' => 'Краткое описание',
				'document_meta_keywords' => 'Meta keywords', 'document_meta_description' => 'Meta description',
				'document_meta_robots' => 'Meta robots', 'document_tags' => 'Теги', 'document_property' => 'Артикул / свойство',
				'guid' => 'GUID', 'document_status' => 'Статус', 'document_in_search' => 'Поиск',
				'document_parent' => 'Родитель', 'rubric_tmpl_id' => 'Шаблон', 'document_linked_navi_id' => 'Навигация',
				'document_position' => 'Позиция', 'document_published' => 'Дата публикации', 'document_expire' => 'Дата окончания',
				'document_author_id' => 'Автор', 'document_sitemap_freq' => 'Sitemap: частота', 'document_sitemap_pr' => 'Sitemap: приоритет',
				'document_in_sitemap' => 'Sitemap: участие', 'document_is_technical' => 'Служебный документ',
			);
			$out = array();
			foreach ($document as $key => $value) {
				if (!isset($labels[$key])) { continue; }
				if (in_array($key, array('document_published', 'document_expire'), true)) {
					$value = (int) $value > 0 ? date('d.m.Y H:i:s', (int) $value) : 'не задано';
				} elseif ($key === 'document_status') {
					$value = (int) $value === 1 ? 'Опубликован' : 'Черновик';
					} elseif ($key === 'document_in_search') {
						$value = (int) $value === 1 ? 'В поиске' : 'Скрыт';
					} elseif ($key === 'document_in_sitemap') {
						$value = (int) $value === 1 ? 'Добавляется' : 'Не добавляется';
					} elseif ($key === 'document_is_technical') {
						$value = (int) $value === 1 ? 'Служебный' : 'Обычный';
				}

				$out[] = array('key' => $key, 'title' => $labels[$key], 'value' => (string) $value, 'value_preview' => self::shorten((string) $value, 420));
			}

			return $out;
		}

		protected static function preview(array $values)
		{
			$fieldIds = array();
			foreach (array_keys($values) as $fieldId) {
				$fieldId = (int) $fieldId;
				if ($fieldId > 0) { $fieldIds[] = $fieldId; }
			}

			$fields = array();
			if (!empty($fieldIds)) {
				$rows = DB::query(
					'SELECT Id, rubric_field_title, rubric_field_alias, rubric_field_type FROM ' . Model::fieldsTable()
					. ' WHERE Id IN (' . implode(',', $fieldIds) . ')'
				)->getAll();
				foreach ($rows as $row) {
					$fields[(int) $row['Id']] = array(
						'title' => self::decode(isset($row['rubric_field_title']) ? $row['rubric_field_title'] : ''),
						'alias' => isset($row['rubric_field_alias']) ? (string) $row['rubric_field_alias'] : '',
						'type' => isset($row['rubric_field_type']) ? (string) $row['rubric_field_type'] : '',
					);
				}
			}

			$out = array();
			foreach ($values as $fieldId => $value) {
				$meta = isset($fields[(int) $fieldId]) ? $fields[(int) $fieldId] : array();
				$out[] = array(
					'field_id' => (int) $fieldId,
					'title' => !empty($meta['title']) ? $meta['title'] : (!empty($meta['alias']) ? $meta['alias'] : 'Поле #' . (int) $fieldId),
					'alias' => isset($meta['alias']) ? $meta['alias'] : '',
					'type' => isset($meta['type']) ? $meta['type'] : '',
					'value' => (string) $value,
					'value_preview' => self::shorten((string) $value, 420),
					'size_label' => self::formatBytes(strlen((string) $value)),
				);
			}

			return $out;
		}

		protected static function decode($value)
		{
			return htmlspecialchars_decode(stripcslashes((string) $value), ENT_QUOTES);
		}

		protected static function sameValues(array $a, array $b)
		{
			ksort($a);
			ksort($b);
			return serialize($a) === serialize($b);
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) {
				return '';
			}

			$columns = self::userColumns();
			$idField = in_array('id', $columns, true) ? 'id' : (in_array('Id', $columns, true) ? 'Id' : 'id');
			$row = DB::query('SELECT * FROM ' . SystemTables::table('users') . ' WHERE ' . $idField . ' = %i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) {
				return '#' . (int) $id;
			}

			$legacyName = trim((isset($row['firstname']) ? (string) $row['firstname'] : '') . ' ' . (isset($row['lastname']) ? (string) $row['lastname'] : ''));
			if ($legacyName !== '') {
				return $legacyName;
			}

			if (!empty($row['name'])) {
				return (string) $row['name'];
			}

			if (!empty($row['login'])) {
				return '@' . (string) $row['login'];
			}

			if (!empty($row['user_name'])) {
				return '@' . (string) $row['user_name'];
			}

			if (!empty($row['email'])) {
				return (string) $row['email'];
			}

			return '#' . (int) $id;
		}

		protected static function userColumns()
		{
			static $columns = null;
			if ($columns !== null) {
				return $columns;
			}

			$columns = array();
			$rows = DB::query('SHOW COLUMNS FROM ' . SystemTables::table('users'))->getAll();
			foreach ($rows as $row) {
				$columns[] = (string) $row['Field'];
			}

			return $columns;
		}

		protected static function shorten($value, $limit)
		{
			$value = trim((string) $value);
			if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) {
				return mb_substr($value, 0, $limit, 'UTF-8') . '...';
			}

			if (strlen($value) > $limit) {
				return substr($value, 0, $limit) . '...';
			}

			return $value;
		}

		protected static function formatBytes($bytes)
		{
			$bytes = (int) $bytes;
			if ($bytes < 1024) {
				return $bytes . ' Б';
			}

			if ($bytes < 1048576) {
				return round($bytes / 1024, 1) . ' KB';
			}

			return round($bytes / 1048576, 1) . ' MB';
		}
	}
