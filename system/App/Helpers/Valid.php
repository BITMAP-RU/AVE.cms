<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Valid.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	/**
	 * Валидатор значений.
	 *
	 * Два режима:
	 *   1. Статические методы: Valid::email($v), Valid::int($v, 1, 100)
	 *   2. Пакетная проверка: Valid::check($data, $rules) → ['field' => 'сообщение об ошибке']
	 */
	class Valid
	{
		/** Сообщения об ошибках на русском */
		protected static $messages = [
			'required'   => 'Поле «%field%» обязательно для заполнения',
			'email'      => 'Поле «%field%» должно быть корректным email-адресом',
			'url'        => 'Поле «%field%» должно быть корректным URL',
			'ip'         => 'Поле «%field%» должно быть корректным IP-адресом',
			'numeric'    => 'Поле «%field%» должно быть числом',
			'int'        => 'Поле «%field%» должно быть целым числом',
			'float'      => 'Поле «%field%» должно быть числом с плавающей точкой',
			'alpha'      => 'Поле «%field%» должно содержать только буквы',
			'alphaNum'   => 'Поле «%field%» должно содержать только буквы и цифры',
			'slug'       => 'Поле «%field%» должно содержать только строчные буквы, цифры и дефисы',
			'phone'      => 'Поле «%field%» должно быть корректным номером телефона',
			'date'       => 'Поле «%field%» должно быть датой в формате %param%',
			'min'        => 'Значение «%field%» должно быть не меньше %param%',
			'max'        => 'Значение «%field%» должно быть не больше %param%',
			'minLength'  => 'Поле «%field%» должно содержать не менее %param% символов',
			'maxLength'  => 'Поле «%field%» должно содержать не более %param% символов',
			'length'     => 'Поле «%field%» должно содержать ровно %param% символов',
			'regex'      => 'Поле «%field%» имеет неверный формат',
			'inArray'    => 'Значение «%field%» должно быть одним из: %param%',
			'notEmpty'   => 'Поле «%field%» не должно быть пустым',
		];

		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  Пакетная проверка (цепочка правил)
		// ------------------------------------------------------------------ //

		/**
		 * Проверить массив данных по набору правил.
		 *
		 * Правила задаются строкой вида 'required|email|maxLength:255'
		 * или массивом ['required', 'email', 'maxLength:255'].
		 *
		 * @param  array $data   Входные данные ($_POST, $_GET и т.п.)
		 * @param  array $rules  ['field' => 'rule1|rule2:param']
		 * @param  array $labels ['field' => 'Название поля'] (опционально)
		 * @return array         ['field' => 'сообщение'] — пустой массив если всё ок
		 */
		public static function check(array $data, array $rules, array $labels = [])
		{
			$errors = [];

			foreach ($rules as $field => $fieldRules) {
				$value     = isset($data[$field]) ? $data[$field] : null;
				$label     = isset($labels[$field]) ? $labels[$field] : $field;
				$rulesList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);

				foreach ($rulesList as $ruleItem) {
					$params = [];

					if (strpos($ruleItem, ':') !== false) {
						list($rule, $paramStr) = explode(':', $ruleItem, 2);
						$params = explode(',', $paramStr);
					} else {
						$rule = $ruleItem;
					}

					$rule = trim($rule);

					// Если поле пустое и правило не required — пропустить остальные правила
					if ($rule !== 'required' && ($value === null || $value === '')) {
						continue;
					}

					$error = self::applyRule($label, $value, $rule, $params);

					if ($error !== null) {
						$errors[$field] = $error;
						break; // первая ошибка на поле, переходим к следующему
					}
				}
			}

			return $errors;
		}

		/**
		 * Применить одно правило к значению.
		 *
		 * @param  string   $field  Название поля для сообщения об ошибке
		 * @param  mixed    $value  Проверяемое значение
		 * @param  string   $rule   Имя правила
		 * @param  string[] $params Параметры правила
		 * @return string|null      Сообщение об ошибке или null если ок
		 */
		protected static function applyRule($field, $value, $rule, array $params = [])
		{
			$param = isset($params[0]) ? $params[0] : '';

			switch ($rule) {
				case 'required':
					return self::required($value) ? null : self::msg('required', $field, $param);

				case 'email':
					return self::email($value) ? null : self::msg('email', $field, $param);

				case 'url':
					return self::url($value) ? null : self::msg('url', $field, $param);

				case 'ip':
					return self::ip($value) ? null : self::msg('ip', $field, $param);

				case 'numeric':
					return self::numeric($value) ? null : self::msg('numeric', $field, $param);

				case 'int':
				case 'integer':
					return self::int($value) ? null : self::msg('int', $field, $param);

				case 'float':
					return self::float($value) ? null : self::msg('float', $field, $param);

				case 'alpha':
					return self::alpha($value) ? null : self::msg('alpha', $field, $param);

				case 'alphaNum':
				case 'alphanum':
					return self::alphaNum($value) ? null : self::msg('alphaNum', $field, $param);

				case 'slug':
					return self::slug($value) ? null : self::msg('slug', $field, $param);

				case 'phone':
					return self::phone($value) ? null : self::msg('phone', $field, $param);

				case 'date':
					$format = $param ?: 'Y-m-d';
					return self::date($value, $format) ? null : self::msg('date', $field, $format);

				case 'min':
					return ($param !== '' && is_numeric($value) && $value >= (float)$param)
						? null
						: self::msg('min', $field, $param);

				case 'max':
					return ($param !== '' && is_numeric($value) && $value <= (float)$param)
						? null
						: self::msg('max', $field, $param);

				case 'minLength':
					return ($param !== '' && mb_strlen((string)$value) >= (int)$param)
						? null
						: self::msg('minLength', $field, $param);

				case 'maxLength':
					return ($param !== '' && mb_strlen((string)$value) <= (int)$param)
						? null
						: self::msg('maxLength', $field, $param);

				case 'length':
					return ($param !== '' && mb_strlen((string)$value) === (int)$param)
						? null
						: self::msg('length', $field, $param);

				case 'regex':
					return self::regex($value, $param) ? null : self::msg('regex', $field, $param);

				case 'inArray':
					return self::inArray($value, $params) ? null : self::msg('inArray', $field, implode(', ', $params));

				case 'notEmpty':
					return ($value !== null && $value !== '' && $value !== [])
						? null
						: self::msg('notEmpty', $field, $param);

				default:
					return null;
			}
		}

		/** Сформировать сообщение об ошибке */
		protected static function msg($rule, $field, $param = '')
		{
			$tpl = isset(self::$messages[$rule]) ? self::$messages[$rule] : "Ошибка валидации поля «{$field}»";
			return str_replace(['%field%', '%param%'], [$field, $param], $tpl);
		}

		/**
		 * Переопределить сообщения (для кастомизации).
		 */
		public static function setMessages(array $messages)
		{
			self::$messages = array_merge(self::$messages, $messages);
		}

		// ------------------------------------------------------------------ //
		//  Статические методы проверки
		// ------------------------------------------------------------------ //

		/** Значение непустое (не null, не '', не []) */
		public static function required($value)
		{
			if ($value === null || $value === '') return false;
			if (is_array($value) && empty($value)) return false;
			return true;
		}

		/** Email-адрес */
		public static function email($email)
		{
			$email = (string) $email;
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return true;
			}

			// filter_var отвергает домены без точки (напр. admin@local), но именно
			// такие внутренние логины система заводит и авторизует сама. Принимаем
			// адрес с непустой локальной частью и доменом-хостнеймом (в т.ч.
			// однословным), отклоняя мусор вида "@local" или "user@".
			return (bool) preg_match(
				'/^[^\s@]+@[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i',
				$email
			);
		}

		/** URL */
		public static function url($url)
		{
			return (bool)filter_var($url, FILTER_VALIDATE_URL);
		}

		/** IP-адрес (v4 или v6) */
		public static function ip($ip)
		{
			return (bool)filter_var($ip, FILTER_VALIDATE_IP);
		}

		/** IPv4 */
		public static function ipv4($ip)
		{
			return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
		}

		/** IPv6 */
		public static function ipv6($ip)
		{
			return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
		}

		/** Числовое значение (int или float в виде строки) */
		public static function numeric($value)
		{
			return is_numeric($value);
		}

		/** Целое число */
		public static function int($value, $min = null, $max = null)
		{
			$options = [];
			if ($min !== null) $options['min_range'] = (int)$min;
			if ($max !== null) $options['max_range'] = (int)$max;

			$result = filter_var($value, FILTER_VALIDATE_INT, $options ? ['options' => $options] : null);
			return $result !== false;
		}

		/** Число с плавающей точкой */
		public static function float($value)
		{
			return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
		}

		/** Только буквы (Unicode-aware) */
		public static function alpha($value)
		{
			return (bool)preg_match('/^\pL+$/u', (string)$value);
		}

		/** Только буквы и цифры */
		public static function alphaNum($value)
		{
			return (bool)preg_match('/^[\pL\pN]+$/u', (string)$value);
		}

		/** slug: строчные буквы a-z, цифры 0-9, дефис */
		public static function slug($value)
		{
			return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$value);
		}

		/** Минимальная длина строки (UTF-8) */
		public static function minLength($value, $min)
		{
			return mb_strlen((string)$value) >= (int)$min;
		}

		/** Максимальная длина строки (UTF-8) */
		public static function maxLength($value, $max)
		{
			return mb_strlen((string)$value) <= (int)$max;
		}

		/** Точная длина строки (UTF-8) */
		public static function length($value, $length)
		{
			return mb_strlen((string)$value) === (int)$length;
		}

		/** Значение соответствует регулярному выражению */
		public static function regex($value, $pattern)
		{
			return (bool)preg_match($pattern, (string)$value);
		}

		/** Значение входит в список допустимых */
		public static function inArray($value, array $allowed)
		{
			return in_array($value, $allowed, true);
		}

		/**
		 * Телефонный номер.
		 * Принимает форматы: +79991234567, 89991234567, 9991234567, (999) 123-45-67
		 * Минимум 7 цифр, максимум 15 (E.164).
		 */
		public static function phone($value)
		{
			$digits = preg_replace('/\D/', '', (string)$value);
			return strlen($digits) >= 7 && strlen($digits) <= 15;
		}

		/**
		 * Дата в указанном формате (по умолчанию Y-m-d).
		 * Проверяет и формат и валидность даты.
		 */
		public static function date($value, $format = 'Y-m-d')
		{
			$d = \DateTime::createFromFormat($format, (string)$value);
			return $d && $d->format($format) === (string)$value;
		}

		/** Значение меньше или равно max */
		public static function max($value, $max)
		{
			return is_numeric($value) && $value <= $max;
		}

		/** Значение больше или равно min */
		public static function min($value, $min)
		{
			return is_numeric($value) && $value >= $min;
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
