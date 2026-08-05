<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/PublicViewTemplates.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\ViewOverrideEditor;
	use App\Frontend\ThemeAssets;

	class PublicViewTemplates
	{
		public static function all()
		{
			$out = self::contentEditor()->all();
			foreach (self::themeEditors() as $editor) { $out = array_merge($out, $editor->all()); }
			return $out;
		}

		public static function one($code)
		{
			list($editor, $resolved) = self::resolve($code);
			return $editor->one($resolved);
		}

		public static function save($code, $content, $authorId)
		{
			list($editor, $resolved) = self::resolve($code, true);
			return $editor->save($resolved, $content, $authorId, 'public-site-view');
		}

		public static function deleteOverride($code, $authorId)
		{
			list($editor, $resolved) = self::resolve($code, true);
			return $editor->deleteOverride($resolved, $authorId);
		}

		public static function lint($content)
		{
			return self::contentEditor()->lint($content, 'public-site');
		}

		protected static function resolve($code, $strict = false)
		{
			$code = trim((string) $code);
			$editors = array_merge(array(self::contentEditor()), self::themeEditors());
			foreach ($editors as $editor) {
				foreach ($editor->all() as $item) {
					if ($item['code'] === $code) { return array($editor, $code); }
				}
			}

			if ($strict) { throw new \InvalidArgumentException('Публичный шаблон сайта не найден'); }
			return array(self::contentEditor(), 'content-list');
		}

		protected static function contentEditor()
		{
			return new ViewOverrideEditor('content_public', BASEPATH . '/system/App/Frontend/view/content', 'system/App/Frontend/view/content', array(
				'content-list' => array(
					'file' => 'list.twig', 'group' => 'Общие компоненты', 'title' => 'Список материалов',
					'description' => 'Резервный вывод коллекции документов, когда не назначено отдельное представление.',
					'icon' => 'ti ti-list-details',
					'variables' => array(array('rubric_id', 'ID рубрики'), array('items', 'Материалы'), array('total', 'Всего материалов'), array('page', 'Текущая страница'), array('pages', 'Всего страниц'), array('pagination', 'Ссылки пагинации')),
				),
			));
		}

		protected static function themeEditors()
		{
			$theme = ThemeAssets::currentTheme();
			$manifest = ThemeAssets::manifest($theme);
			$owned = array('products_public', 'search_public', 'system_auth', 'system_basket', 'quiz_public', 'widgets_public', 'content_public');
			$out = array();
			foreach (isset($manifest['view_overrides']) ? $manifest['view_overrides'] : array() as $namespace) {
				if (in_array($namespace, $owned, true)) { continue; }
				$definitions = self::themeDefinitions($theme, $namespace);
				if (!$definitions) { continue; }
				$out[] = new ViewOverrideEditor($namespace, '', '', $definitions);
			}

			return $out;
		}

		protected static function themeDefinitions($theme, $namespace)
		{
			$root = BASEPATH . '/templates/' . $theme . '/views/' . $namespace;
			if (!is_dir($root)) { return array(); }
			$files = array();
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension()) !== 'twig') { continue; }
				$relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
				if (ThemeAssets::normalizePath($relative) === '') { continue; }
				$files[] = $relative;
			}

			sort($files, SORT_NATURAL | SORT_FLAG_CASE);
			$out = array();
			foreach ($files as $file) {
				$code = 'theme-' . substr(sha1($namespace . '/' . $file), 0, 12);
				$name = pathinfo($file, PATHINFO_FILENAME);
				$title = self::title($name);
				$out[$code] = array(
					'file' => $file, 'group' => 'Компоненты темы · ' . $namespace, 'title' => $title,
					'description' => 'Файл активной темы: ' . $file, 'icon' => $name === 'page' ? 'ti ti-browser' : 'ti ti-template',
					'variables' => array(),
				);
			}

			return $out;
		}

		protected static function title($name)
		{
			$labels = array(
				'page' => 'Оболочка страницы', 'breadcrumbs' => 'Хлебные крошки', 'home' => 'Главная страница',
				'not-found' => 'Страница 404', 'article' => 'Статья', 'news-list' => 'Список новостей',
				'contacts' => 'Контакты', 'account-nav' => 'Навигация кабинета', 'feature-strip' => 'Сервисная полоса',
				'exhibition' => 'Выставка', 'exhibitions' => 'Список выставок', 'info' => 'Информационная страница',
				'podbor' => 'Подбор товара', 'sfr' => 'Страница СФР',
			);
			return isset($labels[$name]) ? $labels[$name] : ucfirst(str_replace(array('-', '_'), ' ', (string) $name));
		}
	}
