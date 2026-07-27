<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/PublicUserTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/** Resolves public profiles; Adminx access is linked through system users.legacy_id. */
	class PublicUserTables
	{
		protected static $allowed = array(
			'users', 'users_session', 'auth_tokens', 'user_profile_fields',
			'user_profile_values', 'user_groups', 'auth_settings',
			'auth_templates', 'user_identities',
		);

		public static function table($suffix)
		{
			if (!in_array((string) $suffix, self::$allowed, true)) {
				throw new \InvalidArgumentException('Некорректная таблица публичных пользователей');
			}

			return PublicConfiguration::prefix('public_user') . '_' . $suffix;
		}

		public static function compatibilityPrefix()
		{
			$table = self::table('users');
			return substr($table, 0, -strlen('_users'));
		}

	}
