<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/AdminLocale.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Language;
	use App\Common\Session;
	use App\Common\Settings;

	/**
	 * Независимая локаль интерфейса Adminx.
	 *
	 * Публичный сайт остаётся одноязычным. Выбор панели хранится отдельно в
	 * admin_language и не изменяет PUBLIC_LANGUAGE/current_language.
	 */
	class AdminLocale
	{
		const DEFAULT_LOCALE = 'ru';
		const SESSION_KEY = 'admin_language';

		protected function __construct()
		{
		}

		public static function current()
		{
			$locale = strtolower(trim((string) Session::get(self::SESSION_KEY)));
			if (self::supports($locale)) {
				return $locale;
			}

			try {
				$locale = strtolower(trim((string) Settings::get('admin_default_language', self::DEFAULT_LOCALE)));
			} catch (\Throwable $e) {
				$locale = self::DEFAULT_LOCALE;
			}

			return self::supports($locale) ? $locale : self::DEFAULT_LOCALE;
		}

		public static function set($locale)
		{
			$locale = strtolower(trim((string) $locale));
			if (!self::supports($locale)) {
				return false;
			}

			Session::set(self::SESSION_KEY, $locale);
			return true;
		}

		public static function supports($locale)
		{
			$locale = strtolower(trim((string) $locale));
			return $locale !== '' && isset(self::locales()[$locale]);
		}

		/**
		 * Доступные языки определяются физическими словарями каркаса.
		 */
		public static function locales()
		{
			$labels = array(
				'ru' => 'Русский',
				'en' => 'English',
			);
			$locales = array();
			$root = rtrim(ADMINX_PATH, '/\\') . DS . 'language';
			$directories = is_dir($root) ? glob($root . DS . '*', GLOB_ONLYDIR) : array();

			foreach ((array) $directories as $directory) {
				$code = strtolower((string) basename($directory));
				if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $code)
					|| !glob($directory . DS . '*.xml')) {
					continue;
				}

				$locales[$code] = isset($labels[$code]) ? $labels[$code] : strtoupper($code);
			}

			if (!isset($locales[self::DEFAULT_LOCALE])) {
				$locales[self::DEFAULT_LOCALE] = $labels[self::DEFAULT_LOCALE];
			}

			return $locales;
		}

		/**
		 * Загрузить словари каркаса и зарегистрированных admin-модулей.
		 *
		 * Language сохраняет первый загруженный ключ, поэтому сначала идёт
		 * выбранная локаль, затем русский fallback только для отсутствующих фраз.
		 */
		public static function boot(array $modules = array())
		{
			Language::reset();
			$locale = self::current();
			$selected = self::readLocale($locale, $modules);
			$source = $locale === self::DEFAULT_LOCALE
				? $selected
				: self::readLocale(self::DEFAULT_LOCALE, $modules);

			Language::merge($selected);
			if ($locale !== self::DEFAULT_LOCALE) {
				Language::merge($source);
			}

			Language::$lang = $locale;
			Language::setSourceTranslations(self::buildSourceTranslations($locale, $source, $selected));
		}

		public static function text($key, $fallback = '')
		{
			return Language::has($key) ? (string) Language::get($key) : (string) $fallback;
		}


		/**
		 * Перевести статичный фрагмент шаблона или служебное сообщение.
		 *
		 * Метод вызывается только для исходного текста интерфейса. Динамические
		 * названия документов, товаров и другие пользовательские данные через
		 * него не проходят.
		 */
		public static function translateMarkup($markup)
		{
			return Language::translateSource((string) $markup);
		}


		public static function sourceTranslations()
		{
			return Language::sourceTranslations();
		}


		/**
		 * Небольшой словарь стабильных ключей, которые читает общий JavaScript.
		 */
		public static function clientDictionary()
		{
			$keys = array(
				'btn_cancel',
				'btn_close',
				'btn_confirm',
				'btn_logout',
				'confirm_action',
				'confirm_title',
				'field_current_password',
				'help_load_error',
				'help_loading',
				'help_not_found',
				'help_title',
				'logout_message',
				'logout_title',
				'reauth_error',
				'reauth_hint',
				'reauth_reason',
				'reauth_subtitle',
				'reauth_title',
				'server_unavailable',
				'tag_copied',
			);
			$dictionary = array();

			foreach ($keys as $key) {
				if (Language::has($key)) {
					$dictionary[$key] = (string) Language::get($key);
				}
			}

			return $dictionary;
		}


		/**
		 * Исходные фразы только из client.xml загруженных модулей.
		 *
		 * Полный серверный словарь не встраивается в каждую HTML-страницу.
		 */
		public static function clientSourceTranslations(array $modules = array())
		{
			$locale = self::current();
			if ($locale === self::DEFAULT_LOCALE) {
				return array();
			}

			$source = self::readClientLocale(self::DEFAULT_LOCALE, $modules);
			$target = self::readClientLocale($locale, $modules);
			return self::buildSourceTranslations($locale, $source, $target);
		}

		/**
		 * Навигация переводится по стабильному коду, а не исходной подписи.
		 */
		public static function translateNavigation(array $items)
		{
			foreach ($items as &$item) {
				$code = isset($item['code']) ? (string) $item['code'] : '';
				if ($code !== '') {
					$fallback = isset($item['label']) ? $item['label'] : $code;
					$item['label'] = self::text('nav_' . $code, self::translateMarkup($fallback));
				}

				$group = isset($item['group']) ? (string) $item['group'] : '';
				if ($group !== '') {
					$item['group'] = self::text('nav_group_' . self::groupKey($group), self::translateMarkup($group));
				}
			}

			unset($item);

			return $items;
		}

		protected static function buildSourceTranslations($locale, array $source, array $target)
		{
			if ($locale === self::DEFAULT_LOCALE) {
				return array();
			}

			$translations = array();

			foreach ($source as $key => $value) {
				if ($value === '' || !isset($target[$key]) || $target[$key] === '' || $target[$key] === $value) {
					continue;
				}

				if (!isset($translations[$value])) {
					$translations[$value] = $target[$key];
				}
			}

			return $translations;
		}


		protected static function readLocale($locale, array $modules)
		{
			$directories = array(
				rtrim(ADMINX_PATH, '/\\') . DS . 'language' . DS . $locale,
			);

			foreach ($modules as $module) {
				$basePath = isset($module['base_path']) ? (string) $module['base_path'] : '';
				if ($basePath !== '') {
					$directories[] = rtrim($basePath, '/\\') . DS . 'language' . DS . $locale;
				}
			}

			$phrases = array();
			foreach ($directories as $directory) {
				$files = is_dir($directory) ? glob(rtrim($directory, '/\\') . DS . '*.xml') : array();
				sort($files, SORT_STRING);

				foreach ($files as $file) {
					$xml = @simplexml_load_file($file);
					if (!$xml) {
						continue;
					}

					foreach ($xml as $phrase) {
						$key = trim((string) $phrase['data']);
						if ($key !== '' && !isset($phrases[$key])) {
							$phrases[$key] = (string) $phrase;
						}
					}
				}
			}

			return $phrases;
		}


		protected static function readClientLocale($locale, array $modules)
		{
			$files = array(
				rtrim(ADMINX_PATH, '/\\') . DS . 'language' . DS . $locale . DS . 'client.xml',
			);

			foreach ($modules as $module) {
				$basePath = isset($module['base_path']) ? (string) $module['base_path'] : '';
				if ($basePath !== '') {
					$files[] = rtrim($basePath, '/\\') . DS . 'language' . DS . $locale . DS . 'client.xml';
				}
			}

			$phrases = array();
			foreach ($files as $file) {
				if (!is_file($file)) {
					continue;
				}

				$xml = @simplexml_load_file($file);
				if (!$xml) {
					continue;
				}

				foreach ($xml as $phrase) {
					$key = trim((string) $phrase['data']);
					if ($key !== '' && !isset($phrases[$key])) {
						$phrases[$key] = (string) $phrase;
					}
				}
			}

			return $phrases;
		}

		protected static function groupKey($group)
		{
			$map = array(
				'Обзор' => 'overview',
				'Контент' => 'content',
				'Каталог' => 'catalog',
				'Продажи' => 'sales',
				'Аналитика' => 'analytics',
				'Интеграции' => 'integrations',
				'Система' => 'system',
				'Справка' => 'help',
				'fields' => 'fields',
				'modules_repository' => 'modules_repository',
				'site_readiness' => 'site_readiness',
				'source' => 'source',
			);

			return isset($map[$group]) ? $map[$group] : strtolower(preg_replace('/[^a-z0-9_-]+/i', '_', $group));
		}
	}
