<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicNotFoundResponder.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Terminates legacy external endpoints with their historical 404 contract. */
	class PublicNotFoundResponder
	{
		public function redirectToDocument($documentId, $fallbackHtml)
		{
			$repository = new DocumentRepository();
			if ($repository->exists($documentId)) {
				header('Location:' . ABS_PATH . 'index.php?id=' . (int) $documentId);
			} else {
				http_response_code(404);
				echo (string) $fallbackHtml;
			}

			exit;
		}

		public function emptyResponse()
		{
			http_response_code(404);
			exit;
		}
	}
