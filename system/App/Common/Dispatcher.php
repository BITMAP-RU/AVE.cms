<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Dispatcher.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Резолвер маршрутов: связывает Router::match() с реальным вызовом хендлера.
	 *
	 * Router только находит маршрут и возвращает ['handler', 'params']; сам вызов
	 * хендлера (создание контроллера, вызов метода, include файла) вынесен сюда,
	 * чтобы точки входа (adminx/index.php и т.п.) не дублировали эту логику.
	 *
	 * Поддерживаемые формы хендлера (как в докблоке Router):
	 *   [class, method]   — метод контроллера; класс инстанцируется (или объект как есть);
	 *   Closure/callable  — вызывается с массивом $params;
	 *   string (файл)     — include файла-хендлера.
	 *
	 * Именованные параметры маршрута ({id} и т.п.) передаются методу/функции
	 * единым ассоциативным массивом $params — контроллеры объявляют
	 * `method(array $params = [])`.
	 *
	 * Инфраструктура общая (как Router): не знает ни про adminx, ни про модули.
	 */
	class Dispatcher
	{
		protected function __construct()
		{
			//
		}

		/**
		 * Найти маршрут и выполнить его.
		 *
		 * @param  string|null $path   Путь (уже без монтажного префикса)
		 * @param  string|null $method HTTP-метод
		 * @return array{matched: bool, output: mixed}
		 *         matched=false — маршрут не найден (вызывающий рендерит 404);
		 *         output — возвращённое хендлером значение (обычно строка HTML;
		 *         JSON/redirect-хендлеры завершают запрос сами).
		 */
		public static function handle($path = null, $method = null, $guard = null)
		{
			$route = Router::match($path, $method);

			if ($route === null) {
				return array('matched' => false, 'output' => null);
			}

			if ($guard !== null) {
				if (!is_callable($guard)) {
					throw new \InvalidArgumentException('Route guard must be callable');
				}

				call_user_func($guard, $route);
			}

			$output = self::invoke($route['handler'], $route['params']);
			$requestMethod = strtoupper((string) ($method ?: (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET')));

			return array(
				'matched' => true,
				'output'  => $requestMethod === 'HEAD' ? '' : $output,
			);
		}

		/**
		 * Вызвать хендлер с параметрами маршрута.
		 *
		 * @param  mixed $handler [class, method] | callable | string(файл)
		 * @param  array $params  именованные параметры маршрута
		 * @return mixed
		 * @throws \RuntimeException если хендлер не резолвится
		 */
		public static function invoke($handler, array $params = array())
		{
			// [class, method]
			if (is_array($handler) && count($handler) === 2 && !is_callable($handler[0])) {
				list($class, $method) = $handler;
				$instance = is_object($class) ? $class : new $class();

				if (!method_exists($instance, $method)) {
					throw new \RuntimeException(
						'Route handler method not found: ' . (is_object($class) ? get_class($class) : $class) . '::' . $method
					);
				}

				return call_user_func(array($instance, $method), $params);
			}

			// Closure / callable (в т.ч. 'Class::method' и [$obj, 'method'])
			if (is_callable($handler)) {
				return call_user_func($handler, $params);
			}

			// Файл-хендлер
			if (is_string($handler) && is_file($handler)) {
				return include $handler;
			}

			throw new \RuntimeException('Unresolvable route handler');
		}
	}
