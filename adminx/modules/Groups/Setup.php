<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Groups/Setup.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Groups;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\Permission;
	use App\Common\SystemTables;

	/**
	 * Инициализация RBAC-схемы новой админки: таблицы ролей/прав, seed канонических
	 * ролей и синхронизация прав, объявленных модулями, в {prefix}_permissions.
	 *
	 * Схема совпадает с контрактом App\Common\Permission (forRole/syncRegistryToDb):
	 *   _roles(id, code, name, is_system, ...)
	 *   _role_permissions(role_id, permission_code)
	 *   _permissions(code, module, group_code, name, description, sort_order)
	 *
	 * Роль `admin` при пустом наборе прав получает all_permissions (fallback ядра),
	 * поэтому явных строк ей не требуется.
	 */
	class Setup
	{
		/** Канонические роли новой админки (code => отображаемое имя). */
		public static function roleNames()
		{
			return array(
				'admin'     => 'Администратор',
				'guest'     => 'Гостевая',
				'moderator' => 'Модератор',
				'user'      => 'Пользователи',
			);
		}

		public static function ensureSchema()
		{
			DB::query(
				'CREATE TABLE IF NOT EXISTS ' . SystemTables::table('roles') . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(150) NOT NULL DEFAULT "",
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);

			DB::query(
				'CREATE TABLE IF NOT EXISTS ' . SystemTables::table('role_permissions') . ' (
                role_id INT UNSIGNED NOT NULL,
                permission_code VARCHAR(100) NOT NULL,
                PRIMARY KEY (role_id, permission_code),
                KEY idx_role (role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);

			DB::query(
				'CREATE TABLE IF NOT EXISTS ' . SystemTables::table('permissions') . ' (
                code VARCHAR(100) NOT NULL,
                module VARCHAR(50) NOT NULL DEFAULT "",
                group_code VARCHAR(50) NOT NULL DEFAULT "",
                name VARCHAR(200) NOT NULL DEFAULT "",
                description VARCHAR(255) NOT NULL DEFAULT "",
                sort_order INT NOT NULL DEFAULT 100,
                PRIMARY KEY (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);
		}

		/** Завести канонические роли + роли, встречающиеся у пользователей. */
		public static function seedRoles()
		{
			$names = self::roleNames();

			//-- роли, реально используемые в users
			$used = DB::query('SELECT DISTINCT role FROM ' . SystemTables::table('users') . ' WHERE role != ""')->getAll();
			foreach ($used ?: array() as $row) {
				$code = trim((string) ((array) $row)['role']);
				if ($code !== '' && !isset($names[$code])) {
					$names[$code] = $code;
				}
			}

			$now = date('Y-m-d H:i:s');
			$seeded = 0;
			foreach ($names as $code => $name) {
				DB::query(
					'INSERT INTO ' . SystemTables::table('roles') . ' (code, name, is_system, created_at, updated_at)
                 VALUES (%s, %s, %i, %s, %s)
                 ON DUPLICATE KEY UPDATE name = IF(name = "", VALUES(name), name)',
					$code, $name, (in_array($code, array('admin', 'guest', 'moderator', 'user'), true) ? 1 : 0), $now, $now
				);
				$seeded++;
			}

			return $seeded;
		}

		/** Прогнать всё: схема + роли + синхронизация прав модулей. */
		public static function run()
		{
			self::ensureSchema();
			$roles = self::seedRoles();
			$perms = Permission::syncRegistryToDb();

			return array(
				'roles' => $roles,
				'permissions' => $perms,
			);
		}
	}
