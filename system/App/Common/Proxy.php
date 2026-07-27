<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Proxy.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	class Proxy {

		public function __get ($key)
		{
			return $this->{$key};
		}


		public function __set ( $key, $value)
		{
			$this->{$key} = $value;
		}

		public function __call (string $name, array $arguments)
		{
			$arg_data = [];

			foreach ($arguments as $arg)
			{
				if ($arg instanceof Ref) {
					$arg_data[] =& $arg->getRef();
				} else {
					$arg_data[] =& $arg;
				}
			}

			if (isset($this->{$name})) {
				return call_user_func_array($this->{$name}, $arg_data);
			} else {
				$trace = debug_backtrace();

				exit('<b>Notice</b>:  Undefined property: Proxy::' . $name . ' in <b>' . $trace[1]['file'] . '</b> on line <b>' . $trace[1]['line'] . '</b>');
			}
		}
	}
