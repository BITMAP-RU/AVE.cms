<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Cookie.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');


	class Cookie
	{
	/**
	 * Установить Cookie
	 *
	 * Метод устанавливает cookie в браузере пользователя с заданными параметрами.
	 * Используется для хранения пользовательских данных на стороне клиента.
	 *
	 * @param string $key Ключ cookie
	 * @param mixed $value Значение cookie
	 * @param int $expire Время жизни cookie в секундах (по умолчанию 86400 - 24 часа)
	 * @param string $domain Домен, для которого будет установлен cookie (по умолчанию пустая строка)
	 * @param string $path Путь на сервере, для которого будет установлен cookie (по умолчанию '/')
	 * @param bool $secure Флаг secure (cookie будет передаваться только по HTTPS)
	 * @param bool $httpOnly Флаг httpOnly (cookie будет доступен только через HTTP, а не JavaScript)
	 * @param string $sameSite Политика SameSite: Lax, Strict или None
	 * @return bool Результат установки cookie
	 *
	 * @example
	 * <code>
	 * Cookie::set('username', 'john_doe', 3600);
	 * // Установит cookie с именем 'username' и значением 'john_doe' на 1 час
	 * </code>
	 */

	public static function set ($key, $value, $expire = 86400, $domain = '', $path = '/', $secure = false, $httpOnly = false, $sameSite = 'Lax')
	{
		$key      = (string) $key;
		$path     = (string) $path;
		$domain   = (string) $domain;
		$secure   = (bool) $secure;
		$httpOnly = (bool) $httpOnly;
		$sameSite = self::sameSite($sameSite, $secure);

		$expires = $expire > 0 ? time() + $expire : $expire;

		return setcookie($key, $value, array(
			'expires' => $expires,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => $httpOnly,
			'samesite' => $sameSite,
		));
	}


	/**
	 * Проверить наличие Cookie по ключу
	 *
	 * Метод проверяет, существует ли cookie с указанным ключом в массиве $_COOKIE.
	 * Используется для проверки наличия пользовательских данных в браузере.
	 *
	 * @param string $key Ключ cookie для проверки
	 * @return bool TRUE если cookie существует, FALSE в противном случае
	 *
	 * @example
	 * <code>
	 * if (Cookie::has('username')) {
	 *     // Cookie 'username' существует
	 * }
	 * </code>
	 */
	public static function has ($key)
	{
		return ! is_null(Arr::get($_COOKIE, $key));
	}


	/**
	 * Получить Cookie по ключу
	 *
	 * Метод возвращает значение cookie с указанным ключом из массива $_COOKIE.
	 * Если cookie не существует, возвращается значение по умолчанию.
	 * Используется для получения пользовательских данных, сохраненных в браузере.
	 *
	 * @param string $key Ключ cookie для получения значения
	 * @param mixed $default Значение по умолчанию, если cookie не найден
	 * @return mixed Значение cookie или значение по умолчанию
	 *
	 * @example
	 * <code>
	 * $username = Cookie::get('username', 'guest');
	 * // Вернет значение cookie 'username' или 'guest', если cookie не существует
	 * </code>
	 */
	public static function get ($key = null, $default = null)
	{
		return Arr::get($_COOKIE, $key, $default);
	}


	/**
	 * Удалить Cookie по ключу
	 *
	 * Метод удаляет cookie с указанным ключом из массива $_COOKIE.
	 * Используется для очистки пользовательских данных, сохраненных в браузере.
	 *
	 * @param string $key Ключ cookie для удаления
	 * @return void
	 *
	 * @example
	 * <code>
	 * Cookie::delete('username');
	 * // Удалит cookie с именем 'username'
	 * </code>
	 */
	public static function delete ($key, $path = '/', $domain = '', $secure = null, $sameSite = 'Lax')
	{
		$secure = $secure === null ? Request::isHttps() : (bool) $secure;
		setcookie((string) $key, '', array(
			'expires' => time() - 3600,
			'path' => (string) $path,
			'domain' => (string) $domain,
			'secure' => $secure,
			'httponly' => true,
			'samesite' => self::sameSite($sameSite, $secure),
		));
		unset($_COOKIE[$key]);
	}


	protected static function sameSite($value, $secure)
	{
		$value = ucfirst(strtolower(trim((string) $value)));
		if (!in_array($value, array('Lax', 'Strict', 'None'), true)) {
			$value = 'Lax';
		}

		return $value === 'None' && !$secure ? 'Lax' : $value;
	}
	}
