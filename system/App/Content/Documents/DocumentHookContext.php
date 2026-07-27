<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentHookContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Common\SettingsRegistry;
	use App\Helpers\Json;

	/** Stable API exposed to administrator-managed document lifecycle code. */
	class DocumentHookContext
	{
		const BEFORE_SAVE = 'before_save';
		const AFTER_SAVE = 'after_save';

		protected $phase;
		protected $rubricId;
		protected $documentId;
		protected $actorId;
		protected $isNew;
		protected $source;
		protected $data;
		protected $fields;
		protected $logs;
		protected $fieldAliases = array();
		protected $knownFieldIds = array();
		protected $skippedFieldIds = array();

		public function __construct($phase, $rubricId, $documentId, array &$data, array &$fields, array &$logs, array $options = array())
		{
			$phase = (string) $phase;
			if (!in_array($phase, array(self::BEFORE_SAVE, self::AFTER_SAVE), true)) {
				throw new \InvalidArgumentException('Unknown document hook phase: ' . $phase);
			}

			$this->phase = $phase;
			$this->rubricId = (int) $rubricId;
			$this->documentId = (int) $documentId;
			$this->actorId = isset($options['actor_id']) ? (int) $options['actor_id'] : 0;
			$this->isNew = !empty($options['is_new']);
			$this->source = isset($options['source']) ? (string) $options['source'] : 'document';
			$this->data =& $data;
			$this->fields =& $fields;
			$this->logs =& $logs;
			foreach (array_keys($fields) as $fieldId) {
				if ((int) $fieldId > 0) {
					$this->knownFieldIds[(int) $fieldId] = true;
				}
			}

			$aliases = isset($options['field_aliases']) && is_array($options['field_aliases'])
				? $options['field_aliases']
				: array();
			foreach ($aliases as $alias => $id) {
				$alias = trim((string) $alias);
				if ($alias !== '' && (int) $id > 0) {
					$this->fieldAliases[$alias] = (int) $id;
					$this->knownFieldIds[(int) $id] = true;
				}
			}
		}

		public function phase() { return $this->phase; }
		public function rubricId() { return $this->rubricId; }
		public function documentId() { return $this->documentId; }
		public function actorId() { return $this->actorId; }
		public function isNew() { return $this->isNew; }
		public function source() { return $this->source; }
		public function isBeforeSave() { return $this->phase === self::BEFORE_SAVE; }
		public function isAfterSave() { return $this->phase === self::AFTER_SAVE; }

		public function phaseLabel()
		{
			return $this->isBeforeSave() ? 'до сохранения' : 'после сохранения';
		}

		public function document()
		{
			return $this->data;
		}

		public function value($key, $default = null)
		{
			$key = (string) $key;
			return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
		}

		public function setValue($key, $value)
		{
			$key = trim((string) $key);
			if ($key === '' || $key === 'field' || $key === 'feld' || $key === 'fields') {
				throw new \InvalidArgumentException('Укажите допустимое свойство документа');
			}

			$this->data[$key] = $value;
			return $this;
		}

		public function fields()
		{
			return $this->fields;
		}

		public function field($field, $default = null)
		{
			$id = $this->fieldId($field);
			return $id > 0 && array_key_exists($id, $this->fields) ? $this->fields[$id] : $default;
		}

		public function hasField($field)
		{
			$id = $this->fieldId($field);
			return $id > 0 && array_key_exists($id, $this->fields);
		}

		public function setField($field, $value)
		{
			$id = $this->requireFieldId($field);
			$this->fields[$id] = $value;
			return $this;
		}

		/** Remove a field from the current save payload without clearing its stored value. */
		public function skipField($field)
		{
			$id = $this->requireFieldId($field);
			unset($this->fields[$id]);
			$this->skippedFieldIds[$id] = $id;
			return $this;
		}

		public function skippedFieldIds()
		{
			return array_values($this->skippedFieldIds);
		}

		public function fieldId($field)
		{
			if (is_int($field) || (is_string($field) && ctype_digit($field))) {
				return max(0, (int) $field);
			}

			$alias = trim((string) $field);
			return isset($this->fieldAliases[$alias]) ? (int) $this->fieldAliases[$alias] : 0;
		}

		public function setting($key, $default = null)
		{
			return SettingsRegistry::value((string) $key, $default);
		}

		public function log($message, array $context = array())
		{
			$message = trim((string) $message);
			if (!empty($context)) {
				$message .= ' ' . Json::encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			if ($message !== '') {
				$this->logs[] = $message;
			}

			return $this;
		}

		public function logs()
		{
			return $this->logs;
		}

		public function audit($action, array $meta = array())
		{
			AuditLog::record((string) $action, array(
				'actor_id' => $this->actorId > 0 ? $this->actorId : null,
				'target_type' => 'document',
				'target_id' => $this->documentId > 0 ? $this->documentId : null,
				'meta' => array_merge(array(
					'rubric_id' => $this->rubricId,
					'phase' => $this->phase,
					'source' => $this->source,
				), $meta),
			));
			return $this;
		}

		public function fail($message)
		{
			throw new \RuntimeException((string) $message);
		}

		public function metadata()
		{
			return array(
				'phase' => $this->phase,
				'rubric_id' => $this->rubricId,
				'document_id' => $this->documentId,
				'actor_id' => $this->actorId,
				'is_new' => $this->isNew,
				'source' => $this->source,
			);
		}

		protected function requireFieldId($field)
		{
			$id = $this->fieldId($field);
			if ($id <= 0 || !isset($this->knownFieldIds[$id])) {
				throw new \InvalidArgumentException('Поле рубрики не найдено: ' . (string) $field);
			}

			return $id;
		}
	}
