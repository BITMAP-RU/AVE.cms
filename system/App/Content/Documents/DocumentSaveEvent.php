<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentSaveEvent.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Mutable module event shared by the panel, API and background document writers. */
	class DocumentSaveEvent
	{
		protected $phase;
		protected $operation;
		protected $source;
		protected $documentId;
		protected $rubricId;
		protected $actorId;
		protected $data;
		protected $fields;
		protected $fieldAliases;
		protected $previous;
		protected $snapshot;
		protected $failed = false;
		protected $message = '';
		protected $errors = array();

		public function __construct($phase, $operation, $source, $documentId, $rubricId, $actorId, array &$data, array &$fields, array $fieldAliases = array(), array $previous = array(), array $snapshot = array())
		{
			$this->phase = (string) $phase;
			$this->operation = (string) $operation;
			$this->source = (string) $source;
			$this->documentId = (int) $documentId;
			$this->rubricId = (int) $rubricId;
			$this->actorId = (int) $actorId;
			$this->data =& $data;
			$this->fields =& $fields;
			$this->fieldAliases = $fieldAliases;
			$this->previous = $previous;
			$this->snapshot = $snapshot;
		}

		public function phase() { return $this->phase; }
		public function operation() { return $this->operation; }
		public function source() { return $this->source; }
		public function documentId() { return $this->documentId; }
		public function rubricId() { return $this->rubricId; }
		public function actorId() { return $this->actorId; }
		public function isNew() { return $this->operation === 'create'; }
		public function previous() { return $this->previous; }
		public function snapshot() { return $this->snapshot; }

		public function data() { return $this->data; }

		public function value($key, $default = null)
		{
			$key = $this->dataKey($key);
			return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
		}

		public function setValue($key, $value)
		{
			$this->data[$this->dataKey($key)] = $value;
			return $this;
		}

		public function fields() { return $this->fields; }

		public function field($idOrAlias, $default = null)
		{
			$id = $this->fieldId($idOrAlias);
			return $id > 0 && array_key_exists($id, $this->fields) ? $this->fields[$id] : $default;
		}

		public function setField($idOrAlias, $value)
		{
			$id = $this->fieldId($idOrAlias);
			if ($id <= 0) {
				throw new \InvalidArgumentException('Поле документа не найдено: ' . (string) $idOrAlias);
			}

			$this->fields[$id] = $value;
			return $this;
		}

		public function fail($message, array $errors = array())
		{
			$this->failed = true;
			$this->message = trim((string) $message);
			$this->errors = $errors;
			return $this;
		}

		public function failed() { return $this->failed; }
		public function message() { return $this->message; }
		public function errors() { return $this->errors; }

		protected function fieldId($idOrAlias)
		{
			if (is_int($idOrAlias) || ctype_digit((string) $idOrAlias)) {
				return (int) $idOrAlias;
			}

			$alias = trim((string) $idOrAlias);
			return isset($this->fieldAliases[$alias]) ? (int) $this->fieldAliases[$alias] : 0;
		}

		protected function dataKey($key)
		{
			$map = array(
				'title' => 'document_title', 'alias' => 'document_alias', 'status' => 'document_status',
				'published_at' => 'document_published', 'expire_at' => 'document_expire',
				'parent_id' => 'document_parent', 'template_id' => 'rubric_tmpl_id',
				'breadcrumb_title' => 'document_breadcrumb_title',
				'excerpt' => 'document_excerpt', 'teaser' => 'document_excerpt',
				'tags' => 'document_tags', 'property' => 'document_property', 'in_search' => 'document_in_search',
			);
			$key = (string) $key;
			return isset($map[$key]) ? $map[$key] : $key;
		}
	}
