<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFromRubSearch.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Документы из рубрики через поиск (AVE doc_from_rub_search) — множественный. */
	class DocFromRubSearch extends DocFromRubMulti
	{
		public function code()
		{
			return 'doc_from_rub_search';
		}

		public function name()
		{
			return 'Документы из рубрики (поиск)';
		}
	}
