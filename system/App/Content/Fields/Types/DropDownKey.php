<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DropDownKey.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Список ключ→подпись (AVE drop_down_key). value=ключ, settings.options={key:label}. */
	class DropDownKey extends AbstractFieldType
	{
		use OptionFieldSupport;

		public function code()
		{
			return 'drop_down_key';
		}

		public function name()
		{
			return 'Список (ключ→подпись)';
		}

		public function isChoice()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'options',
					'type' => 'map',
					'label' => 'Список значений',
					'hint' => 'Ключ хранится в документе, подпись показывается пользователю.',
				),
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
			foreach ($map as $key => $label) {
				$sel = ((string) $key === (string) $ctx->value) ? ' selected' : '';
				$html .= '<option value="' . $this->attr($key) . '"' . $sel . '>' . $this->e($label) . '</option>';
			}

			return $html . '</select>';
		}

		public function renderView(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			// Legacy AVE casts an empty value to key 0 and returns an empty string
			// for an unknown key. Public templates rely on both behaviours.
			$key = trim((string) $ctx->value) === '' ? '0' : (string) $ctx->value;
			return array_key_exists($key, $map) ? $this->e($map[$key]) : '';
		}
	}
