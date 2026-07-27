<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/PeriodValue.php
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

	/** Date or date-time period with named bounds and JSON storage. */
	class PeriodValue extends AbstractFieldType
	{
		public function code()
		{
			return 'period';
		}

		public function name()
		{
			return 'Период';
		}

		public function isNumeric()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'mode',
					'type' => 'select',
					'label' => 'Точность периода',
					'options' => array('date' => 'Даты', 'datetime' => 'Дата и время'),
					'default' => 'date',
				),
				array(
					'key' => 'display_format',
					'type' => 'select',
					'label' => 'Формат на сайте',
					'options' => array(
						'd.m.Y' => '31.12.2026',
						'd.m.Y H:i' => '31.12.2026 18:30',
						'Y-m-d' => '2026-12-31',
						'custom' => 'Свой PHP-формат',
					),
					'default' => 'd.m.Y',
				),
				array('key' => 'custom_format', 'type' => 'text', 'label' => 'Свой формат', 'default' => 'd.m.Y'),
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
			$period = self::parseValue($ctx->value);
			$name = $this->attr($ctx->inputName());
			$dateOnly = $this->mode($ctx) === 'date';
			return '<div class="documents-period-control">'
				. $this->input($name . '[start]', 'Начало', $period['start'], $dateOnly)
				. $this->input($name . '[end]', 'Окончание', $period['end'], $dateOnly)
				. '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$period = self::parseValue($ctx->value);
			if ($period['start'] === '' && $period['end'] === '') {
				return '';
			}

			$format = $this->format($ctx);
			if ($period['start'] === '') {
				return 'До ' . $this->e(date($format, (int) $period['end']));
			}

			if ($period['end'] === '') {
				return 'С ' . $this->e(date($format, (int) $period['start']));
			}

			if ($period['start'] === $period['end']) {
				return $this->e(date($format, (int) $period['start']));
			}

			$separator = trim((string) $ctx->setting('separator', '–'));
			$separator = $separator !== '' ? $separator : '–';
			return $this->e(date($format, (int) $period['start'])) . '&nbsp;' . $this->e($separator) . '&nbsp;'
				. $this->e(date($format, (int) $period['end']));
		}

		public function save(FieldContext $ctx)
		{
			$period = self::parseValue($ctx->value);
			if ($period['start'] === '' && $period['end'] === '') {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Укажите корректный период');
			}

			return Json::encode($period, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function valid(FieldContext $ctx)
		{
			$raw = self::rawValue($ctx->value);
			$period = self::parseValue($ctx->value);
			if ($period['start'] === '' && $period['end'] === '') {
				return $raw['start'] === '' && $raw['end'] === '';
			}

			if (($raw['start'] !== '' && $period['start'] === '') || ($raw['end'] !== '' && $period['end'] === '')) {
				return false;
			}

			if (!$ctx->setting('allow_open', true) && ($period['start'] === '' || $period['end'] === '')) {
				return false;
			}

			return $period['start'] === '' || $period['end'] === '' || (int) $period['start'] <= (int) $period['end'];
		}

		public static function parseValue($value)
		{
			$raw = self::rawValue($value);
			return array(
				'start' => self::normalizeBound($raw['start']),
				'end' => self::normalizeBound($raw['end']),
			);
		}

		public static function encodeValue($value)
		{
			$period = self::parseValue($value);
			return $period['start'] === '' && $period['end'] === ''
				? ''
				: Json::encode($period, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public static function indexValue($value)
		{
			$period = self::parseValue($value);
			return $period['start'] !== '' ? $period['start'] : ($period['end'] !== '' ? $period['end'] : '0');
		}

		protected static function rawValue($value)
		{
			if (is_array($value)) {
				return array(
					'start' => isset($value['start']) ? trim((string) $value['start']) : '',
					'end' => isset($value['end']) ? trim((string) $value['end']) : '',
				);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array('start' => '', 'end' => '');
			}

			$json = Json::toArray($value, array());
			if (!empty($json) || $value === '{}') {
				return array(
					'start' => isset($json['start']) ? trim((string) $json['start']) : '',
					'end' => isset($json['end']) ? trim((string) $json['end']) : '',
				);
			}

			$parts = explode('|', $value, 2);
			return array(
				'start' => isset($parts[0]) ? trim((string) $parts[0]) : '',
				'end' => isset($parts[1]) ? trim((string) $parts[1]) : '',
			);
		}

		protected static function normalizeBound($value)
		{
			$timestamp = DateTimeValue::normalize($value);
			return $timestamp !== '' && (int) $timestamp > 0 ? (string) (int) $timestamp : '';
		}

		protected function input($name, $label, $timestamp, $dateOnly)
		{
			$value = (int) $timestamp > 0 ? date($dateOnly ? 'Y-m-d' : 'Y-m-d\TH:i', (int) $timestamp) : '';
			return '<label class="field"><span class="field-label">' . $this->e($label) . '</span>'
				. '<input class="input" type="' . ($dateOnly ? 'date' : 'datetime-local') . '" name="' . $name
				. '" value="' . $this->attr($value) . '"></label>';
		}

		protected function mode(FieldContext $ctx)
		{
			return (string) $ctx->setting('mode', 'date') === 'datetime' ? 'datetime' : 'date';
		}

		protected function format(FieldContext $ctx)
		{
			$fallback = $this->mode($ctx) === 'date' ? 'd.m.Y' : 'd.m.Y H:i';
			$format = (string) $ctx->setting('display_format', $fallback);
			if ($format === 'custom') {
				$format = trim((string) $ctx->setting('custom_format', $fallback));
			}

			return $format === '' || strlen($format) > 40 || preg_match('/[<>]/', $format) ? $fallback : $format;
		}
	}
