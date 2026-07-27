<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/AddressValue.php
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

	/** Structured postal address with optional geographic coordinates. */
	class AddressValue extends AbstractFieldType
	{
		protected static $keys = array('postal_code', 'region', 'city', 'street', 'building', 'unit', 'latitude', 'longitude');

		public function code()
		{
			return 'address';
		}

		public function name()
		{
			return 'Адрес';
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'public_format',
					'type' => 'select',
					'label' => 'Публичный формат',
					'options' => array('short' => 'Краткий', 'full' => 'Полный', 'multiline' => 'Полный в две строки'),
					'default' => 'full',
				),
				array(
					'key' => 'required_parts',
					'type' => 'select',
					'label' => 'Обязательные части',
					'options' => array('none' => 'Без ограничений', 'city' => 'Город', 'city_street' => 'Город и улица'),
					'default' => 'city',
				),
				array('key' => 'coordinates', 'type' => 'bool', 'label' => 'Редактировать координаты', 'default' => false),
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
			$address = self::parseValue($ctx->value);
			$name = $this->attr($ctx->inputName());
			$html = '<div class="documents-address-control">';
			foreach (array(
				'postal_code' => array('Индекс', 'text'),
				'region' => array('Регион', 'text'),
				'city' => array('Город / населённый пункт', 'text'),
				'street' => array('Улица', 'text'),
				'building' => array('Дом / строение', 'text'),
				'unit' => array('Квартира / офис', 'text'),
			) as $key => $meta) {
				$html .= $this->input($name, $key, $meta[0], $address[$key], $meta[1]);
			}

			if ($ctx->setting('coordinates', false)) {
				$html .= $this->input($name, 'latitude', 'Широта', $address['latitude'], 'decimal')
					. $this->input($name, 'longitude', 'Долгота', $address['longitude'], 'decimal');
			} else {
				$html .= '<input type="hidden" name="' . $name . '[latitude]" value="' . $this->attr($address['latitude']) . '">'
					. '<input type="hidden" name="' . $name . '[longitude]" value="' . $this->attr($address['longitude']) . '">';
			}

			return $html . '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$address = self::parseValue($ctx->value);
			if (self::isEmpty($address)) {
				return '';
			}

			$format = (string) $ctx->setting('public_format', 'full');
			$lineOne = array();
			if ($format !== 'short') {
				$lineOne[] = $address['postal_code'];
				$lineOne[] = $address['region'];
			}

			$lineOne[] = $address['city'];
			$lineTwo = array($address['street']);
			if ($address['building'] !== '') {
				$lineTwo[] = 'д. ' . $address['building'];
			}

			if ($address['unit'] !== '') {
				$lineTwo[] = 'пом. ' . $address['unit'];
			}

			$lineOne = array_values(array_filter($lineOne, 'strlen'));
			$lineTwo = array_values(array_filter($lineTwo, 'strlen'));
			if ($format === 'multiline' && !empty($lineOne) && !empty($lineTwo)) {
				return '<address class="field-address">' . $this->e(implode(', ', $lineOne)) . '<br>'
					. $this->e(implode(', ', $lineTwo)) . '</address>';
			}

			return '<address class="field-address">' . $this->e(implode(', ', array_merge($lineOne, $lineTwo))) . '</address>';
		}

		public function save(FieldContext $ctx)
		{
			$address = self::parseValue($ctx->value);
			if (self::isEmpty($address)) {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Проверьте состав адреса и координаты');
			}

			return Json::encode($address, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function valid(FieldContext $ctx)
		{
			$raw = self::rawValue($ctx->value);
			$address = self::parseValue($ctx->value);
			if (self::isEmpty($address)) {
				return true;
			}

			$limits = array('postal_code' => 24, 'region' => 160, 'city' => 160, 'street' => 200, 'building' => 60, 'unit' => 60);
			foreach ($limits as $key => $limit) {
				if (self::length($address[$key]) > $limit) {
					return false;
				}
			}

			$required = (string) $ctx->setting('required_parts', 'city');
			if (($required === 'city' || $required === 'city_street') && $address['city'] === '') {
				return false;
			}

			if ($required === 'city_street' && $address['street'] === '') {
				return false;
			}

			if (($raw['latitude'] !== '' && $address['latitude'] === '') || ($raw['longitude'] !== '' && $address['longitude'] === '')) {
				return false;
			}

			$hasLatitude = $address['latitude'] !== '';
			$hasLongitude = $address['longitude'] !== '';
			if ($hasLatitude !== $hasLongitude) {
				return false;
			}

			if ($hasLatitude && ((float) $address['latitude'] < -90 || (float) $address['latitude'] > 90)) {
				return false;
			}

			if ($hasLongitude && ((float) $address['longitude'] < -180 || (float) $address['longitude'] > 180)) {
				return false;
			}

			return true;
		}

		public static function parseValue($value)
		{
			$raw = self::rawValue($value);
			$out = array();
			foreach (array('postal_code', 'region', 'city', 'street', 'building', 'unit') as $key) {
				$out[$key] = self::cleanText($raw[$key]);
			}

			$out['latitude'] = NumberValue::normalize($raw['latitude']);
			$out['longitude'] = NumberValue::normalize($raw['longitude']);
			return $out;
		}

		public static function encodeValue($value)
		{
			$address = self::parseValue($value);
			return self::isEmpty($address) ? '' : Json::encode($address, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function rawValue($value)
		{
			if (is_array($value)) {
				return self::withKeys($value);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return self::withKeys(array());
			}

			$json = Json::toArray($value, array());
			if (!empty($json) || $value === '{}') {
				return self::withKeys($json);
			}

			$parts = explode('|', $value, count(self::$keys));
			return self::withKeys(array_combine(array_slice(self::$keys, 0, count($parts)), $parts));
		}

		protected static function withKeys(array $value)
		{
			$out = array();
			foreach (self::$keys as $key) {
				$out[$key] = isset($value[$key]) ? trim((string) $value[$key]) : '';
			}

			return $out;
		}

		protected static function cleanText($value)
		{
			return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
		}

		protected static function isEmpty(array $address)
		{
			return count(array_filter($address, 'strlen')) === 0;
		}

		protected static function length($value)
		{
			return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
		}

		protected function input($name, $key, $label, $value, $mode)
		{
			$decimal = $mode === 'decimal';
			return '<label class="field documents-address-' . $this->attr($key) . '"><span class="field-label">' . $this->e($label) . '</span>'
				. '<input class="input" type="text"' . ($decimal ? ' inputmode="decimal"' : '') . ' name="' . $name . '[' . $this->attr($key)
				. ']" value="' . $this->attr($value) . '"></label>';
		}
	}
