<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Teasers.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\SerialFieldType;
	use App\Content\Fields\FieldContext;
	use App\Content\Fields\DocumentRelationFieldTrait;
	use App\Content\Fields\DocumentRelationValue;

	/** Тизеры документов (AVE teasers). Сериализованный список ID документов. */
	class Teasers extends SerialFieldType
	{
		use DocumentRelationFieldTrait;

		public function code()
		{
			return 'teasers';
		}

		public function name()
		{
			return 'Тизеры документов';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$ids = $this->ids($ctx->value);
			return '<textarea class="textarea" rows="3" placeholder="ID документов через запятую или с новой строки" name="'
				. $this->attr($ctx->inputName()) . '">' . $this->e(implode(', ', $ids)) . '</textarea>';
		}

		public function save(FieldContext $ctx)
		{
			return DocumentRelationValue::encodeStorage($this->code(), $ctx->value);
		}

		public function renderView(FieldContext $ctx)
		{
			$ids = $this->ids($ctx->value);
			if (empty($ids)) {
				return '';
			}

			$items = array();
			foreach ($ids as $id) {
				$html = (string) \App\Frontend\PublicRequestApi::teaser($id);
				if ($html !== '') {
					$items[] = $html;
				}
			}

			return implode(PHP_EOL, $items);
		}

		protected function ids($value)
		{
			return DocumentRelationValue::ids($value);
		}
	}
