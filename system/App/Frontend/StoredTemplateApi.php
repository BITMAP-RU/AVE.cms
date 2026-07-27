<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/StoredTemplateApi.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/** Native targets for historical helper calls in administrator-managed PHP. */
	class StoredTemplateApi
	{
		public static function settings($field = '')
		{
			return PublicSettings::get((string) $field);
		}

		public static function sort($array, $key, $sortFlags = SORT_REGULAR, $sortWay = SORT_ASC)
		{
			if (!is_array($array) || !$array || empty($key)) {
				return $array;
			}

			$mapping = array();
			foreach ($array as $index => $value) {
				if (is_array($key)) {
					$sortKey = '';
					foreach ($key as $part) {
						$sortKey .= isset($value[$part]) ? $value[$part] : '';
					}

					$sortFlags = SORT_STRING;
				} else {
					$sortKey = isset($value[$key]) ? $value[$key] : '';
				}

				$mapping[$index] = $sortKey;
			}

			if ($sortWay === SORT_DESC) {
				arsort($mapping, $sortFlags);
			} else {
				asort($mapping, $sortFlags);
			}

			$sorted = array();
			foreach ($mapping as $index => $unused) {
				$sorted[] = $array[$index];
			}

			return $sorted;
		}

		public static function outputJson($data, $exit = false)
		{
			header('Content-Type: application/json;charset=utf-8');
			$json = Json::encode($data);
			if (strpos($json, '["jsonError",') === 0) {
				http_response_code(500);
			}

			echo $json;
			if ($exit) {
				exit;
			}
		}
	}
