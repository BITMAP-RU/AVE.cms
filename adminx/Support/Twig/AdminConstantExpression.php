<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Twig/AdminConstantExpression.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support\Twig;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use Twig\Compiler;
	use Twig\Node\Expression\ConstantExpression;

	/**
	 * Строковая константа Twig с отложенным переводом интерфейса.
	 */
	class AdminConstantExpression extends ConstantExpression
	{
		public function compile(Compiler $compiler): void
		{
			$compiler
				->raw('\App\Adminx\Support\AdminLocale::translateMarkup(')
				->repr($this->getAttribute('value'))
				->raw(')');
		}
	}
