<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Tags.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;

	/** Теги (AVE tags). Список строк; legacy — через запятую/пайп. */
	class Tags extends MultiList
	{
		public function code()
		{
			return 'tags';
		}

		public function name()
		{
			return 'Теги';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$items = $this->linesFromValue($ctx->value);
			return '<input class="input" type="text" placeholder="тег1, тег2, тег3" name="'
				. $this->attr($ctx->inputName()) . '" value="' . $this->attr(implode(', ', $items)) . '">';
		}

		public function save(FieldContext $ctx)
		{
			$raw = is_array($ctx->value) ? implode(',', $ctx->value) : (string) $ctx->value;
			$tags = array();
			foreach (preg_split('/\s*,\s*/', $raw) as $t) {
				$t = trim($t);
				if ($t !== '') {
					$tags[] = $t;
				}
			}

			return $this->encodeJson(array_values(array_unique($tags)));
		}
	}
