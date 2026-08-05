<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/AuthService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Frontend\Auth\Registration\RegistrationGateRegistry;
	use App\Frontend\Auth\Registration\CheckoutRegistrationGateInterface;
	use App\Common\Lifecycle;
	use App\Common\SiteOrigin;

	class AuthService
	{
		protected $users;
		protected $tokens;
		protected $config;

		public function __construct(array $config)
		{
			$this->users = new UserRepository();
			$this->tokens = new TokenRepository();
			$this->config = $config;
		}

		public function register(array $data)
		{
			if (!\App\Common\PublicAuthSettings::allowsRegistrationMethod('email', $this->config)) {
				throw new \RuntimeException('Регистрация по email отключена.');
			}

			$registering = Lifecycle::event('auth.user.registering', 'user', 'registering', null, array(
				'data' => $data,
			), null, array(), 'auth_service');
			if ($registering->cancelled()) {
				throw new \RuntimeException($registering->message() !== '' ? $registering->message() : 'Регистрация отменена модулем.');
			}

			$data = $registering->value('data', $data);
			$gate = RegistrationGateRegistry::get('email');
			if (!$gate) {
				throw new \RuntimeException('Канал регистрации не настроен.');
			}

			$email = $gate->normalize(isset($data['email']) ? $data['email'] : '');
			if (!$gate->valid($email)) {
				throw new \InvalidArgumentException('Укажите корректный email.');
			}

			if ($this->deniedEmail($email)) {
				throw new \InvalidArgumentException('Регистрация с этим email недоступна.');
			}

			if ($this->users->findByEmail($email)) {
				throw new \InvalidArgumentException('Пользователь с таким email уже зарегистрирован.');
			}

			$data['email'] = $email;
			$user = $this->users->create($data, $this->defaultGroup());
			$extra = isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : array();
			(new ProfileRepository())->save((int) $user['Id'], $extra, 'registration');
			$mode = $this->setting('registration_mode', 'email');
			if ($mode === 'now') {
				$this->users->activate((int) $user['Id']);
			} elseif ($mode === 'email') {
				$token = $this->tokens->issue((int) $user['Id'], 'registration', $gate->code(), $this->setting('verification_ttl', 86400));
				$gate->send($user, $token);
			}

			$registered = Lifecycle::event('auth.user.registered', 'user', 'registered', isset($user['Id']) ? (int) $user['Id'] : 0, array(
				'user' => $user,
				'gate' => $gate->code(),
				'mode' => $mode,
			), $user, array(), 'auth_service');
			return is_array($registered->result()) ? $registered->result() : $user;
		}

		public function verifyRegistration($token)
		{
			return $this->verifyEmailToken($token) !== false;
		}

		public function verifyEmailToken($token)
		{
			$row = $this->tokens->consume($token, 'registration');
			if ($row) {
				$this->users->verifyEmail((int) $row['user_id']);
				return 'registration';
			}

			$row = $this->tokens->consume($token, 'email_change');
			if (!$row) { return false; }
			$user = $this->users->find((int) $row['user_id']);
			$email = $user && isset($user['email']) ? mb_strtolower(trim((string) $user['email'])) : '';
			$channel = $this->emailVerificationChannel($email);
			if ($email === '' || !hash_equals((string) $row['channel'], $channel)) { return false; }
			$this->users->confirmEmail((int) $row['user_id']);
			return 'email_change';
		}

		public function sendEmailVerification($userId)
		{
			$user = $this->users->find((int) $userId);
			$email = $user && isset($user['email']) ? mb_strtolower(trim((string) $user['email'])) : '';
			if (!$user || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new \InvalidArgumentException('Укажите корректный email.');
			}

			$channel = $this->emailVerificationChannel($email);
			$raw = $this->tokens->issue((int) $user['Id'], 'email_change', $channel, $this->setting('verification_ttl', 86400));
			$url = SiteOrigin::absolute(Feature::url('verify'), true) . '?token=' . rawurlencode($raw);
			$html = '<h2>Подтверждение email</h2><p><a href="'
				. htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Подтвердить email</a></p>';
			$sent = \App\Frontend\PublicMailer::send($email, $html, 'Подтверждение email', '', '', 'text/html', array(), false, false);
			if ($sent !== true) { throw new \RuntimeException('Не удалось отправить письмо подтверждения.'); }
			return true;
		}

		public function checkoutOptions()
		{
			$gate = $this->checkoutGate();
			if (!$gate || empty($this->config['registration_enabled'])) {
				return array('enabled' => false, 'policy_url' => '', 'policy_label' => '');
			}

			$options = $gate->checkoutOptions();
			return array(
				'enabled' => !empty($options['enabled']) && !empty($this->config['password_reset_enabled']),
				'policy_url' => isset($options['policy_url']) ? (string) $options['policy_url'] : '',
				'policy_label' => isset($options['policy_label']) ? (string) $options['policy_label'] : '',
			);
		}

		public function checkoutAccountState($email)
		{
			$gate = $this->checkoutGate();
			$email = $gate ? $gate->normalize($email) : mb_strtolower(trim((string) $email));
			if (!$gate || !$gate->valid($email)) {
				return array('valid' => false, 'exists' => false, 'email' => $email);
			}

			return array('valid' => true, 'exists' => $this->users->findByEmail($email) !== null, 'email' => $email);
		}

		public function registerCheckout(array $data, array $context = array())
		{
			$gate = $this->checkoutGate();
			$options = $this->checkoutOptions();
			if (!$gate || empty($options['enabled'])) { throw new \RuntimeException('Регистрация при оформлении заказа отключена.'); }
			if (empty($context['consent'])) { throw new \InvalidArgumentException('Подтвердите согласие с политикой конфиденциальности.'); }

			$email = $gate->normalize(isset($data['email']) ? $data['email'] : '');
			if (!$gate->valid($email)) { throw new \InvalidArgumentException('Для регистрации укажите корректный email.'); }
			if ($this->deniedEmail($email)) { throw new \InvalidArgumentException('Регистрация с этим email недоступна.'); }
			if ($this->users->findByEmail($email)) { throw new \InvalidArgumentException('Пользователь с таким email уже зарегистрирован. Войдите в аккаунт.'); }

			$data['email'] = $email;
			$registering = Lifecycle::event('auth.user.registering', 'user', 'registering', null, array(
				'data' => $data,
				'gate' => $gate->code(),
				'mode' => 'checkout',
				'context' => $context,
			), null, array(), 'auth_service');
			if ($registering->cancelled()) {
				throw new \RuntimeException($registering->message() !== '' ? $registering->message() : 'Регистрация отменена модулем.');
			}

			$data = $registering->value('data', $data);
			$user = $this->users->createCheckout($data, $this->defaultGroup());
			$registered = Lifecycle::event('auth.user.registered', 'user', 'registered', (int) $user['Id'], array(
				'user' => $user,
				'gate' => $gate->code(),
				'mode' => 'checkout',
				'context' => $context,
			), $user, array(), 'auth_service');
			return is_array($registered->result()) ? $registered->result() : $user;
		}

		public function sendCheckoutAccess(array $user, array $context = array())
		{
			$gate = $this->checkoutGate();
			if (!$gate) { return false; }
			$ttl = max(300, (int) $this->setting('reset_ttl', 3600));
			$token = $this->tokens->issue((int) $user['Id'], 'checkout_access', $gate->code(), $ttl);
			$context['ttl'] = $ttl;
			$gate->sendCheckoutAccess($user, $token, $context);
			return true;
		}

		public function requestPasswordReset($email)
		{
			$user = $this->users->findByEmail($email);
			if (!$user || (string) $user['status'] !== '1') {
				return;
			}

			if (empty($user['email_verified_at']) && !empty($user['phone_verified_at'])
				&& mb_strtolower((string) $user['user_name']) !== mb_strtolower((string) $user['email'])) {
				return;
			}

			$raw = $this->tokens->issue((int) $user['Id'], 'password_reset', 'email', $this->setting('reset_ttl', 3600));
			$url = SiteOrigin::absolute(Feature::url('reset'), true) . '?token=' . rawurlencode($raw);
			$html = '<h2>Восстановление пароля</h2><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Задать новый пароль</a></p>';
			$sent = \App\Frontend\PublicMailer::send(
				(string) $user['email'], $html, 'Восстановление пароля', '', '', 'text/html', array(), false, false
			);
			if ($sent !== true) {
				throw new \RuntimeException('Не удалось отправить письмо восстановления.');
			}
		}

		public function resetPassword($token, $password)
		{
			$row = $this->tokens->consume($token, 'password_reset');
			$checkout = false;
			if (!$row) {
				$row = $this->tokens->consume($token, 'checkout_access');
				$checkout = $row !== null;
			}

			if (!$row) {
				return false;
			}

			$this->users->setPassword((int) $row['user_id'], $password);
			if ($checkout) { $this->users->verifyEmail((int) $row['user_id']); }
			return true;
		}

		protected function checkoutGate()
		{
			if (!\App\Common\PublicAuthSettings::allowsRegistrationMethod('email', $this->config)) {
				return null;
			}

			$gate = RegistrationGateRegistry::get('email');
			return $gate instanceof CheckoutRegistrationGateInterface ? $gate : null;
		}

		protected function setting($key, $default)
		{
			return isset($this->config[$key]) ? $this->config[$key] : $default;
		}

		protected function emailVerificationChannel($email)
		{
			return 'email:' . substr(hash('sha256', mb_strtolower(trim((string) $email))), 0, 18);
		}

		protected function defaultGroup()
		{
			$group = (int) $this->setting('default_group', 4);
			if ($group <= 0) {
				throw new \RuntimeException('Группа регистрации не настроена.');
			}

			return $group;
		}

		protected function deniedEmail($email)
		{
			$email = mb_strtolower(trim((string) $email));
			$parts = explode('@', $email, 2);
			$domain = isset($parts[1]) ? $parts[1] : '';
			$emails = $this->lines($this->setting('deny_emails', ''));
			$domains = $this->lines($this->setting('deny_domains', ''));
			return in_array($email, $emails, true) || ($domain !== '' && in_array($domain, $domains, true));
		}

		protected function lines($value)
		{
			return array_values(array_filter(array_map(function ($item) {
				return mb_strtolower(trim((string) $item));
			}, preg_split('/[\r\n,]+/', (string) $value))));
		}
	}
