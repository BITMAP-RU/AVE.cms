<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Session.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use Exception;
	use App\Helpers\Request;
	use RuntimeException;

	class Session
	{
		protected static $options  = [];
		protected static $adapter;
		protected static $instance;

		protected function __construct(array $options = [])
		{
			$options += [
				'cache_limiter'   => 'nocache',
				'cookie_httponly' => 1,
				'cookie_samesite' => 'Lax',
				'cookie_secure'   => Request::isHttps() ? 1 : 0,
				'use_trans_sid'   => 0,
				'use_cookies'     => 1,
				'use_only_cookies' => 1,
				'lazy_write'      => 1,
				'use_strict_mode' => 1,
			];

			self::setOptions($options);
			self::setDomain();

			$handler = strtolower((string) SESSION_SAVE_HANDLER);
			$adapters = array('mysql' => 'DB', 'db' => 'DB', 'files' => 'Files', 'file' => 'Files', 'native' => 'Native');
			$adapter = isset($adapters[$handler]) ? $adapters[$handler] : 'DB';
			self::$adapter = 'App\Common\Session\Session' . $adapter;

			self::start();
		}

		public static function init()
		{
			if (null === self::$instance) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public static function isActive()
		{
			return session_status() === PHP_SESSION_ACTIVE;
		}

		public static function start()
		{
			try {
				self::$adapter::init();
				self::setHandler();

				$success = @session_start();

				if (!$success) {
					$last  = error_get_last();
					$error = $last ? $last['message'] : 'Unknown error';
					throw new RuntimeException($error);
				}
			} catch (Exception $e) {
				throw new RuntimeException('Failed to start session: ' . $e->getMessage(), 500);
			}

			return true;
		}

		public static function regenerateId($delete_old = false)
		{
			if (self::isActive() && !headers_sent()) {
				session_regenerate_id($delete_old);
			}
		}

		public static function getId()
		{
			if (!session_id()) {
				self::start();
			}

			return session_id() ?: null;
		}

		public static function setId($id)
		{
			session_id($id);
			return self::$instance;
		}

		public static function getName()
		{
			return session_name() ?: null;
		}

		public static function setName($name)
		{
			session_name($name);
			return self::$instance;
		}

		/**
		 * Проверить наличие одного или нескольких ключей в сессии
		 */
		public static function check(...$keys)
		{
			foreach ($keys as $argument) {
				foreach ((array) $argument as $key) {
					if (!isset($_SESSION[(string) $key])) {
						return false;
					}
				}
			}

			return true;
		}

		public static function get($key)
		{
			return isset($_SESSION[(string) $key]) ? $_SESSION[(string) $key] : null;
		}

		public static function set($key, $value)
		{
			$_SESSION[(string) $key] = $value;
		}

		/**
		 * Удалить один или несколько ключей из сессии
		 */
		public static function del(...$keys)
		{
			foreach ($keys as $argument) {
				foreach ((array) $argument as $key) {
					unset($_SESSION[(string) $key]);
				}
			}
		}

		public static function setDomain($cookie_domain = '')
		{
			// Кросс-доменная (на поддомены) cookie ставится ТОЛЬКО при явном
			// COOKIE_DOMAIN или переданном аргументе. По умолчанию — host-only:
			// атрибут Domain не выставляется, cookie привязана строго к текущему
			// хосту. Это исключает коллизии сессии/auth между родительским доменом
			// и поддоменами (например, preview.example.org и .example.org).
			if ($cookie_domain === '' && defined('COOKIE_DOMAIN') && COOKIE_DOMAIN !== '') {
				$cookie_domain = COOKIE_DOMAIN;
			}

			if ($cookie_domain !== '') {
				$cookie_domain = ltrim($cookie_domain, '.');

				if (strpos($cookie_domain, 'www.') === 0) {
					$cookie_domain = substr($cookie_domain, 4);
				}

				$parts         = explode(':', $cookie_domain);
				$cookie_domain = '.' . $parts[0];

				if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
					self::setOption('cookie_domain', $cookie_domain);
				}
			} else {
				// host-only: явно очищаем Domain (на случай предустановленного ini)
				self::setOption('cookie_domain', '');
			}

			Registry::set('cookie_domain', $cookie_domain);
			self::setOption('cookie_path', ABS_PATH);
		}

		public static function setOptions(array $options)
		{
			if (headers_sent() || PHP_SESSION_ACTIVE === session_status()) {
				return;
			}

			$allowed = [
				'save_path'                 => true,
				'name'                      => true,
				'save_handler'              => true,
				'gc_probability'            => true,
				'gc_divisor'                => true,
				'gc_maxlifetime'            => true,
				'serialize_handler'         => true,
				'cookie_lifetime'           => true,
				'cookie_path'               => true,
				'cookie_domain'             => true,
				'cookie_secure'             => true,
				'cookie_httponly'           => true,
				'use_strict_mode'           => true,
				'use_cookies'               => true,
				'use_only_cookies'          => true,
				'cookie_samesite'           => true,
				'referer_check'             => true,
				'cache_limiter'             => true,
				'cache_expire'              => true,
				'use_trans_sid'             => true,
				'trans_sid_tags'            => true,
				'trans_sid_hosts'           => true,
				'sid_length'                => true,
				'sid_bits_per_character'    => true,
				'upload_progress.enabled'   => true,
				'upload_progress.cleanup'   => true,
				'upload_progress.prefix'    => true,
				'upload_progress.name'      => true,
				'upload_progress.freq'      => true,
				'upload_progress.min-freq'  => true,
				'lazy_write'                => true,
			];

			foreach ($options as $key => $value) {
				if (is_array($value)) {
					foreach ($value as $key2 => $value2) {
						$ckey = "{$key}.{$key2}";
						if (isset($allowed[$ckey])) {
							self::setOption($ckey, $value2);
						}
					}
				} elseif (isset($allowed[$key])) {
					self::setOption($key, $value);
				}
			}
		}

		protected static function setOption($key, $value)
		{
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			} else {
				$value = (string) $value;
			}

			self::$options[$key] = $value;
			ini_set("session.{$key}", $value);
		}

		public static function setHandler()
		{
			session_set_save_handler(
				[self::$adapter, 'open'],
				[self::$adapter, 'close'],
				[self::$adapter, 'read'],
				[self::$adapter, 'write'],
				[self::$adapter, 'destroy'],
				[self::$adapter, 'gc']
			);
		}

		// ------------------------------------------------------------------ //
		//  CSRF
		// ------------------------------------------------------------------ //

		/**
		 * Вернуть CSRF-токен текущей сессии (создаёт, если не существует).
		 */
		public static function csrfToken()
		{
			if (!self::check('_csrf_token')) {
				self::set('_csrf_token', bin2hex(random_bytes(32)));
			}

			return self::get('_csrf_token');
		}

		/**
		 * Проверить CSRF-токен. Бросает RuntimeException при несовпадении.
		 *
		 * @param  string $token  Токен из запроса ($_POST['_csrf'] или заголовка)
		 * @return true
		 * @throws \RuntimeException
		 */
		public static function verifyCsrf($token)
		{
			$stored = self::get('_csrf_token');

			if (!$stored || !hash_equals($stored, (string)$token)) {
				throw new \RuntimeException('CSRF token mismatch', 419);
			}

			return true;
		}

		/**
		 * Сгенерировать новый CSRF-токен (после успешного submit формы).
		 */
		public static function regenerateCsrf()
		{
			self::set('_csrf_token', bin2hex(random_bytes(32)));
			return self::get('_csrf_token');
		}

		// ------------------------------------------------------------------ //

		public static function close()
		{
			if (self::isActive()) {
				session_write_close();
			}

			return true;
		}

		public static function destroy()
		{
			if (self::isActive()) {
				session_unset();
				session_destroy();
				$_SESSION = [];
			}
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
