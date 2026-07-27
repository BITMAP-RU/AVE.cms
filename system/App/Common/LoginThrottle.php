<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/LoginThrottle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Политика защиты формы входа от перебора.
	 *
	 * Класс не знает о конкретной таблице пользователей и не выполняет вход.
	 * Он только строит устойчивые ключи лимитера и применяет лимиты по IP,
	 * идентификатору логина и паре IP+логин.
	 */
	class LoginThrottle
	{
		const DEFAULT_PREFIX = 'login';

		const IP_LIMIT = 12;
		const IP_DECAY = 900;
		const IDENTIFIER_LIMIT = 8;
		const IDENTIFIER_DECAY = 900;
		const PAIR_LIMIT = 5;
		const PAIR_DECAY = 900;

		/**
		 * Построить ключи для лимитера.
		 *
		 * @param string $identifier Логин/email/телефон из формы
		 * @param string $ip         IP клиента
		 * @param string $prefix     Префикс приложения, чтобы разные зоны входа не смешивались
		 * @return array
		 */
		public static function keys($identifier, $ip, $prefix = self::DEFAULT_PREFIX)
		{
			$identifier = self::normalizeIdentifier($identifier);
			$identifierHash = $identifier !== '' ? sha1($identifier) : 'empty';
			$ipHash = sha1((string) $ip);
			$prefix = trim((string) $prefix);
			if ($prefix === '') {
				$prefix = self::DEFAULT_PREFIX;
			}

			return [
				'ip' => $prefix . ':ip:' . $ipHash,
				'identifier' => $prefix . ':identifier:' . $identifierHash,
				'pair' => $prefix . ':pair:' . $ipHash . ':' . $identifierHash,
			];
		}

		/**
		 * Сколько секунд ждать до следующей попытки. 0 — можно пробовать.
		 */
		public static function availableIn(array $keys, array $limits = [])
		{
			$limits = self::limits($limits);
			$wait = 0;

			if (RateLimiter::tooManyAttempts($keys['ip'], $limits['ip_limit'])) {
				$wait = max($wait, RateLimiter::availableIn($keys['ip']));
			}

			if (RateLimiter::tooManyAttempts($keys['identifier'], $limits['identifier_limit'])) {
				$wait = max($wait, RateLimiter::availableIn($keys['identifier']));
			}

			if (RateLimiter::tooManyAttempts($keys['pair'], $limits['pair_limit'])) {
				$wait = max($wait, RateLimiter::availableIn($keys['pair']));
			}

			return $wait;
		}

		/**
		 * Зафиксировать неудачную попытку входа.
		 */
		public static function hit(array $keys, array $limits = [])
		{
			$limits = self::limits($limits);

			RateLimiter::hit($keys['ip'], $limits['ip_decay']);
			RateLimiter::hit($keys['identifier'], $limits['identifier_decay']);
			RateLimiter::hit($keys['pair'], $limits['pair_decay']);
		}

		/**
		 * Сбросить счётчики после успешного входа.
		 */
		public static function clear(array $keys)
		{
			RateLimiter::clear($keys['ip']);
			RateLimiter::clear($keys['identifier']);
			RateLimiter::clear($keys['pair']);
		}

		protected static function limits(array $limits)
		{
			return array_merge([
				'ip_limit' => self::IP_LIMIT,
				'ip_decay' => self::IP_DECAY,
				'identifier_limit' => self::IDENTIFIER_LIMIT,
				'identifier_decay' => self::IDENTIFIER_DECAY,
				'pair_limit' => self::PAIR_LIMIT,
				'pair_decay' => self::PAIR_DECAY,
			], $limits);
		}

		protected static function normalizeIdentifier($identifier)
		{
			$identifier = trim((string) $identifier);

			return function_exists('mb_strtolower')
				? mb_strtolower($identifier, 'UTF-8')
				: strtolower($identifier);
		}
	}
