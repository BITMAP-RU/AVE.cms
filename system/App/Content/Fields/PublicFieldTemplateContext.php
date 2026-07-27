<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/PublicFieldTemplateContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Frontend\Media\ThumbnailUrl;
	use App\Helpers\Str;
	use DB;

	/** Data and helpers exposed to native public field templates. */
	class PublicFieldTemplateContext
	{
		protected $type;
		protected $mode;
		protected $value;
		protected $field;
		protected $templateId;
		protected $items;

		public function __construct($type, $mode, $value, array $field, $templateId = null)
		{
			$this->type = (string) $type;
			$this->mode = (string) $mode;
			$this->value = $value;
			$this->field = $field;
			$this->templateId = $templateId === null || $templateId === '' ? null : (string) $templateId;
			$this->items = null;
		}

		public function type()
		{
			return $this->type;
		}

		public function mode()
		{
			return $this->mode;
		}

		public function value()
		{
			return $this->value;
		}

		public function field()
		{
			return $this->field;
		}

		public function fieldId()
		{
			return isset($this->field['rubric_field_id'])
				? (int) $this->field['rubric_field_id']
				: (isset($this->field['Id']) ? (int) $this->field['Id'] : 0);
		}

		public function alias()
		{
			return isset($this->field['rubric_field_alias']) ? trim((string) $this->field['rubric_field_alias']) : '';
		}

		public function templateId()
		{
			return $this->templateId;
		}

		public function isEmpty()
		{
			return trim((string) $this->value) === '' || empty($this->items());
		}

		public function items()
		{
			if ($this->items !== null) {
				return $this->items;
			}

			if ($this->type === 'image_single') {
				$image = MediaFieldValue::imageSingle($this->value);
				return $this->items = $image['url'] === '' ? array() : array($image);
			}

			if ($this->type === 'image_multi') {
				return $this->items = MediaFieldValue::imageMulti($this->value);
			}

			if ($this->type === 'image_mega') {
				return $this->items = MediaFieldValue::imageMega($this->value);
			}

			if (in_array($this->type, array(
				'doc_from_rub', 'doc_from_rub_all', 'doc_from_rub_check', 'doc_from_rub_multi',
				'doc_from_rub_search', 'analoque', 'teasers'
			), true)) {
				return $this->items = $this->relationItems();
			}

			if (in_array($this->type, array('multi_links', 'multi_list', 'multi_list_single', 'multi_list_triple'), true)) {
				return $this->items = $this->listItems();
			}

			$structured = FieldValueCodec::decodeStructured($this->value, null);
			if (is_array($structured)) {
				return $this->items = $structured;
			}

			return $this->items = trim((string) $this->value) === '' ? array() : array((string) $this->value);
		}

		public function first()
		{
			$items = $this->items();
			return isset($items[0]) ? $items[0] : null;
		}

		public function escape($value)
		{
			return Str::escape((string) $value);
		}

		public function thumbnail($url, $size)
		{
			$result = ThumbnailUrl::make(array('link' => (string) $url, 'size' => (string) $size));
			return $result !== false ? $result : (string) $url;
		}

		public function webp($url)
		{
			return preg_replace('/\.[^.\/]+$/', '.webp', (string) $url);
		}

		public function host()
		{
			return defined('HOST') ? rtrim((string) HOST, '/') : '';
		}

		protected function listItems()
		{
			$decoded = FieldValueCodec::decodeStructured($this->value, null);
			if (!is_array($decoded)) {
				$decoded = preg_split('/\r\n|\r|\n/', trim((string) $this->value), -1, PREG_SPLIT_NO_EMPTY);
			}

			$items = array();
			foreach ($decoded as $item) {
				if (is_array($item)) {
					$param = isset($item['param']) ? $item['param'] : (isset($item[0]) ? $item[0] : (isset($item['value']) ? $item['value'] : ''));
					$value = isset($item['value']) && isset($item['param']) ? $item['value'] : (isset($item[1]) ? $item[1] : '');
					$value2 = isset($item['value2']) ? $item['value2'] : (isset($item[2]) ? $item[2] : '');
				} else {
					$parts = explode('|', (string) $item);
					$param = isset($parts[0]) ? $parts[0] : '';
					$value = isset($parts[1]) ? $parts[1] : '';
					$value2 = isset($parts[2]) ? $parts[2] : '';
				}

				if ((string) $param !== '') {
					$items[] = array('param' => (string) $param, 'value' => (string) $value, 'value2' => (string) $value2);
				}
			}

			return $items;
		}

		protected function relationItems()
		{
			$structured = FieldValueCodec::decodeStructured($this->value, null);
			$values = is_array($structured) ? $structured : preg_split('/[^0-9]+/', (string) $this->value, -1, PREG_SPLIT_NO_EMPTY);
			$ids = array();
			array_walk_recursive($values, function ($value, $key) use (&$ids) {
				$value = (string) $value;
				$candidates = array();
				if (in_array((string) $key, array('id', 'Id', 'document_id'), true) || ctype_digit($value)) {
					$candidates[] = (int) $value;
				} elseif (preg_match('/(?:^|\|)(\d+)(?:\|)?$/', $value, $match)) {
					$candidates[] = (int) $match[1];
				}

				foreach ($candidates as $id) {
					if ($id > 0) {
						$ids[$id] = $id;
					}
				}
			});
			if (empty($ids)) {
				return array();
			}

			$rows = DB::query(
				'SELECT Id, document_title, document_alias, document_breadcrumb_title FROM %b'
				. ' WHERE Id IN (' . implode(',', $ids) . ") AND document_deleted = '0'",
				ContentTables::table('documents')
			)->getAll();
			$byId = array();
			foreach ($rows ?: array() as $row) {
				$byId[(int) $row['Id']] = $row;
			}

			$items = array();
			foreach ($ids as $id) {
				if (isset($byId[$id])) {
					$items[] = $byId[$id];
				}
			}

			return $items;
		}
	}
