<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Checkbox.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Флажок 0/1 (AVE checkbox). value = '0'|'1'. */
	class Checkbox extends AbstractFieldType
	{
		public function code()
		{
			return 'checkbox';
		}

		public function name()
		{
			return 'Флажок';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$checked = ((string) $ctx->value === '1') ? ' checked' : '';
			$name = $this->attr($ctx->inputName());
			return '<input type="hidden" name="' . $name . '" value="0">'
				. '<label class="switch"><input type="checkbox" name="' . $name . '" value="1"' . $checked . '>'
				. '<span class="switch-track"></span><span>Включено</span></label>';
		}

		public function renderView(FieldContext $ctx)
		{
			$value = trim((string) $ctx->value);
			if ((int) $value === 1) {
				return '1';
			}

			return $value === '0' ? '0' : '';
		}

		public function save(FieldContext $ctx)
		{
			return ((string) $ctx->value === '1') ? '1' : '0';
		}
	}
