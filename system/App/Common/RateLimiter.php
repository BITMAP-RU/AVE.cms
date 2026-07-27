<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/RateLimiter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Ограничитель частоты запросов (Rate Limiter).
	 *
	 * Хранит состояние в файлах (через Cache). Подходит для:
	 *   - защиты формы входа от перебора
	 *   - ограничения API-запросов по IP
	 *   - защиты любых действий от злоупотреблений
	 *
	 * Пример:
	 *   $key = 'login:' . Request::ip();
	 *   if (RateLimiter::tooManyAttempts($key, 5)) {
	 *       Response::tooManyRequests('Слишком много попыток', RateLimiter::availableIn($key));
	 *   }
	 *   // ... попытка входа ...
	 *   if ($loginFailed) {
	 *       RateLimiter::hit($key, 60); // окно 60 секунд
	 *   } else {
	 *       RateLimiter::clear($key);
	 *   }
	 */
	class RateLimiter
	{
		/** Префикс ключей в хранилище */
		const PREFIX = 'rl:';

		// ------------------------------------------------------------------ //
		//  Основные методы
		// ------------------------------------------------------------------ //

		/**
		 * Зафиксировать попытку и проверить лимит.
		 * Возвращает true если попытка разрешена, false если лимит превышен.
		 *
		 * @param  string $key         Уникальный ключ (напр. 'login:127.0.0.1')
		 * @param  int    $maxAttempts Максимальное число попыток
		 * @param  int    $decaySeconds Окно в секундах
		 * @return bool   true — разрешено, false — заблокировано
		 */
		public static function attempt($key, $maxAttempts, $decaySeconds = 60)
		{
			// hit() выполняет проверку окна и инкремент под одним lock. Отдельная
			// tooManyAttempts() здесь создавала TOCTOU между проверкой и записью.
			return self::hit($key, $decaySeconds) <= (int) $maxAttempts;
		}

		/**
		 * Проверить, превышен ли лимит (без увеличения счётчика).
		 */
		public static function tooManyAttempts($key, $maxAttempts)
		{
			return self::attempts($key) >= $maxAttempts;
		}

		/**
		 * Зафиксировать одну попытку.
		 *
		 * @param  string $key
		 * @param  int    $decaySeconds Окно сброса. Если запись уже есть — окно не сдвигается.
		 * @return int    Текущее количество попыток
		 */
		public static function hit($key, $decaySeconds = 60)
		{
			// Инкремент попытки должен быть атомарным, иначе при параллельных
			// запросах счётчик недосчитывается и лимит обходится.
			return Lock::run(self::PREFIX . $key, function () use ($key, $decaySeconds) {
				$data = self::load($key);

				if ($data === null || $data['reset_at'] <= time()) {
					// Новое окно
					$data = [
						'attempts' => 1,
						'reset_at' => time() + (int)$decaySeconds,
					];
				} else {
					$data['attempts']++;
				}

				self::save($key, $data);

				return $data['attempts'];
			});
		}

		/**
		 * Получить текущее количество попыток.
		 */
		public static function attempts($key)
		{
			$data = self::load($key);

			if ($data === null || $data['reset_at'] <= time()) {
				return 0;
			}

			return (int)$data['attempts'];
		}

		/**
		 * Сколько секунд осталось до сброса окна.
		 * Возвращает 0 если ограничение ещё не достигнуто или окно уже истекло.
		 */
		public static function availableIn($key)
		{
			$data = self::load($key);

			if ($data === null || $data['reset_at'] <= time()) {
				return 0;
			}

			return max(0, $data['reset_at'] - time());
		}

		/**
		 * Сбросить счётчик (например, после успешного входа).
		 */
		public static function clear($key)
		{
			Cache::forget(self::PREFIX . $key);
		}

		/**
		 * Получить метку времени сброса окна.
		 * Возвращает 0 если нет активного окна.
		 */
		public static function resetAt($key)
		{
			$data = self::load($key);

			if ($data === null || $data['reset_at'] <= time()) {
				return 0;
			}

			return (int)$data['reset_at'];
		}

		// ------------------------------------------------------------------ //
		//  Вспомогательные
		// ------------------------------------------------------------------ //

		protected static function load($key)
		{
			return Cache::get(self::PREFIX . $key);
		}

		protected static function save($key, array $data)
		{
			$ttl = max(1, $data['reset_at'] - time() + 10); // +10с запас
			Cache::set(self::PREFIX . $key, $data, $ttl);
		}
	}
