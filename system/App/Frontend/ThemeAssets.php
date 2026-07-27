<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ThemeAssets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;
	use App\Common\Twig;

	/** Safe public resolver for files declared by a theme manifest. */
	class ThemeAssets
	{
		const MANIFEST = 'theme.json';

		protected static $manifests = array();
		protected static $revisions = array();
		protected static $registeredViewThemes = array();
		protected static $twigHelpersRegistered = false;

		public static function currentTheme()
		{
			$theme = defined('THEME_FOLDER') && THEME_FOLDER !== ''
				? (string) THEME_FOLDER
				: (defined('DEFAULT_THEME_FOLDER') ? (string) DEFAULT_THEME_FOLDER : 'public');

			return self::validTheme($theme) ? $theme : 'public';
		}

		public static function manifest($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			if (!self::validTheme($theme)) {
				return self::defaults('public');
			}

			if (isset(self::$manifests[$theme])) {
				return self::$manifests[$theme];
			}

			$manifest = array();
			$file = self::themeRoot($theme) . '/' . self::MANIFEST;
			if (is_file($file) && is_readable($file) && filesize($file) <= 1048576) {
				$manifest = Json::toArray((string) file_get_contents($file));
			}

			return self::$manifests[$theme] = self::normalizeManifest($manifest, $theme);
		}

		public static function assetUrl($path, $theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			$path = self::normalizePath($path);
			if (!self::validTheme($theme) || $path === '' || !self::publicExtension($path)) {
				return '';
			}

			$file = self::themeRoot($theme) . '/' . $path;
			if (!self::isThemeFile($file, $theme)) {
				return '';
			}

			$key = $theme . '/' . $path;
			if (!isset(self::$revisions[$key])) {
				$checksum = @sha1_file($file);
				self::$revisions[$key] = is_string($checksum) && $checksum !== '' ? substr($checksum, 0, 12) : 'missing';
			}

			$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
			return $base . '/templates/' . rawurlencode($theme) . '/' . self::encodePath($path)
				. '?v=' . self::$revisions[$key];
		}

		public static function styles($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			$html = '';
			foreach (self::manifest($theme)['styles'] as $asset) {
				$url = self::assetUrl($asset['file'], $theme);
				if ($url === '') { continue; }
				$html .= '<link rel="stylesheet" href="' . self::escape($url) . '"';
				if ($asset['media'] !== '') { $html .= ' media="' . self::escape($asset['media']) . '"'; }
				$html .= '>';
			}

			return $html;
		}

		public static function scripts($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			$html = '';
			foreach (self::manifest($theme)['scripts'] as $asset) {
				$url = self::assetUrl($asset['file'], $theme);
				if ($url === '') { continue; }
				$html .= '<script src="' . self::escape($url) . '"';
				if ($asset['module']) { $html .= ' type="module"'; }
				if ($asset['defer']) { $html .= ' defer'; }
				if ($asset['async']) { $html .= ' async'; }
				$html .= '></script>';
			}

			return $html;
		}

		public static function replaceTags($content, $theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			$content = str_replace(
				array('[tag:theme-styles]', '[tag:theme-scripts]'),
				array(self::styles($theme), self::scripts($theme)),
				(string) $content
			);

			return preg_replace_callback('/\[tag:asset:([^\]<>]+)\]/u', function ($match) use ($theme) {
				return self::assetUrl(isset($match[1]) ? $match[1] : '', $theme);
			}, $content);
		}

		public static function normalizeManifest(array $manifest, $theme)
		{
			$defaults = self::defaults($theme);
			$name = trim(isset($manifest['name']) ? (string) $manifest['name'] : '');
			$version = trim(isset($manifest['version']) ? (string) $manifest['version'] : '');
			$pageShell = self::normalizePageShell(isset($manifest['page_shell']) ? $manifest['page_shell'] : '');
			$presentationMode = isset($manifest['presentation_mode'])
				? (string) $manifest['presentation_mode']
				: ($pageShell !== '' ? 'theme' : 'native');
			return array(
				'format' => 'ave-theme-v1',
				'name' => $name !== '' ? mb_substr($name, 0, 160, 'UTF-8') : $defaults['name'],
				'version' => preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,31}$/', $version) ? $version : '1.0.0',
				'styles' => self::normalizeStyles(isset($manifest['styles']) ? $manifest['styles'] : array()),
				'scripts' => self::normalizeScripts(isset($manifest['scripts']) ? $manifest['scripts'] : array()),
				'presentation_mode' => $presentationMode === 'theme' && $pageShell !== '' ? 'theme' : 'native',
				'page_shell' => $pageShell,
				'page_shell_mode' => isset($manifest['page_shell_mode']) && $manifest['page_shell_mode'] === 'replace'
					? 'replace'
					: 'wrap',
				'inherit_rubric_templates' => !array_key_exists('inherit_rubric_templates', $manifest)
					|| !empty($manifest['inherit_rubric_templates']),
				'view_overrides' => self::normalizeViewOverrides(
					isset($manifest['view_overrides']) ? $manifest['view_overrides'] : array()
				),
				'settings' => self::normalizeSettings(
					isset($manifest['settings']) ? $manifest['settings'] : array()
				),
			);
		}

		/** Registers explicit theme-owned Twig overrides for public view namespaces. */
		public static function registerViewOverrides($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			if (!self::validTheme($theme) || isset(self::$registeredViewThemes[$theme])) {
				return isset(self::$registeredViewThemes[$theme]) ? self::$registeredViewThemes[$theme] : array();
			}

			self::registerTwigHelpers();
			$registered = array();
			foreach (self::manifest($theme)['view_overrides'] as $namespace) {
				$path = self::themeRoot($theme) . '/views/' . $namespace;
				if (!is_dir($path)) { continue; }
				Twig::prependPath($path, $namespace);
				$registered[$namespace] = $path;
			}

			self::$registeredViewThemes[$theme] = $registered;
			return $registered;
		}

		public static function normalizePath($path)
		{
			$path = trim(str_replace('\\', '/', (string) $path), '/');
			if ($path === '' || strlen($path) > 500 || strpos($path, "\0") !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) {
				return '';
			}

			foreach (explode('/', $path) as $segment) {
				if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[\/:*?"<>|]/u', $segment)) {
					return '';
				}
			}

			return $path;
		}

		public static function validTheme($theme)
		{
			return (bool) preg_match('/^[a-z][a-z0-9_-]{0,63}$/', (string) $theme);
		}

		public static function publicExtension($path)
		{
			foreach (explode('/', str_replace('\\', '/', (string) $path)) as $segment) {
				if (preg_match('/(?:^|\.)(?:php\d*|phtml|phar|cgi|pl|py|sh|htaccess|user\.ini)(?:\.|$)/i', $segment)) {
					return false;
				}
			}

			$extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
			return in_array($extension, array(
				'css', 'js', 'mjs', 'json', 'map', 'txt', 'xml', 'webmanifest', 'svg',
				'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico',
				'woff', 'woff2', 'ttf', 'otf', 'eot',
			), true);
		}

		public static function textExtension($path)
		{
			return in_array(strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION)), array(
				'css', 'js', 'mjs', 'json', 'map', 'txt', 'xml', 'webmanifest', 'svg',
			), true);
		}

		/** Twig sources are editable theme files, but are never public assets. */
		public static function viewTemplate($path)
		{
			$path = self::normalizePath($path);
			$parts = $path !== '' ? explode('/', $path) : array();
			return count($parts) >= 3
				&& $parts[0] === 'views'
				&& preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $parts[1])
				&& strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'twig';
		}

		public static function editableText($path)
		{
			return self::textExtension($path) || self::viewTemplate($path);
		}

		/** Converts a validated theme view path into a namespaced Twig template. */
		public static function twigTemplate($path)
		{
			$path = self::normalizePageShell($path);
			if ($path === '') { return ''; }

			$parts = explode('/', $path);
			$namespace = $parts[1];
			return '@' . $namespace . '/' . implode('/', array_slice($parts, 2));
		}

		/** Reports whether the active theme explicitly owns a module view. */
		public static function hasViewOverride($namespace, $template, $theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			$namespace = trim((string) $namespace);
			$template = self::normalizePath($template);
			if (!self::validTheme($theme)
				|| !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $namespace)
				|| $template === '') {
				return false;
			}

			if (!in_array($namespace, self::manifest($theme)['view_overrides'], true)) { return false; }
			return self::isThemeFile(self::themeRoot($theme) . '/views/' . $namespace . '/' . $template, $theme);
		}

		/** Reads a declared theme view override for editable template fallbacks. */
		public static function viewOverrideSource($namespace, $template, $theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : self::currentTheme();
			if (!self::hasViewOverride($namespace, $template, $theme)) {
				return '';
			}

			$file = self::themeRoot($theme) . '/views/' . trim((string) $namespace) . '/' . self::normalizePath($template);
			if (!is_readable($file) || filesize($file) > 1048576) {
				return '';
			}

			return (string) file_get_contents($file);
		}

		public static function clearRuntimeCache()
		{
			self::$manifests = array();
			self::$revisions = array();
			self::$registeredViewThemes = array();
		}

		protected static function normalizeStyles($items)
		{
			$out = array();
			foreach (is_array($items) ? $items : array() as $item) {
				$item = is_string($item) ? array('file' => $item) : (is_array($item) ? $item : array());
				$file = self::normalizePath(isset($item['file']) ? $item['file'] : '');
				if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'css') { continue; }
				$out[] = array('file' => $file, 'media' => self::safeAttribute(isset($item['media']) ? $item['media'] : ''));
			}

			return $out;
		}

		protected static function normalizeScripts($items)
		{
			$out = array();
			foreach (is_array($items) ? $items : array() as $item) {
				$item = is_string($item) ? array('file' => $item) : (is_array($item) ? $item : array());
				$file = self::normalizePath(isset($item['file']) ? $item['file'] : '');
				if ($file === '' || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('js', 'mjs'), true)) { continue; }
				$out[] = array(
					'file' => $file,
					'defer' => !empty($item['defer']),
					'async' => !empty($item['async']),
					'module' => !empty($item['module']) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mjs',
				);
			}

			return $out;
		}

		protected static function defaults($theme)
		{
			return array(
				'format' => 'ave-theme-v1',
				'name' => ucfirst(str_replace(array('-', '_'), ' ', (string) $theme)),
				'version' => '1.0.0',
				'styles' => array(),
				'scripts' => array(),
				'presentation_mode' => 'native',
				'page_shell' => '',
				'page_shell_mode' => 'wrap',
				'inherit_rubric_templates' => true,
				'view_overrides' => array(),
				'settings' => array('group' => array(), 'fields' => array()),
			);
		}

		protected static function normalizePageShell($path)
		{
			$path = self::normalizePath($path);
			return self::viewTemplate($path) ? $path : '';
		}

		protected static function normalizeViewOverrides($items)
		{
			$out = array();
			foreach (is_array($items) ? $items : array() as $namespace) {
				$namespace = trim((string) $namespace);
				if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $namespace)) { continue; }
				$out[$namespace] = true;
			}

			return array_keys($out);
		}

		/** Normalizes an optional admin-editable settings schema owned by the theme. */
		protected static function normalizeSettings($settings)
		{
			if (!is_array($settings)) {
				return array('group' => array(), 'fields' => array());
			}

			$group = isset($settings['group']) && is_array($settings['group']) ? $settings['group'] : array();
			$normalizedGroup = array(
				'label' => self::plainText(isset($group['label']) ? $group['label'] : '', 100),
				'description' => self::plainText(isset($group['description']) ? $group['description'] : '', 300),
				'icon' => preg_match('/^ti ti-[a-z0-9-]{1,60}$/', isset($group['icon']) ? (string) $group['icon'] : '')
					? (string) $group['icon']
					: 'ti ti-browser',
			);

			$fields = array();
			foreach (isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : array() as $field) {
				if (!is_array($field) || count($fields) >= 100) { continue; }
				$key = isset($field['key']) ? trim((string) $field['key']) : '';
				$type = isset($field['type']) ? trim((string) $field['type']) : 'string';
				if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key)
					|| !in_array($type, array('string', 'email', 'text', 'int', 'bool', 'select', 'section_order'), true)) {
					continue;
				}

				$options = self::normalizeSettingOptions(isset($field['options']) ? $field['options'] : array());
				if (in_array($type, array('select', 'section_order'), true) && !$options) { continue; }
				$default = array_key_exists('default', $field) ? $field['default'] : ($type === 'bool' ? false : '');
				if ($type === 'section_order') {
					$default = self::normalizeSectionOrder($default, $options);
				} elseif ($type === 'int') {
					$default = (int) $default;
				} elseif ($type === 'bool') {
					$default = !empty($default);
				} else {
					$default = (string) $default;
				}

				$fields[] = array(
					'key' => $key,
					'label' => self::plainText(isset($field['label']) ? $field['label'] : $key, 120),
					'hint' => self::plainText(isset($field['hint']) ? $field['hint'] : '', 300),
					'section' => self::plainText(isset($field['section']) ? $field['section'] : '', 120),
					'type' => $type,
					'default' => $default,
					'options' => $options,
					'min' => isset($field['min']) ? (int) $field['min'] : null,
					'max' => isset($field['max']) ? (int) $field['max'] : null,
					'sort_order' => isset($field['sort_order']) ? (int) $field['sort_order'] : 100,
				);
			}

			return array('group' => $normalizedGroup, 'fields' => $fields);
		}

		protected static function normalizeSettingOptions($options)
		{
			$out = array();
			foreach (is_array($options) ? $options : array() as $value => $label) {
				$value = trim((string) $value);
				if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) || count($out) >= 50) { continue; }
				$out[$value] = self::plainText($label, 120);
			}

			return $out;
		}

		protected static function normalizeSectionOrder($value, array $options)
		{
			$out = array();
			$seen = array();
			foreach (is_array($value) ? $value : array() as $item) {
				$code = is_array($item) && isset($item['code']) ? (string) $item['code'] : (string) $item;
				if (!isset($options[$code]) || isset($seen[$code])) { continue; }
				$visible = !is_array($item) || !array_key_exists('visible', $item) || !empty($item['visible']);
				$out[] = array('code' => $code, 'visible' => $visible);
				$seen[$code] = true;
			}

			foreach ($options as $code => $label) {
				if (!isset($seen[$code])) { $out[] = array('code' => $code, 'visible' => false); }
			}

			return $out;
		}

		protected static function plainText($value, $limit)
		{
			$value = trim(strip_tags((string) $value));
			return function_exists('mb_substr') ? mb_substr($value, 0, (int) $limit, 'UTF-8') : substr($value, 0, (int) $limit);
		}

		protected static function registerTwigHelpers()
		{
			if (self::$twigHelpersRegistered || !Twig::twig()) { return; }
			self::$twigHelpersRegistered = true;
			Twig::twig()->addFunction(new \Twig\TwigFunction('theme_asset', array(self::class, 'assetUrl')));
			Twig::twig()->addFunction(new \Twig\TwigFunction(
				'theme_sysblock',
				array(new BlockRenderer(), 'system'),
				array('is_safe' => array('html'))
			));
			Twig::twig()->addFunction(new \Twig\TwigFunction(
				'theme_breadcrumb',
				array(new BreadcrumbRenderer(), 'render')
			));
			Twig::addGlobal('theme_code', self::currentTheme());
		}

		protected static function themeRoot($theme)
		{
			return rtrim(str_replace('\\', '/', BASEPATH), '/') . '/templates/' . (string) $theme;
		}

		protected static function isThemeFile($file, $theme)
		{
			if (!is_file($file) || is_link($file)) { return false; }
			$root = realpath(self::themeRoot($theme));
			$real = realpath($file);
			if ($root === false || $real === false) { return false; }
			$root = rtrim(str_replace('\\', '/', $root), '/');
			$real = str_replace('\\', '/', $real);
			return strpos($real, $root . '/') === 0;
		}

		protected static function encodePath($path)
		{
			return implode('/', array_map('rawurlencode', explode('/', (string) $path)));
		}

		protected static function safeAttribute($value)
		{
			$value = trim((string) $value);
			return preg_match('/^[A-Za-z0-9 ()_.,:\/-]{0,100}$/', $value) ? $value : '';
		}

		protected static function escape($value)
		{
			return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
