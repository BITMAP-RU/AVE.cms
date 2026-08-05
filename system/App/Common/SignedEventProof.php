<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/SignedEventProof.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Short-lived HMAC proof binding a public event to its server-side assignment. */
	class SignedEventProof
	{
		protected static $secrets = array();

		public static function issue($module, array $claims, $visitorHash, $ttl = 3600)
		{
			$claims['exp'] = time() + max(60, min(86400, (int) $ttl));
			$claims['visitor'] = hash('sha256', (string) $visitorHash);
			$payload = self::encode(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			return $payload . '.' . self::encode(hash_hmac('sha256', $payload, self::secret($module), true));
		}

		public static function verify($module, $proof, $visitorHash, array $expected = array())
		{
			$parts = explode('.', trim((string) $proof));
			if (count($parts) !== 2 || !hash_equals(self::encode(hash_hmac('sha256', $parts[0], self::secret($module), true)), $parts[1])) {
				return null;
			}

			$data = json_decode(self::decode($parts[0]), true);
			if (!is_array($data) || empty($data['exp']) || (int) $data['exp'] < time()
				|| empty($data['visitor']) || !hash_equals((string) $data['visitor'], hash('sha256', (string) $visitorHash))) {
				return null;
			}

			foreach ($expected as $key => $value) {
				if (!array_key_exists($key, $data) || (string) $data[$key] !== (string) $value) { return null; }
			}

			return $data;
		}

		protected static function secret($module)
		{
			if (isset(self::$secrets[$module])) { return self::$secrets[$module]; }
			self::$secrets[$module] = Lock::run('event-proof:' . $module, function () use ($module) {
				$data = ModuleSecretStore::read($module);
				if (empty($data['event_proof_key']) || !preg_match('/^[a-f0-9]{64}$/', (string) $data['event_proof_key'])) {
					$data['event_proof_key'] = bin2hex(random_bytes(32));
					ModuleSecretStore::write($module, $data);
				}

				return (string) $data['event_proof_key'];
			});
			return self::$secrets[$module];
		}

		protected static function encode($value)
		{
			return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
		}

		protected static function decode($value)
		{
			$value = strtr((string) $value, '-_', '+/');
			return (string) base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
		}
	}
