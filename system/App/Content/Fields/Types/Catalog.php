<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Catalog.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;
	use App\Content\Fields\SerialFieldType;

	/** System catalog relation. The editor itself is owned by the Catalog module. */
	class Catalog extends SerialFieldType
	{
		public function code() { return 'catalog'; }

		public function name() { return 'Раздел каталога'; }

		public function renderEdit(FieldContext $ctx)
		{
			return '<input class="input" type="text" name="' . $this->attr($ctx->inputName())
				. '" value="' . $this->attr($ctx->value) . '">';
		}

		public function save(FieldContext $ctx)
		{
			$data = is_array($ctx->value) ? $ctx->value : $this->decode($ctx->value);
			return $this->encodeJson($data);
		}

		public function renderView(FieldContext $ctx)
		{
			$data = $this->decode($ctx->value);
			if (!empty($data['names'])) {
				return $this->e(implode(', ', array_filter(array_map('trim', explode(',', (string) $data['names'])), 'strlen')));
			}

			return '';
		}
	}
