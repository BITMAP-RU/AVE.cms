<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ColorValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Canonical HEX color with a native picker in Adminx. */
	class ColorValue extends AbstractFieldType
	{
		public function code()
		{
			return 'color';
		}

		public function name()
		{
			return 'Цвет';
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'display',
					'type' => 'select',
					'label' => 'Вывод на сайте',
					'options' => array('value' => 'HEX-значение', 'swatch' => 'Только образец', 'both' => 'Образец и HEX'),
					'default' => 'value',
				),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$value = self::normalize($this->value($ctx));
			return '<div class="documents-color-control" data-document-color-control>'
				. '<input type="color" value="' . $this->attr($value !== '' ? $value : '#000000') . '" data-document-color-picker aria-label="Выбрать цвет">'
				. '<input type="text" class="input mono" placeholder="#rrggbb" name="' . $this->attr($ctx->inputName())
				. '" value="' . $this->attr($value) . '" data-document-color-value>'
				. '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$value = self::normalize($this->value($ctx));
			if ($value === '') {
				return '';
			}

			$display = (string) $ctx->setting('display', 'value');
			if ($display === 'value') {
				return $this->e($value);
			}

			$swatch = '<span aria-hidden="true" style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;border:1px solid rgba(0,0,0,.2);border-radius:3px;background-color:'
				. $this->attr($value) . '"></span>';
			return $display === 'swatch' ? $swatch : $swatch . '&nbsp;' . $this->e($value);
		}

		public function save(FieldContext $ctx)
		{
			$raw = $this->value($ctx);
			if ($raw === '') {
				return '';
			}

			$value = self::normalize($raw);
			if ($value === '') {
				throw new \RuntimeException('Укажите цвет в формате HEX');
			}

			return $value;
		}

		public function valid(FieldContext $ctx)
		{
			$value = $this->value($ctx);
			return $value === '' || self::normalize($value) !== '';
		}

		public static function normalize($value)
		{
			$value = strtolower(trim((string) $value));
			$value = ltrim($value, '#');
			if (preg_match('/^[0-9a-f]{3}$/', $value)) {
				$value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
			}

			return preg_match('/^[0-9a-f]{6}$/', $value) ? '#' . $value : '';
		}

		protected function value(FieldContext $ctx)
		{
			$value = $ctx->value;
			if (is_array($value)) {
				$value = isset($value['raw']) ? $value['raw'] : '';
			}

			return trim((string) $value);
		}
	}
