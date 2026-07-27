<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ContactsTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves Contacts-owned tables independently from the legacy module prefix. */
	class ContactsTables
	{
		public static function table($suffix)
		{
			if (!in_array((string) $suffix, array('module_contacts_fields', 'module_contacts_forms', 'module_contacts_history'), true)) {
				throw new \InvalidArgumentException('Некорректная таблица Contacts');
			}

			return PublicConfiguration::prefix('contacts') . '_' . $suffix;
		}
	}
