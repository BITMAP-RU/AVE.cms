<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/UserRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\DatabaseSchema;
	use App\Content\PublicUserTables;
	use App\Helpers\Phone;

	class UserRepository
	{
		public function findByEmail($email)
		{
			if (!filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL)) {
				return null;
			}

			$row = DB::query('SELECT * FROM `' . PublicUserTables::table('users') . '` WHERE LOWER(email)=LOWER(%s) AND deleted!=%s LIMIT 1', trim((string) $email), '1')->getAssoc();
			return $row ? (array) $row : null;
		}

		public function findByPhone($phone)
		{
			$phone = Phone::normalize($phone);
			if ($phone === '') {
				return null;
			}

			if (!DatabaseSchema::columnExists(PublicUserTables::table('users'), 'phone_normalized')) {
				throw new \RuntimeException('Схема телефонной авторизации не обновлена. Примените миграции ядра.');
			}

			$row = DB::query(
				'SELECT * FROM `' . PublicUserTables::table('users') . '`'
					. ' WHERE phone_normalized=%s AND deleted!=%s LIMIT 1',
				$phone,
				'1'
			)->getAssoc();
			if ($row) {
				return (array) $row;
			}

			return null;
		}

		public function find($id)
		{
			$row = DB::query('SELECT * FROM `' . PublicUserTables::table('users') . '` WHERE Id=%i AND deleted!=%s LIMIT 1', (int) $id, '1')->getAssoc();
			return $row ? (array) $row : null;
		}

		public function create(array $data, $group)
		{
			$email = mb_strtolower(trim((string) $data['email']));
			DB::Insert(PublicUserTables::table('users'), array_merge($this->recordDefaults(), array(
				'email' => $email,
				'password' => password_hash((string) $data['password'], PASSWORD_BCRYPT, array('cost' => 12)),
				'firstname' => trim((string) $data['firstname']),
				'lastname' => trim((string) $data['lastname']),
				'phone' => trim((string) (isset($data['phone']) ? $data['phone'] : '')),
				'company' => trim((string) (isset($data['company']) ? $data['company'] : '')),
				'user_name' => $this->userName($email),
				'user_group' => (int) $group,
				'reg_time' => time(),
				'status' => '0',
				'reg_ip' => $this->ipLong(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
				'email_verified_at' => 0,
			)));
			return $this->find((int) DB::insertId());
		}

		public function createSocial(array $profile, $group)
		{
			$email = mb_strtolower(trim((string) (isset($profile['email']) ? $profile['email'] : '')));
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new \InvalidArgumentException('Провайдер не передал подтверждённый email.');
			}

			$password = bin2hex(random_bytes(32));
			$userName = mb_strlen($email) <= 50
				? $email
				: mb_substr($email, 0, 37) . '_' . substr(sha1($email), 0, 12);
			DB::Insert(PublicUserTables::table('users'), array_merge($this->recordDefaults(), array(
				'email' => $email,
				'password' => password_hash($password, PASSWORD_BCRYPT, array('cost' => 12)),
				'firstname' => mb_substr(trim((string) (isset($profile['firstname']) ? $profile['firstname'] : '')), 0, 50),
				'lastname' => mb_substr(trim((string) (isset($profile['lastname']) ? $profile['lastname'] : '')), 0, 50),
				'phone' => '', 'company' => '', 'user_name' => $userName,
				'user_group' => (int) $group, 'reg_time' => time(), 'status' => '1',
				'reg_ip' => $this->ipLong(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
				'email_verified_at' => time(),
			)));
			return $this->find((int) DB::insertId());
		}

		public function createCheckout(array $data, $group)
		{
			$email = mb_strtolower(trim((string) $data['email']));
			$password = bin2hex(random_bytes(32));
			$userName = mb_strlen($email) <= 50
				? $email
				: mb_substr($email, 0, 37) . '_' . substr(sha1($email), 0, 12);
			DB::Insert(PublicUserTables::table('users'), array_merge($this->recordDefaults(), array(
				'email' => $email,
				'password' => password_hash($password, PASSWORD_BCRYPT, array('cost' => 12)),
				'firstname' => mb_substr(trim((string) (isset($data['firstname']) ? $data['firstname'] : '')), 0, 50),
				'lastname' => mb_substr(trim((string) (isset($data['lastname']) ? $data['lastname'] : '')), 0, 50),
				'phone' => mb_substr(trim((string) (isset($data['phone']) ? $data['phone'] : '')), 0, 50),
				'company' => '',
				'city' => mb_substr(trim((string) (isset($data['city']) ? $data['city'] : '')), 0, 100),
				'street' => mb_substr(trim((string) (isset($data['street']) ? $data['street'] : '')), 0, 255),
				'street_nr' => '',
				'zipcode' => mb_substr(trim((string) (isset($data['zipcode']) ? $data['zipcode'] : '')), 0, 20),
				'user_name' => $userName,
				'user_group' => (int) $group,
				'reg_time' => time(),
				'status' => '1',
				'reg_ip' => $this->ipLong(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
				'email_verified_at' => 0,
			)));
			return $this->find((int) DB::insertId());
		}

		public function createPhone($phone, $group)
		{
			$phone = Phone::normalize($phone);
			if ($phone === '') {
				throw new \InvalidArgumentException('Укажите корректный номер телефона.');
			}

			if ($this->findByPhone($phone)) {
				throw new \InvalidArgumentException('Этот номер телефона уже используется.');
			}

			$password = bin2hex(random_bytes(32));
			$userName = $this->uniqueUserName($phone);
			DB::Insert(PublicUserTables::table('users'), array_merge($this->recordDefaults(), array(
				'email' => null,
				'password' => password_hash($password, PASSWORD_BCRYPT, array('cost' => 12)),
				'phone' => $phone,
				'phone_normalized' => $phone,
				'phone_verified_at' => time(),
				'user_name' => $userName,
				'user_group' => (int) $group,
				'reg_time' => time(),
				'status' => '1',
				'reg_ip' => $this->ipLong(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
			)));
			return $this->find((int) DB::insertId());
		}

		public function verifyEmail($id)
		{
			DB::query('UPDATE `' . PublicUserTables::table('users') . '` SET status=%s,email_verified_at=%i WHERE Id=%i', '1', time(), (int) $id);
		}

		public function confirmEmail($id)
		{
			DB::query('UPDATE `' . PublicUserTables::table('users') . '` SET email_verified_at=%i WHERE Id=%i AND email IS NOT NULL', time(), (int) $id);
		}

		public function activate($id)
		{
			DB::query('UPDATE `' . PublicUserTables::table('users') . '` SET status=%s WHERE Id=%i', '1', (int) $id);
		}

		public function verifyPhone($id, $phone)
		{
			$phone = Phone::normalize($phone);
			if ($phone === '') {
				throw new \InvalidArgumentException('Некорректный номер телефона.');
			}

			DB::Update(PublicUserTables::table('users'), array(
				'phone' => $phone,
				'phone_normalized' => $phone,
				'phone_verified_at' => time(),
			), 'Id=%i', (int) $id);
		}

		public function setPassword($id, $password)
		{
			$hash = password_hash((string) $password, PASSWORD_BCRYPT, array('cost' => 12));
			DB::query('UPDATE `' . PublicUserTables::table('users') . '` SET password=%s,salt=%s,new_pass=%s,new_salt=%s WHERE Id=%i', $hash, '', '', '', (int) $id);
		}

		public function verifyPassword(array $user, $password)
		{
			$hash = isset($user['password']) ? (string) $user['password'] : '';
			if (strlen($hash) === 32) {
				return hash_equals($hash, md5(md5((string) $password . (string) $user['salt'])));
			}

			return password_verify((string) $password, $hash);
		}

		public function updateProfile($id, array $data)
		{
			$current = $this->find((int) $id);
			if (!$current) { throw new \InvalidArgumentException('Пользователь не найден.'); }
			$email = mb_strtolower(trim((string) (isset($data['email']) ? $data['email'] : $current['email'])));
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new \InvalidArgumentException('Укажите корректный email.');
			}

			$emailOwner = $email !== '' ? $this->findByEmail($email) : null;
			if ($emailOwner && (int) $emailOwner['Id'] !== (int) $id) {
				throw new \InvalidArgumentException('Этот email уже используется.');
			}

			$currentEmail = mb_strtolower(trim((string) (isset($current['email']) ? $current['email'] : '')));
			$emailChanged = $email !== $currentEmail;
			$phone = Phone::normalize(isset($data['phone']) ? $data['phone'] : '');
			if (trim((string) (isset($data['phone']) ? $data['phone'] : '')) !== '' && $phone === '') {
				throw new \InvalidArgumentException('Укажите корректный номер телефона.');
			}

			if ($email === '' && empty($current['phone_verified_at'])) {
				throw new \InvalidArgumentException('Нельзя удалить email, пока не подтверждён телефон.');
			}

			$duplicate = $phone !== '' ? $this->findByPhone($phone) : null;
			if ($duplicate && (int) $duplicate['Id'] !== (int) $id) {
				throw new \InvalidArgumentException('Этот номер телефона уже используется.');
			}

			$currentPhone = $current ? Phone::normalize(isset($current['phone']) ? $current['phone'] : '') : '';
			if ($current && empty($current['email']) && !empty($current['phone_verified_at']) && $currentPhone !== $phone) {
				throw new \InvalidArgumentException('Подтверждённый телефон нельзя изменить без повторной проверки.');
			}

			$phoneUnchanged = $current && $currentPhone === $phone;
			DB::Update(PublicUserTables::table('users'), array(
				'email' => $email !== '' ? $email : null,
				'email_verified_at' => $emailChanged ? 0 : (int) $current['email_verified_at'],
				'firstname' => trim((string) $data['firstname']),
				'lastname' => trim((string) $data['lastname']),
				'phone' => $phone,
				'phone_normalized' => $phoneUnchanged && !empty($current['phone_normalized'])
					? (string) $current['phone_normalized']
					: null,
				'phone_verified_at' => $phoneUnchanged ? (int) $current['phone_verified_at'] : 0,
				'company' => trim((string) $data['company']),
				'city' => trim((string) $data['city']),
				'street' => trim((string) $data['street']),
				'street_nr' => trim((string) $data['street_nr']),
				'zipcode' => trim((string) $data['zipcode']),
			), 'Id=%i', (int) $id);

			return array('email_changed' => $emailChanged, 'email' => $email);
		}

		protected function ipLong($ip)
		{
			$value = ip2long((string) $ip);
			return $value === false ? 0 : sprintf('%u', $value);
		}

		protected function userName($email)
		{
			$email = trim((string) $email);
			return mb_strlen($email) <= 50
				? $email
				: mb_substr($email, 0, 37) . '_' . substr(sha1($email), 0, 12);
		}

		protected function uniqueUserName($preferred)
		{
			$preferred = mb_substr(trim((string) $preferred), 0, 50);
			$candidate = $preferred;
			$index = 2;
			while (DB::query(
				'SELECT Id FROM `' . PublicUserTables::table('users') . '` WHERE user_name=%s LIMIT 1',
				$candidate
			)->getValue()) {
				$suffix = '-' . $index++;
				$candidate = mb_substr($preferred, 0, 50 - strlen($suffix)) . $suffix;
			}

			return $candidate;
		}

		protected function recordDefaults()
		{
			return array(
				'password' => '',
				'email' => null,
				'email_verified_at' => 0,
				'street' => '',
				'street_nr' => '',
				'zipcode' => '',
				'city' => '',
				'phone' => '',
				'phone_normalized' => null,
				'phone_verified_at' => 0,
				'telefax' => '',
				'description' => '',
				'firstname' => '',
				'lastname' => '',
				'user_name' => '',
				'user_group' => 4,
				'user_group_extra' => '',
				'reg_time' => time(),
				'status' => '1',
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
			);
		}
	}
