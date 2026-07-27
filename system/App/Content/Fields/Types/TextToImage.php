<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/TextToImage.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Текст-в-картинку (AVE text_to_image). Хранится текст; legacy рендерит картинкой. */
	class TextToImage extends AbstractFieldType
	{
		public function code()
		{
			return 'text_to_image';
		}

		public function name()
		{
			return 'Текст картинкой';
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<input class="input" type="text" name="' . $this->attr($ctx->inputName())
				. '" value="' . $this->attr($ctx->value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->e($ctx->value);
		}
	}
