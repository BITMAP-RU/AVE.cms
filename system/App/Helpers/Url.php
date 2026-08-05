<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Url.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Public URL generation without a dependency on compatibility functions. */
	class Url
	{
		protected function __construct()
		{
		}

		public static function to($path = '')
		{
			$path = '/' . ltrim((string) $path, '/');
			return $path === '/' ? '/' : $path;
		}

		public static function asset($path)
		{
			return self::to($path);
		}

		/** Return a same-site path suitable for a Location header. */
		public static function localReturn($url, $fallback = '/')
		{
			$value = trim((string) $url);
			$parts = $value !== '' ? parse_url($value) : false;
			if (!is_array($parts)
				|| isset($parts['scheme'])
				|| isset($parts['host'])
				|| isset($parts['user'])
				|| isset($parts['pass'])) {
				return (string) $fallback;
			}

			$path = isset($parts['path']) ? (string) $parts['path'] : '';
			$decodedPath = rawurldecode($path);
			if ($path === ''
				|| $path[0] !== '/'
				|| preg_match('#^[/\\\\]{2}#', $decodedPath)
				|| preg_match('/[\x00-\x1F\x7F]/', $value)) {
				return (string) $fallback;
			}

			$query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
			return $path . $query;
		}

		public static function uploads($path = '')
		{
			$base = defined('UPLOAD_URL') ? UPLOAD_URL : '/uploads';
			return rtrim($base, '/') . ($path !== '' ? '/' . ltrim((string) $path, '/') : '');
		}

		public static function prepare($url)
		{
			$source = (string) $url;
			$prepared = strip_tags($source);
			$prepared = str_replace(
				array('«', '»', '—', '–', '“', '”'),
				array('', '', '', '', '', ''),
				$prepared
			);

			if (defined('TRANSLIT_URL') && TRANSLIT_URL) {
				$prepared = Locales::transliterate(trim(Locales::lower($prepared)));
			}

			$prepared = preg_replace(
				array(
					'/^[\/-]+|[\/-]+$|^[\/_]+|[\/_]+$|[^\.a-zа-яеёA-ZА-ЯЕЁ0-9\/_-]/u',
					'/--+/',
					'/-*\/+-*/',
					'/\/\/+/',
				),
				array('-', '-', '/', '/'),
				$prepared
			);
			$prepared = trim($prepared, '-');

			if (substr(URL_SUFF, 0, 1) !== '/' && substr($source, -1) === '/') {
				$prepared .= '/';
			}

			return mb_strtolower(rtrim($prepared, '.'), 'UTF-8');
		}

		public static function rewrite($value)
		{
			$value = (string) $value;
			if (!REWRITE_MODE) {
				return $value;
			}

			$document = '/index.php(?:\?)id=(?:[0-9]+)&(?:amp;)*doc='
				. (TRANSLIT_URL ? '([\.a-z0-9\/_-]+)' : '([\.a-zа-яёїєі0-9\/_-]+)');
			$page = '&(?:amp;)*(artpage|apage|page)=([{s}0-9]+)';

			$value = preg_replace($document . $page . $page . $page . '/', ABS_PATH . '$1/$2-$3/$4-$5/$6-$7' . URL_SUFF, $value);
			$value = preg_replace($document . $page . $page . '/', ABS_PATH . '$1/$2-$3/$4-$5' . URL_SUFF, $value);
			$value = preg_replace($document . $page . '/', ABS_PATH . '$1/$2-$3' . URL_SUFF, $value);
			return preg_replace($document . '/', ABS_PATH . '$1' . URL_SUFF, $value);
		}

		public static function site()
		{
			$configured = \App\Common\SiteOrigin::configuredUrl();
			if ($configured !== '') { return $configured; }
			return defined('HOST') ? rtrim((string) HOST, '/') : '';
		}

		public static function home()
		{
			return HOST . ABS_PATH;
		}

		public static function redirect($exclude = '')
		{
			$current = \App\Frontend\PublicPageContext::document();
			$link = 'index.php';
			if (empty($_GET)) {
				return $link;
			}

			if ($exclude !== '' && !is_array($exclude)) {
				$exclude = explode(',', $exclude);
			}

			$exclude = empty($exclude) ? array() : $exclude;
			$exclude[] = 'url';
			$params = array();

			foreach ($_GET as $key => $value) {
				if (in_array($key, $exclude)) {
					continue;
				}

				if ($key === 'doc') {
					$alias = $current && isset($current->document_alias) ? (string) $current->document_alias : '';
					$title = $current && isset($current->document_title) ? (string) $current->document_title : '';
					$params[] = 'doc=' . ($alias === '' ? self::prepare($title) : $alias);
					continue;
				}

				if (!is_array($value)) {
					$params[] = @urlencode($key) . '=' . @urlencode($value);
					continue;
				}

				foreach ($value as $nestedKey => $nestedValue) {
					$params[] = @urlencode($nestedKey) . '=' . @urlencode($nestedValue);
				}
			}

			return $params ? $link . '?' . implode('&', $params) : $link;
		}

		public static function printLink()
		{
			return ABS_PATH . 'index.php?id=' . \App\Frontend\PublicPageContext::documentId() . '&print=1';
		}

		public static function referer()
		{
			static $link;
			if ($link !== null) {
				return $link;
			}

			$referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
			$refererHost = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : '';
			$serverName = isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : '';
			$link = $refererHost !== '' && $refererHost === $serverName ? $referer : self::home();
			return $link;
		}

		public static function canonical($url)
		{
			return preg_replace('/^(.+?)(\?.*?)?(#.*)?$/', '$1$3', (string) $url);
		}

		public static function prepareUrl($url)
		{
			return self::prepare($url);
		}

		public static function getRedirectLink($exclude = '')
		{
			return self::redirect($exclude);
		}

		public static function getRefererLink()
		{
			return self::referer();
		}
	}
