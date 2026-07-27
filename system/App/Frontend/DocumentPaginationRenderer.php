<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentPaginationRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Url;

	/** Splits rich-text fields into public pages and renders their navigation. */
	class DocumentPaginationRenderer
	{
		public function render($text, $document = null)
		{
			$document = is_object($document) ? $document : PublicPageContext::document();
			$documentId = $document && isset($document->Id)
				? (int) $document->Id
				: (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 1);
			$alias = $document && isset($document->document_alias) ? (string) $document->document_alias : '';
			$title = $document && isset($document->document_title) ? (string) $document->document_title : '';

			$pages = preg_split(
				'#<div style="page-break-after:[; ]*always[; ]*"><span style="display:[ ]*none[;]*">&nbsp;</span></div>#i',
				(string) $text
			);
			$totalPages = count($pages);

			if ($totalPages > 1) {
				$page = PaginationRenderer::currentPage('artpage');
				$text = isset($pages[$page - 1]) ? $pages[$page - 1] : '';
				$template = ' <a class="pnav" href="index.php?id=' . $documentId
					. '&amp;doc=' . ($alias === '' ? Url::prepare($title) : $alias)
					. '&amp;artpage={s}'
					. ((isset($_REQUEST['apage']) && is_numeric($_REQUEST['apage'])) ? '&amp;apage=' . $_REQUEST['apage'] : '')
					. ((isset($_REQUEST['page']) && is_numeric($_REQUEST['page'])) ? '&amp;page=' . $_REQUEST['page'] : '')
					. '">{t}</a> ';
				$pagination = (new PaginationRenderer())->render(
					$totalPages,
					'artpage',
					$template,
					PublicSettings::get('navi_box')
				);
				$text .= Url::rewrite($pagination);
			}

			$record = '<?php \\App\\Frontend\\PublicPageContext::recordPagination('
				. $documentId . ', \'artpage\', ' . $totalPages . '); ?>';
			return $record . $text;
		}
	}
