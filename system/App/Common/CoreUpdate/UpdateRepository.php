<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CoreUpdate/UpdateRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\CoreUpdate;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\DistributionProfile;
	use App\Common\ModuleSettings;
	use App\Common\OutboundHttpClient;

	/** Signed owner-controlled catalog of core update patches. */
	class UpdateRepository
	{
		const MODULE = 'core_updates';
		const ENVELOPE_FORMAT = 'ave-core-update-repository-v1';
		const PAYLOAD_FORMAT = 'ave-core-update-index-v1';
		const CACHE_TTL = 900;

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
			Cache::forgetTag('core-update-repository');
			return self::settings();
		}

		public static function officialSettings()
		{
			try {
				$profile = DistributionProfile::official();
				$repository = DistributionProfile::repository($profile, 'core_updates');
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
			if (!$settings['enabled']) { return self::emptyCatalog('Каталог обновлений выключен'); }
			if ($settings['url'] === '' || $settings['public_key'] === '') { return self::emptyCatalog('Укажите URL и публичный ключ'); }
			$key = 'core-update-repository:' . hash('sha256', $settings['url'] . "\0" . $settings['public_key']);
			if ($force) { Cache::forget($key); }
			try {
				$catalog = Cache::rememberTagged($key, self::CACHE_TTL, array('core-update-repository'), function () use ($settings) {
					$response = OutboundHttpClient::get($settings['url'], array('max_bytes' => 2097152, 'timeout' => 20));
					return self::verifyEnvelope($response['body'], $settings['public_key'], $settings['url']);
				});
				$catalog['enabled'] = true;
				return self::withState($catalog);
			} catch (\Throwable $e) {
				return self::emptyCatalog($e->getMessage(), true);
			}
		}

		/**
		 * Каталог только из кеша, без обращения к сети.
		 *
		 * Нужен там, где нельзя блокировать отрисовку страницы ожиданием
		 * внешнего сервера: колокольчик, виджеты. Кеш прогревает регулярная
		 * проверка, поэтому пустой ответ означает «пока не проверяли».
		 */
		public static function cachedCatalog()
		{
			$settings = self::settings();
			if (!$settings['enabled'] || $settings['url'] === '' || $settings['public_key'] === '') { return null; }
			$key = 'core-update-repository:' . hash('sha256', $settings['url'] . "\0" . $settings['public_key']);
			try {
				$catalog = Cache::get($key);
			} catch (\Throwable $e) {
				return null;
			}

			if (!is_array($catalog) || !isset($catalog['items'])) { return null; }
			$catalog['enabled'] = true;
			return self::withState($catalog);
		}

		public static function download($id, $actorId = null)
		{
			$catalog = self::catalog(false);
			if (!empty($catalog['error'])) { throw new \RuntimeException($catalog['error']); }
			$item = null;
			foreach ($catalog['items'] as $candidate) { if ($candidate['id'] === (string) $id) { $item = $candidate; break; } }
			if (!$item || empty($item['applicable'])) { throw new \RuntimeException('Подходящий патч отсутствует в проверенном каталоге'); }
			$response = OutboundHttpClient::get($item['archive'], array('max_bytes' => min(PatchPackage::MAX_ARCHIVE_BYTES, $item['size'] + 1), 'timeout' => 120));
			if ((int) $response['bytes'] !== $item['size']) {
				throw new \RuntimeException(
					'Файл патча на сервере загружен не полностью: ожидалось ' . $item['size']
					. ' байт, получено ' . (int) $response['bytes'] . '. Повторно загрузите архив репозитория.'
				);
			}

			if (!hash_equals($item['sha256'], hash('sha256', $response['body']))) {
				throw new \RuntimeException('Контрольная сумма патча на сервере не совпадает с подписанным каталогом. Повторно загрузите архив репозитория.');
			}

			$temporary = BASEPATH . '/tmp/.core-update-' . bin2hex(random_bytes(8)) . '.zip';
			if (file_put_contents($temporary, $response['body'], LOCK_EX) === false) { throw new \RuntimeException('Не удалось сохранить загруженный патч'); }
			@chmod($temporary, 0600);
			try { return UpdateJob::create($temporary, self::settings()['public_key'], $actorId); }
			finally { @unlink($temporary); }
		}

		public static function verifyEnvelope($raw, $publicKey, $catalogUrl)
		{
			$envelope = json_decode((string) $raw, true);
			if (!is_array($envelope) || !isset($envelope['format'], $envelope['payload'], $envelope['signature']) || $envelope['format'] !== self::ENVELOPE_FORMAT) { throw new \RuntimeException('Формат каталога обновлений не поддерживается'); }
			$payloadRaw = base64_decode((string) $envelope['payload'], true);
			$signature = base64_decode((string) $envelope['signature'], true);
			$key = openssl_pkey_get_public(self::publicKey($publicKey, true));
			if ($payloadRaw === false || $signature === false || !$key || openssl_verify($payloadRaw, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) { throw new \RuntimeException('Подпись каталога обновлений не прошла проверку'); }
			if (is_resource($key)) { openssl_free_key($key); }
			$payload = json_decode($payloadRaw, true);
			if (!is_array($payload) || !isset($payload['format'], $payload['updates']) || $payload['format'] !== self::PAYLOAD_FORMAT || !is_array($payload['updates'])) { throw new \RuntimeException('Подписанные данные каталога повреждены'); }
			$items = array();
			foreach ($payload['updates'] as $update) { $item = self::normalizeItem($update, $catalogUrl); if (isset($items[$item['id']])) { throw new \RuntimeException('Каталог содержит повторяющийся патч'); } $items[$item['id']] = $item; }
			return array('enabled' => true, 'verified' => true, 'error' => '', 'name' => isset($payload['name']) ? (string) $payload['name'] : 'AVE.cms core updates', 'channel' => isset($payload['channel']) ? (string) $payload['channel'] : 'stable', 'generated_at' => isset($payload['generated_at']) ? (string) $payload['generated_at'] : '', 'items' => array_values($items));
		}

		protected static function normalizeItem($item, $catalogUrl)
		{
			if (!is_array($item) || empty($item['id']) || !isset($item['from'], $item['to'], $item['archive'], $item['size'], $item['sha256'])) { throw new \RuntimeException('Некорректная запись обновления'); }
			$id = trim((string) $item['id']);
			$size = (int) $item['size'];
			$checksum = strtolower((string) $item['sha256']);
			if (!preg_match('/^[A-Za-z0-9._-]{6,160}$/', $id) || $size < 1 || $size > PatchPackage::MAX_ARCHIVE_BYTES || !preg_match('/^[a-f0-9]{64}$/', $checksum)) { throw new \RuntimeException('Некорректные метаданные обновления'); }
			return array('id' => $id, 'from' => (array) $item['from'], 'to' => (array) $item['to'], 'cumulative' => !empty($item['cumulative']), 'archive' => self::archiveUrl($item['archive'], $catalogUrl), 'size' => $size, 'sha256' => $checksum, 'notes' => isset($item['notes']) ? trim((string) $item['notes']) : '', 'requirements' => isset($item['requirements']) ? (array) $item['requirements'] : array());
		}

		protected static function withState(array $catalog)
		{
			$current = ReleaseState::installed();
			foreach ($catalog['items'] as &$item) {
				$item['compatibility'] = ReleaseState::patchCompatibility($item['from'], $item['to'], !empty($item['cumulative']));
				$item['applicable'] = $item['compatibility']['applicable'];
				$item['installed'] = ReleaseState::targetInstalled($item['to'], $current);
				$item['state'] = $item['installed'] ? 'installed' : ($item['applicable'] ? 'available' : 'unavailable');
			}

			unset($item);
			$catalog['items'] = self::collapseVariants($catalog['items']);
			$catalog['items'] = self::preferCumulative($catalog['items']);
			$available = 0;
			foreach ($catalog['items'] as $item) { if (!empty($item['applicable'])) { $available++; } }
			$catalog['available'] = $available;
			return $catalog;
		}

		/** Keep one logical transition and select the variant matching this installation. */
		protected static function collapseVariants(array $items)
		{
			$groups = array();
			$order = array();
			foreach ($items as $item) {
				$key = (string) $item['from']['version'] . "\0" . (string) $item['from']['build']
					. "\0" . (string) $item['to']['version'] . "\0" . (string) $item['to']['build'];
				if (!isset($groups[$key])) {
					$groups[$key] = array('selected' => $item, 'rank' => self::variantRank($item), 'count' => 1);
					$order[] = $key;
					continue;
				}

				$groups[$key]['count']++;
				$rank = self::variantRank($item);
				if ($rank < $groups[$key]['rank']) {
					$groups[$key]['selected'] = $item;
					$groups[$key]['rank'] = $rank;
				}
			}

			$out = array();
			foreach ($order as $key) {
				$item = $groups[$key]['selected'];
				$item['variant_count'] = (int) $groups[$key]['count'];
				$out[] = $item;
			}

			return $out;
		}

		protected static function variantRank(array $item)
		{
			if (!empty($item['applicable'])) { return 0; }
			return isset($item['compatibility']['code']) && $item['compatibility']['code'] === 'fingerprint_mismatch' ? 1 : 2;
		}

		/** Hide obsolete chain steps when one cumulative patch covers this build. */
		protected static function preferCumulative(array $items)
		{
			$selected = null;
			foreach ($items as $item) {
				if (empty($item['cumulative']) || empty($item['applicable'])) { continue; }
				if ($selected === null || version_compare((string) $item['to']['build'], (string) $selected['to']['build'], '>')) { $selected = $item; }
			}

			if ($selected === null) { return $items; }
			$result = array();
			foreach ($items as $item) {
				if ($item['id'] === $selected['id']
					|| version_compare((string) $item['to']['build'], (string) $selected['to']['build'], '>')) {
					$result[] = $item;
				}
			}

			return $result;
		}

		protected static function emptyCatalog($error, $enabled = false) { return array('enabled' => $enabled, 'verified' => false, 'error' => (string) $error, 'name' => '', 'channel' => '', 'generated_at' => '', 'items' => array(), 'available' => 0); }
		protected static function catalogUrl($url, $required) { $url = trim((string) $url); if ($url === '' && !$required) { return ''; } OutboundHttpClient::inspectUrl($url); return $url; }
		protected static function publicKey($key, $required) { $key = trim((string) $key); if ($key === '' && !$required) { return ''; } $resource = $key !== '' ? openssl_pkey_get_public($key) : false; if (!$resource) { throw new \InvalidArgumentException('Публичный RSA-ключ обновлений некорректен'); } $details = openssl_pkey_get_details($resource); if (is_resource($resource)) { openssl_free_key($resource); } if (!is_array($details) || !isset($details['type']) || $details['type'] !== OPENSSL_KEYTYPE_RSA) { throw new \InvalidArgumentException('Требуется публичный RSA-ключ'); } return $key; }
		protected static function archiveUrl($archive, $catalogUrl) { $archive = trim((string) $archive); if (preg_match('#^https://#i', $archive)) { OutboundHttpClient::inspectUrl($archive); return $archive; } if ($archive === '' || $archive[0] === '/' || strpos($archive, '..') !== false) { throw new \RuntimeException('Некорректный URL архива обновления'); } $parts = parse_url($catalogUrl); if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) { throw new \RuntimeException('Некорректный URL каталога'); } $basePath = isset($parts['path']) ? dirname($parts['path']) : ''; $url = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . rtrim($basePath, '/') . '/' . ltrim($archive, '/'); OutboundHttpClient::inspectUrl($url); return $url; }
	}
