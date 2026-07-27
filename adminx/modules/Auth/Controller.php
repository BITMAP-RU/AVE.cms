<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Auth/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\AuditLog;
	use App\Common\Controller as BaseController;
	use App\Common\RateLimiter;
	use App\Common\Session;
	use App\Adminx\Support\AdminLocale;
	use App\Adminx\Support\ReAuth;
	use App\Helpers\Request;
	use App\Helpers\Url;

	/**
	 * Логин/логаут новой админки.
	 *
	 * Форма логина — обычный POST (работает без JS); logout — ajax POST + CSRF,
	 * чтобы обкатать единый ajax-контракт (ТЗ §8.5).
	 */
	class Controller extends BaseController
	{
		/** GET /login — экран входа. */
		public function form(array $params = [])
		{
			return $this->render('@auth/login.twig', [
				'error' => Request::getStr('error'),
			]);
		}

		/** POST /login — попытка входа. */
		public function login(array $params = [])
		{
			ReAuth::forget();
			try {
				$this->verifyCsrf();
			} catch (\RuntimeException $e) {
				return $this->render('@auth/login.twig', [
					'error' => AdminLocale::text('auth_err_session', 'Сессия устарела, обновите страницу и попробуйте снова.'),
				]);
			}

			$login    = Request::postStr('login');
			$password = (string) Request::post('password', '');
			$remember = Request::postBool('remember', false);

			//-- Современный Auth API (SystemTables users: email/password_hash/role).
			//-- Вход по email или логину; запоминание — по флажку.
			$options = [
				'identifier_fields' => ['email', 'login'],
				'remember'          => $remember,
				'issue_session_token' => true,
			];
			$result = Auth::attempt($login, $password, $options);

			if (is_array($result)) {
				Session::regenerateCsrf();
				$this->redirect($this->base() . '/');
				return null;
			}

			//-- Legacy-пароль (перенесённые пользователи, md5(md5(pass+salt))):
			//-- проверяем и молча перехешируем в bcrypt при первом входе.
			if ($this->legacyLogin($login, $password, $options)) {
				Session::regenerateCsrf();
				$this->redirect($this->base() . '/');
				return null;
			}

			$messages = [
				1 => AdminLocale::text('auth_err_required', 'Введите логин или email и пароль.'),
				2 => AdminLocale::text('auth_err_invalid', 'Неверный логин или пароль.'),
				3 => AdminLocale::text('auth_err_disabled', 'Учётная запись отключена.'),
			];
			$error = isset($messages[$result])
				? $messages[$result]
				: AdminLocale::text('auth_err_failed', 'Не удалось войти.');

			return $this->render('@auth/login.twig', [
				'error'      => $error,
				'login_prev' => $login,
			]);
		}

		/** POST /locale — смена только языка интерфейса Adminx. */
		public function language(array $params = array())
		{
			try {
				$this->verifyCsrf();
			} catch (\RuntimeException $e) {
				$this->redirect($this->base() . '/login');
				return null;
			}

			AdminLocale::set(Request::postStr('locale'));
			$return = Url::localReturn(Request::postStr('_return'), $this->base() . '/');
			$this->redirect($return);
			return null;
		}

		/** POST /reauth — подтверждение пароля перед чувствительным действием. */
		public function reauth(array $params = array())
		{
			$user = Auth::user();
			if (!$user) {
				return $this->error('Сессия входа завершена', array(), 401);
			}

			$key = 'adminx-reauth:' . (int) $user['id'] . ':' . Request::ip();
			if (RateLimiter::tooManyAttempts($key, 5)) {
				return $this->error(
					'Слишком много попыток. Повторите через ' . max(1, RateLimiter::availableIn($key)) . ' сек.',
					array(),
					429
				);
			}

			if (!ReAuth::confirm((string) Request::post('password', ''))) {
				RateLimiter::hit($key, 600);
				AuditLog::record('auth.reauth_failed', array(
					'actor_id' => (int) $user['id'],
					'actor_name' => isset($user['name']) ? (string) $user['name'] : '',
				));
				return $this->error('Неверный пароль', array('password' => 'Проверьте пароль'), 422);
			}

			RateLimiter::clear($key);
			AuditLog::record('auth.reauth_confirmed', array(
				'actor_id' => (int) $user['id'],
				'actor_name' => isset($user['name']) ? (string) $user['name'] : '',
			));

			return $this->success('Пароль подтверждён', array(
				'data' => array('ttl' => ReAuth::DEFAULT_TTL),
			));
		}

		/**
		 * Вход перенесённого пользователя по legacy-паролю md5(md5(pass+salt)).
		 * При успехе перехеширует в bcrypt, очищает legacy-поля и логинит.
		 */
		protected function legacyLogin($identifier, $password, array $options)
		{
			$identifier = trim((string) $identifier);
			if ($identifier === '' || $password === '') {
				return false;
			}

			$user = \App\Adminx\Users\Model::findByIdentifier($identifier);
			if (!$user || empty($user->legacy_password)) {
				return false;
			}

			$salt = (string) (isset($user->legacy_salt) ? $user->legacy_salt : '');
			if (!hash_equals((string) $user->legacy_password, md5(md5($password . $salt)))) {
				return false;
			}

			if ((int) $user->is_active !== 1) {
				return false;
			}

			$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
			\App\Adminx\Users\Model::setPassword($user->id, $hash);

			$arr = (array) $user;
			$arr['password_hash'] = $hash;
			Auth::login($arr, $options);

			return true;
		}

		/** POST /logout — выход (ajax + CSRF). */
		public function logout(array $params = [])
		{
			if (($err = $this->csrfGuard()) !== null) {
				return $err;
			}

			// Adminx и публичный сайт используют связанную личность. Если оставить
			// public-сессию, следующий запрос немедленно восстановит системный вход.
			ReAuth::forget();
			Auth::publicLogout();
			Auth::logout();

			if ($this->wantsJson()) {
				return $this->success('Вы вышли из системы', [
					'redirect' => $this->base() . '/login',
				]);
			}

			$this->redirect($this->base() . '/login');
			return null;
		}
	}
