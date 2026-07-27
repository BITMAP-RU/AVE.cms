<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/BreadcrumbRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Url;

	/** Renders public breadcrumbs from the current document ancestry. */
	class BreadcrumbRenderer
	{
		protected static $cache = array();

		public function render()
		{
			$currentDocument = PublicPageContext::document();
			$documentId = PublicPageContext::documentId();
			if ($documentId <= 0) {
				return '';
			}

			if (array_key_exists($documentId, self::$cache)) {
				return self::$cache[$documentId];
			}

			$settings = PublicSettings::all();
			$box = trim(isset($settings['bread_box']) ? $settings['bread_box'] : '');
			$showMain = !empty($settings['bread_show_main']);
			$showHost = !empty($settings['bread_show_host']);
			$separator = trim(isset($settings['bread_separator']) ? $settings['bread_separator'] : '');
			$useSeparator = !empty($settings['bread_separator_use']);
			$linkBox = trim(isset($settings['bread_link_box']) ? $settings['bread_link_box'] : '');
			$linkTemplate = trim(isset($settings['bread_link_template']) ? $settings['bread_link_template'] : '');
			$selfBox = trim(isset($settings['bread_self_box']) ? $settings['bread_self_box'] : '');
			$showLast = !empty($settings['bread_link_box_last']);
			$repository = new DocumentRepository();
			$document = $repository->find($documentId);
			if (!$document) {
				return self::$cache[$documentId] = '';
			}

			$html = '';
			$number = 0;
			if ($showMain) {
				$home = $repository->find(1);
				if ($home) {
					$number = 1;
					$homeTitle = isset($home->document_breadcrumb_title) ? $home->document_breadcrumb_title : $home->document_title;
					$homeLink = $showHost
						? HOST . '/' . ltrim((string) $home->document_alias, '/')
						: (string) $home->document_alias;
					$link = str_replace(
						array('[name]', '[link]', '[count]'),
						array($homeTitle, $homeLink, 1),
						$linkTemplate
					);
					$html = sprintf($linkBox, $link);
					if ($useSeparator) {
						$html .= $separator;
					}
				}
			}

			$title = empty($document->document_breadcrumb_title)
				? stripslashes(htmlspecialchars_decode($document->document_title))
				: stripslashes(htmlspecialchars_decode($document->document_breadcrumb_title));
			$parentId = $currentDocument && isset($currentDocument->document_parent) && (int) $currentDocument->document_parent !== 0
				? (int) $currentDocument->document_parent
				: (int) $document->document_parent;
			$parents = array();
			$visited = array($documentId => true);

			while ($parentId !== 0 && !isset($visited[$parentId])) {
				$visited[$parentId] = true;
				$parent = $repository->find($parentId);
				if (!$parent) {
					break;
				}

				if ((int) $parent->document_status === 1) {
					$parents[] = $parent;
				}

				$nextParent = (int) $parent->document_parent;
				if ($nextParent === (int) $parent->Id) {
					error_log('Breadcrumb parent cycle at document ' . (int) $parent->Id);
					break;
				}

				$parentId = $nextParent === 0 && (int) $parent->Id !== 1 ? 1 : $nextParent;
			}

			$parents = array_reverse($parents);
			$visibleParents = array_values(array_filter($parents, function ($parent) {
				return (int) $parent->Id !== 1;
			}));
			foreach ($visibleParents as $index => $parent) {
				$number = $index + ($showMain ? 2 : 1);
				$url = Url::rewrite(
					'index.php?id=' . (int) $parent->Id . '&amp;doc=' . (string) $parent->document_alias
				);
				$parentTitle = empty($parent->document_breadcrumb_title)
					? stripslashes(htmlspecialchars_decode($parent->document_title))
					: stripslashes(htmlspecialchars_decode($parent->document_breadcrumb_title));
				$link = str_replace(
					array('[name]', '[link]', '[count]'),
					array($parentTitle, $showHost ? HOST . '/' . ltrim($url, '/') : $url, $number),
					$linkTemplate
				);
				$html .= sprintf($linkBox, $link);
				if ($useSeparator && ($showLast || $index !== count($visibleParents) - 1)) {
					$html .= $separator;
				}
			}

			$hideCurrent = $currentDocument && isset($currentDocument->bread_link_box_last)
				&& (string) $currentDocument->bread_link_box_last === '0';
			if (!$hideCurrent && ($showLast || ($currentDocument && !empty($currentDocument->bread_link_box_last)))) {
				$html .= str_replace('[count]', $number + 1, sprintf($selfBox, $title));
			}

			$result = ($documentId === 1 || $documentId === (int) PAGE_NOT_FOUND_ID)
				? ''
				: sprintf($box, $html);
			self::$cache[$documentId] = $result;
			return $result;
		}

		public static function reset()
		{
			self::$cache = array();
		}
	}
