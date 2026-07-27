<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Twig/AdminLocaleNodeVisitor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support\Twig;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use Twig\Environment;
	use Twig\Node\Node;
	use Twig\Node\Expression\ConstantExpression;
	use Twig\Node\TextNode;
	use Twig\NodeVisitor\NodeVisitorInterface;

	class AdminLocaleNodeVisitor implements NodeVisitorInterface
	{
		public function enterNode(Node $node, Environment $environment): Node
		{
			return $node;
		}


		public function leaveNode(Node $node, Environment $environment): ?Node
		{
			if ($node instanceof TextNode && !$node instanceof AdminTextNode) {
				return new AdminTextNode(
					(string) $node->getAttribute('data'),
					(int) $node->getTemplateLine()
				);
			}

			if ($node instanceof ConstantExpression && !$node instanceof AdminConstantExpression) {
				$value = $node->getAttribute('value');
				if (is_string($value) && preg_match('/[А-Яа-яЁё]/u', $value)) {
					return new AdminConstantExpression($value, (int) $node->getTemplateLine());
				}
			}

			return $node;
		}


		public function getPriority()
		{
			return 0;
		}
	}
