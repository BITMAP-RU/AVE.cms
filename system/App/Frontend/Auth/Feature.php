<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Feature.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicTagRegistry;
	use App\Common\PublicAuthSettings;
	use App\Common\Router;
	use App\Common\Twig;
	use App\Frontend\Auth\Phone\ProviderRegistry as PhoneProviderRegistry;

	class Feature
	{
		protected static $booted = false;
		protected static $config = array();

		public static function boot(array $config = array())
		{
			if (self::$booted) {
				return;
			}

			self::$booted = true;
			Registration\RegistrationGateRegistry::register(new Registration\EmailRegistrationGate());
			$fallback = isset($config['auth_registration']) && is_array($config['auth_registration'])
				? $config['auth_registration'] : array();
			self::$config = PublicAuthSettings::all($fallback);

			Twig::addPath(__DIR__ . '/view', 'system_auth');
			PublicTagRegistry::register('system-auth', '#\[tag:user-panel\]#', array(Tags::class, 'render'), 5, array('cacheable' => false));
			PublicTagRegistry::register('system-auth-legacy', '#\[mod_login\]#', array(Tags::class, 'render'), 5, array(
				'cacheable' => false,
				'inspect' => array(
					'module' => 'auth',
					'title' => 'Регистрация и личный кабинет',
					'edit_url' => 'system/customers',
				),
			));

			self::registerRoutes('login', '/login', array(Controller::class, 'show'), array(Controller::class, 'authorize'));
			self::registerRoutes('logout', '/logout', array(Controller::class, 'logoutGet'), array(Controller::class, 'logout'));
			self::registerRoutes('register', '/register', array(Controller::class, 'registerForm'), array(Controller::class, 'register'));
			self::registerRoutes('verify', '/register/verify', array(Controller::class, 'verifyRegistration'));
			self::registerRoutes('remember', '/remember', array(Controller::class, 'rememberForm'), array(Controller::class, 'remember'));
			self::registerRoutes('reset', '/password/reset', array(Controller::class, 'resetForm'), array(Controller::class, 'reset'));
			self::registerRoutes('overview', '/personal/overview', array(Controller::class, 'overview'));
			self::registerRoutes('profile', '/personal', array(Controller::class, 'profileForm'), array(Controller::class, 'profile'));
			self::registerRoutes('password', '/personal/password', array(Controller::class, 'passwordForm'), array(Controller::class, 'password'));
			Router::get('/auth/oauth/{provider}', array(Controller::class, 'oauthStart'));
			Router::get('/auth/oauth/{provider}/callback', array(Controller::class, 'oauthCallback'));
			Router::post('/auth/phone/request', array(Phone\Controller::class, 'requestCode'));
			Router::post('/auth/phone/verify', array(Phone\Controller::class, 'verifyCode'));
		}

		public static function config()
		{
			return self::$config;
		}

		public static function registrationEnabled()
		{
			return !empty(self::$config['registration_enabled']);
		}

		public static function registrationFormEnabled()
		{
			return self::emailRegistrationEnabled() || !empty(self::phoneRegistrationProviders());
		}

		public static function emailRegistrationEnabled()
		{
			return PublicAuthSettings::allowsRegistrationMethod('email', self::$config)
				&& Registration\RegistrationGateRegistry::get('email') !== null;
		}

		public static function phoneRegistrationEnabled()
		{
			return PublicAuthSettings::allowsRegistrationMethod('phone', self::$config);
		}

		public static function phoneRegistrationProviders()
		{
			if (!self::phoneRegistrationEnabled()) { return array(); }

			return array_values(array_filter(PhoneProviderRegistry::publicItems(), function ($provider) {
				return !empty($provider['allow_registration']);
			}));
		}

		public static function passwordLoginEnabled()
		{
			return PublicAuthSettings::allowsPasswordLogin(self::$config);
		}

		public static function page($key)
		{
			return isset(self::$config['pages'][$key]) ? self::$config['pages'][$key] : array();
		}

		public static function url($key)
		{
			$page = self::page($key);
			return isset($page['path']) ? (string) $page['path'] : '/';
		}

		public static function urls()
		{
			$urls = array();
			foreach (isset(self::$config['pages']) ? self::$config['pages'] : array() as $key => $page) {
				$urls[$key] = isset($page['path']) ? (string) $page['path'] : '/';
			}

			return $urls;
		}

		protected static function registerRoutes($key, $legacyPath, $getHandler, $postHandler = null)
		{
			$paths = array_values(array_unique(array(self::url($key), $legacyPath)));
			foreach ($paths as $path) {
				Router::get($path, $getHandler);
				if ($postHandler !== null) { Router::post($path, $postHandler); }
			}
		}
	}
