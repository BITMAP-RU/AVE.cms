<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/NumberValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/**
	 * Universal numeric value for new fields.
	 *
	 * The stored value is always a locale-independent decimal string. Display
	 * formatting belongs to settings and does not affect numeric indexing.
	 */
	class NumberValue extends AbstractFieldType
	{
		public function code()
		{
			return 'number';
		}

		public function name()
		{
			return 'Числовое значение';
		}

		public function isNumeric()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'format',
					'type' => 'select',
					'label' => 'Формат вывода',
					'options' => array(
						'decimal' => 'Обычное число',
						'integer' => 'Целое число',
						'money' => 'Денежная сумма',
						'percent' => 'Процент',
						'measurement' => 'Величина с единицей',
						'rating' => 'Рейтинг',
					),
					'default' => 'decimal',
				),
				array(
					'key' => 'precision',
					'type' => 'int',
					'label' => 'Знаков после запятой',
					'default' => 2,
					'hint' => 'От 0 до 6. Для целого числа всегда используется 0.',
				),
				array(
					'key' => 'unit',
					'type' => 'text',
					'label' => 'Единица измерения',
					'hint' => 'Например: кг, см, шт. Используется для формата «Величина».',
				),
				array(
					'key' => 'currency',
					'type' => 'select',
					'label' => 'Валюта',
					'options' => array(
						'RUB' => 'Российский рубль (₽)',
						'USD' => 'Доллар США ($)',
						'EUR' => 'Евро (€)',
						'none' => 'Без обозначения',
					),
					'default' => 'RUB',
				),
				array(
					'key' => 'rating_max',
					'type' => 'number',
					'label' => 'Максимум рейтинга',
					'default' => 5,
					'hint' => 'Используется только для формата «Рейтинг».',
				),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<input type="text" inputmode="decimal" pattern="[+\\-]?[0-9 ]*([.,][0-9]*)?" spellcheck="false" class="input" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr($ctx->value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			$normalized = self::normalize($ctx->value);
			if ($normalized === '') {
				return $this->e($ctx->value);
			}

			$format = $this->format($ctx);
			$precision = $format === 'integer' ? 0 : max(0, min(6, (int) $ctx->setting('precision', 2)));
			$value = number_format((float) $normalized, $precision, ',', ' ');

			if ($format === 'money') {
				return $value . $this->moneySuffix($ctx);
			}

			if ($format === 'percent') {
				return $value . '&nbsp;%';
			}

			if ($format === 'measurement') {
				$unit = trim((string) $ctx->setting('unit', ''));
				return $value . ($unit !== '' ? '&nbsp;' . $this->e($unit) : '');
			}

			if ($format === 'rating') {
				$max = self::normalize($ctx->setting('rating_max', 5));
				return $value . ($max !== '' ? '&nbsp;/&nbsp;' . $this->e($max) : '');
			}

			return $value;
		}

		public function save(FieldContext $ctx)
		{
			$raw = trim((string) $ctx->value);
			if ($raw === '') {
				return '';
			}

			$normalized = self::normalize($raw);
			if ($normalized === '') {
				throw new \RuntimeException('Укажите корректное числовое значение');
			}

			return $normalized;
		}

		/** Convert user input with comma/spaces into a stable decimal string. */
		public static function normalize($value)
		{
			$value = trim(str_replace(array("\xC2\xA0", "\xE2\x80\xAF", ' '), '', (string) $value));
			if ($value === '') {
				return '';
			}

			$comma = strrpos($value, ',');
			$dot = strrpos($value, '.');
			if ($comma !== false && $dot !== false) {
				$decimal = $comma > $dot ? ',' : '.';
				$thousands = $decimal === ',' ? '.' : ',';
				$value = str_replace($thousands, '', $value);
				if ($decimal === ',') {
					$value = str_replace(',', '.', $value);
				}
			} elseif ($comma !== false) {
				$value = str_replace(',', '.', $value);
			}

			if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $value)) {
				return '';
			}

			$negative = isset($value[0]) && $value[0] === '-';
			$value = ltrim($value, '+-');
			$parts = explode('.', $value, 2);
			$integer = ltrim($parts[0], '0');
			$integer = $integer === '' ? '0' : $integer;
			$fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';
			$normalized = $integer . ($fraction !== '' ? '.' . $fraction : '');
			return $negative && $normalized !== '0' ? '-' . $normalized : $normalized;
		}

		protected function format(FieldContext $ctx)
		{
			$format = (string) $ctx->setting('format', 'decimal');
			return in_array($format, array('decimal', 'integer', 'money', 'percent', 'measurement', 'rating'), true)
				? $format
				: 'decimal';
		}

		protected function moneySuffix(FieldContext $ctx)
		{
			$symbols = array('RUB' => '₽', 'USD' => '$', 'EUR' => '€', 'none' => '');
			$currency = (string) $ctx->setting('currency', 'RUB');
			$symbol = isset($symbols[$currency]) ? $symbols[$currency] : $symbols['RUB'];
			return $symbol !== '' ? '&nbsp;' . $symbol : '';
		}
	}
