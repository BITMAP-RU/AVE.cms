<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/AdminView.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Content\ContentTables;
	use App\Content\Fields\FieldSettings;
	use App\Helpers\Json;
	use App\Helpers\Str;
	use DB;

	/** Настраиваемое представление документов конкретной рубрики в Adminx. */
	class AdminView
	{
		const MAX_ITEMS = 10;

		public static function table()
		{
			return ContentTables::table('rubric_admin_views');
		}

		public static function defaults($rubricId)
		{
			return array(
				'rubric_id' => (int) $rubricId,
				'mode' => 'standard',
				'items' => array(),
				'template' => '',
				'updated_at' => '',
			);
		}

		public static function get($rubricId)
		{
			$rubricId = (int) $rubricId;
			if ($rubricId <= 0) {
				return self::defaults(0);
			}

			try {
				$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE rubric_id = %i LIMIT 1', $rubricId)->getAssoc();
			} catch (\Throwable $e) {
				return self::defaults($rubricId);
			}

			if (!$row) {
				return self::defaults($rubricId);
			}

			$config = Json::toArray((string) $row['config_json'], array());
			return array(
				'rubric_id' => $rubricId,
				'mode' => self::mode(isset($row['mode']) ? $row['mode'] : 'standard'),
				'items' => isset($config['items']) && is_array($config['items']) ? array_values($config['items']) : array(),
				'template' => isset($config['template']) ? (string) $config['template'] : '',
				'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
			);
		}

		public static function fields($rubricId)
		{
			$rows = DB::query(
				'SELECT Id, rubric_field_title, rubric_field_alias, rubric_field_type, rubric_field_settings'
				. ' FROM ' . ContentTables::table('rubric_fields')
				. ' WHERE rubric_id = %i ORDER BY rubric_field_position ASC, Id ASC',
				(int) $rubricId
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = array(
					'id' => (int) $row['Id'],
					'source' => 'field:' . (int) $row['Id'],
					'title' => self::decode($row['rubric_field_title']),
					'alias' => (string) $row['rubric_field_alias'],
					'type' => (string) $row['rubric_field_type'],
					'format' => self::recommendedFormat((string) $row['rubric_field_type']),
				);
			}

			return $out;
		}

		public static function payload($rubricId)
		{
			$rubric = Model::one((int) $rubricId);
			if (!$rubric) {
				return null;
			}

			$view = self::normalizeView(self::get((int) $rubricId), self::fields((int) $rubricId));
			$preview = DB::query(
				'SELECT Id, rubric_id, document_title, document_alias, document_status, document_deleted, document_changed'
				. ' FROM ' . ContentTables::table('documents')
				. ' WHERE rubric_id = %i ORDER BY document_changed DESC, Id DESC LIMIT 3',
				(int) $rubricId
			)->getAll() ?: array();
			foreach ($preview as &$document) {
				$document['Id'] = (int) $document['Id'];
				$document['document_title'] = self::decode($document['document_title']);
			}

			unset($document);
			$preview = self::previewDocuments(self::fields((int) $rubricId), $preview);
			$preview = self::decorateDocuments($view, $preview);
			return array(
				'rubric' => array('Id' => (int) $rubric['Id'], 'rubric_title' => (string) $rubric['rubric_title']),
				'view' => $view,
				'fields' => self::fields((int) $rubricId),
				'preview' => $preview,
			);
		}

		public static function save($rubricId, array $input, $actorId)
		{
			$rubricId = (int) $rubricId;
			$fields = self::fields($rubricId);
			$items = isset($input['items']) ? $input['items'] : array();
			if (is_string($items)) {
				$items = Json::toArray($items, array());
			}

			$view = self::normalizeView(array(
				'rubric_id' => $rubricId,
				'mode' => isset($input['mode']) ? $input['mode'] : 'standard',
				'items' => $items,
				'template' => isset($input['template']) ? $input['template'] : '',
			), $fields, true);
			$config = Json::encode(array(
				'items' => $view['items'],
				'template' => $view['template'],
			), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			DB::query(
				'INSERT INTO ' . self::table() . ' (rubric_id, mode, config_json, updated_by, updated_at)'
				. ' VALUES (%i, %s, %s, %i, NOW()) ON DUPLICATE KEY UPDATE mode = VALUES(mode),'
				. ' config_json = VALUES(config_json), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
				$rubricId,
				$view['mode'],
				$config,
				(int) $actorId
			);
			AuditLog::record('rubric.admin_view_saved', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'rubric',
				'target_id' => $rubricId,
				'meta' => array('mode' => $view['mode'], 'items' => count($view['items'])),
			));
			return $view;
		}

		public static function forDocuments($rubricId, array $documents)
		{
			$rubricId = (int) $rubricId;
			$view = self::normalizeView(self::get($rubricId), self::fields($rubricId));
			$view['active'] = $rubricId > 0 && $view['mode'] !== 'standard';
			$view['documents'] = $view['active'] ? self::decorateDocuments($view, $documents) : $documents;
			return $view;
		}

		protected static function normalizeView(array $view, array $fields, $strict = false)
		{
			foreach ($fields as $field) {
				$fieldMap[$field['source']] = $field;
			}

			$items = array();
			foreach (isset($view['items']) && is_array($view['items']) ? $view['items'] : array() as $item) {
				if (!is_array($item) || empty($item['source']) || !isset($fieldMap[(string) $item['source']])) {
					continue;
				}

				$field = $fieldMap[(string) $item['source']];
				$format = isset($item['format']) ? (string) $item['format'] : $field['format'];
				if (!in_array($format, array('text', 'badge', 'number', 'date', 'image'), true)) {
					$format = $field['format'];
				}

				$width = isset($item['width']) ? (string) $item['width'] : 'medium';
				if (!in_array($width, array('small', 'medium', 'wide'), true)) {
					$width = 'medium';
				}

				$items[] = array(
					'source' => $field['source'],
					'field_id' => $field['id'],
					'label' => mb_substr(trim(isset($item['label']) ? (string) $item['label'] : $field['title']), 0, 80),
					'format' => $format,
					'width' => $width,
					'type' => $field['type'],
					'alias' => $field['alias'],
				);
				if (count($items) >= self::MAX_ITEMS) {
					break;
				}
			}

			$template = trim(isset($view['template']) ? (string) $view['template'] : '');
			if (strlen($template) > 20000) {
				$template = substr($template, 0, 20000);
			}

			if ($strict) {
				self::assertSafeTemplate($template);
			}

			return array(
				'rubric_id' => isset($view['rubric_id']) ? (int) $view['rubric_id'] : 0,
				'mode' => self::mode(isset($view['mode']) ? $view['mode'] : 'standard'),
				'items' => $items,
				'template' => $template,
				'updated_at' => isset($view['updated_at']) ? (string) $view['updated_at'] : '',
			);
		}

		protected static function decorateDocuments(array $view, array $documents)
		{
			$docIds = array();
			$fieldIds = array();
			foreach ($documents as $document) {
				$docIds[] = (int) $document['Id'];
			}

			foreach ($view['items'] as $item) {
				$fieldIds[] = (int) $item['field_id'];
			}

			$values = array();
			if (!empty($docIds) && !empty($fieldIds)) {
				$rows = DB::query(
					'SELECT df.document_id, df.rubric_field_id, df.field_value, dft.field_value AS field_value_more,'
					. ' rf.rubric_field_type, rf.rubric_field_default, rf.rubric_field_settings'
					. ' FROM ' . ContentTables::table('document_fields') . ' df'
					. ' INNER JOIN ' . ContentTables::table('rubric_fields') . ' rf ON rf.Id = df.rubric_field_id'
					. ' LEFT JOIN ' . ContentTables::table('document_fields_text') . ' dft'
					. ' ON dft.document_id = df.document_id AND dft.rubric_field_id = df.rubric_field_id'
					. ' WHERE df.document_id IN %li AND df.rubric_field_id IN %li',
					array_values(array_unique($docIds)),
					array_values(array_unique($fieldIds))
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$raw = (string) $row['field_value'] . (string) $row['field_value_more'];
					$values[(int) $row['document_id']][(int) $row['rubric_field_id']] = self::displayValue($raw, $row);
				}
			}

			foreach ($documents as &$document) {
				$document['admin_view_values'] = array();
				foreach ($view['items'] as $item) {
					$document['admin_view_values'][] = array(
						'label' => $item['label'],
						'format' => $item['format'],
						'width' => $item['width'],
						'value' => isset($values[(int) $document['Id']][(int) $item['field_id']])
							? $values[(int) $document['Id']][(int) $item['field_id']]
							: array('text' => '', 'image' => ''),
					);
				}

				$document['admin_view_html'] = $view['mode'] === 'custom'
					? self::renderTemplate($view['template'], $document, $view['items'])
					: '';
			}

			unset($document);
			return $documents;
		}

		protected static function previewDocuments(array $fields, array $documents)
		{
			$docIds = array();
			$fieldIds = array();
			$fieldMap = array();
			foreach ($documents as $document) { $docIds[] = (int) $document['Id']; }
			foreach ($fields as $field) {
				$fieldIds[] = (int) $field['id'];
			}

			$values = array();
			if (!empty($docIds) && !empty($fieldIds)) {
				$rows = DB::query(
					'SELECT df.document_id, df.rubric_field_id, df.field_value, dft.field_value AS field_value_more,'
					. ' rf.rubric_field_type, rf.rubric_field_default, rf.rubric_field_settings'
					. ' FROM ' . ContentTables::table('document_fields') . ' df'
					. ' INNER JOIN ' . ContentTables::table('rubric_fields') . ' rf ON rf.Id = df.rubric_field_id'
					. ' LEFT JOIN ' . ContentTables::table('document_fields_text') . ' dft'
					. ' ON dft.document_id = df.document_id AND dft.rubric_field_id = df.rubric_field_id'
					. ' WHERE df.document_id IN %li AND df.rubric_field_id IN %li',
					array_values(array_unique($docIds)),
					array_values(array_unique($fieldIds))
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$fieldId = (int) $row['rubric_field_id'];
					$raw = (string) $row['field_value'] . (string) $row['field_value_more'];
					$values[(int) $row['document_id']]['field:' . $fieldId] = self::displayValue($raw, $row);
				}
			}

			foreach ($documents as &$document) {
				$document['admin_field_values'] = isset($values[(int) $document['Id']])
					? $values[(int) $document['Id']]
					: array();
			}

			unset($document);
			return $documents;
		}

		protected static function displayValue($raw, array $field)
		{
			$type = (string) $field['rubric_field_type'];
			$settings = FieldSettings::effective($field);
			$text = trim((string) $raw);
			if ($type === 'drop_down_key' && isset($settings['options']) && is_array($settings['options'])) {
				foreach ($settings['options'] as $option) {
					if (is_array($option) && isset($option['value']) && (string) $option['value'] === $text) {
						$text = isset($option['label']) ? (string) $option['label'] : $text;
						break;
					}
				}
			}

			$decoded = ($text !== '' && ($text[0] === '[' || $text[0] === '{'))
				? Json::toArray($text, array())
				: array();
			if (!empty($decoded)) {
				$flat = array();
				array_walk_recursive($decoded, function ($value) use (&$flat) {
					if (is_scalar($value) && trim((string) $value) !== '') { $flat[] = trim((string) $value); }
				});
				if (!empty($flat)) { $text = implode(', ', array_slice($flat, 0, 8)); }
			}

			$image = self::imageFromValue($raw);
			$text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8')));
			if (mb_strlen($text) > 180) { $text = mb_substr($text, 0, 177) . '...'; }
			return array('text' => $text, 'image' => $image);
		}

		protected static function renderTemplate($template, array $document, array $items)
		{
			$template = trim((string) $template);
			try {
				self::assertSafeTemplate($template);
			} catch (\RuntimeException $e) {
				$template = '';
			}

			if ($template === '') {
				$template = '<strong>[document:title]</strong><small>#[document:id] · [document:alias]</small>';
			}

			$replace = array(
				'[document:id]' => (string) (int) $document['Id'],
				'[document:title]' => Str::escape(isset($document['document_title']) ? $document['document_title'] : ''),
				'[document:alias]' => Str::escape(isset($document['document_alias']) ? $document['document_alias'] : ''),
			);
			foreach ($items as $index => $item) {
				$value = isset($document['admin_view_values'][$index]['value']['text']) ? $document['admin_view_values'][$index]['value']['text'] : '';
				$replace['[field:' . (int) $item['field_id'] . ']'] = Str::escape($value);
				if ($item['alias'] !== '') { $replace['[field:' . $item['alias'] . ']'] = Str::escape($value); }
			}

			return strtr($template, $replace);
		}

		protected static function assertSafeTemplate($template)
		{
			if ($template === '') { return; }
			if (preg_match('/<\?(?:php|=)?|<\s*(script|style|iframe|object|embed|form)\b|\son[a-z]+\s*=|javascript\s*:/i', $template)) {
				throw new \RuntimeException('В административном шаблоне запрещены PHP, JavaScript и исполняемые HTML-элементы');
			}
		}

		protected static function imageFromValue($raw)
		{
			if (preg_match('~(?:https?://[^\s"\']+|/[^\s"\']+)\.(?:jpe?g|png|gif|webp|avif)(?:\?[^\s"\']*)?~iu', (string) $raw, $match)) {
				return $match[0];
			}

			return '';
		}

		protected static function recommendedFormat($type)
		{
			$type = (string) $type;
			if (strpos($type, 'image') !== false || strpos($type, 'file') !== false) { return 'image'; }
			if (strpos($type, 'date') !== false) { return 'date'; }
			if (strpos($type, 'number') !== false || strpos($type, 'numeric') !== false) { return 'number'; }
			if (strpos($type, 'checkbox') !== false || $type === 'boolean') { return 'badge'; }
			return 'text';
		}

		protected static function mode($mode)
		{
			$mode = (string) $mode;
			return in_array($mode, array('standard', 'table', 'cards', 'custom'), true) ? $mode : 'standard';
		}

		protected static function decode($value)
		{
			return htmlspecialchars_decode(stripcslashes((string) $value), ENT_QUOTES);
		}
	}
