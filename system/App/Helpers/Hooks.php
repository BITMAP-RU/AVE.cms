<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Hooks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	class Hooks
	{
		public static $instance;

		/**
		 * Зарегистрированные обработчики.
		 * $hooks[name][priority][id] = ['function' => callable]
		 * @var array
		 */
		public static $hooks = [];

		/** Имя хука, выполняющегося прямо сейчас */
		public static $current_hook = '';

		/** Журнал всех сработавших хуков */
		public static $run_hooks = [];

		/** Per-handler runtime trace used by the protected public debugger. */
		protected static $trace = array();
		protected static $stack = array();
		protected static $traceDropped = 0;
		protected static $traceEnabled = false;
		const TRACE_LIMIT = 1500;

		public static function init()
		{
			if (!self::$instance) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		// ------------------------------------------------------------------ //
		//  Регистрация
		// ------------------------------------------------------------------ //

		/**
		 * Зарегистрировать обработчик хука.
		 *
		 * @param string|string[] $name     Имя хука или массив имён
		 * @param callable        $function Обработчик (строка, массив [class, method] или Closure)
		 * @param int             $priority Приоритет (меньше = раньше)
		 */
		public static function add($name, $function, $priority = 10)
		{
			$id = self::makeId($function);

			if (is_array($name)) {
				foreach ($name as $item) {
					self::$hooks[$item][$priority][$id] = ['function' => $function];
				}
			} else {
				if (isset(self::$hooks[$name][$priority][$id])) {
					return true;
				}

				self::$hooks[$name][$priority][$id] = ['function' => $function];
			}

			return true;
		}

		/**
		 * Зарегистрировать обработчик, который сработает ровно один раз и удалится.
		 *
		 * @param string   $name
		 * @param callable $function
		 * @param int      $priority
		 */
		public static function once($name, $function, $priority = 10)
		{
			$wrapper = null;
			$wrapper = function () use ($name, $function, $priority, &$wrapper) {
				$result = call_user_func_array($function, func_get_args());
				self::remove($name, $wrapper, $priority);
				return $result;
			};
			return self::add($name, $wrapper, $priority);
		}

		/**
		 * Удалить обработчик хука.
		 *
		 * @param string   $name
		 * @param callable $function
		 * @param int      $priority
		 */
		public static function remove($name, $function, $priority = 10)
		{
			$id = self::makeId($function);

			if (!isset(self::$hooks[$name][$priority][$id])) {
				return true;
			}

			unset(self::$hooks[$name][$priority][$id]);

			if (empty(self::$hooks[$name][$priority])) {
				unset(self::$hooks[$name][$priority]);
			}

			return true;
		}

		// ------------------------------------------------------------------ //
		//  Выполнение
		// ------------------------------------------------------------------ //

		/**
		 * Запустить хук-событие. Обработчики вызываются по порядку приоритета.
		 * Возвращаемое значение обработчика может модифицировать $arguments.
		 *
		 * @param  string $name
		 * @param  mixed  $arguments
		 * @return mixed
		 */
		public static function action($name, $arguments = '')
		{
			self::begin($name, 'action');
			if (!isset(self::$hooks[$name])) {
				self::record($name, 'action', '', null, 0.0, 'unhandled');
				self::end();
				return $arguments;
			}

			ksort(self::$hooks[$name]);

			foreach (self::$hooks[$name] as $priority => $handlers) {
				foreach ($handlers as $handler) {
					$started = microtime(true);
					try {
						$return = call_user_func_array($handler['function'], array(&$arguments));
						if ($return !== null) {
							$arguments = $return;
						}

						self::record($name, 'action', self::handlerLabel($handler['function']), $priority, microtime(true) - $started, 'ok');
					} catch (\Throwable $e) {
						self::record($name, 'action', self::handlerLabel($handler['function']), $priority, microtime(true) - $started, 'error', $e);
						self::end();
						throw $e;
					}
				}
			}

			self::end();

			return $arguments;
		}

		/**
		 * Запустить хук-фильтр. Значение передаётся по цепочке обработчиков и возвращается.
		 * Каждый обработчик получает текущее значение первым аргументом и должен его вернуть.
		 *
		 * @param  string $name
		 * @param  mixed  $value
		 * @return mixed
		 */
		public static function filter($name, $value)
		{
			self::begin($name, 'filter');
			if (!isset(self::$hooks[$name])) {
				self::record($name, 'filter', '', null, 0.0, 'unhandled');
				self::end();
				return $value;
			}

			ksort(self::$hooks[$name]);

			foreach (self::$hooks[$name] as $priority => $handlers) {
				foreach ($handlers as $handler) {
					$started = microtime(true);
					try {
						$result = call_user_func($handler['function'], $value);
						if ($result !== null) {
							$value = $result;
						}

						self::record($name, 'filter', self::handlerLabel($handler['function']), $priority, microtime(true) - $started, 'ok');
					} catch (\Throwable $e) {
						self::record($name, 'filter', self::handlerLabel($handler['function']), $priority, microtime(true) - $started, 'error', $e);
						self::end();
						throw $e;
					}
				}
			}

			self::end();

			return $value;
		}

		public static function trace()
		{
			return array(
				'entries' => self::$trace,
				'dropped' => self::$traceDropped,
				'counts' => array_count_values(self::$run_hooks),
			);
		}

		public static function enableTrace($enabled = true)
		{
			self::$traceEnabled = (bool) $enabled;
		}

		public static function traceEnabled()
		{
			return self::$traceEnabled;
		}

		public static function resetRuntime()
		{
			self::$current_hook = '';
			self::$run_hooks = array();
			self::$trace = array();
			self::$stack = array();
			self::$traceDropped = 0;
			self::$traceEnabled = false;
		}

		// ------------------------------------------------------------------ //
		//  Проверки
		// ------------------------------------------------------------------ //

		/** Проверить, есть ли хоть один обработчик для хука */
		public static function exists($name)
		{
			return !empty(self::$hooks[$name]);
		}

		/** Проверить, есть ли обработчики с конкретным приоритетом */
		public static function has($hook, $priority = 10)
		{
			return !empty(self::$hooks[$hook][$priority]);
		}

		/** Имя хука, выполняющегося сейчас */
		public static function current()
		{
			return self::$current_hook;
		}

		// ------------------------------------------------------------------ //
		//  Отладка
		// ------------------------------------------------------------------ //

		public static function debug()
		{
			$map = array();
			foreach (self::$hooks as $hookName => $priorities) {
				foreach ($priorities as $prio => $handlers) {
					foreach ($handlers as $id => $handler) {
						$fn = $handler['function'];
						$map[$hookName][$prio][] = is_string($fn) ? $fn : (is_array($fn) ? implode('::', $fn) : 'Closure#' . $id);
					}
				}
			}

			return array(
				'registered' => $map,
				'run' => array_count_values(self::$run_hooks),
			);
		}

		// ------------------------------------------------------------------ //
		//  Приватное
		// ------------------------------------------------------------------ //

		/**
		 * Получить уникальный строковый идентификатор callable.
		 * Для строк — имя функции, для массивов — Class::method, для Closure — spl_object_hash.
		 */
		protected static function makeId($function)
		{
			if (is_string($function)) {
				return $function;
			}

			if (is_array($function)) {
				return (is_object($function[0]) ? get_class($function[0]) : $function[0]) . '::' . $function[1];
			}

			return spl_object_hash($function);
		}

		protected static function begin($name, $kind)
		{
			$name = (string) $name;
			self::$run_hooks[] = $name;
			self::$stack[] = $name;
			self::$current_hook = $name;
			if (self::$traceEnabled && class_exists('App\\Common\\HookCatalog')) {
				\App\Common\HookCatalog::observe($name, $kind);
			}
		}

		protected static function end()
		{
			array_pop(self::$stack);
			self::$current_hook = self::$stack ? (string) end(self::$stack) : '';
		}

		protected static function record($name, $kind, $handler, $priority, $duration, $status, $exception = null)
		{
			if (!self::$traceEnabled) {
				return;
			}

			if (count(self::$trace) >= self::TRACE_LIMIT) {
				self::$traceDropped++;
				return;
			}

			self::$trace[] = array(
				'name' => (string) $name,
				'kind' => (string) $kind,
				'handler' => (string) $handler,
				'priority' => $priority === null ? null : (int) $priority,
				'duration' => (float) $duration,
				'status' => (string) $status,
				'depth' => max(0, count(self::$stack) - 1),
				'exception' => $exception instanceof \Throwable ? get_class($exception) . ': ' . $exception->getMessage() : '',
			);
		}

		protected static function handlerLabel($function)
		{
			if (is_string($function)) {
				return $function;
			}

			if (is_array($function)) {
				return (is_object($function[0]) ? get_class($function[0]) : (string) $function[0]) . '::' . $function[1];
			}

			return $function instanceof \Closure ? 'Closure#' . substr(spl_object_hash($function), -8) : 'callable';
		}
	}
