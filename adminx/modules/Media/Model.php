<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\CacheKey;
	use App\Frontend\Media\ThumbnailUrl;
	use App\Frontend\Media\ThumbnailStorage;
	use App\Common\UploadPolicy;
	use App\Helpers\Dir;
	use App\Helpers\File;

	class Model
	{
		const ROOT = '/uploads';
		const MAX_UPLOAD_SIZE = 52428800;
		const MAX_IMAGE_DIM = 2400;

		protected static $imageExt = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
		protected static $allowedExt = array(
			'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
			'csv', 'txt', 'rtf', 'zip', 'rar', '7z'
		);
		protected static $hiddenEntries = array(
			'index.php', '.htaccess', 'th', '_derivatives', 'recycled',
			'.tmb', '.quarantine', '.temp', '.uploader', '.drafts'
		);

		public static function normalize($path, $fallback)
		{
			$path = html_entity_decode(trim((string) $path), ENT_QUOTES, 'UTF-8');
			$path = preg_replace('/[?#].*$/', '', $path);
			$path = str_replace('\\', '/', $path);
			$path = preg_replace('#/+#', '/', $path);
			if ($path === '') {
				$path = $fallback;
			}

			if ($path === '') {
				return '';
			}

			if ($path[0] !== '/') {
				$path = '/' . $path;
			}

			$path = rtrim($path, '/');
			if ($path === '') {
				$path = self::ROOT;
			}

			if (strpos($path, '..') !== false || !self::isAllowedPath($path)) {
				return $fallback;
			}

			return $path;
		}

		public static function isAllowedPath($path)
		{
			$path = (string) $path;
			return $path === self::ROOT || strpos($path, self::ROOT . '/') === 0;
		}

		public static function isProtectedPath($path)
		{
			$path = self::normalize($path, '');
			if ($path === '' || $path === self::ROOT) {
				return true;
			}

			$parts = explode('/', trim($path, '/'));
			foreach ($parts as $part) {
				if ($part === 'uploads') {
					continue;
				}

				if (!self::isVisibleEntry($part)) {
					return true;
				}
			}

			return false;
		}

		public static function supportsWebp()
		{
			return function_exists('imagewebp') && function_exists('imagecreatefromwebp');
		}

		public static function abs($path)
		{
			$path = self::normalize($path, self::ROOT);
			return rtrim(BASEPATH, '/') . $path;
		}

		public static function listing($dir, $q, $type, $page, $perPage)
		{
			$dir = self::normalize($dir, self::ROOT);
			if (!self::isBrowseablePath($dir)) {
				$dir = self::ROOT;
			}

			$abs = self::abs($dir);
			if (!is_dir($abs)) {
				$dir = self::ROOT;
				$abs = self::abs($dir);
			}

			$folders = array();
			$files = array();
			if ($q !== '') {
				self::searchTree($dir, $abs, $q, $type, $folders, $files);
			} else {
				$items = @scandir($abs);
				if ($items === false) {
					$items = array();
				}

				foreach ($items as $name) {
					if (!self::isVisibleEntry($name)) {
						continue;
					}

					$path = rtrim($dir, '/') . '/' . $name;
					$itemAbs = $abs . DIRECTORY_SEPARATOR . $name;
					if (is_dir($itemAbs)) {
						$folders[] = self::folderRow($path, $itemAbs);
						continue;
					}

					if (is_file($itemAbs) && self::matches($name, '', $type, false)) {
						$files[] = self::fileRow($path, $itemAbs);
					}
				}
			}

			usort($folders, array(__CLASS__, 'sortByName'));
			usort($files, array(__CLASS__, 'sortByName'));

			$total = count($files);
			$pages = max(1, (int) ceil($total / $perPage));
			$page = max(1, min($page, $pages));
			$files = array_slice($files, ($page - 1) * $perPage, $perPage);

			return array(
				'dir' => $dir,
				'folders' => $folders,
				'files' => $files,
				'total' => $total,
				'pages' => $pages,
				'page' => $page,
				'stats' => self::stats(self::abs(self::ROOT)),
			);
		}

		public static function file($path)
		{
			$path = self::normalize($path, '');
			$abs = self::abs($path);
			if ($path === '' || !self::isBrowseablePath($path) || !is_file($abs)) {
				return null;
			}

			return self::fileRow($path, $abs);
		}

		public static function folderFiles($dir, $type, $limit)
		{
			$dir = self::normalize($dir, self::ROOT);
			if (!self::isBrowseablePath($dir)) {
				throw new \RuntimeException('Некорректная папка');
			}

			$abs = self::abs($dir);
			if (!is_dir($abs)) {
				return array();
			}

			$out = array();
			$items = @scandir($abs);
			if ($items === false) {
				return $out;
			}

			foreach ($items as $name) {
				if (!self::isVisibleEntry($name)) {
					continue;
				}

				$path = rtrim($dir, '/') . '/' . $name;
				$fileAbs = $abs . DIRECTORY_SEPARATOR . $name;
				if (!is_file($fileAbs) || !self::matches($name, '', (string) $type, false)) {
					continue;
				}

				$out[] = self::fileRow($path, $fileAbs);
				if (count($out) >= (int) $limit) {
					break;
				}
			}

			usort($out, array(__CLASS__, 'sortByName'));
			return $out;
		}

		public static function breadcrumbs($dir)
		{
			$dir = self::normalize($dir, self::ROOT);
			$out = array(array('title' => 'uploads', 'path' => self::ROOT));
			$parts = explode('/', trim($dir, '/'));
			$path = self::ROOT;
			foreach ($parts as $i => $part) {
				if ($part === '' || ($i === 0 && $part === 'uploads')) {
					continue;
				}

				$path = rtrim($path, '/') . '/' . $part;
				$out[] = array('title' => $part, 'path' => $path);
			}

			return $out;
		}

		public static function parentDir($path)
		{
			$path = self::normalize($path, self::ROOT);
			if ($path === self::ROOT) {
				return '';
			}

			$parent = dirname($path);
			return ($parent === '/' || $parent === '.' || $parent === '\\') ? self::ROOT : $parent;
		}

		public static function createFolder($parent, $name)
		{
			$parent = self::normalize($parent, self::ROOT);
			$name = self::safeName($name, false);
			if ($name === '') {
				throw new \RuntimeException('Укажите название папки');
			}

			$path = rtrim($parent, '/') . '/' . $name;
			$abs = self::abs($path);
			if (file_exists($abs)) {
				throw new \RuntimeException('Папка или файл с таким именем уже существует');
			}

			if (!Dir::create($abs)) {
				throw new \RuntimeException('Не удалось создать папку');
			}

			self::invalidateStats();
			return $path;
		}

		public static function upload($dir, array $files)
		{
			$dir = self::normalize($dir, self::ROOT);
			$absDir = self::abs($dir);
			if (!Dir::create($absDir)) {
				throw new \RuntimeException('Не удалось создать каталог загрузки');
			}

			$uploaded = array();
			$errors = array();
			$names = isset($files['name']) ? (array) $files['name'] : array();
			$tmpNames = isset($files['tmp_name']) ? (array) $files['tmp_name'] : array();
			$uploadErrors = isset($files['error']) ? (array) $files['error'] : array();
			$sizes = isset($files['size']) ? (array) $files['size'] : array();

			foreach ($names as $i => $name) {
				$name = (string) $name;
				$tmp = isset($tmpNames[$i]) ? (string) $tmpNames[$i] : '';
				$err = isset($uploadErrors[$i]) ? (int) $uploadErrors[$i] : UPLOAD_ERR_NO_FILE;
				$size = isset($sizes[$i]) ? (int) $sizes[$i] : 0;
				if ($err !== UPLOAD_ERR_OK || $tmp === '') {
					$errors[] = $name !== '' ? $name : ('file #' . ($i + 1));
					continue;
				}

				if ($size > self::MAX_UPLOAD_SIZE) {
					$errors[] = $name . ' (слишком большой файл)';
					continue;
				}

				$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				if (!in_array($ext, self::$allowedExt, true)) {
					$errors[] = $name . ' (тип не разрешён)';
					continue;
				}

				$base = self::safeName(pathinfo($name, PATHINFO_FILENAME), false);
				if ($base === '') {
					$base = 'file';
				}

				$filename = self::uniqueName($absDir, $base, $ext);
				$target = $absDir . DIRECTORY_SEPARATOR . $filename;
				try {
					UploadPolicy::assertAllowed($tmp, $name, $size, $target, 'admin_media');
				} catch (\RuntimeException $e) {
					$errors[] = $name . ' (' . $e->getMessage() . ')';
					continue;
				}

				if (!@move_uploaded_file($tmp, $target)) {
					$errors[] = $name . ' (не удалось сохранить)';
					continue;
				}

				UploadPolicy::stored($target, $name, $size, 'admin_media');
				$uploaded[] = self::fileRow(rtrim($dir, '/') . '/' . $filename, $target);
			}

			if ($uploaded) {
				self::invalidateStats();
			}

			return array('uploaded' => $uploaded, 'errors' => $errors, 'dir' => $dir);
		}

		public static function rename($path, $name)
		{
			$path = self::normalize($path, '');
			$abs = self::abs($path);
			if (self::isProtectedPath($path) || !file_exists($abs)) {
				throw new \RuntimeException('Некорректный путь');
			}

			$parent = self::parentDir($path);
			$isDir = is_dir($abs);
			$safe = self::safeName($name, !$isDir);
			if ($safe === '') {
				throw new \RuntimeException('Укажите новое имя');
			}

			if (!$isDir && pathinfo($safe, PATHINFO_EXTENSION) === '') {
				$safe .= '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));
			}

			if (!$isDir && strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
				throw new \RuntimeException('Расширение файла нельзя менять переименованием');
			}

			$target = rtrim($parent, '/') . '/' . $safe;
			$targetAbs = self::abs($target);
			if (file_exists($targetAbs)) {
				throw new \RuntimeException('По этому пути уже есть файл или папка');
			}

			if (!$isDir) {
				self::assertCompanionRenameAvailable($path, $target);
				self::clearGeneratedForFile($path, $abs);
			}

			if (!@rename($abs, $targetAbs)) {
				throw new \RuntimeException('Не удалось переименовать');
			}

			if (!$isDir) {
				self::renameWebpCompanion($path, $target);
			}

			self::invalidateStats();
			return $target;
		}

		public static function delete($path)
		{
			$path = self::normalize($path, '');
			if (self::isProtectedPath($path)) {
				throw new \RuntimeException('Этот путь нельзя удалить');
			}

			$abs = self::abs($path);
			if (is_dir($abs)) {
				if (!self::removeDir($abs)) {
					throw new \RuntimeException('Не удалось удалить папку');
				}

				self::invalidateStats();
				return true;
			}

			if (is_file($abs)) {
				self::deleteFileCompanions($path, $abs);
			}

			if (is_file($abs) && !File::delete($abs)) {
				throw new \RuntimeException('Не удалось удалить файл');
			}

			self::invalidateStats();
			return true;
		}

		public static function clearThumbnails($dir)
		{
			$dir = self::normalize($dir, self::ROOT);
			if (!self::isBrowseablePath($dir)) {
				throw new \RuntimeException('Некорректная папка');
			}

			$abs = self::abs($dir);
			if (!is_dir($abs)) {
				throw new \RuntimeException('Папка не найдена');
			}

			$base = realpath($abs);
			if (!$base) {
				throw new \RuntimeException('Некорректная папка');
			}

			$thumbDir = self::thumbnailDirName();
			$targets = array();
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($iterator as $item) {
				if (!$item->isDir() || $item->isLink() || $item->getFilename() !== $thumbDir) {
					continue;
				}

				$real = $item->getRealPath();
				if ($real && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
					$targets[$real] = $real;
				}
			}

			$direct = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $thumbDir;
			if (is_dir($direct)) {
				$real = realpath($direct);
				if ($real && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
					$targets[$real] = $real;
				}
			}

			uksort($targets, function ($left, $right) {
				return strlen($right) - strlen($left);
			});
			$files = 0;
			$dirs = 0;
			$roots = 0;
			foreach ($targets as $target) {
				if (!is_dir($target)) {
					continue;
				}

				if (!self::removeDirWithCount($target, $files, $dirs)) {
					throw new \RuntimeException('Не удалось удалить превью');
				}

				$roots++;
			}

			return array(
				'files' => $files,
				'dirs' => $dirs,
				'roots' => $roots,
				'path' => $dir,
			);
		}

		/**
		 * Общий рендер по настройкам редактора (crop + resample). Возвращает
		 * GD-ресурс и выбранные format/quality/размеры; сохранение — на вызывающем.
		 */
		protected static function prepareRender($path, array $input)
		{
			$file = self::file($path);
			if (!$file || !$file['is_image'] || $file['extension'] === 'svg') {
				throw new \RuntimeException('Файл не является редактируемым изображением');
			}

			if (!function_exists('imagecreatetruecolor')) {
				throw new \RuntimeException('GD недоступен для обработки изображений');
			}

			$srcExt = strtolower($file['extension']);
			$format = self::format(isset($input['format']) ? $input['format'] : 'original', $srcExt);
			if ($format === 'webp' && !function_exists('imagewebp')) {
				throw new \RuntimeException('PHP GD не поддерживает сохранение WebP');
			}

			$width = self::clamp(isset($input['width']) ? $input['width'] : $file['width'], 1, self::MAX_IMAGE_DIM);
			$height = self::clamp(isset($input['height']) ? $input['height'] : $file['height'], 1, self::MAX_IMAGE_DIM);
			$quality = self::clamp(isset($input['quality']) ? $input['quality'] : 82, 1, 100);
			$mode = in_array(isset($input['mode']) ? $input['mode'] : '', array('fit', 'cover', 'contain', 'stretch'), true) ? $input['mode'] : 'fit';

			$src = self::loadImage($file['abs'], $srcExt);
			if (!$src) {
				throw new \RuntimeException('Не удалось открыть исходное изображение');
			}

			$sw = imagesx($src);
			$sh = imagesy($src);
			if (!empty($input['crop_enabled'])) {
				$crop = self::cropImage($src, $sw, $sh, array(
					'x' => isset($input['crop_x']) ? $input['crop_x'] : 0,
					'y' => isset($input['crop_y']) ? $input['crop_y'] : 0,
					'w' => isset($input['crop_w']) ? $input['crop_w'] : $sw,
					'h' => isset($input['crop_h']) ? $input['crop_h'] : $sh,
				));
				imagedestroy($src);
				$src = $crop;
				$sw = imagesx($src);
				$sh = imagesy($src);
			}

			$dst = self::resample($src, $sw, $sh, $width, $height, $mode);
			imagedestroy($src);

			return array(
				'file' => $file,
				'dst' => $dst,
				'format' => $format,
				'quality' => $quality,
				'width' => $width,
				'height' => $height,
				'mode' => $mode,
			);
		}

		public static function transform($path, array $input)
		{
			$r = self::prepareRender($path, $input);
			$dst = $r['dst'];

			$dir = self::parentDir($r['file']['path']);
			$outDir = rtrim($dir, '/') . '/_derivatives';
			$outAbsDir = self::abs($outDir);
			if (!Dir::create($outAbsDir)) {
				imagedestroy($dst);
				throw new \RuntimeException('Не удалось создать каталог производных изображений');
			}

			$base = pathinfo($r['file']['name'], PATHINFO_FILENAME) . '-' . $r['mode'] . '-' . $r['width'] . 'x' . $r['height'];
			$name = self::uniqueName($outAbsDir, self::safeName($base, false), $r['format']);
			$out = $outAbsDir . DIRECTORY_SEPARATOR . $name;
			self::saveImage($dst, $out, $r['format'], $r['quality']);
			imagedestroy($dst);
			return rtrim($outDir, '/') . '/' . $name;
		}

		/**
		 * Временный превью по текущим настройкам редактора. Кладётся в
		 * `_derivatives/_preview` (скрыт от листинга), имя `preview-<hex>` —
		 * удаляется через deletePreview() при закрытии окна предпросмотра.
		 */
		public static function previewTransform($path, array $input)
		{
			$r = self::prepareRender($path, $input);
			$dst = $r['dst'];

			$dir = self::parentDir($r['file']['path']);
			$outDir = rtrim($dir, '/') . '/_derivatives/_preview';
			$outAbsDir = self::abs($outDir);
			if (!Dir::create($outAbsDir)) {
				imagedestroy($dst);
				throw new \RuntimeException('Не удалось создать каталог предпросмотра');
			}

			self::sweepPreviews($outAbsDir);
			$token = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : md5(uniqid('', true));
			$name = 'preview-' . $token . '.' . $r['format'];
			$out = $outAbsDir . DIRECTORY_SEPARATOR . $name;
			self::saveImage($dst, $out, $r['format'], $r['quality']);
			imagedestroy($dst);
			$info = @getimagesize($out);
			$w = $info ? (int) $info[0] : (int) $r['width'];
			$h = $info ? (int) $info[1] : (int) $r['height'];
			return array(
				'path' => rtrim($outDir, '/') . '/' . $name,
				'width' => $w,
				'height' => $h,
				'format' => $r['format'],
				'size' => is_file($out) ? (int) @filesize($out) : 0,
				'size_label' => self::formatBytes(is_file($out) ? (int) @filesize($out) : 0),
			);
		}

		/** Удаление временного превью (только файлы preview-* внутри _preview). */
		public static function deletePreview($path)
		{
			$path = self::normalize($path, '');
			if ($path === '' || strpos($path, '/_derivatives/_preview/') === false) {
				return false;
			}

			if (strpos(basename($path), 'preview-') !== 0) {
				return false;
			}

			$abs = self::abs($path);
			if (is_file($abs)) {
				File::delete($abs);
				return true;
			}

			return false;
		}

		/** Подчистка «протухших» превью (старше 1 часа), чтобы не копились. */
		protected static function sweepPreviews($absDir)
		{
			$files = @glob(rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'preview-*');
			if (!is_array($files)) {
				return;
			}

			$limit = time() - 3600;
			foreach ($files as $f) {
				if (is_file($f) && @filemtime($f) < $limit) {
					File::delete($f);
				}
			}
		}

		public static function convertWebp($path, $quality)
		{
			$file = self::file($path);
			if (!$file || !$file['is_image'] || $file['extension'] === 'svg') {
				throw new \RuntimeException('Файл не является редактируемым изображением');
			}

			if ($file['extension'] === 'webp') {
				throw new \RuntimeException('Исходник уже в формате WebP');
			}

			if (!self::supportsWebp()) {
				throw new \RuntimeException('PHP GD не поддерживает сохранение WebP');
			}

			if ($file['has_webp']) {
				throw new \RuntimeException('WebP-копия уже существует');
			}

			$src = self::loadImage($file['abs'], $file['extension']);
			if (!$src) {
				throw new \RuntimeException('Не удалось открыть исходное изображение');
			}

			$quality = self::clamp($quality, 1, 100);
			$outPath = $file['webp_path'];
			$outAbs = self::abs($outPath);
			self::saveImage($src, $outAbs, 'webp', $quality);
			imagedestroy($src);
			if (!is_file($outAbs)) {
				throw new \RuntimeException('WebP-файл не был сохранён');
			}

			self::invalidateStats();
			return $outPath;
		}

		protected static function fileRow($path, $abs)
		{
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$isImage = in_array($ext, self::$imageExt, true);
			$width = 0;
			$height = 0;
			$mime = '';
			if ($isImage && $ext !== 'svg') {
				$info = @getimagesize($abs);
				if ($info) {
					$width = (int) $info[0];
					$height = (int) $info[1];
					$mime = isset($info['mime']) ? (string) $info['mime'] : '';
				}
			}

			return array(
				'name' => basename($path),
				'path' => $path,
				'abs' => $abs,
				'url' => $path,
				'thumb_url' => ($isImage && $ext !== 'svg') ? self::thumbnailUrl($path, 'c320x320') : '',
				'preview_url' => ($isImage && $ext !== 'svg') ? self::thumbnailUrl($path, 'f480x360') : $path,
				'extension' => $ext,
				'is_image' => $isImage,
				'type' => $isImage ? 'image' : 'file',
				'mime' => $mime,
				'size' => is_file($abs) ? (int) @filesize($abs) : 0,
				'size_label' => self::formatBytes(is_file($abs) ? (int) @filesize($abs) : 0),
				'width' => $width,
				'height' => $height,
				'mtime' => is_file($abs) ? (int) @filemtime($abs) : 0,
				'mtime_label' => is_file($abs) ? date('d.m.Y H:i', (int) @filemtime($abs)) : '',
				'meta' => trim(($width && $height ? $width . 'x' . $height : '') . (is_file($abs) ? ' · ' . self::formatBytes((int) @filesize($abs)) : ''), ' ·'),
				'webp_path' => self::webpPath($path),
				'has_webp' => is_file(self::abs(self::webpPath($path))),
			);
		}

		protected static function webpPath($path)
		{
			$dir = dirname($path);
			$base = pathinfo($path, PATHINFO_FILENAME);
			if ($dir === '.' || $dir === '/' || $dir === '\\') {
				$dir = self::ROOT;
			}

			return rtrim($dir, '/') . '/' . $base . '.webp';
		}

		protected static function thumbnailUrl($path, $size)
		{
			$url = ThumbnailUrl::make(array('link' => $path, 'size' => $size));
			return $url === false ? $path : (string) $url;
		}

		protected static function thumbnailDirName()
		{
			return ThumbnailUrl::directoryName();
		}

		protected static function folderRow($path, $abs)
		{
			$count = 0;
			$items = @scandir($abs);
			if ($items !== false) {
				foreach ($items as $item) {
					if (self::isVisibleEntry($item)) {
						$count++;
					}
				}
			}

			return array(
				'name' => basename($path),
				'path' => $path,
				'parent' => self::parentDir($path),
				'count' => $count,
			);
		}

		protected static function searchTree($dir, $abs, $q, $type, array &$folders, array &$files)
		{
			$rootLength = strlen(rtrim($abs, DIRECTORY_SEPARATOR));
			$directory = new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS);
			$visible = new \RecursiveCallbackFilterIterator($directory, function ($item) {
				return !$item->isLink() && self::isVisibleEntry($item->getFilename());
			});
			$iterator = new \RecursiveIteratorIterator(
				$visible,
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $item) {
				$name = $item->getFilename();
				$relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), $rootLength)), '/');
				$path = rtrim($dir, '/') . '/' . $relative;
				if ($item->isDir()) {
					if (self::matches($name, $q, '', true)) {
						$folders[] = self::folderRow($path, $item->getPathname());
					}

					continue;
				}

				if ($item->isFile() && self::matches($name, $q, $type, false)) {
					$files[] = self::fileRow($path, $item->getPathname());
				}
			}
		}

		protected static function stats($absRoot)
		{
			return Cache::rememberTagged(
				CacheKey::make('media', array('stats')),
				300,
				CacheKey::tags('media'),
				function () use ($absRoot) {
					return self::calculateStats($absRoot);
				}
			);
		}

		protected static function calculateStats($absRoot)
		{
			$out = array('files' => 0, 'folders' => 0, 'images' => 0, 'size' => 0, 'size_label' => '0 B');
			if (!is_dir($absRoot)) {
				return $out;
			}

			$directory = new \RecursiveDirectoryIterator($absRoot, \FilesystemIterator::SKIP_DOTS);
			$visible = new \RecursiveCallbackFilterIterator($directory, function ($item) {
				return self::isVisibleEntry($item->getFilename());
			});
			$it = new \RecursiveIteratorIterator(
				$visible,
				\RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ($it as $item) {
				if ($item->isDir()) {
					$out['folders']++;
				} elseif ($item->isFile()) {
					$out['files']++;
					$out['size'] += (int) $item->getSize();
					if (in_array(strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION)), self::$imageExt, true)) {
						$out['images']++;
					}
				}
			}

			$out['size_label'] = self::formatBytes($out['size']);
			return $out;
		}

		protected static function invalidateStats()
		{
			Cache::forgetTag(CacheKey::tag('media'));
		}

		protected static function isVisibleEntry($name)
		{
			$name = (string) $name;
			if ($name === '' || $name === '.' || $name === '..') {
				return false;
			}

			if ($name[0] === '.') {
				return false;
			}

			return !in_array(strtolower($name), self::$hiddenEntries, true);
		}

		protected static function isBrowseablePath($path)
		{
			$path = self::normalize($path, '');
			if ($path === '' || !self::isAllowedPath($path)) {
				return false;
			}

			if ($path === self::ROOT) {
				return true;
			}

			$parts = explode('/', trim($path, '/'));
			foreach ($parts as $part) {
				if ($part === 'uploads') {
					continue;
				}

				if (!self::isVisibleEntry($part)) {
					return false;
				}
			}

			return true;
		}

		protected static function matches($name, $q, $type, $isDir)
		{
			if ($q !== '' && mb_stripos($name, $q, 0, 'UTF-8') === false) {
				return false;
			}

			if ($type === 'image') {
				return !$isDir && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::$imageExt, true);
			}

			if ($type === 'file') {
				return !$isDir && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::$imageExt, true);
			}

			return true;
		}

		protected static function safeName($name, $keepExt)
		{
			$name = trim((string) $name);
			$ext = $keepExt ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
			$base = $keepExt && $ext !== '' ? pathinfo($name, PATHINFO_FILENAME) : $name;
			$base = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ._-]+/u', '-', $base);
			$base = trim($base, '.-_');
			if ($base === '') {
				return '';
			}

			return $ext !== '' ? $base . '.' . $ext : $base;
		}

		protected static function uniqueName($dir, $base, $ext)
		{
			$name = $base . '.' . $ext;
			$n = 2;
			while (file_exists(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name)) {
				$name = $base . '-' . $n . '.' . $ext;
				$n++;
			}

			return $name;
		}

		protected static function removeDir($dir)
		{
			$base = realpath(self::abs(self::ROOT));
			$real = realpath($dir);
			if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
				return false;
			}

			$items = @scandir($real);
			if ($items === false) {
				return false;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				$path = $real . DIRECTORY_SEPARATOR . $item;
				if (is_dir($path)) {
					if (!self::removeDir($path)) {
						return false;
					}
				} elseif (is_file($path) && !File::delete($path)) {
					return false;
				}
			}

			return @rmdir($real);
		}

		protected static function removeDirWithCount($dir, &$files, &$dirs)
		{
			$base = realpath(self::abs(self::ROOT));
			$real = realpath($dir);
			if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
				return false;
			}

			$items = @scandir($real);
			if ($items === false) {
				return false;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				$path = $real . DIRECTORY_SEPARATOR . $item;
				if (is_dir($path)) {
					if (!self::removeDirWithCount($path, $files, $dirs)) {
						return false;
					}
				} elseif (is_file($path)) {
					if (!File::delete($path)) {
						return false;
					}

					if ($item !== ThumbnailStorage::MARKER) {
						$files++;
					}
				}
			}

			if (!@rmdir($real)) {
				return false;
			}

			$dirs++;
			return true;
		}

		protected static function deleteFileCompanions($path, $abs)
		{
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$base = pathinfo($path, PATHINFO_FILENAME);
			$dir = dirname($abs);

			self::clearGeneratedForFile($path, $abs);

			if (in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
				$webp = $dir . DIRECTORY_SEPARATOR . $base . '.webp';
				if (is_file($webp)) {
					self::deleteThumbnailsFor($webp);
					File::delete($webp);
				}
			}
		}

		protected static function clearGeneratedForFile($path, $abs)
		{
			self::deleteThumbnailsFor($abs);
			self::deleteDerivativesFor($abs);
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			if (in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
				$webp = dirname($abs) . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '.webp';
				if (is_file($webp)) {
					self::deleteThumbnailsFor($webp);
				}
			}
		}

		protected static function deleteThumbnailsFor($source)
		{
			ThumbnailStorage::removeForSource($source);
		}

		protected static function deleteDerivativesFor($source)
		{
			$dir = dirname($source) . DIRECTORY_SEPARATOR . '_derivatives';
			$base = preg_quote(pathinfo($source, PATHINFO_FILENAME), '/');
			self::deleteMatchingFiles($dir, '/^' . $base . '-(?:fit|cover|contain|stretch)-\d+x\d+\.(?:jpe?g|png|webp)$/i');
		}

		protected static function deleteMatchingFiles($dir, $pattern)
		{
			$base = realpath(self::abs(self::ROOT));
			$real = realpath($dir);
			if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_dir($real)) {
				return;
			}

			$items = @scandir($real);
			if ($items === false) {
				return;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..' || !preg_match($pattern, $item)) {
					continue;
				}

				$file = $real . DIRECTORY_SEPARATOR . $item;
				if (is_file($file)) {
					File::delete($file);
				}
			}
		}

		protected static function assertCompanionRenameAvailable($sourcePath, $targetPath)
		{
			$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
			if (!in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
				return;
			}

			$sourceWebp = self::abs(self::webpPath($sourcePath));
			$targetWebp = self::abs(self::webpPath($targetPath));
			if (is_file($sourceWebp) && $sourceWebp !== $targetWebp && file_exists($targetWebp)) {
				throw new \RuntimeException('WebP-копия с новым именем уже существует');
			}
		}

		protected static function renameWebpCompanion($sourcePath, $targetPath)
		{
			$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
			if (!in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
				return;
			}

			$sourceWebp = self::abs(self::webpPath($sourcePath));
			$targetWebp = self::abs(self::webpPath($targetPath));
			if (is_file($sourceWebp) && $sourceWebp !== $targetWebp) {
				@rename($sourceWebp, $targetWebp);
			}
		}

		protected static function sortByName($a, $b)
		{
			return strnatcasecmp($a['name'], $b['name']);
		}

		protected static function formatBytes($bytes)
		{
			$bytes = (int) $bytes;
			if ($bytes < 1024) {
				return $bytes . ' B';
			}

			if ($bytes >= 1048576) {
				return round($bytes / 1048576, 1) . ' MB';
			}

			return round($bytes / 1024, 1) . ' KB';
		}

		protected static function clamp($value, $min, $max)
		{
			$value = (int) $value;
			return max($min, min($max, $value));
		}

		protected static function format($format, $sourceExt)
		{
			$format = strtolower((string) $format);
			if ($format === '' || $format === 'original') {
				$format = $sourceExt === 'jpeg' ? 'jpg' : $sourceExt;
			}

			if ($format === 'jpeg') {
				$format = 'jpg';
			}

			if (!in_array($format, array('jpg', 'png', 'webp'), true)) {
				throw new \RuntimeException('Формат результата не поддерживается');
			}

			return $format;
		}

		protected static function loadImage($abs, $ext)
		{
			switch ($ext) {
				case 'jpg':
				case 'jpeg':
					return @imagecreatefromjpeg($abs);
				case 'png':
					return @imagecreatefrompng($abs);
				case 'gif':
					return @imagecreatefromgif($abs);
				case 'webp':
					return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : null;
			}

			return null;
		}

		protected static function cropImage($src, $sw, $sh, array $crop)
		{
			$x = min(max(0, (int) $crop['x']), max(0, $sw - 1));
			$y = min(max(0, (int) $crop['y']), max(0, $sh - 1));
			$w = min(max(1, (int) $crop['w']), $sw - $x);
			$h = min(max(1, (int) $crop['h']), $sh - $y);
			$dst = imagecreatetruecolor($w, $h);
			self::prepareCanvas($dst);
			imagecopyresampled($dst, $src, 0, 0, $x, $y, $w, $h, $w, $h);
			return $dst;
		}

		protected static function resample($src, $sw, $sh, $tw, $th, $mode)
		{
			if ($mode === 'stretch') {
				$sx = 0; $sy = 0; $cw = $sw; $ch = $sh; $dw = $tw; $dh = $th;
			} elseif ($mode === 'cover') {
				$scale = max($tw / $sw, $th / $sh);
				$cw = (int) round($tw / $scale); $ch = (int) round($th / $scale);
				$sx = (int) round(($sw - $cw) / 2); $sy = (int) round(($sh - $ch) / 2);
				$dw = $tw; $dh = $th;
			} else {
				$scale = min($tw / $sw, $th / $sh, 1);
				$dw = max(1, (int) round($sw * $scale)); $dh = max(1, (int) round($sh * $scale));
				$sx = 0; $sy = 0; $cw = $sw; $ch = $sh;
				if ($mode === 'fit') {
					$tw = $dw; $th = $dh;
				}
			}

			$dst = imagecreatetruecolor($tw, $th);
			self::prepareCanvas($dst);
			$dx = $mode === 'contain' ? (int) round(($tw - $dw) / 2) : 0;
			$dy = $mode === 'contain' ? (int) round(($th - $dh) / 2) : 0;
			imagecopyresampled($dst, $src, $dx, $dy, $sx, $sy, $dw, $dh, $cw, $ch);
			return $dst;
		}

		protected static function prepareCanvas($img)
		{
			imagealphablending($img, false);
			imagesavealpha($img, true);
			$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
			imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $transparent);
		}

		protected static function saveImage($img, $out, $format, $quality)
		{
			if ($format === 'png') {
				imagepng($img, $out, 6);
			} elseif ($format === 'webp') {
				imagewebp($img, $out, $quality);
			} else {
				imagejpeg($img, $out, $quality);
			}
		}
	}
