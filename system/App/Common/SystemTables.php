<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/SystemTables.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Resolves framework-owned tables shared by the control panel and public runtime. */
	class SystemTables
	{
		protected static $allowed = array(
			'users', 'users_session', 'roles', 'role_permissions', 'permissions',
			'settings', 'audit_log', 'module_migrations', 'module_migration_attempts',
			'modules', 'module_events', 'ip_blocks', 'referrer_log',
			'console_snippets', 'admin_todos', 'admin_kanban', 'admin_kanban_columns',
			'admin_notes', 'admin_reminders', 'not_found_log',
			'admin_saved_views',
			'constants', 'countries', 'paginations',
			'theme_asset_revisions',
			'api_tokens', 'api_idempotency',
		);

		public static function table($suffix)
		{
			$suffix = (string) $suffix;
			if (!in_array($suffix, self::$allowed, true)) {
				throw new \InvalidArgumentException('Некорректная системная таблица');
			}

			return PublicConfiguration::prefix('system') . '_' . $suffix;
		}

		public static function prefix()
		{
			return PublicConfiguration::prefix('system');
		}
	}
