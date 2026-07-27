<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/ProviderRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class ProviderRegistry
	{
		protected static $providers = array();

		public static function register($code, $label, $icon, $factory, $configProvider, $configured = null)
		{
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z0-9_-]+$/', $code) || !is_callable($factory) || !is_callable($configProvider)) {
				throw new \InvalidArgumentException('Некорректный OAuth gateway');
			}

			self::$providers[$code] = array(
				'label' => trim((string) $label), 'icon' => (string) $icon,
				'factory' => $factory, 'config' => $configProvider,
				'configured' => is_callable($configured) ? $configured : null,
			);
		}

		public static function get($code, $requireEnabled = true)
		{
			$code = strtolower(trim((string) $code));
			if (!isset(self::$providers[$code])) { throw new \InvalidArgumentException('Неизвестный способ входа.'); }
			$config = self::config($code);
			if ($requireEnabled && (empty($config['enabled']) || !self::configured($code, $config))) {
				throw new \InvalidArgumentException('Этот способ входа не настроен.');
			}

			$provider = call_user_func(self::$providers[$code]['factory'], $config);
			if (!$provider instanceof ProviderInterface) { throw new \RuntimeException('Некорректный OAuth provider'); }
			return $provider;
		}

		public static function publicItems()
		{
			$items = array();
			foreach (self::$providers as $code => $definition) {
				$config = self::config($code);
				if (empty($config['enabled']) || !self::configured($code, $config)) { continue; }
				$items[] = array(
					'code' => $code, 'label' => $definition['label'],
					'url' => '/auth/oauth/' . $code,
					'icon' => $definition['icon'],
				);
			}

			return $items;
		}

		public static function configured($code, array $config)
		{
			$code = strtolower(trim((string) $code));
			if (!isset(self::$providers[$code])) { return false; }
			$callback = self::$providers[$code]['configured'];
			return $callback ? (bool) call_user_func($callback, $config) : !empty($config['client_id']);
		}

		public static function config($code)
		{
			$code = strtolower(trim((string) $code));
			if (!isset(self::$providers[$code])) { return array(); }
			$config = call_user_func(self::$providers[$code]['config']);
			return is_array($config) ? $config : array();
		}

		public static function codes()
		{
			return array_keys(self::$providers);
		}
	}
