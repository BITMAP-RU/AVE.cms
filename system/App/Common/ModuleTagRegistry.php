<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleTagRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Реестр безопасных обработчиков модульных тегов.
	 *
	 * В отличие от legacy ModulePHPTag обработчик сразу возвращает HTML и никогда
	 * не генерирует PHP-код для последующего eval.
	 */
	class ModuleTagRegistry
	{
		protected static $handlers = array();
		protected static $deferPrivate = false;
		protected static $renderObserver;

		public static function register($code, $pattern, $handler, $priority = 10, array $options = array())
		{
			$code = trim((string) $code);
			if ($code === '' || !is_string($pattern) || $pattern === '' || !is_callable($handler)) {
				throw new \InvalidArgumentException('Некорректный обработчик модульного тега');
			}

			self::$handlers[] = array(
				'code' => $code,
				'pattern' => $pattern,
				'handler' => $handler,
				'priority' => (int) $priority,
				'private' => empty($options['cacheable']),
				'inspect' => isset($options['inspect']) && is_array($options['inspect']) ? $options['inspect'] : array(),
			);

			usort(self::$handlers, function ($left, $right) {
				return $left['priority'] <=> $right['priority'];
			});
		}

		public static function parse($content, array $context = array(), $scope = 'all')
		{
			$content = (string) $content;
			foreach (self::$handlers as $definition) {
				$isPrivate = !empty($definition['private']);
				if ((self::$deferPrivate && $isPrivate)
					|| ($scope === 'private' && !$isPrivate)
					|| ($scope === 'cacheable' && $isPrivate)) {
					continue;
				}

				$handler = $definition['handler'];
				$originalPattern = $definition['pattern'];
				$content = preg_replace_callback($originalPattern, function ($matches) use ($handler, $context, $definition) {
					try {
						$result = call_user_func($handler, $matches, $context);
						$result = is_string($result) ? $result : '';
						if (is_callable(self::$renderObserver)) {
							$observed = call_user_func(self::$renderObserver, $result, array(
								'code' => $definition['code'],
								'tag' => isset($matches[0]) ? (string) $matches[0] : '',
								'private' => !empty($definition['private']),
								'inspect' => isset($definition['inspect']) ? $definition['inspect'] : array(),
							));
							$result = is_string($observed) ? $observed : $result;
						}

						return $result;
					} catch (\Throwable $e) {
						error_log('Public module tag [' . $definition['code'] . ']: ' . $e->getMessage());
						return '';
					}
				}, $content);
			}

			return $content;
		}

		public static function deferPrivate($defer = true)
		{
			self::$deferPrivate = (bool) $defer;
		}

		public static function all()
		{
			return self::$handlers;
		}

		public static function setRenderObserver($observer = null)
		{
			if ($observer !== null && !is_callable($observer)) {
				throw new \InvalidArgumentException('Некорректный observer модульного тега');
			}

			self::$renderObserver = $observer;
		}

		public static function reset()
		{
			self::$handlers = array();
			self::$deferPrivate = false;
			self::$renderObserver = null;
		}
	}
