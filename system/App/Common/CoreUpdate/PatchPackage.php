<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CoreUpdate/PatchPackage.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\CoreUpdate;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Signature, manifest and payload verifier for owner-issued core patches. */
	class PatchPackage
	{
		const FORMAT = 'ave-core-patch-v1';
		const MAX_ARCHIVE_BYTES = 52428800;
		const MAX_FILES = 5000;
		const MAX_EXTRACTED_BYTES = 157286400;

		public static function inspect($archive, $publicKey)
		{
			if (!class_exists('ZipArchive')) { throw new \RuntimeException('Для обновлений требуется PHP-расширение zip'); }
			if (!is_file($archive) || filesize($archive) < 1 || filesize($archive) > self::MAX_ARCHIVE_BYTES) {
				throw new \RuntimeException('Размер архива обновления недопустим');
			}

			$zip = new \ZipArchive();
			if ($zip->open($archive) !== true) { throw new \RuntimeException('Архив обновления повреждён'); }
			try {
				$manifestRaw = $zip->getFromName('PATCH-MANIFEST.json');
				$signatureRaw = $zip->getFromName('PATCH-SIGNATURE.txt');
				if ($manifestRaw === false || $signatureRaw === false) {
					throw new \RuntimeException('В архиве отсутствует подписанный manifest');
				}

				self::verifySignature($manifestRaw, $signatureRaw, $publicKey);
				$manifest = json_decode($manifestRaw, true);
				$manifest = self::normalizeManifest($manifest);
				self::verifyEntries($zip, $manifest);
				$manifest['manifest_sha256'] = hash('sha256', $manifestRaw);
				$manifest['archive_sha256'] = hash_file('sha256', $archive);
				return $manifest;
			} finally {
				$zip->close();
			}
		}

		public static function extract($archive, $directory, array $manifest)
		{
			if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
				throw new \RuntimeException('Не удалось создать staging-каталог обновления');
			}

			$zip = new \ZipArchive();
			if ($zip->open($archive) !== true) { throw new \RuntimeException('Архив обновления повреждён'); }
			try {
				foreach ($manifest['files'] as $file) {
					self::extractEntry($zip, 'payload/' . $file['path'], $directory . '/payload/' . $file['path'], $file['sha256']);
				}

				foreach ($manifest['migrations'] as $migration) {
					self::extractEntry($zip, 'migrations/' . $migration['file'], $directory . '/migrations/' . $migration['file'], $migration['sha256']);
				}
			} finally {
				$zip->close();
			}
		}

		protected static function normalizeManifest($manifest)
		{
			if (!is_array($manifest) || !isset($manifest['format'], $manifest['owner'], $manifest['from'], $manifest['to'])
				|| $manifest['format'] !== self::FORMAT || $manifest['owner'] !== 'AVE.cms') {
				throw new \RuntimeException('Формат core-патча не поддерживается');
			}

			foreach (array('from', 'to') as $side) {
				if (!is_array($manifest[$side]) || empty($manifest[$side]['version']) || !isset($manifest[$side]['build'])) {
					throw new \RuntimeException('В manifest отсутствует версия ' . $side);
				}

				$manifest[$side]['version'] = trim((string) $manifest[$side]['version']);
				$manifest[$side]['build'] = trim((string) $manifest[$side]['build']);
				$fingerprint = isset($manifest[$side]['core_fingerprint']) ? strtolower(trim((string) $manifest[$side]['core_fingerprint'])) : '';
				if ($fingerprint !== '' && !preg_match('/^[a-f0-9]{64}$/', $fingerprint)) { throw new \RuntimeException('Некорректный fingerprint релиза'); }
				$manifest[$side]['core_fingerprint'] = $fingerprint;
			}

			$manifest['files'] = self::normalizeFiles(isset($manifest['files']) ? $manifest['files'] : array());
			$manifest['deletions'] = self::normalizeDeletions(isset($manifest['deletions']) ? $manifest['deletions'] : array());
			$manifest['migrations'] = self::normalizeMigrations(isset($manifest['migrations']) ? $manifest['migrations'] : array());
			$manifest['cumulative'] = !empty($manifest['cumulative']);
			if (count($manifest['files']) + count($manifest['deletions']) + count($manifest['migrations']) > self::MAX_FILES) {
				throw new \RuntimeException('Патч содержит слишком много операций');
			}

			$filePaths = array();
			foreach ($manifest['files'] as $file) { $filePaths[$file['path']] = true; }
			foreach ($manifest['deletions'] as $deletion) {
				if (isset($filePaths[$deletion['path']])) { throw new \RuntimeException('Файл нельзя одновременно заменить и удалить'); }
			}

			$manifest['requirements'] = isset($manifest['requirements']) && is_array($manifest['requirements']) ? $manifest['requirements'] : array();
			$manifest['notes'] = trim(isset($manifest['notes']) ? (string) $manifest['notes'] : '');
			$manifest['id'] = trim(isset($manifest['id']) ? (string) $manifest['id'] : '');
			if (!preg_match('/^[A-Za-z0-9._-]{6,160}$/', $manifest['id'])) {
				throw new \RuntimeException('Некорректный идентификатор core-патча');
			}

			return $manifest;
		}

		protected static function normalizeFiles($files)
		{
			if (!is_array($files) || count($files) > self::MAX_FILES) { throw new \RuntimeException('Некорректный список файлов патча'); }
			$result = array();
			foreach ($files as $file) {
				if (!is_array($file) || !isset($file['path'], $file['sha256'], $file['size'])) { throw new \RuntimeException('Некорректная запись файла патча'); }
				$path = ReleaseState::normalizeLogicalPath($file['path']);
				if (ReleaseState::protectedPath($path) || isset($result[$path])) { throw new \RuntimeException('Запрещённый или повторяющийся путь патча: ' . $path); }
				$checksum = strtolower((string) $file['sha256']);
				$before = isset($file['before_sha256']) && $file['before_sha256'] !== null ? strtolower((string) $file['before_sha256']) : null;
				if (!preg_match('/^[a-f0-9]{64}$/', $checksum) || ($before !== null && !preg_match('/^[a-f0-9]{64}$/', $before))) { throw new \RuntimeException('Некорректная checksum файла патча'); }
				$size = (int) $file['size'];
				if ($size < 0 || $size > self::MAX_EXTRACTED_BYTES) { throw new \RuntimeException('Некорректный размер файла патча'); }
				$result[$path] = array('path' => $path, 'before_sha256' => $before, 'sha256' => $checksum, 'size' => $size, 'mode' => isset($file['mode']) ? ((int) $file['mode'] & 0777) : 0644);
			}

			return array_values($result);
		}

		protected static function normalizeDeletions($deletions)
		{
			if (!is_array($deletions)) { throw new \RuntimeException('Некорректный список удалений патча'); }
			$result = array();
			foreach ($deletions as $file) {
				if (!is_array($file) || empty($file['path']) || empty($file['before_sha256'])) { throw new \RuntimeException('Удаление без исходной checksum запрещено'); }
				$path = ReleaseState::normalizeLogicalPath($file['path']);
				$checksum = strtolower((string) $file['before_sha256']);
				if (ReleaseState::protectedPath($path) || isset($result[$path]) || !preg_match('/^[a-f0-9]{64}$/', $checksum)) { throw new \RuntimeException('Некорректное удаление: ' . $path); }
				$result[$path] = array('path' => $path, 'before_sha256' => $checksum);
			}

			return array_values($result);
		}

		protected static function normalizeMigrations($migrations)
		{
			if (!is_array($migrations) || count($migrations) > self::MAX_FILES) { throw new \RuntimeException('Некорректный список миграций'); }
			$result = array();
			$files = array();
			foreach ($migrations as $migration) {
				$module = is_array($migration) && isset($migration['module']) ? trim((string) $migration['module']) : 'core_update';
				$id = is_array($migration) && isset($migration['id']) ? trim((string) $migration['id']) : '';
				$file = is_array($migration) && isset($migration['file']) ? trim((string) $migration['file']) : '';
				$checksum = is_array($migration) && isset($migration['sha256']) ? strtolower((string) $migration['sha256']) : '';
				$key = $module . "\0" . $id;
				if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $module)
					|| !preg_match('/^[A-Za-z0-9._-]{3,160}$/', $id) || !preg_match('/^[A-Za-z0-9._-]+\.(?:php|sql)$/', $file)
					|| !preg_match('/^[a-f0-9]{64}$/', $checksum) || isset($result[$key]) || isset($files[$file])) {
					throw new \RuntimeException('Некорректная миграция core-патча');
				}

				$files[$file] = true;
				$result[$key] = array('module' => $module, 'id' => $id, 'file' => $file, 'sha256' => $checksum);
				if (array_key_exists('backup_tables', $migration)) {
					if (!is_array($migration['backup_tables'])) { throw new \RuntimeException('Некорректный список таблиц миграции'); }
					$tables = array();
					foreach ($migration['backup_tables'] as $table) {
						$table = trim((string) $table);
						if (!preg_match('/^\{\{(?:prefix|database_prefix|catalog_prefix|content_prefix|module_prefix|basket_prefix|contacts_prefix|public_shell_prefix|public_user_prefix|system_prefix)\}\}_[A-Za-z0-9_]+$/', $table)) {
							throw new \RuntimeException('Некорректная таблица миграции');
						}

						$tables[$table] = $table;
					}

					ksort($tables, SORT_STRING);
					$result[$key]['backup_tables'] = array_values($tables);
				}
			}

			return array_values($result);
		}

		protected static function verifyEntries(\ZipArchive $zip, array $manifest)
		{
			if ($zip->numFiles > self::MAX_FILES * 2 + 20) { throw new \RuntimeException('ZIP core-патча содержит слишком много записей'); }
			$total = 0;
			$expected = array('PATCH-MANIFEST.json' => null, 'PATCH-SIGNATURE.txt' => null);
			foreach ($manifest['files'] as $file) { $expected['payload/' . $file['path']] = $file['size']; $total += $file['size']; }
			foreach ($manifest['migrations'] as $migration) { $expected['migrations/' . $migration['file']] = null; }
			if ($total > self::MAX_EXTRACTED_BYTES) { throw new \RuntimeException('Распакованный патч превышает лимит'); }

			for ($index = 0; $index < $zip->numFiles; $index++) {
				$stat = $zip->statIndex($index);
				$name = isset($stat['name']) ? str_replace('\\', '/', (string) $stat['name']) : '';
				if ($name === '' || substr($name, -1) === '/') { throw new \RuntimeException('Core-патч содержит незаявленный каталог'); }
				if (!array_key_exists($name, $expected)) { throw new \RuntimeException('Неожиданный файл в core-патче: ' . $name); }
				$size = isset($stat['size']) ? (int) $stat['size'] : -1;
				$compressed = isset($stat['comp_size']) ? (int) $stat['comp_size'] : 0;
				if ($size < 0 || $size > self::MAX_EXTRACTED_BYTES || ($expected[$name] !== null && $size !== $expected[$name])) { throw new \RuntimeException('Размер ZIP-записи не совпадает с manifest: ' . $name); }
				if ($size > 1048576 && $compressed > 0 && $size > $compressed * 200) { throw new \RuntimeException('Core-патч содержит подозрительно сжатый файл'); }
				$total += $expected[$name] === null ? $size : 0;
				if ($total > self::MAX_EXTRACTED_BYTES) { throw new \RuntimeException('Распакованный патч превышает лимит'); }
				unset($expected[$name]);
			}

			if ($expected) { throw new \RuntimeException('Core-патч содержит не все заявленные файлы'); }
		}

		protected static function verifySignature($manifest, $signatureEncoded, $publicKey)
		{
			$signature = base64_decode(trim((string) $signatureEncoded), true);
			$key = function_exists('openssl_pkey_get_public') ? openssl_pkey_get_public(trim((string) $publicKey)) : false;
			if ($signature === false || !$key || openssl_verify($manifest, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
				if (is_resource($key)) { openssl_free_key($key); }
				throw new \RuntimeException('Подпись core-патча не прошла проверку');
			}

			if (is_resource($key)) { openssl_free_key($key); }
		}

		protected static function extractEntry(\ZipArchive $zip, $entry, $target, $checksum)
		{
			$content = $zip->getFromName($entry);
			if ($content === false || !hash_equals($checksum, hash('sha256', $content))) { throw new \RuntimeException('Checksum не совпала: ' . $entry); }
			$directory = dirname($target);
			if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) { throw new \RuntimeException('Не удалось создать staging-путь'); }
			if (file_put_contents($target, $content, LOCK_EX) === false) { throw new \RuntimeException('Не удалось распаковать core-патч'); }
			@chmod($target, 0600);
		}
	}
