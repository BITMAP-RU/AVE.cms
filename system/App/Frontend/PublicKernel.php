<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicKernel.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\DebugChannel;
	use App\Common\IpBlocker;
	use App\Common\PublicModuleRuntime;
	use App\Common\ReferrerLog;
	use App\Common\RuntimeConstants;
	use App\Common\TrafficAttribution;
	use App\Common\WebMaintenance;
	use App\Common\Lifecycle;
	use App\Frontend\Debug\PublicDebugToolbar;
	use App\Frontend\Media\ThumbnailGateway;
	use App\Helpers\Debug;

	/** Coordinates one native public HTTP request. */
	class PublicKernel
	{
		public static function run()
		{
			\App\Helpers\Hooks::resetRuntime();
			DebugChannel::reset();
			PublicProfiler::reset();
			self::rejectThumbnailQuery();
			self::dispatchThumbnailPath();

			ob_start();
			require_once BASEPATH . '/system/vendor/MobileDetect/Mobile_Detect.php';
			$MDetect = new \Mobile_Detect();
			$initStart = microtime(true);

			require_once BASEPATH . '/system/App/Frontend/PublicBootstrapEntry.php';
			require_once BASEPATH . '/system/public.php';

			Auth::publicBootstrap();
			if (!SiteAccess::enforce()) {
				return;
			}

			PublicDebugToolbar::boot();
			Lifecycle::event('frontend.request.received', 'request', 'received', null, array(
				'method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET',
				'uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/',
			), null, array(), 'public_kernel');
			Lifecycle::event('frontend.runtime.ready', 'runtime', 'ready', null, array(
				'modules' => array_keys(PublicModuleRuntime::loaded()),
			), null, array(), 'public_kernel');
			$attribution = new TrafficAttribution();
			$attribution->capture();
			IpBlocker::enforce();
			ReferrerLog::capture();
			WebMaintenance::runIfDue();

			if (PublicModuleRuntime::dispatchCurrent()) {
				return;
			}

			self::normalizePageRequest();
			PublicProfiler::record(array('INIT', 'END'), number_format(
				Debug::elapsed($initStart),
				3,
				',',
				' '
			) . ' sec');

			Debug::startTime('CODEEND');
			$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
			$pageRenderer = PublicPageLifecycle::resolve($requestUri);
			PublicPageContext::resetPagination(isset($_REQUEST['id']) ? $_REQUEST['id'] : '');

			$revisionId = self::revisionPreviewId();
			if ($revisionId !== null) {
				$documentId = PublicPageContext::documentId();
				$token = isset($_GET['preview_token']) ? (string) $_GET['preview_token'] : '';
				if ($revisionId <= 0 || $documentId <= 0
					|| !DocumentRevisionPreview::validate($token, $documentId, $revisionId)) {
					self::rejectRevisionPreview(403);
					return;
				}

				$revisionRepository = new DocumentRevisionRepository();
				$revision = $revisionRepository->findById($documentId, $revisionId);
				if (!$revision) {
					self::rejectRevisionPreview(404);
					return;
				}

				$revisionFields = $revision['fields'];
				DocumentFieldRepository::useRevision($documentId, $revisionFields);
				$revisionDocument = isset($revision['document']) && is_array($revision['document']) ? $revision['document'] : array();
				$currentDocument = PublicPageContext::document();
				if ($currentDocument && !empty($revisionDocument)) {
					foreach ($revisionDocument as $key => $value) { $currentDocument->{$key} = $value; }
					PublicPageContext::setDocument($currentDocument);
				}

				$flds = (new DocumentFieldRepository())->all($documentId);
				self::disablePublicCache();
				header('X-AVE-Revision-Preview: ' . $revisionId);
				header('Referrer-Policy: no-referrer');
				header('X-Robots-Tag: noindex, nofollow');
			}

			$pageRenderer->render(PublicPageContext::documentId());
			PublicPageLifecycle::prepareResponseStatus();
			Debug::startTime('CONTENT');

			$content = ob_get_clean();
			self::startResponseBuffer();
			Debug::$_document_content = $content;
			Debug::startTime('EVALCONTENT');

			PublicPageLifecycle::execute($content, get_defined_vars());
			if ($revisionId !== null) {
				DocumentFieldRepository::clearRevision(PublicPageContext::documentId());
			}

			PublicProfiler::record(array('DOCUMENT', 'EVALCONTENT'), Debug::endTime('EVALCONTENT'));
			$render = ob_get_clean();

			PublicPageLifecycle::finish($render, $pageRenderer);
		}

		protected static function revisionPreviewId()
		{
			$value = null;
			if (isset($_GET['revision_preview'])) {
				$value = (string) $_GET['revision_preview'];
			} elseif (isset($_GET['revission'])) {
				$value = (string) $_GET['revission'];
			}

			if ($value === null) {
				return null;
			}

			return preg_match('/^[1-9][0-9]*$/', $value) ? (int) $value : 0;
		}

		protected static function rejectRevisionPreview($status)
		{
			self::disablePublicCache();
			if (ob_get_level() > 0) {
				ob_end_clean();
			}

			http_response_code((int) $status);
			header('Content-Type: text/plain; charset=UTF-8');
			header('Cache-Control: no-store, private');
			header('X-Robots-Tag: noindex, nofollow');
			echo (int) $status === 404 ? 'Ревизия не найдена.' : 'Предпросмотр ревизии недоступен.';
		}

		protected static function disablePublicCache()
		{
			if (!defined('PUBLIC_RESPONSE_NO_CACHE')) {
				define('PUBLIC_RESPONSE_NO_CACHE', true);
			}

			if (function_exists('apache_setenv')) {
				@apache_setenv('PUBLIC_NO_CACHE', '1');
			}

			$_SERVER['PUBLIC_NO_CACHE'] = '1';
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
		}

		protected static function rejectThumbnailQuery()
		{
			if (!isset($_GET['thumb']) && !isset($_POST['thumb']) && !isset($_REQUEST['thumb'])) {
				return;
			}

			http_response_code(404);
			header('Content-Type: text/plain; charset=UTF-8');
			echo 'No image';
			exit;
		}

		protected static function dispatchThumbnailPath()
		{
			RuntimeConstants::load();
			$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
			$requestPath = parse_url($requestUri, PHP_URL_PATH);
			$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index.php';
			$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
			$uploadPath = $basePath . '/' . trim(UPLOAD_DIR, '/') . '/';
			if (strpos('/' . ltrim((string) $requestPath, '/'), $uploadPath) !== 0) {
				return;
			}

			ThumbnailGateway::handle();
		}

		protected static function normalizePageRequest()
		{
			unset($_GET['module'], $_POST['module'], $_REQUEST['module']);
			unset($_GET['action'], $_POST['action'], $_REQUEST['action']);

			if (!PublicModuleRuntime::hasDeferredPage()) {
				return;
			}

			$_GET['id'] = $_REQUEST['id'] = 1;
			foreach (array('sysblock', 'request', 'alias_id') as $key) {
				unset($_GET[$key], $_POST[$key], $_REQUEST[$key]);
			}
		}

		protected static function startResponseBuffer()
		{
			$acceptEncoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string) $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
			if (strpos($acceptEncoding, 'gzip') !== false && defined('GZIP_COMPRESSION') && GZIP_COMPRESSION) {
				ob_start('ob_gzhandler');
				return;
			}

			ob_start();
		}
	}
