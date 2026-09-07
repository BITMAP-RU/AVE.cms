<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicPageLifecycle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicModuleRuntime;
	use App\Common\Lifecycle;
	use App\Common\Registry;
	use App\Common\StoredPhpRuntime;
	use App\Helpers\Debug;

	/** Resolves document state and finalizes output around the native page renderer. */
	class PublicPageLifecycle
	{
		protected static $notFoundPrepared = false;

		public static function resolve($requestUri)
		{
			$core = new PublicPageRenderer();

			$resolver = new PublicDocumentResolver();
			$core->curentdoc = $resolver->resolve(
				PublicModuleRuntime::hasDeferredPage() ? '/' : (string) $requestUri,
				UGROUP
			);
			self::applyDeferredMetadata($core);
			PublicPageContext::setDocument($core->curentdoc);

			$route = Lifecycle::event('frontend.route.resolved', 'route', 'resolved', PublicPageContext::documentId(), array(
				'request_uri' => (string) $requestUri,
				'document' => $core->curentdoc,
				'deferred_page' => PublicPageContext::hasDeferredPage(),
			), $core->curentdoc, array(), 'public_page_lifecycle');
			if (is_object($route->result())) {
				$core->curentdoc = $route->result();
				PublicPageContext::setDocument($core->curentdoc);
			}

			return $core;
		}

		protected static function applyDeferredMetadata($core)
		{
			if (!PublicPageContext::hasDeferredPage() || empty($core->curentdoc)) { return; }

			$page = PublicPageContext::deferredPage();
			if (!empty($page['title'])) { $core->curentdoc->document_title = (string) $page['title']; }
			$core->curentdoc->document_meta_description = isset($page['description']) ? (string) $page['description'] : '';
			$core->curentdoc->document_meta_robots = isset($page['robots']) ? (string) $page['robots'] : 'noindex,follow';
			$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
			$core->curentdoc->document_alias = trim((string) $path, '/');
		}

		public static function execute($content, array $context)
		{
			$content = (string) $content;
			$rendering = Lifecycle::event('content.template.rendering', 'template', 'executing', PublicPageContext::documentId(), array(
				'context' => array('document_id' => PublicPageContext::documentId()),
			), $content, array('stored_php' => true), 'public_page_lifecycle');
			if ($rendering->cancelled()) { echo (string) $rendering->result(); return true; }
			$content = (string) $rendering->result();
			// Historical template #1 denied every guest and only appeared to work while full-page cache was warm.
			$content = preg_replace("#<\?php\s+if\s*\(UGROUP\s*!=\s*1\)\s*die\(['\"]Пусто['\"]\);\s*\?>#u", '', $content, 1);
			try {
				StoredPhpRuntime::execute($content, $context);
				Lifecycle::event('content.template.rendered', 'template', 'executed', PublicPageContext::documentId(), array(
					'document_id' => PublicPageContext::documentId(),
				), true, array('stored_php' => true), 'public_page_lifecycle');
				return true;
			} catch (\Throwable $e) {
				error_log(sprintf(
					'Public template render failed [%s] %s in %s:%d',
					get_class($e),
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				));
				http_response_code(500);
				echo 'Ошибка формирования страницы.';
				return false;
			}
		}

		public static function prepareResponseStatus()
		{
			if (!PublicPageContext::isNotFound()) { return; }
			if (!self::$notFoundPrepared) { \App\Common\CsvLogWriter::notFound(); \App\Common\NotFoundLog::record(); self::$notFoundPrepared = true; }
			http_response_code(404);
		}

		public static function finish($render, $core)
		{
			if (PublicPageContext::isNotFound()) {
				if (!self::$notFoundPrepared) { \App\Common\CsvLogWriter::notFound(); \App\Common\NotFoundLog::record(); self::$notFoundPrepared = true; }
				http_response_code(404);
			}

			if (self::paginationIsOutOfRange()) {
				$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 1;
				$location = $id === 1
					? ABS_PATH
					: ABS_PATH . (isset($core->curentdoc->document_alias) ? $core->curentdoc->document_alias : '') . URL_SUFF;
				header('Location:' . $location);
				exit;
			}

			PublicProfiler::record(array('DOCUMENT', 'CONTENT'), Debug::endTime('CONTENT'));
			PublicProfiler::record(array('INIT', 'CODEEND'), Debug::endTime('CODEEND'));
			$response = Lifecycle::event('frontend.response.rendering', 'response', 'rendering', PublicPageContext::documentId(), array(
				'status' => (int) (http_response_code() ?: 200),
			), (string) $render, array(), 'public_page_lifecycle');
			$render = (string) $response->result();
			$render = \App\Frontend\QuickEdit\PublicQuickEdit::inject($render);
			$rendered = Lifecycle::event('frontend.response.rendered', 'response', 'rendered', PublicPageContext::documentId(), array(
				'status' => (int) (http_response_code() ?: 200),
			), (string) $render, array(), 'public_page_lifecycle');
			$render = (string) $rendered->result();
			$render = SiteAccess::injectPreviewNotice($render);
			$render = \App\Frontend\Debug\PublicDebugToolbar::inject($render, $core);
			Registry::clean();
			PublicHtmlResponse::emit((string) $render);

		}

		protected static function paginationIsOutOfRange()
		{
			// Deferred module pages own their query parameters and pagination.
			if (PublicPageContext::hasDeferredPage()) { return false; }
			if (!empty($_REQUEST['module'])) { return false; }
			$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
			foreach (array('page', 'apage', 'artpage') as $name) {
				if (!isset($_REQUEST[$name]) || !is_numeric($_REQUEST[$name])) { continue; }
				$maximum = PublicPageContext::paginationMaximum($id, $name);
				if ((float) $_REQUEST[$name] < 2 || (float) $_REQUEST[$name] > (float) $maximum) { return true; }
			}

			return false;
		}
	}
