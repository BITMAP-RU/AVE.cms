<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DimensionsValue.php
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

	/** Length, width and height with JSON storage. */
	class DimensionsValue extends AbstractFieldType
	{
		public function code()
		{
			return 'dimensions';
		}

		public function name()
		{
			return 'Габариты';
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'unit',
					'type' => 'select',
					'label' => 'Единица измерения',
					'options' => array('mm' => 'мм', 'cm' => 'см', 'm' => 'м'),
					'default' => 'cm',
				),
				array('key' => 'precision', 'type' => 'int', 'label' => 'Знаков после запятой', 'default' => 0),
				array('key' => 'require_all', 'type' => 'bool', 'label' => 'Требовать все три размера', 'default' => true),
				array('key' => 'show_volume', 'type' => 'bool', 'label' => 'Показывать объём', 'default' => false),
				array('key' => 'separator', 'type' => 'text', 'label' => 'Разделитель размеров', 'default' => '×'),
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
			$dimensions = self::parseValue($ctx->value);
			$name = $this->attr($ctx->inputName());
			$unit = $this->unit($ctx);
			$labels = array('length' => 'Длина', 'width' => 'Ширина', 'height' => 'Высота');
			$html = '<div class="documents-dimensions-control">';
			foreach ($labels as $key => $label) {
				$html .= $this->dimensionInput($name . '[' . $key . ']', $label, $dimensions[$key], $unit);
			}

			return $html . '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$dimensions = self::parseValue($ctx->value);
			$values = array_filter($dimensions, function ($value) { return $value !== ''; });
			if (empty($values)) {
				return '';
			}

			$precision = max(0, min(6, (int) $ctx->setting('precision', 0)));
			$separator = trim((string) $ctx->setting('separator', '×'));
			$separator = $separator !== '' ? $separator : '×';
			$formatted = array();
			foreach (array('length', 'width', 'height') as $key) {
				$formatted[] = $dimensions[$key] === '' ? '—' : $this->formatNumber($dimensions[$key], $precision);
			}

			$html = implode('&nbsp;' . $this->e($separator) . '&nbsp;', $formatted) . '&nbsp;' . $this->e($this->unit($ctx));
			if ($ctx->setting('show_volume', false) && count($values) === 3) {
				$volume = (float) $dimensions['length'] * (float) $dimensions['width'] * (float) $dimensions['height'];
				$html .= ' <small>(' . $this->formatNumber($volume, $precision) . '&nbsp;' . $this->e($this->unit($ctx)) . '³)</small>';
			}

			return $html;
		}

		public function save(FieldContext $ctx)
		{
			$dimensions = self::parseValue($ctx->value);
			if (count(array_filter($dimensions, function ($value) { return $value !== ''; })) === 0) {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Укажите корректные габариты');
			}

			return Json::encode($dimensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function valid(FieldContext $ctx)
		{
			$raw = self::rawValue($ctx->value);
			$dimensions = self::parseValue($ctx->value);
			$filled = 0;
			foreach (array('length', 'width', 'height') as $key) {
				if ($raw[$key] !== '' && ($dimensions[$key] === '' || (float) $dimensions[$key] <= 0)) {
					return false;
				}

				if ($dimensions[$key] !== '') {
					$filled++;
				}
			}

			return $filled === 0 || !$ctx->setting('require_all', true) || $filled === 3;
		}

		public static function parseValue($value)
		{
			$raw = self::rawValue($value);
			return array(
				'length' => NumberValue::normalize($raw['length']),
				'width' => NumberValue::normalize($raw['width']),
				'height' => NumberValue::normalize($raw['height']),
			);
		}

		protected static function rawValue($value)
		{
			if (is_array($value)) {
				return array(
					'length' => isset($value['length']) ? trim((string) $value['length']) : '',
					'width' => isset($value['width']) ? trim((string) $value['width']) : '',
					'height' => isset($value['height']) ? trim((string) $value['height']) : '',
				);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array('length' => '', 'width' => '', 'height' => '');
			}

			$json = Json::toArray($value, array());
			if (!empty($json) || $value === '{}') {
				return array(
					'length' => isset($json['length']) ? trim((string) $json['length']) : '',
					'width' => isset($json['width']) ? trim((string) $json['width']) : '',
					'height' => isset($json['height']) ? trim((string) $json['height']) : '',
				);
			}

			$parts = preg_split('/[|;xх×]/u', $value, 3);
			return array(
				'length' => isset($parts[0]) ? trim((string) $parts[0]) : '',
				'width' => isset($parts[1]) ? trim((string) $parts[1]) : '',
				'height' => isset($parts[2]) ? trim((string) $parts[2]) : '',
			);
		}

		protected function dimensionInput($name, $label, $value, $unit)
		{
			return '<label class="field"><span class="field-label">' . $this->e($label) . '</span><span class="input-group">'
				. '<input class="input" type="text" inputmode="decimal" name="' . $name . '" value="' . $this->attr($value) . '">'
				. '<span class="input-addon">' . $this->e($unit) . '</span></span></label>';
		}

		protected function unit(FieldContext $ctx)
		{
			$unit = (string) $ctx->setting('unit', 'cm');
			$units = array('mm' => 'мм', 'cm' => 'см', 'm' => 'м');
			return isset($units[$unit]) ? $units[$unit] : $units['cm'];
		}

		protected function formatNumber($value, $precision)
		{
			return $this->e(number_format((float) $value, (int) $precision, ',', ' '));
		}
	}
