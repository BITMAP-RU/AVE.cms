<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/SiteAccess.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\ResponseSecurityHeaders;
	use App\Common\Settings;

	/** Blocks the public runtime during development while preserving privileged preview. */
	class SiteAccess
	{
		const MODE_PUBLIC = 'public';
		const MODE_DEVELOPMENT = 'development';
		const PREVIEW_PERMISSION = 'view_development_site';

		protected static $preview = false;

		public static function enabled()
		{
			Settings::init();
			return (string) Settings::get('site_access_mode', self::MODE_PUBLIC) === self::MODE_DEVELOPMENT;
		}

		/** Returns false after emitting the temporary unavailable response. */
		public static function enforce()
		{
			self::$preview = false;
			if (!self::enabled()) {
				return true;
			}

			self::disablePublicCache();
			header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);
			header('X-AVE-Site-Access: development', true);

			if (Auth::systemUserCan(self::PREVIEW_PERMISSION)) {
				self::$preview = true;
				return true;
			}

			self::respondUnavailable();
			return false;
		}

		public static function isPreview()
		{
			return self::$preview;
		}

		/** Adds a small persistent marker without changing the site's document flow. */
		public static function injectPreviewNotice($html)
		{
			$html = (string) $html;
			if (!self::$preview || $html === '') {
				return $html;
			}

			$notice = '<div role="status" aria-label="Режим разработки" style="position:fixed;right:16px;bottom:16px;z-index:2147483000;max-width:340px;padding:10px 14px;border:1px solid #f59e0b;border-radius:6px;background:#fef3c7;color:#78350f;box-shadow:0 8px 24px rgba(15,23,42,.16);font:600 13px/1.4 system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">Режим разработки: посетителям сайт недоступен</div>';
			if (stripos($html, '</body>') !== false) {
				return preg_replace('/<\/body>/i', $notice . '</body>', $html, 1);
			}

			return $html . $notice;
		}

		public static function unavailableDocument($title, $message, $siteName = '')
		{
			$title = self::escape($title !== '' ? $title : 'Сайт готовится к запуску');
			$message = nl2br(self::escape($message !== '' ? $message : 'Мы завершаем настройку сайта. Пожалуйста, зайдите немного позже.'));
			$siteName = self::escape($siteName);

			return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
				. '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet"><title>' . $title . '</title>'
				. '<style>html{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f6f8;color:#1f2937;font:16px/1.6 system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif}.site-access{width:min(100%,620px);text-align:center}.site-access__mark{display:grid;width:48px;height:48px;margin:0 auto 20px;place-items:center;border-radius:6px;background:#dbeafe;color:#1d4ed8;font-size:24px;font-weight:700}.site-access h1{margin:0 0 12px;font-size:clamp(26px,5vw,38px);line-height:1.15}.site-access p{margin:0;color:#64748b}.site-access small{display:block;margin-top:24px;color:#94a3b8}</style></head><body>'
				. '<main class="site-access"><div class="site-access__mark" aria-hidden="true">A</div><h1>' . $title . '</h1><p>' . $message . '</p>'
				. ($siteName !== '' ? '<small>' . $siteName . '</small>' : '') . '</main></body></html>';
		}

		protected static function respondUnavailable()
		{
			if (ob_get_level() > 0) {
				ob_clean();
			}

			$title = trim((string) Settings::get('site_development_title', 'Сайт готовится к запуску'));
			$message = trim((string) Settings::get('site_development_message', 'Мы завершаем настройку сайта. Пожалуйста, зайдите немного позже.'));
			$siteName = trim((string) Settings::get('site_name', ''));
			$json = self::wantsJson();
			$body = $json
				? json_encode(array('success' => false, 'error' => 'site_development', 'message' => $title), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: self::unavailableDocument($title, $message, $siteName);

			http_response_code(503);
			ResponseSecurityHeaders::apply();
			header('Retry-After: 3600');
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
			header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=UTF-8');
			header('Content-Length: ' . strlen($body));
			if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'HEAD') {
				echo $body;
			}
		}

		protected static function wantsJson()
		{
			$path = parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
			$accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';
			return strpos('/' . ltrim((string) $path, '/'), '/api/') === 0
				|| strpos($accept, 'application/json') !== false
				|| (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
		}

		protected static function disablePublicCache()
		{
			if (!defined('PUBLIC_RESPONSE_NO_CACHE')) {
				define('PUBLIC_RESPONSE_NO_CACHE', true);
			}

			if (function_exists('apache_setenv')) {
				@apache_setenv('PUBLIC_NO_CACHE', '1');
			}

			$_SERVER['PUBLIC_NO_CACHE'] = '1';
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
		}

		protected static function escape($value)
		{
			return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
