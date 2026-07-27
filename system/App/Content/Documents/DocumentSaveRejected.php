<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentSaveRejected.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class DocumentSaveRejected extends \RuntimeException
	{
		protected $validationErrors;

		public function __construct($message, array $errors = array())
		{
			parent::__construct((string) $message);
			$this->validationErrors = $errors;
		}

		public function errors() { return $this->validationErrors; }
	}
