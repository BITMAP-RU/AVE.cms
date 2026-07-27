<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicDocumentResolver.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Debug;

	/** Resolves the current public document and applies legacy-compatible request metadata. */
	class PublicDocumentResolver
	{
		public function resolve($requestUri, $userGroupId)
		{
			Debug::startTime('URL_PARSE');

			$parser = new DocumentUrlParser();
			$parsed = $parser->parse($requestUri, ABS_PATH, URL_SUFF);
			$routeRepository = new DocumentRouteRepository();
			$alias = $routeRepository->normalizeAlias($parsed['alias']);
			$checkUrl = $parsed['check_url'];

			$this->applyRequestMetadata($parsed);

			if ($alias === '') {
				$alias = '/';
			}

			if (!empty($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
				$documentId = (int) $_REQUEST['id'];
				$alias = $routeRepository->aliasById($documentId);
			} else {
				$documentId = $routeRepository->idByAlias($alias);
			}

			$repository = new DocumentRepository();
			$document = $repository->findForPage($documentId, $userGroupId);

			if ($document) {
				$status = (new DocumentAccessPolicy())->status($document, PAGE_NOT_FOUND_ID);
				if ($status !== DocumentAccessPolicy::AVAILABLE && !$this->canPreviewUnavailable()) {
					$this->finishTiming();
					return $this->notFoundDocument($repository, $userGroupId);
				}

				PublicPageContext::markNotFound(false);
				$_GET['id'] = $_REQUEST['id'] = $document->Id;
				$_GET['doc'] = $_REQUEST['doc'] = $document->document_alias;

				if ($parsed['fake_url']) {
					$checkUrl = preg_replace('/\/(a|art)?page-\d+/i', '', $checkUrl);
					$_GET['doc'] = $_REQUEST['doc'] = $checkUrl;
					$document->document_alias = $checkUrl;
					$alias = $checkUrl;
				}

				$this->finishTiming();
				$this->redirectToCanonicalUrl($document, $alias, $checkUrl, $parsed['pagination']);
				return $document;
			}

			$redirect = $routeRepository->historicalRedirect($alias);
			$this->finishTiming();

			if ($redirect && !empty($redirect->document_alias)) {
				$location = str_replace('//', '/', ABS_PATH . $redirect->document_alias . URL_SUFF);
				$responder = new PublicRedirectResponder();
				$responder->historical($location, $redirect->document_alias_header);
			}

			if (empty($_REQUEST['sysblock']) && empty($_REQUEST['request'])) {
				return $this->notFoundDocument($repository, $userGroupId);
			}

			return false;
		}

		protected function notFoundDocument(DocumentRepository $repository, $userGroupId)
		{
			PublicPageContext::markNotFound(true);
			$_GET['id'] = $_REQUEST['id'] = PAGE_NOT_FOUND_ID;

			return $repository->findNotFoundPage(PAGE_NOT_FOUND_ID, $userGroupId);
		}

		protected function canPreviewUnavailable()
		{
			return isset($_SESSION['adminpanel']) || isset($_SESSION['alles']);
		}

		protected function applyRequestMetadata(array $parsed)
		{
			if ($parsed['print']) {
				$_GET['print'] = $_REQUEST['print'] = 1;
			}

			foreach ($parsed['pagination'] as $name => $number) {
				$_GET[$name] = $_REQUEST[$name] = $number;
			}

			if ($parsed['tag'] === null) {
				return;
			}

			$_GET['tag'] = $_REQUEST['tag'] = $parsed['tag'];
			parse_str(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '', $query);
			$query['tag'] = $parsed['tag'];
			$_SERVER['QUERY_STRING'] = http_build_query($query);
		}

		protected function redirectToCanonicalUrl($document, $alias, $checkUrl, array $pagination)
		{
			if ($checkUrl === $alias . URL_SUFF
				|| !empty($pagination)
				|| !$checkUrl
				|| !empty($_REQUEST['print'])
				|| !empty($_REQUEST['tag'])
				|| PublicPageContext::hasDeferredPage()
				|| !REWRITE_MODE) {
				return;
			}

			$responder = new PublicRedirectResponder();
			$responder->permanent((int) $document->Id === 1
				? ABS_PATH
				: ABS_PATH . $alias . URL_SUFF);
		}

		protected function finishTiming()
		{
			PublicProfiler::record(array('DOCUMENT', 'URL_PARSE'), Debug::endTime('URL_PARSE'));
		}
	}
