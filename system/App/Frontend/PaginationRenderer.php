<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PaginationRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Public page-number parsing and legacy-compatible document pagination. */
	class PaginationRenderer
	{
		protected static $types = array('page', 'apage', 'artpage');

		public static function currentPage($type = 'page')
		{
			if (!in_array($type, self::$types, true)) {
				return 1;
			}

			return isset($_REQUEST[$type]) && is_numeric($_REQUEST[$type])
				? max(1, (int) $_REQUEST[$type])
				: 1;
		}

		public function render($totalPages, $type, $templateLabel, $navigationBox = '')
		{
			$navigation = '';
			$totalPages = max(0, (int) $totalPages);
			if (!in_array($type, self::$types, true)) {
				$type = 'page';
			}

			$currentPage = self::currentPage($type);
			if ($currentPage === 1) {
				$pages = array($currentPage, $currentPage + 1, $currentPage + 2, $currentPage + 3, $currentPage + 4);
			} elseif ($currentPage === 2) {
				$pages = array($currentPage - 1, $currentPage, $currentPage + 1, $currentPage + 2, $currentPage + 3);
			} elseif ($currentPage + 1 === $totalPages) {
				$pages = array($currentPage - 3, $currentPage - 2, $currentPage - 1, $currentPage, $currentPage + 1);
			} elseif ($currentPage === $totalPages) {
				$pages = array($currentPage - 4, $currentPage - 3, $currentPage - 2, $currentPage - 1, $currentPage);
			} else {
				$pages = array($currentPage - 2, $currentPage - 1, $currentPage, $currentPage + 1, $currentPage + 2);
			}

			$pages = array_unique($pages);

			$linkBox = trim((string) PublicSettings::get('link_box'));
			$separatorBox = trim((string) PublicSettings::get('separator_box'));
			$totalBox = trim((string) PublicSettings::get('total_box'));
			$activeBox = trim((string) PublicSettings::get('active_box'));
			$totalLabel = trim((string) PublicSettings::get('total_label'));
			$startLabel = trim((string) PublicSettings::get('start_label'));
			$endLabel = trim((string) PublicSettings::get('end_label'));
			$separatorLabel = trim((string) PublicSettings::get('separator_label'));
			$nextLabel = trim((string) PublicSettings::get('next_label'));
			$previousLabel = trim((string) PublicSettings::get('prev_label'));

			if ($totalPages > 5 && $currentPage > 3) {
				$first = str_replace('data-pagination="{s}"', 'data-pagination="1"', $templateLabel);
				$navigation .= sprintf(
					$linkBox,
					str_replace(
						array('{s}', '{t}'),
						$startLabel,
						str_replace($this->pageTokens($type), '', $first)
					)
				);
				if ($separatorLabel !== '') {
					$navigation .= sprintf($separatorBox, $separatorLabel);
				}
			}

			if ($currentPage > 1) {
				if ($currentPage - 1 === 1) {
					$link = str_replace($this->pageTokens($type), '', $templateLabel);
					$navigation .= sprintf($linkBox, str_replace(array('{s}', '{t}'), $previousLabel, $link));
				} else {
					$navigation .= sprintf(
						$linkBox,
						str_replace('{t}', $previousLabel, str_replace('{s}', $currentPage - 1, $templateLabel))
					);
				}
			}

			foreach ($pages as $page) {
				if ($page < 1 || $page > $totalPages) {
					continue;
				}

				if ($currentPage === $page) {
					$navigation .= sprintf(
						$linkBox,
						sprintf($activeBox, str_replace(array('{s}', '{t}'), $page, $currentPage))
					);
				} elseif ($page === 1) {
					$link = str_replace($this->pageTokens($type), '', $templateLabel);
					$navigation .= sprintf($linkBox, str_replace(array('{s}', '{t}'), $page, $link));
				} else {
					$navigation .= sprintf(
						$linkBox,
						str_replace(array('{s}', '{t}'), $page, $templateLabel)
					);
				}
			}

			if ($currentPage < $totalPages) {
				$navigation .= sprintf(
					$linkBox,
					str_replace('{t}', $nextLabel, str_replace('{s}', $currentPage + 1, $templateLabel))
				);
			}

			if ($totalPages > 5 && $currentPage < $totalPages - 2) {
				if ($separatorLabel !== '') {
					$navigation .= sprintf($separatorBox, $separatorLabel);
				}

				$navigation .= sprintf(
					$linkBox,
					str_replace('{t}', $endLabel, str_replace('{s}', $totalPages, $templateLabel))
				);
			}

			if ($navigation !== '') {
				if ($totalLabel !== '') {
					$navigation = sprintf($totalBox, sprintf($totalLabel, $currentPage, $totalPages)) . $navigation;
				}

				if ($navigationBox !== '') {
					$navigation = sprintf($navigationBox, $navigation);
				}
			}

			return $navigation;
		}

		protected function pageTokens($type)
		{
			return array('&amp;' . $type . '={s}', '&' . $type . '={s}', '/' . $type . '-{s}');
		}
	}
