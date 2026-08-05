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
			$pattern = self::placeholderPattern();

			if (!Auth::systemUserCan('manage_documents')) {
				return preg_replace($pattern, '', $html);
			}

			return self::replacePlaceholders($html, $pattern, Auth::systemUserCan('manage_products'));
		}

		protected static function replacePlaceholders($html, $pattern = null, $canManageProducts = true)
		{
			$pattern = $pattern ?: self::placeholderPattern();
			return preg_replace_callback($pattern, function ($match) use ($canManageProducts) {
				$attributes = isset($match[1]) ? (string) $match[1] : '';
				if (!preg_match('/\bdata-document-id\s*=\s*(["\'])(\d+)\1/i', $attributes, $idMatch)) {
					return '';
				}

				$documentId = (int) $idMatch[2];
				$class = '';
				if (preg_match('/\bdata-edit-class\s*=\s*(["\'])(.*?)\1/i', $attributes, $classMatch)) {
					$class = preg_replace('/[^A-Za-z0-9 _-]/', '', html_entity_decode($classMatch[2], ENT_QUOTES, 'UTF-8'));
				}

				$target = 'document';
				if (preg_match('/\bdata-edit-target\s*=\s*(["\'])(document|product)\1/i', $attributes, $targetMatch)) {
					$target = strtolower((string) $targetMatch[2]);
				}

				if ($target === 'product' && !$canManageProducts) {
					return '';
				}

				$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
				$path = $target === 'product'
					? 'catalog/products/' . $documentId . '/edit'
					: 'documents/' . $documentId . '/edit';
				$label = $target === 'product' ? 'Редактировать товар' : 'Редактировать документ';
				$url = $base . AdminLocation::url($path) . '?quick_edit=1&pop=1';

				return '<a class="edit_trigger' . ($class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '') . '"'
					. ' href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="avecms-editdoc" rel="noopener"'
					. ' onclick="var popupWidth=Math.min(1300,Math.max(640,screen.availWidth-40));'
					. 'var popupHeight=Math.min(900,Math.max(600,screen.availHeight-40));'
					. 'var popupLeft=(window.screenX||window.screenLeft||0)+Math.max(0,(window.outerWidth-popupWidth)/2);'
					. 'var popupTop=(window.screenY||window.screenTop||0)+Math.max(0,(window.outerHeight-popupHeight)/2);'
					. 'var editWindow=window.open(this.href,\'avecms-editdoc\',\'width=\'+popupWidth+\',height=\'+popupHeight+\',left=\'+Math.round(popupLeft)+\',top=\'+Math.round(popupTop)+\',resizable=yes,scrollbars=yes\');'
					. 'if(editWindow){editWindow.focus();return false;}return true;"'
					. ' title="' . $label . ' #' . $documentId . '"><span>ID ' . $documentId . '</span>'
					. '<span class="edit_title">Редактировать</span></a>';
			}, $html);
		}

		protected static function placeholderPattern()
		{
			return '/<span\b(?=[^>]*\bdata-adminx-edit-placeholder\b)([^>]*)>\s*<\/span>/i';
		}
	}
