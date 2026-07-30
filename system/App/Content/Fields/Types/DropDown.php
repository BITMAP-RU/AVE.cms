<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DropDown.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Выпадающий список (AVE drop_down). Опции — settings.options (fallback legacy default). */
	class DropDown extends AbstractFieldType
	{
		use OptionFieldSupport;

		public function code()
		{
			return 'drop_down';
		}

		public function name()
		{
			return 'Выпадающий список';
		}

		public function isChoice()
		{
			return true;
		}

		public function settingsSchema()
		{
			return $this->optionSettingsSchema(
				array('key' => 'options', 'type' => 'list', 'label' => 'Варианты')
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			if (empty($map)) {
				return '<input type="text" class="input" name="' . $this->attr($ctx->inputName())
					. '" value="' . $this->attr($ctx->value) . '">';
			}

			$html = '<select class="select" name="' . $this->attr($ctx->inputName()) . '"><option value="">— не выбрано —</option>';
			foreach ($map as $value => $label) {
				$sel = ((string) $value === (string) $ctx->value) ? ' selected' : '';
				$html .= '<option value="' . $this->attr($value) . '"' . $sel . '>' . $this->e($label) . '</option>';
			}

			return $html . '</select>';
		}

		public function renderView(FieldContext $ctx)
		{
			$value = (string) $ctx->value;
			$map = $this->optionMap($ctx);
			return $this->e(array_key_exists($value, $map) ? $map[$value] : $value);
		}
	}
