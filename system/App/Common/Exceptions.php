<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Exceptions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || exit('Direct access to this location is not allowed.');

	use App\Helpers\Debug;
	use App\Common\Logs;
	use Exception;


	class Exceptions extends Exception
	{
		protected $code;
		//

		public function __construct($message = null, array $values = [], $code = 0, $_trace = true, $status = 0, $log = true)
		{
			if (! is_null($message) && ! (empty($values)))
			{
				$values = array_map('htmlspecialchars', $values);
				$message = str_replace(array_keys($values), array_values($values), $message);
			}

			if ($_trace)
			{
				$traces = Debug::debugBacktrace();

				foreach ($traces as $trace)
				{
					$message .= "\n<br>{$trace['file']}:{$trace['line']} {$trace['function']}";
				}
			}

			$log && Logs::instance()->clear()->status($status)->write(strip_tags($message));

			$isNumeric = is_numeric($code);

			parent::__construct($message, $isNumeric ? $code : 0);

			! $isNumeric && $this->code = $code;
		}
	}
