<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Контекст рендера/сохранения поля — замена глобальных $AVE_DB/$AVE_Template.
	 * Передаётся в методы FieldType.
	 *
	 * $definition — строка legacy `{prefix}_rubric_fields`: ключи Id,
	 * rubric_field_alias, rubric_field_type, rubric_field_settings (JSON),
	 * rubric_field_default (legacy-настройка), rubric_id и т.д.
	 */
	class FieldContext
	{
		/** @var mixed текущее значение поля */
		public $value;

		/** @var array определение поля (строка rubric_fields) */
		public $definition;

		/** @var object|array|null документ */
		public $document;

		/** @var object|array|null рубрика */
		public $rubric;

		/** @var string режим: edit|view|filter|save */
		public $mode;

		/** @var array доп. данные (все поля документа и т.п.) */
		public $extra;

		public function __construct($value = null, array $definition = array(), $mode = 'view')
		{
			$this->value = $value;
			$this->definition = $definition;
			$this->mode = (string) $mode;
			$this->extra = array();
			$this->document = null;
			$this->rubric = null;
		}

		/** HTML-имя инпута формы редактора документа: fields[<field_id>]. */
		public function inputName($suffix = '')
		{
			return 'fields[' . $this->fieldId() . ']' . $suffix;
		}

		public function fieldId()
		{
			if (isset($this->definition['rubric_field_id'])) {
				return (int) $this->definition['rubric_field_id'];
			}

			return isset($this->definition['Id']) ? (int) $this->definition['Id'] : 0;
		}

		public function alias()
		{
			return isset($this->definition['rubric_field_alias']) ? (string) $this->definition['rubric_field_alias'] : '';
		}

		public function type()
		{
			return isset($this->definition['rubric_field_type']) ? (string) $this->definition['rubric_field_type'] : '';
		}

		public function rubricId()
		{
			return isset($this->definition['rubric_id']) ? (int) $this->definition['rubric_id'] : 0;
		}

		/**
		 * Настройки поля в едином native-формате. Старые строки нормализуются в
		 * одном месте и после web-миграции уже читаются из JSON.
		 */
		public function settings()
		{
			return FieldSettings::effective($this->definition);
		}

		public function setting($key, $default = null)
		{
			$settings = $this->settings();
			return array_key_exists($key, $settings) ? $settings[$key] : $default;
		}

		/** Legacy-«умолчание/настройка» поля (rubric_field_default). */
		public function legacyDefault()
		{
			return isset($this->definition['rubric_field_default']) ? (string) $this->definition['rubric_field_default'] : '';
		}
	}
