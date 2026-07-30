<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/GlobalSearch/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\GlobalSearch;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Controller as BaseController;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			$query = trim(Request::getStr('q', ''));
			if (mb_strlen($query, 'UTF-8') < 2) {
				return $this->success('', array('data' => array('items' => array())));
			}

			return $this->success('', array('data' => array('items' => Model::search($query, 30))));
		}
	}
