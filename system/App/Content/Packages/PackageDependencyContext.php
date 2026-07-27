<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Packages/PackageDependencyContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Packages;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Request-local state of dependencies delivered by a content package.
	 *
	 * The archive installer records the state before deploying an embedded
	 * module. Package lifecycle code can then report the real original state
	 * even when the dependency has already been installed from the nested ZIP.
	 */
	class PackageDependencyContext
	{
		protected static $states = array();

		public static function remember($packageCode, $moduleCode, $state)
		{
			$packageCode = self::code($packageCode);
			$moduleCode = self::code($moduleCode);
			$state = strtolower(trim((string) $state));
			if ($packageCode === '' || $moduleCode === ''
				|| !in_array($state, array('available', 'disabled', 'enabled'), true)) {
				return;
			}

			if (!isset(self::$states[$packageCode])) {
				self::$states[$packageCode] = array();
			}

			if (!isset(self::$states[$packageCode][$moduleCode])) {
				self::$states[$packageCode][$moduleCode] = $state;
			}
		}

		public static function states($packageCode)
		{
			$packageCode = self::code($packageCode);
			return $packageCode !== '' && isset(self::$states[$packageCode])
				? self::$states[$packageCode]
				: array();
		}

		public static function clear($packageCode)
		{
			$packageCode = self::code($packageCode);
			if ($packageCode !== '') {
				unset(self::$states[$packageCode]);
			}
		}

		protected static function code($code)
		{
			$code = strtolower(trim((string) $code));
			return preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code) ? $code : '';
		}
	}
