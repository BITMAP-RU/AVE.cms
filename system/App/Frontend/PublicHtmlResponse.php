<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicHtmlResponse.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ResponseSecurityHeaders;

	/** Emits the final public HTML response with the configured compression. */
	class PublicHtmlResponse
	{
		public static function compress($html)
		{
			return preg_replace(
				array(
					'/\>[^\S ]+/s',
					'/[^\S ]+\</s',
					'/(\s)+/s',
					'/<!--(?!ave-inspect:(?:begin|end):[0-9]+-->)(.|\s)*?-->/',
				),
				array('>', '<', '\\1', ''),
				(string) $html
			);
		}

		public static function emit($html)
		{
			$data = (string) $html;
			$acceptEncoding = isset($_SERVER['HTTP_ACCEPT_ENCODING'])
				? (string) $_SERVER['HTTP_ACCEPT_ENCODING']
				: '';
			$gzip = strpos($acceptEncoding, 'gzip') !== false;

			if (defined('HTML_COMPRESSION') && HTML_COMPRESSION) {
				$data = self::compress($data);
			}

			if (isset($_REQUEST['sysblock']) && !defined('ONLYCONTENT')) {
				define('ONLYCONTENT', true);
			}

			if ($gzip && defined('GZIP_COMPRESSION') && GZIP_COMPRESSION) {
				$data = gzencode($data, 9);
				header('Content-Encoding: gzip');
			}

			ResponseSecurityHeaders::apply();
			header('Content-Type: text/html; charset=utf-8');
			header(
				defined('PUBLIC_RESPONSE_NO_CACHE') && PUBLIC_RESPONSE_NO_CACHE
					? 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
					: 'Cache-Control: must-revalidate',
				true
			);

			if (defined('OUTPUT_EXPIRE') && OUTPUT_EXPIRE) {
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + OUTPUT_EXPIRE_OFFSET) . ' GMT');
			}

			header('Content-Length: ' . strlen($data));
			header('Vary: Accept-Encoding');
			echo $data;
		}
	}
