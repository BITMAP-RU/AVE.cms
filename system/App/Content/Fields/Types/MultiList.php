<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiList.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\SerialFieldType;
	use App\Content\Fields\FieldContext;

	/** Список значений (AVE multi_list). Сериализованный массив. */
	class MultiList extends SerialFieldType
	{
		public function code()
		{
			return 'multi_list';
		}

		public function name()
		{
			return 'Список значений';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$items = $this->linesFromValue($ctx->value);
			return '<textarea class="textarea" rows="4" placeholder="по значению на строку" name="'
				. $this->attr($ctx->inputName()) . '">' . $this->e(implode("\n", $items)) . '</textarea>';
		}

		public function save(FieldContext $ctx)
		{
			return $this->encodeJson($this->linesFromValue($ctx->value));
		}

		public function renderView(FieldContext $ctx)
		{
			$items = $this->flatten($this->decode($ctx->value));
			if (empty($items)) {
				return '';
			}

			$out = array();
			foreach ($items as $it) {
				$out[] = $this->e($it);
			}

			return implode(', ', $out);
		}
	}
