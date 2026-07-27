<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/DB_Eval.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	class DB_Eval
	{
		public $text;

		public function __construct($text)
		{
			$this->text = $text;
		}

		public function __toString()
		{
			return $this->text;
		}
	}
