<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Introspection/IntrospectionService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Introspection;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Requests\RequestMatchExplainer;

	/** Stable read-only facade for the panel and future protocol adapters. */
	class IntrospectionService
	{
		public function page($target)
		{
			$context = (new PageContextInspector())->inspect($target);
			$context['diagnostics'] = (new ContentDiagnosis())->page($context);
			return IntrospectionSanitizer::value($context);
		}

		public function request($requestId, $documentId, array $parameters = array())
		{
			return IntrospectionSanitizer::value(
				(new RequestMatchExplainer())->explain($requestId, $documentId, $parameters)
			);
		}

		public function diagnosePage($target)
		{
			$context = (new PageContextInspector())->inspect($target);
			return (new ContentDiagnosis())->page($context);
		}

		public function diagnoseDocument($documentId)
		{
			return $this->diagnosePage((int) $documentId);
		}

		public function diagnoseRequest($requestId, $documentId, array $parameters = array())
		{
			return $this->request($requestId, $documentId, $parameters);
		}

		public function diagnoseTemplate($target)
		{
			$context = (new PageContextInspector())->inspect($target);
			return array(
				'templates' => $context['templates'],
				'findings' => array_values(array_filter((new ContentDiagnosis())->page($context), function ($finding) {
					return strpos((string) $finding['code'], 'template') !== false;
				})),
			);
		}

		public function diagnoseCache($target)
		{
			$context = (new PageContextInspector())->inspect($target);
			return array('document' => $context['document'], 'cache' => $context['cache']);
		}
	}
