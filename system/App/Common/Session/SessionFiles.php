<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Session/SessionFiles.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Session;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use RuntimeException;

	class SessionFiles
	{
		public static $sessLifeTime;
		public static $savePath;
		public static $sessionName;

		private static $instance = null;

		private function __construct()
		{
			$configured = (int) get_cfg_var('session.gc_maxlifetime');
			$defined    = defined('SESSION_LIFETIME') && is_numeric(SESSION_LIFETIME) ? (int) SESSION_LIFETIME : 0;

			self::$sessLifeTime = $defined > 0 ? $defined : max($configured, 1440);
		}

		public static function init()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public static function open($savePath, $sessionName)
		{
			self::$savePath    = BASEPATH . SESSION_DIR;
			self::$sessionName = $sessionName;
			return true;
		}

		public static function close()
		{
			return true;
		}

		public static function read($id)
		{
			$sessionFile = self::_folder($id) . '/' . $id . '.sess';

			if (!file_exists($sessionFile)) {
				return '';
			}

			$fp = @fopen($sessionFile, 'rb');
			if (!$fp) {
				return '';
			}

			flock($fp, LOCK_SH);
			$data = fread($fp, max(1, filesize($sessionFile)));
			flock($fp, LOCK_UN);
			fclose($fp);

			return $data;
		}

		public static function write($id, $sessionData)
		{
			$folder = self::_folder($id);

			if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
				throw new RuntimeException(sprintf('Session directory "%s" could not be created', $folder));
			}

			$sessionFile = $folder . '/' . $id . '.sess';
			$fp = @fopen($sessionFile, 'wb');
			if (!$fp) {
				return false;
			}

			flock($fp, LOCK_EX);
			fwrite($fp, $sessionData);
			flock($fp, LOCK_UN);
			fclose($fp);

			return true;
		}

		public static function destroy($id)
		{
			$sessionFile = self::_folder($id) . '/' . $id . '.sess';
			return file_exists($sessionFile) ? @unlink($sessionFile) : true;
		}

		public static function gc($maxLifeTime)
		{
			self::clearBounded(BASEPATH . SESSION_DIR, 'sess', $maxLifeTime, 500);
			return true;
		}

		public static function clearBounded($dir, $mask, $maxLifeTime, $limit = 500)
		{
			$remaining = max(1, min(5000, (int) $limit));
			$stack = array((string) $dir);
			$cutoff = time() - max(1, (int) $maxLifeTime);
			while ($stack && $remaining > 0) {
				$current = array_pop($stack);
				$items = is_dir($current) ? glob($current . '/*') : false;
				if (!is_array($items)) { continue; }
				foreach ($items as $fileName) {
					if ($remaining-- <= 0) { break 2; }
					if (is_dir($fileName)) {
						$stack[] = $fileName;
						continue;
					}

					$ext = strtolower(substr($fileName, -strlen($mask)));
					if ($ext === strtolower($mask) && filemtime($fileName) < $cutoff) {
						@unlink($fileName);
					}
				}
			}
		}

		public static function clear($dir, $mask, $maxLifeTime)
		{
			self::clearBounded($dir, $mask, $maxLifeTime, 5000);
		}

		public static function _folder($id)
		{
			return self::$savePath . '/' . mb_substr($id, 0, 3);
		}

		public function __destruct()
		{
			//
		}
	}
