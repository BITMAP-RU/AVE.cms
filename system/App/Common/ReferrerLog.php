<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ReferrerLog.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;
	use App\Helpers\Request;
	use DB;

	/** Stores compact, privacy-aware statistics for external landing visits. */
	class ReferrerLog
	{
		const RETENTION_DAYS = 180;

		protected static $trackingParameters = array(
			'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
			'gclid', 'dclid', 'gbraid', 'wbraid', 'yclid', 'ysclid', 'ymclid',
			'srsltid', 'fbclid', 'msclkid', 'ttclid', 'roistat',
		);

		public static function capture()
		{
			$method = Request::method();
			if (!in_array($method, array('GET', 'HEAD'), true) || Request::isAjax()) {
				return false;
			}

			$userAgent = self::clean(self::server('HTTP_USER_AGENT'), 500);
			if (self::isBot($userAgent)) {
				return false;
			}

			$landingPath = self::landingPath();
			if ($landingPath === '' || self::isTechnicalPath($landingPath)) {
				return false;
			}

			$tracking = self::trackingParameters();
			$referer = self::externalReferer();
			if (!$tracking && $referer === null) {
				return false;
			}

			try {
				$source = self::source($tracking, $referer);
				$visitor = self::visitorHash($userAgent);
				$date = date('Y-m-d');
				$trackingJson = $tracking ? Json::encode($tracking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
				$refererHost = $referer !== null ? $referer['host'] : '';
				$refererUrl = $referer !== null ? $referer['url'] : '';
				$dedupe = hash('sha256', implode('|', array($date, $visitor, $landingPath, $refererHost, $trackingJson)));
				$now = time();

				DB::query(
					'INSERT INTO `' . self::table() . '`'
					. ' (log_date, visitor_hash, source_type, source_name, referer_host, referer_url, landing_path,'
					. ' utm_source, utm_medium, utm_campaign, utm_term, utm_content, tracking_json, user_agent,'
					. ' first_seen_at, last_seen_at, hits, dedupe_hash)'
					. ' VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %i, %i, 1, %s)'
					. ' ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at), hits=hits+1',
					$date,
					$visitor,
					$source['type'],
					$source['name'],
					$refererHost,
					$refererUrl,
					$landingPath,
					isset($tracking['utm_source']) ? $tracking['utm_source'] : '',
					isset($tracking['utm_medium']) ? $tracking['utm_medium'] : '',
					isset($tracking['utm_campaign']) ? $tracking['utm_campaign'] : '',
					isset($tracking['utm_term']) ? $tracking['utm_term'] : '',
					isset($tracking['utm_content']) ? $tracking['utm_content'] : '',
					$trackingJson,
					$userAgent,
					$now,
					$now,
					$dedupe
				);
			} catch (\Throwable $e) {
				error_log('Referrer log write failed: ' . $e->getMessage());
				return false;
			}

			return true;
		}

		public static function table()
		{
			return SystemTables::table('referrer_log');
		}

		public static function count()
		{
			return (int) DB::query('SELECT COALESCE(SUM(hits), 0) FROM `' . self::table() . '`')->getValue();
		}

		public static function rows($limit = 300, $query = '')
		{
			$limit = max(1, min(1000, (int) $limit));
			$query = trim((string) $query);
			$sql = 'SELECT * FROM `' . self::table() . '`';
			if ($query !== '') {
				$sql .= ' WHERE source_name LIKE %ss OR referer_host LIKE %ss OR landing_path LIKE %ss'
					. ' OR utm_campaign LIKE %ss OR utm_term LIKE %ss';
				return DB::query($sql . ' ORDER BY last_seen_at DESC, id DESC LIMIT ' . $limit,
					$query, $query, $query, $query, $query)->getAll() ?: array();
			}

			return DB::query($sql . ' ORDER BY last_seen_at DESC, id DESC LIMIT ' . $limit)->getAll() ?: array();
		}

		public static function clear()
		{
			DB::query('DELETE FROM `' . self::table() . '`');
			return true;
		}

		public static function pruneExpired($limit = 250)
		{
			$limit = max(1, min(1000, (int) $limit));
			$cutoff = date('Y-m-d', time() - (self::RETENTION_DAYS * 86400));
			DB::query('DELETE FROM `' . self::table() . '` WHERE log_date < %s LIMIT ' . $limit, $cutoff);
			return (int) DB::affectedRows();
		}

		protected static function trackingParameters()
		{
			$out = array();
			foreach (self::$trackingParameters as $name) {
				$value = self::clean(Request::getStr($name, ''), 255);
				if ($value !== '') {
					$out[$name] = $value;
				}
			}

			return $out;
		}

		protected static function externalReferer()
		{
			$value = trim(self::server('HTTP_REFERER'));
			if ($value === '') {
				return null;
			}

			$host = self::normalizeHost((string) parse_url($value, PHP_URL_HOST));
			if ($host === '' || $host === self::normalizeHost(self::server('HTTP_HOST'))) {
				return null;
			}

			$scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
			$path = (string) parse_url($value, PHP_URL_PATH);
			$url = ($scheme === 'http' || $scheme === 'https' ? $scheme : 'https') . '://' . $host;
			if ($path !== '' && $path !== '/') {
				$url .= '/' . ltrim($path, '/');
			}

			return array('host' => $host, 'url' => self::clean($url, 1000));
		}

		protected static function source(array $tracking, $referer)
		{
			if (!empty($tracking['utm_source'])) {
				return array('type' => 'campaign', 'name' => $tracking['utm_source']);
			}

			foreach (array('yclid', 'ysclid', 'ymclid') as $key) {
				if (!empty($tracking[$key])) {
					return array('type' => 'search', 'name' => 'yandex');
				}
			}

			foreach (array('gclid', 'dclid', 'gbraid', 'wbraid', 'srsltid') as $key) {
				if (!empty($tracking[$key])) {
					return array('type' => 'search', 'name' => 'google');
				}
			}

			if (!empty($tracking['fbclid'])) {
				return array('type' => 'social', 'name' => 'facebook');
			}

			if (!empty($tracking['msclkid'])) {
				return array('type' => 'search', 'name' => 'bing');
			}

			if (!empty($tracking['ttclid'])) {
				return array('type' => 'social', 'name' => 'tiktok');
			}

			$host = $referer !== null ? $referer['host'] : 'Метка без источника';
			if (preg_match('/(^|\.)(google|yandex|ya|bing|mail|rambler|duckduckgo)\./i', $host)) {
				return array('type' => 'search', 'name' => $host);
			}

			if (preg_match('/(^|\.)(vk|ok|facebook|instagram|tiktok|youtube|t\.me)(\.|$)/i', $host)) {
				return array('type' => 'social', 'name' => $host);
			}

			return array('type' => $referer !== null ? 'referral' : 'campaign', 'name' => $host);
		}

		protected static function visitorHash($userAgent)
		{
			$session = session_id();
			if ($session === '') {
				$session = self::server('REMOTE_ADDR');
			}

			return substr(hash('sha256', $session . '|' . $userAgent), 0, 32);
		}

		protected static function landingPath()
		{
			$uri = self::server('REQUEST_URI');
			$path = (string) parse_url($uri, PHP_URL_PATH);
			if ($path === '') {
				$path = '/';
			}

			return self::clean('/' . ltrim($path, '/'), 1000);
		}

		protected static function isTechnicalPath($path)
		{
			$admin = rtrim(AdminLocation::url(), '/');
			if ($path === $admin || strpos($path, $admin . '/') === 0
				|| strpos($path, '/install') === 0 || strpos($path, '/uploads/') === 0) {
				return true;
			}

			return (bool) preg_match('/\.(?:css|js|map|jpe?g|png|gif|webp|svg|ico|woff2?|ttf|xml|yml|txt|pdf|zip)$/i', $path);
		}

		protected static function isBot($userAgent)
		{
			if ($userAgent === '') {
				return true;
			}

			return (bool) preg_match('/bot|crawl|spider|slurp|preview|fetcher|monitor|headless|lighthouse|validator/i', $userAgent);
		}

		protected static function normalizeHost($host)
		{
			$host = strtolower(trim((string) $host));
			$host = preg_replace('/:\d+$/', '', $host);
			return preg_replace('/^www\./', '', $host);
		}

		protected static function clean($value, $limit)
		{
			$value = trim(strip_tags((string) $value));
			$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
			return mb_substr($value, 0, (int) $limit);
		}

		protected static function server($key)
		{
			return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
		}

	}
