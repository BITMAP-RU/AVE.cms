<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentAliasTemplate.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Шаблон префикса URL рубрики и его объединение с алиасом документа. */
	class DocumentAliasTemplate
	{
		protected static $tokens = array(
			'%d' => 'День, 01-31',
			'%m' => 'Месяц, 01-12',
			'%Y' => 'Год, 4 цифры',
			'%y' => 'Год, 2 цифры',
			'%H' => 'Час, 00-23',
			'%M' => 'Минута, 00-59',
			'%S' => 'Секунда, 00-59',
		);

		public static function tokens()
		{
			return self::$tokens;
		}

		public static function normalize($pattern)
		{
			$pattern = str_replace('\\', '/', trim((string) $pattern));
			$pattern = preg_replace('#/+#', '/', $pattern);
			return trim((string) $pattern, '/');
		}

		public static function validationError($pattern)
		{
			$pattern = self::normalize($pattern);
			if ($pattern === '') {
				return '';
			}

			if (strlen($pattern) > 255) {
				return 'Шаблон URL не должен быть длиннее 255 символов';
			}

			if (preg_match('/^[A-Za-z0-9_\-\.\/%]+$/', $pattern) !== 1) {
				return 'Допустимы латиница, цифры, точка, дефис, подчёркивание, слеш и маркеры даты';
			}

			if (preg_match_all('/%[A-Za-z]/', $pattern, $matches)) {
				foreach (array_unique($matches[0]) as $token) {
					if (!isset(self::$tokens[$token])) {
						return 'Неизвестный маркер даты: ' . $token;
					}
				}
			}

			if (strpos($pattern, '%') !== false && preg_match('/%(?![dmYyHMS])/', $pattern)) {
				return 'После % должен идти поддерживаемый маркер даты';
			}

			return '';
		}

		public static function expand($pattern, $timestamp = 0)
		{
			$pattern = self::normalize($pattern);
			if ($pattern === '') {
				return '';
			}

			$timestamp = (int) $timestamp > 0 ? (int) $timestamp : time();
			return strtr($pattern, array(
				'%d' => date('d', $timestamp),
				'%m' => date('m', $timestamp),
				'%Y' => date('Y', $timestamp),
				'%y' => date('y', $timestamp),
				'%H' => date('H', $timestamp),
				'%M' => date('i', $timestamp),
				'%S' => date('s', $timestamp),
			));
		}

		public static function compose($pattern, $documentAlias, $timestamp = 0, $parentPath = '')
		{
			$alias = trim(str_replace('\\', '/', (string) $documentAlias), '/');
			$prefix = trim((string) $parentPath, '/');
			if ($prefix === '') {
				$prefix = self::expand($pattern, $timestamp);
			}

			if ($prefix === '' || $alias === $prefix || strpos($alias, $prefix . '/') === 0) {
				return $alias !== '' ? $alias : $prefix;
			}

			// Полный путь, заданный редактором вручную, не переписываем.
			if (strpos($alias, '/') !== false) {
				return $alias;
			}

			return $alias === '' ? $prefix : $prefix . '/' . $alias;
		}
	}
