<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/QuickEdit/PublicQuickEdit.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\QuickEdit;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\AdminLocation;

	/** Resolves cache-safe edit placeholders immediately before the public response. */
	class PublicQuickEdit
	{
		public static function inject($html)
		{
			$html = (string) $html;
			$pattern = '/<span\s+data-adminx-edit-placeholder\s+data-document-id="(\d+)"\s+data-edit-class="([^"]*)"\s*><\/span>/i';

			if (!Auth::systemUserCan('manage_documents')) {
				return preg_replace($pattern, '', $html);
			}

			return preg_replace_callback($pattern, function ($match) {
				$documentId = (int) $match[1];
				$class = preg_replace('/[^A-Za-z0-9 _-]/', '', html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'));
				$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
				$url = $base . AdminLocation::url('documents/' . $documentId . '/edit') . '?quick_edit=1&pop=1';

				return '<a class="edit_trigger ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
					. ' href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"'
					. ' onclick="windowOpen(this.href, 1300, 900, true, \'editdoc\'); return false;"'
					. ' title="Редактировать"><i class="fa fa-pencil"></i> <span>ID: ' . $documentId . '</span>'
					. '<span class="edit_title">&nbsp;Редактировать</span></a>';
			}, $html);
		}
	}
