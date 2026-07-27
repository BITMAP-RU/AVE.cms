<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFromRubAll.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Все документы из рубрики (AVE doc_from_rub_all) — множественный. */
	class DocFromRubAll extends DocFromRubMulti
	{
		public function code()
		{
			return 'doc_from_rub_all';
		}

		public function name()
		{
			return 'Документы из рубрики (все)';
		}
	}
