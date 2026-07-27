<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Ip.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	if (! defined("BASEPATH"))
	{
		die ('Direct access to this location is not allowed.');
	}

	class Ip
	{
		public static $ip;
		public static $charset;

		/**
		 * Конструктор класса Ip
		 *
		 * Устанавливает IP-адрес и кодировку для последующих запросов.
		 * Если IP-адрес не предоставлен, он определяется автоматически.
		 *
		 * @param array|null $options Массив опций ('ip' => '8.8.8.8', 'charset' => 'UTF-8').
		 *
		 * @example
		 * <code>
		 * $geo = new Ip(['ip' => '8.8.8.8', 'charset' => 'UTF-8']);
		 * </code>
		 */
		public function __construct($options = null)
		{
			if (! isset($options['ip']) || ! self::isValid($options['ip'])) {
				self::$ip = self::getIp();
			} elseif (self::isValid($options['ip'])) {
				self::$ip = $options['ip'];
			}

			if (isset($options['charset']) && $options['charset'] && $options['charset'] != 'windows-1251') {
				self::$charset = $options['charset'];
			} else {
				self::$charset = 'UTF-8';
			}
		}

		/**
		 * Получает географические данные по IP-адресу.
		 *
		 * Метод запрашивает данные с сервиса IpGeoBase.ru. Результат может
		 * кэшироваться в cookie для предотвращения повторных запросов.
		 *
		 * @param string|false $key Ключ для получения конкретного значения ('country', 'city', 'region', etc.).
		 * @param bool $cookie Флаг, указывающий, нужно ли использовать кэширование в cookie.
		 * @return array|string|null Массив со всеми данными, строка с запрошенным значением или null.
		 *
		 * @example
		 * <code>
		 * // Получить город
		 * $city = Ip::getValue('city');
		 *
		 * // Получить все данные в виде массива
		 * $data = Ip::getValue();
		 * </code>
		 */
		public static function getValue($key = false, $cookie = true)
		{
			$key_array = ['inetnum', 'country', 'city', 'region', 'district', 'lat', 'lng'];

			if (! in_array($key, $key_array, true)) {
				$key = false;
			}

			if ($cookie && ! empty(Cookie::get('geobase'))) {
				$data = Arr::unserialToArray(stripslashes(Cookie::get('geobase')));
			} else {
				$data = self::geoBaseData();
				Cookie::set('geobase', serialize($data), time() + 3600 * 24 * 7);
			}

			if ($key) {
				return $data[$key] ?? null;
			}

			return $data;
		}

		/**
		 * Запрашивает данные с сервиса IpGeoBase.ru
		 *
		 * Метод отправляет cURL-запрос к API IpGeoBase.ru для получения XML-ответа с гео-данными.
		 *
		 * @return array Массив с данными, полученный после парсинга ответа.
		 */
		public static function geoBaseData()
		{
			$link = 'http://ipgeobase.ru:7020/geo?ip=' . self::$ip;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $link);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
			$string = curl_exec($ch);

			if (self::$charset && self::$charset !== 'windows-1251') {
				$string = iconv('windows-1251', self::$charset, $string);
			}

			return self::parseString($string);
		}

		/**
		 * Парсит XML-строку ответа от IpGeoBase
		 *
		 * Извлекает данные из XML-строки с помощью регулярных выражений.
		 *
		 * @param string $string XML-строка для парсинга.
		 * @return array Массив с извлеченными данными.
		 */
		public static function parseString($string)
		{
			$pa = [
				'inetnum'	=> '#<inetnum>(.*)</inetnum>#is',
				'country'	=> '#<country>(.*)</country>#is',
				'city'		=> '#<city>(.*)</city>#is',
				'region'	=> '#<region>(.*)</region>#is',
				'district'	=> '#<district>(.*)</district>#is',
				'lat'		=> '#<lat>(.*)</lat>#is',
				'lng'		=> '#<lng>(.*)</lng>#is'
			];

			$data = [];

			foreach ($pa as $key => $pattern) {
				preg_match($pattern, $string, $out);
				if (isset($out[1]) && $out[1]) {
					$data[$key] = trim($out[1]);
				}
			}

			return $data;
		}

		/**
		 * Определяет IP-адрес пользователя
		 *
		 * Метод проверяет различные заголовки $_SERVER для определения реального
		 * IP-адреса пользователя, в том числе при использовании прокси.
		 *
		 * @return string|false IP-адрес пользователя или false в случае неудачи.
		 *
		 * @example
		 * <code>
		 * $user_ip = Ip::getIp();
		 * </code>
		 */
		public static function getIp()
		{
			$ip = false;
			$ipa = [];

			if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ipa[] = trim(strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ','));
			}

			if (isset($_SERVER['HTTP_CLIENT_IP'])) {
				$ipa[] = $_SERVER['HTTP_CLIENT_IP'];
			}

			if (isset($_SERVER['REMOTE_ADDR'])) {
				$ipa[] = $_SERVER['REMOTE_ADDR'];
			}

			if (isset($_SERVER['HTTP_X_REAL_IP'])) {
				$ipa[] = $_SERVER['HTTP_X_REAL_IP'];
			}

			foreach ($ipa as $ips) {
				if (self::isValid($ips)) {
					if ($ips == '::1') {
						$ips = '127.0.0.1';
					}

					$ip = $ips;
					break;
				}
			}

			return $ip;
		}

		/**
		 * Проверяет валидность IPv4-адреса
		 *
		 * @param string|null $ip IP-адрес для проверки.
		 * @return bool Возвращает true, если IP-адрес валиден, иначе false.
		 */
		public static function isValid($ip = null)
		{
			if (preg_match('#^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$#', $ip)) {
				return true;
			}

			if ($ip == '::1') {
				return true;
			}

			return false;
		}

		/**
		 * Конвертирует IPv4-адрес в HEX-представление.
		 *
		 * @param string $ip IPv4-адрес.
		 * @return string|null HEX-представление IP-адреса или null в случае ошибки.
		 */
		public static function ip2hex($ip)
		{
			if (self::isValid($ip)) {
				$ip_code = explode('.', $ip);
				if (isset($ip_code[3])) {
					return sprintf('%02x%02x%02x%02x', $ip_code[0], $ip_code[1], $ip_code[2], $ip_code[3]);
				}
			}

			return null;
		}

		/**
		 * Конвертирует HEX-представление в IPv4-адрес.
		 *
		 * @param string $hex HEX-строка.
		 * @return string IPv4-адрес.
		 */
		public static function hex2ip($hex)
		{
			$aHex = explode('.', chunk_split($hex, 2, '.'));
			$aReturn = [];

			if (count($aHex) > 0) {
				foreach ($aHex as $field) {
					if (!empty($field)) {
						$aReturn[] = hexdec($field);
					}
				}
			}

			return implode('.', $aReturn);
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
