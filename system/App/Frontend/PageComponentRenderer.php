<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PageComponentRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Frontend\Media\ThumbnailUrl;

	/** Renders nested public components before shell/meta tag replacement. */
	class PageComponentRenderer
	{
		public function render($content, $documentId, $owner = null, $document = null)
		{
			$requestFields = new RequestFieldRenderer();
			$documentFields = new DocumentFieldRenderer();
			$requestItems = new RequestItemRenderer();
			$requests = new RequestRenderer();
			$blocks = new BlockRenderer();

			$content = preg_replace_callback(
				'/\[tag:fld:([a-zA-Z0-9-_]+)]\[img]/',
				function ($match) use ($documentId, $requestFields) {
					return $requestFields->render(
						$match[1],
						$documentId,
						'img',
						defined('RUB_ID') ? RUB_ID : 0
					);
				},
				(string) $content
			);
			$content = preg_replace_callback(
				'/\[tag:rfld:([a-zA-Z0-9-_]+)]\[(more|esc|img|[0-9-]+)]/',
				function ($match) use ($documentId, $requestFields) {
					return $requestFields->render(
						$match[1],
						$documentId,
						$match[2],
						defined('RUB_ID') ? RUB_ID : 0
					);
				},
				$content
			);
			$content = preg_replace_callback(
				'/\[tag:fld:([a-zA-Z0-9-_]+)(|[:(\d)])+?\]/',
				function ($match) use ($documentId, $documentFields) {
					return $documentFields->render($match, $documentId);
				},
				$content
			);
			$content = preg_replace('/\[tag:fld:\w*\]/', '', $content);
			$content = preg_replace('/\[tag:rfld:\w*\]/', '', $content);
			$content = preg_replace_callback(
				'/\[tag:([rcfts]\d+x\d+r*):(.*?)]/',
				array(ThumbnailUrl::class, 'fromTag'),
				$content
			);
			$content = $this->renderPrintConditions($content);

			$content = preg_replace_callback(
				'/\[tag:block:([A-Za-z0-9-_]{1,20}+)\]/',
				function ($match) use ($blocks) { return $blocks->visual($match[1]); },
				$content
			);
			$content = preg_replace_callback(
				'/\[tag:sysblock:([A-Za-z0-9-_]{1,20}+)(|:\{(.*?)\})\]/',
				function ($match) use ($blocks) { return $blocks->system($match[1], $match[2]); },
				$content
			);
			$content = preg_replace_callback(
				'/\[tag:teaser:(\d+)(|:\[(.*?)\])\]/',
				function ($match) use ($requestItems) { return $requestItems->render($match[1], '', $match[2]); },
				$content
			);

			$content = (new ModuleTagRenderer())->render($content, $owner, $document);
			$content = preg_replace_callback(
				'/\[tag:request:([A-Za-z0-9-_]{1,20}+)\]/',
				function ($match) use ($requests) { return $requests->render($match); },
				$content
			);

			$navigation = new NavigationRenderer();
			$content = preg_replace_callback(
				'/\[tag:navigation:([A-Za-z0-9-_]{1,20}+):?([0-9,]*)\]/',
				function ($match) use ($navigation) { return $navigation->render($match[1], $match[2]); },
				$content
			);

			return (new HiddenContentRenderer())->render($content);
		}

		protected function renderPrintConditions($content)
		{
			if (isset($_REQUEST['print']) && (int) $_REQUEST['print'] === 1) {
				$content = str_replace(array('[tag:if_print]', '[/tag:if_print]'), '', $content);
				return preg_replace('/\[tag:if_notprint\](.*?)\[\/tag:if_notprint\]/si', '', $content);
			}

			$content = preg_replace('/\[tag:if_print\](.*?)\[\/tag:if_print\]/si', '', $content);
			return str_replace(array('[tag:if_notprint]', '[/tag:if_notprint]'), '', $content);
		}
	}
