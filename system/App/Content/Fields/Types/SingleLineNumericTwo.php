<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/SingleLineNumericTwo.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Числовое из двух частей (AVE single_line_numeric_two). */
	class SingleLineNumericTwo extends SingleLineNumeric
	{
		public function code()
		{
			return 'single_line_numeric_two';
		}

		public function name()
		{
			return 'Числовое (две части)';
		}

		public function isNumeric()
		{
			return false;
		}

		public function save(\App\Content\Fields\FieldContext $ctx)
		{
			$parts = is_array($ctx->value) ? $ctx->value : explode('|', (string) $ctx->value);
			return implode('|', array_slice(array_map(array($this, 'numericPart'), $parts), 0, 2));
		}

		protected function numericPart($value)
		{
			return preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', (string) $value));
		}
	}
