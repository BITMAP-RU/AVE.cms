<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/PublicFieldTemplateResolver.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Resolves native doc/req field templates, including field-specific variants. */
	class PublicFieldTemplateResolver
	{
		protected static $resolved = array();

		public static function render($type, $mode, $value, array $field, $templateId = null)
		{
			$context = new PublicFieldTemplateContext($type, $mode, $value, $field, $templateId);
			$file = self::resolve($context);
			if ($file === null) {
				return null;
			}

			return self::includeTemplate($file, $context);
		}

		public static function resolve(PublicFieldTemplateContext $context)
		{
			$type = $context->type();
			$mode = $context->mode();
			if (!preg_match('/^[a-z0-9_]+$/', $type) || !in_array($mode, array('doc', 'req'), true)) {
				return null;
			}

			$key = implode('|', array($type, $mode, $context->fieldId(), $context->alias(), (string) $context->templateId()));
			if (array_key_exists($key, self::$resolved)) {
				return self::$resolved[$key];
			}

			$base = __DIR__ . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
			$prefix = 'field-' . $mode;
			$identifiers = array();
			if ($context->fieldId() > 0) {
				$identifiers[] = (string) $context->fieldId();
			}

			if ($context->alias() !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $context->alias())) {
				$identifiers[] = $context->alias();
			}

			$identifiers = array_values(array_unique($identifiers));

			$candidates = array();
			if ($context->templateId() !== null && preg_match('/^[A-Za-z0-9_-]+$/', (string) $context->templateId())) {
				foreach ($identifiers as $identifier) {
					$candidates[] = $base . $prefix . '-' . $identifier . '-' . $context->templateId() . '.php';
				}
			}

			foreach ($identifiers as $identifier) {
				$candidates[] = $base . $prefix . '-' . $identifier . '.php';
			}

			$candidates[] = $base . $prefix . '.php';

			foreach ($candidates as $candidate) {
				if (is_file($candidate)) {
					return self::$resolved[$key] = $candidate;
				}
			}

			return self::$resolved[$key] = null;
		}

		public static function reset()
		{
			self::$resolved = array();
		}

		protected static function includeTemplate($file, PublicFieldTemplateContext $template)
		{
			$value = $template->value();
			$items = $template->items();
			$field = $template->field();
			ob_start();
			try {
				include $file;
				return (string) ob_get_clean();
			} catch (\Throwable $e) {
				ob_end_clean();
				throw $e;
			}
		}
	}
