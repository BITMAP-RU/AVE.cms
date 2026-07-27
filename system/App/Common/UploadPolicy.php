<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/UploadPolicy.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Hooks;

	/** Baseline upload safety with extension hooks for stricter project policies. */
	class UploadPolicy
	{
		protected static $blockedExtensions = array(
			'cgi', 'css', 'htm', 'html', 'js', 'mjs', 'phar', 'php', 'php3', 'php4',
			'php5', 'php7', 'php8', 'phtml', 'pl', 'py', 'rb', 'shtml', 'sh', 'svg',
			'svgz', 'xhtml',
		);
		protected static $blockedNames = array('.htaccess', '.user.ini', 'web.config');
		protected static $sourceExtensions = array(
			'admin_media' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf', 'zip', 'rar', '7z'),
			'module_archive' => array('zip'),
			'core_update' => array('zip'),
			'content_packages' => array('json'),
			'document_import' => array('csv', 'xml', 'xls', 'xlsx'),
			'legacy_migration' => array('zip'),
			'price_import' => array('xlsx'),
			'contact_form' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'rtf', 'zip'),
		);

		public static function inspect($temporaryPath, $originalName, $size, $destination, $source)
		{
			$payload = array(
				'allowed' => true,
				'reason' => '',
				'severity' => '',
				'temporary_path' => (string) $temporaryPath,
				'original_name' => (string) $originalName,
				'size' => max(0, (int) $size),
				'destination' => (string) $destination,
				'source' => (string) $source,
			);
			$baseline = self::baselineDecision($payload);
			$filtered = Hooks::filter('file.upload.validating', $payload);
			$result = is_array($filtered) ? array_merge($payload, $filtered) : $payload;
			if (empty($baseline['allowed'])) {
				$result['allowed'] = false;
				$result['reason'] = $baseline['reason'];
				$result['severity'] = 'high';
			}

			return $result;
		}

		public static function assertAllowed($temporaryPath, $originalName, $size, $destination, $source)
		{
			$result = self::inspect($temporaryPath, $originalName, $size, $destination, $source);
			if (empty($result['allowed'])) {
				$reason = trim((string) (isset($result['reason']) ? $result['reason'] : ''));
				throw new \RuntimeException($reason !== '' ? $reason : 'Файл отклонён политикой безопасности');
			}

			return $result;
		}

		public static function stored($path, $originalName, $size, $source)
		{
			return Hooks::action('file.upload.stored', array(
				'path' => (string) $path,
				'original_name' => (string) $originalName,
				'size' => max(0, (int) $size),
				'source' => (string) $source,
			));
		}

		protected static function baselineDecision(array $payload)
		{
			$original = str_replace('\\', '/', (string) $payload['original_name']);
			$name = strtolower(basename($original));
			if ($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name) || preg_match('/[.\s]$/u', $name)) {
				return array('allowed' => false, 'reason' => 'Имя файла содержит недопустимые символы');
			}

			if ($name[0] === '.' || in_array($name, self::$blockedNames, true)) {
				return array('allowed' => false, 'reason' => 'Служебные файлы сервера загружать нельзя');
			}

			$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
			if ($extension !== '' && in_array($extension, self::$blockedExtensions, true)) {
				return array('allowed' => false, 'reason' => 'Активные и исполняемые файлы загружать нельзя');
			}

			foreach (array_filter(explode('.', $name)) as $part) {
				if (in_array($part, self::$blockedExtensions, true)) {
					return array('allowed' => false, 'reason' => 'Двойные исполняемые расширения запрещены');
				}
			}

			$source = isset($payload['source']) ? (string) $payload['source'] : '';
			if (isset(self::$sourceExtensions[$source]) && !in_array($extension, self::$sourceExtensions[$source], true)) {
				return array('allowed' => false, 'reason' => 'Этот тип файла не разрешён для выбранной загрузки');
			}

			if (self::looksLikeSvg((string) $payload['temporary_path'])) {
				return array('allowed' => false, 'reason' => 'SVG-файлы нельзя загружать в публичное хранилище');
			}

			if (!self::contentMatches((string) $payload['temporary_path'], $extension)) {
				return array('allowed' => false, 'reason' => 'Содержимое файла не соответствует его расширению');
			}

			return array('allowed' => true, 'reason' => '');
		}

		protected static function contentMatches($path, $extension)
		{
			if ($path === '' || !is_file($path) || !is_readable($path)) { return true; }
			$extension = strtolower((string) $extension);
			$imageTypes = array(
				'gif' => IMAGETYPE_GIF, 'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG,
				'png' => IMAGETYPE_PNG, 'bmp' => IMAGETYPE_BMP,
			);
			if (defined('IMAGETYPE_WEBP')) { $imageTypes['webp'] = IMAGETYPE_WEBP; }
			if (isset($imageTypes[$extension])) {
				$info = @getimagesize($path);
				return is_array($info) && isset($info[2]) && (int) $info[2] === (int) $imageTypes[$extension];
			}

			$head = self::head($path, 16);
			if ($extension === 'pdf') { return strncmp($head, '%PDF-', 5) === 0; }
			if (in_array($extension, array('zip', 'docx', 'xlsx', 'pptx'), true)) {
				return strncmp($head, "PK\x03\x04", 4) === 0 || strncmp($head, "PK\x05\x06", 4) === 0 || strncmp($head, "PK\x07\x08", 4) === 0;
			}

			if (in_array($extension, array('doc', 'xls', 'ppt'), true)) { return strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0; }
			if ($extension === 'rar') { return strncmp($head, "Rar!\x1A\x07", 6) === 0; }
			if ($extension === '7z') { return strncmp($head, "7z\xBC\xAF\x27\x1C", 6) === 0; }
			if ($extension === 'rtf') { return strncmp(ltrim($head), '{\\rtf', 5) === 0; }
			if ($extension === 'json') {
				$content = @file_get_contents($path);
				if ($content === false) { return false; }
				json_decode($content, true);
				return json_last_error() === JSON_ERROR_NONE;
			}

			return true;
		}

		protected static function head($path, $length)
		{
			$handle = @fopen($path, 'rb');
			if (!$handle) { return ''; }
			$head = (string) fread($handle, max(1, (int) $length));
			fclose($handle);
			return $head;
		}

		protected static function looksLikeSvg($path)
		{
			if ($path === '' || !is_file($path) || !is_readable($path)) { return false; }
			$handle = @fopen($path, 'rb');
			if (!$handle) { return false; }
			$head = (string) fread($handle, 8192);
			fclose($handle);
			$head = preg_replace('/^\xEF\xBB\xBF/', '', $head);
			return (bool) preg_match('/^\s*(?:<\?xml\b[^>]*>\s*)?(?:<!--.*?-->\s*)*<svg\b/is', $head);
		}
	}
