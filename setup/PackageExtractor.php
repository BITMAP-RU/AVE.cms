<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         setup/PackageExtractor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('AVE_SETUP') || die('Direct access to this location is not allowed.');

	/** Порционная и проверяемая распаковка runtime-архива до запуска установщика. */
	class AvePackageExtractor
	{
		const SESSION_KEY = 'setup_package_extraction';
		const BATCH_FILES = 60;
		const MAX_FILE_BYTES = 134217728;
		const MAX_ARCHIVE_BYTES = 536870912;

		protected $root;
		protected $payload;
		protected $checksumFile;

		public function __construct($root)
		{
			$canonicalRoot = realpath((string) $root);
			if ($canonicalRoot === false || !is_dir($canonicalRoot)) {
				throw new RuntimeException('Корневой каталог установки не найден');
			}

			$this->root = rtrim(str_replace('\\', '/', $canonicalRoot), '/');
			$this->payload = $this->root . '/setup/runtime.zip';
			$this->checksumFile = $this->root . '/setup/runtime.sha256';
		}

		public function available()
		{
			return is_file($this->payload);
		}

		public function step()
		{
			if (!class_exists('ZipArchive')) {
				throw new RuntimeException('Для распаковки требуется расширение PHP ZipArchive');
			}

			$state = isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])
				? $_SESSION[self::SESSION_KEY]
				: $this->initialize();
			$this->assertPayload($state);

			$zip = new ZipArchive();
			if ($zip->open($this->payload) !== true) {
				throw new RuntimeException('Не удалось открыть runtime-архив');
			}

			$startedAt = microtime(true);
			$processed = 0;
			try {
				while ($state['index'] < $zip->numFiles && $processed < self::BATCH_FILES && microtime(true) - $startedAt < 1.8) {
					$this->extractEntry($zip, (int) $state['index'], $state);
					$state['index']++;
					$processed++;
				}
			} finally {
				$zip->close();
			}

			$state['updated_at'] = time();
			$_SESSION[self::SESSION_KEY] = $state;
			if ($state['index'] >= $state['entries']) {
				$this->complete($state);
				return array(
					'success' => true,
					'complete' => true,
					'progress' => 100,
					'current' => $state['entries'],
					'total' => $state['entries'],
					'file' => 'Runtime AVE.cms подготовлен',
					'redirect' => './',
				);
			}

			return array(
				'success' => true,
				'complete' => false,
				'progress' => max(1, min(99, (int) floor(($state['index'] / max(1, $state['entries'])) * 100))),
				'current' => $state['index'],
				'total' => $state['entries'],
				'file' => $state['current_file'],
			);
		}

		protected function initialize()
		{
			if (!is_file($this->payload) || (int) filesize($this->payload) <= 0
				|| (int) filesize($this->payload) > self::MAX_ARCHIVE_BYTES) {
				throw new RuntimeException('Размер runtime-архива недопустим');
			}

			if (!is_file($this->checksumFile)) {
				throw new RuntimeException('Рядом с архивом отсутствует runtime.sha256');
			}

			$expectedArchive = strtolower(trim((string) file_get_contents($this->checksumFile)));
			if (!preg_match('/^[a-f0-9]{64}$/', $expectedArchive)) {
				throw new RuntimeException('Контрольная сумма runtime-архива имеет неверный формат');
			}

			$actualArchive = hash_file('sha256', $this->payload);
			if ($actualArchive === false || !hash_equals($expectedArchive, strtolower($actualArchive))) {
				throw new RuntimeException('Runtime-архив повреждён: SHA-256 не совпадает');
			}

			$zip = new ZipArchive();
			if ($zip->open($this->payload) !== true) {
				throw new RuntimeException('Не удалось открыть runtime-архив');
			}

			try {
				$manifestRaw = $zip->getFromName('PACKAGE-MANIFEST.json');
				$manifest = $manifestRaw !== false ? json_decode($manifestRaw, true) : null;
				if (!is_array($manifest) || !isset($manifest['format']) || $manifest['format'] !== 'ave-core-package-v2'
					|| empty($manifest['files']) || !is_array($manifest['files'])) {
					throw new RuntimeException('Runtime-архив не содержит корректный manifest AVE.cms');
				}

				foreach ($manifest['files'] as $path => $checksum) {
					$this->normalizePath($path);
					if (!preg_match('/^[a-f0-9]{64}$/', (string) $checksum)) {
						throw new RuntimeException('Некорректная сумма файла в manifest: ' . $path);
					}
				}

				$entries = array();
				for ($index = 0; $index < $zip->numFiles; $index++) {
					$name = $this->normalizePath($zip->getNameIndex($index));
					if (isset($entries[$name])) {
						throw new RuntimeException('Путь продублирован в runtime-архиве: ' . $name);
					}

					$entries[$name] = true;
				}

				if (!isset($entries['PACKAGE-MANIFEST.json'])) {
					throw new RuntimeException('Runtime-архив не содержит PACKAGE-MANIFEST.json');
				}

				foreach ($manifest['files'] as $path => $checksum) {
					if (!isset($entries[$path])) {
						throw new RuntimeException('Заявленный файл отсутствует в архиве: ' . $path);
					}
				}

				return array(
					'index' => 0,
					'entries' => $zip->numFiles,
					'archive_size' => (int) filesize($this->payload),
					'archive_mtime' => (int) filemtime($this->payload),
					'archive_sha256' => $expectedArchive,
					'manifest_sha256' => hash('sha256', $manifestRaw),
					'files' => $manifest['files'],
					'current_file' => '',
					'created_at' => time(),
				);
			} finally {
				$zip->close();
			}
		}

		protected function assertPayload(array $state)
		{
			if (!is_file($this->payload)
				|| (int) filesize($this->payload) !== (int) $state['archive_size']
				|| (int) filemtime($this->payload) !== (int) $state['archive_mtime']) {
				throw new RuntimeException('Runtime-архив изменился во время распаковки');
			}
		}

		protected function extractEntry(ZipArchive $zip, $index, array &$state)
		{
			$stat = $zip->statIndex($index);
			if (!is_array($stat) || !isset($stat['name'])) {
				throw new RuntimeException('Не удалось прочитать элемент runtime-архива');
			}

			$rawName = (string) $stat['name'];
			$name = $this->normalizePath($rawName);
			$state['current_file'] = $name;
			$isDirectory = substr($rawName, -1) === '/';
			$this->assertRegularEntry($zip, $index, $name, $isDirectory);
			$target = $this->root . '/' . $name;
			if ($isDirectory) {
				$this->ensureDirectory(rtrim($target, '/'));
				return;
			}

			if (!isset($state['files'][$name]) && $name !== 'PACKAGE-MANIFEST.json') {
				throw new RuntimeException('Файл не заявлен в manifest: ' . $name);
			}

			$size = isset($stat['size']) ? (int) $stat['size'] : 0;
			if ($size < 0 || $size > self::MAX_FILE_BYTES) {
				throw new RuntimeException('Недопустимый размер файла: ' . $name);
			}

			$this->ensureDirectory(dirname($target));
			if (is_link($target) || is_dir($target)) {
				throw new RuntimeException('Целевой путь занят небезопасным объектом: ' . $name);
			}

			$source = $zip->getStream($rawName);
			if (!$source) {
				throw new RuntimeException('Не удалось прочитать файл из архива: ' . $name);
			}

			$temporary = $target . '.setup-' . bin2hex(random_bytes(4));
			$output = @fopen($temporary, 'xb');
			if (!$output) {
				fclose($source);
				throw new RuntimeException('Не удалось создать файл: ' . $name);
			}

			$hash = hash_init('sha256');
			$written = 0;
			try {
				while (!feof($source)) {
					$chunk = fread($source, 1048576);
					if ($chunk === false) { throw new RuntimeException('Ошибка чтения: ' . $name); }
					if ($chunk === '') { continue; }
					$length = strlen($chunk);
					if (fwrite($output, $chunk) !== $length) { throw new RuntimeException('Ошибка записи: ' . $name); }
					hash_update($hash, $chunk);
					$written += $length;
				}
			} catch (Throwable $e) {
				fclose($source);
				fclose($output);
				@unlink($temporary);
				throw $e;
			}

			fclose($source);
			fclose($output);
			$expected = $name === 'PACKAGE-MANIFEST.json'
				? (string) $state['manifest_sha256']
				: strtolower((string) $state['files'][$name]);
			if ($written !== $size || !hash_equals($expected, hash_final($hash))) {
				@unlink($temporary);
				throw new RuntimeException('Контрольная сумма файла не совпала: ' . $name);
			}

			if (!@rename($temporary, $target)) {
				@unlink($temporary);
				throw new RuntimeException('Не удалось завершить запись файла: ' . $name);
			}

			@chmod($target, $this->entryMode($zip, $index));
		}

		protected function complete(array $state)
		{
			foreach ($state['files'] as $path => $checksum) {
				$target = $this->root . '/' . $path;
				if (!is_file($target) || !hash_equals((string) $checksum, (string) hash_file('sha256', $target))) {
					throw new RuntimeException('Финальная проверка не пройдена: ' . $path);
				}
			}

			if (!@unlink($this->payload) && is_file($this->payload)) {
				throw new RuntimeException('Файлы распакованы, но runtime.zip не удалось удалить');
			}

			if (!@unlink($this->checksumFile) && is_file($this->checksumFile)) {
				throw new RuntimeException('Файлы распакованы, но runtime.sha256 не удалось удалить');
			}

			unset($_SESSION[self::SESSION_KEY]);
		}

		protected function normalizePath($path)
		{
			$path = str_replace('\\', '/', (string) $path);
			if ($path === '' || strpos($path, "\0") !== false || $path[0] === '/' || preg_match('/^[A-Za-z]:/', $path)) {
				throw new RuntimeException('Некорректный путь в runtime-архиве');
			}

			$segments = explode('/', trim($path, '/'));
			foreach ($segments as $segment) {
				if ($segment === '' || $segment === '.' || $segment === '..') {
					throw new RuntimeException('Небезопасный путь в runtime-архиве: ' . $path);
				}
			}

			return implode('/', $segments) . (substr($path, -1) === '/' ? '/' : '');
		}

		protected function assertRegularEntry(ZipArchive $zip, $index, $name, $directory)
		{
			$opsys = 0;
			$attributes = 0;
			if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
				$type = ($attributes >> 16) & 0170000;
				if ($type !== 0 && $type !== 0100000 && !($directory && $type === 0040000)) {
					throw new RuntimeException('Ссылки и специальные файлы запрещены: ' . $name);
				}
			}
		}

		protected function entryMode(ZipArchive $zip, $index)
		{
			$opsys = 0;
			$attributes = 0;
			if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
				$mode = ($attributes >> 16) & 0777;
				if ($mode > 0) { return $mode; }
			}

			return 0644;
		}

		protected function ensureDirectory($directory)
		{
			$directory = rtrim(str_replace('\\', '/', (string) $directory), '/');
			if ($directory !== $this->root && strpos($directory . '/', $this->root . '/') !== 0) {
				throw new RuntimeException('Каталог назначения находится за пределами установки');
			}

			$relative = trim(substr($directory, strlen($this->root)), '/');
			$current = $this->root;
			foreach ($relative === '' ? array() : explode('/', $relative) as $segment) {
				$current .= '/' . $segment;
				if (is_link($current)) {
					throw new RuntimeException('Каталог назначения является ссылкой: ' . $segment);
				}

				if (!is_dir($current) && !@mkdir($current, 0775) && !is_dir($current)) {
					throw new RuntimeException('Не удалось создать каталог: ' . $current);
				}
			}
		}
	}
