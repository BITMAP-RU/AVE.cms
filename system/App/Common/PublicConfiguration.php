<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicConfiguration.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Defines the compatibility constants shared by the public shell and native domains. */
	class PublicConfiguration
	{
		protected static $values;

		public static function load($file = null)
		{
			$isDefault = $file === null;
			if ($isDefault && self::$values !== null) {
				return self::$values;
			}

			$file = $isDefault ? BASEPATH . '/configs/public.config.php' : (string) $file;
			$config = is_file($file) ? include $file : array();
			if (!is_array($config)) {
				$config = array();
			}

			$databasePrefix = DatabaseConfiguration::prefix();
			self::definePrefix('CONTENT_PREFIX', $config, 'content_table_prefix', $databasePrefix);
			self::definePrefix('CATALOG_PREFIX', $config, 'catalog_table_prefix', $databasePrefix);
			self::definePrefix('MODULE_DATA_PREFIX', $config, 'module_table_prefix', $databasePrefix);
			self::definePrefix('BASKET_DATA_PREFIX', $config, 'basket_table_prefix', $databasePrefix);
			self::definePrefix('CONTACTS_DATA_PREFIX', $config, 'contacts_table_prefix', $databasePrefix);
			self::definePrefix('SYSTEM_DATA_PREFIX', $config, 'system_table_prefix', $databasePrefix);
			self::definePrefix('PUBLIC_USER_PREFIX', $config, 'public_user_table_prefix', $databasePrefix, '_public');
			self::definePrefix('PUBLIC_SHELL_PREFIX', $config, 'public_shell_table_prefix', $databasePrefix, '_public');

			$language = isset($config['public_language']) ? strtolower((string) $config['public_language']) : 'ru';
			if (!preg_match('/^[a-z]{2,8}$/', $language)) {
				$language = 'ru';
			}

			defined('PUBLIC_LANGUAGE') || define('PUBLIC_LANGUAGE', $language);
			defined('DEFAULT_LANGUAGE') || define('DEFAULT_LANGUAGE', PUBLIC_LANGUAGE);

			if ($isDefault) {
				self::$values = $config;
			}

			return $config;
		}

		public static function all()
		{
			return self::load();
		}

		public static function value($key, $default = null)
		{
			$config = self::load();
			return array_key_exists((string) $key, $config) ? $config[(string) $key] : $default;
		}

		/** Return a validated data-domain prefix from the request-scoped config. */
		public static function prefix($domain)
		{
			self::load();
			$constants = array(
				'content' => 'CONTENT_PREFIX',
				'catalog' => 'CATALOG_PREFIX',
				'module' => 'MODULE_DATA_PREFIX',
				'basket' => 'BASKET_DATA_PREFIX',
				'contacts' => 'CONTACTS_DATA_PREFIX',
				'system' => 'SYSTEM_DATA_PREFIX',
				'public_user' => 'PUBLIC_USER_PREFIX',
				'public_shell' => 'PUBLIC_SHELL_PREFIX',
			);
			$domain = (string) $domain;
			if (!isset($constants[$domain])) {
				throw new \InvalidArgumentException('Unknown public data domain: ' . $domain);
			}

			return (string) constant($constants[$domain]);
		}

		protected static function definePrefix($constant, array $config, $key, $default, $suffix = '')
		{
			$prefix = (isset($config[$key]) ? (string) $config[$key] : (string) $default) . (string) $suffix;
			if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
				throw new \RuntimeException('Invalid public data prefix: ' . $key);
			}

			defined($constant) || define($constant, $prefix);
		}
	}
