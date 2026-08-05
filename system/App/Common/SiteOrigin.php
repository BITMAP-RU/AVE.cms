<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/SiteOrigin.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;

	/** Canonical public origin and Host-header boundary. */
	class SiteOrigin
	{
		public static function configuredUrl()
		{
			$value = getenv('AVE_PUBLIC_URL');
			if ($value === false || trim((string) $value) === '') {
				$value = defined('PUBLIC_SITE_URL') ? (string) PUBLIC_SITE_URL : '';
			}

			if (trim((string) $value) === '') {
				$value = (string) PublicConfiguration::value('canonical_url', '');
			}

			return self::normalizeUrl($value, false);
		}

		public static function requireConfiguredUrl()
		{
			$url = self::configuredUrl();
			if ($url === '') {
				throw new \RuntimeException(
					'Укажите PUBLIC_SITE_URL в разделе «Настройки → Константы», чтобы отправлять защищённые ссылки.'
				);
			}

			return $url;
		}

		public static function absolute($path, $required = false)
		{
			$base = $required ? self::requireConfiguredUrl() : self::configuredUrl();
			if ($base === '') {
				$base = defined('HOST') ? rtrim((string) HOST, '/') : '';
			}

			if ($base === '') { return ''; }
			$path = (string) $path;
			if ($path === '') { return $base; }
			return rtrim($base, '/') . '/' . ltrim($path, '/');
		}

		public static function initializeHost()
		{
			$configured = self::configuredUrl();
			$header = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : '';
			if ($header === '' && PHP_SAPI === 'cli') {
				$origin = $configured !== '' ? self::origin($configured) : 'http://localhost';
				defined('HOST') || define('HOST', $origin);
				return $origin;
			}

			if (!self::validAuthority($header)) { self::reject(); }

			if ($configured !== '' && !self::hostAllowed($header, $configured, self::configuredAliases())) {
				self::reject();
			}

			$_SERVER['HTTP_HOST'] = $header;
			$origin = $configured !== '' ? self::origin($configured) : self::requestOrigin($header);
			defined('HOST') || define('HOST', $origin);
			return $origin;
		}

		public static function hostAllowed($authority, $configuredUrl, array $aliases = array())
		{
			$authority = self::normalizeAuthority($authority);
			$configured = self::normalizeUrl($configuredUrl, false);
			if ($authority === '' || $configured === '') { return false; }

			$allowed = array(self::authority($configured));
			foreach ($aliases as $alias) {
				$alias = self::normalizeAuthority($alias);
				if ($alias !== '') { $allowed[] = $alias; }
			}

			return in_array($authority, array_values(array_unique($allowed)), true);
		}

		public static function normalizeUrl($url, $required = true)
		{
			$url = trim((string) $url);
			if ($url === '') {
				if ($required) { throw new \InvalidArgumentException('Укажите полный публичный URL сайта'); }
				return '';
			}

			$parts = parse_url($url);
			if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
				|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
				throw new \InvalidArgumentException('Публичный URL должен содержать только протокол, домен и путь установки');
			}

			$scheme = strtolower((string) $parts['scheme']);
			if (!in_array($scheme, array('http', 'https'), true)) {
				throw new \InvalidArgumentException('Публичный URL должен использовать HTTP или HTTPS');
			}

			$host = strtolower(rtrim((string) $parts['host'], '.'));
			if (!self::validAuthority($host)) { throw new \InvalidArgumentException('Некорректный домен публичного URL'); }
			$hostForUrl = strpos($host, ':') !== false ? '[' . trim($host, '[]') . ']' : $host;
			$port = isset($parts['port']) ? (int) $parts['port'] : 0;
			if ($port < 0 || $port > 65535) { throw new \InvalidArgumentException('Некорректный порт публичного URL'); }
			$portPart = $port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
				? ':' . $port
				: '';
			$path = isset($parts['path']) ? '/' . trim((string) $parts['path'], '/') : '';
			if ($path === '/') { $path = ''; }

			return $scheme . '://' . $hostForUrl . $portPart . $path;
		}

		protected static function configuredAliases()
		{
			$aliases = PublicConfiguration::value('allowed_hosts', array());
			return is_array($aliases) ? $aliases : array();
		}

		protected static function requestOrigin($authority)
		{
			$scheme = Request::isHttps() ? 'https' : 'http';
			return $scheme . '://' . self::normalizeAuthority($authority, $scheme);
		}

		protected static function origin($url)
		{
			$parts = parse_url((string) $url);
			return strtolower((string) $parts['scheme']) . '://' . self::authority((string) $url);
		}

		protected static function authority($url)
		{
			$parts = parse_url((string) $url);
			$scheme = strtolower((string) $parts['scheme']);
			$host = strtolower((string) $parts['host']);
			$host = strpos($host, ':') !== false ? '[' . trim($host, '[]') . ']' : $host;
			$port = isset($parts['port']) ? (int) $parts['port'] : 0;
			return self::normalizeAuthority($host . ($port > 0 ? ':' . $port : ''), $scheme);
		}

		protected static function normalizeAuthority($authority, $scheme = '')
		{
			$authority = strtolower(rtrim(trim((string) $authority), '.'));
			if (!self::validAuthority($authority)) { return ''; }
			if (($scheme === 'https' && substr($authority, -4) === ':443')
				|| ($scheme === 'http' && substr($authority, -3) === ':80')) {
				$authority = substr($authority, 0, strrpos($authority, ':'));
			}

			return $authority;
		}

		protected static function validAuthority($authority)
		{
			return $authority !== ''
				&& strlen((string) $authority) <= 255
				&& preg_match('/^\[?(?:[a-z0-9-:\]_]+\.?)+$/', (string) $authority);
		}

		protected static function reject()
		{
			http_response_code(400);
			header('Content-Type: text/plain; charset=UTF-8');
			echo 'Некорректный адрес сайта.';
			exit;
		}
	}
