<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DateTimeValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Date or date-time for new fields, stored as a Unix timestamp. */
	class DateTimeValue extends AbstractFieldType
	{
		public function code()
		{
			return 'date_time';
		}

		public function name()
		{
			return 'Дата и время';
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
					'label' => 'Режим поля',
					'options' => array(
						'date' => 'Только дата',
						'datetime' => 'Дата и время',
					),
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
						'Y-m-d H:i' => '2026-12-31 18:30',
						'custom' => 'Свой PHP-формат',
					),
					'default' => 'd.m.Y',
				),
				array(
					'key' => 'custom_format',
					'type' => 'text',
					'label' => 'Свой формат',
					'default' => 'd.m.Y',
					'hint' => 'Например: d.m.Y, H:i. Используется только при выборе своего формата.',
				),
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
			$timestamp = (int) self::normalize($ctx->value);
			$dateOnly = $this->mode($ctx) === 'date';
			$value = $timestamp > 0 ? date($dateOnly ? 'Y-m-d' : 'Y-m-d\TH:i', $timestamp) : '';
			return '<input type="' . ($dateOnly ? 'date' : 'datetime-local') . '" class="input" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr($value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			$timestamp = (int) self::normalize($ctx->value);
			if ($timestamp <= 0) {
				return '';
			}

			$format = (string) $ctx->setting('display_format', $this->mode($ctx) === 'date' ? 'd.m.Y' : 'd.m.Y H:i');
			if ($format === 'custom') {
				$format = trim((string) $ctx->setting('custom_format', 'd.m.Y'));
			}

			if ($format === '' || strlen($format) > 40 || preg_match('/[<>]/', $format)) {
				$format = $this->mode($ctx) === 'date' ? 'd.m.Y' : 'd.m.Y H:i';
			}

			return $this->e(date($format, $timestamp));
		}

		public function save(FieldContext $ctx)
		{
			$raw = trim((string) $ctx->value);
			if ($raw === '') {
				return '';
			}

			$timestamp = self::normalize($raw);
			if ($timestamp === '') {
				throw new \RuntimeException('Укажите корректную дату и время');
			}

			return $timestamp;
		}

		public static function normalize($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}

			if (preg_match('/^-?\d+$/', $value)) {
				return (string) (int) $value;
			}

			$formats = array('Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y');
			foreach ($formats as $format) {
				$date = \DateTime::createFromFormat('!' . $format, $value);
				$errors = \DateTime::getLastErrors();
				if ($date instanceof \DateTime && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
					&& $date->format($format) === $value) {
					return (string) $date->getTimestamp();
				}
			}

			return '';
		}

		protected function mode(FieldContext $ctx)
		{
			return (string) $ctx->setting('mode', 'date') === 'datetime' ? 'datetime' : 'date';
		}
	}
