<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Ref.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	class Ref {

		private $value;

		public function __construct (&$value)
		{
			$this->value = &$value;
		}

		public function &getRef()
		{
			return $this->value;
		}
	}
