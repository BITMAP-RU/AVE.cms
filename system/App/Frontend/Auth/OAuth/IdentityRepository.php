<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/IdentityRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\PublicUserTables;
	use DB;

	class IdentityRepository
	{
		public function find($provider, $providerUserId)
		{
			$row = DB::query(
				'SELECT * FROM `' . PublicUserTables::table('user_identities') . '` WHERE provider=%s AND provider_user_id=%s LIMIT 1',
				(string) $provider, (string) $providerUserId
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		public function forUser($userId)
		{
			$rows = DB::query(
				'SELECT provider,provider_user_id,email,created_at,updated_at,last_login_at FROM `' . PublicUserTables::table('user_identities') . '` WHERE user_id=%i ORDER BY provider',
				(int) $userId
			)->getAll();
			$out = array();
			foreach ($rows ?: array() as $row) { $out[(string) $row['provider']] = (array) $row; }
			return $out;
		}

		public function findForUser($userId, $provider)
		{
			$row = DB::query(
				'SELECT * FROM `' . PublicUserTables::table('user_identities') . '` WHERE user_id=%i AND provider=%s LIMIT 1',
				(int) $userId, (string) $provider
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		public function save($userId, array $profile)
		{
			$provider = (string) $profile['provider'];
			$providerUserId = (string) $profile['provider_user_id'];
			$linkedProvider = $this->findForUser((int) $userId, $provider);
			if ($linkedProvider && (string) $linkedProvider['provider_user_id'] !== $providerUserId) {
				throw new \RuntimeException('К аккаунту уже привязана другая учётная запись этого сервиса.');
			}
			$current = $this->find($provider, $providerUserId);
			$now = time();
			$data = array(
				'user_id' => (int) $userId,
				'email' => trim((string) $profile['email']),
				'profile_json' => json_encode(isset($profile['raw']) ? $profile['raw'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'updated_at' => $now, 'last_login_at' => $now,
			);
			if ($current) {
				if ((int) $current['user_id'] !== (int) $userId) {
					throw new \RuntimeException('Внешняя учётная запись уже привязана к другому пользователю.');
				}

				DB::Update(PublicUserTables::table('user_identities'), $data, 'id=%i', (int) $current['id']);
				return (int) $current['id'];
			}

			$data['provider'] = $provider;
			$data['provider_user_id'] = $providerUserId;
			$data['created_at'] = $now;
			DB::Insert(PublicUserTables::table('user_identities'), $data);
			return (int) DB::insertId();
		}
	}
