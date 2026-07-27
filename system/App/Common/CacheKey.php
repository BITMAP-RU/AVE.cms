<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CacheKey.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/**
	 * Канонические cache keys/tags для доменных кешей.
	 *
	 * Cache хранит файлы по md5, поэтому человекочитаемый ключ нужен до записи:
	 * для отладки, инвалидации, dependency tracking и будущего Redis/CDN backend.
	 */
	class CacheKey
	{
		const SCOPE_PUBLIC = 'public';
		const SCOPE_ROLE = 'role';
		const SCOPE_USER = 'user';
		const SCOPE_SESSION = 'session';

		public static function make($domain, array $parts = array())
		{
			$segments = array(self::segment($domain));

			foreach ($parts as $key => $value) {
				if ($value === null || $value === '') {
					continue;
				}

				if (!is_int($key)) {
					$segments[] = self::segment($key);
				}

				$segments[] = self::segment($value);
			}

			return implode(':', array_filter($segments, 'strlen'));
		}

		public static function tag($domain, $id = null)
		{
			return $id === null || $id === ''
				? self::segment($domain)
				: self::segment($domain) . ':' . self::segment($id);
		}

		public static function tags($domain, $id = null, array $extra = array())
		{
			$tags = array(self::tag($domain));

			if ($id !== null && $id !== '') {
				$tags[] = self::tag($domain, $id);
			}

			foreach ($extra as $tag) {
				if ($tag !== null && $tag !== '') {
					$tags[] = self::normalizeTag($tag);
				}
			}

			return array_values(array_unique($tags));
		}

		public static function hash($value, $length = 16)
		{
			if (is_array($value) || is_object($value)) {
				$value = self::normalizeValue($value);
			}

			$hash = sha1((string) $value);
			$length = max(8, min(40, (int) $length));
			return substr($hash, 0, $length);
		}

		public static function userScope($scope = self::SCOPE_PUBLIC, $value = null)
		{
			$scope = self::segment($scope);

			if ($scope === self::SCOPE_PUBLIC) {
				return self::SCOPE_PUBLIC;
			}

			if (!in_array($scope, array(self::SCOPE_ROLE, self::SCOPE_USER, self::SCOPE_SESSION), true)) {
				$scope = self::SCOPE_PUBLIC;
			}

			if ($value === null || $value === '') {
				return self::SCOPE_PUBLIC;
			}

			return $scope . ':' . self::segment($value);
		}

		public static function settings()
		{
			return self::make('settings', array('all'));
		}

		public static function permissionsRole($roleCode)
		{
			return self::make('permissions', array('role' => $roleCode));
		}

		public static function rubric($rubricId)
		{
			return self::make('content', array('rubric' => $rubricId));
		}

		public static function document($documentId, $lang = null)
		{
			return self::make('content', array('document' => $documentId, 'lang' => $lang));
		}

		public static function documentRender($documentId, $templateHash, $userScope = self::SCOPE_PUBLIC, $lang = null)
		{
			return self::make('content', array(
				'document-render' => $documentId,
				'tpl' => $templateHash,
				'user' => $userScope,
				'lang' => $lang,
			));
		}

		public static function request($requestId, $page = 1, array $filters = array(), $lang = null)
		{
			return self::make('content', array(
				'request' => $requestId,
				'page' => max(1, (int) $page),
				'filters' => self::hash($filters),
				'lang' => $lang,
			));
		}

		public static function templateCompiled($templateId, $sourceHash)
		{
			return self::make('template', array('compiled' => $templateId, $sourceHash));
		}

		public static function block($alias, $lang = null)
		{
			return self::make('block', array($alias, 'lang' => $lang));
		}

		public static function sysblock($alias, $mode = 'default', $lang = null)
		{
			return self::make('sysblock', array($alias, 'mode' => $mode, 'lang' => $lang));
		}

		public static function menu($alias, $activeDocumentId = null, $lang = null)
		{
			return self::make('menu', array($alias, 'active' => $activeDocumentId, 'lang' => $lang));
		}

		public static function catalogCategory($categoryId, array $filters = array(), $page = 1, $sort = null)
		{
			return self::make('catalog', array(
				'category' => $categoryId,
				'filters' => self::hash($filters),
				'page' => max(1, (int) $page),
				'sort' => $sort,
			));
		}

		public static function catalogFilterOptions($catalogId, $categoryId, array $filters = array())
		{
			return self::make('catalog', array(
				'filter-options' => $catalogId,
				$categoryId,
				self::hash($filters),
			));
		}

		public static function redirect($sourcePath)
		{
			return self::make('redirect', array(self::hash($sourcePath)));
		}

		public static function segment($value)
		{
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			} elseif (is_array($value) || is_object($value)) {
				$value = self::hash($value);
			}

			$value = strtolower(trim((string) $value));
			$value = preg_replace('/[^a-z0-9_.-]+/i', '-', $value);
			$value = trim($value, '-_.');

			return $value === '' ? 'none' : $value;
		}

		protected static function normalizeValue($value)
		{
			if (is_object($value)) {
				$value = get_object_vars($value);
			}

			if (is_array($value)) {
				ksort($value);
				foreach ($value as $key => $item) {
					$value[$key] = is_array($item) || is_object($item)
						? self::normalizeValue($item)
						: $item;
				}
			}

			return Json::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function normalizeTag($tag)
		{
			$parts = explode(':', (string) $tag);
			foreach ($parts as $i => $part) {
				$parts[$i] = self::segment($part);
			}

			return implode(':', array_filter($parts, 'strlen'));
		}
	}
