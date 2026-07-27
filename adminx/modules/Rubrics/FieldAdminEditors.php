<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldAdminEditors.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Rubrics\FieldEditors\ChoiceFieldEditor;
	use App\Adminx\Rubrics\FieldEditors\FieldEditorInterface;
	use App\Adminx\Rubrics\FieldEditors\ListFieldEditor;
	use App\Adminx\Rubrics\FieldEditors\MediaFieldEditor;
	use App\Adminx\Rubrics\FieldEditors\RelationFieldEditor;
	use App\Adminx\Rubrics\FieldEditors\SimpleFieldEditor;
	use App\Content\Fields\FieldSettings;

	class FieldAdminEditors
	{
		protected static $editors = null;

		public static function describe($type)
		{
			$editor = self::editorFor($type);
			if (!$editor) {
				return self::unavailable($type);
			}

			$description = $editor->describe($type);
			if (FieldSettings::legacyDefaultIsConfiguration($type)) {
				$description['controls'] = array_values(array_filter(
					isset($description['controls']) ? $description['controls'] : array(),
					function ($control) {
						return !isset($control['name']) || $control['name'] !== 'rubric_field_default';
					}
				));
				$description['default_is_configuration'] = true;
			}

			return $description;
		}

		public static function normalizeDefault($type, $value)
		{
			$editor = self::editorFor($type);
			if (!$editor) {
				throw new \RuntimeException('Для типа поля не зарегистрирован admin-редактор: ' . (string) $type);
			}

			return $editor->normalizeDefault($type, $value);
		}

		public static function parseValue($type, $value)
		{
			$editor = self::editorFor($type);
			if (!$editor) {
				throw new \RuntimeException('Для типа поля не зарегистрирован admin-редактор: ' . (string) $type);
			}

			return $editor->parseValue($type, $value);
		}

		public static function serializeValue($type, $value)
		{
			$editor = self::editorFor($type);
			if (!$editor) {
				throw new \RuntimeException('Для типа поля не зарегистрирован admin-редактор: ' . (string) $type);
			}

			return $editor->serializeValue($type, $value);
		}

		public static function renderEdit($type, array $field)
		{
			$editor = self::editorFor($type);
			if (!$editor) {
				throw new \RuntimeException('Для типа поля не зарегистрирован admin-редактор: ' . (string) $type);
			}

			return $editor->renderEdit($field);
		}

		public static function knownTypes()
		{
			$types = array();
			foreach (self::editors() as $editor) {
				foreach (self::editorTypes($editor) as $type) {
					$types[] = $type;
				}
			}

			sort($types, SORT_NATURAL | SORT_FLAG_CASE);
			return array_values(array_unique($types));
		}

		protected static function editorFor($type)
		{
			foreach (self::editors() as $editor) {
				if ($editor->supports($type)) {
					return $editor;
				}
			}

			return null;
		}

		protected static function editors()
		{
			if (self::$editors !== null) {
				return self::$editors;
			}

			self::loadEditorClasses();
			self::$editors = array(
				new SimpleFieldEditor(),
				new ChoiceFieldEditor(),
				new ListFieldEditor(),
				new MediaFieldEditor(),
				new RelationFieldEditor(),
			);
			return self::$editors;
		}

		protected static function loadEditorClasses()
		{
			$files = array(
				'FieldEditorInterface.php',
				'AbstractFieldEditor.php',
				'SimpleFieldEditor.php',
				'ChoiceFieldEditor.php',
				'ListFieldEditor.php',
				'MediaFieldEditor.php',
				'RelationFieldEditor.php',
			);
			foreach ($files as $file) {
				require_once __DIR__ . '/FieldEditors/' . $file;
			}
		}

		protected static function editorTypes(FieldEditorInterface $editor)
		{
			$ref = new \ReflectionClass($editor);
			$method = $ref->getMethod('definitions');
			$method->setAccessible(true);
			$map = $method->invoke($editor);
			return is_array($map) ? array_keys($map) : array();
		}

		protected static function unavailable($type)
		{
			return array(
				'title' => (string) $type,
				'icon' => 'plug',
				'status' => 'error',
				'summary' => 'Для типа не зарегистрирован native admin-редактор.',
				'type' => (string) $type,
				'controls' => array(),
			);
		}
	}
