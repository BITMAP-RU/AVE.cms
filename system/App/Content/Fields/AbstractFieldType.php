<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/AbstractFieldType.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Str;
	use App\Content\ContentTables;

	/**
	 * База для типов полей: разумные дефолты + хелперы вывода.
	 * Плагин переопределяет только нужное (обычно code/name/renderEdit/renderView).
	 *
	 * Порт из sample/other, адаптирован под legacy-хранилище значений
	 * ({prefix}_document_fields / _document_fields_text).
	 */
	abstract class AbstractFieldType implements FieldType
	{
		public function storage()
		{
			return 'value';
		}

		public function isNumeric()
		{
			return false;
		}

		public function isMultiple()
		{
			return false;
		}

		/** Файловый тип (медиа/загрузка) — для правила «Разрешённые расширения». */
		public function isFile()
		{
			return false;
		}

		/** Тип с фиксированным списком опций — для правила «Только из списка». */
		public function isChoice()
		{
			return false;
		}

		public function settingsSchema()
		{
			return array();
		}

		/**
		 * Общие правила валидации по природе типа. Ключи совпадают с тем, что
		 * читает FieldValidator (required/min/max/minLength/maxLength/minCount/maxCount).
		 */
		public function validationSchema()
		{
			$rules = array(
				array('key' => 'required', 'type' => 'bool', 'label' => 'Обязательное'),
			);
			if ($this->isNumeric()) {
				$rules[] = array('key' => 'min', 'type' => 'number', 'label' => 'Мин. значение');
				$rules[] = array('key' => 'max', 'type' => 'number', 'label' => 'Макс. значение');
			} elseif ($this->isFile()) {
				$rules[] = array('key' => 'allowedExtensions', 'type' => 'text', 'label' => 'Разрешённые расширения', 'hint' => 'Через запятую, напр. jpg, png, webp');
			} elseif ($this->isChoice()) {
				$rules[] = array('key' => 'allowedValues', 'type' => 'bool', 'label' => 'Только из списка', 'hint' => 'Отклонять значения не из заданных вариантов');
			} elseif (!$this->isMultiple()) {
				$rules[] = array('key' => 'minLength', 'type' => 'int', 'label' => 'Мин. длина');
				$rules[] = array('key' => 'maxLength', 'type' => 'int', 'label' => 'Макс. длина');
				$rules[] = array('key' => 'regex', 'type' => 'text', 'label' => 'Шаблон (regex)', 'hint' => 'Регулярное выражение, напр. ^[0-9]{6}$');
			}

			if ($this->isMultiple()) {
				$rules[] = array('key' => 'minCount', 'type' => 'int', 'label' => 'Мин. количество');
				$rules[] = array('key' => 'maxCount', 'type' => 'int', 'label' => 'Макс. количество');
			}

			return $rules;
		}

		public function renderFilter(FieldContext $ctx)
		{
			return $this->renderView($ctx);
		}

		public function save(FieldContext $ctx)
		{
			return (string) $ctx->value;
		}

		//-- Фильтр: по умолчанию поле не участвует в фильтрах. --//

		public function getFilterTypes()
		{
			return array();
		}

		public function buildFilterOptions($fieldId, $rubricId)
		{
			return array();
		}

		public function applyFilter($fieldId, $selected)
		{
			return array('sql' => '', 'args' => array());
		}

		public function normalizeFilterValue($value)
		{
			return is_array($value) ? $value : (string) $value;
		}

		/**
		 * Хелпер: EXISTS-условие по legacy document_fields для листинга (алиас d).
		 * $inner — условие по строке значения (v.rubric_field_id / v.field_value / …).
		 */
		protected function existsValue($inner)
		{
			return ' AND EXISTS (SELECT 1 FROM ' . ContentTables::table('document_fields') . ' v'
				. ' WHERE v.document_id = d.Id AND ' . $inner . ')';
		}

		/** Экранирование HTML. */
		protected function e($value)
		{
			return Str::escape((string) $value);
		}

		/** Экранирование значения атрибута. */
		protected function attr($value)
		{
			return Str::escape((string) $value);
		}
	}
