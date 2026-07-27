<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Youtube.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Видео YouTube (AVE youtube). value = URL или ID ролика. */
	class Youtube extends AbstractFieldType
	{
		public function code()
		{
			return 'youtube';
		}

		public function name()
		{
			return 'Видео YouTube';
		}

		protected function videoId($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}

			if (preg_match('#(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{6,})#', $value, $m)) {
				return $m[1];
			}

			return preg_match('#^[A-Za-z0-9_-]{6,}$#', $value) ? $value : '';
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<input class="input mono" type="text" placeholder="https://youtu.be/… или ID" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr($ctx->value) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			$id = $this->videoId($ctx->value);
			if ($id === '') {
				return '';
			}

			return '<div class="video-embed"><iframe width="560" height="315" src="https://www.youtube.com/embed/'
				. $this->attr($id) . '" frameborder="0" allowfullscreen></iframe></div>';
		}
	}
