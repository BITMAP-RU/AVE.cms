<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldValidator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Valid;
	use App\Helpers\Json;
	use App\Common\Lifecycle;

	/**
	 * Серверная валидация значений полей документа.
	 *
	 * Правила берутся из JSON-настроек поля (`rubric_field_settings`): либо
	 * `settings.rules` (список строк типа `required`, `minLength:3`, `max:100`),
	 * либо флаги-ключи (`required:true`, `min:0`). Ядро правил делегирует
	 * App\Helpers\Valid; несколько правил (count/media/allowed) — доменные.
	 *
	 * $field — строка legacy `{prefix}_rubric_fields` (rubric_field_type/title/
	 * alias/Id + rubric_field_settings). Значение — то, что пришло из формы.
	 */
	class FieldValidator
	{
		/** Проверить набор значений: $fields — строки rubric_fields, $values — по Id поля. */
		public static function validateValues(array $fields, array $values, $conditionsEnabled = true)
		{
			$errors = array();
			$states = $conditionsEnabled ? FieldConditionEvaluator::states($fields, $values) : array();
			foreach ($fields as $field) {
				$field = (array) $field;
				if (!empty($field['_condition_source'])) { continue; }
				$id = isset($field['Id']) ? (int) $field['Id'] : 0;
				if (isset($states[$id]) && empty($states[$id]['visible'])) { continue; }
				if (isset($states[$id])) {
					$field['_condition_required'] = !empty($states[$id]['required']);
					if (is_array($states[$id]['allowed_values'])) {
						$field['_condition_allowed_values'] = $states[$id]['allowed_values'];
					}
				}

				$value = array_key_exists($id, $values) ? $values[$id] : '';
				$error = self::validateField($field, $value);
				if ($error !== null) {
					$errors['fields[' . $id . ']'] = $error;
				}
			}

			return $errors;
		}

		public static function assertValues(array $fields, array $values)
		{
			$errors = self::validateValues($fields, $values);
			if (!empty($errors)) {
				throw new FieldValidationException($errors);
			}
		}

		/** Проверить одно поле. Возвращает текст ошибки или null. */
		public static function validateField(array $field, $value)
		{
			$id = isset($field['Id']) ? (int) $field['Id'] : 0;
			$validating = Lifecycle::event('content.field.validating', 'field', 'validating', $id, array(
				'type' => isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '',
				'field' => $field,
				'value' => $value,
			), null, array(), 'field_validator');
			if ($validating->cancelled()) {
				return $validating->result() === null ? null : (string) $validating->result();
			}

			$field = $validating->value('field', $field);
			$value = $validating->value('value', $value);
			$error = self::validateNative($field, $value);
			$validated = Lifecycle::event('content.field.validated', 'field', 'validated', $id, array(
				'type' => isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '',
				'field' => $field,
				'value' => $value,
			), $error, array('valid' => $error === null), 'field_validator');
			return $validated->result() === null ? null : (string) $validated->result();
		}

		protected static function validateNative(array $field, $value)
		{
			if (isset($field['_condition_allowed_values'])
				&& !self::allowedValueArray($value, (array) $field['_condition_allowed_values'])) {
				return 'Поле «' . self::label($field) . '» содержит вариант, недоступный при текущих условиях';
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'number'
				&& !self::isEmpty($field, $value) && Types\NumberValue::normalize(self::scalarValue($value)) === '') {
				return 'Поле «' . self::label($field) . '» должно быть числом';
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'date_time'
				&& !self::isEmpty($field, $value) && Types\DateTimeValue::normalize(self::scalarValue($value)) === '') {
				return 'Поле «' . self::label($field) . '» должно содержать корректную дату и время';
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'period') {
				$period = FieldRegistry::get('period');
				if ($period instanceof Types\PeriodValue && !$period->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректный период';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'choice') {
				$choice = FieldRegistry::get('choice');
				if ($choice instanceof Types\ChoiceValue && !$choice->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит вариант, которого нет в настройках';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'contact') {
				$contact = FieldRegistry::get('contact');
				if ($contact instanceof Types\ContactValue && !$contact->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректный контакт';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'color') {
				$color = FieldRegistry::get('color');
				if ($color instanceof Types\ColorValue && !$color->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» должно содержать HEX-цвет';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'range') {
				$range = FieldRegistry::get('range');
				if ($range instanceof Types\RangeValue && !$range->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректный диапазон';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'dimensions') {
				$dimensions = FieldRegistry::get('dimensions');
				if ($dimensions instanceof Types\DimensionsValue && !$dimensions->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректные габариты';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'packages') {
				$packages = FieldRegistry::get('packages');
				if ($packages instanceof Types\PackagesValue && !$packages->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректные упаковки';
				}
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'address') {
				$address = FieldRegistry::get('address');
				if ($address instanceof Types\AddressValue && !$address->valid(new FieldContext($value, $field, 'save'))) {
					return 'Поле «' . self::label($field) . '» содержит некорректный адрес';
				}
			}

			$rules = self::rules($field);
			if (empty($rules)) {
				return null;
			}

			$label = self::label($field);
			foreach ($rules as $rule) {
				$error = self::apply($field, $value, $rule, $label);
				if ($error !== null) {
					return $error;
				}
			}

			return null;
		}

		protected static function label(array $field)
		{
			if (isset($field['rubric_field_title']) && trim((string) $field['rubric_field_title']) !== '') {
				return (string) $field['rubric_field_title'];
			}

			if (isset($field['rubric_field_alias']) && (string) $field['rubric_field_alias'] !== '') {
				return (string) $field['rubric_field_alias'];
			}

			return 'поле #' . (isset($field['Id']) ? (int) $field['Id'] : 0);
		}

		protected static function settings(array $field)
		{
			$settings = FieldSettings::effective($field);
			if (array_key_exists('_condition_required', $field)) {
				$settings['required'] = !empty($field['_condition_required']);
			}

			return $settings;
		}

		protected static function rules(array $field)
		{
			$settings = self::settings($field);
			$out = array();
			if (isset($settings['rules'])) {
				$out = array_merge($out, self::normalizeRuleList($settings['rules']));
			}

			foreach ($settings as $key => $value) {
				if ($key === 'rules' || $key === 'options' || $value === false || $value === null || $value === '') {
					continue;
				}

				if ($key === 'required') {
					if ($value) {
						$out[] = 'required';
					}

					continue;
				}

				if (in_array($key, array('min', 'max', 'minLength', 'maxLength', 'length', 'minCount', 'maxCount',
					'date', 'regex', 'allowedValues', 'inArray', 'allowedExtensions', 'email', 'url'), true)) {
					if (is_array($value)) {
						$value = implode(',', array_filter(array_map('trim', array_map('strval', $value)), 'strlen'));
					}

					$out[] = $key . ($value === true ? '' : ':' . (string) $value);
				}
			}

			return array_values(array_unique(array_filter(array_map('trim', $out), 'strlen')));
		}

		protected static function normalizeRuleList($rules)
		{
			if (is_array($rules)) {
				$out = array();
				foreach ($rules as $rule) {
					$rule = trim((string) $rule);
					if ($rule !== '') {
						$out[] = $rule;
					}
				}

				return $out;
			}

			return array_filter(array_map('trim', explode('|', (string) $rules)), 'strlen');
		}

		protected static function apply(array $field, $value, $ruleItem, $label)
		{
			$rule = $ruleItem;
			$param = '';
			if (strpos($ruleItem, ':') !== false) {
				list($rule, $param) = explode(':', $ruleItem, 2);
			}

			$rule = self::normalizeRuleName(trim($rule));
			$param = trim((string) $param);

			switch ($rule) {
				case 'required':
					return self::isEmpty($field, $value) ? 'Поле «' . $label . '» обязательно для заполнения' : null;
				case 'numeric':
					return self::isEmpty($field, $value) || is_numeric(self::numericScalarValue($field, $value))
						? null : 'Поле «' . $label . '» должно быть числом';
				case 'min':
					$numericValue = self::numericScalarValue($field, $value);
					return self::isEmpty($field, $value) || (is_numeric($numericValue) && (float) $numericValue >= (float) $param)
						? null : 'Значение «' . $label . '» должно быть не меньше ' . $param;
				case 'max':
					$numericValue = self::numericScalarValue($field, $value);
					return self::isEmpty($field, $value) || (is_numeric($numericValue) && (float) $numericValue <= (float) $param)
						? null : 'Значение «' . $label . '» должно быть не больше ' . $param;
				case 'minLength':
					return self::isEmpty($field, $value) || self::length(self::scalarValue($value)) >= (int) $param
						? null : 'Поле «' . $label . '» — не менее ' . (int) $param . ' символов';
				case 'maxLength':
					return self::isEmpty($field, $value) || self::length(self::scalarValue($value)) <= (int) $param
						? null : 'Поле «' . $label . '» — не более ' . (int) $param . ' символов';
				case 'minCount':
					return self::countValue($field, $value) >= (int) $param
						? null : 'Поле «' . $label . '» — не менее ' . (int) $param . ' знач.';
				case 'maxCount':
					return self::countValue($field, $value) <= (int) $param
						? null : 'Поле «' . $label . '» — не более ' . (int) $param . ' знач.';
				case 'allowedValues':
					return self::allowedValues($field, $value, $param)
						? null : 'Поле «' . $label . '» содержит недопустимое значение';
				case 'allowedExtensions':
					return self::allowedExtensions($value, $param)
						? null : 'Поле «' . $label . '» содержит файл недопустимого формата';
				case 'date':
					return self::isEmpty($field, $value) || self::validDate(self::scalarValue($value), $param !== '' ? $param : 'd.m.Y')
						? null : 'Поле «' . $label . '» должно быть корректной датой';
				case 'regex':
					return self::isEmpty($field, $value) || @preg_match($param, self::scalarValue($value)) === 1
						? null : 'Поле «' . $label . '» имеет неверный формат';
				default:
					$data = array('value' => self::scalarValue($value));
					$errors = Valid::check($data, array('value' => array($ruleItem)), array('value' => $label));
					return isset($errors['value']) ? $errors['value'] : null;
			}
		}

		protected static function normalizeRuleName($rule)
		{
			$map = array(
				'min_count' => 'minCount', 'max_count' => 'maxCount',
				'min_length' => 'minLength', 'max_length' => 'maxLength',
				'allowed_values' => 'allowedValues', 'allowed_extensions' => 'allowedExtensions',
				'in' => 'allowedValues', 'inArray' => 'allowedValues',
				'number' => 'numeric', 'integer' => 'int',
			);
			return isset($map[$rule]) ? $map[$rule] : $rule;
		}

		protected static function isEmpty(array $field, $value)
		{
			return self::countValue($field, $value) === 0;
		}

		protected static function countValue(array $field, $value)
		{
			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'choice'
				&& is_array($value) && array_key_exists('choice_present', $value)) {
				$value = isset($value['selected']) ? $value['selected'] : array();
			}

			if (isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'packages'
				&& is_array($value) && array_key_exists('list_present', $value)) {
				$value = isset($value['items']) ? $value['items'] : array();
			}

			if (is_array($value)) {
				return count(array_filter(self::flatten($value), 'strlen'));
			}

			$text = trim((string) $value);
			if ($text === '') {
				return 0;
			}

			$json = Json::toArray($text);
			if (!empty($json)) {
				return count(array_filter(self::flatten($json), 'strlen'));
			}

			return 1;
		}

		protected static function scalarValue($value)
		{
			return is_array($value) ? implode("\n", self::flatten($value)) : (string) $value;
		}

		protected static function numericScalarValue(array $field, $value)
		{
			$value = self::scalarValue($value);
			return isset($field['rubric_field_type']) && (string) $field['rubric_field_type'] === 'number'
				? Types\NumberValue::normalize($value)
				: $value;
		}

		protected static function length($value)
		{
			return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
		}

		protected static function allowedValues($field, $value, $param)
		{
			$allowed = array_filter(array_map('trim', explode(',', (string) $param)), 'strlen');
			// Без явного списка — берём допустимые значения из опций поля (choice-типы).
			if (empty($allowed) && isset($field['options']) && is_array($field['options'])) {
				foreach ($field['options'] as $opt) {
					if (is_array($opt) && isset($opt['value']) && (string) $opt['value'] !== '') {
						$allowed[] = (string) $opt['value'];
					}
				}
			}

			if (empty($allowed)) {
				return true;
			}

			$values = is_array($value) ? self::flatten($value) : array_filter(array_map('trim', preg_split('/\s*,\s*/', (string) $value)), 'strlen');
			foreach ($values as $v) {
				if (!in_array((string) $v, $allowed, true)) {
					return false;
				}
			}

			return true;
		}

		protected static function allowedValueArray($value, array $allowed)
		{
			$allowed = array_values(array_unique(array_map('strval', $allowed)));
			$values = is_array($value)
				? self::flatten($value)
				: array_filter(array_map('trim', preg_split('/\s*,\s*/', (string) $value)), 'strlen');
			foreach ($values as $candidate) {
				if ((string) $candidate !== '' && !in_array((string) $candidate, $allowed, true)) { return false; }
			}

			return true;
		}

		protected static function allowedExtensions($value, $param)
		{
			$exts = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) $param)), 'strlen'));
			if (empty($exts)) {
				return true;
			}

			$text = self::scalarValue($value);
			if (trim($text) === '') {
				return true;
			}

			if (preg_match_all('#[^\s"|]+\.([a-z0-9]{2,5})#i', $text, $m)) {
				foreach ($m[1] as $ext) {
					if (!in_array(strtolower($ext), $exts, true)) {
						return false;
					}
				}
			}

			return true;
		}

		protected static function validDate($value, $format)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return true;
			}

			$dt = \DateTime::createFromFormat($format, $value);
			return $dt !== false && $dt->format($format) === $value;
		}

		protected static function flatten(array $data)
		{
			$out = array();
			array_walk_recursive($data, function ($v) use (&$out) {
				if (is_scalar($v) && (string) $v !== '') {
					$out[] = (string) $v;
				}
			});
			return $out;
		}
	}
