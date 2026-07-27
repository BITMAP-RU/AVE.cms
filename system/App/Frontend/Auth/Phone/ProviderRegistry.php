<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Phone/ProviderRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Phone;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class ProviderRegistry
	{
		protected static $providers = array();

		public static function register(ProviderInterface $provider)
		{
			$code = strtolower(trim((string) $provider->code()));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,23}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код SMS-провайдера.');
			}

			self::$providers[$code] = $provider;
		}

		public static function get($code = '')
		{
			$code = strtolower(trim((string) $code));
			if ($code !== '') {
				return isset(self::$providers[$code]) ? self::$providers[$code] : null;
			}

			foreach (self::$providers as $provider) {
				if ($provider->enabled() && $provider->configured()) {
					return $provider;
				}
			}

			return null;
		}

		public static function publicItems()
		{
			$items = array();
			foreach (self::$providers as $provider) {
				if (!$provider->enabled() || !$provider->configured()) {
					continue;
				}

				$options = self::normalizeOptions($provider->options());
				$items[] = array(
					'code' => (string) $provider->code(),
					'label' => (string) $provider->label(),
					'allow_registration' => !empty($options['allow_registration']),
					'code_length' => (int) $options['code_length'],
					'ttl' => (int) $options['ttl'],
					'resend_after' => (int) $options['resend_after'],
				);
			}

			return $items;
		}

		public static function options(ProviderInterface $provider)
		{
			return self::normalizeOptions($provider->options());
		}

		protected static function normalizeOptions(array $options)
		{
			return array(
				'allow_registration' => !empty($options['allow_registration']),
				'code_length' => max(4, min(8, (int) (isset($options['code_length']) ? $options['code_length'] : 6))),
				'ttl' => max(60, min(900, (int) (isset($options['ttl']) ? $options['ttl'] : 300))),
				'resend_after' => max(30, min(300, (int) (isset($options['resend_after']) ? $options['resend_after'] : 60))),
				'max_attempts' => max(3, min(10, (int) (isset($options['max_attempts']) ? $options['max_attempts'] : 5))),
				'daily_limit' => max(1, min(50, (int) (isset($options['daily_limit']) ? $options['daily_limit'] : 10))),
			);
		}
	}
