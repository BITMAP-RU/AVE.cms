<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/SingleLineNumericThree.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Числовое из трёх частей (AVE single_line_numeric_three). */
	class SingleLineNumericThree extends SingleLineNumeric
	{
		public function code()
		{
			return 'single_line_numeric_three';
		}

		public function name()
		{
			return 'Числовое (три части)';
		}

		public function isNumeric()
		{
			return false;
		}

		public function save(\App\Content\Fields\FieldContext $ctx)
		{
			$parts = is_array($ctx->value) ? $ctx->value : explode('|', (string) $ctx->value);
			$out = array();
			foreach (array_slice($parts, 0, 3) as $part) {
				$out[] = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', (string) $part));
			}

			return implode('|', $out);
		}
	}
