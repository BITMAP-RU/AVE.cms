<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/SingleLine.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Однострочное поле (AVE single_line). value = строка. */
	class SingleLine extends AbstractFieldType
	{
		public function code()
		{
			return 'single_line';
		}

		public function name()
		{
			return 'Однострочное поле';
		}

		public function settingsSchema()
		{
			return array();
		}

		public function renderEdit(FieldContext $ctx)
		{
			// maxLength is the canonical validation setting. Keep the old
			// maxlength key readable for fields saved by earlier adminx builds.
			$max = (int) $ctx->setting('maxLength', $ctx->setting('maxlength', 0));
			return '<input type="text" class="input" name="' . $this->attr($ctx->inputName('[value]')) . '"'
				. ' value="' . $this->attr($ctx->value) . '"'
				. ($max > 0 ? ' maxlength="' . $max . '"' : '') . '>';
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->e($ctx->value);
		}

		public function save(FieldContext $ctx)
		{
			return trim((string) $ctx->value);
		}
	}
