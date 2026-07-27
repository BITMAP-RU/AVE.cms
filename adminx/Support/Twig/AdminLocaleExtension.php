<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Twig/AdminLocaleExtension.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support\Twig;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\AdminLocale;
	use Twig\Extension\AbstractExtension;
	use Twig\TwigFilter;
	use Twig\TwigFunction;

	class AdminLocaleExtension extends AbstractExtension
	{
		public function getNodeVisitors()
		{
			return array(new AdminLocaleNodeVisitor());
		}


		public function getFilters()
		{
			return array(
				new TwigFilter('admin_trans', array(AdminLocale::class, 'translateMarkup')),
			);
		}


		public function getFunctions()
		{
			return array(
				new TwigFunction('admin_trans', array(AdminLocale::class, 'translateMarkup')),
			);
		}
	}
