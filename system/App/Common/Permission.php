<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Permission.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use DB;


	class Permission
	{
		protected static $_permissions = [];
		protected static $_rolePermissions = [];


		protected function __construct()
		{
			//
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function add ($key, array $permission, $icon = '', $priority = 10)
		{
			if (! empty($permission))
			{
				self::$_permissions[$key] = [
					'perms' => $permission,
					'icon' => $icon,
					'priority' => (int)$priority
				];
			}
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function get ()
		{
			return self::$_permissions;
		}


		/*
		|--------------------------------------------------------------------------------------
		| Вернуть плоский список зарегистрированных прав.
		|--------------------------------------------------------------------------------------
		*/
		public static function all ()
		{
			$items = [];

			foreach (self::$_permissions as $module => $group)
			{
				$perms = isset($group['perms']) && is_array($group['perms']) ? $group['perms'] : [];

				foreach ($perms as $permission)
				{
					if (is_array($permission))
					{
						$code = isset($permission['code']) ? (string)$permission['code'] : '';
						if ($code === '')
						{
							continue;
						}

						$items[$code] = array_merge([
							'code' => $code,
							'module' => $module,
							'group_code' => '',
							'name' => $code,
							'description' => '',
							'sort_order' => isset($group['priority']) ? (int)$group['priority'] : 100
						], $permission);

						continue;
					}

					$code = (string)$permission;
					if ($code === '')
					{
						continue;
					}

					$items[$code] = [
						'code' => $code,
						'module' => $module,
						'group_code' => '',
						'name' => $code,
						'description' => '',
						'sort_order' => isset($group['priority']) ? (int)$group['priority'] : 100
					];
				}
			}

			return $items;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function set (string $permissions)
		{
			Session::del('permissions');

			$permissions = explode('|', preg_replace('/\s+/', '', $permissions));

			Session::set('permissions', []);

			$_permissions = [];

			foreach ($permissions as $permission)
			{
				$_permissions[$permission] = 1;
			}

			Session::set('permissions', $_permissions);

			unset($_permissions);
		}


		/*
		|--------------------------------------------------------------------------------------
		| Установить права в сессию из массива кодов.
		|--------------------------------------------------------------------------------------
		*/
		public static function setArray (array $permissions)
		{
			Session::del('permissions');
			Session::set('permissions', []);

			$_permissions = [];

			foreach ($permissions as $permission)
			{
				$permission = trim((string)$permission);
				if ($permission === '')
				{
					continue;
				}

				$_permissions[$permission] = 1;
			}

			Session::set('permissions', $_permissions);
		}


		/*
		|--------------------------------------------------------------------------------------
		| Загрузить права роли из новых таблиц RBAC.
		|--------------------------------------------------------------------------------------
		*/
		public static function forRole ($roleCode)
		{
			$roleCode = trim((string)$roleCode);

			if ($roleCode === '')
			{
				return [];
			}

			if (isset(self::$_rolePermissions[$roleCode]))
			{
				return self::$_rolePermissions[$roleCode];
			}

			if (! self::tableExists(SystemTables::table('roles')) || ! self::tableExists(SystemTables::table('role_permissions')))
			{
				self::$_rolePermissions[$roleCode] = [];
				return [];
			}

			$rows = DB::query(
				"SELECT rp.permission_code
				 FROM " . SystemTables::table('roles') . " r
				 JOIN " . SystemTables::table('role_permissions') . " rp ON rp.role_id = r.id
				 WHERE r.code = %s
				 ORDER BY rp.permission_code ASC",
				$roleCode
			)->getAll();

			$permissions = [];
			foreach ($rows ?: [] as $row)
			{
				$row = (array)$row;
				if (!empty($row['permission_code']))
				{
					$permissions[] = (string)$row['permission_code'];
				}
			}

			self::$_rolePermissions[$roleCode] = $permissions;
			return $permissions;
		}


		/*
		|--------------------------------------------------------------------------------------
		| Установить права роли в текущую сессию.
		|--------------------------------------------------------------------------------------
		*/
		public static function setForRole ($roleCode)
		{
			$permissions = self::forRole($roleCode);

			if ((string)$roleCode === 'admin' && empty($permissions))
			{
				$permissions = ['all_permissions'];
			}

			self::setArray($permissions);
		}


		/*
		|--------------------------------------------------------------------------------------
		| Синхронизировать зарегистрированные модулями права с таблицей permissions.
		|--------------------------------------------------------------------------------------
		*/
		public static function syncRegistryToDb ()
		{
			if (! self::tableExists(SystemTables::table('permissions')))
			{
				return 0;
			}

			$existing = [];
			$rows = DB::query(
				"SELECT code, module, group_code, name, description, sort_order FROM " . SystemTables::table('permissions')
			)->getAll();
			foreach ($rows ?: [] as $row)
			{
				$row = (array)$row;
				$existing[(string)$row['code']] = $row;
			}

			$count = 0;
			foreach (self::all() as $permission)
			{
				$count++;
				$current = isset($existing[$permission['code']]) ? $existing[$permission['code']] : null;
				$expected = [
					'module' => (string)$permission['module'],
					'group_code' => isset($permission['group_code']) ? (string)$permission['group_code'] : '',
					'name' => isset($permission['name']) ? (string)$permission['name'] : (string)$permission['code'],
					'description' => isset($permission['description']) ? (string)$permission['description'] : '',
					'sort_order' => isset($permission['sort_order']) ? (int)$permission['sort_order'] : 100
				];
				if ($current
					&& (string)$current['module'] === $expected['module']
					&& (string)$current['group_code'] === $expected['group_code']
					&& (string)$current['name'] === $expected['name']
					&& (string)$current['description'] === $expected['description']
					&& (int)$current['sort_order'] === $expected['sort_order'])
				{
					continue;
				}

				DB::query(
					"INSERT INTO " . SystemTables::table('permissions') . "
						(code, module, group_code, name, description, sort_order)
					 VALUES (%s, %s, %s, %s, %s, %i)
					 ON DUPLICATE KEY UPDATE
						module = VALUES(module),
						group_code = VALUES(group_code),
						name = VALUES(name),
						description = VALUES(description),
						sort_order = VALUES(sort_order)",
					$permission['code'],
					$permission['module'],
					$expected['group_code'],
					$expected['name'],
					$expected['description'],
					$expected['sort_order']
				);
			}

			return $count;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function check ($perm)
		{
			$permissions		= Session::get('permissions');
			$all_permissions	= (isset($permissions['all_permissions']) && $permissions['all_permissions'] == 1);
			$permission			= (isset($permissions[$perm]) && $permissions[$perm] == 1);

			return $all_permissions || $permission;
		}


		protected static function tableExists ($table)
		{
			$rows = DB::query("SHOW TABLES LIKE %s", (string)$table)->getAll();
			return !empty($rows);
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function checkAcp ($perm)
		{
			if (! self::check($perm))
			{
				if (! defined('NOPERM'))
				{
					define('NOPERM', 1);
				}

				return false;
			}

			return true;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function perm ($perm)
		{
			$permissions		= Session::get('permissions');
			$permission			= (isset($permissions[$perm]) AND $permissions[$perm] == 1);
			$all_permissions	= (isset($permissions['all_permissions']) AND $permissions['all_permissions'] == 1);

			return $permission || $all_permissions;
		}


		protected function __clone ()
		{
			//
		}


		protected function __wakeup ()
		{
			//
		}
	}
