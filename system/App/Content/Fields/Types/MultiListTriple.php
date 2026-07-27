<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiListTriple.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Трёхколоночный список (AVE multi_list_triple). Хранится сериализованным массивом.
	 * Adminx использует повторяемый редактор трёх колонок с DnD-сортировкой.
	 */
	class MultiListTriple extends MultiList
	{
		public function code()
		{
			return 'multi_list_triple';
		}

		public function name()
		{
			return 'Трёхколоночный список';
		}
	}
