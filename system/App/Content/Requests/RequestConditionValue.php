<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestConditionValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Data-only value source used by request conditions in both executors. */
	class RequestConditionValue
	{
		const SOURCE_LITERAL = 'literal';
		const SOURCE_INPUT = 'input';

		public static function tag($key)
		{
			$key = self::key($key);
			return $key === '' ? '' : '[tag:cond:' . $key . ']';
		}

		public static function tagKey($value)
		{
			$match = array();
			return preg_match('/^\[tag:cond:([A-Za-z_][A-Za-z0-9_-]{0,63})\]$/', trim((string) $value), $match) === 1
				? (string) $match[1]
				: '';
		}

		/** Returns an explicit descriptor or null for a legacy/literal condition. */
		public static function descriptor($condition)
		{
			$row = is_object($condition) ? get_object_vars($condition) : (array) $condition;
			$source = isset($row['condition_value_source']) ? strtolower(trim((string) $row['condition_value_source'])) : '';
			$value = isset($row['condition_value']) ? trim((string) $row['condition_value']) : '';
			$key = isset($row['condition_value_key']) ? self::key($row['condition_value_key']) : '';
			if ($key === '') {
				$key = self::tagKey($value);
			}

			if ($source !== self::SOURCE_INPUT || $key === '') {
				return null;
			}

			$config = self::decodeConfig(isset($row['condition_value_config']) ? $row['condition_value_config'] : '');
			return self::normalizeDescriptor(array(
				'source' => self::SOURCE_INPUT,
				'key' => $key,
				'cast' => isset($config['cast']) ? $config['cast'] : 'string',
				'shape' => isset($config['shape']) ? $config['shape'] : 'scalar',
				'transform' => isset($config['transform']) ? $config['transform'] : 'plain',
				'constant' => isset($config['constant']) ? $config['constant'] : '',
				'range_min_default' => array_key_exists('range_min_default', $config) ? $config['range_min_default'] : null,
				'range_max_positive' => !empty($config['range_max_positive']),
			));
		}

		/** Recognizes the finite set of legacy request snippets without executing them. */
		public static function legacyDescriptor($value, $operator = '')
		{
			$source = RequestRuntimeValue::parse($value);
			if ($source !== null) {
				$cast = isset($source['cast']) ? (string) $source['cast'] : 'string';
				$descriptor = array(
					'source' => self::SOURCE_INPUT,
					'key' => isset($source['key']) ? $source['key'] : '',
					'cast' => strpos($cast, 'integer') !== false ? 'integer' : 'string',
					'shape' => $cast === 'constant' ? 'presence' : 'scalar',
					'transform' => strpos($cast, 'pipe_') === 0 ? 'pipe_member' : 'plain',
					'constant' => $cast === 'constant' && isset($source['value']) ? $source['value'] : '',
				);
				return self::normalizeDescriptor($descriptor);
			}

			if ((string) $operator !== 'FRE') { return null; }
			$key = self::legacyRangeKey($value);
			$maxPositive = $key !== '' && preg_match(
				'/intval\s*\(\s*\$_REQUEST\[\'' . preg_quote($key, '/') . '\'\]\[\'max\'\]\s*\)\s*>\s*0/i',
				(string) $value
			) === 1;
			return $key === '' ? null : self::normalizeDescriptor(array(
				'source' => self::SOURCE_INPUT,
				'key' => $key,
				'cast' => 'integer',
				'shape' => 'range',
				'transform' => 'plain',
				'constant' => '',
				'range_min_default' => '0',
				'range_max_positive' => $maxPositive,
			));
		}

		public static function normalizeDescriptor(array $descriptor)
		{
			$cast = isset($descriptor['cast']) ? strtolower((string) $descriptor['cast']) : 'string';
			$shape = isset($descriptor['shape']) ? strtolower((string) $descriptor['shape']) : 'scalar';
			$transform = isset($descriptor['transform']) ? strtolower((string) $descriptor['transform']) : 'plain';
			$result = array(
				'source' => self::SOURCE_INPUT,
				'key' => self::key(isset($descriptor['key']) ? $descriptor['key'] : ''),
				'cast' => in_array($cast, array('string', 'integer', 'decimal'), true) ? $cast : 'string',
				'shape' => in_array($shape, array('scalar', 'list', 'range', 'presence'), true) ? $shape : 'scalar',
				'transform' => $transform === 'pipe_member' ? 'pipe_member' : 'plain',
				'constant' => self::plain(isset($descriptor['constant']) ? $descriptor['constant'] : ''),
			);
			if ($result['shape'] === 'range') {
				$result['range_min_default'] = array_key_exists('range_min_default', $descriptor)
					? self::cast($descriptor['range_min_default'], $result['cast'])
					: null;
				$result['range_max_positive'] = !empty($descriptor['range_max_positive']);
			}

			return $result;
		}

		public static function config(array $descriptor)
		{
			$descriptor = self::normalizeDescriptor($descriptor);
			$config = array(
				'cast' => $descriptor['cast'],
				'shape' => $descriptor['shape'],
				'transform' => $descriptor['transform'],
				'constant' => $descriptor['constant'],
			);
			if ($descriptor['shape'] === 'range') {
				$config['range_min_default'] = $descriptor['range_min_default'];
				$config['range_max_positive'] = $descriptor['range_max_positive'];
			}

			return $config;
		}

		public static function encodeConfig(array $config)
		{
			return json_encode(self::config($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public static function decodeConfig($value)
		{
			if (is_array($value)) {
				return $value;
			}

			$decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;
			return is_array($decoded) ? $decoded : array();
		}

		/** Returns a scalar/list/range value or null when the optional input is empty. */
		public static function resolve(array $descriptor, array $input)
		{
			$descriptor = self::normalizeDescriptor($descriptor);
			$key = $descriptor['key'];
			if ($key === '' || !array_key_exists($key, $input)) {
				return null;
			}

			$raw = $input[$key];
			if ($descriptor['shape'] === 'presence') {
				if (is_array($raw) ? !$raw : trim((string) $raw) === '') { return null; }
				return array('kind' => 'scalar', 'value' => $descriptor['constant']);
			}

			if ($descriptor['shape'] === 'range') {
				if (!is_array($raw)) { return null; }
				$min = self::cast(isset($raw['min']) ? $raw['min'] : '', $descriptor['cast']);
				$max = self::cast(isset($raw['max']) ? $raw['max'] : '', $descriptor['cast']);
				if ($min === null && $descriptor['range_min_default'] !== null) {
					$min = $descriptor['range_min_default'];
				}

				if ($max !== null && $descriptor['range_max_positive'] && (float) $max <= 0) {
					$max = null;
				}

				return $min === null && $max === null ? null : array('kind' => 'range', 'min' => $min, 'max' => $max);
			}

			if ($descriptor['shape'] === 'list') {
				$items = is_array($raw) ? $raw : array($raw);
				$values = array();
				foreach ($items as $item) {
					if (is_array($item) || is_object($item)) { continue; }
					$item = self::cast($item, $descriptor['cast']);
					if ($item !== null) { $values[(string) $item] = $item; }
				}

				return $values ? array(
					'kind' => 'list',
					'values' => array_values($values),
					'transform' => $descriptor['transform'],
				) : null;
			}

			if (is_array($raw) || is_object($raw)) { return null; }
			$value = self::cast($raw, $descriptor['cast']);
			if ($value === null) { return null; }
			if ($descriptor['transform'] === 'pipe_member') { $value = '|' . $value . '|'; }
			return array('kind' => 'scalar', 'value' => $value);
		}

		public static function probe(array $descriptor)
		{
			$descriptor = self::normalizeDescriptor($descriptor);
			if ($descriptor['shape'] === 'range') { return array('min' => '1', 'max' => '2'); }
			if ($descriptor['shape'] === 'list') { return array('1'); }
			return '1';
		}

		/** Builds only a comparison fragment from normalized, server-validated data. */
		public static function legacyComparison($field, $operator, array $resolved)
		{
			if (!in_array($field, array('rf.field_value', 'rf.field_number_value'), true)) {
				return '';
			}

			$kind = isset($resolved['kind']) ? (string) $resolved['kind'] : '';
			if ($kind === 'range') {
				$parts = array();
				if ($resolved['min'] !== null) { $parts[] = $field . " >= '" . (float) $resolved['min'] . "'"; }
				if ($resolved['max'] !== null) { $parts[] = $field . " <= '" . (float) $resolved['max'] . "'"; }
				return $parts ? '(' . implode(' AND ', $parts) . ')' : '';
			}

			if ($kind === 'list') {
				$parts = array();
				foreach ((array) $resolved['values'] as $value) {
					$value = DB::safe((string) $value);
					$parts[] = !empty($resolved['transform']) && $resolved['transform'] === 'pipe_member'
						? $field . " LIKE '%|" . $value . "|%'"
						: $field . " = '" . $value . "'";
				}

				return $parts ? '(' . implode(' OR ', $parts) . ')' : '';
			}

			$value = DB::safe(isset($resolved['value']) ? (string) $resolved['value'] : '');
			$map = array(
				'==' => '=', '!=' => '!=', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<=',
				'N>' => '>', 'N<' => '<', 'N>=' => '>=', 'N<=' => '<=',
				'%%' => 'LIKE', '%' => 'LIKE', '--' => 'NOT LIKE', '!-' => 'NOT LIKE',
			);
			if (!isset($map[$operator])) { return ''; }
			if ($operator === '%%' || $operator === '--') { $value = '%' . $value . '%'; }
			elseif ($operator === '%' || $operator === '!-') { $value .= '%'; }

			return $field . ' ' . $map[$operator] . " '" . $value . "'";
		}

		protected static function cast($value, $cast)
		{
			$value = trim((string) $value);
			if ($value === '') { return null; }
			if ($cast === 'integer') { return (string) (int) $value; }
			if ($cast === 'decimal') {
				$value = str_replace(',', '.', $value);
				return is_numeric($value) ? (string) (float) $value : null;
			}

			return self::plain($value);
		}

		protected static function key($value)
		{
			$value = trim((string) $value);
			return preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $value) === 1 ? $value : '';
		}

		protected static function legacyRangeKey($value)
		{
			$value = trim((string) $value);
			$match = array();
			if (preg_match('/^<\?(?:php)?\s*if\s*\(\s*isset\(\$_REQUEST\[\'([A-Za-z_][A-Za-z0-9_-]{0,63})\'\]\)\s*\).*\[field\]\s*>=.*\[\'min\'\].*\[field\]\s*<=.*\[\'max\'\].*\}\s*\?>$/is', $value, $match) !== 1) {
				return '';
			}

			$keys = array();
			preg_match_all('/\$_REQUEST\[\'([A-Za-z_][A-Za-z0-9_-]{0,63})\'\]/', $value, $keys);
			if (empty($keys[1]) || count(array_unique($keys[1])) !== 1 || reset($keys[1]) !== $match[1]) {
				return '';
			}

			return self::key($match[1]);
		}

		protected static function plain($value)
		{
			$value = str_replace(array("\0", "\r", "\n"), '', (string) $value);
			return function_exists('mb_substr') ? mb_substr($value, 0, 1000, 'UTF-8') : substr($value, 0, 1000);
		}
	}
