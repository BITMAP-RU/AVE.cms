<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/EditConflict.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class EditConflict extends \RuntimeException
	{
		protected $currentVersion;

		public function __construct($currentVersion)
		{
			parent::__construct('Документ уже изменён в другой вкладке или другим пользователем');
			$this->currentVersion = max(1, (int) $currentVersion);
		}

		public function currentVersion()
		{
			return $this->currentVersion;
		}
	}
