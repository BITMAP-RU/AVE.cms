<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/AbstractFieldEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	use App\Content\Fields\FieldValueCodec;
	use App\Content\Fields\FieldSettings;
	use App\Helpers\Str;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	abstract class AbstractFieldEditor implements FieldEditorInterface
	{
		public function supports($type)
		{
			$map = $this->definitions();
			return isset($map[(string) $type]);
		}

		public function describe($type)
		{
			$type = (string) $type;
			$map = $this->definitions();
			if (!isset($map[$type])) {
				throw new \RuntimeException('Для типа поля не зарегистрирован native admin-редактор: ' . $type);
			}

			$editor = $map[$type];
			$editor['type'] = $type;
			$editor['status'] = isset($editor['status']) ? $editor['status'] : 'native';
			$editor['controls'] = $this->controls($editor);
			$editor['entity_fields'] = isset($editor['entity_fields']) && is_array($editor['entity_fields']) ? array_values($editor['entity_fields']) : array();
			$editor['default_is_configuration'] = isset($editor['default_is_configuration'])
				? (bool) $editor['default_is_configuration']
				: FieldSettings::legacyDefaultIsConfiguration($type);
			$editor['capabilities'] = array(
				'parse_value' => true,
				'serialize_value' => true,
				'normalize_default' => true,
			);
			return $editor;
		}

		public function normalizeDefault($type, $value)
		{
			return (string) $value;
		}

		public function parseValue($type, $value)
		{
			return array(
				'raw' => (string) $value,
				'value' => (string) $value,
			);
		}

		public function serializeValue($type, $value)
		{
			if (is_array($value)) {
				return isset($value['raw']) ? (string) $value['raw'] : '';
			}

			return (string) $value;
		}

		public function renderEdit(array $field)
		{
			return $this->renderGeneric($field);
		}

		// -------- Общие помощники отрисовки контролов документа --------

		/** Экранирование значения атрибута/текста. */
		protected function e($value)
		{
			return Str::escape((string) $value);
		}

		/** Имя поля формы: fields[ID]<suffix>, напр. fname($f,'[url]'). */
		protected function fname(array $field, $suffix = '[raw]')
		{
			return 'fields[' . (int) $field['Id'] . ']' . $suffix;
		}

		/** Значение parsed поля (нормализованное во viewValue). */
		protected function value(array $field)
		{
			return isset($field['parsed']) ? $field['parsed'] : '';
		}

		protected function renderGeneric(array $field)
		{
			$parsed = $this->value($field);
			$parsed = is_array($parsed) ? '' : (string) $parsed;
			return '<textarea class="textarea" name="' . $this->e($this->fname($field, '[raw]'))
				. '" rows="5">' . $this->e($parsed) . '</textarea>';
		}

		abstract protected function definitions();

		protected function structuredValue($value)
		{
			return FieldValueCodec::decodeStructured($value, null);
		}

		protected function structuredScalars($value)
		{
			$data = $this->structuredValue($value);
			if (!is_array($data)) {
				return null;
			}

			$items = array();
			array_walk_recursive($data, function ($item) use (&$items) {
				if (is_scalar($item) && trim((string) $item) !== '') {
					$items[] = trim((string) $item);
				}
			});
			return array_values($items);
		}

		protected function controls(array $editor)
		{
			$kind = isset($editor['default_kind']) ? (string) $editor['default_kind'] : 'textarea';
			return array(
				array(
					'name' => 'rubric_field_default',
					'label' => isset($editor['default_label']) ? (string) $editor['default_label'] : 'Значение по умолчанию',
					'kind' => $kind,
					'hint' => isset($editor['default_hint']) ? (string) $editor['default_hint'] : '',
				),
				array(
					'name' => 'rubric_field_template',
					'label' => 'Шаблон вывода',
					'kind' => 'code',
					'hint' => isset($editor['template_hint']) ? (string) $editor['template_hint'] : 'Публичный шаблон вывода поля.',
				),
				array(
					'name' => 'rubric_field_template_request',
					'label' => 'Шаблон request',
					'kind' => 'code',
					'hint' => 'Шаблон обработки request-значения, если тип поля его использует.',
				),
			);
		}

		protected function commaList($value)
		{
			$parts = preg_split('/[,\r\n]+/', (string) $value);
			$out = array();
			foreach ($parts ?: array() as $part) {
				$part = trim((string) $part);
				if ($part !== '') {
					$out[] = $part;
				}
			}

			return implode(',', $out);
		}

		protected function pipeWrappedList($value)
		{
			$parts = preg_split('/[|,\r\n]+/', (string) $value);
			$out = array();
			foreach ($parts ?: array() as $part) {
				$part = trim((string) $part);
				if ($part !== '') {
					$out[] = $part;
				}
			}

			return !empty($out) ? '|' . implode('|', $out) . '|' : '';
		}
	}
