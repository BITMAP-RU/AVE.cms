<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/DB_Exception.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	class DB_Exception extends Exception
	{
		protected $query = '';

		function __construct($message = '', $query = '', $code = 0)
		{
			parent::__construct($message);

			$this->query = $query;
			$this->code = $code;
		}

		public function getQuery()
		{
			return $this->query;
		}
	}
