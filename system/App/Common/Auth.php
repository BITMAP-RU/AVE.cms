<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Auth.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	use DB;
	use App\Helpers\Request;
	use App\Common\Auth\IdentityLinker;
	use App\Common\Auth\PublicUserProvider;

	class Auth
	{
		const DUMMY_PASSWORD_HASH = '$2y$12$CPSYMCBc.us9zmv1TxUrmuc6r8fEQzxQwT2f93rwzGGyZpW92RKBq';
		/**
		 * Кеш пользователей нового table-based API в рамках запроса.
		 *
		 * @var array
		 */
		protected static $currentUsers = [];
		protected static $systemRolePermissions = [];
		protected static $sessionExpiryColumns = [];

		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  Системная авторизация публичного сайта
		// ------------------------------------------------------------------ //

		public static function publicCheck()
		{
			return PublicUserProvider::check();
		}

		public static function publicBootstrap()
		{
			if (!PublicUserProvider::check()) {
				$systemUser = self::systemUser();
				if (!$systemUser) {
					$systemUser = self::user();
				}

				$publicUser = $systemUser ? IdentityLinker::publicForSystem($systemUser) : null;
				if ($systemUser && !$publicUser) {
					$publicId = IdentityLinker::ensurePublicForSystem((int) $systemUser['id']);
					if ($publicId > 0) {
						PublicUserProvider::syncById($publicId);
					}
				}

				if ($publicUser && (string) $publicUser['status'] === '1') {
					PublicUserProvider::syncById((int) $publicUser['Id']);
				}
			}

			return PublicUserProvider::bootstrap();
		}

		public static function publicUser()
		{
			return PublicUserProvider::user();
		}

		public static function publicAttempt($identifier, $password, $remember = false)
		{
			return PublicUserProvider::attempt($identifier, $password, $remember);
		}

		public static function publicLoginById($userId, $remember = false)
		{
			return PublicUserProvider::loginById($userId, $remember);
		}

		public static function publicLogout()
		{
			PublicUserProvider::logout();
		}

		public static function publicRegistrationEnabled()
		{
			return PublicUserProvider::registrationEnabled();
		}

		// ------------------------------------------------------------------ //
		//  Новый универсальный Auth API для схемы:
		//  {{prefix}}_users + {{prefix}}_users_session
		// ------------------------------------------------------------------ //

		/**
		 * Попытка входа по логину/email + пароль.
		 *
		 * Не зависит от Marketplace: таблицы, поля, cookie и session key задаются
		 * через options. По умолчанию рассчитано на актуальную схему:
		 * users.email/password_hash/role/is_active и users_session.token_hash.
		 *
		 * Возвращает массив пользователя при успехе, иначе:
		 *   1 — пустой логин/пароль
		 *   2 — неверная пара
		 *   3 — пользователь отключён
		 */
		public static function attempt($identifier, $password, array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$identifier = trim((string) $identifier);

			if ($identifier === '' || $password === '') {
				return 1;
			}

			$where = [];
			$args = [];
			foreach ($options['identifier_fields'] as $field) {
				$where[] = $field . ' = %s';
				$args[] = $identifier;
			}

			$sql = "SELECT * FROM " . $options['users_table'] . " WHERE (" . implode(' OR ', $where) . ") LIMIT 1";
			$rows = call_user_func_array([DB::class, 'query'], array_merge([$sql], $args))->getAll();
			$user = $rows ? (array) $rows[0] : null;

			$storedHash = $user && !empty($user[$options['password_field']])
				? (string) $user[$options['password_field']]
				: self::DUMMY_PASSWORD_HASH;
			$passwordValid = password_verify($password, $storedHash);
			if (!$user || empty($user[$options['password_field']]) || !$passwordValid) {
				return 2;
			}

			if ($options['active_field'] !== '' && (int) $user[$options['active_field']] !== (int) $options['active_value']) {
				return 3;
			}

			if (password_needs_rehash($user[$options['password_field']], PASSWORD_BCRYPT, ['cost' => $options['bcrypt_cost']])) {
				$newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $options['bcrypt_cost']]);
				DB::Update($options['users_table'], [$options['password_field'] => $newHash], "id = %i", $user['id']);
				$user[$options['password_field']] = $newHash;
			}

			self::login($user, $options);
			return $user;
		}

		/**
		 * Установить пользователя как авторизованного для нового Auth API.
		 */
		public static function login(array $user, array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$cacheKey = self::modernAuthCacheKey($options);

			if (!empty($options['regenerate_session'])) {
				Session::regenerateId(!empty($options['delete_old_session']));
			}

			Session::set($options['session_key'], (int) $user['id']);
			self::$currentUsers[$cacheKey] = $user;

			if (!empty($options['load_permissions']) && !empty($options['role_field']) && isset($user[$options['role_field']])) {
				Permission::setForRole($user[$options['role_field']]);
			}

			if (!empty($options['remember']) || !empty($options['issue_session_token'])) {
				self::modernIssueRememberToken((int) $user['id'], $options, !empty($options['remember']));
			}
		}

		/**
		 * Гарантирует общий browser-token для уже открытой системной сессии.
		 * Нужен, когда разные части приложения используют разные PHP session handlers.
		 */
		public static function ensureBrowserToken(array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$user = self::user($options);
			if (!$user) {
				return false;
			}

			$token = isset($_COOKIE[$options['cookie_name']]) ? (string) $_COOKIE[$options['cookie_name']] : '';
			if (preg_match('/^[a-f0-9]{64}$/', $token)) {
				$hash = hash('sha256', $token);
				if (self::modernTokenBelongsTo($hash, (int) $user['id'], $options)) {
					Session::set(self::modernTokenSessionKey($options), hash('sha256', $token));
					return true;
				}
			}

			self::modernIssueRememberToken((int) $user['id'], $options, false);
			return true;
		}

		/** Системная личность, доступная и в публичном runtime через общий token. */
		public static function systemUser()
		{
			$options = self::modernAuthOptions(array(
				'users_table' => SystemTables::table('users'),
				'session_table' => SystemTables::table('users_session'),
				'load_permissions' => false,
				'regenerate_session' => false,
				'regenerate_session_on_logout' => false,
			));
			$cacheKey = 'system-token|' . $options['cookie_name'];

			if (array_key_exists($cacheKey, self::$currentUsers)) {
				return self::$currentUsers[$cacheKey] ?: null;
			}

			$id = self::modernRestoreFromCookie($options, false);
			if ($id <= 0) {
				$linkedUser = self::linkedPublicSystemUser($options);
				if (!$linkedUser) {
					self::$currentUsers[$cacheKey] = false;
					return null;
				}

				self::modernIssueRememberToken((int) $linkedUser['id'], $options, false);
				self::$currentUsers[$cacheKey] = $linkedUser;
				return $linkedUser;
			}

			$rows = DB::query(
				"SELECT * FROM " . $options['users_table']
				. " WHERE id = %i AND " . $options['active_field'] . " = %i LIMIT 1",
				$id,
				(int) $options['active_value']
			)->getAll();
			self::$currentUsers[$cacheKey] = $rows ? (array) $rows[0] : false;

			return self::$currentUsers[$cacheKey] ?: null;
		}

		protected static function linkedPublicSystemUser(array $options)
		{
			$publicUser = self::publicUser();
			if (!$publicUser || empty($publicUser['email'])) {
				return null;
			}

			$linked = IdentityLinker::systemForPublic((int) $publicUser['id'], (string) $publicUser['email']);
			if ($linked && (int) $linked[$options['active_field']] === (int) $options['active_value']) {
				return $linked;
			}

			$config = PublicConfiguration::all();
			$groups = isset($config['system_identity_public_groups']) && is_array($config['system_identity_public_groups'])
				? array_map('intval', $config['system_identity_public_groups'])
				: array();
			if (!in_array((int) $publicUser['group'], $groups, true)) {
				return null;
			}

			$rows = DB::query(
				'SELECT * FROM ' . $options['users_table']
				. ' WHERE email = %s AND ' . $options['active_field'] . ' = %i LIMIT 1',
				(string) $publicUser['email'],
				(int) $options['active_value']
			)->getAll();

			return $rows ? (array) $rows[0] : null;
		}

		public static function systemUserCan($permission)
		{
			$user = self::systemUser();
			if (!$user || empty($user['role'])) {
				return false;
			}

			$role = (string) $user['role'];
			if ($role === 'admin') {
				return true;
			}

			return in_array((string) $permission, self::systemPermissionsForRole($role), true);
		}

		protected static function systemPermissionsForRole($role)
		{
			$role = trim((string) $role);
			if ($role === '') {
				return array();
			}

			if (isset(self::$systemRolePermissions[$role])) {
				return self::$systemRolePermissions[$role];
			}

			try {
				$rows = DB::query(
					'SELECT rp.permission_code FROM ' . SystemTables::table('roles') . ' r'
					. ' JOIN ' . SystemTables::table('role_permissions') . ' rp ON rp.role_id = r.id'
					. ' WHERE r.code = %s ORDER BY rp.permission_code ASC',
					$role
				)->getAll();
			} catch (\Throwable $e) {
				$rows = array();
			}

			self::$systemRolePermissions[$role] = array();
			foreach ($rows ?: array() as $row) {
				$row = (array) $row;
				if (!empty($row['permission_code'])) {
					self::$systemRolePermissions[$role][] = (string) $row['permission_code'];
				}
			}

			return self::$systemRolePermissions[$role];
		}

		/**
		 * Завершить вход для нового Auth API.
		 */
		public static function logout(array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$cacheKey = self::modernAuthCacheKey($options);

			if (!empty($_COOKIE[$options['cookie_name']])) {
				DB::Delete(
					$options['session_table'],
					"token_hash = %s",
					hash('sha256', (string) $_COOKIE[$options['cookie_name']])
				);
			}

			self::modernForgetCookie($options);
			Session::del($options['session_key'], self::modernTokenSessionKey($options));
			if (!empty($options['load_permissions'])) {
				Session::del('permissions');
			}

			self::$currentUsers[$cacheKey] = false;

			if (!empty($options['regenerate_session_on_logout'])) {
				Session::regenerateId(!empty($options['delete_old_session']));
			}
		}

		public static function check(array $options = [])
		{
			return self::user($options) !== null;
		}

		/**
		 * Текущий пользователь нового Auth API как массив или null.
		 */
		public static function user(array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$cacheKey = self::modernAuthCacheKey($options);

			if (array_key_exists($cacheKey, self::$currentUsers)) {
				return self::$currentUsers[$cacheKey] ?: null;
			}

			$id = (int) Session::get($options['session_key']);
			if ($id <= 0) {
				$id = self::modernRestoreFromCookie($options);
			}

			if ($id <= 0) {
				self::$currentUsers[$cacheKey] = false;
				return null;
			}

			// Browser-token is the revocable identity of this login. Without this
			// check deleting a row from users_session would leave the PHP session
			// authorised until its own expiry.
			$tokenHash = self::currentBrowserTokenHash($options);
			if ($tokenHash !== '' && !self::modernTokenBelongsTo($tokenHash, $id, $options)) {
				self::modernForgetCookie($options);
				Session::del($options['session_key'], self::modernTokenSessionKey($options));
				self::$currentUsers[$cacheKey] = false;
				return null;
			}

			$sql = "SELECT * FROM " . $options['users_table'] . " WHERE id = %i";
			$args = [$sql, $id];
			if ($options['active_field'] !== '') {
				$sql .= " AND " . $options['active_field'] . " = %i";
				$args = [$sql, $id, (int) $options['active_value']];
			}

			$sql .= " LIMIT 1";
			$args[0] = $sql;

			$rows = call_user_func_array([DB::class, 'query'], $args)->getAll();
			self::$currentUsers[$cacheKey] = $rows ? (array) $rows[0] : false;

			if (self::$currentUsers[$cacheKey] && !empty($options['load_permissions']) && !empty($options['role_field'])) {
				$user = self::$currentUsers[$cacheKey];
				if (isset($user[$options['role_field']])) {
					Permission::setForRole($user[$options['role_field']]);
				}
			}

			return self::$currentUsers[$cacheKey] ?: null;
		}

		public static function id(array $options = [])
		{
			$user = self::user($options);
			return $user ? (int) $user['id'] : 0;
		}

		/** SHA-256 browser-token of the current modern-auth session, if present. */
		public static function currentBrowserTokenHash(array $options = array())
		{
			$options = self::modernAuthOptions($options);
			$sessionKey = self::modernTokenSessionKey($options);
			$stored = (string) Session::get($sessionKey);
			if (preg_match('/^[a-f0-9]{64}$/', $stored)) {
				return $stored;
			}

			$token = isset($_COOKIE[$options['cookie_name']]) ? (string) $_COOKIE[$options['cookie_name']] : '';
			if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
				return '';
			}

			$hash = hash('sha256', $token);
			Session::set($sessionKey, $hash);
			return $hash;
		}

		public static function role(array $options = [])
		{
			$options = self::modernAuthOptions($options);
			$user = self::user($options);
			return ($user && isset($user[$options['role_field']])) ? (string) $user[$options['role_field']] : '';
		}

		public static function is($role, array $options = [])
		{
			return self::role($options) === (string) $role;
		}

		protected static function modernAuthOptions(array $options)
		{
			return array_merge([
				'users_table' => SystemTables::table('users'),
				'session_table' => SystemTables::table('users_session'),
				'session_key' => 'auth_user_id',
				'cookie_name' => 'auth_token',
				'cookie_path' => defined('ABS_PATH') && ABS_PATH !== '' ? ABS_PATH : '/',
				'cookie_domain' => '',
				'token_ttl' => defined('COOKIE_LIFETIME') ? COOKIE_LIFETIME : 1209600,
				'identifier_fields' => ['email'],
				'password_field' => 'password_hash',
				'active_field' => 'is_active',
				'active_value' => 1,
				'role_field' => 'role',
				'bcrypt_cost' => 12,
				'remember' => true,
				'issue_session_token' => false,
				'load_permissions' => true,
				'regenerate_session' => true,
				'regenerate_session_on_logout' => true,
				'delete_old_session' => true,
			], $options);
		}

		protected static function modernAuthCacheKey(array $options)
		{
			return $options['session_key'] . '|' . $options['cookie_name'];
		}

		protected static function modernTokenSessionKey(array $options)
		{
			return '_auth_browser_token_' . substr(sha1($options['session_key'] . '|' . $options['cookie_name']), 0, 16);
		}

		protected static function modernTokenBelongsTo($hash, $userId, array $options)
		{
			$expiryColumn = self::modernSessionHasExpiry($options);
			$row = DB::query(
				'SELECT user_id,last_active,created_at' . ($expiryColumn ? ',expires_at' : '')
					. ' FROM ' . $options['session_table'] . ' WHERE token_hash=%s LIMIT 1',
				(string) $hash
			)->getAssoc();
			if (!$row || (int) $row['user_id'] !== (int) $userId) {
				return false;
			}

			if (self::modernTokenExpired($row, $options)) {
				DB::Delete($options['session_table'], 'token_hash=%s', (string) $hash);
				return false;
			}

			return true;
		}

		protected static function modernIssueRememberToken($userId, array $options, $persistent = true)
		{
			if (headers_sent()) {
				return;
			}

			$token = bin2hex(random_bytes(32));
			$now = date('Y-m-d H:i:s');

			$tokenHash = hash('sha256', $token);
			$values = [
				'user_id' => (int) $userId,
				'token_hash' => $tokenHash,
				'agent' => substr(Request::userAgent(), 0, 255),
				'ip' => self::modernClientIp(),
				'created_at' => $now,
				'last_active' => $now,
			];
			if (self::modernSessionHasExpiry($options)) {
				$values['expires_at'] = date('Y-m-d H:i:s', time() + (int) $options['token_ttl']);
			}

			DB::Insert($options['session_table'], $values);
			Session::set(self::modernTokenSessionKey($options), $tokenHash);

			self::modernSendCookie($token, $persistent ? time() + (int) $options['token_ttl'] : 0, $options);
			$_COOKIE[$options['cookie_name']] = $token;

			if (random_int(1, 20) === 1) {
				DB::Delete(
					$options['session_table'],
					"last_active < %s",
					date('Y-m-d H:i:s', time() - (int) $options['token_ttl'])
				);
			}
		}

		protected static function modernRestoreFromCookie(array $options, $persistSession = true)
		{
			if (empty($_COOKIE[$options['cookie_name']])) {
				return 0;
			}

			$token = (string) $_COOKIE[$options['cookie_name']];
			if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
				return 0;
			}

			$hash = hash('sha256', $token);
			$expiryColumn = self::modernSessionHasExpiry($options);
			$tokenRow = DB::query(
				"SELECT user_id,last_active,created_at" . ($expiryColumn ? ',expires_at' : '')
					. " FROM " . $options['session_table'] . " WHERE token_hash = %s LIMIT 1",
				$hash
			)->getAssoc();
			$userId = $tokenRow ? (int) $tokenRow['user_id'] : 0;

			if ($userId <= 0 || self::modernTokenExpired($tokenRow ?: array(), $options)) {
				if ($userId > 0) {
					DB::Delete($options['session_table'], "token_hash = %s", $hash);
				}

				self::modernForgetCookie($options);
				return 0;
			}

			$sql = "SELECT id FROM " . $options['users_table'] . " WHERE id = %i";
			$args = [$sql, $userId];
			if ($options['active_field'] !== '') {
				$sql .= " AND " . $options['active_field'] . " = %i";
				$args = [$sql, $userId, (int) $options['active_value']];
			}

			$sql .= " LIMIT 1";
			$args[0] = $sql;
			$ok = (int) call_user_func_array([DB::class, 'query'], $args)->getOne();

			if ($ok <= 0) {
				DB::Delete($options['session_table'], "token_hash = %s", $hash);
				self::modernForgetCookie($options);
				return 0;
			}

			if ($persistSession) {
				$newToken = bin2hex(random_bytes(32));
				$newHash = hash('sha256', $newToken);
				DB::Update(
					$options['session_table'],
					array('token_hash' => $newHash, 'last_active' => date('Y-m-d H:i:s'), 'ip' => self::modernClientIp()),
					'token_hash=%s',
					$hash
				);
				if ((int) DB::affectedRows() !== 1) { self::modernForgetCookie($options); return 0; }
				$hash = $newHash;
				$expiresAt = !empty($tokenRow['expires_at']) ? strtotime((string) $tokenRow['expires_at']) : 0;
				self::modernSendCookie($newToken, $expiresAt > time() ? $expiresAt : 0, $options);
				$_COOKIE[$options['cookie_name']] = $newToken;
				Session::set($options['session_key'], $userId);
				Session::set(self::modernTokenSessionKey($options), $hash);
			}

			$touchKey = '_auth_token_touch_' . substr($hash, 0, 16);
			$lastTouch = (int) Session::get($touchKey);
			if ($lastTouch <= time() - 60) {
				DB::Update(
					$options['session_table'],
					['last_active' => date('Y-m-d H:i:s'), 'ip' => self::modernClientIp()],
					"token_hash = %s",
					$hash
				);
				Session::set($touchKey, time());
			}

			return $userId;
		}

		protected static function modernTokenExpired(array $row, array $options)
		{
			$ttl = max(1, (int) $options['token_ttl']);
			$lastActive = !empty($row['last_active']) ? strtotime((string) $row['last_active']) : 0;
			$absolute = !empty($row['expires_at'])
				? strtotime((string) $row['expires_at'])
				: (!empty($row['created_at']) ? strtotime((string) $row['created_at']) + $ttl : 0);
			return $lastActive <= 0 || $lastActive < time() - $ttl || $absolute <= 0 || $absolute < time();
		}

		protected static function modernSessionHasExpiry(array $options)
		{
			$table = (string) $options['session_table'];
			if (!array_key_exists($table, self::$sessionExpiryColumns)) {
				self::$sessionExpiryColumns[$table] = (bool) DB::query(
					'SHOW COLUMNS FROM ' . $table . ' LIKE %s',
					'expires_at'
				)->getAssoc();
			}

			return self::$sessionExpiryColumns[$table];
		}

		protected static function modernClientIp()
		{
			return substr((string) Request::ip(), 0, 45);
		}

		protected static function modernForgetCookie(array $options)
		{
			unset($_COOKIE[$options['cookie_name']]);
			if (!headers_sent()) {
				self::modernSendCookie('', 1, $options);
			}
		}

		protected static function modernSendCookie($value, $expires, array $options)
		{
			$secure = Request::isHttps();

			setcookie($options['cookie_name'], $value, [
				'expires' => $expires,
				'path' => $options['cookie_path'],
				'domain' => $options['cookie_domain'],
				'secure' => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
	}
