<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Users;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\Auth\IdentityLinker;
	use App\Common\SystemTables;

	/**
	 * Доступ к пользователям (таблица {prefix}_users, современная схема:
	 * id, name, email, password_hash, role, is_active, created_at, updated_at).
	 */
	class Model
	{
		/** Известные роли (пока без таблицы ролей — фиксированный список). */
		public static function roles()
		{
			return ['admin', 'manager', 'designer', 'user'];
		}

		protected static function table()
		{
			return SystemTables::table('users');
		}

		/** Пустую строку сохраняем как NULL. */
		protected static function nullable(array $data, $key)
		{
			$v = isset($data[$key]) ? trim((string) $data[$key]) : '';
			return $v === '' ? null : $v;
		}

		/** Список с фильтром по поиску (имя/email/логин) и роли. */
		public static function all($search = '', $role = '')
		{
			$sql  = 'SELECT id, name, email, login, phone, role, is_active, created_at, updated_at, last_login_at FROM ' . self::table() . ' WHERE 1=1';
			$args = [];

			$search = trim((string) $search);
			if ($search !== '') {
				// email обычно ascii, name/login — utf8mb4: без приведения к
				// charset соединения (utf8) кириллический запрос падает на
				// «illegal mix of collations». Ведущий % и так гасит индекс.
				$sql .= ' AND (CONVERT(name USING utf8) LIKE %ss OR CONVERT(email USING utf8) LIKE %ss OR CONVERT(login USING utf8) LIKE %ss)';
				$args[] = $search;
				$args[] = $search;
				$args[] = $search;
			}

			$role = trim((string) $role);
			if ($role !== '') {
				$sql .= ' AND role = %s';
				$args[] = $role;
			}

			$sql .= ' ORDER BY id ASC';

			return call_user_func_array([DB::class, 'query'], array_merge([$sql], $args))->getAll();
		}

		public static function find($id)
		{
			return DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getObject();
		}

		/** Занят ли email другим пользователем. */
		public static function emailExists($email, $exceptId = 0)
		{
			return (bool) DB::query(
				'SELECT id FROM ' . self::table() . ' WHERE email = %s AND id != %i LIMIT 1',
				(string) $email, (int) $exceptId
			)->getValue();
		}

		/** Занят ли логин другим пользователем (пустой логин не проверяем). */
		public static function loginExists($login, $exceptId = 0)
		{
			$login = trim((string) $login);
			if ($login === '') {
				return false;
			}

			return (bool) DB::query(
				'SELECT id FROM ' . self::table() . ' WHERE login = %s AND id != %i LIMIT 1',
				$login, (int) $exceptId
			)->getValue();
		}

		/** Найти пользователя по email или логину (для входа). */
		public static function findByIdentifier($identifier)
		{
			return DB::query(
				'SELECT * FROM ' . self::table() . ' WHERE email = %s OR login = %s LIMIT 1',
				(string) $identifier, (string) $identifier
			)->getObject();
		}

		/** Проставить bcrypt-хеш и очистить legacy-пароль (после первого входа). */
		public static function setPassword($id, $hash)
		{
			DB::Update(self::table(), [
				'password_hash'   => (string) $hash,
				'legacy_password' => null,
				'legacy_salt'     => null,
				'updated_at'      => date('Y-m-d H:i:s'),
			], 'id = %i', (int) $id);
		}

		public static function create(array $data)
		{
			$now = date('Y-m-d H:i:s');
			DB::Insert(self::table(), [
				'name'          => (string) $data['name'],
				'email'         => (string) $data['email'],
				'login'         => self::nullable($data, 'login'),
				'phone'         => self::nullable($data, 'phone'),
				'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
				'role'          => (string) $data['role'],
				'is_active'     => !empty($data['is_active']) ? 1 : 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			]);
			$id = (int) DB::insertId();
			IdentityLinker::ensurePublicForSystem($id, (string) $data['password']);
			return $id;
		}

		public static function update($id, array $data)
		{
			$fields = [
				'name'       => (string) $data['name'],
				'email'      => (string) $data['email'],
				'login'      => self::nullable($data, 'login'),
				'phone'      => self::nullable($data, 'phone'),
				'role'       => (string) $data['role'],
				'is_active'  => !empty($data['is_active']) ? 1 : 0,
				'updated_at' => date('Y-m-d H:i:s'),
			];
			//-- Пароль меняем только если задан новый.
			if (!empty($data['password'])) {
				$fields['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
			}

			DB::Update(self::table(), $fields, 'id = %i', (int) $id);
			IdentityLinker::ensurePublicForSystem((int) $id, !empty($data['password']) ? (string) $data['password'] : '');
		}

		public static function toggle($id)
		{
			DB::query(
				'UPDATE ' . self::table() . ' SET is_active = 1 - is_active, updated_at = %s WHERE id = %i',
				date('Y-m-d H:i:s'), (int) $id
			);
			return (int) DB::query('SELECT is_active FROM ' . self::table() . ' WHERE id = %i', (int) $id)->getValue();
		}

		public static function setActive($id, $active)
		{
			DB::Update(self::table(), array(
				'is_active' => $active ? 1 : 0,
				'updated_at' => date('Y-m-d H:i:s'),
			), 'id = %i', (int) $id);
		}

		public static function delete($id)
		{
			DB::Delete(self::table(), 'id = %i', (int) $id);
		}

		public static function count()
		{
			return (int) DB::query('SELECT COUNT(*) FROM ' . self::table())->getValue();
		}

		/** Сводка для карточек: всего / активных / отключённых / ролей. */
		public static function stats()
		{
			$total  = self::count();
			$active = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_active = 1')->getValue();
			$roles  = (int) DB::query('SELECT COUNT(DISTINCT role) FROM ' . self::table() . ' WHERE role != ""')->getValue();

			return [
				'total'    => $total,
				'active'   => $active,
				'inactive' => $total - $active,
				'roles'    => $roles,
			];
		}
	}
