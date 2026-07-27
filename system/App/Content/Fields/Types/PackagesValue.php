<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/PackagesValue.php
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

	/** Multiple shipping packages with dimensions and weight. */
	class PackagesValue extends AbstractFieldType
	{
		public function code()
		{
			return 'packages';
		}

		public function name()
		{
			return 'Упаковки товара';
		}

		public function isMultiple()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'dimension_unit', 'type' => 'select', 'label' => 'Единица габаритов', 'options' => array('mm' => 'мм', 'cm' => 'см', 'm' => 'м'), 'default' => 'cm'),
				array('key' => 'weight_unit', 'type' => 'select', 'label' => 'Единица веса', 'options' => array('g' => 'г', 'kg' => 'кг'), 'default' => 'kg'),
				array('key' => 'require_weight', 'type' => 'bool', 'label' => 'Вес обязателен', 'default' => true),
				array('key' => 'max_packages', 'type' => 'int', 'label' => 'Максимум упаковок', 'default' => 20),
				array('key' => 'precision', 'type' => 'int', 'label' => 'Знаков после запятой', 'default' => 1),
				array('key' => 'show_total_weight', 'type' => 'bool', 'label' => 'Показывать общий вес', 'default' => true),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<textarea class="textarea mono" rows="6" name="' . $this->attr($ctx->inputName())
				. '" placeholder="длина|ширина|высота|вес">' . $this->e($ctx->value) . '</textarea>';
		}

		public function renderView(FieldContext $ctx)
		{
			$items = self::parseItems($ctx->value);
			if (empty($items)) {
				return '';
			}

			$precision = max(0, min(6, (int) $ctx->setting('precision', 1)));
			$dimensionUnit = $this->dimensionUnit($ctx);
			$weightUnit = $this->weightUnit($ctx);
			$totalWeight = 0.0;
			$html = '<ul class="field-packages">';
			foreach ($items as $index => $item) {
				$totalWeight += $item['weight'] !== '' ? (float) $item['weight'] : 0.0;
				$dimensions = array();
				foreach (array('length', 'width', 'height') as $key) {
					$dimensions[] = $this->formatNumber($item[$key], $precision);
				}

				$html .= '<li><span>Упаковка ' . ((int) $index + 1) . ':</span> '
					. implode('&nbsp;×&nbsp;', $dimensions) . '&nbsp;' . $this->e($dimensionUnit)
					. ($item['weight'] !== '' ? ' · ' . $this->formatNumber($item['weight'], $precision) . '&nbsp;' . $this->e($weightUnit) : '')
					. '</li>';
			}

			$html .= '</ul>';
			if ($ctx->setting('show_total_weight', true) && $totalWeight > 0) {
				$html .= '<small>Общий вес: ' . $this->formatNumber((string) $totalWeight, $precision) . '&nbsp;' . $this->e($weightUnit) . '</small>';
			}

			return $html;
		}

		public function save(FieldContext $ctx)
		{
			$items = self::parseItems($ctx->value);
			if (empty($items)) {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Проверьте габариты и вес упаковок');
			}

			return Json::encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function valid(FieldContext $ctx)
		{
			$rawItems = self::rawItems($ctx->value);
			$items = self::parseItems($ctx->value);
			$max = max(1, min(100, (int) $ctx->setting('max_packages', 20)));
			if (count($rawItems) > $max || count($items) !== count($rawItems)) {
				return false;
			}

			foreach ($items as $item) {
				foreach (array('length', 'width', 'height') as $key) {
					if ($item[$key] === '' || (float) $item[$key] <= 0) {
						return false;
					}
				}

				if ($ctx->setting('require_weight', true) && ($item['weight'] === '' || (float) $item['weight'] <= 0)) {
					return false;
				}

				if ($item['weight'] !== '' && (float) $item['weight'] <= 0) {
					return false;
				}
			}

			return true;
		}

		public static function parseItems($value)
		{
			$out = array();
			foreach (self::rawItems($value) as $item) {
				$normalized = array(
					'length' => NumberValue::normalize(isset($item['length']) ? $item['length'] : ''),
					'width' => NumberValue::normalize(isset($item['width']) ? $item['width'] : ''),
					'height' => NumberValue::normalize(isset($item['height']) ? $item['height'] : ''),
					'weight' => NumberValue::normalize(isset($item['weight']) ? $item['weight'] : ''),
				);
				if (count(array_filter($normalized, 'strlen')) > 0) {
					$out[] = $normalized;
				}
			}

			return $out;
		}

		public static function encodeItems($value)
		{
			$items = self::parseItems($value);
			return empty($items) ? '' : Json::encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function rawItems($value)
		{
			if (is_array($value)) {
				$value = isset($value['items']) && is_array($value['items']) ? $value['items'] : $value;
				return self::cleanRawItems($value);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			$json = Json::toArray($value, array());
			if (!empty($json)) {
				$json = isset($json['items']) && is_array($json['items']) ? $json['items'] : $json;
				return self::cleanRawItems($json);
			}

			$items = array();
			foreach (preg_split('/\r\n|\r|\n/', $value) ?: array() as $line) {
				$parts = explode('|', trim((string) $line));
				if (count($parts) >= 3) {
					$items[] = array(
						'length' => isset($parts[0]) ? $parts[0] : '',
						'width' => isset($parts[1]) ? $parts[1] : '',
						'height' => isset($parts[2]) ? $parts[2] : '',
						'weight' => isset($parts[3]) ? $parts[3] : '',
					);
				}
			}

			return self::cleanRawItems($items);
		}

		protected static function cleanRawItems(array $items)
		{
			$out = array();
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}

				$raw = array();
				foreach (array('length', 'width', 'height', 'weight') as $key) {
					$raw[$key] = isset($item[$key]) ? trim((string) $item[$key]) : '';
				}

				if (count(array_filter($raw, 'strlen')) > 0) {
					$out[] = $raw;
				}
			}

			return $out;
		}

		protected function dimensionUnit(FieldContext $ctx)
		{
			$units = array('mm' => 'мм', 'cm' => 'см', 'm' => 'м');
			$unit = (string) $ctx->setting('dimension_unit', 'cm');
			return isset($units[$unit]) ? $units[$unit] : $units['cm'];
		}

		protected function weightUnit(FieldContext $ctx)
		{
			return (string) $ctx->setting('weight_unit', 'kg') === 'g' ? 'г' : 'кг';
		}

		protected function formatNumber($value, $precision)
		{
			return $this->e(number_format((float) $value, (int) $precision, ',', ' '));
		}
	}
