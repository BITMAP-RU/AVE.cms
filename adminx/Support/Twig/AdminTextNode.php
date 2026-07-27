<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Twig/AdminTextNode.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support\Twig;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use Twig\Compiler;
	use Twig\Node\TextNode;

	/**
	 * Статичный Twig-фрагмент Adminx, переводимый во время рендера.
	 *
	 * Перевод не компилируется в кеш Twig, поэтому разные администраторы могут
	 * использовать разные языки с одним набором скомпилированных шаблонов.
	 */
	class AdminTextNode extends TextNode
	{
		public function compile(Compiler $compiler): void
		{
			$compiler
				->addDebugInfo($this)
				->write('echo \\App\\Adminx\\Support\\AdminLocale::translateMarkup(')
				->string($this->getAttribute('data'))
				->raw(");\n");
		}
	}
