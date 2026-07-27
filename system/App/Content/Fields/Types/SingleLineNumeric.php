<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/SingleLineNumeric.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Числовое однострочное поле (AVE single_line_numeric). Пишется в field_number_value. */
	class SingleLineNumeric extends AbstractFieldType
	{
		public function code()
		{
			return 'single_line_numeric';
		}

		public function name()
		{
			return 'Числовое';
		}

		public function isNumeric()
		{
			return true;
		}

		public function renderEdit(FieldContext $ctx)
		{
			// Суффикс/единица рисуется редактором документа (rubric_field_settings.suffix
			// + авто-подстановка по названию), поэтому здесь — голый input.
			return '<input type="text" inputmode="decimal" class="input" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr($ctx->value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->e($ctx->value);
		}

		public function save(FieldContext $ctx)
		{
			$v = str_replace(',', '.', (string) $ctx->value);
			return preg_replace('/[^0-9.\-]/', '', $v);
		}
	}
