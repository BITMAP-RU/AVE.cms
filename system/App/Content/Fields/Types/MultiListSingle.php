<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiListSingle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Повторяемый одноколоночный список — AVE multi_list_single. */
	class MultiListSingle extends MultiList
	{
		public function code()
		{
			return 'multi_list_single';
		}

		public function name()
		{
			return 'Одноколоночный список';
		}
	}
