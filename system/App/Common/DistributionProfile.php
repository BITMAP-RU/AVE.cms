<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/DistributionProfile.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Trusted repository profile shipped with a release or supplied by its publisher. */
	class DistributionProfile
	{
		const FORMAT = 'ave-repository-profile-v1';

		public static function official()
		{
			$file = BASEPATH . '/system/distribution.json';
			if (!is_file($file)) {
				throw new \RuntimeException('В сборке отсутствует официальный профиль источников.');
			}

			$content = file_get_contents($file);
			if ($content === false) {
				throw new \RuntimeException('Не удалось прочитать официальный профиль источников.');
			}

			return self::decode($content);
		}

		public static function decode($content)
		{
			$data = json_decode(trim((string) $content), true);
			if (!is_array($data)) {
				throw new \InvalidArgumentException('Профиль подключения должен быть корректным JSON-документом.');
			}

			return self::normalize($data);
		}

		public static function encode(array $profile)
		{
			return json_encode(self::normalize($profile), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public static function repository(array $profile, $code)
		{
			$profile = self::normalize($profile);
			$code = (string) $code;
			if (!isset($profile[$code])) {
				throw new \InvalidArgumentException('Источник отсутствует в профиле подключения.');
			}

			return $profile[$code];
		}

		public static function fingerprint($publicKey)
		{
			$normalized = str_replace(array("\r\n", "\r"), "\n", trim((string) $publicKey));
			return strtoupper(implode(':', str_split(substr(hash('sha256', $normalized), 0, 24), 4)));
		}

		protected static function normalize(array $data)
		{
			if (!isset($data['format']) || (string) $data['format'] !== self::FORMAT) {
				throw new \InvalidArgumentException('Формат профиля подключения не поддерживается.');
			}

			$name = trim((string) (isset($data['name']) ? $data['name'] : ''));
			if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
				throw new \InvalidArgumentException('Укажите название источника длиной до 120 символов.');
			}

			return array(
				'format' => self::FORMAT,
				'name' => $name,
				'channel' => self::channel(isset($data['channel']) ? $data['channel'] : 'stable'),
				'core_updates' => self::normalizeRepository(isset($data['core_updates']) ? $data['core_updates'] : null, 'обновлений ядра'),
				'modules' => self::normalizeRepository(isset($data['modules']) ? $data['modules'] : null, 'модулей'),
			);
		}

		protected static function normalizeRepository($repository, $label)
		{
			if (!is_array($repository)) {
				throw new \InvalidArgumentException('В профиле отсутствует источник ' . $label . '.');
			}

			$url = trim((string) (isset($repository['url']) ? $repository['url'] : ''));
			$parts = parse_url($url);
			if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
				|| !isset($parts['scheme'], $parts['host']) || strtolower((string) $parts['scheme']) !== 'https'
				|| isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
				|| strtolower((string) $parts['host']) === 'localhost') {
				throw new \InvalidArgumentException('Источник ' . $label . ' должен иметь публичный HTTPS URL.');
			}

			if (filter_var($parts['host'], FILTER_VALIDATE_IP)
				&& !filter_var($parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				throw new \InvalidArgumentException('Источник ' . $label . ' не может использовать локальный IP-адрес.');
			}

			$key = str_replace(array("\r\n", "\r"), "\n", trim((string) (isset($repository['public_key']) ? $repository['public_key'] : '')));
			$resource = $key !== '' ? @openssl_pkey_get_public($key) : false;
			if (!$resource) {
				throw new \InvalidArgumentException('Публичный RSA-ключ источника ' . $label . ' некорректен.');
			}

			$details = openssl_pkey_get_details($resource);
			if (is_resource($resource)) { openssl_free_key($resource); }
			if (!is_array($details) || !isset($details['type']) || $details['type'] !== OPENSSL_KEYTYPE_RSA) {
				throw new \InvalidArgumentException('Для источника ' . $label . ' требуется RSA-ключ.');
			}

			return array(
				'url' => $url,
				'public_key' => $key,
				'fingerprint' => self::fingerprint($key),
			);
		}

		protected static function channel($channel)
		{
			$channel = strtolower(trim((string) $channel));
			return preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $channel) ? $channel : 'stable';
		}
	}
