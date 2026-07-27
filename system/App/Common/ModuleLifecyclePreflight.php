<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleLifecyclePreflight.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Stores short-lived one-use confirmations for destructive module lifecycle actions. */
	class ModuleLifecyclePreflight
	{
		const SESSION_KEY = '_module_lifecycle_preflight';
		const TTL = 300;
		const MAX_TOKENS = 8;

		public static function issue($moduleCode, $operation, $actorId, array $preview)
		{
			$actorId = (int) $actorId;
			if ($actorId < 1) {
				throw new \RuntimeException('Для подтверждения lifecycle-операции требуется авторизация');
			}

			$token = bin2hex(random_bytes(32));
			$entries = self::activeEntries();
			$entries[hash('sha256', $token)] = array(
				'module' => (string) $moduleCode,
				'operation' => (string) $operation,
				'actor_id' => $actorId,
				'fingerprint' => self::fingerprint($preview),
				'expires_at' => time() + self::TTL,
			);

			while (count($entries) > self::MAX_TOKENS) {
				array_shift($entries);
			}

			Session::set(self::SESSION_KEY, $entries);
			return $token;
		}

		public static function consume($token, $moduleCode, $operation, $actorId, array $preview)
		{
			$entries = self::activeEntries();
			$key = hash('sha256', (string) $token);
			$entry = isset($entries[$key]) ? $entries[$key] : null;
			if ($entry) {
				unset($entries[$key]);
			}

			Session::set(self::SESSION_KEY, $entries);

			if (!$entry
				|| !isset($entry['module'], $entry['operation'], $entry['actor_id'], $entry['fingerprint'], $entry['expires_at'])
				|| !hash_equals((string) $entry['module'], (string) $moduleCode)
				|| !hash_equals((string) $entry['operation'], (string) $operation)
				|| (int) $entry['actor_id'] !== (int) $actorId
				|| (int) $entry['expires_at'] < time()) {
				throw new \RuntimeException('Подтверждение lifecycle-операции недействительно или устарело');
			}

			if (!hash_equals((string) $entry['fingerprint'], self::fingerprint($preview))) {
				throw new \RuntimeException('Состав данных модуля изменился. Повторите предварительную проверку');
			}
		}

		public static function fingerprint(array $preview)
		{
			$normalized = self::normalize($preview);
			$json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) {
				throw new \RuntimeException('Не удалось вычислить состав lifecycle-операции');
			}

			return hash('sha256', $json);
		}

		protected static function activeEntries()
		{
			$stored = Session::get(self::SESSION_KEY);
			$entries = is_array($stored) ? $stored : array();
			$now = time();
			foreach ($entries as $key => $entry) {
				if (!is_array($entry) || !isset($entry['expires_at']) || (int) $entry['expires_at'] < $now) {
					unset($entries[$key]);
				}
			}

			return $entries;
		}

		protected static function normalize($value)
		{
			if (!is_array($value)) {
				return $value;
			}

			if (self::isAssociative($value)) {
				ksort($value, SORT_STRING);
			}

			foreach ($value as $key => $item) {
				$value[$key] = self::normalize($item);
			}

			return $value;
		}

		protected static function isAssociative(array $value)
		{
			if ($value === array()) {
				return false;
			}

			return array_keys($value) !== range(0, count($value) - 1);
		}
	}
