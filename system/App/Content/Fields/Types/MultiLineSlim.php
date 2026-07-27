<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiLineSlim.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Многострочное «slim» (AVE multi_line_slim) — вариант MultiLine. */
	class MultiLineSlim extends MultiLine
	{
		public function code()
		{
			return 'multi_line_slim';
		}

		public function name()
		{
			return 'Многострочное (slim)';
		}

		protected function rows()
		{
			return 4;
		}
	}
