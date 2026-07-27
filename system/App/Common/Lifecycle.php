<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Lifecycle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Hooks;

	/** Small dispatcher that keeps lifecycle hooks on one context contract. */
	class Lifecycle
	{
		public static function dispatch($name, LifecycleEvent $event)
		{
			if (!Hooks::exists((string) $name) && !Hooks::traceEnabled()) {
				return $event;
			}

			$result = Hooks::action((string) $name, $event);
			return $result instanceof LifecycleEvent ? $result : $event;
		}

		public static function event($name, $subject, $operation, $identifier = null, array $data = array(), $result = null, array $meta = array(), $source = 'runtime')
		{
			return self::dispatch($name, new LifecycleEvent(
				$subject,
				$operation,
				$identifier,
				$data,
				$result,
				$meta,
				$source
			));
		}
	}
