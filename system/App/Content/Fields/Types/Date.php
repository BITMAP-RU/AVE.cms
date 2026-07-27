<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Date.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Дата (AVE date). Хранится строкой. */
	class Date extends AbstractFieldType
	{
		public function code()
		{
			return 'date';
		}

		public function name()
		{
			return 'Дата';
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<input type="text" class="input" placeholder="дд.мм.гггг" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr($ctx->value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->e($ctx->value);
		}
	}
