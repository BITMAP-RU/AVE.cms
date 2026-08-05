<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Phone/PhoneAuthService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Phone;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Lifecycle;
	use App\Common\PublicAuthSettings;
	use App\Common\RateLimiter;
	use App\Common\Session;
	use App\Frontend\Auth\UserRepository;
	use App\Helpers\Phone;
	use App\Helpers\Request;
	use App\Helpers\Url;

	class PhoneAuthService
	{
		public function request($providerCode, $phone, $consent, $returnUrl)
		{
			$provider = ProviderRegistry::get($providerCode);
			if (!$provider || !$provider->enabled() || !$provider->configured()) {
				throw new \RuntimeException('Вход по телефону сейчас недоступен.');
			}

			$phone = Phone::normalize($phone);
			if ($phone === '') {
				throw new \InvalidArgumentException('Укажите корректный номер телефона.');
			}

			$options = ProviderRegistry::options($provider);
			$phoneKey = hash('sha256', $phone);
			$settings = PublicAuthSettings::all();
			if (!empty($options['allow_registration'])
				&& PublicAuthSettings::allowsRegistrationMethod('phone', $settings)
				&& !$consent) {
				throw new \InvalidArgumentException('Подтвердите согласие с политикой конфиденциальности.');
			}

			$resendKey = 'phone-auth-resend:' . $this->sessionHash() . ':' . $phoneKey;
			if (!RateLimiter::attempt($resendKey, 1, $options['resend_after'])) {
				$wait = max(1, RateLimiter::availableIn($resendKey));
				throw new \InvalidArgumentException('Новый код можно запросить через ' . $wait . ' сек.');
			}

			if (!RateLimiter::attempt('phone-auth-ip:' . Request::ip(), 10, 900)
				|| !RateLimiter::attempt('phone-auth-phone:' . $phoneKey, 3, 900)
				|| !RateLimiter::attempt('phone-auth-day:' . $phoneKey, $options['daily_limit'], 86400)) {
				throw new \InvalidArgumentException('Слишком много запросов кода. Попробуйте позже.');
			}

			$registrationEnabled = !empty($options['allow_registration'])
				&& PublicAuthSettings::allowsRegistrationMethod('phone', $settings);
			$deliver = $registrationEnabled || (new UserRepository())->findByPhone($phone) !== null;
			$code = $this->code($options['code_length']);
			$repository = new ChallengeRepository($provider->challengeTable());
			$publicId = $repository->issue(
				$provider->code(),
				$phone,
				$code,
				$this->sessionHash(),
				Request::ip(),
				$consent,
				Url::localReturn($returnUrl),
				$options
			);
			if ($deliver) {
				try {
					$provider->sendCode($phone, $code);
				} catch (\Throwable $e) {
					$repository->deleteByPublicId($publicId);
					RateLimiter::clear($resendKey);
					throw $e;
				}
			}

			if (random_int(1, 100) === 1) {
				$repository->prune();
			}

			if ($deliver) {
				Lifecycle::event('auth.phone.code_sent', 'phone_auth', 'code_sent', null, array(
					'provider' => $provider->code(),
					'phone_mask' => Phone::mask($phone),
				), null, array(), 'phone_auth');
			}

			return array(
				'challenge' => $publicId,
				'phone_mask' => Phone::mask($phone),
				'expires_in' => (int) $options['ttl'],
				'resend_after' => (int) $options['resend_after'],
			);
		}

		public function verify($providerCode, $publicId, $code, $remember)
		{
			$provider = ProviderRegistry::get($providerCode);
			if (!$provider || !$provider->enabled() || !$provider->configured()) {
				throw new \RuntimeException('Вход по телефону сейчас недоступен.');
			}

			if (!preg_match('/^[a-f0-9]{32}$/', (string) $publicId) || !preg_match('/^[0-9]{4,8}$/', (string) $code)) {
				throw new \InvalidArgumentException('Проверьте код подтверждения.');
			}

			$repository = new ChallengeRepository($provider->challengeTable());
			$challenge = $repository->findActive($publicId, $this->sessionHash());
			if (!$challenge || !$repository->registerAttempt($challenge)
				|| !password_verify((string) $code, (string) $challenge['code_hash'])) {
				throw new \InvalidArgumentException('Код неверен или срок его действия истёк.');
			}

			if (!$repository->consume($challenge)) {
				throw new \InvalidArgumentException('Код уже использован.');
			}

			$users = new UserRepository();
			$user = $users->findByPhone((string) $challenge['phone']);
			$created = false;
			if (!$user) {
				$options = ProviderRegistry::options($provider);
				$settings = PublicAuthSettings::all();
				if (empty($options['allow_registration'])
					|| !PublicAuthSettings::allowsRegistrationMethod('phone', $settings)
					|| empty($challenge['consent'])) {
					throw new \InvalidArgumentException('Аккаунт с таким телефоном не найден.');
				}

				$registering = Lifecycle::event('auth.user.registering', 'user', 'registering', null, array(
					'data' => array('phone' => (string) $challenge['phone']),
					'gate' => $provider->code(),
					'mode' => 'phone',
				), null, array(), 'phone_auth');
				if ($registering->cancelled()) {
					throw new \RuntimeException($registering->message() !== '' ? $registering->message() : 'Регистрация отменена модулем.');
				}

				try {
					$user = $users->createPhone((string) $challenge['phone'], (int) $settings['default_group']);
					$created = true;
				} catch (\Throwable $e) {
					$user = $users->findByPhone((string) $challenge['phone']);
					if (!$user) {
						throw $e;
					}
				}

				Lifecycle::event('auth.user.registered', 'user', 'registered', (int) $user['Id'], array(
					'user' => $user,
					'gate' => $provider->code(),
					'mode' => 'phone',
				), $user, array(), 'phone_auth');
			}

			if ((string) $user['status'] !== '1' || (string) $user['deleted'] === '1') {
				throw new \InvalidArgumentException('Этот аккаунт недоступен.');
			}

			$users->verifyPhone((int) $user['Id'], (string) $challenge['phone']);
			if (!Auth::publicLoginById((int) $user['Id'], !empty($remember))) {
				throw new \RuntimeException('Не удалось открыть пользовательскую сессию.');
			}

			Lifecycle::event('auth.user.authenticated', 'user', 'authenticated', (int) $user['Id'], array(
				'user' => $user,
				'provider' => $provider->code(),
			), $user, array(), 'phone_auth');
			AuditLog::record($created ? 'auth.phone_registered' : 'auth.phone_authenticated', array(
				'target_type' => 'public_user',
				'target_id' => (int) $user['Id'],
				'meta' => array('provider' => $provider->code(), 'phone' => Phone::mask((string) $challenge['phone'])),
			));
			return array(
				'user' => $user,
				'created' => $created,
				'return_url' => Url::localReturn((string) $challenge['return_url']),
			);
		}

		protected function code($length)
		{
			$length = max(4, min(8, (int) $length));
			$minimum = (int) pow(10, $length - 1);
			$maximum = (int) pow(10, $length) - 1;
			return (string) random_int($minimum, $maximum);
		}

		protected function sessionHash()
		{
			$id = Session::getId();
			if ($id === null || $id === '') {
				throw new \RuntimeException('Сессия авторизации недоступна.');
			}

			return hash('sha256', (string) $id);
		}
	}
