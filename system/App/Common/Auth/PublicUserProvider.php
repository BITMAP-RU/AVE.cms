<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Auth/PublicUserProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Registry;
	use App\Common\Session;
	use App\Common\PublicAuthSettings;
	use App\Common\DatabaseSchema;
	use App\Helpers\Cookie;
	use App\Helpers\Phone;
	use App\Helpers\Request;
	use DB;
	use App\Content\PublicUserTables;

	/**
	 * Provider публичных пользователей для текущей схемы AVE.cms.
	 * Изолирует legacy-названия колонок от системного Auth facade.
	 */
	class PublicUserProvider
	{
		protected static $permissionKeys;
		protected static $phoneIdentityReady;

		public static function bootstrap()
		{
			if (!self::check()) {
				self::restoreRememberedUser();
			}

			if (!self::check()) {
				Session::set('user_group', 2);
				Session::set('user_name', PublicUserNames::format());
			}

			if (!defined('UID')) { define('UID', self::check() ? (int) Session::get('user_id') : 0); }
			if (!defined('UGROUP')) { define('UGROUP', Session::check('user_group') ? (int) Session::get('user_group') : 2); }
			if (!defined('UNAME')) { define('UNAME', Session::check('user_name') ? (string) Session::get('user_name') : 'Anonymous'); }
			return self::check();
		}

		public static function check()
		{
			return !empty($_SESSION['user_id'])
				&& (!empty($_SESSION['user_pass']) || !empty($_SESSION['user_system_identity']));
		}

		public static function user()
		{
			if (!self::check()) {
				return null;
			}

			return array(
				'id' => (int) $_SESSION['user_id'],
				'name' => isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : '',
				'email' => isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : '',
				'group' => isset($_SESSION['user_group']) ? (int) $_SESSION['user_group'] : 2,
			);
		}

		public static function attempt($identifier, $password, $remember = false)
		{
			$identifier = trim((string) $identifier);
			if ($identifier === '' || (string) $password === '') {
				return false;
			}

			$user = self::findUser($identifier);
			if (!$user || (string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				return false;
			}

			if (!self::verifyPassword((string) $password, (string) $user['password'], (string) $user['salt'])) {
				return false;
			}

			if (self::passwordNeedsUpgrade((string) $user['password'])) {
				$user['password'] = self::upgradePassword((int) $user['id'], (string) $password);
				$user['salt'] = '';
			}

			return self::loginUser($user, $remember);
		}

		/** Start the same public session after a trusted external identity check. */
		public static function loginById($userId, $remember = false)
		{
			$user = self::findUserById((int) $userId);
			if (!$user || (string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				return false;
			}

			return self::loginUser($user, $remember);
		}

		/**
		 * Mirrors an already authenticated system identity into the public session.
		 * The system login has already regenerated the session, so repeating it here
		 * would detach session-bound data such as the current basket.
		 */
		public static function syncById($userId)
		{
			$user = self::findUserById((int) $userId);
			if (!$user || (string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				return false;
			}

			return self::loginUser($user, false, false, true);
		}

		public static function logout()
		{
			self::forgetRememberCookie();
			Session::regenerateId(true);
			self::clearIdentity();
			Session::set('user_group', 2);
			Session::set('user_name', PublicUserNames::format());
		}

		public static function registrationEnabled()
		{
			$settings = PublicAuthSettings::all();
			return !empty($settings['registration_enabled']);
		}

		protected static function findUser($identifier)
		{
			$phone = Phone::normalize($identifier);
			if (self::phoneIdentityReady()) {
				$row = DB::query(
					'SELECT usr.Id AS id, usr.user_group, usr.user_name, usr.firstname,'
					. ' usr.lastname, usr.email, usr.country, usr.password, usr.salt,'
					. ' usr.status, usr.deleted, grp.user_group_permission'
					. ' FROM `' . PublicUserTables::table('users') . '` usr'
					. ' LEFT JOIN `' . PublicUserTables::table('user_groups') . '` grp ON grp.user_group = usr.user_group'
					. ' WHERE usr.deleted != %s'
					. ' AND (usr.email = %s1 OR usr.user_name = %s1 OR usr.phone_normalized = %s2) LIMIT 1',
					'1',
					(string) $identifier,
					$phone
				)->getAssoc();
				return $row ? (array) $row : null;
			}

			$row = DB::query(
				'SELECT usr.Id AS id, usr.user_group, usr.user_name, usr.firstname,'
				. ' usr.lastname, usr.email, usr.country, usr.password, usr.salt,'
				. ' usr.status, usr.deleted, grp.user_group_permission'
				. ' FROM `' . PublicUserTables::table('users') . '` usr'
				. ' LEFT JOIN `' . PublicUserTables::table('user_groups') . '` grp ON grp.user_group = usr.user_group'
				. ' WHERE usr.deleted != %s AND (usr.email = %s1 OR usr.user_name = %s1) LIMIT 1',
				'1',
				(string) $identifier
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		protected static function phoneIdentityReady()
		{
			if (self::$phoneIdentityReady === null) {
				self::$phoneIdentityReady = DatabaseSchema::columnExists(
					PublicUserTables::table('users'),
					'phone_normalized'
				);
			}

			return self::$phoneIdentityReady;
		}

		protected static function findUserById($userId)
		{
			$row = DB::query(
				'SELECT usr.Id AS id, usr.user_group, usr.user_name, usr.firstname,'
				. ' usr.lastname, usr.email, usr.country, usr.password, usr.salt,'
				. ' usr.status, usr.deleted, grp.user_group_permission'
				. ' FROM `' . PublicUserTables::table('users') . '` usr'
				. ' LEFT JOIN `' . PublicUserTables::table('user_groups') . '` grp ON grp.user_group = usr.user_group'
				. ' WHERE usr.Id = %i LIMIT 1',
				(int) $userId
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		protected static function loginUser(array $user, $remember, $regenerate = true, $systemIdentity = false)
		{
				if ($regenerate) {
					Session::regenerateId(true);
				}

				self::clearIdentity();
			$name = PublicUserNames::format($user['user_name'], $user['firstname'], $user['lastname']);
			$requestIp = self::requestIp();
			Session::set('user_id', (int) $user['id']);
			Session::set('user_name', $name);
			Session::set('user_firstname', (string) $user['firstname']);
			Session::set('user_lastname', (string) $user['lastname']);
			Session::set('user_pass', (string) $user['password']);
			if ($systemIdentity) {
				Session::set('user_system_identity', 1);
			}

			Session::set('user_group', (int) $user['user_group']);
			Session::set('user_email', (string) $user['email']);
			Session::set('user_country', strtoupper((string) $user['country']));
			Session::set('user_ip', $requestIp);
			foreach (self::permissions((string) $user['user_group_permission']) as $permission) {
				Session::set($permission, 1);
			}

			DB::query(
				'UPDATE `' . PublicUserTables::table('users') . '` SET last_visit = %i, user_ip = %i WHERE Id = %i',
				time(), self::ipLong($requestIp), (int) $user['id']
			);
			self::forgetRememberCookie();
			if ($remember) { self::remember((int) $user['id'], $requestIp); }
			unset($_SESSION['basket-favorites-hydrated']);
			return true;
		}

		protected static function verifyPassword($plain, $hash, $salt)
		{
			if (strlen($hash) === 32) {
				return hash_equals($hash, md5(md5($plain . $salt)));
			}

			$info = password_get_info($hash);
			return !empty($info['algo']) && password_verify($plain, $hash);
		}

		protected static function passwordNeedsUpgrade($hash)
		{
			if (strlen((string) $hash) === 32) {
				return true;
			}

			return password_needs_rehash((string) $hash, PASSWORD_BCRYPT, array('cost' => 12));
		}

		protected static function upgradePassword($userId, $password)
		{
			$hash = password_hash((string) $password, PASSWORD_BCRYPT, array('cost' => 12));
			DB::Update(PublicUserTables::table('users'), array(
				'password' => $hash,
				'salt' => '',
				'new_pass' => '',
				'new_salt' => '',
			), 'Id=%i', (int) $userId);
			return $hash;
		}

		protected static function remember($userId, $ip)
		{
			if (!self::rememberSchemaReady()) {
				return;
			}

			$token = bin2hex(random_bytes(32));
			$agent = mb_substr(Request::userAgent(), 0, 255);
			$now = time();
			$ttl = self::rememberTtl();
			DB::query(
				'DELETE FROM `' . PublicUserTables::table('users_session') . '` WHERE user_id = %i AND agent = %s',
				(int) $userId,
				$agent
			);
			DB::Insert(PublicUserTables::table('users_session'), array(
				'user_id' => (int) $userId,
				'hash' => self::tokenHash($token),
				'token_version' => 2,
				'ip' => self::ipLong($ip),
				'agent' => $agent,
				'last_active' => $now,
				'created_at' => $now,
				'expires_at' => $now + $ttl,
			));
			Cookie::set(
				'auth',
				$token,
				$ttl,
				self::cookieDomain(),
				self::cookiePath(),
				self::isHttps(),
				true
			);
			$_COOKIE['auth'] = $token;
			self::pruneRememberTokens($now);
		}

		protected static function restoreRememberedUser()
		{
			$token = isset($_COOKIE['auth']) ? trim((string) $_COOKIE['auth']) : '';
			if (!preg_match('/^[a-f0-9]{64}$/', $token) || !self::rememberSchemaReady()) {
				self::forgetRememberCookie();
				return false;
			}

			$now = time();
			$tokenHash = self::tokenHash($token);
			$row = DB::query(
				'SELECT usr.Id AS id, usr.user_group, usr.user_name, usr.firstname, usr.lastname, usr.email,'
				. ' usr.country, usr.password, usr.status, usr.deleted, grp.user_group_permission,'
				. ' ses.id AS session_id,ses.expires_at'
				. ' FROM `' . PublicUserTables::table('users_session') . '` ses'
				. ' INNER JOIN `' . PublicUserTables::table('users') . '` usr ON usr.Id = ses.user_id'
				. ' LEFT JOIN `' . PublicUserTables::table('user_groups') . '` grp ON grp.user_group = usr.user_group'
				. ' WHERE ses.hash = %s AND ses.token_version=2 AND ses.agent = %s'
				. ' AND ses.expires_at >= %i AND ses.last_active >= %i'
				. ' AND usr.status = %s AND usr.deleted != %s LIMIT 1',
				$tokenHash,
				mb_substr(Request::userAgent(), 0, 255),
				$now,
				$now - self::rememberTtl(),
				'1',
				'1'
			)->getAssoc();
			if (!$row) {
				self::forgetRememberCookie();
				return false;
			}

			$row = (array) $row;
			$newToken = bin2hex(random_bytes(32));
			DB::Update(PublicUserTables::table('users_session'), array(
				'hash' => self::tokenHash($newToken),
				'last_active' => $now,
				'ip' => self::ipLong(Request::ip()),
			), 'id=%i AND hash=%s', (int) $row['session_id'], $tokenHash);
			if (DB::affectedRows() !== 1) {
				return false;
			}

			Session::regenerateId(true);
			self::clearIdentity();
			$name = PublicUserNames::format($row['user_name'], $row['firstname'], $row['lastname']);
			Session::set('user_id', (int) $row['id']);
			Session::set('user_name', $name);
			Session::set('user_firstname', (string) $row['firstname']);
			Session::set('user_lastname', (string) $row['lastname']);
			Session::set('user_pass', (string) $row['password']);
			Session::set('user_group', (int) $row['user_group']);
			Session::set('user_email', (string) $row['email']);
			Session::set('user_country', strtoupper((string) $row['country']));
			Session::set('user_ip', Request::ip());
			foreach (self::permissions((string) $row['user_group_permission']) as $permission) {
				Session::set($permission, 1);
			}

			$remaining = max(1, (int) $row['expires_at'] - $now);
			Cookie::set('auth', $newToken, $remaining, self::cookieDomain(), self::cookiePath(), self::isHttps(), true);
			$_COOKIE['auth'] = $newToken;
			DB::query('UPDATE `' . PublicUserTables::table('users') . '` SET last_visit = %i WHERE Id = %i', $now, (int) $row['id']);
			self::pruneRememberTokens($now);
			return true;
		}

		protected static function forgetRememberCookie()
		{
			$token = isset($_COOKIE['auth']) ? (string) $_COOKIE['auth'] : '';
			if (preg_match('/^[a-f0-9]{64}$/', $token)) {
				$stored = self::rememberSchemaReady() ? self::tokenHash($token) : $token;
				DB::query('DELETE FROM `' . PublicUserTables::table('users_session') . '` WHERE hash = %s', $stored);
			}

			Cookie::delete('auth', self::cookiePath(), self::cookieDomain());
		}

		protected static function rememberSchemaReady()
		{
			static $ready;
			if ($ready === null) {
				$columns = DB::columnList(PublicUserTables::table('users_session'));
				$ready = !array_diff(array('token_version', 'created_at', 'expires_at'), $columns);
			}

			return $ready;
		}

		protected static function rememberTtl()
		{
			return defined('COOKIE_LIFETIME') ? max(3600, (int) COOKIE_LIFETIME) : 1209600;
		}

		protected static function tokenHash($token)
		{
			return hash('sha256', (string) $token);
		}

		protected static function pruneRememberTokens($now)
		{
			if (random_int(1, 20) === 1) {
				DB::Delete(PublicUserTables::table('users_session'), 'expires_at < %i OR last_active < %i', (int) $now, (int) $now - self::rememberTtl());
			}
		}

		/** Resolve the activity column during the one-time schema rename. */
		protected static function sessionActivityColumn()
		{
			static $column;
			if ($column === null) {
				$exists = DB::query(
					'SHOW COLUMNS FROM `' . PublicUserTables::table('users_session') . '` LIKE %s',
					'last_active'
				)->getAssoc();
				$column = $exists ? 'last_active' : 'last_activ';
			}

			return $column;
		}

		protected static function clearIdentity()
		{
			Session::del(array_merge(array(
				'user_id', 'user_name', 'user_firstname', 'user_lastname', 'user_pass',
				'user_system_identity', 'user_group', 'user_email', 'user_country', 'user_ip',
			), self::permissionKeys()));
		}

		protected static function permissions($value)
		{
			$out = array();
			foreach (explode('|', preg_replace('/\s+/', '', (string) $value)) as $permission) {
				if ($permission !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $permission)) {
					$out[] = $permission;
				}
			}

			return array_values(array_unique($out));
		}

		protected static function permissionKeys()
		{
			if (self::$permissionKeys !== null) {
				return self::$permissionKeys;
			}

			self::$permissionKeys = array();
			$rows = DB::query('SELECT user_group_permission FROM `' . PublicUserTables::table('user_groups') . '`')->getAll();
			foreach ($rows ?: array() as $row) {
				self::$permissionKeys = array_merge(
					self::$permissionKeys,
					self::permissions((string) ((array) $row)['user_group_permission'])
				);
			}

			self::$permissionKeys = array_values(array_unique(self::$permissionKeys));
			return self::$permissionKeys;
		}

		protected static function requestIp()
		{
			return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : Request::ip();
		}

		protected static function ipLong($ip)
		{
			$long = ip2long((string) $ip);
			return $long === false ? 0 : sprintf('%u', $long);
		}

		protected static function cookieDomain()
		{
			$domain = ltrim((string) Registry::get('cookie_domain', null, ''), '.');
			if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP) || strpos($domain, '.') === false) {
				return '';
			}

			return '.' . $domain;
		}

		protected static function cookiePath()
		{
			return defined('ABS_PATH') && ABS_PATH !== '' ? (string) ABS_PATH : '/';
		}

		protected static function isHttps()
		{
			return Request::isHttps();
		}
	}
