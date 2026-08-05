<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/ViewOverrideEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Twig;
	use App\Adminx\Themes\Model;
	use App\Frontend\ThemeAssets;
	use App\Helpers\Json;

	/** Safe editor for a fixed set of public Twig view overrides. */
	class ViewOverrideEditor
	{
		protected $namespace;
		protected $fallbackRoot;
		protected $fallbackPrefix;
		protected $definitions;

		public function __construct($namespace, $fallbackRoot, $fallbackPrefix, array $definitions)
		{
			$namespace = trim((string) $namespace);
			if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $namespace)) {
				throw new \InvalidArgumentException('Некорректное пространство публичных шаблонов');
			}

			$this->namespace = $namespace;
			$this->fallbackRoot = rtrim(str_replace('\\', '/', (string) $fallbackRoot), '/');
			$this->fallbackPrefix = trim(str_replace('\\', '/', (string) $fallbackPrefix), '/');
			$this->definitions = $this->normalizeDefinitions($definitions);
		}

		public function all()
		{
			$out = array();
			foreach ($this->definitions as $code => $definition) {
				$out[] = $this->describe($code, $definition);
			}

			return $out;
		}

		public function one($code)
		{
			$code = trim((string) $code);
			if ($code === '' || !isset($this->definitions[$code])) {
				$code = (string) key($this->definitions);
			}

			return $this->describe($code, $this->definitions[$code], true);
		}

		public function save($code, $content, $authorId, $action = 'public-view')
		{
			$this->assertCode($code);
			$item = $this->one($code);
			$syntax = $this->lint($content, $code);
			if (empty($syntax['ok'])) {
				throw new \InvalidArgumentException($syntax['message']);
			}

			Model::saveFile($item['theme'], $item['theme_path'], (string) $content, (int) $authorId, $action);
			$this->ensureNamespace($item['theme'], (int) $authorId);
			return $this->one($item['code']);
		}

		public function deleteOverride($code, $authorId)
		{
			$this->assertCode($code);
			$item = $this->one($code);
			if (empty($item['has_fallback'])) {
				throw new \RuntimeException('Для этого шаблона нет резервной версии');
			}

			if (empty($item['has_theme_file'])) {
				throw new \RuntimeException('Переопределение темы не найдено');
			}

			Model::deletePath($item['theme'], $item['theme_path'], (int) $authorId);
			return $this->one($item['code']);
		}

		public function lint($content, $code = 'template')
		{
			$content = (string) $content;
			if (trim($content) === '') {
				return array('ok' => false, 'message' => 'Twig-шаблон не может быть пустым');
			}

			try {
				Twig::twig()->createTemplate($content, 'public_view_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $code));
			} catch (\Throwable $e) {
				return array('ok' => false, 'message' => 'Ошибка Twig: ' . $e->getMessage());
			}

			return array('ok' => true, 'message' => 'Twig-синтаксис корректен');
		}

		protected function describe($code, array $definition, $includeContent = false)
		{
			$theme = ThemeAssets::currentTheme();
			$themePath = 'views/' . $this->namespace . '/' . $definition['file'];
			$themeFile = BASEPATH . '/templates/' . $theme . '/' . $themePath;
			$fallbackFile = $this->fallbackRoot !== '' ? $this->fallbackRoot . '/' . $definition['file'] : '';
			$manifest = ThemeAssets::manifest($theme);
			$namespaceEnabled = in_array($this->namespace, isset($manifest['view_overrides']) ? $manifest['view_overrides'] : array(), true);
			$hasThemeFile = is_file($themeFile) && is_readable($themeFile);
			$hasFallback = $fallbackFile !== '' && is_file($fallbackFile) && is_readable($fallbackFile);
			$activeOverride = $namespaceEnabled && $hasThemeFile;
			$source = $activeOverride ? 'theme' : ($hasThemeFile ? 'theme_disabled' : ($hasFallback ? 'fallback' : 'missing'));
			$item = array_merge($definition, array(
				'code' => $code,
				'namespace' => $this->namespace,
				'theme' => $theme,
				'theme_path' => $themePath,
				'fallback_path' => $this->fallbackPrefix !== '' ? $this->fallbackPrefix . '/' . $definition['file'] : '',
				'has_theme_file' => $hasThemeFile,
				'has_fallback' => $hasFallback,
				'can_delete_override' => $hasThemeFile && $hasFallback,
				'namespace_enabled' => $namespaceEnabled,
				'active_override' => $activeOverride,
				'source' => $source,
				'source_label' => $activeOverride ? 'Активная тема' : ($hasThemeFile ? 'Файл темы отключён' : ($hasFallback ? 'Резерв компонента' : 'Файл отсутствует')),
				'modified_label' => $hasThemeFile ? date('d.m.Y H:i', (int) filemtime($themeFile)) : ($hasFallback ? date('d.m.Y H:i', (int) filemtime($fallbackFile)) : ''),
			));

			if ($includeContent) {
				$sourceFile = $hasThemeFile ? $themeFile : $fallbackFile;
				$item['content'] = $sourceFile !== '' && is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
				$item['fallback_content'] = $hasFallback ? (string) file_get_contents($fallbackFile) : '';
			}

			return $item;
		}

		protected function ensureNamespace($theme, $authorId)
		{
			$manifest = ThemeAssets::manifest($theme);
			$overrides = isset($manifest['view_overrides']) && is_array($manifest['view_overrides'])
				? $manifest['view_overrides']
				: array();
			if (in_array($this->namespace, $overrides, true)) { return; }

			$overrides[] = $this->namespace;
			$manifest['view_overrides'] = array_values(array_unique($overrides));
			Model::saveFile(
				$theme,
				ThemeAssets::MANIFEST,
				Json::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
				(int) $authorId,
				'public-view-namespace'
			);
		}

		protected function assertCode($code)
		{
			if (!isset($this->definitions[trim((string) $code)])) {
				throw new \InvalidArgumentException('Публичный шаблон не найден');
			}
		}

		protected function normalizeDefinitions(array $definitions)
		{
			$out = array();
			foreach ($definitions as $code => $definition) {
				$code = trim((string) $code);
				$file = isset($definition['file']) ? ThemeAssets::normalizePath($definition['file']) : '';
				if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code) || $file === '' || substr($file, -5) !== '.twig') {
					throw new \InvalidArgumentException('Некорректное описание публичного шаблона');
				}

				$definition['file'] = $file;
				$definition['title'] = isset($definition['title']) ? (string) $definition['title'] : $code;
				$definition['description'] = isset($definition['description']) ? (string) $definition['description'] : '';
				$definition['icon'] = isset($definition['icon']) ? (string) $definition['icon'] : 'ti ti-template';
				$definition['group'] = isset($definition['group']) ? (string) $definition['group'] : '';
				$definition['variables'] = isset($definition['variables']) && is_array($definition['variables']) ? $definition['variables'] : array();
				$out[$code] = $definition;
			}

			if (!$out) { throw new \InvalidArgumentException('Реестр публичных шаблонов пуст'); }
			return $out;
		}
	}
