<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicPageContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Request-scoped state shared by the native public page pipeline. */
	class PublicPageContext
	{
		protected static $deferredPage;
		protected static $pagination = array();
		protected static $document;
		protected static $notFound = false;

		public static function setDocument($document)
		{
			self::$document = is_object($document) ? $document : null;
		}

		public static function document()
		{
			return self::$document;
		}

		public static function documentId()
		{
			return self::$document && isset(self::$document->Id) ? (int) self::$document->Id : 0;
		}

		public static function markNotFound($notFound)
		{
			self::$notFound = (bool) $notFound;
		}

		public static function isNotFound()
		{
			return self::$notFound;
		}

		public static function deferPage($html, array $meta = array())
		{
			self::$deferredPage = array(
				'html' => (string) $html,
				'title' => isset($meta['title']) ? (string) $meta['title'] : '',
				'description' => isset($meta['description']) ? (string) $meta['description'] : '',
				'robots' => isset($meta['robots']) ? (string) $meta['robots'] : 'noindex,follow',
				'template_id' => isset($meta['template_id']) ? max(0, (int) $meta['template_id']) : 0,
			);
		}

		public static function deferredPage()
		{
			return self::$deferredPage;
		}

		public static function hasDeferredPage()
		{
			return is_array(self::$deferredPage) && array_key_exists('html', self::$deferredPage);
		}

		public static function resetPagination($documentId)
		{
			self::$pagination[(string) $documentId] = array('page' => 0.0);
		}

		public static function recordPagination($documentId, $name, $maximum)
		{
			$documentId = (string) $documentId;
			$name = (string) $name;
			if (!in_array($name, array('page', 'apage', 'artpage'), true)) { return; }
			$maximum = max(0.0, (float) $maximum);
			$current = isset(self::$pagination[$documentId][$name]) ? (float) self::$pagination[$documentId][$name] : 0.0;
			self::$pagination[$documentId][$name] = max($current, $maximum);
		}

		public static function paginationMaximum($documentId, $name)
		{
			$documentId = (string) $documentId;
			$name = (string) $name;
			return isset(self::$pagination[$documentId][$name]) ? (float) self::$pagination[$documentId][$name] : 0.0;
		}

		public static function reset()
		{
			self::$deferredPage = null;
			self::$pagination = array();
			self::$document = null;
			self::$notFound = false;
		}
	}
