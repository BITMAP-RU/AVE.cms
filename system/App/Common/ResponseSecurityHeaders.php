<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ResponseSecurityHeaders.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;

	/** Shared defensive headers for public and administrative responses. */
	class ResponseSecurityHeaders
	{
		public static function all()
		{
			return array(
				'X-Content-Type-Options: nosniff',
				'X-Frame-Options: SAMEORIGIN',
				'Referrer-Policy: strict-origin-when-cross-origin',
				'Permissions-Policy: camera=(), microphone=(), geolocation=()',
				"Content-Security-Policy: default-src 'self' data: blob: https:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob: http: https:; font-src 'self' data: https:; connect-src 'self' https: wss:; frame-src 'self' https:",
			);
		}

		public static function apply()
		{
			if (function_exists('header_remove')) {
				@header_remove('X-Powered-By');
				@header_remove('X-Engine');
				@header_remove('X-Engine-Version');
				@header_remove('X-Engine-Build');
				@header_remove('X-Engine-Copyright');
				@header_remove('X-Engine-Site');
			}

			Request::setHeaders(self::all());
		}
	}
