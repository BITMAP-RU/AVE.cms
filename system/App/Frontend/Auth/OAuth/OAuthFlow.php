<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/OAuthFlow.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session;
	use App\Common\SiteOrigin;

	class OAuthFlow
	{
		const SESSION_KEY = 'public_oauth_flows';
		const TTL = 600;

		public static function create($provider, $returnUrl, array $context = array())
		{
			$state = bin2hex(random_bytes(24));
			$verifier = self::base64Url(random_bytes(48));
			$flow = array(
				'provider' => (string) $provider, 'state' => $state,
				'return_url' => (string) $returnUrl, 'expires_at' => time() + self::TTL,
				'redirect_uri' => self::callbackUrl($provider, true),
				'code_verifier' => $verifier,
				'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
			);
			if (!empty($context['link_user_id'])) { $flow['link_user_id'] = (int) $context['link_user_id']; }
			$flows = self::active();
			$flows[$state] = $flow;
			while (count($flows) > 5) { array_shift($flows); }
			Session::set(self::SESSION_KEY, $flows);
			return $flow;
		}

		public static function consume($provider, $state)
		{
			$flows = self::active();
			$state = trim((string) $state);
			$flow = isset($flows[$state]) ? $flows[$state] : null;
			unset($flows[$state]);
			Session::set(self::SESSION_KEY, $flows);
			if (!$flow || !hash_equals((string) $flow['provider'], (string) $provider)) {
				throw new \InvalidArgumentException('Сессия социального входа устарела. Повторите вход.');
			}

			return $flow;
		}

		public static function callbackUrl($provider, $required = false)
		{
			return SiteOrigin::absolute(
				'/auth/oauth/' . rawurlencode((string) $provider) . '/callback',
				(bool) $required
			);
		}

		protected static function active()
		{
			$flows = Session::get(self::SESSION_KEY);
			$flows = is_array($flows) ? $flows : array();
			foreach ($flows as $state => $flow) {
				if (!is_array($flow) || empty($flow['expires_at']) || (int) $flow['expires_at'] < time()) { unset($flows[$state]); }
			}

			return $flows;
		}

		protected static function base64Url($value)
		{
			return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
		}
	}
