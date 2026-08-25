<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\ModuleManager;
	use App\Common\RateLimiter;
	use App\Common\Session;
	use App\Common\Lifecycle;
	use App\Helpers\Request;
	use App\Helpers\Url;
	use App\Helpers\Hooks;
	use App\Frontend\Auth\OAuth\OAuthFlow;
	use App\Frontend\Auth\OAuth\OAuthService;
	use App\Frontend\Auth\OAuth\ProviderRegistry;
	use App\Frontend\Auth\OAuth\IdentityRepository;

	class Controller
	{
		public function show(array $params = array())
		{
			$preview = $this->isPreview();
			if ($this->hasPublicIdentity() && !$preview) {
				return $this->redirect(Feature::url('overview'));
			}

			return Renderer::page('login', array('error' => '', 'return_url' => self::localReturn(Request::getStr('_return', '/')), 'preview' => $preview));
		}

		public function authorize(array $params = array())
		{
			if ($this->hasPublicIdentity()) {
				return $this->redirect(Feature::url('overview'));
			}

			if (!Feature::passwordLoginEnabled()) {
				return $this->loginError('Вход по паролю отключён. Используйте код из SMS.', 403);
			}

			if (!$this->verifyCsrf()) {
				return $this->loginError('Сессия устарела. Обновите страницу.', 403);
			}

			if (!$this->verifyProtection('login')) {
				return $this->loginError('Проверка защиты не пройдена. Обновите форму и попробуйте снова.', 422);
			}

			$identifier = trim(Request::postStr('user_login', ''));
			$password = Request::postStr('user_pass', '');
			$limitKey = 'public-login:' . Request::ip() . ':' . sha1(mb_strtolower($identifier));
			if (!RateLimiter::attempt($limitKey, 5, 300)) {
				return $this->loginError('Слишком много попыток. Попробуйте позже.', 429);
			}

			if (!Auth::publicAttempt($identifier, $password, Request::postBool('keep_in', false))) {
				return $this->loginError('Неверный логин или пароль.', 422);
			}

			$user = Auth::publicUser();
			Lifecycle::event('auth.user.authenticated', 'user', 'authenticated', is_array($user) && isset($user['id']) ? (int) $user['id'] : 0, array(
				'user' => $user,
				'provider' => 'password',
			), $user, array(), 'auth_controller');

			$return = self::localReturn(Request::postStr('_return', '/'));
			if ($this->wantsJson()) {
				return $this->json(array('success' => true, 'redirect' => $return), 200);
			}

			return $this->redirect($return);
		}

		public function oauthStart(array $params = array())
		{
			$linkUser = null;
			if (Request::getBool('link', false)) {
				$linkUser = $this->authenticatedUser();
				if (!$linkUser) { return $this->redirectToLogin(Feature::url('profile')); }
			} elseif ($this->hasPublicIdentity()) { return $this->redirect('/'); }
			$providerCode = isset($params['provider']) ? strtolower((string) $params['provider']) : '';
			if (!RateLimiter::attempt('public-oauth-start:' . Request::ip() . ':' . $providerCode, 20, 600)) {
				return $this->loginError('Слишком много попыток. Попробуйте позже.', 429);
			}

			try {
				$provider = ProviderRegistry::get($providerCode);
				$returnUrl = $linkUser ? Feature::url('profile') : self::localReturn(Request::getStr('_return', '/'));
				$flow = OAuthFlow::create($providerCode, $returnUrl, $linkUser ? array('link_user_id' => (int) $linkUser['Id']) : array());
				return $this->externalRedirect($provider->authorizationUrl($flow));
			} catch (\InvalidArgumentException $e) {
				return $this->loginError($e->getMessage(), 422);
			} catch (\Throwable $e) {
				error_log('OAuth start [' . $providerCode . ']: ' . $e->getMessage());
				return $this->loginError('Не удалось начать социальный вход. Повторите позже.', 500);
			}
		}

		public function oauthCallback(array $params = array())
		{
			$providerCode = isset($params['provider']) ? strtolower((string) $params['provider']) : '';
			try {
				$flow = OAuthFlow::consume($providerCode, Request::getStr('state', ''));
				if (Request::getStr('error', '') !== '') {
					throw new \InvalidArgumentException('Вход через ' . ProviderRegistry::get($providerCode)->label() . ' отменён.');
				}

				$provider = ProviderRegistry::get($providerCode);
				$query = Request::getAll();
				$profile = $provider->profile($query, $flow);
				if (!empty($flow['link_user_id'])) {
					$current = $this->authenticatedUser();
					if (!$current || (int) $current['Id'] !== (int) $flow['link_user_id']) { throw new \InvalidArgumentException('Сессия привязки аккаунта устарела.'); }
					(new OAuthService())->link((int) $current['Id'], $profile);
				} else {
					(new OAuthService())->login($profile);
				}

				return $this->redirect(isset($flow['return_url']) ? $flow['return_url'] : '/');
			} catch (\InvalidArgumentException $e) {
				return $this->loginError($e->getMessage(), 422);
			} catch (\Throwable $e) {
				error_log('OAuth callback [' . $providerCode . ']: ' . $e->getMessage());
				return $this->loginError('Не удалось завершить социальный вход. Повторите позже.', 500);
			}
		}

		public function logout(array $params = array())
		{
			if (!$this->verifyCsrf()) {
				return $this->error('Сессия устарела. Обновите страницу.', 403);
			}

			$user = Auth::publicUser();
			Auth::publicLogout();
			Auth::logout();
			Lifecycle::event('auth.user.logged_out', 'user', 'logged_out', is_array($user) && isset($user['id']) ? (int) $user['id'] : 0, array(
				'user' => $user,
			), null, array(), 'auth_controller');
			$return = self::localReturn(Request::postStr('_return', '/'));
			if ($this->wantsJson()) {
				return $this->json(array('success' => true, 'redirect' => $return), 200);
			}

			return $this->redirect($return);
		}

		public function logoutGet(array $params = array())
		{
			http_response_code(405);
			header('Allow: POST');
			header('Content-Type: text/plain; charset=UTF-8');
			return 'Для выхода отправьте POST-запрос.';
		}

		public function registerForm(array $params = array())
		{
			$preview = $this->isPreview();
			if (!Feature::registrationFormEnabled()) {
				return Renderer::page('message', array('title' => 'Регистрация недоступна', 'message' => 'Регистрация новых пользователей временно отключена.'));
			}

			if ($this->hasPublicIdentity() && !$preview) {
				return $this->redirect(Feature::url('profile'));
			}

			return $this->renderRegistration(array(), array(), 200, $preview);
		}

		public function register(array $params = array())
		{
			if ($this->hasPublicIdentity()) {
				return $this->redirect('/');
			}

			if (!$this->verifyCsrf()) {
				return $this->renderRegistration(array(), array('_form' => 'Сессия устарела. Обновите страницу.'), 403);
			}

			if (!Feature::emailRegistrationEnabled()) {
				return $this->renderRegistration(array(), array('_form' => 'Регистрация по email отключена. Используйте доступный способ регистрации.'), 403);
			}

			if (!$this->verifyProtection('registration')) {
				return $this->renderRegistration(array(), array('_form' => 'Проверка защиты не пройдена. Обновите форму и попробуйте снова.'), 422);
			}

			$values = array(
				'email' => trim(Request::postStr('email', '')),
				'firstname' => trim(Request::postStr('firstname', '')),
				'lastname' => trim(Request::postStr('lastname', '')),
				'phone' => trim(Request::postStr('phone', '')),
				'company' => trim(Request::postStr('company', '')),
			);
			$values['extra'] = Request::post('extra', array());
			if (!is_array($values['extra'])) { $values['extra'] = array(); }
			$password = Request::postStr('password', '');
			$confirm = Request::postStr('password_confirm', '');
			$config = Feature::config();
			if (empty($config['show_lastname'])) { $values['lastname'] = ''; }
			if (empty($config['show_phone'])) { $values['phone'] = ''; }
			if (empty($config['show_company'])) { $values['company'] = ''; }
			$errors = $this->validatePassword($password, $confirm);
			if (!empty($config['require_firstname']) && $values['firstname'] === '') { $errors['firstname'] = 'Укажите имя.'; }
			if (!empty($config['require_lastname']) && $values['lastname'] === '') { $errors['lastname'] = 'Укажите фамилию.'; }
			if (!empty($config['require_phone']) && $values['phone'] === '') { $errors['phone'] = 'Укажите телефон.'; }
			if (!empty($config['require_company']) && $values['company'] === '') { $errors['company'] = 'Укажите компанию.'; }
			$errors = array_merge($errors, (new ProfileRepository())->validate($values['extra'], 'registration'));
			if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Укажите корректный email.'; }
			if (!empty($errors)) {
				return $this->renderRegistration($values, $errors, 422);
			}

			$limitKey = 'public-register:' . Request::ip() . ':' . sha1(mb_strtolower($values['email']));
			if (!RateLimiter::attempt($limitKey, 3, 900)) {
				return $this->renderRegistration($values, array('_form' => 'Слишком много попыток. Попробуйте позже.'), 429);
			}

			try {
				$values['password'] = $password;
				(new AuthService(Feature::config()))->register($values);
			} catch (\InvalidArgumentException $e) {
				return $this->renderRegistration($values, array('email' => $e->getMessage()), 422);
			} catch (\Throwable $e) {
				error_log('Registration: ' . $e->getMessage());
				return $this->renderRegistration($values, array('_form' => 'Не удалось завершить регистрацию. Повторите позже.'), 500);
			}

			$mode = isset($config['registration_mode']) ? $config['registration_mode'] : 'email';
			if ($mode === 'now') {
				return Renderer::page('message', array('title' => 'Регистрация завершена', 'message' => 'Аккаунт создан и уже доступен для входа.', 'action_url' => Feature::url('login'), 'action_label' => 'Войти'));
			}

			if ($mode === 'byadmin') {
				return Renderer::page('message', array('title' => 'Заявка принята', 'message' => 'Аккаунт создан. Вход станет доступен после проверки администратором.'));
			}

			return Renderer::page('message', array('title' => 'Проверьте почту', 'message' => 'Мы отправили ссылку для подтверждения регистрации на указанный email.'));
		}

		public function verifyRegistration(array $params = array())
		{
			$token = Request::getStr('token', '');
			$result = $token !== '' ? (new AuthService(Feature::config()))->verifyEmailToken($token) : false;
			$valid = $result !== false;
			$emailChange = $result === 'email_change';
			return Renderer::page('message', array(
				'title' => $valid ? 'Email подтвержден' : 'Ссылка недействительна',
				'message' => $valid
					? ($emailChange ? 'Адрес добавлен к вашему аккаунту.' : 'Регистрация завершена. Теперь можно войти.')
					: 'Ссылка уже использована или срок её действия истёк.',
				'action_url' => $valid ? ($emailChange ? Feature::url('profile') : Feature::url('login')) : '',
				'action_label' => $valid ? ($emailChange ? 'Вернуться в профиль' : 'Войти') : '',
			));
		}

		public function rememberForm(array $params = array())
		{
			if ($this->hasPublicIdentity() && !$this->isPreview()) {
				return $this->redirect('/');
			}

			if (empty(Feature::config()['password_reset_enabled'])) {
				return Renderer::page('message', array('title' => 'Восстановление недоступно', 'message' => 'Обратитесь к администратору сайта.'));
			}

			return Renderer::page('remember', array('values' => array(), 'errors' => array(), 'preview' => $this->isPreview()));
		}

		public function remember(array $params = array())
		{
			if (empty(Feature::config()['password_reset_enabled'])) { return $this->error('Восстановление пароля отключено.', 403); }
			if (!$this->verifyCsrf()) { return $this->formError('remember', 'Сессия устарела. Обновите страницу.', 403); }
			if (!$this->verifyProtection('password_reset')) { return $this->formError('remember', 'Проверка защиты не пройдена. Обновите форму и попробуйте снова.', 422); }
			$email = trim(Request::postStr('email', ''));
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return $this->renderForm('remember', array('email' => $email), array('email' => 'Укажите корректный email.'), 422);
			}

			if (!RateLimiter::attempt('public-reset:' . Request::ip() . ':' . sha1(mb_strtolower($email)), 3, 900)) {
				return $this->formError('remember', 'Слишком много попыток. Попробуйте позже.', 429);
			}

			try {
				(new AuthService(Feature::config()))->requestPasswordReset($email);
			} catch (\Throwable $e) {
				error_log('Password reset request: ' . $e->getMessage());
			}

			return Renderer::page('message', array('title' => 'Проверьте почту', 'message' => 'Если пользователь найден, мы отправили ссылку для смены пароля.'));
		}

		public function resetForm(array $params = array())
		{
			if (empty(Feature::config()['password_reset_enabled'])) {
				return Renderer::page('message', array('title' => 'Восстановление недоступно', 'message' => 'Обратитесь к администратору сайта.'));
			}

			return Renderer::page('reset', array('token' => Request::getStr('token', ''), 'errors' => array(), 'preview' => $this->isPreview()));
		}

		public function reset(array $params = array())
		{
			if (empty(Feature::config()['password_reset_enabled'])) { return $this->error('Восстановление пароля отключено.', 403); }
			if (!$this->verifyCsrf()) { return $this->formError('reset', 'Сессия устарела. Обновите страницу.', 403); }
			if (!$this->verifyProtection('password_reset')) { return $this->formError('reset', 'Проверка защиты не пройдена. Обновите форму и попробуйте снова.', 422); }
			$token = Request::postStr('token', '');
			$password = Request::postStr('password', '');
			$errors = $this->validatePassword($password, Request::postStr('password_confirm', ''));
			if (!empty($errors)) {
				http_response_code(422);
				return Renderer::page('reset', array('token' => $token, 'errors' => $errors));
			}

			if (!(new AuthService(Feature::config()))->resetPassword($token, $password)) {
				return $this->formError('reset', 'Ссылка уже использована или срок её действия истёк.', 422, array('token' => $token));
			}

			return Renderer::page('message', array('title' => 'Пароль изменен', 'message' => 'Используйте новый пароль для входа.', 'action_url' => Feature::url('login'), 'action_label' => 'Войти'));
		}

		public function profileForm(array $params = array())
		{
			$preview = $this->isPreview();
			$user = $this->authenticatedUser();
			if (!$user && !$preview) { return $this->redirectToLogin(Feature::url('profile')); }
			if (!$user) { $user = $this->previewUser(); }
			$profiles = new ProfileRepository();
			return Renderer::page('profile', array('user' => $user, 'fields' => $profiles->fields(), 'extra_values' => $preview ? array() : $profiles->values((int) $user['Id']), 'errors' => array(), 'saved' => Request::getBool('saved', false), 'email_pending' => Request::getBool('email_pending', false), 'preview' => $preview, 'oauth_connections' => $this->oauthConnections((int) $user['Id'], $preview)));
		}

		public function overview(array $params = array())
		{
			$preview = $this->isPreview();
			$user = $this->authenticatedUser();
			if (!$user && !$preview) {
				return $this->redirectToLogin(Feature::url('overview'));
			}

			if (!$user) { $user = $this->previewUser(); }
			return Renderer::page('overview', array_merge(
				array('user' => $user, 'preview' => $preview),
				$this->overviewData((int) $user['Id'], $preview)
			));
		}

		public function profile(array $params = array())
		{
			$user = $this->authenticatedUser();
			if (!$user) { return $this->redirectToLogin(Feature::url('profile')); }
			if (!$this->verifyCsrf()) { return $this->formError('profile', 'Сессия устарела. Обновите страницу.', 403, array('user' => $user)); }
			$data = array();
			foreach (array('email','firstname','lastname','phone','company','city','street','street_nr','zipcode') as $key) {
				$data[$key] = trim(Request::postStr($key, ''));
			}

			$extra = Request::post('extra', array());
			$extra = is_array($extra) ? $extra : array();
			$profiles = new ProfileRepository();
			$errors = $profiles->validate($extra);
			if ($data['firstname'] === '') { $errors['firstname'] = 'Укажите имя.'; }
			if (!empty($errors)) {
				http_response_code(422);
				return Renderer::page('profile', array('user' => array_merge($user, $data), 'fields' => $profiles->fields(), 'extra_values' => $extra, 'errors' => $errors, 'saved' => false, 'oauth_connections' => $this->oauthConnections((int) $user['Id'])));
			}

			try {
				$profileResult = (new UserRepository())->updateProfile((int) $user['Id'], $data);
				if ($profileResult['email'] !== ''
					&& (!empty($profileResult['email_changed']) || empty($user['email_verified_at']))) {
					(new AuthService(Feature::config()))->sendEmailVerification((int) $user['Id']);
				}
			} catch (\InvalidArgumentException $e) {
				http_response_code(422);
				return Renderer::page('profile', array(
					'user' => array_merge($user, $data),
					'fields' => $profiles->fields(),
					'extra_values' => $extra,
					'errors' => array('_form' => $e->getMessage()),
					'saved' => false,
					'oauth_connections' => $this->oauthConnections((int) $user['Id']),
				));
			}

			$profiles->save((int) $user['Id'], $extra);
			Session::set('user_firstname', $data['firstname']);
			Session::set('user_lastname', $data['lastname']);
			Session::set('user_name', trim($data['firstname'] . ' ' . $data['lastname']));
			$query = '?saved=1';
			if ($profileResult['email'] !== ''
				&& (!empty($profileResult['email_changed']) || empty($user['email_verified_at']))) { $query .= '&email_pending=1'; }
			return $this->redirect(Feature::url('profile') . $query);
		}

		public function passwordForm(array $params = array())
		{
			$preview = $this->isPreview();
			if (!$this->authenticatedUser() && !$preview) { return $this->redirectToLogin(Feature::url('password')); }
			return Renderer::page('password', array('errors' => array(), 'preview' => $preview));
		}

		public function password(array $params = array())
		{
			$user = $this->authenticatedUser();
			if (!$user) { return $this->redirectToLogin(Feature::url('password')); }
			if (!$this->verifyCsrf()) { return $this->formError('password', 'Сессия устарела. Обновите страницу.', 403); }
			$repository = new UserRepository();
			if (!$repository->verifyPassword($user, Request::postStr('current_password', ''))) {
				return $this->renderForm('password', array(), array('current_password' => 'Текущий пароль указан неверно.'), 422);
			}

			$password = Request::postStr('password', '');
			$errors = $this->validatePassword($password, Request::postStr('password_confirm', ''));
			if (!empty($errors)) { return $this->renderForm('password', array(), $errors, 422); }
			$repository->setPassword((int) $user['Id'], $password);
			Auth::publicLogout();
			return Renderer::page('message', array('title' => 'Пароль изменен', 'message' => 'Войдите снова с новым паролем.', 'action_url' => Feature::url('login'), 'action_label' => 'Войти'));
		}

		protected function verifyCsrf()
		{
			try {
				Session::verifyCsrf(Request::postStr('_csrf', ''));
				return true;
			} catch (\Throwable $e) {
				return false;
			}
		}

		protected function verifyProtection($profile)
		{
			$input = Request::postAll();
			$result = Hooks::filter('public.form.protection.verify', array(
				'profile' => (string) $profile,
				'input' => $input,
				'context' => array('ip' => Request::ip()),
				'allowed' => true,
				'handled' => false,
			));

			return !is_array($result) || empty($result['handled']) || !empty($result['allowed']);
		}

		protected function authenticatedUser()
		{
			$current = Auth::publicUser();
			return $current ? (new UserRepository())->find((int) $current['id']) : null;
		}

		protected function oauthConnections($userId, $preview = false)
		{
			$providers = ProviderRegistry::publicItems();
			if (!$providers) { return array(); }
			try {
				$linked = $preview ? array() : (new IdentityRepository())->forUser((int) $userId);
			} catch (\Throwable $e) {
				error_log('OAuth connections: ' . $e->getMessage());
				$linked = array();
			}

			$out = array();
			foreach ($providers as $provider) {
				$code = isset($provider['code']) ? (string) $provider['code'] : '';
				if ($code === '') { continue; }
				$provider['linked'] = isset($linked[$code]);
				$provider['link_url'] = '/auth/oauth/' . rawurlencode($code) . '?link=1&_return=' . rawurlencode(Feature::url('profile'));
				$out[] = $provider;
			}

			return $out;
		}

		protected function overviewData($userId, $preview)
		{
			$data = array(
				'commerce_available' => false,
				'orders' => array(),
				'orders_total' => 0,
				'orders_paid' => 0,
				'favorites_count' => 0,
				'account_extensions' => array(),
			);
			$orderRepository = '\\App\\Frontend\\Basket\\CustomerOrderRepository';
			$favoriteRepository = '\\App\\Frontend\\Basket\\FavoriteRepository';
			$commerce = ModuleManager::get('commerce');
			if ($commerce && !empty($commerce['enabled']) && class_exists($orderRepository)) {
				$data['commerce_available'] = true;
				if (!$preview) {
					try {
						$summary = $orderRepository::summary($userId);
						$data['orders_total'] = isset($summary['total']) ? (int) $summary['total'] : 0;
						$data['orders_paid'] = isset($summary['paid']) ? (int) $summary['paid'] : 0;
						$data['orders'] = $orderRepository::recent($userId, 2);
						if (class_exists($favoriteRepository)) {
							$data['favorites_count'] = (int) $favoriteRepository::count();
						}
					} catch (\Throwable $e) {
						error_log('Account overview: ' . $e->getMessage());
					}
				}
			}

			$extensions = Hooks::filter('auth.account.overview', array(
				'user_id' => (int) $userId,
				'preview' => (bool) $preview,
				'extensions' => array(),
			));
			if (is_array($extensions) && isset($extensions['extensions']) && is_array($extensions['extensions'])) {
				$data['account_extensions'] = $extensions['extensions'];
			}

			return $data;
		}

		/** A control-panel identity is also valid on public pages. */
		protected function hasPublicIdentity()
		{
			return Auth::publicCheck() || (bool) Auth::systemUser();
		}

		protected function validatePassword($password, $confirm)
		{
			$errors = array();
			$minimum = max(8, (int) (isset(Feature::config()['password_min_length']) ? Feature::config()['password_min_length'] : 8));
			if (mb_strlen((string) $password) < $minimum) { $errors['password'] = 'Пароль должен содержать не менее ' . $minimum . ' символов.'; }
			if ((string) $password !== (string) $confirm) { $errors['password_confirm'] = 'Пароли не совпадают.'; }
			return $errors;
		}

		protected function renderForm($template, array $values, array $errors, $status)
		{
			http_response_code((int) $status);
			return Renderer::page($template, array('values' => $values, 'errors' => $errors));
		}

		protected function renderRegistration(array $values, array $errors, $status, $preview = false)
		{
			http_response_code((int) $status);
			$profiles = new ProfileRepository();
			return Renderer::page('register', array(
				'values' => $values,
				'extra_values' => isset($values['extra']) && is_array($values['extra']) ? $values['extra'] : array(),
				'fields' => $profiles->fields('registration'),
				'config' => Feature::config(),
				'email_registration_enabled' => Feature::emailRegistrationEnabled(),
				'phone_registration_enabled' => Feature::phoneRegistrationEnabled(),
				'registration_phone_providers' => Feature::phoneRegistrationProviders(),
				'errors' => $errors,
				'preview' => !empty($preview),
			));
		}

		protected function isPreview()
		{
			return Request::getBool('_auth_preview', false) && Auth::systemUserCan('manage_customers');
		}

		protected function previewUser()
		{
			return array(
				'Id' => 0,
				'email' => 'customer@example.org',
				'email_verified_at' => time(),
				'firstname' => 'Имя',
				'lastname' => 'Фамилия',
				'phone' => '+7 900 000-00-00',
				'phone_verified_at' => 0,
				'company' => 'Компания',
				'city' => 'Город',
				'street' => 'Улица',
				'street_nr' => '1',
				'zipcode' => '000000',
			);
		}

		protected function formError($template, $message, $status, array $data = array())
		{
			http_response_code((int) $status);
			return Renderer::page($template, array_merge(array('values' => array(), 'errors' => array('_form' => $message)), $data));
		}

		protected function error($message, $status)
		{
			if ($this->wantsJson()) {
				return $this->json(array('success' => false, 'message' => $message), $status);
			}

			http_response_code((int) $status);
			return Renderer::panel(true, $message);
		}

		protected function loginError($message, $status)
		{
			if ($this->wantsJson()) { return $this->json(array('success' => false, 'message' => $message), $status); }
			http_response_code((int) $status);
			return Renderer::page('login', array('error' => (string) $message, 'return_url' => self::localReturn(Request::postStr('_return', '/'))));
		}

		protected function json(array $payload, $status)
		{
			return \App\Helpers\Response::jsonBody($payload, $status);
		}

		protected function redirect($url)
		{
			header('Location: ' . self::localReturn($url), true, 303);
			return '';
		}

		protected function redirectToLogin($returnUrl)
		{
			$loginUrl = Feature::url('login');
			$returnUrl = self::localReturn($returnUrl);
			header('Location: ' . $loginUrl . '?_return=' . rawurlencode($returnUrl), true, 303);
			return '';
		}

		protected function externalRedirect($url)
		{
			$url = (string) $url;
			if (!preg_match('#^https://(?:oauth\.yandex\.ru|id\.vk\.ru)/#', $url)) {
				throw new \InvalidArgumentException('Некорректный адрес сервиса авторизации.');
			}

			header('Location: ' . $url, true, 303);
			return '';
		}

		protected function wantsJson()
		{
			$accept = (string) Request::header('Accept', '');
			return Request::isAjax() || strpos($accept, 'application/json') !== false;
		}

		protected static function localReturn($return)
		{
			$path = Url::localReturn($return);
			$query = parse_url($path, PHP_URL_QUERY);
			$path = (string) parse_url($path, PHP_URL_PATH);
			if (in_array(rtrim($path, '/'), array(rtrim(Feature::url('login'), '/'), rtrim(Feature::url('logout'), '/'), '/login', '/logout'), true)) {
				$path = '/';
				$query = '';
			}

			return $path . ($query ? '?' . $query : '');
		}
	}
