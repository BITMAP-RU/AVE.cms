<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Analoque.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;

	/**
	 * Аналоги / «продаётся вместе» (AVE analoque). Множественная связь без
	 * рубрики-источника. Legacy-данные смешанные: CSV id или serialize `label|id`.
	 */
	class Analoque extends DocFromRubMulti
	{
		public function code()
		{
			return 'analoque';
		}

		public function name()
		{
			return 'Аналоги / продаётся вместе';
		}

		public function settingsSchema()
		{
			return array();
		}

		public function renderView(FieldContext $ctx)
		{
			$ids = $this->documentIds($ctx->value);
			return empty($ids) ? '' : $this->renderDocumentLinks($ids);
		}
	}
