<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/CheckboxMulti.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Множественный выбор флажками (AVE checkbox_multi) — как multi_checkbox. */
	class CheckboxMulti extends MultiCheckbox
	{
		public function code()
		{
			return 'checkbox_multi';
		}

		public function name()
		{
			return 'Флажки (множественный выбор)';
		}
	}
