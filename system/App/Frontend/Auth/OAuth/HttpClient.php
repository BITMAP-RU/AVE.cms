<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/HttpClient.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class HttpClient
	{
		public function get($url, array $headers = array())
		{
			return $this->request('GET', $url, array(), $headers);
		}

		public function post($url, array $data, array $headers = array())
		{
			return $this->request('POST', $url, $data, $headers);
		}

		protected function request($method, $url, array $data, array $headers)
		{
			if (!function_exists('curl_init')) {
				throw new \RuntimeException('Для социального входа требуется расширение cURL.');
			}

			$handle = curl_init((string) $url);
			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 12,
				CURLOPT_HTTPHEADER => array_merge(array('Accept: application/json'), $headers),
				CURLOPT_USERAGENT => 'AVE.cms OAuth/1.0',
			);
			if ($method === 'POST') {
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
				$options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
			}

			curl_setopt_array($handle, $options);
			$body = curl_exec($handle);
			$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
			$error = curl_error($handle);
			curl_close($handle);
			if ($body === false || $error !== '') {
				throw new \RuntimeException('Сервис авторизации временно недоступен.');
			}

			$decoded = json_decode((string) $body, true);
			if ($status < 200 || $status >= 300 || !is_array($decoded)) {
				throw new \RuntimeException('Сервис авторизации отклонил запрос.');
			}

			if (!empty($decoded['error'])) {
				throw new \RuntimeException('Сервис авторизации вернул ошибку.');
			}

			return $decoded;
		}
	}
