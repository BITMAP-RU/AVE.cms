<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/ModuleRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Modules;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\DistributionProfile;
	use App\Common\ModuleSettings;

	/** Подписанный удалённый каталог официальных ZIP-пакетов AVE.cms. */
	class ModuleRepository
	{
		const MODULE = 'modules';
		const ENVELOPE_FORMAT = 'ave-module-repository-v1';
		const PAYLOAD_FORMAT = 'ave-module-index-v1';
		const CACHE_TTL = 900;
		const MAX_CATALOG_BYTES = 2097152;

		public static function settings()
		{
			return array(
				'enabled' => (bool) ModuleSettings::value('repository_enabled', false, self::MODULE),
				'url' => trim((string) ModuleSettings::value('repository_url', '', self::MODULE)),
				'public_key' => trim((string) ModuleSettings::value('repository_public_key', '', self::MODULE)),
			);
		}

		public static function saveSettings(array $input)
		{
			$enabled = !empty($input['enabled']);
			$url = self::catalogUrl(isset($input['url']) ? $input['url'] : '', $enabled);
			$key = self::publicKey(isset($input['public_key']) ? $input['public_key'] : '', $enabled);
			ModuleSettings::set('repository_enabled', $enabled, self::MODULE, 'bool');
			ModuleSettings::set('repository_url', $url, self::MODULE, 'string');
			ModuleSettings::set('repository_public_key', $key, self::MODULE, 'string');
			Cache::forgetTag('module-repository');

			return self::settings();
		}

		public static function officialSettings()
		{
			try {
				$profile = DistributionProfile::official();
				$repository = DistributionProfile::repository($profile, 'modules');
				return array('available' => true, 'name' => $profile['name'], 'error' => '') + $repository;
			} catch (\Throwable $e) {
				return array('available' => false, 'name' => '', 'url' => '', 'public_key' => '', 'fingerprint' => '', 'error' => $e->getMessage());
			}
		}

		public static function restoreOfficial()
		{
			$official = self::officialSettings();
			if (empty($official['available'])) { throw new \RuntimeException($official['error']); }
			return self::saveSettings(array('enabled' => true, 'url' => $official['url'], 'public_key' => $official['public_key']));
		}

		public static function catalog($force = false)
		{
			$settings = self::settings();
			if (!$settings['enabled']) {
				return self::emptyCatalog('disabled', 'Удалённый каталог выключен');
			}

			if ($settings['url'] === '' || $settings['public_key'] === '') {
				return self::emptyCatalog('configuration', 'Укажите URL каталога и публичный ключ');
			}

			$key = 'module-repository:' . hash('sha256', $settings['url'] . "\0" . $settings['public_key']);
			if ($force) { Cache::forget($key); }
			try {
				$catalog = Cache::rememberTagged($key, self::CACHE_TTL, array('module-repository'), function () use ($settings) {
					$raw = self::request($settings['url'], self::MAX_CATALOG_BYTES);
					return self::verifyEnvelope($raw, $settings['public_key'], $settings['url']);
				});
				$catalog['enabled'] = true;
				$catalog['configured'] = true;
				$catalog['error'] = '';
				return self::withLocalState($catalog);
			} catch (\Throwable $e) {
				$catalog = self::emptyCatalog('fetch', $e->getMessage());
				$catalog['enabled'] = true;
				$catalog['configured'] = true;
				return $catalog;
			}
		}

		/** Каталог только из кеша: для колокольчика, без сетевого запроса. */
		public static function cachedCatalog()
		{
			$settings = self::settings();
			if (!$settings['enabled'] || $settings['url'] === '' || $settings['public_key'] === '') { return null; }
			$key = 'module-repository:' . hash('sha256', $settings['url'] . "\0" . $settings['public_key']);
			try {
				$catalog = Cache::get($key);
			} catch (\Throwable $e) {
				return null;
			}

			if (!is_array($catalog) || !isset($catalog['items'])) { return null; }
			$catalog['enabled'] = true;
			$catalog['configured'] = true;
			$catalog['error'] = '';
			return self::withLocalState($catalog);
		}

		public static function install($code)
		{
			$code = strtolower(trim((string) $code));
			$catalog = self::catalog(false);
			if (!empty($catalog['error'])) { throw new \RuntimeException($catalog['error']); }
			$item = null;
			foreach ($catalog['items'] as $candidate) {
				if ($candidate['code'] === $code) { $item = $candidate; break; }
			}

			if (!$item) { throw new \InvalidArgumentException('Модуль отсутствует в проверенном каталоге'); }
			if (empty($item['compatible'])) { throw new \RuntimeException((string) $item['compatibility_message']); }
			if (!empty($item['local'])) {
				if ($item['status'] === 'update') {
					$archive = self::download($item);
					$result = ModuleArchiveInstaller::updateDownloaded($archive, $item['code'], $item['version']);
					$result['repository_operation'] = 'update';
					return $result;
				}

				if (empty($item['installed']) && version_compare($item['local_version'], $item['version'], '=')) {
					$result = NativeModuleAdapter::install($code);
					$result['repository_operation'] = 'install';
					return $result;
				}

				throw new \RuntimeException('Эта версия модуля уже находится на сервере');
			}

			$archive = self::download($item);
			$result = ModuleArchiveInstaller::installDownloaded($archive, $item['code'], $item['version']);
			$result['repository_operation'] = 'install';
			return $result;
		}

		public static function verifyEnvelope($raw, $publicKey, $catalogUrl = 'https://repository.invalid/index.json')
		{
			if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_verify')) {
				throw new \RuntimeException('Для проверки каталога требуется PHP-расширение OpenSSL');
			}

			$envelope = json_decode((string) $raw, true);
			if (!is_array($envelope) || json_last_error() !== JSON_ERROR_NONE
				|| !isset($envelope['format'], $envelope['payload'], $envelope['signature'])
				|| $envelope['format'] !== self::ENVELOPE_FORMAT) {
				throw new \RuntimeException('Удалённый каталог имеет неподдерживаемый формат');
			}

			$payloadRaw = base64_decode((string) $envelope['payload'], true);
			$signature = base64_decode((string) $envelope['signature'], true);
			if ($payloadRaw === false || $signature === false || strlen($payloadRaw) > self::MAX_CATALOG_BYTES) {
				throw new \RuntimeException('Подпись удалённого каталога повреждена');
			}

			$key = openssl_pkey_get_public(self::publicKey($publicKey, true));
			if (!$key) { throw new \RuntimeException('Публичный ключ каталога не читается OpenSSL'); }
			$keyDetails = openssl_pkey_get_details($key);
			if (!is_array($keyDetails) || !isset($keyDetails['type']) || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
				if (is_resource($key)) { openssl_free_key($key); }
				throw new \RuntimeException('Каталог должен использовать публичный RSA-ключ');
			}

			$verified = openssl_verify($payloadRaw, $signature, $key, OPENSSL_ALGO_SHA256);
			if (is_resource($key)) { openssl_free_key($key); }
			if ($verified !== 1) { throw new \RuntimeException('Цифровая подпись каталога не прошла проверку'); }

			$payload = json_decode($payloadRaw, true);
			if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE
				|| !isset($payload['format'], $payload['modules']) || $payload['format'] !== self::PAYLOAD_FORMAT
				|| !is_array($payload['modules'])) {
				throw new \RuntimeException('Подписанные данные каталога повреждены');
			}

			$items = array();
			foreach ($payload['modules'] as $module) {
				$item = self::normalizeItem($module, $catalogUrl);
				if (isset($items[$item['code']])) { throw new \RuntimeException('Каталог содержит повторяющийся code: ' . $item['code']); }
				$items[$item['code']] = $item;
			}

			uasort($items, array(self::class, 'compareByName'));

			return array(
				'enabled' => true, 'configured' => true, 'verified' => true, 'error' => '',
				'name' => trim(isset($payload['name']) ? (string) $payload['name'] : 'AVE.cms'),
				'channel' => trim(isset($payload['channel']) ? (string) $payload['channel'] : 'stable'),
				'generated_at' => trim(isset($payload['generated_at']) ? (string) $payload['generated_at'] : ''),
				'items' => array_values($items), 'available' => 0, 'updates' => 0,
			);
		}

		protected static function withLocalState(array $catalog)
		{
			$locals = array();
			foreach (NativeModuleAdapter::all() as $local) { $locals[$local['code']] = $local; }
			$available = 0;
			$updates = 0;
			foreach ($catalog['items'] as &$item) {
				$local = isset($locals[$item['code']]) ? $locals[$item['code']] : null;
				$item['local'] = $local !== null;
				$item['installed'] = $local && !empty($local['installed']);
				$item['local_version'] = $local ? (string) $local['file_version'] : '';
				$item['database_version'] = $local ? (string) $local['db_version'] : '';
				$item['migration_pending'] = $local && !empty($local['migration_pending']);
				$item['status'] = self::localStatus((string) $item['version'], $local);
				if ($item['status'] === 'available') {
					$available++;
				} elseif ($item['status'] === 'update') {
					$updates++;
				} elseif ($item['status'] === 'local') {
					$available++;
				}
			}

			unset($item);
			usort($catalog['items'], array(self::class, 'compareByName'));
			$catalog['available'] = $available;
			$catalog['updates'] = $updates;
			return $catalog;
		}

		protected static function compareByName(array $left, array $right)
		{
			$name = strnatcasecmp((string) $left['name'], (string) $right['name']);
			return $name !== 0 ? $name : strnatcasecmp((string) $left['code'], (string) $right['code']);
		}

		protected static function localStatus($remoteVersion, array $local = null)
		{
			if ($local === null) { return 'available'; }
			$fileVersion = isset($local['file_version']) ? (string) $local['file_version'] : '';
			if ($fileVersion !== '' && version_compare((string) $remoteVersion, $fileVersion, '>')) { return 'update'; }
			if (empty($local['installed'])) { return 'local'; }
			if (!empty($local['migration_pending'])) { return 'migration'; }
			return 'current';
		}

		protected static function normalizeItem($module, $catalogUrl)
		{
			if (!is_array($module)) { throw new \RuntimeException('Каталог содержит некорректную запись модуля'); }
			$code = strtolower(trim(isset($module['code']) ? (string) $module['code'] : ''));
			$version = trim(isset($module['version']) ? (string) $module['version'] : '');
			$checksum = strtolower(trim(isset($module['sha256']) ? (string) $module['sha256'] : ''));
			$size = isset($module['size']) ? (int) $module['size'] : 0;
			$kind = isset($module['kind']) && $module['kind'] === 'admin' ? 'admin' : 'package';
			if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)
				|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._-]+)?$/', $version)
				|| !preg_match('/^[a-f0-9]{64}$/', $checksum)
				|| $size < 1 || $size > ModuleArchiveInstaller::MAX_ARCHIVE_BYTES) {
				throw new \RuntimeException('Каталог содержит некорректные метаданные модуля');
			}

			$phpMin = trim(isset($module['php_min']) ? (string) $module['php_min'] : '7.3.0');
			$phpMax = trim(isset($module['php_max']) ? (string) $module['php_max'] : '');
			$aveMin = trim(isset($module['ave_min']) ? (string) $module['ave_min'] : '3.3.0');
			$aveMax = trim(isset($module['ave_max']) ? (string) $module['ave_max'] : '');
			$engineVersion = self::normalizedVersion(defined('APP_VERSION') ? APP_VERSION : '3.3');
			$compatible = version_compare(PHP_VERSION, $phpMin, '>=')
				&& ($phpMax === '' || version_compare(PHP_VERSION, $phpMax, '<='))
				&& version_compare($engineVersion, self::normalizedVersion($aveMin), '>=')
				&& ($aveMax === '' || version_compare($engineVersion, self::normalizedVersion($aveMax), '<='));
			return array(
				'code' => $code, 'version' => $version, 'kind' => $kind,
				'name' => trim(isset($module['name']) ? (string) $module['name'] : $code) ?: $code,
				'description' => trim(isset($module['description']) ? (string) $module['description'] : ''),
				'author' => trim(isset($module['author']) ? (string) $module['author'] : 'AVE.cms'),
				'archive' => self::archiveUrl(isset($module['archive']) ? $module['archive'] : '', $catalogUrl),
				'sha256' => $checksum, 'size' => $size,
				'php_min' => $phpMin, 'php_max' => $phpMax, 'ave_min' => $aveMin, 'ave_max' => $aveMax,
				'compatible' => $compatible,
				'compatibility_message' => $compatible ? 'Совместим с этой установкой' : 'Модуль несовместим с текущими версиями PHP или AVE.cms',
			);
		}

		protected static function download(array $item)
		{
			$directory = BASEPATH . DS . 'tmp';
			if (!is_dir($directory) && !@mkdir($directory, 0755, true)) { throw new \RuntimeException('Не удалось создать временный каталог'); }
			$target = $directory . DS . '.module-remote-' . bin2hex(random_bytes(8)) . '.zip';
			try {
				self::request($item['archive'], min(ModuleArchiveInstaller::MAX_ARCHIVE_BYTES, $item['size'] + 1), $target);
				if (!is_file($target)) {
					throw new \RuntimeException('Сервер каталога не вернул ZIP-пакет модуля «' . $item['name'] . '».');
				}

				$actualSize = (int) filesize($target);
				if ($actualSize !== (int) $item['size']) {
					throw new \RuntimeException(
						'ZIP модуля «' . $item['name'] . '» загружен на сервер не полностью: ожидалось '
						. (int) $item['size'] . ' байт, получено ' . $actualSize
						. '. Повторно загрузите файл пакета и только после проверки обновляйте index.json.'
					);
				}

				if (!hash_equals($item['sha256'], hash_file('sha256', $target))) {
					throw new \RuntimeException(
						'Контрольная сумма ZIP модуля «' . $item['name']
						. '» не совпадает с подписанным каталогом. Повторно загрузите файл пакета и очистите кеш CDN.'
					);
				}

				@chmod($target, 0600);
				return $target;
			} catch (\Throwable $e) {
				if (is_file($target)) { @unlink($target); }
				throw $e;
			}
		}

		protected static function request($url, $maxBytes, $target = '')
		{
			if (!function_exists('curl_init')) { throw new \RuntimeException('Для удалённого каталога требуется PHP-расширение cURL'); }
			$url = self::httpsUrl($url);
			$parts = parse_url($url);
			$ips = gethostbynamel((string) $parts['host']) ?: array();
			$publicIps = array_values(array_filter($ips, function ($ip) {
				return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
			}));
			if (!$publicIps) { throw new \RuntimeException('Хост каталога не имеет доступного публичного IPv4-адреса'); }

			$port = isset($parts['port']) ? (int) $parts['port'] : 443;
			$handle = curl_init($url);
			$options = array(
				CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $target === '' ? 20 : 120,
				CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FAILONERROR => false,
				CURLOPT_USERAGENT => 'AVE.cms/' . (defined('APP_VERSION') ? APP_VERSION : '3.3') . ' ModuleRepository',
				CURLOPT_HTTPHEADER => array('Accept: ' . ($target === '' ? 'application/json' : 'application/zip')),
				CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
				CURLOPT_RESOLVE => array($parts['host'] . ':' . $port . ':' . $publicIps[0]),
			);
			$buffer = '';
			$output = null;
			$bytes = 0;
			if ($target === '') {
				$options[CURLOPT_WRITEFUNCTION] = function ($curl, $chunk) use (&$buffer, &$bytes, $maxBytes) {
					$length = strlen($chunk);
					$bytes += $length;
					if ($bytes > $maxBytes) { return 0; }
					$buffer .= $chunk;
					return $length;
				};
			} else {
				$output = @fopen($target, 'wb');
				if (!$output) { throw new \RuntimeException('Не удалось создать временный ZIP'); }
				$options[CURLOPT_WRITEFUNCTION] = function ($curl, $chunk) use (&$bytes, $maxBytes, $output) {
					$length = strlen($chunk);
					$bytes += $length;
					if ($bytes > $maxBytes) { return 0; }
					return fwrite($output, $chunk);
				};
			}

			curl_setopt_array($handle, $options);
			$result = curl_exec($handle);
			$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
			$error = curl_error($handle);
			curl_close($handle);
			if (is_resource($output)) { fclose($output); }
			if ($result === false || $status !== 200) {
				if ($target !== '' && is_file($target)) { @unlink($target); }
				throw new \RuntimeException($bytes > $maxBytes ? 'Удалённый файл превышает допустимый размер' : ('Сервер каталога недоступен' . ($error !== '' ? ': ' . $error : '')));
			}

			if ($target !== '') { return $target; }
			if ($buffer === '' || strlen($buffer) > $maxBytes) { throw new \RuntimeException('Ответ каталога пуст или слишком велик'); }
			return $buffer;
		}

		protected static function emptyCatalog($reason, $message)
		{
			return array(
				'enabled' => false, 'configured' => false, 'verified' => false,
				'error' => $reason === 'disabled' ? '' : (string) $message,
				'message' => (string) $message, 'name' => '', 'channel' => '', 'generated_at' => '',
				'items' => array(), 'available' => 0, 'updates' => 0,
			);
		}

		protected static function catalogUrl($value, $required)
		{
			$value = trim((string) $value);
			if ($value === '' && !$required) { return ''; }
			return self::httpsUrl($value);
		}

		protected static function publicKey($value, $required)
		{
			$value = str_replace("\r\n", "\n", trim((string) $value));
			if ($value === '' && !$required) { return ''; }
			if (strlen($value) < 200 || strlen($value) > 10000 || strpos($value, 'BEGIN PUBLIC KEY') === false) {
				throw new \InvalidArgumentException('Укажите публичный RSA-ключ каталога в формате PEM');
			}

			return $value . "\n";
		}

		protected static function archiveUrl($value, $catalogUrl)
		{
			$value = trim((string) $value);
			$base = parse_url(self::httpsUrl($catalogUrl));
			if (strpos($value, 'https://') === 0) {
				$url = self::httpsUrl($value);
			} else {
				$path = $value !== '' && $value[0] === '/' ? $value : rtrim(dirname((string) $base['path']), '/') . '/' . ltrim($value, '/');
				$url = 'https://' . $base['host'] . (isset($base['port']) ? ':' . (int) $base['port'] : '') . $path;
				$url = self::httpsUrl($url);
			}

			$parts = parse_url($url);
			$path = isset($parts['path']) ? rawurldecode((string) $parts['path']) : '';
			if (strcasecmp((string) $parts['host'], (string) $base['host']) !== 0
				|| (isset($parts['port']) ? (int) $parts['port'] : 443) !== (isset($base['port']) ? (int) $base['port'] : 443)
				|| preg_match('#(?:^|/)\.\.(?:/|$)#', str_replace('\\', '/', $path))) {
				throw new \RuntimeException('ZIP должен находиться на том же хосте, что и каталог');
			}

			return $url;
		}

		protected static function httpsUrl($value)
		{
			$value = trim((string) $value);
			$parts = parse_url($value);
			if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || strtolower($parts['scheme']) !== 'https'
				|| isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
				|| (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535))
				|| filter_var($parts['host'], FILTER_VALIDATE_IP) !== false
				|| in_array(strtolower((string) $parts['host']), array('localhost', 'localhost.localdomain'), true)) {
				throw new \InvalidArgumentException('Каталог должен иметь публичный HTTPS URL без логина и фрагмента');
			}

			return $value;
		}

		/** Дополняет короткие версии ядра до semver без изменения prerelease-части. */
		protected static function normalizedVersion($value)
		{
			$value = trim((string) $value);
			if (!preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(.*)$/', $value, $matches)) { return $value; }

			return $matches[1] . '.'
				. (isset($matches[2]) && $matches[2] !== '' ? $matches[2] : '0') . '.'
				. (isset($matches[3]) && $matches[3] !== '' ? $matches[3] : '0')
				. (isset($matches[4]) ? $matches[4] : '');
		}
	}
