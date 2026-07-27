<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/OAuthService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\Lifecycle;
	use App\Frontend\Auth\Feature;
	use App\Frontend\Auth\UserRepository;

	class OAuthService
	{
		public function login(array $profile)
		{
			$provider = (string) (isset($profile['provider']) ? $profile['provider'] : '');
			$providerUserId = trim((string) (isset($profile['provider_user_id']) ? $profile['provider_user_id'] : ''));
			if ($provider === '' || $providerUserId === '') {
				throw new \RuntimeException('Провайдер не передал идентификатор пользователя.');
			}

			$identities = new IdentityRepository();
			$users = new UserRepository();
			$identity = $identities->find($provider, $providerUserId);
			$user = $identity ? $users->find((int) $identity['user_id']) : null;
			$created = false;
			$config = ProviderRegistry::config($provider);

			if (!$user) {
				$email = mb_strtolower(trim((string) (isset($profile['email']) ? $profile['email'] : '')));
				if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
					throw new \InvalidArgumentException('В аккаунте провайдера недоступен подтверждённый email.');
				}

				$emailUser = $users->findByEmail($email);
				if ($emailUser && !empty($config['link_by_email'])) {
					$user = $emailUser;
				} elseif ($emailUser) {
					throw new \InvalidArgumentException('Пользователь с таким email уже существует. Войдите паролем для привязки аккаунта.');
				} elseif (!empty($config['allow_registration']) && Feature::registrationEnabled()) {
					$settings = Feature::config();
					$user = $users->createSocial($profile, isset($settings['default_group']) ? (int) $settings['default_group'] : 4);
					$created = true;
				} else {
					throw new \InvalidArgumentException('Автоматическая регистрация через этот сервис отключена.');
				}
			}

			if ((string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				throw new \InvalidArgumentException('Учётная запись отключена.');
			}

			$identities->save((int) $user['Id'], $profile);
			if (!Auth::publicLoginById((int) $user['Id'], false)) {
				throw new \RuntimeException('Не удалось открыть пользовательскую сессию.');
			}

			if ($created) {
				Lifecycle::event('auth.user.registered', 'user', 'registered', (int) $user['Id'], array(
					'user' => $user,
					'gate' => $provider,
					'mode' => 'oauth',
				), $user, array(), 'oauth_service');
			}

			Lifecycle::event('auth.user.authenticated', 'user', 'authenticated', (int) $user['Id'], array(
				'user' => $user,
				'provider' => $provider,
			), $user, array(), 'oauth_service');
			return $user;
		}

		public function link($userId, array $profile)
		{
			$user = (new UserRepository())->find((int) $userId);
			if (!$user || (string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				throw new \InvalidArgumentException('Пользовательский аккаунт недоступен.');
			}

			$provider = (string) (isset($profile['provider']) ? $profile['provider'] : '');
			$providerUserId = trim((string) (isset($profile['provider_user_id']) ? $profile['provider_user_id'] : ''));
			if ($provider === '' || $providerUserId === '') { throw new \InvalidArgumentException('Провайдер не передал идентификатор пользователя.'); }
			(new IdentityRepository())->save((int) $userId, $profile);
			Lifecycle::event('auth.user.identity_linked', 'user', 'identity_linked', (int) $userId, array(
				'user' => $user,
				'provider' => $provider,
			), $user, array(), 'oauth_service');
			return $user;
		}
	}
