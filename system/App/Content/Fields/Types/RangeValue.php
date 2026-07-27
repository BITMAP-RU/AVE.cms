<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/RangeValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;
	use App\Helpers\Json;

	/** Numeric interval with named bounds and JSON storage. */
	class RangeValue extends AbstractFieldType
	{
		public function code()
		{
			return 'range';
		}

		public function name()
		{
			return 'Числовой диапазон';
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'min_label', 'type' => 'text', 'label' => 'Подпись нижней границы', 'default' => 'От'),
				array('key' => 'max_label', 'type' => 'text', 'label' => 'Подпись верхней границы', 'default' => 'До'),
				array('key' => 'unit', 'type' => 'text', 'label' => 'Единица измерения', 'hint' => 'Например: кг, см, ₽.'),
				array('key' => 'precision', 'type' => 'int', 'label' => 'Знаков после запятой', 'default' => 0),
				array('key' => 'allow_open', 'type' => 'bool', 'label' => 'Разрешить одну пустую границу', 'default' => true),
				array('key' => 'separator', 'type' => 'text', 'label' => 'Разделитель на сайте', 'default' => '–'),
			);
		}

		public function validationSchema()
		{
			return array(
				array('key' => 'required', 'type' => 'bool', 'label' => 'Обязательное'),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$range = self::parseValue($ctx->value);
			$name = $this->attr($ctx->inputName());
			$unit = trim((string) $ctx->setting('unit', ''));
			return '<div class="documents-range-control">'
				. $this->rangeInput($name . '[min]', (string) $ctx->setting('min_label', 'От'), $range['min'], $unit)
				. $this->rangeInput($name . '[max]', (string) $ctx->setting('max_label', 'До'), $range['max'], $unit)
				. '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$range = self::parseValue($ctx->value);
			if ($range['min'] === '' && $range['max'] === '') {
				return '';
			}

			$precision = max(0, min(6, (int) $ctx->setting('precision', 0)));
			$unit = trim((string) $ctx->setting('unit', ''));
			$suffix = $unit !== '' ? '&nbsp;' . $this->e($unit) : '';
			if ($range['min'] === '') {
				return $this->e((string) $ctx->setting('max_label', 'До')) . ' ' . $this->formatNumber($range['max'], $precision) . $suffix;
			}

			if ($range['max'] === '') {
				return $this->e((string) $ctx->setting('min_label', 'От')) . ' ' . $this->formatNumber($range['min'], $precision) . $suffix;
			}

			$separator = trim((string) $ctx->setting('separator', '–'));
			$separator = $separator !== '' ? $separator : '–';
			return $this->formatNumber($range['min'], $precision) . '&nbsp;' . $this->e($separator) . '&nbsp;'
				. $this->formatNumber($range['max'], $precision) . $suffix;
		}

		public function save(FieldContext $ctx)
		{
			$range = self::parseValue($ctx->value);
			if ($range['min'] === '' && $range['max'] === '') {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Укажите корректный числовой диапазон');
			}

			return Json::encode($range, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function valid(FieldContext $ctx)
		{
			$raw = self::rawValue($ctx->value);
			$range = self::parseValue($ctx->value);
			if ($range['min'] === '' && $range['max'] === '') {
				return $raw['min'] === '' && $raw['max'] === '';
			}

			if (($raw['min'] !== '' && $range['min'] === '') || ($raw['max'] !== '' && $range['max'] === '')) {
				return false;
			}

			if (!$ctx->setting('allow_open', true) && ($range['min'] === '' || $range['max'] === '')) {
				return false;
			}

			return $range['min'] === '' || $range['max'] === '' || (float) $range['min'] <= (float) $range['max'];
		}

		public static function parseValue($value)
		{
			$raw = self::rawValue($value);
			return array(
				'min' => NumberValue::normalize($raw['min']),
				'max' => NumberValue::normalize($raw['max']),
			);
		}

		protected static function rawValue($value)
		{
			if (is_array($value)) {
				return array(
					'min' => isset($value['min']) ? trim((string) $value['min']) : '',
					'max' => isset($value['max']) ? trim((string) $value['max']) : '',
				);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array('min' => '', 'max' => '');
			}

			$json = Json::toArray($value, array());
			if (!empty($json) || $value === '{}') {
				return array(
					'min' => isset($json['min']) ? trim((string) $json['min']) : '',
					'max' => isset($json['max']) ? trim((string) $json['max']) : '',
				);
			}

			$parts = preg_split('/[|;]/', $value, 2);
			return array(
				'min' => isset($parts[0]) ? trim((string) $parts[0]) : '',
				'max' => isset($parts[1]) ? trim((string) $parts[1]) : '',
			);
		}

		protected function rangeInput($name, $label, $value, $unit)
		{
			return '<label class="field"><span class="field-label">' . $this->e($label) . '</span><span class="input-group">'
				. '<input class="input" type="text" inputmode="decimal" name="' . $name . '" value="' . $this->attr($value) . '">'
				. ($unit !== '' ? '<span class="input-addon">' . $this->e($unit) . '</span>' : '') . '</span></label>';
		}

		protected function formatNumber($value, $precision)
		{
			return $this->e(number_format((float) $value, (int) $precision, ',', ' '));
		}
	}
