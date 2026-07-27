<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Code.php
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
	 * HTML/код (AVE code). Хранится в longtext, на выводе НЕ экранируется
	 * (доверенный контент редактора). PHP не исполняется — только вывод HTML.
	 */
	class Code extends AbstractFieldType
	{
		public function code()
		{
			return 'code';
		}

		public function name()
		{
			return 'HTML / код';
		}

		public function storage()
		{
			return 'text';
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'language', 'type' => 'select', 'label' => 'Язык подсветки',
					  'options' => array('html', 'twig', 'php', 'css', 'javascript', 'json', 'sql', 'text'), 'default' => 'html'),
				array('key' => 'height', 'type' => 'int', 'label' => 'Высота редактора, px', 'default' => 280),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<textarea class="textarea mono" rows="10" spellcheck="false" data-code-editor data-language="'
				. $this->attr($this->language($ctx)) . '" data-code-height="' . $this->editorHeight($ctx)
				. '" name="' . $this->attr($ctx->inputName()) . '">' . $this->e($ctx->value) . '</textarea>';
		}

		public function renderView(FieldContext $ctx)
		{
			return (string) $ctx->value;
		}

		protected function language(FieldContext $ctx)
		{
			$language = (string) $ctx->setting('language', 'html');
			$allowed = array('html', 'twig', 'smarty', 'php', 'css', 'javascript', 'js', 'json', 'sql', 'text');
			return in_array($language, $allowed, true) ? $language : 'html';
		}

		protected function editorHeight(FieldContext $ctx)
		{
			$height = (int) $ctx->setting('height', 280);
			if ($height < 140) {
				return 140;
			}

			if ($height > 900) {
				return 900;
			}

			return $height;
		}
	}
