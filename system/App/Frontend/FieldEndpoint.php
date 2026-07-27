<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/FieldEndpoint.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicSysblockRegistry;
	use App\Content\Fields\FieldValueCodec;
	use App\Frontend\Media\ThumbnailUrl;
	use App\Helpers\Request;
	use App\Helpers\Response;

	/** Native replacement for the public field system-block endpoint. */
	class FieldEndpoint
	{
		public static function boot()
		{
			PublicSysblockRegistry::register('system-field', 'field', array(__CLASS__, 'handle'), 5);
		}

		public static function handle($id, array $params = array(), array $context = array())
		{
			$fieldId = Request::requestInt('fid', 0);
			$documentId = Request::requestInt('did', 0);
			if ($fieldId <= 0 || $documentId <= 0) {
				Response::html('', 400);
			}

			if ($fieldId === 22) {
				$source = self::firstFile(PublicFieldApi::raw($fieldId, $documentId));
				Response::json(array(
					'link' => $source,
					'thumb' => $source !== ''
						? ThumbnailUrl::make(array('link' => $source, 'size' => 't450x450'))
						: false,
				));
			}

			Response::html((string) PublicFieldApi::render($fieldId, $documentId));
		}

		protected static function firstFile($value)
		{
			$items = FieldValueCodec::decodeStructured($value, null);
			$first = is_array($items) ? reset($items) : $value;
			if (is_array($first)) {
				foreach (array('url', 'img', 'image', 'value', 'file') as $key) {
					if (isset($first[$key]) && is_scalar($first[$key])) {
						$first = $first[$key];
						break;
					}
				}
			}

			if (!is_scalar($first)) {
				return '';
			}

			$parts = explode('|', trim((string) $first));
			return trim((string) $parts[0]);
		}
	}
