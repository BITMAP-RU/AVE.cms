<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Themes/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Themes;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Common\SystemTables;
	use App\Frontend\ThemeAssets;
	use App\Frontend\ThemeSettings;
	use App\Helpers\File;
	use App\Helpers\Json;
	use DB;

	class Model
	{
		const MAX_TEXT_BYTES = 2097152;
		const MAX_FILE_BYTES = 10485760;
		const MAX_ARCHIVE_BYTES = 52428800;
		const MAX_ARCHIVE_FILES = 2000;
		const MAX_ARCHIVE_UNPACKED = 104857600;

		protected static $hiddenDirectories = array('include', 'templates', 'lang', '.tmb', '.quarantine');

		public static function themes()
		{
			$items = array();
			$root = self::themesRoot();
			if (!is_dir($root)) { return $items; }
			foreach (new \DirectoryIterator($root) as $entry) {
				if ($entry->isDot() || !$entry->isDir() || $entry->isLink()) { continue; }
				$code = $entry->getFilename();
				if (!ThemeAssets::validTheme($code)) { continue; }
				$items[] = self::theme($code);
			}

			usort($items, function ($left, $right) {
				if ($left['active'] !== $right['active']) { return $left['active'] ? -1 : 1; }
				return strnatcasecmp($left['name'], $right['name']);
			});
			return $items;
		}

		public static function theme($code)
		{
			$code = self::assertTheme($code);
			$manifest = ThemeAssets::manifest($code);
			$stats = self::themeStats($code);
			return array(
				'code' => $code,
				'name' => $manifest['name'],
				'version' => $manifest['version'],
				'active' => $code === ThemeAssets::currentTheme(),
				'has_manifest' => is_file(self::themeRoot($code) . '/' . ThemeAssets::MANIFEST),
				'files_count' => $stats['files'],
				'size' => $stats['size'],
				'size_label' => self::formatBytes($stats['size']),
				'styles_count' => count($manifest['styles']),
				'scripts_count' => count($manifest['scripts']),
				'manifest' => $manifest,
			);
		}

		public static function directory($theme, $directory = '')
		{
			$theme = self::assertTheme($theme);
			$directory = self::normalizeDirectory($directory);
			$absolute = self::existingPath($theme, $directory, true);
			$manifest = ThemeAssets::manifest($theme);
			$connected = array();
			foreach ($manifest['styles'] as $item) { $connected[$item['file']] = 'CSS'; }
			foreach ($manifest['scripts'] as $item) { $connected[$item['file']] = 'JS'; }
			$items = array();
			foreach (new \DirectoryIterator($absolute) as $entry) {
				if ($entry->isDot() || $entry->isLink()) { continue; }
				$name = $entry->getFilename();
				$path = ltrim($directory . '/' . $name, '/');
				if ($entry->isDir()) {
					if (self::hiddenSegment($name)) { continue; }
					$items[] = array(
						'type' => 'directory', 'name' => $name, 'path' => $path, 'extension' => '',
						'size' => 0, 'size_label' => '', 'modified' => $entry->getMTime(),
						'modified_label' => date('d.m.Y H:i', $entry->getMTime()), 'editable' => false,
						'connected' => '', 'url' => '', 'preview' => '',
					);
					continue;
				}

				if (!$entry->isFile() || ($name !== ThemeAssets::MANIFEST && !ThemeAssets::publicExtension($path) && !ThemeAssets::viewTemplate($path))) { continue; }
				$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
				$url = $name === ThemeAssets::MANIFEST || ThemeAssets::viewTemplate($path) ? '' : ThemeAssets::assetUrl($path, $theme);
				$items[] = array(
					'type' => 'file', 'name' => $name, 'path' => $path, 'extension' => $extension,
					'size' => $entry->getSize(), 'size_label' => self::formatBytes($entry->getSize()),
					'modified' => $entry->getMTime(), 'modified_label' => date('d.m.Y H:i', $entry->getMTime()),
					'editable' => $name === ThemeAssets::MANIFEST || ThemeAssets::editableText($path),
					'connected' => isset($connected[$path]) ? $connected[$path] : '',
					'url' => $url, 'preview' => self::imageExtension($extension) ? $url : '',
				);
			}

			usort($items, function ($left, $right) {
				if ($left['type'] !== $right['type']) { return $left['type'] === 'directory' ? -1 : 1; }
				return strnatcasecmp($left['name'], $right['name']);
			});

			return array(
				'theme' => self::theme($theme), 'directory' => $directory, 'items' => $items,
				'breadcrumbs' => self::breadcrumbs($directory),
			);
		}

		public static function file($theme, $path)
		{
			$theme = self::assertTheme($theme);
			$path = self::assertAssetPath($path, true);
			$absolute = self::existingPath($theme, $path, false);
			if (!is_file($absolute)) { throw new \RuntimeException('Файл темы не найден'); }
			$editable = basename($path) === ThemeAssets::MANIFEST || ThemeAssets::editableText($path);
			$size = filesize($absolute);
			if ($editable && $size > self::MAX_TEXT_BYTES) { throw new \RuntimeException('Файл слишком большой для редактора'); }
			$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
			return array(
				'theme' => $theme, 'path' => $path, 'name' => basename($path), 'extension' => $extension,
				'editable' => $editable, 'content' => $editable ? (string) file_get_contents($absolute) : '',
				'mode' => self::editorMode($extension), 'size' => $size, 'size_label' => self::formatBytes($size),
				'modified' => filemtime($absolute), 'modified_label' => date('d.m.Y H:i:s', filemtime($absolute)),
				'url' => basename($path) === ThemeAssets::MANIFEST || ThemeAssets::viewTemplate($path) ? '' : ThemeAssets::assetUrl($path, $theme),
				'preview' => self::imageExtension($extension) ? ThemeAssets::assetUrl($path, $theme) : '',
			);
		}

		public static function saveFile($theme, $path, $content, $authorId, $action = 'update', $sourceRevisionId = 0, $invalidate = true)
		{
			$theme = self::assertTheme($theme);
			$path = self::assertAssetPath($path, true);
			if (basename($path) !== ThemeAssets::MANIFEST && !ThemeAssets::editableText($path)) {
				throw new \RuntimeException('Этот тип файла нельзя редактировать как текст');
			}

			$content = (string) $content;
			if (strlen($content) > self::MAX_TEXT_BYTES) { throw new \RuntimeException('Текстовый файл не должен превышать 2 МБ'); }
			$content = self::validateText($path, $content);
			$absolute = self::targetPath($theme, $path);
			$exists = is_file($absolute);
			if ($exists && is_link($absolute)) { throw new \RuntimeException('Символические ссылки редактировать нельзя'); }
			if ($exists && (string) file_get_contents($absolute) === $content) { return self::file($theme, $path); }

			if ($exists) {
				Revisions::capture($theme, $path, 'backup', (string) file_get_contents($absolute), $authorId, 0);
			}

			if (!File::putAtomic($absolute, $content)) { throw new \RuntimeException('Не удалось записать файл темы'); }
			Revisions::capture($theme, $path, $exists ? $action : 'create', $content, $authorId, $sourceRevisionId);
			if ($invalidate) { self::invalidate($theme); }
			return self::file($theme, $path);
		}

		public static function createFile($theme, $directory, $name, $authorId)
		{
			$directory = self::normalizeDirectory($directory);
			$name = self::safeName($name);
			$path = ltrim($directory . '/' . $name, '/');
			self::assertAssetPath($path, false);
			if (!ThemeAssets::editableText($path)) { throw new \InvalidArgumentException('Создать можно CSS, JS, JSON, SVG, XML, Twig или текстовый файл'); }
			if (file_exists(self::targetPath(self::assertTheme($theme), $path))) { throw new \RuntimeException('Файл с таким именем уже существует'); }
			$content = self::initialContent($path);
			return self::saveFile($theme, $path, $content, $authorId, 'create');
		}

		public static function createDirectory($theme, $directory, $name)
		{
			$theme = self::assertTheme($theme);
			$directory = self::normalizeDirectory($directory);
			$name = self::safeName($name, false);
			if (self::hiddenSegment($name)) { throw new \InvalidArgumentException('Это имя каталога зарезервировано'); }
			$path = ltrim($directory . '/' . $name, '/');
			$absolute = self::targetPath($theme, $path);
			if (file_exists($absolute)) { throw new \RuntimeException('Каталог с таким именем уже существует'); }
			if (!@mkdir($absolute, 0775, true)) { throw new \RuntimeException('Не удалось создать каталог'); }
			return self::directory($theme, $directory);
		}

		public static function upload($theme, $directory, array $files, $authorId)
		{
			$theme = self::assertTheme($theme);
			$directory = self::normalizeDirectory($directory);
			$names = isset($files['name']) ? (array) $files['name'] : array();
			$tmpNames = isset($files['tmp_name']) ? (array) $files['tmp_name'] : array();
			$sizes = isset($files['size']) ? (array) $files['size'] : array();
			$errors = isset($files['error']) ? (array) $files['error'] : array();
			$result = array('uploaded' => array(), 'errors' => array());
			foreach ($names as $index => $original) {
				try {
					if (!isset($errors[$index]) || (int) $errors[$index] !== UPLOAD_ERR_OK) { throw new \RuntimeException('Файл загрузился не полностью'); }
					$tmp = isset($tmpNames[$index]) ? (string) $tmpNames[$index] : '';
					$size = isset($sizes[$index]) ? (int) $sizes[$index] : 0;
					if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > self::MAX_FILE_BYTES) { throw new \RuntimeException('Размер файла должен быть от 1 байта до 10 МБ'); }
					$name = self::safeName($original);
					$path = ltrim($directory . '/' . $name, '/');
					self::assertAssetPath($path, false);
					if (!ThemeAssets::publicExtension($path) && !ThemeAssets::viewTemplate($path)) { throw new \RuntimeException('Расширение файла не разрешено для темы'); }
					$target = self::targetPath($theme, $path);
					if (file_exists($target)) { throw new \RuntimeException('Файл уже существует'); }
					$content = (string) file_get_contents($tmp);
					self::validateUploadedContent($path, $tmp, $content);
					if (ThemeAssets::editableText($path)) {
						self::saveFile($theme, $path, self::validateText($path, $content), $authorId, 'upload', 0, false);
					} elseif (!File::putAtomic($target, $content)) {
						throw new \RuntimeException('Не удалось сохранить файл');
					}

					$result['uploaded'][] = $path;
				} catch (\Throwable $e) {
					$result['errors'][] = basename((string) $original) . ': ' . $e->getMessage();
				}
			}

			self::invalidate($theme);
			return $result;
		}

		public static function deletePath($theme, $path, $authorId)
		{
			$theme = self::assertTheme($theme);
			$path = self::assertAssetPath($path, false);
			if (basename($path) === ThemeAssets::MANIFEST) { throw new \RuntimeException('Реестр темы удалить нельзя'); }
			$absolute = self::existingPath($theme, $path, null);
			if (is_file($absolute)) {
				if (ThemeAssets::editableText($path)) { Revisions::capture($theme, $path, 'delete', (string) file_get_contents($absolute), $authorId, 0); }
				if (!@unlink($absolute)) { throw new \RuntimeException('Не удалось удалить файл'); }
			} elseif (is_dir($absolute)) {
				self::removeAssetDirectory($absolute, $theme, $path, $authorId);
			} else {
				throw new \RuntimeException('Файл или каталог не найден');
			}

			self::removeFromManifest($theme, $path, $authorId);
			self::invalidate($theme);
			return true;
		}

		public static function saveManifest($theme, array $input, $authorId)
		{
			$theme = self::assertTheme($theme);
			$current = ThemeAssets::manifest($theme);
			$styles = self::manifestRows(isset($input['styles']) ? $input['styles'] : array(), 'style', $theme);
			$scripts = self::manifestRows(isset($input['scripts']) ? $input['scripts'] : array(), 'script', $theme);
			$manifest = ThemeAssets::normalizeManifest(array(
				'name' => isset($input['name']) ? $input['name'] : '',
				'version' => isset($input['version']) ? $input['version'] : '',
				'styles' => $styles, 'scripts' => $scripts,
				'presentation_mode' => isset($current['presentation_mode']) ? $current['presentation_mode'] : 'native',
				'page_shell' => isset($current['page_shell']) ? $current['page_shell'] : '',
				'page_shell_mode' => isset($current['page_shell_mode']) ? $current['page_shell_mode'] : 'wrap',
				'inherit_rubric_templates' => !empty($current['inherit_rubric_templates']),
				'view_overrides' => isset($current['view_overrides']) ? $current['view_overrides'] : array(),
				'settings' => isset($current['settings']) ? $current['settings'] : array(),
			), $theme);
			$content = Json::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
			self::saveFile($theme, ThemeAssets::MANIFEST, $content, $authorId, 'manifest');
			return $manifest;
		}

		public static function saveSettings($theme, array $input, $authorId)
		{
			$theme = self::assertTheme($theme);
			$manifest = ThemeAssets::manifest($theme);
			$mode = isset($input['presentation_mode']) && (string) $input['presentation_mode'] === 'theme'
				? 'theme'
				: 'native';
			if ($mode === 'theme' && empty($manifest['page_shell'])) {
				throw new \RuntimeException('В теме не указан файл Twig-оболочки');
			}

			$result = ThemeSettings::save($theme, $input);
			if (empty($result['success'])) {
				$message = !empty($result['errors']) ? reset($result['errors']) : 'Проверьте значения настроек темы';
				throw new \InvalidArgumentException((string) $message);
			}

			$manifest['presentation_mode'] = $mode;
			$manifest = ThemeAssets::normalizeManifest($manifest, $theme);
			$content = Json::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
			self::saveFile($theme, ThemeAssets::MANIFEST, $content, $authorId, 'settings');
			return array('manifest' => $manifest, 'saved' => (int) $result['saved']);
		}

		public static function availableAssets($theme)
		{
			$theme = self::assertTheme($theme);
			$out = array('styles' => array(), 'scripts' => array());
			$root = self::themeRoot($theme);
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), function ($current) use ($root) {
					if ($current->isLink()) { return false; }
					if ($current->isDir() && self::hiddenSegment($current->getFilename())) { return false; }
					return true;
				})
			);
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->isLink()) { continue; }
				$path = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
				$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
				if ($extension === 'css') { $out['styles'][] = $path; }
				if (in_array($extension, array('js', 'mjs'), true)) { $out['scripts'][] = $path; }
			}

			sort($out['styles'], SORT_NATURAL | SORT_FLAG_CASE);
			sort($out['scripts'], SORT_NATURAL | SORT_FLAG_CASE);
			return $out;
		}

		public static function createTheme($code, $name, $authorId)
		{
			$code = self::validateThemeCode($code);
			$root = self::themesRoot() . '/' . $code;
			if (file_exists($root)) { throw new \RuntimeException('Тема с таким кодом уже существует'); }
			if (!@mkdir($root . '/css', 0775, true) || !@mkdir($root . '/js', 0775, true)
				|| !@mkdir($root . '/images', 0775, true) || !@mkdir($root . '/fonts', 0775, true)) {
				self::removeTree($root);
				throw new \RuntimeException('Не удалось создать структуру темы');
			}

			File::putAtomic($root . '/index.php', self::guardFile());
			File::putAtomic($root . '/css/app.css', "/* Styles for theme " . $code . " */\n");
			File::putAtomic($root . '/js/app.js', "/* Scripts for theme " . $code . " */\n");
			self::saveManifest($code, array(
				'name' => trim((string) $name) !== '' ? $name : ucfirst($code), 'version' => '1.0.0',
				'styles' => array(array('file' => 'css/app.css', 'media' => '')),
				'scripts' => array(array('file' => 'js/app.js', 'defer' => 1)),
			), $authorId);
			return self::theme($code);
		}

		public static function activate($theme)
		{
			$theme = self::assertTheme($theme);
			$table = SystemTables::table('constants');
			if (DB::query('SELECT name FROM ' . $table . ' WHERE name=%s LIMIT 1', 'DEFAULT_THEME_FOLDER')->getValue()) {
				DB::Update($table, array('value' => $theme, 'type' => 'select', 'updated_at' => date('Y-m-d H:i:s')), 'name=%s', 'DEFAULT_THEME_FOLDER');
			} else {
				DB::Insert($table, array(
					'name' => 'DEFAULT_THEME_FOLDER', 'value' => $theme, 'type' => 'select', 'group_code' => '_CONST_THEMES',
					'label' => 'Тема публичной части', 'description' => 'Активная тема публичной части.', 'options' => '[]',
					'is_system' => 1, 'sort_order' => 40, 'updated_at' => date('Y-m-d H:i:s'),
				));
			}

			self::invalidate($theme, true);
			$item = self::theme($theme);
			$item['active'] = true;
			return $item;
		}

		public static function deleteTheme($theme)
		{
			$theme = self::assertTheme($theme);
			if ($theme === ThemeAssets::currentTheme()) { throw new \RuntimeException('Активную тему удалить нельзя'); }
			if (!self::removeTree(self::themeRoot($theme))) { throw new \RuntimeException('Не удалось удалить каталог темы'); }
			Revisions::deleteTheme($theme);
			self::invalidate($theme);
			return true;
		}

		public static function exportArchive($theme)
		{
			$theme = self::assertTheme($theme);
			if (!class_exists('ZipArchive')) { throw new \RuntimeException('Для экспорта темы требуется PHP-расширение zip'); }
			$file = tempnam(BASEPATH . '/tmp', 'ave-theme-');
			if ($file === false) { throw new \RuntimeException('Не удалось создать временный ZIP'); }
			$archive = $file . '.zip';
			@rename($file, $archive);
			$zip = new \ZipArchive();
			if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { @unlink($archive); throw new \RuntimeException('Не удалось создать ZIP темы'); }
			$root = self::themeRoot($theme);
			$files = self::exportFiles($theme);
			foreach ($files as $path) { $zip->addFile($root . '/' . $path, $path); }
			if (!in_array(ThemeAssets::MANIFEST, $files, true)) {
				$zip->addFromString(
					ThemeAssets::MANIFEST,
					Json::encode(ThemeAssets::manifest($theme), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
				);
			}

			$zip->close();
			return array('path' => $archive, 'name' => 'ave-theme-' . $theme . '-' . date('Ymd-His') . '.zip');
		}

		public static function importArchive(array $file, $theme, $authorId)
		{
			$theme = self::validateThemeCode($theme);
			if (file_exists(self::themesRoot() . '/' . $theme)) { throw new \RuntimeException('Тема с таким кодом уже существует'); }
			if (!class_exists('ZipArchive')) { throw new \RuntimeException('Для импорта темы требуется PHP-расширение zip'); }
			self::validateArchiveUpload($file);
			$zip = new \ZipArchive();
			if ($zip->open((string) $file['tmp_name']) !== true) { throw new \RuntimeException('Не удалось открыть ZIP-архив'); }
			$entries = array();
			$total = 0;
			try {
				if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_FILES) { throw new \RuntimeException('ZIP содержит недопустимое количество файлов'); }
				$prefix = self::archivePrefix($zip);
				for ($index = 0; $index < $zip->numFiles; $index++) {
					$stat = $zip->statIndex($index);
					if (!is_array($stat) || !isset($stat['name'])) { throw new \RuntimeException('Не удалось прочитать структуру ZIP'); }
					self::assertArchiveNotSymlink($zip, $index);
					$name = self::archiveEntry((string) $stat['name'], $prefix);
					if ($name === null || substr($name, -1) === '/') { continue; }
					$size = isset($stat['size']) ? (int) $stat['size'] : 0;
					$total += $size;
					if ($size > self::MAX_FILE_BYTES || $total > self::MAX_ARCHIVE_UNPACKED) { throw new \RuntimeException('Распакованная тема превышает безопасный лимит'); }
					self::assertAssetPath($name, true);
					if (basename($name) !== ThemeAssets::MANIFEST && !ThemeAssets::publicExtension($name) && !ThemeAssets::viewTemplate($name)) { throw new \RuntimeException('ZIP содержит запрещённый файл «' . $name . '»'); }
					if (isset($entries[strtolower($name)])) { throw new \RuntimeException('ZIP содержит повторяющийся путь «' . $name . '»'); }
					$entries[strtolower($name)] = array('index' => $index, 'path' => $name);
				}

				if (!isset($entries[ThemeAssets::MANIFEST])) { throw new \RuntimeException('ZIP не содержит theme.json'); }
				$manifestRaw = $zip->getFromIndex($entries[ThemeAssets::MANIFEST]['index']);
				$manifest = Json::toArray((string) $manifestRaw);
				if (!isset($manifest['format']) || $manifest['format'] !== 'ave-theme-v1') { throw new \RuntimeException('theme.json имеет неподдерживаемый формат'); }
				$manifest = ThemeAssets::normalizeManifest($manifest, $theme);
				foreach (array_merge($manifest['styles'], $manifest['scripts']) as $asset) {
					if (!isset($entries[strtolower($asset['file'])])) {
						throw new \RuntimeException('theme.json ссылается на отсутствующий файл «' . $asset['file'] . '»');
					}
				}

				if ($manifest['page_shell'] !== '' && !isset($entries[strtolower($manifest['page_shell'])])) {
					throw new \RuntimeException('theme.json ссылается на отсутствующую оболочку «' . $manifest['page_shell'] . '»');
				}

				$manifestContent = Json::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
				$root = self::themesRoot() . '/' . $theme;
				if (!@mkdir($root, 0775, true)) { throw new \RuntimeException('Не удалось создать каталог темы'); }
				try {
					foreach ($entries as $entry) {
						$content = $zip->getFromIndex($entry['index']);
						if ($content === false) { throw new \RuntimeException('Не удалось извлечь «' . $entry['path'] . '»'); }
						$content = basename($entry['path']) === ThemeAssets::MANIFEST
							? $manifestContent
							: (ThemeAssets::editableText($entry['path']) ? self::validateText($entry['path'], (string) $content) : (string) $content);
						self::validateUploadedContent($entry['path'], '', $content);
						if (!File::putAtomic($root . '/' . $entry['path'], $content)) { throw new \RuntimeException('Не удалось записать «' . $entry['path'] . '»'); }
					}

					File::putAtomic($root . '/index.php', self::guardFile());
				} catch (\Throwable $e) {
					self::removeTree($root);
					throw $e;
				}
			} finally {
				$zip->close();
			}

			Revisions::capture($theme, ThemeAssets::MANIFEST, 'import', (string) file_get_contents(self::themeRoot($theme) . '/' . ThemeAssets::MANIFEST), $authorId, 0);
			self::invalidate($theme);
			return self::theme($theme);
		}

		public static function invalidate($theme = '', $forcePublic = false)
		{
			ThemeAssets::clearRuntimeCache();
			if ($forcePublic || ((string) $theme !== '' && (string) $theme === ThemeAssets::currentTheme())) {
				FileCacheInvalidator::publicPresentation();
			}
		}

		public static function formatBytes($bytes)
		{
			$bytes = max(0, (int) $bytes);
			if ($bytes < 1024) { return $bytes . ' Б'; }
			if ($bytes < 1048576) { return round($bytes / 1024, 1) . ' KB'; }
			return round($bytes / 1048576, 1) . ' MB';
		}

		protected static function themesRoot()
		{
			return rtrim(str_replace('\\', '/', BASEPATH), '/') . '/templates';
		}

		protected static function themeRoot($theme)
		{
			return self::themesRoot() . '/' . (string) $theme;
		}

		protected static function assertTheme($theme)
		{
			$theme = self::validateThemeCode($theme);
			$root = self::themeRoot($theme);
			if (!is_dir($root) || is_link($root)) { throw new \InvalidArgumentException('Тема не найдена'); }
			return $theme;
		}

		protected static function validateThemeCode($theme)
		{
			$theme = strtolower(trim((string) $theme));
			if (!ThemeAssets::validTheme($theme)) { throw new \InvalidArgumentException('Код темы: латиница, цифры, _ и -, до 64 символов'); }
			return $theme;
		}

		protected static function normalizeDirectory($directory)
		{
			$directory = trim(str_replace('\\', '/', (string) $directory), '/');
			if ($directory === '') { return ''; }
			return self::assertAssetPath($directory, false);
		}

		protected static function assertAssetPath($path, $allowManifest)
		{
			$path = ThemeAssets::normalizePath($path);
			if ($path === '') { throw new \InvalidArgumentException('Некорректный путь файла темы'); }
			foreach (explode('/', $path) as $segment) {
				if (self::hiddenSegment($segment)) { throw new \InvalidArgumentException('Служебный каталог темы недоступен'); }
				if (preg_match('/(?:^|\.)(?:php\d*|phtml|phar|cgi|pl|py|sh|htaccess|user\.ini)(?:\.|$)/i', $segment)) {
					throw new \InvalidArgumentException('Исполняемые и серверные файлы в теме запрещены');
				}
			}

			if (basename($path) === ThemeAssets::MANIFEST && (!$allowManifest || $path !== ThemeAssets::MANIFEST)) {
				throw new \InvalidArgumentException('Служебный файл темы доступен только в корне темы');
			}

			return $path;
		}

		protected static function targetPath($theme, $path)
		{
			$root = realpath(self::themeRoot($theme));
			if ($root === false) { throw new \RuntimeException('Каталог темы не найден'); }
			$parent = dirname(self::themeRoot($theme) . '/' . $path);
			if (!is_dir($parent) && !@mkdir($parent, 0775, true)) { throw new \RuntimeException('Не удалось создать каталог ассета'); }
			self::assertInsideRoot($parent, $root, true);
			return rtrim(str_replace('\\', '/', $root), '/') . '/' . $path;
		}

		protected static function existingPath($theme, $path, $directory)
		{
			$root = realpath(self::themeRoot($theme));
			$absolute = realpath(self::themeRoot($theme) . ($path !== '' ? '/' . $path : ''));
			if ($root === false || $absolute === false) { throw new \RuntimeException('Путь темы не найден'); }
			self::assertInsideRoot($absolute, $root, $path === '');
			if (is_link(self::themeRoot($theme) . ($path !== '' ? '/' . $path : ''))) { throw new \RuntimeException('Символические ссылки недоступны'); }
			if ($directory === true && !is_dir($absolute)) { throw new \RuntimeException('Каталог темы не найден'); }
			if ($directory === false && !is_file($absolute)) { throw new \RuntimeException('Файл темы не найден'); }
			return str_replace('\\', '/', $absolute);
		}

		protected static function assertInsideRoot($path, $root, $allowRoot = false)
		{
			$root = rtrim(str_replace('\\', '/', (string) realpath($root)), '/');
			$real = realpath($path);
			$real = $real !== false ? rtrim(str_replace('\\', '/', $real), '/') : '';
			if ($root === '' || $real === '' || (!$allowRoot && strpos($real, $root . '/') !== 0) || ($allowRoot && $real !== $root && strpos($real, $root . '/') !== 0)) {
				throw new \RuntimeException('Путь выходит за каталог темы');
			}
		}

		protected static function safeName($name, $withExtension = true)
		{
			$name = trim(str_replace(array('/', '\\'), '', (string) $name));
			if ($name === '' || strlen($name) > 160 || $name[0] === '.' || preg_match('/[\x00-\x1F\x7F:*?"<>|]/u', $name) || preg_match('/[.\s]$/u', $name)) {
				throw new \InvalidArgumentException('Некорректное имя файла или каталога');
			}

			if (!$withExtension && strpos($name, '.') !== false) { throw new \InvalidArgumentException('В имени каталога нельзя использовать точку'); }
			return $name;
		}

		protected static function hiddenSegment($segment)
		{
			return in_array(strtolower((string) $segment), self::$hiddenDirectories, true) || substr((string) $segment, 0, 1) === '.';
		}

		protected static function breadcrumbs($directory)
		{
			$out = array(array('name' => 'Корень темы', 'path' => ''));
			$path = '';
			foreach ($directory === '' ? array() : explode('/', $directory) as $part) {
				$path = ltrim($path . '/' . $part, '/');
				$out[] = array('name' => $part, 'path' => $path);
			}

			return $out;
		}

		protected static function themeStats($theme)
		{
			$stats = array('files' => 0, 'size' => 0);
			$root = self::themeRoot($theme);
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), function ($current) {
					if ($current->isLink()) { return false; }
					if ($current->isDir() && self::hiddenSegment($current->getFilename())) { return false; }
					return true;
				})
			);
			foreach ($iterator as $file) {
				if ($file->isFile() && !$file->isLink()) {
					$path = $file->getFilename();
					if ($path === ThemeAssets::MANIFEST || ThemeAssets::publicExtension($path)) { $stats['files']++; $stats['size'] += $file->getSize(); }
				}
			}

			return $stats;
		}

		protected static function validateText($path, $content)
		{
			if (strpos((string) $content, "\0") !== false) { throw new \InvalidArgumentException('Текстовый файл содержит нулевой байт'); }
			$extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
			if ($extension === 'json' || $extension === 'map' || $extension === 'webmanifest') {
				json_decode((string) $content, true);
				if (json_last_error() !== JSON_ERROR_NONE) { throw new \InvalidArgumentException('JSON содержит синтаксическую ошибку: ' . json_last_error_msg()); }
				if (basename((string) $path) === ThemeAssets::MANIFEST) {
					$manifest = Json::toArray((string) $content);
					if (!isset($manifest['format']) || $manifest['format'] !== 'ave-theme-v1') {
						throw new \InvalidArgumentException('theme.json должен иметь формат ave-theme-v1');
					}
				}
			}

			if ($extension === 'svg') { return self::sanitizeSvg($content); }
			return (string) $content;
		}

		protected static function sanitizeSvg($content)
		{
			if (!class_exists('DOMDocument')) { throw new \RuntimeException('Для безопасной обработки SVG требуется DOM'); }
			$previous = libxml_use_internal_errors(true);
			$document = new \DOMDocument();
			$loaded = $document->loadXML((string) $content, LIBXML_NONET | LIBXML_NOBLANKS);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			if (!$loaded || !$document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
				throw new \InvalidArgumentException('SVG содержит некорректный XML');
			}

			$blocked = array('script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video');
			$nodes = array();
			foreach ($document->getElementsByTagName('*') as $node) { $nodes[] = $node; }
			foreach ($nodes as $node) {
				if (in_array(strtolower($node->localName), $blocked, true)) { $node->parentNode->removeChild($node); continue; }
				$attributes = array();
				foreach ($node->attributes ?: array() as $attribute) { $attributes[] = $attribute->name; }
				foreach ($attributes as $name) {
					$value = (string) $node->getAttribute($name);
					if (stripos($name, 'on') === 0 || preg_match('/^\s*(?:javascript|data\s*:\s*text\/html)/i', $value)) { $node->removeAttribute($name); }
				}
			}

			return (string) $document->saveXML($document->documentElement);
		}

		protected static function validateUploadedContent($path, $temporary, $content)
		{
			$extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
			$images = array('gif' => IMAGETYPE_GIF, 'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'bmp' => IMAGETYPE_BMP);
			if (defined('IMAGETYPE_WEBP')) { $images['webp'] = IMAGETYPE_WEBP; }
			if (isset($images[$extension])) {
				$info = $temporary !== '' ? @getimagesize($temporary) : @getimagesizefromstring((string) $content);
				if (!is_array($info) || !isset($info[2]) || (int) $info[2] !== (int) $images[$extension]) { throw new \InvalidArgumentException('Содержимое изображения не соответствует расширению'); }
			}

			if ($extension === 'svg') { self::sanitizeSvg($content); }
			if ((ThemeAssets::editableText($path) || in_array($extension, array('css', 'js', 'mjs', 'json', 'map', 'txt', 'xml', 'webmanifest'), true)) && strpos($content, "\0") !== false) { throw new \InvalidArgumentException('Текстовый ассет содержит бинарные данные'); }
		}

		protected static function editorMode($extension)
		{
			if ($extension === 'css') { return 'text/css'; }
			if (in_array($extension, array('js', 'mjs'), true)) { return 'text/javascript'; }
			if (in_array($extension, array('json', 'map', 'webmanifest'), true)) { return 'application/json'; }
			if (in_array($extension, array('svg', 'xml'), true)) { return 'xml'; }
			return 'text/plain';
		}

		protected static function imageExtension($extension)
		{
			return in_array((string) $extension, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg'), true);
		}

		protected static function initialContent($path)
		{
			$extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
			if ($extension === 'css') { return "/* Theme styles */\n"; }
			if (in_array($extension, array('js', 'mjs'), true)) { return "'use strict';\n"; }
			if (in_array($extension, array('json', 'map', 'webmanifest'), true)) { return "{}\n"; }
			if ($extension === 'svg') { return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"></svg>' . "\n"; }
			if ($extension === 'twig') { return "{# Theme view override #}\n"; }
			return '';
		}

		protected static function manifestRows($rows, $type, $theme)
		{
			$out = array();
			foreach (is_array($rows) ? $rows : array() as $row) {
				if (!is_array($row)) { continue; }
				$file = ThemeAssets::normalizePath(isset($row['file']) ? $row['file'] : '');
				if ($file === '') { continue; }
				$extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
				if (($type === 'style' && $extension !== 'css') || ($type === 'script' && !in_array($extension, array('js', 'mjs'), true))) { continue; }
				self::existingPath($theme, $file, false);
				if ($type === 'style') { $out[] = array('file' => $file, 'media' => isset($row['media']) ? $row['media'] : ''); }
				else { $out[] = array('file' => $file, 'defer' => !empty($row['defer']), 'async' => !empty($row['async']), 'module' => !empty($row['module'])); }
			}

			return $out;
		}

		protected static function removeFromManifest($theme, $path, $authorId)
		{
			$manifest = ThemeAssets::manifest($theme);
			$prefix = rtrim((string) $path, '/') . '/';
			$manifest['styles'] = array_values(array_filter($manifest['styles'], function ($item) use ($path, $prefix) { return $item['file'] !== $path && strpos($item['file'], $prefix) !== 0; }));
			$manifest['scripts'] = array_values(array_filter($manifest['scripts'], function ($item) use ($path, $prefix) { return $item['file'] !== $path && strpos($item['file'], $prefix) !== 0; }));
			self::saveManifest($theme, $manifest, $authorId);
		}

		protected static function removeAssetDirectory($absolute, $theme, $path, $authorId)
		{
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($iterator as $item) {
				if ($item->isLink()) { throw new \RuntimeException('Каталог содержит символическую ссылку'); }
				$relative = ltrim($path . '/' . substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $absolute)) + 1), '/');
				if ($item->isFile() && ThemeAssets::editableText($relative)) { Revisions::capture($theme, $relative, 'delete', (string) file_get_contents($item->getPathname()), $authorId, 0); }
				$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			}

			if (!@rmdir($absolute)) { throw new \RuntimeException('Не удалось удалить каталог'); }
		}

		protected static function exportFiles($theme)
		{
			$out = array();
			$root = self::themeRoot($theme);
			$iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), function ($current) {
				if ($current->isLink()) { return false; }
				if ($current->isDir() && self::hiddenSegment($current->getFilename())) { return false; }
				return true;
			}));
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->isLink()) { continue; }
				$path = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
				if (basename($path) === ThemeAssets::MANIFEST || ThemeAssets::publicExtension($path) || ThemeAssets::viewTemplate($path)) { $out[] = $path; }
			}

			return $out;
		}

		protected static function validateArchiveUpload(array $file)
		{
			if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) { throw new \InvalidArgumentException('Выберите ZIP-архив темы'); }
			$size = isset($file['size']) ? (int) $file['size'] : 0;
			if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES) { throw new \InvalidArgumentException('Размер ZIP должен быть не больше 50 МБ'); }
			if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'zip') { throw new \InvalidArgumentException('Поддерживается только ZIP-архив'); }
			$handle = @fopen((string) $file['tmp_name'], 'rb');
			$head = $handle ? (string) fread($handle, 4) : '';
			if ($handle) { fclose($handle); }
			if (!in_array($head, array("PK\x03\x04", "PK\x05\x06", "PK\x07\x08"), true)) { throw new \InvalidArgumentException('Файл не является ZIP-архивом'); }
		}

		protected static function archivePrefix(\ZipArchive $zip)
		{
			$prefix = null;
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
				$parts = explode('/', trim($name, '/'));
				if (count($parts) < 2) { return ''; }
				if ($prefix === null) { $prefix = $parts[0]; }
				if ($parts[0] !== $prefix) { return ''; }
			}

			return $prefix !== null ? $prefix . '/' : '';
		}

		protected static function archiveEntry($name, $prefix)
		{
			$name = str_replace('\\', '/', (string) $name);
			if ($prefix !== '' && strpos($name, $prefix) === 0) { $name = substr($name, strlen($prefix)); }
			$isDirectory = substr($name, -1) === '/';
			$name = trim($name, '/');
			if ($name === '') { return null; }
			if ($name[0] === '/' || preg_match('/^[A-Za-z]:\//', $name) || strpos($name, "\0") !== false) { throw new \RuntimeException('ZIP содержит абсолютный путь'); }
			foreach (explode('/', $name) as $segment) {
				if ($segment === '' || $segment === '.' || $segment === '..' || self::hiddenSegment($segment)) { throw new \RuntimeException('ZIP содержит небезопасный путь'); }
			}

			return $name . ($isDirectory ? '/' : '');
		}

		protected static function assertArchiveNotSymlink(\ZipArchive $zip, $index)
		{
			$opsys = 0;
			$attributes = 0;
			if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
				throw new \RuntimeException('Символические ссылки в ZIP запрещены');
			}
		}

		protected static function removeTree($path)
		{
			if (!file_exists($path)) { return true; }
			if (is_link($path) || is_file($path)) { return @unlink($path); }
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
			return @rmdir($path);
		}

		protected static function guardFile()
		{
			return "<?php\n\theader('Location:/');\n\texit;\n";
		}
	}
