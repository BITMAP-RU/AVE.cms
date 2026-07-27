<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFromRubCheck.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Документы из рубрики с выбором (AVE doc_from_rub_check) — множественный. */
	class DocFromRubCheck extends DocFromRubMulti
	{
		public function code()
		{
			return 'doc_from_rub_check';
		}

		public function name()
		{
			return 'Документы из рубрики (выбор)';
		}
	}
