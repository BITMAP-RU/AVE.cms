<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/ComputedFieldEvaluator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Calculates readonly document fields from aliases without executing PHP. */
	class ComputedFieldEvaluator
	{
		public static function apply(array $definitions, array $values, array $document = array())
		{
			$definitions = self::normalizeDefinitions($definitions);
			if (!$definitions) { return $values; }

			// Several passes allow one computed field to reference another while
			// keeping the implementation deterministic and cycle-safe.
			$maxPasses = max(1, count($definitions));
			for ($pass = 0; $pass < $maxPasses; $pass++) {
				$changed = false;
				$variables = self::variables($definitions, $values, $document);
				foreach ($definitions as $id => $field) {
					if ((string) $field['rubric_field_type'] !== 'computed') { continue; }
					$settings = FieldSettings::effective($field);
					$value = self::calculate($settings, $variables);
					if (!array_key_exists($id, $values) || (string) $values[$id] !== (string) $value) {
						$values[$id] = $value;
						$changed = true;
					}
				}

				if (!$changed) { break; }
			}

			return $values;
		}

		public static function calculate(array $settings, array $variables)
		{
			$expression = trim(isset($settings['expression']) ? (string) $settings['expression'] : '');
			$fallback = isset($settings['fallback']) ? (string) $settings['fallback'] : '';
			if ($expression === '') { return $fallback; }

			$mode = isset($settings['mode']) && (string) $settings['mode'] === 'number' ? 'number' : 'text';
			if ($mode === 'text') {
				return preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', function ($match) use ($variables) {
					return array_key_exists($match[1], $variables) ? self::scalar($variables[$match[1]]) : '';
				}, $expression);
			}

			try {
				$parser = new ComputedNumberParser($expression, $variables);
				$value = $parser->parse();
				$precision = isset($settings['precision']) ? max(0, min(8, (int) $settings['precision'])) : 2;
				$value = round($value, $precision);
				return $precision > 0
					? rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.')
					: (string) number_format($value, 0, '.', '');
			} catch (\Throwable $e) {
				return $fallback;
			}
		}

		protected static function normalizeDefinitions(array $definitions)
		{
			$out = array();
			foreach ($definitions as $key => $field) {
				if (isset($field['items']) && is_array($field['items'])) {
					foreach ($field['items'] as $item) {
						if (is_array($item) && !empty($item['Id'])) { $out[(int) $item['Id']] = $item; }
					}

					continue;
				}

				if (is_array($field) && !empty($field['Id'])) {
					$out[(int) $field['Id']] = $field;
				} elseif (is_array($field) && (int) $key > 0) {
					$out[(int) $key] = $field;
				}
			}

			return $out;
		}

		protected static function variables(array $definitions, array $values, array $document)
		{
			$variables = array();
			foreach ($document as $key => $value) {
				if (preg_match('/^[A-Za-z0-9_]+$/', (string) $key)) {
					$variables[(string) $key] = self::scalar($value);
				}
			}

			foreach ($definitions as $id => $field) {
				$alias = isset($field['rubric_field_alias']) ? trim((string) $field['rubric_field_alias']) : '';
				if ($alias === '') { $alias = 'field_' . $id; }
				$variables[$alias] = array_key_exists($id, $values) ? self::scalar($values[$id]) : '';
			}

			return $variables;
		}

		protected static function scalar($value)
		{
			if (is_scalar($value) || $value === null) { return (string) $value; }
			if (!is_array($value)) { return ''; }
			foreach (array('raw', 'number', 'selected', 'value', 'date', 'datetime', 'checked') as $key) {
				if (array_key_exists($key, $value) && (is_scalar($value[$key]) || $value[$key] === null)) {
					return (string) $value[$key];
				}
			}

			$flat = array();
			foreach ($value as $item) {
				if (is_scalar($item)) { $flat[] = (string) $item; }
			}

			return implode(', ', $flat);
		}
	}

	/** Minimal arithmetic parser: numbers, aliases, + - * / and parentheses. */
	class ComputedNumberParser
	{
		protected $tokens = array();
		protected $position = 0;
		protected $variables = array();

		public function __construct($expression, array $variables)
		{
			$this->variables = $variables;
			$expression = str_replace(',', '.', (string) $expression);
			if (!preg_match_all('/\s*([A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|[()+\-*\/])\s*/', $expression, $matches)) {
				throw new \InvalidArgumentException('Пустая формула');
			}

			$this->tokens = $matches[1];
			if (preg_replace('/\s+/', '', implode('', $this->tokens)) !== preg_replace('/\s+/', '', $expression)) {
				throw new \InvalidArgumentException('Недопустимый символ формулы');
			}
		}

		public function parse()
		{
			$value = $this->expression();
			if ($this->position !== count($this->tokens)) {
				throw new \InvalidArgumentException('Лишние элементы формулы');
			}

			return $value;
		}

		protected function expression()
		{
			$value = $this->term();
			while ($this->peek() === '+' || $this->peek() === '-') {
				$operator = $this->next();
				$right = $this->term();
				$value = $operator === '+' ? $value + $right : $value - $right;
			}

			return $value;
		}

		protected function term()
		{
			$value = $this->factor();
			while ($this->peek() === '*' || $this->peek() === '/') {
				$operator = $this->next();
				$right = $this->factor();
				if ($operator === '/' && abs($right) < 0.0000000001) {
					throw new \RuntimeException('Деление на ноль');
				}

				$value = $operator === '*' ? $value * $right : $value / $right;
			}

			return $value;
		}

		protected function factor()
		{
			$token = $this->next();
			if ($token === '-') { return -$this->factor(); }
			if ($token === '+') { return $this->factor(); }
			if ($token === '(') {
				$value = $this->expression();
				if ($this->next() !== ')') { throw new \InvalidArgumentException('Не закрыта скобка'); }
				return $value;
			}

			if (is_numeric($token)) { return (float) $token; }
			if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $token)) {
				if (!array_key_exists($token, $this->variables)) {
					throw new \InvalidArgumentException('Неизвестное поле формулы: ' . $token);
				}

				$value = $this->variables[$token];
				$value = str_replace(',', '.', (string) $value);
				$value = preg_replace('/[^0-9.\-]/', '', $value);
				return is_numeric($value) ? (float) $value : 0.0;
			}

			throw new \InvalidArgumentException('Некорректная формула');
		}

		protected function peek()
		{
			return isset($this->tokens[$this->position]) ? $this->tokens[$this->position] : null;
		}

		protected function next()
		{
			return isset($this->tokens[$this->position]) ? $this->tokens[$this->position++] : null;
		}
	}
