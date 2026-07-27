<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldType.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Контракт типа поля (плагин). Соответствует режимам AVE edit/doc/req/save/name.
	 * Порт из sample/other, адаптированный под legacy-схему AVE.cms.
	 *
	 * Новый тип поля = класс в App\Content\Fields\Types\<Name>, реализующий этот
	 * интерфейс (обычно через AbstractFieldType).
	 *
	 * План переезда: docs/development/fields-migration.md.
	 */
	interface FieldType
	{
		/** Машинный код типа, напр. 'single_line'. Совпадает с rubric_field_type. */
		public function code();

		/** Человекочитаемое имя. */
		public function name();

		/** Куда пишется значение: 'value' (document_fields) или 'text' (document_fields_text). */
		public function storage();

		/** Дублировать ли значение в числовую колонку field_number_value (фильтры/сортировка). */
		public function isNumeric();

		/** Несколько значений на поле. */
		public function isMultiple();

		/** Схема настроек поля (для конструктора рубрики). Массив дескрипторов. */
		public function settingsSchema();

		/** Схема правил валидации (required/min/max/…), зависит от природы типа. */
		public function validationSchema();

		/** Форма поля в редакторе документа (режим AVE 'edit'). */
		public function renderEdit(FieldContext $ctx);

		/** Вывод значения на странице (режим 'doc'). */
		public function renderView(FieldContext $ctx);

		/** Вывод в фильтре/поиске (режим 'req'). */
		public function renderFilter(FieldContext $ctx);

		/** Нормализация значения перед сохранением (режим 'save'). Возвращает строку. */
		public function save(FieldContext $ctx);

		//-- Фильтр: сам тип поля описывает своё участие в листингах. --//

		/** Разрешённые widget-типы фильтра, напр. ['range'] или ['select','checkboxes']. Пусто = не фильтруется. */
		public function getFilterTypes();

		/** Опции фильтра: для select — [{value,label,count}], для range — {min,max}. */
		public function buildFilterOptions($fieldId, $rubricId);

		/** SQL-условие для листинга по выбранному значению: ['sql'=>' AND EXISTS(…)', 'args'=>[]]. */
		public function applyFilter($fieldId, $selected);

		/** Нормализовать входящее Ajax/public-значение фильтра. */
		public function normalizeFilterValue($value);
	}
