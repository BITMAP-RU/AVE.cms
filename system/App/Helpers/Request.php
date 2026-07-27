<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Request.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	class Request
	{
		protected static $capturedGet;
		protected static $capturedPost;

		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  HTTP-метод
		// ------------------------------------------------------------------ //

		/** Вернуть HTTP-метод в верхнем регистре: GET, POST, PUT, DELETE, PATCH, ... */
		public static function method()
		{
			$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

			// Поддержка _method override (Laravel-style) из POST или заголовка
			if ($method === 'POST') {
				$override = isset($_POST['_method']) ? strtoupper($_POST['_method']) : null;
				if (!$override) {
					$override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
						? strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
						: null;
				}

				if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
					$method = $override;
				}
			}

			return $method;
		}

		public static function isGet()    { return self::method() === 'GET'; }
		public static function isPost()   { return self::method() === 'POST'; }
		public static function isPut()    { return self::method() === 'PUT'; }
		public static function isDelete() { return self::method() === 'DELETE'; }
		public static function isPatch()  { return self::method() === 'PATCH'; }

		public static function isAjax()
		{
			return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
				&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		}

		public static function isPjax()
		{
			return isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'];
		}

		public static function isHttps()
		{
			if (isset($_SERVER['HTTPS'])) {
				$https = strtolower(trim((string) $_SERVER['HTTPS']));
				if ($https === 'on' || $https === '1') {
					return true;
				}
			}

			if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
				return true;
			}

			$remote = self::validIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
			$trusted = PublicConfiguration::value('trusted_proxies', array());
			$headers = PublicConfiguration::value('trusted_proxy_headers', array('x-forwarded-for'));
			if ($remote === null
				|| !self::matchesAnyNetwork($remote, is_array($trusted) ? $trusted : array())
				|| !in_array('x-forwarded-proto', array_map('strtolower', is_array($headers) ? $headers : array()), true)
				|| empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
				return false;
			}

			$values = explode(',', strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']));
			return trim((string) reset($values)) === 'https';
		}

		// ------------------------------------------------------------------ //
		//  Входные данные (GET / POST / combined)
		// ------------------------------------------------------------------ //

		/**
		 * Получить значение из GET/POST/REQUEST по ключу.
		 * Если ключ не указан — весь массив input.
		 */
		public static function input($key = null, $default = null)
		{
			$data = array_merge(self::getData(), self::postData());
			if ($key === null) {
				return $data;
			}

			return Arr::get($data, $key, $default);
		}

		/** Весь массив GET + POST */
		public static function all()
		{
			return array_merge(self::getData(), self::postData());
		}

		/** Только указанные ключи из input */
		public static function only(array $keys)
		{
			$data = self::all();
			$result = [];
			foreach ($keys as $key) {
				$result[$key] = Arr::get($data, $key);
			}

			return $result;
		}

		/** Всё из input кроме указанных ключей */
		public static function except(array $keys)
		{
			$data = self::all();
			foreach ($keys as $key) {
				unset($data[$key]);
			}

			return $data;
		}

		/** Проверить, что ключ присутствует и непустой */
		public static function has($key)
		{
			$value = self::input($key);
			return $value !== null && $value !== '';
		}

		// ------------------------------------------------------------------ //
		//  GET с типизацией
		// ------------------------------------------------------------------ //

		public static function get($key, $default = null)
		{
			return Arr::get(self::getData(), $key, $default);
		}

		public static function getAll()
		{
			return self::getData();
		}

		public static function getInt($key, $default = 0)
		{
			return (int)Arr::get(self::getData(), $key, $default);
		}

		public static function getStr($key, $default = '')
		{
			return (string)Arr::get(self::getData(), $key, $default);
		}

		public static function getFloat($key, $default = 0.0)
		{
			return (float)Arr::get(self::getData(), $key, $default);
		}

		public static function getBool($key, $default = false)
		{
			$v = Arr::get(self::getData(), $key, null);
			if ($v === null) return $default;
			return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
		}

		// ------------------------------------------------------------------ //
		//  POST с типизацией
		// ------------------------------------------------------------------ //

		public static function post($key, $default = null)
		{
			return Arr::get(self::postData(), $key, $default);
		}

		public static function postAll()
		{
			return self::postData();
		}

		public static function postInt($key, $default = 0)
		{
			return (int)Arr::get(self::postData(), $key, $default);
		}

		public static function postNullableInt($key)
		{
			$value = Arr::get(self::postData(), $key, null);
			if ($value === null || $value === '') {
				return null;
			}

			$value = (int)$value;
			return $value > 0 ? $value : null;
		}

		public static function postStr($key, $default = '')
		{
			return (string)Arr::get(self::postData(), $key, $default);
		}

		public static function postFloat($key, $default = 0.0)
		{
			return (float)Arr::get(self::postData(), $key, $default);
		}

		public static function postBool($key, $default = false)
		{
			$v = Arr::get(self::postData(), $key, null);
			if ($v === null) return $default;
			return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
		}

		/** Decode a JSON array stored in one POST field; malformed JSON returns null. */
		public static function postJsonArray($key, array $default = array())
		{
			$value = Arr::get(self::postData(), $key, null);
			if ($value === null || $value === '') {
				return $default;
			}

			if (is_array($value)) {
				return $value;
			}

			try {
				$decoded = Json::decode((string) $value);
			} catch (\RuntimeException $e) {
				return null;
			}

			return is_array($decoded) ? $decoded : null;
		}

		// ------------------------------------------------------------------ //
		//  REQUEST (combined)
		// ------------------------------------------------------------------ //

		public static function request($key, $default = null)
		{
			return Arr::get(self::all(), $key, $default);
		}

		public static function requestInt($key, $default = 0)
		{
			return (int) self::request($key, $default);
		}

		/** Preserve the original HTTP input before a compatibility layer mutates superglobals. */
		public static function captureInput(array $get, array $post)
		{
			self::$capturedGet = $get;
			self::$capturedPost = $post;
		}

		public static function clearCapturedInput()
		{
			self::$capturedGet = null;
			self::$capturedPost = null;
		}

		protected static function getData()
		{
			return is_array(self::$capturedGet) ? self::$capturedGet : (array) $_GET;
		}

		protected static function postData()
		{
			return is_array(self::$capturedPost) ? self::$capturedPost : (array) $_POST;
		}

		// ------------------------------------------------------------------ //
		//  JSON-тело
		// ------------------------------------------------------------------ //

		/**
		 * Прочитать тело запроса как JSON и вернуть массив.
		 * Кешируется на время запроса.
		 *
		 * @param  bool $assoc true = массив, false = stdClass
		 * @return array|object|null
		 */
		public static function json($assoc = true)
		{
			static $parsed = [];
			$key = $assoc ? 'assoc' : 'object';

			if (!array_key_exists($key, $parsed)) {
				$body = self::rawBody();
				if ($body === '') {
					$parsed[$key] = null;
				} else {
					try {
						$parsed[$key] = Json::decode($body, $assoc);
					} catch (\RuntimeException $e) {
						$parsed[$key] = null;
					}
				}
			}

			return $parsed[$key];
		}

		/** Сырое тело запроса (php://input) */
		public static function rawBody()
		{
			static $body = null;
			if ($body === null) {
				$body = (string)file_get_contents('php://input');
			}

			return $body;
		}

		// ------------------------------------------------------------------ //
		//  Окружение запроса
		// ------------------------------------------------------------------ //

		/** IP-адрес клиента */
		public static function ip()
		{
			$remote = self::validIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
			if ($remote === null) {
				return '0.0.0.0';
			}

			$trusted = PublicConfiguration::value('trusted_proxies', array());
			if (!self::matchesAnyNetwork($remote, is_array($trusted) ? $trusted : array())) {
				return $remote;
			}

			$headers = PublicConfiguration::value('trusted_proxy_headers', array('x-forwarded-for'));
			foreach (is_array($headers) ? $headers : array() as $header) {
				$header = strtolower(trim((string) $header));
				if ($header === 'x-forwarded-for' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$client = self::forwardedClient((string) $_SERVER['HTTP_X_FORWARDED_FOR'], $remote, $trusted);
					if ($client !== null) {
						return $client;
					}
				}

				$serverKey = $header === 'cf-connecting-ip'
					? 'HTTP_CF_CONNECTING_IP'
					: ($header === 'x-real-ip' ? 'HTTP_X_REAL_IP' : '');
				if ($serverKey !== '' && !empty($_SERVER[$serverKey])) {
					$client = self::validIp($_SERVER[$serverKey]);
					if ($client !== null) {
						return $client;
					}
				}
			}

			return $remote;
		}

		protected static function forwardedClient($header, $remote, array $trusted)
		{
			$chain = array();
			foreach (explode(',', (string) $header) as $value) {
				$ip = self::validIp($value);
				if ($ip !== null) {
					$chain[] = $ip;
				}
			}

			$chain[] = $remote;
			for ($index = count($chain) - 1; $index >= 0; $index--) {
				if (!self::matchesAnyNetwork($chain[$index], $trusted)) {
					return $chain[$index];
				}
			}

			return $chain ? $chain[0] : null;
		}

		protected static function validIp($value)
		{
			$value = trim((string) $value);
			return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
		}

		protected static function matchesAnyNetwork($ip, array $networks)
		{
			foreach ($networks as $network) {
				if (self::matchesNetwork($ip, $network)) {
					return true;
				}
			}

			return false;
		}

		protected static function matchesNetwork($ip, $network)
		{
			$network = trim((string) $network);
			if ($network === '') {
				return false;
			}

			$parts = explode('/', $network, 2);
			$address = self::validIp($parts[0]);
			$packedIp = @inet_pton((string) $ip);
			$packedNetwork = $address !== null ? @inet_pton($address) : false;
			if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
				return false;
			}

			$maxBits = strlen($packedIp) * 8;
			$bits = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_INT) : $maxBits;
			if ($bits === false || $bits < 0 || $bits > $maxBits) {
				return false;
			}

			$bytes = intdiv((int) $bits, 8);
			$remaining = (int) $bits % 8;
			if ($bytes > 0 && substr($packedIp, 0, $bytes) !== substr($packedNetwork, 0, $bytes)) {
				return false;
			}

			if ($remaining === 0) {
				return true;
			}

			$mask = (0xFF << (8 - $remaining)) & 0xFF;
			return (ord($packedIp[$bytes]) & $mask) === (ord($packedNetwork[$bytes]) & $mask);
		}

		/** User-Agent строка */
		public static function userAgent()
		{
			return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		}

		/** Referer */
		public static function referer()
		{
			return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		}

		/** Полный URL текущего запроса */
		public static function fullUrl()
		{
			$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
			return $scheme . '://' . $host . $uri;
		}

		/** Путь без query string */
		public static function path()
		{
			$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
			$pos = strpos($uri, '?');
			return $pos !== false ? substr($uri, 0, $pos) : $uri;
		}

		/** Query string */
		public static function queryString()
		{
			return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
		}

		// ------------------------------------------------------------------ //
		//  Заголовки
		// ------------------------------------------------------------------ //

		public static function setHeader($header, $replace = false, $status = null)
		{
			header((string)$header, $replace, $status);
		}

		public static function setHeaders($headers)
		{
			foreach ((array)$headers as $header) {
				if (!empty($header)) {
					header((string)$header);
				}
			}
		}

		/** Получить значение HTTP-заголовка запроса */
		public static function header($name, $default = null)
		{
			$key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
			return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
		}

		// ------------------------------------------------------------------ //
		//  Редирект
		// ------------------------------------------------------------------ //

		/**
		 * Выполнить HTTP-редирект.
		 *
		 * @param string   $url
		 * @param int      $status  302 | 301 | ...
		 * @param int|null $delay   секунды (через Refresh-заголовок, не blocking sleep)
		 */
		public static function redirect($url, $status = 302, $delay = null)
		{
			$url    = (string)$url;
			$status = (int)$status;

			if (headers_sent()) {
				echo "<script>document.location.href='" . addslashes($url) . "';</script>\n";
				return;
			}

			Response::setStatus($status);

			if ($delay !== null) {
				header("Refresh: {$delay}; url={$url}");
			} else {
				self::setHeader('Location: ' . $url, true, $status);
			}

			self::shutDown();
		}

		public static function shutDown()
		{
			exit(0);
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
