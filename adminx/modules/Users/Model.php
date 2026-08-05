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
	use App\Common\AuditLog;
	use App\Common\Auth;
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

		protected static function sessionTable()
		{
			return SystemTables::table('users_session');
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
			$sql  = 'SELECT id,name,email,login,phone,role,is_active,created_at,updated_at,last_login_at,'
				. 'password_changed_at,must_change_password FROM ' . self::table() . ' WHERE 1=1';
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
		public static function setPassword($id, $hash, $mustChange = false)
		{
			DB::Update(self::table(), [
				'password_hash'   => (string) $hash,
				'legacy_password' => null,
				'legacy_salt'     => null,
				'password_changed_at' => date('Y-m-d H:i:s'),
				'must_change_password' => $mustChange ? 1 : 0,
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
				'password_changed_at' => $now,
				'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
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
				'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
				'updated_at' => date('Y-m-d H:i:s'),
			];
			//-- Пароль меняем только если задан новый.
			if (!empty($data['password'])) {
				$fields['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
				$fields['password_changed_at'] = date('Y-m-d H:i:s');
			}

			DB::Update(self::table(), $fields, 'id = %i', (int) $id);
			if (!empty($data['password'])) {
				self::revokeSessions((int) $id, (int) Auth::id() === (int) $id ? Auth::currentBrowserTokenHash() : '');
			}

			IdentityLinker::ensurePublicForSystem((int) $id, !empty($data['password']) ? (string) $data['password'] : '');
		}

		public static function completePasswordChange($id, $password)
		{
			$hash = password_hash((string) $password, PASSWORD_BCRYPT, array('cost' => 12));
			self::setPassword((int) $id, $hash, false);
			self::revokeSessions((int) $id, Auth::currentBrowserTokenHash());
			IdentityLinker::ensurePublicForSystem((int) $id, (string) $password);
		}

		public static function recordSuccessfulLogin($id)
		{
			DB::Update(self::table(), array(
				'last_login_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			), 'id=%i', (int) $id);
		}

		public static function toggle($id)
		{
			DB::query(
				'UPDATE ' . self::table() . ' SET is_active = 1 - is_active, updated_at = %s WHERE id = %i',
				date('Y-m-d H:i:s'), (int) $id
			);
			$active = (int) DB::query('SELECT is_active FROM ' . self::table() . ' WHERE id = %i', (int) $id)->getValue();
			if (!$active) { self::revokeSessions((int) $id); }
			return $active;
		}

		public static function setActive($id, $active)
		{
			DB::Update(self::table(), array(
				'is_active' => $active ? 1 : 0,
				'updated_at' => date('Y-m-d H:i:s'),
			), 'id = %i', (int) $id);
			if (!$active) { self::revokeSessions((int) $id); }
		}

		public static function delete($id)
		{
			self::revokeSessions((int) $id);
			DB::Delete(self::table(), 'id = %i', (int) $id);
		}

		public static function security($userId)
		{
			$userId = (int) $userId;
			$currentHash = Auth::currentBrowserTokenHash();
			$ttl = defined('COOKIE_LIFETIME') ? max(3600, (int) COOKIE_LIFETIME) : 1209600;
			$rows = DB::query(
				'SELECT id,user_id,token_hash,agent,ip,created_at,last_active,expires_at FROM ' . self::sessionTable()
					. ' WHERE user_id=%i AND last_active>=%s AND expires_at>=%s ORDER BY last_active DESC,id DESC',
				$userId,
				date('Y-m-d H:i:s', time() - $ttl),
				date('Y-m-d H:i:s')
			)->getAll() ?: array();
			$sessions = array();
			foreach ($rows as $row) {
				$device = self::device((string) $row['agent']);
				$sessions[] = array(
					'id' => (int) $row['id'],
					'ip' => (string) $row['ip'],
					'created_at' => (string) $row['created_at'],
					'last_active' => (string) $row['last_active'],
					'expires_at' => (string) $row['expires_at'],
					'is_current' => $currentHash !== '' && hash_equals($currentHash, (string) $row['token_hash']),
					'browser' => $device['browser'],
					'platform' => $device['platform'],
					'device' => $device['device'],
				);
			}

			return array(
				'sessions' => $sessions,
				'failed' => self::failedLoginSummary($userId),
			);
		}

		public static function revokeSession($userId, $sessionId)
		{
			$row = DB::query(
				'SELECT id,token_hash FROM ' . self::sessionTable() . ' WHERE id=%i AND user_id=%i LIMIT 1',
				(int) $sessionId,
				(int) $userId
			)->getAssoc();
			if (!$row) { return array('removed' => false, 'current' => false); }
			$currentHash = Auth::currentBrowserTokenHash();
			$current = $currentHash !== '' && hash_equals($currentHash, (string) $row['token_hash']);
			DB::Delete(self::sessionTable(), 'id=%i AND user_id=%i', (int) $sessionId, (int) $userId);
			return array('removed' => true, 'current' => $current);
		}

		public static function revokeSessions($userId, $exceptTokenHash = '')
		{
			$exceptTokenHash = trim((string) $exceptTokenHash);
			if (preg_match('/^[a-f0-9]{64}$/', $exceptTokenHash)) {
				DB::Delete(self::sessionTable(), 'user_id=%i AND token_hash!=%s', (int) $userId, $exceptTokenHash);
			} else {
				DB::Delete(self::sessionTable(), 'user_id=%i', (int) $userId);
			}

			return max(0, (int) DB::affectedRows());
		}

		public static function failedLoginSummary($userId)
		{
			$since = date('Y-m-d H:i:s', time() - 86400);
			$count = (int) DB::query(
				'SELECT COUNT(*) FROM ' . AuditLog::table()
					. ' WHERE action=%s AND target_id=%i AND created_at>=%s',
				'auth.login_failed',
				(int) $userId,
				$since
			)->getValue();
			$last = DB::query(
				'SELECT created_at,ip FROM ' . AuditLog::table()
					. ' WHERE action=%s AND target_id=%i ORDER BY id DESC LIMIT 1',
				'auth.login_failed',
				(int) $userId
			)->getAssoc();

			return array(
				'count_24h' => $count,
				'last_at' => $last ? (string) $last['created_at'] : '',
				'last_ip' => $last ? (string) $last['ip'] : '',
			);
		}

		public static function failedLoginSpike($minutes = 10)
		{
			return (int) DB::query(
				'SELECT COUNT(*) FROM ' . AuditLog::table() . ' WHERE action=%s AND created_at>=%s',
				'auth.login_failed',
				date('Y-m-d H:i:s', time() - max(1, (int) $minutes) * 60)
			)->getValue();
		}

		protected static function device($agent)
		{
			$agent = (string) $agent;
			$browser = strpos($agent, 'Edg/') !== false ? 'Edge'
				: (strpos($agent, 'OPR/') !== false ? 'Opera'
				: (strpos($agent, 'Firefox/') !== false ? 'Firefox'
				: (strpos($agent, 'Chrome/') !== false ? 'Chrome'
				: (strpos($agent, 'Safari/') !== false ? 'Safari' : ''))));
			$platform = strpos($agent, 'Windows') !== false ? 'Windows'
				: (strpos($agent, 'Android') !== false ? 'Android'
				: (strpos($agent, 'iPhone') !== false || strpos($agent, 'iPad') !== false ? 'iOS'
				: (strpos($agent, 'Macintosh') !== false ? 'macOS'
				: (strpos($agent, 'Linux') !== false ? 'Linux' : ''))));
			$device = strpos($agent, 'Mobile') !== false || strpos($agent, 'Android') !== false ? 'mobile' : 'desktop';

			return array('browser' => $browser, 'platform' => $platform, 'device' => $device);
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
