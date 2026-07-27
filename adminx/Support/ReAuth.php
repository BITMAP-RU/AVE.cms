<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/ReAuth.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\Session;

	/** Short-lived password confirmation for high-risk Adminx actions. */
	class ReAuth
	{
		const DEFAULT_TTL = 300;
		const MAX_TTL = 900;
		const SESSION_KEY = '_adminx_reauth';

		public static function confirm($password)
		{
			$user = Auth::user();
			$hash = self::passwordHash($user);
			if (!$user || $hash === '' || !password_verify((string) $password, $hash)) {
				self::forget();
				return false;
			}

			Session::set(self::SESSION_KEY, array(
				'user_id' => (int) $user['id'],
				'confirmed_at' => time(),
				'password_fingerprint' => hash('sha256', $hash),
			));

			return true;
		}

		public static function valid($ttl = self::DEFAULT_TTL)
		{
			$user = Auth::user();
			$state = Session::get(self::SESSION_KEY);
			$hash = self::passwordHash($user);
			$ttl = self::normalizeTtl($ttl);

			if (!$user || !is_array($state) || $hash === '') {
				return false;
			}

			$valid = isset($state['user_id'], $state['confirmed_at'], $state['password_fingerprint'])
				&& (int) $state['user_id'] === (int) $user['id']
				&& (int) $state['confirmed_at'] >= time() - $ttl
				&& hash_equals((string) $state['password_fingerprint'], hash('sha256', $hash));

			if (!$valid) {
				self::forget();
			}

			return $valid;
		}

		public static function forget()
		{
			Session::del(self::SESSION_KEY);
		}

		public static function normalizeTtl($ttl)
		{
			return max(30, min(self::MAX_TTL, (int) $ttl));
		}

		protected static function passwordHash($user)
		{
			return is_array($user) && isset($user['password_hash'])
				? trim((string) $user['password_hash'])
				: '';
		}
	}
