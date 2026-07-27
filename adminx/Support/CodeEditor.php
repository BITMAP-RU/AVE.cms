<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/CodeEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;

	/**
	 * Shared подключение CodeMirror 5 для source/code-полей новой админки.
	 *
	 * Библиотека принадлежит Adminx и хранится рядом с его общими assets.
	 */
	class CodeEditor
	{
		protected static $codeMirrorLoaded = false;
		protected static $tiptapLoaded = false;

		public static function useCodeMirror($mode = 'htmlmixed')
		{
			$mode = (string) $mode;
			$base = ADMINX_BASE . '/assets/vendor/codemirror';

			if (!self::$codeMirrorLoaded) {
				AdminAssets::addStyle($base . '/lib/codemirror.css', 0);
				AdminAssets::addStyle($base . '/addon/dialog/dialog.css', 0);

				if (defined('CODEMIRROR_THEME') && CODEMIRROR_THEME !== '' && CODEMIRROR_THEME !== 'default') {
					AdminAssets::addStyle($base . '/theme/' . rawurlencode(CODEMIRROR_THEME) . '.css', 0);
				}

				AdminAssets::addScript($base . '/lib/codemirror.js', 20);
				AdminAssets::addScript($base . '/mode/xml/xml.js', 21);
				AdminAssets::addScript($base . '/mode/javascript/javascript.js', 21);
				AdminAssets::addScript($base . '/mode/css/css.js', 21);
				AdminAssets::addScript($base . '/mode/clike/clike.js', 21);
				AdminAssets::addScript($base . '/mode/php/php.js', 21);
				AdminAssets::addScript($base . '/mode/smarty/smarty.js', 21);
				AdminAssets::addScript($base . '/mode/smartymixed/smartymixed.js', 21);
				AdminAssets::addScript($base . '/mode/htmlmixed/htmlmixed.js', 21);
				AdminAssets::addScript($base . '/mode/sql/sql.js', 21);
				AdminAssets::addScript($base . '/addon/edit/closetag.js', 22);
				AdminAssets::addScript($base . '/addon/edit/matchbrackets.js', 22);
				AdminAssets::addScript($base . '/addon/selection/active-line.js', 22);
				AdminAssets::addScript($base . '/addon/dialog/dialog.js', 22);
				AdminAssets::addScript($base . '/addon/search/searchcursor.js', 22);
				AdminAssets::addScript($base . '/addon/search/search.js', 22);

				AdminAssets::addScript(ADMINX_BASE . '/assets/js/editor-codemirror.js', 30);
				self::$codeMirrorLoaded = true;
			}

			if ($mode !== '') {
				self::useMode($mode);
			}
		}

		public static function useTiptap()
		{
			if (self::$tiptapLoaded) {
				return;
			}

			AdminAssets::addScript(ADMINX_BASE . '/assets/js/editor-tiptap.js', 31);
			self::$tiptapLoaded = true;
		}

		public static function useRichEditor()
		{
			self::useCodeMirror('htmlmixed');
			self::useTiptap();
		}

		protected static function useMode($mode)
		{
			$base = ADMINX_BASE . '/assets/vendor/codemirror';
			if ($mode === 'text/css') {
				AdminAssets::addScript($base . '/mode/css/css.js', 21);
			} elseif ($mode === 'text/javascript' || $mode === 'application/json') {
				AdminAssets::addScript($base . '/mode/javascript/javascript.js', 21);
			} elseif ($mode === 'application/x-httpd-php' || $mode === 'php') {
				AdminAssets::addScript($base . '/mode/php/php.js', 21);
			} elseif ($mode === 'text/x-sql' || $mode === 'sql') {
				AdminAssets::addScript($base . '/mode/sql/sql.js', 21);
			}
		}

	}
