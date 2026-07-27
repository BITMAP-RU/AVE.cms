<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Router.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Легковесный HTTP-роутер.
	 *
	 * Паттерны:
	 *   '/'                — корень
	 *   '/forecast'        — точный путь
	 *   '/product/{id}'    — именованный параметр
	 *   '/export/{type}'
	 *
	 * Хендлеры:
	 *   string             — путь к PHP-файлу (include на стороне вызывающего кода)
	 *   Closure            — анонимная функция, получает массив $params
	 *   [class, method]    — метод класса
	 *
	 * Примечание: для файловых хендлеров используйте match() + include на глобальном
	 * уровне, чтобы контроллер видел переменные точки входа.
	 */
	class Router
	{
		protected static $routes = [];

		// ------------------------------------------------------------------ //
		//  Регистрация маршрутов
		// ------------------------------------------------------------------ //

		public static function get($pattern, $handler, array $options = array())
		{
			self::add('GET', $pattern, $handler, $options);
		}

		public static function head($pattern, $handler, array $options = array())
		{
			self::add('HEAD', $pattern, $handler, $options);
		}

		public static function post($pattern, $handler, array $options = array())
		{
			self::add('POST', $pattern, $handler, $options);
		}

		public static function put($pattern, $handler, array $options = array())
		{
			self::add('PUT', $pattern, $handler, $options);
		}

		public static function patch($pattern, $handler, array $options = array())
		{
			self::add('PATCH', $pattern, $handler, $options);
		}

		public static function delete($pattern, $handler, array $options = array())
		{
			self::add('DELETE', $pattern, $handler, $options);
		}

		public static function any($pattern, $handler, array $options = array())
		{
			self::add('ANY', $pattern, $handler, $options);
		}

		/** Группа маршрутов с общим префиксом */
		public static function group($prefix, callable $fn)
		{
			$prefix = rtrim($prefix, '/');
			$backup = self::$routes;

			$fn();

			// Добавить префикс ко всем маршрутам, добавленным внутри группы
			$added = array_slice(self::$routes, count($backup));
			self::$routes = $backup;
			foreach ($added as $route) {
				$route['pattern'] = $prefix . $route['pattern'];
				self::$routes[]   = $route;
			}
		}

		// ------------------------------------------------------------------ //
		//  Поиск маршрута
		// ------------------------------------------------------------------ //

		/**
		 * Найти маршрут для пути и метода.
		 *
		 * @param  string|null $path   URI-путь (без query string)
		 * @param  string|null $method HTTP-метод
		 * @return array|null  ['handler' => mixed, 'params' => []] или null если не найдено
		 */
		public static function match($path = null, $method = null)
		{
			if ($path === null) {
				$uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
				$path = parse_url($uri, PHP_URL_PATH);
			}

			$path   = '/' . ltrim((string) $path, '/');
			$method = strtoupper($method ?: (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'));

			// Поддержка _method override (PUT/DELETE через POST-форму)
			if ($method === 'POST' && isset($_POST['_method'])) {
				$override = strtoupper($_POST['_method']);
				if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
					$method = $override;
				}
			}

			$matched = self::find($path, $method, true);
			if ($matched === null && $method === 'HEAD') {
				$matched = self::find($path, 'GET', false);
			}

			if ($matched === null) {
				return null;
			}

			$route = $matched['route'];
			return [
				'handler' => $route['handler'],
				'params' => $matched['params'],
				'method' => $route['method'],
				'pattern' => $route['pattern'],
				'options' => isset($route['options']) ? $route['options'] : array(),
			];
		}

		protected static function find($path, $method, $includeAny)
		{
			$matched = null;
			foreach (self::$routes as $route) {
				if ($route['method'] !== $method && (!$includeAny || $route['method'] !== 'ANY')) {
					continue;
				}

				$regex = self::compile($route['pattern']);

				if (preg_match($regex, $path, $routeMatches)) {
					$candidate = array(
						'route' => $route,
						'params' => array_filter($routeMatches, 'is_string', ARRAY_FILTER_USE_KEY),
						'score' => self::specificity($route['pattern']),
					);
					if ($matched === null || $candidate['score'] > $matched['score']) {
						$matched = $candidate;
					}
				}
			}

			return $matched;
		}

		/**
		 * Вернуть все зарегистрированные маршруты (для отладки).
		 */
		public static function routes()
		{
			return self::$routes;
		}

		/**
		 * Сбросить все маршруты (для тестов).
		 */
		public static function reset()
		{
			self::$routes = [];
		}

		// ------------------------------------------------------------------ //
		//  Компиляция паттерна
		// ------------------------------------------------------------------ //

		protected static function compile($pattern)
		{
			$pattern = rtrim($pattern, '/') ?: '/';
			$parts = preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$compiled = '';
			foreach ($parts as $part) {
				if (preg_match('/^\{(\w+)\}$/', $part, $match)) {
					$compiled .= '(?P<' . $match[1] . '>[^/]+)';
				} else {
					$compiled .= preg_quote($part, '#');
				}
			}

			return '#^' . $compiled . '/?$#';
		}

		/** Конкретный маршрут должен выигрывать у ранее объявленного общего. */
		protected static function specificity($pattern)
		{
			$parameters = preg_match_all('/\{\w+\}/', (string) $pattern, $unused);
			$literal = preg_replace('/\{\w+\}/', '', (string) $pattern);
			return (100 - (int) $parameters) * 10000 + strlen($literal);
		}

		// ------------------------------------------------------------------ //
		//  Вспомогательные
		// ------------------------------------------------------------------ //

		protected static function add($method, $pattern, $handler, array $options = array())
		{
			self::$routes[] = [
				'method'  => strtoupper($method),
				'pattern' => $pattern,
				'handler' => $handler,
				'options' => $options,
			];
		}
	}
