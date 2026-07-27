<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ExternalContentDispatcher.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicSysblockRegistry;

	/** Resolves legacy-compatible external sysblock and request endpoints. */
	class ExternalContentDispatcher
	{
		public function dispatch($sysblockId, $requestId, $ajax, $notFoundDocumentId, $fallbackHtml)
		{
			if ($this->hasIdentifier($sysblockId)) {
				return $this->dispatchSysblock($sysblockId, $ajax, $notFoundDocumentId, $fallbackHtml);
			}

			if ($this->hasIdentifier($requestId)) {
				return $this->dispatchRequest($requestId, $ajax, $notFoundDocumentId, $fallbackHtml);
			}

			return array('handled' => false, 'content' => '');
		}

		protected function dispatchSysblock($identifier, $ajax, $notFoundDocumentId, $fallbackHtml)
		{
			if (!$this->validIdentifier($identifier)) {
				$this->responder()->emptyResponse();
			}

			if ((string) $identifier === 'php') {
				$this->responder()->emptyResponse();
			}

			$metadata = PublicSysblockRegistry::metadata($identifier);
			if ($metadata && isset($metadata['sysblock_alias']) && $metadata['sysblock_alias'] === 'php') {
				$this->responder()->emptyResponse();
			}

			if (!$metadata || empty($metadata['sysblock_external'])) {
				$this->responder()->emptyResponse();
			}

			if (!empty($metadata['sysblock_ajax']) && !$ajax) {
				$this->responder()->redirectToDocument($notFoundDocumentId, $fallbackHtml);
			}

			return array('handled' => true, 'content' => (new BlockRenderer())->system($identifier));
		}

		protected function dispatchRequest($identifier, $ajax, $notFoundDocumentId, $fallbackHtml)
		{
			if (!$this->validIdentifier($identifier)) {
				$this->responder()->redirectToDocument($notFoundDocumentId, $fallbackHtml);
			}

			$metadata = (new RequestRepository())->find($identifier);
			if (!is_array($metadata) || empty($metadata['request_external'])) {
				$this->responder()->redirectToDocument($notFoundDocumentId, $fallbackHtml);
			}

			if (!empty($metadata['request_ajax']) && !$ajax) {
				$this->responder()->redirectToDocument($notFoundDocumentId, $fallbackHtml);
			}

			return array('handled' => true, 'content' => (new RequestRenderer())->render($identifier));
		}

		protected function hasIdentifier($identifier)
		{
			return $identifier !== null && $identifier !== '' && $identifier !== 0 && $identifier !== '0';
		}

		protected function validIdentifier($identifier)
		{
			return is_numeric($identifier) || preg_match('/^[A-Za-z0-9-_]{1,20}$/i', (string) $identifier) === 1;
		}

		protected function responder()
		{
			return new PublicNotFoundResponder();
		}
	}
