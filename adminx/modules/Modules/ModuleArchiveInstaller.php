<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/ModuleArchiveInstaller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Modules;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\UploadPolicy;

	use App\Common\CompiledModuleRegistry;
	use App\Common\Loader\Load;
	use App\Common\ModuleManager;
	use App\Content\Packages\PackageDependencyContext;
	use App\Helpers\Dir;

	/** Безопасная доставка Adminx-модуля или составного public/admin-пакета из ZIP. */
	class ModuleArchiveInstaller
	{
		const MAX_ARCHIVE_BYTES = 26214400;
		const MAX_FILES = 1500;
		const MAX_FILE_BYTES = 26214400;
		const MAX_UNPACKED_BYTES = 104857600;

		protected static $lockDepth = 0;
		protected static $dependencyStack = array();

		public static function install(array $file)
		{
			self::assertUpload($file);
			return self::installStoredArchive(self::storeUploadedArchive($file));
		}

		public static function installDownloaded($archive, $expectedCode, $expectedVersion)
		{
			$archive = self::assertStoredArchive($archive);
			self::assertExpectedModule($expectedCode, $expectedVersion);
			return self::installStoredArchive($archive, $expectedCode, $expectedVersion);
		}

		public static function updateDownloaded($archive, $expectedCode, $expectedVersion)
		{
			$archive = self::assertStoredArchive($archive);
			self::assertExpectedModule($expectedCode, $expectedVersion);
			return self::installStoredArchive($archive, $expectedCode, $expectedVersion, true);
		}

		protected static function installStoredArchive($archive, $expectedCode = '', $expectedVersion = '', $replace = false)
		{

			$adminModulesRoot = ADMINX_PATH . DS . 'modules';
			$packagesRoot = BASEPATH . DS . 'modules';
			self::assertWritableRoot($adminModulesRoot);
			self::assertWritableRoot($packagesRoot);

			$lock = self::acquireInstallLock();

			$stage = '';
			$content = '';
			$target = '';
			$backup = '';
			$replacing = false;
			$validated = false;
			$wasInstalled = false;
			$dependencyResults = array();
			$packageCode = '';

			try {
				$stage = BASEPATH . DS . 'tmp' . DS . '.module-install-' . bin2hex(random_bytes(8));
				$content = $stage . DS . 'content';
				if (!@mkdir($content, 0755, true) && !is_dir($content)) {
					throw new \RuntimeException('Не удалось создать временный каталог установки');
				}

				self::extract($archive, $content);
				$sourceInfo = self::moduleRoot($content);
				$source = $sourceInfo['path'];
				$isPackage = $sourceInfo['kind'] === 'package';
				$packageManifest = self::packageManifest($source, $sourceInfo['kind']);
				$packageCode = (string) $packageManifest['code'];
				if ($expectedCode !== '' && !hash_equals($expectedCode, (string) $packageManifest['code'])) {
					throw new \RuntimeException('Код скачанного пакета не совпадает с записью удалённого каталога');
				}

				if ($expectedVersion !== '' && !hash_equals($expectedVersion, (string) $packageManifest['version'])) {
					throw new \RuntimeException('Версия скачанного пакета не совпадает с записью удалённого каталога');
				}

				$dependencyResults = self::installEmbeddedDependencies($source, $packageManifest);
				if (is_dir($source . DS . 'packages')) {
					Dir::delete($source . DS . 'packages');
				}

				$folder = (string) $packageManifest['folder'];
				$modulesRoot = $isPackage ? $packagesRoot : $adminModulesRoot;
				$target = $modulesRoot . DS . $folder;
				if ($replace) {
					$previousModule = ModuleManager::get((string) $packageManifest['code']);
					$wasInstalled = $previousModule && !empty($previousModule['installed']);
					self::assertReplacementTarget($target, $packageManifest['code'], $isPackage);
					$backup = $stage . DS . 'previous';
					if (!@rename($target, $backup)) {
						throw new \RuntimeException('Не удалось подготовить резервную копию файлов модуля');
					}

					$replacing = true;
				} else {
					self::assertFolderAvailable($modulesRoot, $folder);
				}

				if (!@rename($source, $target)) {
					if ($replacing && is_dir($backup)) {
						@rename($backup, $target);
						self::refreshCodeCache($target);
						$replacing = false;
					}

					throw new \RuntimeException('Не удалось перенести файлы модуля');
				}

				if ($replace) { self::refreshCodeCache($target); }

				$existingCodes = array();
				foreach (ModuleManager::all() as $module) {
					$existingCodes[(string) $module['code']] = true;
				}

				if ($isPackage) {
					$class = \App\Common\PackageModuleRuntime::classify($folder);
					if (is_dir($target . DS . 'app')) {
						Load::regNamespace('App\\Modules\\' . $class, $target . DS . 'app');
					}

					Load::regNamespace('App\\Adminx\\Packages\\' . $class, $target . DS . 'admin');
				} else {
					Load::regNamespace('App\\Adminx\\' . $folder, $target);
				}

				$manifest = $target . DS . ($isPackage ? 'admin' . DS : '') . 'module.php';
				$descriptor = include $manifest;
				if (!is_array($descriptor) || empty($descriptor['code'])) {
					throw new \RuntimeException('module.php должен возвращать descriptor с кодом модуля');
				}

				$code = (string) $descriptor['code'];
				if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)) {
					throw new \RuntimeException('Некорректный code в module.php');
				}

				if ($isPackage && $folder !== $code) {
					throw new \RuntimeException('Имя папки составного пакета должно совпадать с его code');
				}

				if ($code !== (string) $packageManifest['code']) {
					throw new \RuntimeException('Code в module.php не совпадает с официальным manifest пакета');
				}

				$version = isset($descriptor['version']) ? (string) $descriptor['version'] : '';
				if ($version !== (string) $packageManifest['version']) {
					throw new \RuntimeException('Версия module.php не совпадает с официальным manifest пакета');
				}

				if (isset($existingCodes[$code]) && !$replace) {
					throw new \RuntimeException('Модуль с кодом «' . $code . '» уже существует');
				}

				if (empty($descriptor['lifecycle']['managed'])) {
					throw new \RuntimeException('Архивный модуль должен объявлять lifecycle.managed = true');
				}

				if (!empty($descriptor['lifecycle']['adopt_existing'])) {
					throw new \RuntimeException('adopt_existing запрещён для установки нового модуля из ZIP');
				}

				if (empty($descriptor['admin_extension']) || !is_array($descriptor['admin_extension'])) {
					throw new \RuntimeException('Архив не содержит admin_extension для новой админки');
				}

				$descriptor['_base_path'] = $isPackage ? $target . DS . 'admin' : $target;
				ModuleManager::register($descriptor);
				if ($replace) {
					self::invalidatePackageRegistry($isPackage);
					$validated = true;
					if ($wasInstalled) { ModuleManager::update($code); }
					if ($backup !== '' && is_dir($backup)) { Dir::delete($backup); }
					$result = self::result($code, $folder, $isPackage, $descriptor, $packageManifest, $dependencyResults);
					PackageDependencyContext::clear($packageCode);
					return $result;
				}

				$validated = true;
				ModuleManager::install($code);
				self::invalidatePackageRegistry($isPackage);
				$result = self::result($code, $folder, $isPackage, $descriptor, $packageManifest, $dependencyResults);
				PackageDependencyContext::clear($packageCode);
				return $result;
			} catch (\Throwable $e) {
				// После успешной проверки manifest папку оставляем: миграции могли
				// примениться частично, и штатный повтор установки должен их продолжить.
				if (!$validated && $target !== '' && is_dir($target)) {
					Dir::delete($target);
				}

				if (!$validated && $replacing && $backup !== '' && is_dir($backup)) {
					@rename($backup, $target);
					self::refreshCodeCache($target);
					self::invalidatePackageRegistry($isPackage);
				}

				if ($packageCode !== '') {
					PackageDependencyContext::clear($packageCode);
				}

				throw $e;
			} finally {
				if ($stage !== '' && is_dir($stage)) { Dir::delete($stage); }
				if ($archive !== '' && is_file($archive)) { @unlink($archive); }
				self::releaseInstallLock($lock);
			}
		}

		protected static function assertExpectedModule(&$code, &$version)
		{
			$code = strtolower(trim((string) $code));
			$version = trim((string) $version);
			if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)
				|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._-]+)?$/', $version)) {
				throw new \InvalidArgumentException('Удалённый каталог передал некорректный модуль');
			}
		}

		protected static function result(
			$code,
			$folder,
			$isPackage,
			array $descriptor,
			array $packageManifest,
			array $dependencies = array()
		)
		{
			$meta = ModuleManager::get($code);
			return array(
				'code' => $code,
				'name' => isset($meta['name']) ? (string) $meta['name'] : $code,
				'version' => isset($meta['version']) ? (string) $meta['version'] : '',
				'folder' => $folder,
				'package' => (bool) $isPackage,
				'installed' => !empty($meta['installed']),
				'admin_url' => isset($descriptor['admin_extension']['url']) ? (string) $descriptor['admin_extension']['url'] : '',
				'manifest_sha256' => (string) $packageManifest['manifest_sha256'],
				'dependencies' => $dependencies,
			);
		}

		protected static function assertStoredArchive($archive)
		{
			$archive = realpath((string) $archive);
			$tmpRoot = realpath(BASEPATH . DS . 'tmp');
			if ($archive === false || $tmpRoot === false || !is_file($archive)
				|| strpos(str_replace('\\', '/', $archive), rtrim(str_replace('\\', '/', $tmpRoot), '/') . '/') !== 0) {
				throw new \RuntimeException('Удалённый ZIP находится вне защищённого временного каталога');
			}

			$size = (int) filesize($archive);
			if (strtolower(pathinfo($archive, PATHINFO_EXTENSION)) !== 'zip'
				|| $size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
				throw new \RuntimeException('Скачанный ZIP имеет недопустимый размер или формат');
			}

			return $archive;
		}

		protected static function storeUploadedArchive(array $file)
		{
			$directory = BASEPATH . DS . 'tmp';
			if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
				throw new \RuntimeException('Не удалось создать локальный каталог загрузки: ' . $directory);
			}

			if (!is_writable($directory)) {
				throw new \RuntimeException('Локальный каталог загрузки недоступен для записи: ' . $directory);
			}

			$target = $directory . DS . '.module-upload-' . bin2hex(random_bytes(8)) . '.zip';
			if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
				throw new \RuntimeException('Не удалось перенести ZIP из временного каталога сервера');
			}

			@chmod($target, 0600);
			if (!is_file($target) || (int) filesize($target) !== (int) $file['size']) {
				@unlink($target);
				throw new \RuntimeException('Локальная копия ZIP сохранена не полностью');
			}

			return $target;
		}

		/** MySQL-lock works across PHP workers and does not depend on hosting /tmp. */
		protected static function acquireInstallLock()
		{
			if (self::$lockDepth > 0) {
				self::$lockDepth++;
				return array('type' => 'nested', 'name' => '', 'handle' => null);
			}

			$name = 'ave_module_install_' . substr(hash('sha256', BASEPATH), 0, 32);
			try {
				$acquired = (int) \DB::query('SELECT GET_LOCK(%s,0)', $name)->getValue();
				if ($acquired === 1) {
					self::$lockDepth = 1;
					return array('type' => 'database', 'name' => $name, 'handle' => null);
				}

				if ($acquired === 0) {
					throw new \RuntimeException('Установка другого модуля уже выполняется. Повторите попытку позже');
				}
			} catch (\RuntimeException $e) {
				if (strpos($e->getMessage(), 'уже выполняется') !== false) { throw $e; }
			} catch (\Throwable $e) {
				// The file lock below keeps installations safe if advisory DB locks are unavailable.
			}

			$directory = BASEPATH . DS . 'tmp';
			if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
				throw new \RuntimeException('Не удалось создать каталог блокировки установки: ' . $directory);
			}

			$handle = @fopen($directory . DS . '.module-install.lock', 'c');
			if (!$handle) {
				throw new \RuntimeException('Каталог временных файлов недоступен для записи: ' . $directory);
			}

			if (!@flock($handle, LOCK_EX | LOCK_NB)) {
				@fclose($handle);
				throw new \RuntimeException('Установка другого модуля уже выполняется. Повторите попытку позже');
			}

			self::$lockDepth = 1;
			return array('type' => 'file', 'name' => '', 'handle' => $handle);
		}

		protected static function releaseInstallLock(array $lock)
		{
			if (self::$lockDepth > 0) {
				self::$lockDepth--;
			}

			if (isset($lock['type']) && $lock['type'] === 'nested') {
				return;
			}

			if (self::$lockDepth > 0) {
				return;
			}

			if (isset($lock['type']) && $lock['type'] === 'database' && !empty($lock['name'])) {
				try {
					\DB::query('SELECT RELEASE_LOCK(%s)', (string) $lock['name']);
				} catch (\Throwable $e) {
					// MySQL releases named locks automatically when the request connection closes.
				}

				return;
			}

			$handle = isset($lock['handle']) ? $lock['handle'] : null;
			if (is_resource($handle)) {
				@flock($handle, LOCK_UN);
				@fclose($handle);
			}
		}

		protected static function assertWritableRoot($root)
		{
			if (!is_dir($root) || !is_writable($root)) {
				throw new \RuntimeException('Каталог модулей недоступен для записи: ' . $root);
			}
		}

		protected static function assertUpload(array $file)
		{
			$error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			if ($error !== UPLOAD_ERR_OK) {
				$messages = array(
					UPLOAD_ERR_INI_SIZE => 'Архив превышает серверный лимит загрузки',
					UPLOAD_ERR_FORM_SIZE => 'Архив превышает допустимый размер',
					UPLOAD_ERR_PARTIAL => 'Архив загрузился не полностью',
					UPLOAD_ERR_NO_FILE => 'Выберите ZIP-архив модуля',
				);
				throw new \RuntimeException(isset($messages[$error]) ? $messages[$error] : 'Ошибка загрузки архива');
			}

			$name = isset($file['name']) ? (string) $file['name'] : '';
			$path = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
			$size = isset($file['size']) ? (int) $file['size'] : 0;
			if ($path === '' || !is_uploaded_file($path)) {
				throw new \RuntimeException('Загруженный файл не найден');
			}

			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
				throw new \RuntimeException('Поддерживаются только ZIP-архивы');
			}

			if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
				throw new \RuntimeException('Размер ZIP должен быть не больше 25 МБ');
			}

			UploadPolicy::assertAllowed($path, $name, $size, '', 'module_archive');
		}

		protected static function extract($archive, $target)
		{
			if (!class_exists('ZipArchive')) {
				throw new \RuntimeException('Для установки модулей требуется PHP-расширение zip');
			}

			$zip = new \ZipArchive();
			$opened = $zip->open($archive);
			if ($opened !== true) {
				throw new \RuntimeException(self::zipOpenError((int) $opened));
			}

			$files = 0;
			$total = 0;
			$seen = array();
			try {
				if ($zip->numFiles > self::MAX_FILES) {
					throw new \RuntimeException('ZIP содержит слишком много файлов и каталогов');
				}

				for ($i = 0; $i < $zip->numFiles; $i++) {
					$stat = $zip->statIndex($i);
					if (!is_array($stat) || !isset($stat['name'])) {
						throw new \RuntimeException('Не удалось прочитать структуру ZIP');
					}

					$name = self::safeEntryName((string) $stat['name']);
					if ($name === null) { continue; }
					$entryKey = strtolower(rtrim($name, '/'));
					if (isset($seen[$entryKey])) {
						throw new \RuntimeException('ZIP содержит повторяющийся путь «' . $name . '»');
					}

					$seen[$entryKey] = true;
					self::assertNotSymlink($zip, $i);

					$isDir = substr($name, -1) === '/';
					$path = $target . DS . str_replace('/', DS, rtrim($name, '/'));
					if ($isDir) {
						if (!is_dir($path) && !@mkdir($path, 0755, true)) {
							throw new \RuntimeException('Не удалось создать каталог из ZIP');
						}

						continue;
					}

					$files++;
					$size = isset($stat['size']) ? (int) $stat['size'] : 0;
					$compressed = isset($stat['comp_size']) ? (int) $stat['comp_size'] : 0;
					$total += $size;
					if ($files > self::MAX_FILES || $size > self::MAX_FILE_BYTES || $total > self::MAX_UNPACKED_BYTES) {
						throw new \RuntimeException('Распакованный модуль превышает безопасные лимиты');
					}

					if ($size > 1048576 && $compressed > 0 && $size > $compressed * 200) {
						throw new \RuntimeException('ZIP содержит файл с подозрительным коэффициентом сжатия');
					}

					$parent = dirname($path);
					if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
						throw new \RuntimeException('Не удалось создать структуру модуля');
					}

					$input = $zip->getStream((string) $stat['name']);
					$output = @fopen($path, 'wb');
					if (!$input || !$output) {
						if ($input) { @fclose($input); }
						if ($output) { @fclose($output); }
						throw new \RuntimeException('Не удалось распаковать файл «' . basename($name) . '»');
					}

					$copied = stream_copy_to_stream($input, $output);
					fclose($input);
					fclose($output);
					if ($copied === false || (int) $copied !== $size) {
						throw new \RuntimeException('Файл «' . basename($name) . '» распакован не полностью');
					}

					@chmod($path, 0644);
				}
			} finally {
				$zip->close();
			}

			if ($files < 1) {
				throw new \RuntimeException('ZIP-архив пуст');
			}
		}

		protected static function zipOpenError($code)
		{
			$errors = array(
				\ZipArchive::ER_EXISTS => 'файл уже существует',
				\ZipArchive::ER_INCONS => 'повреждена структура архива',
				\ZipArchive::ER_INVAL => 'передан некорректный аргумент',
				\ZipArchive::ER_MEMORY => 'серверу не хватило памяти',
				\ZipArchive::ER_NOENT => 'локальная копия архива не найдена',
				\ZipArchive::ER_NOZIP => 'файл не является ZIP-архивом',
				\ZipArchive::ER_OPEN => 'сервер не смог открыть локальную копию архива',
				\ZipArchive::ER_READ => 'сервер не смог прочитать архив',
				\ZipArchive::ER_SEEK => 'сервер не поддерживает позиционирование в файле архива',
			);
			$reason = isset($errors[$code]) ? $errors[$code] : 'неподдерживаемый формат или ошибка ZIP';
			return 'Не удалось открыть ZIP: ' . $reason . ' (код ' . (int) $code . ')';
		}

		protected static function safeEntryName($name)
		{
			$name = str_replace('\\', '/', $name);
			if ($name === '' || strlen($name) > 512 || strpos($name, "\0") !== false || preg_match('/[\x00-\x1F\x7F]/', $name)) {
				throw new \RuntimeException('ZIP содержит некорректное имя файла');
			}

			if ($name[0] === '/' || preg_match('/^[A-Za-z]:\//', $name)) {
				throw new \RuntimeException('ZIP содержит абсолютный путь');
			}

			$segments = explode('/', rtrim($name, '/'));
			foreach ($segments as $segment) {
				if ($segment === '' || $segment === '.' || $segment === '..') {
					throw new \RuntimeException('ZIP содержит небезопасный путь');
				}

				if ($segment === '__MACOSX' || $segment === '.DS_Store') {
					return null;
				}
			}

			return $name;
		}

		protected static function assertNotSymlink(\ZipArchive $zip, $index)
		{
			$opsys = 0;
			$attributes = 0;
			if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
				$type = ($attributes >> 16) & 0170000;
				if ($type === 0120000) {
					throw new \RuntimeException('Символические ссылки в архиве запрещены');
				}
			}
		}

		protected static function installEmbeddedDependencies($source, array $manifest)
		{
			$dependencies = isset($manifest['dependencies']) && is_array($manifest['dependencies'])
				? $manifest['dependencies']
				: array();
			if (!$dependencies) {
				return array();
			}

			$packageCode = (string) $manifest['code'];
			if (in_array($packageCode, self::$dependencyStack, true)) {
				throw new \RuntimeException('Обнаружена циклическая зависимость пакета «' . $packageCode . '»');
			}

			self::$dependencyStack[] = $packageCode;
			$results = array();
			try {
				foreach ($dependencies as $dependency) {
					$code = (string) $dependency['code'];
					$version = (string) $dependency['version'];
					$archive = $source . DS . str_replace('/', DS, (string) $dependency['archive']);
					$module = ModuleManager::get($code);
					$state = self::moduleState($module);
					PackageDependencyContext::remember($packageCode, $code, $state);

					$currentVersion = $module && isset($module['version']) ? (string) $module['version'] : '';
					if ($module && $currentVersion !== '' && version_compare($currentVersion, $version, '>=')) {
						$results[] = array(
							'code' => $code,
							'version' => $currentVersion,
							'required_version' => $version,
							'operation' => 'reused',
							'original_state' => $state,
						);
						continue;
					}

					$replace = is_array($module);
					$installed = self::installStoredArchive($archive, $code, $version, $replace);
					$results[] = array(
						'code' => $code,
						'version' => isset($installed['version']) ? (string) $installed['version'] : $version,
						'required_version' => $version,
						'operation' => $replace ? 'updated' : 'installed',
						'original_state' => $state,
					);
				}
			} finally {
				array_pop(self::$dependencyStack);
			}

			return $results;
		}

		protected static function moduleState($module)
		{
			if (!is_array($module) || empty($module['installed'])) {
				return 'available';
			}

			if (isset($module['status']) && $module['status'] === 'dirty') {
				$previous = isset($module['previous_status'])
					? strtolower(trim((string) $module['previous_status']))
					: '';
				if (in_array($previous, array('available', 'disabled', 'enabled'), true)) {
					return $previous;
				}
			}

			return !empty($module['enabled']) ? 'enabled' : 'disabled';
		}

		protected static function packageManifest($source, $kind)
		{
			$path = rtrim((string) $source, DS) . DS . 'AVE-MODULE.json';
			if (!is_file($path) || filesize($path) > 1048576) {
				throw new \RuntimeException('Архив не содержит допустимый AVE-MODULE.json');
			}

			$json = file_get_contents($path);
			$manifest = json_decode((string) $json, true);
			if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
				throw new \RuntimeException('AVE-MODULE.json содержит некорректный JSON');
			}

			$formats = array('ave-module-package-v1', 'ave-module-package-v2');
			if (!isset($manifest['format']) || !in_array($manifest['format'], $formats, true)
				|| !isset($manifest['owner']) || $manifest['owner'] !== 'AVE.cms') {
				throw new \RuntimeException('Поддерживаются только официальные пакеты AVE.cms форматов v1 и v2');
			}

			if (!isset($manifest['kind']) || $manifest['kind'] !== (string) $kind) {
				throw new \RuntimeException('Тип модуля не совпадает с AVE-MODULE.json');
			}

			$code = isset($manifest['code']) ? strtolower(trim((string) $manifest['code'])) : '';
			$folder = isset($manifest['folder']) ? trim((string) $manifest['folder']) : '';
			$version = isset($manifest['version']) ? trim((string) $manifest['version']) : '';
			if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)
				|| !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $folder)
				|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._-]+)?$/', $version)) {
				throw new \RuntimeException('AVE-MODULE.json содержит некорректные code, folder или version');
			}

			if ($kind === 'package' && $folder !== $code) {
				throw new \RuntimeException('Папка составного пакета в AVE-MODULE.json должна совпадать с code');
			}

			$files = isset($manifest['files']) && is_array($manifest['files']) ? $manifest['files'] : array();
			if (!$files || count($files) > self::MAX_FILES) {
				throw new \RuntimeException('AVE-MODULE.json не содержит допустимый список файлов');
			}

			$expected = array();
			foreach ($files as $relative => $checksum) {
				$relative = str_replace('\\', '/', trim((string) $relative));
				$safe = self::safeEntryName($relative);
				$checksum = strtolower(trim((string) $checksum));
				if ($safe === null || $safe !== $relative || $relative === 'AVE-MODULE.json'
					|| !preg_match('/^[a-f0-9]{64}$/', $checksum)) {
					throw new \RuntimeException('AVE-MODULE.json содержит некорректный файл или checksum');
				}

				$expected[$relative] = $checksum;
			}

			$entry = $kind === 'package' ? 'admin/module.php' : 'module.php';
			if (!isset($expected[$entry])) {
				throw new \RuntimeException('Manifest пакета не включает основной module.php');
			}

			$actual = array();
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->isLink()) {
					throw new \RuntimeException('Пакет содержит недопустимый тип файла');
				}

				$relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($source))), '/');
				if ($relative === 'AVE-MODULE.json') {
					continue;
				}

				if (!isset($expected[$relative])) {
					throw new \RuntimeException('Файл «' . $relative . '» отсутствует в официальном manifest');
				}

				$actual[$relative] = hash_file('sha256', $file->getPathname());
				if (!hash_equals($expected[$relative], $actual[$relative])) {
					throw new \RuntimeException('Checksum файла «' . $relative . '» не совпадает');
				}
			}

			if (count($actual) !== count($expected)) {
				throw new \RuntimeException('В пакете отсутствует часть файлов из официального manifest');
			}

			$manifest['dependencies'] = self::manifestDependencies($manifest, $expected);
			$manifest['code'] = $code;
			$manifest['folder'] = $folder;
			$manifest['version'] = $version;
			$manifest['manifest_sha256'] = hash('sha256', (string) $json);
			return $manifest;
		}

		protected static function manifestDependencies(array $manifest, array $files)
		{
			$dependencies = isset($manifest['dependencies']) && is_array($manifest['dependencies'])
				? $manifest['dependencies']
				: array();
			if ($manifest['format'] === 'ave-module-package-v1') {
				if ($dependencies) {
					throw new \RuntimeException('Вложенные зависимости требуют format ave-module-package-v2');
				}

				return array();
			}

			if (!$dependencies || count($dependencies) > 64) {
				throw new \RuntimeException('Manifest v2 не содержит допустимый список зависимостей');
			}

			$result = array();
			$codes = array();
			$archives = array();
			foreach ($dependencies as $dependency) {
				$code = isset($dependency['code']) ? strtolower(trim((string) $dependency['code'])) : '';
				$version = isset($dependency['version']) ? trim((string) $dependency['version']) : '';
				$archive = isset($dependency['archive'])
					? str_replace('\\', '/', trim((string) $dependency['archive']))
					: '';
				$checksum = isset($dependency['sha256']) ? strtolower(trim((string) $dependency['sha256'])) : '';
				$safeArchive = $archive !== '' ? self::safeEntryName($archive) : null;
				if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)
					|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._-]+)?$/', $version)
					|| $safeArchive === null || $safeArchive !== $archive
					|| strpos($archive, 'packages/modules/') !== 0
					|| strtolower(pathinfo($archive, PATHINFO_EXTENSION)) !== 'zip'
					|| !preg_match('/^[a-f0-9]{64}$/', $checksum)
					|| !isset($files[$archive]) || !hash_equals((string) $files[$archive], $checksum)
					|| isset($codes[$code]) || isset($archives[$archive])) {
					throw new \RuntimeException('AVE-MODULE.json содержит некорректную вложенную зависимость');
				}

				$codes[$code] = true;
				$archives[$archive] = true;
				$result[] = array(
					'code' => $code,
					'version' => $version,
					'archive' => $archive,
					'sha256' => $checksum,
				);
			}

			return $result;
		}

		protected static function moduleRoot($content)
		{
			if (is_file($content . DS . 'module.php')) {
				return array('path' => $content, 'kind' => 'admin');
			}

			if (is_file($content . DS . 'admin' . DS . 'module.php')) {
				return array('path' => $content, 'kind' => 'package');
			}

			$roots = array();
			foreach ((array) scandir($content) as $entry) {
				if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) { continue; }
				$path = $content . DS . $entry;
				if (!is_dir($path)) { continue; }
				if (is_file($path . DS . 'module.php')) {
					$roots[] = array('path' => $path, 'kind' => 'admin');
				} elseif (is_file($path . DS . 'admin' . DS . 'module.php')) {
					$roots[] = array('path' => $path, 'kind' => 'package');
				}
			}

			if (count($roots) !== 1) {
				throw new \RuntimeException('ZIP должен содержать один module.php или пакет с admin/module.php');
			}

			return $roots[0];
		}

		protected static function assertFolderAvailable($modulesRoot, $folder)
		{
			foreach ((array) scandir($modulesRoot) as $entry) {
				if (strcasecmp((string) $entry, $folder) === 0) {
					throw new \RuntimeException('Папка модуля «' . $folder . '» уже существует');
				}
			}
		}

		protected static function assertReplacementTarget($target, $code, $isPackage)
		{
			$module = ModuleManager::get((string) $code);
			$expected = realpath($isPackage ? $target . DS . 'admin' : $target);
			$actual = $module && !empty($module['base_path']) ? realpath((string) $module['base_path']) : false;
			if (!$module || !$expected || !$actual || !hash_equals(str_replace('\\', '/', $expected), str_replace('\\', '/', $actual))) {
				throw new \RuntimeException('Локальный модуль не совпадает с обновляемым пакетом');
			}
		}

		protected static function invalidatePackageRegistry($isPackage)
		{
			if ($isPackage) {
				CompiledModuleRegistry::invalidate('package_admin');
				CompiledModuleRegistry::invalidate('public');
				return;
			}

			CompiledModuleRegistry::invalidate('adminx');
		}

		protected static function invalidateOpcache($root)
		{
			if (!function_exists('opcache_invalidate') || !is_dir($root)) { return; }
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ($iterator as $file) {
				if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
					@opcache_invalidate($file->getPathname(), true);
				}
			}
		}

		protected static function refreshCodeCache($root)
		{
			clearstatcache(true);
			self::invalidateOpcache($root);
		}
	}
