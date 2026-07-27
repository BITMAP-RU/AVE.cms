<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/DocumentMediaFieldType.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Optional contract for fields whose value contains document-owned media URLs. */
	interface DocumentMediaFieldType
	{
		/** @return string[] */
		public function mediaPaths($value);

		/** Replace exact URLs without changing the rest of the field payload. */
		public function replaceMediaPaths($value, array $replacements);
	}
