<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestRuntimeValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Converts known legacy request-input snippets into data-only value sources. */
	class RequestRuntimeValue
	{
		public static function parse($value)
		{
			$code = self::code($value);
			if ($code === null) {
				return null;
			}

			$key = self::sameKeyMatch($code, self::integerPattern());
			if ($key !== null) {
				return self::source($key, 'integer');
			}

			$key = self::sameKeyMatch($code, self::stringPattern());
			if ($key !== null) {
				return self::source($key, 'string');
			}

			$match = array();
			if (preg_match(self::pipePattern(), $code, $match) === 1 && self::sameKeys($match)) {
				return self::source($match['key'], empty($match['integer']) ? 'pipe_string' : 'pipe_integer');
			}

			$match = array();
			if (preg_match(self::constantPattern(), $code, $match) === 1 && self::sameKeys($match)) {
				return array(
					'type' => 'request',
					'key' => $match['key'],
					'cast' => 'constant',
					'value' => (string) $match['constant'],
				);
			}

			return null;
		}

		/** Returns null when an optional input is absent or empty. */
		public static function resolve(array $source, array $input)
		{
			$key = isset($source['key']) ? (string) $source['key'] : '';
			if ($key === '' || !array_key_exists($key, $input) || is_array($input[$key]) || is_object($input[$key])) {
				return null;
			}

			$raw = trim((string) $input[$key]);
			if ($raw === '') {
				return null;
			}

			$cast = isset($source['cast']) ? (string) $source['cast'] : '';
			if ($cast === 'integer') {
				return (string) (int) $raw;
			}

			if ($cast === 'pipe_integer') {
				return '|' . (int) $raw . '|';
			}

			if ($cast === 'pipe_string') {
				return '|' . self::plain($raw) . '|';
			}

			if ($cast === 'constant') {
				return isset($source['value']) ? (string) $source['value'] : '';
			}

			return self::plain($raw);
		}

		public static function probe(array $source)
		{
			return '1';
		}

		protected static function source($key, $cast)
		{
			return array('type' => 'request', 'key' => (string) $key, 'cast' => (string) $cast);
		}

		protected static function plain($value)
		{
			$value = str_replace(array("\0", "\r", "\n"), '', (string) $value);
			return function_exists('mb_substr') ? mb_substr($value, 0, 1000, 'UTF-8') : substr($value, 0, 1000);
		}

		protected static function code($value)
		{
			$value = trim((string) $value);
			if (preg_match('/^<\?(?:php)?\s*(.*?)\s*\?>$/is', $value, $match) !== 1) {
				return null;
			}

			return trim(preg_replace('/\s+/', ' ', $match[1]));
		}

		protected static function sameKeyMatch($code, $pattern)
		{
			$match = array();
			return preg_match($pattern, $code, $match) === 1 && self::sameKeys($match)
				? (string) $match['key']
				: null;
		}

		protected static function sameKeys(array $match)
		{
			return isset($match['key'], $match['value_key'])
				&& $match['key'] === $match['value_key']
				&& preg_match('/^[A-Za-z0-9_-]{1,64}$/', $match['key']) === 1;
		}

		protected static function integerPattern()
		{
			return "~^if\s*\(\s*isset\(\\\$_REQUEST\['(?P<key>[A-Za-z0-9_-]+)'\]\)\s*&&\s*\\\$_REQUEST\['(?P<value_key>[A-Za-z0-9_-]+)'\]\s*!=\s*''\s*\)\s*echo\s*\(int\)\s*\\\$_REQUEST\['(?P=key)'\]\s*;$~";
		}

		protected static function stringPattern()
		{
			return "~^if\s*\(\s*isset\(\\\$_REQUEST\['(?P<key>[A-Za-z0-9_-]+)'\]\)\s*&&\s*\\\$_REQUEST\['(?P<value_key>[A-Za-z0-9_-]+)'\]\s*!=\s*''\s*\)\s*echo\s*DB::safe\(\\\$_REQUEST\['(?P=key)'\]\)\s*;$~i";
		}

		protected static function pipePattern()
		{
			return "~^if\s*\(\s*isset\(\\\$_REQUEST\['(?P<key>[A-Za-z0-9_-]+)'\]\)(?:\s*&&\s*(?:!empty\(\\\$_REQUEST\['(?P=key)'\]\)|\\\$_REQUEST\['(?P=key)'\]\s*!=\s*''))?\s*\)\s*echo\s*'\\|'\s*\.\s*(?P<integer>\(int\)\s*)?\\\$_REQUEST\['(?P<value_key>[A-Za-z0-9_-]+)'\]\s*\.\s*'\\|'\s*;$~";
		}

		protected static function constantPattern()
		{
			return "~^if\s*\(\s*isset\(\\\$_REQUEST\['(?P<key>[A-Za-z0-9_-]+)'\]\)\s*&&\s*\\\$_REQUEST\['(?P<value_key>[A-Za-z0-9_-]+)'\]\s*!=\s*''\s*\)\s*echo\s*(?P<constant>-?[0-9]+)\s*;$~";
		}
	}
