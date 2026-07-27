<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Auth/IdentityLinker.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	use App\Content\PublicUserTables;

	/** Keeps one person linked across the public profile and Adminx RBAC records. */
	class IdentityLinker
	{
		public static function publicForSystem(array $systemUser)
		{
			$publicId = isset($systemUser['legacy_id']) ? (int) $systemUser['legacy_id'] : 0;
			$row = $publicId > 0 ? self::publicById($publicId) : null;
			if (!$row && !empty($systemUser['email'])) {
				$row = self::publicByEmail($systemUser['email']);
			}

			if ($row && !empty($systemUser['id']) && $publicId !== (int) $row['Id']) {
				DB::Update(SystemTables::table('users'), array('legacy_id' => (int) $row['Id']), 'id = %i', (int) $systemUser['id']);
			}

			return $row;
		}

		public static function systemForPublic($publicId, $email = '')
		{
			$row = DB::query(
				'SELECT * FROM ' . SystemTables::table('users') . ' WHERE legacy_id = %i LIMIT 1',
				(int) $publicId
			)->getAssoc();
			if (!$row && trim((string) $email) !== '') {
				$row = DB::query(
					'SELECT * FROM ' . SystemTables::table('users') . ' WHERE LOWER(email) = LOWER(%s) LIMIT 1',
					trim((string) $email)
				)->getAssoc();
				if ($row) {
					DB::Update(SystemTables::table('users'), array('legacy_id' => (int) $publicId), 'id = %i', (int) $row['id']);
					$row['legacy_id'] = (int) $publicId;
				}
			}

			return $row ? (array) $row : null;
		}

		public static function ensurePublicForSystem($systemId, $plainPassword = '')
		{
			$system = DB::query('SELECT * FROM ' . SystemTables::table('users') . ' WHERE id = %i LIMIT 1', (int) $systemId)->getAssoc();
			if (!$system) {
				throw new \InvalidArgumentException('Системный пользователь не найден');
			}

			$public = self::publicForSystem((array) $system);
			if ($public) {
				if ((string) $plainPassword !== '') {
					DB::Update(PublicUserTables::table('users'), array(
						'password' => password_hash((string) $plainPassword, PASSWORD_BCRYPT, array('cost' => 12)),
						'salt' => '',
					), 'Id = %i', (int) $public['Id']);
				}

				return (int) $public['Id'];
			}

			$email = mb_strtolower(trim((string) $system['email']));
			if (!preg_match('/^[^@\s]+@[^@\s]+$/', $email)) {
				return 0;
			}

			$passwordHash = (string) $system['password_hash'];
			if ((string) $plainPassword !== '') {
				$passwordHash = password_hash((string) $plainPassword, PASSWORD_BCRYPT, array('cost' => 12));
			}

			if (empty(password_get_info($passwordHash)['algo'])) {
				$passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, array('cost' => 12));
			}

			$name = trim((string) (isset($system['name']) ? $system['name'] : ''));
			$parts = preg_split('/\s+/u', $name, 2);
			$userName = self::uniquePublicName(!empty($system['login']) ? $system['login'] : $email);
			DB::Insert(PublicUserTables::table('users'), array(
				'password' => $passwordHash,
				'email' => $email,
				'email_verified_at' => time(),
				'street' => '',
				'street_nr' => '',
				'zipcode' => '',
				'city' => '',
				'phone' => '',
				'phone_verified_at' => 0,
				'telefax' => '',
				'description' => '',
				'firstname' => isset($parts[0]) ? mb_substr($parts[0], 0, 50) : '',
				'lastname' => isset($parts[1]) ? mb_substr($parts[1], 0, 50) : '',
				'user_name' => $userName,
				'user_group' => self::publicGroupForRole((string) $system['role']),
				'user_group_extra' => '',
				'reg_time' => time(),
				'status' => !empty($system['is_active']) ? '1' : '0',
				'last_visit' => 0,
				'country' => 'RU',
				'birthday' => '',
				'deleted' => '0',
				'del_time' => 0,
				'emc' => '',
				'reg_ip' => '0',
				'new_pass' => '',
				'company' => '',
				'taxpay' => '0',
				'salt' => '',
				'new_salt' => '',
				'user_ip' => 0,
			));
			$publicId = (int) DB::insertId();
			DB::Update(SystemTables::table('users'), array('legacy_id' => $publicId), 'id = %i', (int) $systemId);

			return $publicId;
		}

		public static function setAdminAccess($publicId, $enabled, $role, $currentSystemId = 0)
		{
			$public = self::publicById($publicId);
			if (!$public) {
				throw new \InvalidArgumentException('Публичный пользователь не найден');
			}

			$system = self::systemForPublic($publicId, $public['email']);
			if (!$enabled) {
				if ($system && (int) $system['id'] === (int) $currentSystemId) {
					throw new \InvalidArgumentException('Нельзя отключить собственный доступ в Adminx');
				}

				if ($system) {
					DB::Update(SystemTables::table('users'), array(
						'is_active' => 0,
						'updated_at' => date('Y-m-d H:i:s'),
					), 'id = %i', (int) $system['id']);
				}

				return null;
			}

			$name = trim(implode(' ', array_filter(array($public['firstname'], $public['lastname']))));
			if ($name === '') {
				$name = (string) $public['user_name'];
			}

			$password = (string) $public['password'];
			$passwordInfo = password_get_info($password);
			$data = array(
				'name' => $name,
				'email' => (string) $public['email'],
				'login' => self::availableSystemLogin((string) $public['user_name'], $system ? (int) $system['id'] : 0),
				'phone' => (string) $public['phone'],
				'role' => (string) $role,
				'is_active' => 1,
				'legacy_id' => (int) $publicId,
				'updated_at' => date('Y-m-d H:i:s'),
			);
			if (!empty($passwordInfo['algo'])) {
				$data['password_hash'] = $password;
				$data['legacy_password'] = null;
				$data['legacy_salt'] = null;
			} elseif (strlen($password) === 32) {
				$data['password_hash'] = '';
				$data['legacy_password'] = $password;
				$data['legacy_salt'] = (string) $public['salt'];
			}

			if ($system) {
				DB::Update(SystemTables::table('users'), $data, 'id = %i', (int) $system['id']);
				return (int) $system['id'];
			}

			$data['created_at'] = date('Y-m-d H:i:s');
			if (!isset($data['password_hash'])) {
				$data['password_hash'] = '';
			}

			DB::Insert(SystemTables::table('users'), $data);

			return (int) DB::insertId();
		}

		protected static function publicById($id)
		{
			$row = DB::query(
				'SELECT * FROM ' . PublicUserTables::table('users') . ' WHERE Id = %i AND deleted != %s LIMIT 1',
				(int) $id,
				'1'
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		protected static function publicGroupForRole($role)
		{
			$groups = array('admin' => 1, 'guest' => 2, 'moderator' => 3, 'user' => 4);
			return isset($groups[$role]) ? $groups[$role] : 4;
		}

		protected static function publicByEmail($email)
		{
			$row = DB::query(
				'SELECT * FROM ' . PublicUserTables::table('users') . ' WHERE LOWER(email) = LOWER(%s) AND deleted != %s LIMIT 1',
				trim((string) $email),
				'1'
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		protected static function uniquePublicName($candidate)
		{
			$base = mb_substr(trim((string) $candidate), 0, 50);
			$base = $base !== '' ? $base : 'user';
			$name = $base;
			$counter = 1;
			while (DB::query('SELECT Id FROM ' . PublicUserTables::table('users') . ' WHERE user_name = %s LIMIT 1', $name)->getValue()) {
				$suffix = '-' . $counter++;
				$name = mb_substr($base, 0, 50 - mb_strlen($suffix)) . $suffix;
			}

			return $name;
		}

		protected static function availableSystemLogin($candidate, $exceptId)
		{
			$candidate = mb_substr(trim((string) $candidate), 0, 150);
			if ($candidate === '') {
				return null;
			}

			$owner = (int) DB::query(
				'SELECT id FROM ' . SystemTables::table('users') . ' WHERE login = %s AND id != %i LIMIT 1',
				$candidate,
				(int) $exceptId
			)->getValue();
			return $owner > 0 ? null : $candidate;
		}
	}
