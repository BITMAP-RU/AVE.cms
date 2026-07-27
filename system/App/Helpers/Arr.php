<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Arr.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use App\Common\Exceptions;
	use ArrayAccess;

	class Arr
	{
		/**
		 * Проверить, является ли переменная массивом
		 *
		 * Метод проверяет, является ли переданное значение массивом или объектом,
		 * который реализует интерфейс ArrayAccess.
		 *
		 * @param mixed $array Значение для проверки.
		 * @return bool Возвращает TRUE, если значение - массив или ArrayAccess, иначе FALSE.
		 *
		 * @example
		 * <code>
		 * $array = [1, 2, 3];
		 * $is_array = Arr::is($array); // true
		 *
		 * $not_array = 'hello';
		 * $is_array = Arr::is($not_array); // false
		 * </code>
		 */
		public static function is($array) : bool
		{
			return is_array($array) || $array instanceof ArrayAccess;
		}

		/**
		 * Определяет, является ли массив ассоциативным.
		 *
		 * Массив считается ассоциативным, если его ключи не являются
		 * последовательными целыми числами, начиная с 0.
		 *
		 * @param array $array Проверяемый массив.
		 * @return bool Возвращает TRUE, если массив ассоциативный, иначе FALSE.
		 *
		 * @example
		 * <code>
		 * Arr::isAssoc(['a' => 1, 'b' => 2]); // true
		 * Arr::isAssoc([1, 2, 3]); // false
		 * </code>
		 */
		public static function isAssoc(array $array)
		{
			return (array_values($array) !== $array);
		}

		/**
		 * Получить значение из массива по ключу.
		 *
		 * Метод позволяет получить значение из массива, в том числе вложенного,
		 * используя "точечную" нотацию. Если ключ не найден, возвращается значение по умолчанию.
		 *
		 * @param \ArrayAccess|array $array Массив для поиска.
		 * @param string|int|null $key Ключ для поиска. Может быть строкой или null.
		 * @param mixed $default Значение, возвращаемое, если ключ не найден.
		 * @param string|null $filter Фильтр для обработки полученного значения ('str', 'int', 'float', 'bool', 'trim').
		 * @return mixed Значение из массива или значение по умолчанию.
		 *
		 * @example
		 * <code>
		 * $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];
		 * $name = Arr::get($data, 'user.name'); // 'John'
		 * $role = Arr::get($data, 'user.role', 'guest'); // 'guest'
		 * </code>
		 */
		public static function get($array, $key, $default = null, $filter = null)
		{
			if (! self::is($array)) {
				return self::filter(self::resolveValue($default), $filter);
			}

			if (is_null($key)) {
				return self::filter($array, $filter);
			}

			if (self::exists($array, $key)) {
				return self::filter($array[$key], $filter);
			}

			if (strpos($key, '.') === false) {
				return self::filter($array[$key] ?? self::resolveValue($default), $filter);
			}

			foreach (explode('.', $key) as $segment) {
				if (self::is($array) && self::exists($array, $segment)) {
					$array = $array[$segment];
				} else {
					return self::filter(self::resolveValue($default), $filter);
				}
			}

			return self::filter($array, $filter);
		}

		/**
		 * Добавляет элемент в массив по ключу, если он не существует.
		 *
		 * Метод использует "точечную" нотацию для вложенных ключей. Если элемент
		 * с таким ключом уже существует, массив не изменяется.
		 *
		 * @param array $array Массив, в который добавляется элемент.
		 * @param string $key Ключ, по которому будет добавлен элемент.
		 * @param mixed $value Значение для добавления.
		 * @return array Возвращает измененный массив.
		 *
		 * @example
		 * <code>
		 * $array = ['product' => ['name' => 'Desk']];
		 * Arr::add($array, 'product.price', 100);
		 * // $array теперь: ['product' => ['name' => 'Desk', 'price' => 100]]
		 * </code>
		 */
		public static function add($array, $key, $value)
		{
			if (is_null(self::get($array, $key))) {
				self::set($array, $key, $value);
			}

			return $array;
		}

		/**
		 * Устанавливает значение в массиве по ключу.
		 *
		 * Метод использует "точечную" нотацию для установки значения во вложенных массивах.
		 * Если ключ равен null, будет заменен весь массив.
		 *
		 * @param array $array Массив, который изменяется (передается по ссылке).
		 * @param string|null $key Ключ для установки значения.
		 * @param mixed $value Новое значение.
		 * @return array Возвращает измененный массив.
		 *
		 * @example
		 * <code>
		 * $array = ['products' => ['desk' => ['price' => 100]]];
		 * Arr::set($array, 'products.desk.price', 200);
		 * // $array теперь: ['products' => ['desk' => ['price' => 200]]]
		 * </code>
		 */
		public static function set(&$array, $key, $value)
		{
			if (is_null($key)) {
				return $array = $value;
			}

			$keys = explode('.', $key);

			while (count($keys) > 1) {
				$key = array_shift($keys);

				if (! isset($array[$key]) || ! is_array($array[$key])) {
					$array[$key] = [];
				}

				$array = &$array[$key];
			}

			$array[array_shift($keys)] = $value;

			return $array;
		}

		/**
		 * Проверяет существование ключа в массиве.
		 *
		 * Обертка для `array_key_exists`, которая также работает с объектами,
		 * реализующими интерфейс `ArrayAccess`.
		 *
		 * @param \ArrayAccess|array $array Массив или объект для проверки.
		 * @param string|int $key Ключ для поиска.
		 * @return bool Возвращает TRUE, если ключ существует, иначе FALSE.
		 */
		public static function exists($array, $key)
		{
			if ($array instanceof ArrayAccess) {
				return $array->offsetExists($key);
			}

			return array_key_exists($key, $array);
		}

		/**
		 * Проверяет наличие одного или нескольких ключей в массиве.
		 *
		 * Метод использует "точечную" нотацию для проверки существования
		 * ключей во вложенных массивах.
		 *
		 * @param \ArrayAccess|array $array Массив для поиска.
		 * @param string|array $keys Ключ или массив ключей.
		 * @return bool Возвращает TRUE, если все указанные ключи существуют, иначе FALSE.
		 *
		 * @example
		 * <code>
		 * $array = ['product' => ['name' => 'Desk', 'price' => 100]];
		 * Arr::has($array, 'product.name'); // true
		 * Arr::has($array, ['product.name', 'product.price']); // true
		 * Arr::has($array, 'product.discount'); // false
		 * </code>
		 */
		public static function has($array, $keys)
		{
			if (is_null($keys)) {
				return false;
			}

			$keys = (array) $keys;

			if (! $array) {
				return false;
			}

			if ($keys === []) {
				return false;
			}

			foreach ($keys as $key) {
				$subKeyArray = $array;

				if (self::exists($array, $key)) {
					continue;
				}

				foreach (explode('.', $key) as $segment) {
					if (self::is($subKeyArray) && self::exists($subKeyArray, $segment)) {
						$subKeyArray = $subKeyArray[$segment];
					} else {
						return false;
					}
				}
			}

			return true;
		}

		/**
		 * Возвращает первый элемент массива, удовлетворяющий условию.
		 *
		 * Если callback-функция не передана, возвращает просто первый элемент массива.
		 *
		 * @param array $array Массив для поиска.
		 * @param callable|null $callback Условие в виде callback-функции.
		 * @param mixed $default Значение по умолчанию, если элемент не найден.
		 * @return mixed Найденный элемент или значение по умолчанию.
		 *
		 * @example
		 * <code>
		 * $array = [100, 200, 300];
		 * $first = Arr::first($array, function ($value) { return $value >= 150; }); // 200
		 * </code>
		 */
		public static function first($array, callable $callback = null, $default = null)
		{
			if (is_null($callback)) {
				if (empty($array)) {
					return self::resolveValue($default);
				}

				foreach ($array as $item) {
					return $item;
				}
			}

			foreach ($array as $key => $value) {
				if ($callback($value, $key)) {
					return $value;
				}
			}

			return self::resolveValue($default);
		}

		/**
		 * Возвращает последний элемент массива, удовлетворяющий условию.
		 *
		 * Если callback-функция не передана, возвращает просто последний элемент массива.
		 *
		 * @param array $array Массив для поиска.
		 * @param callable|null $callback Условие в виде callback-функции.
		 * @param mixed $default Значение по умолчанию, если элемент не найден.
		 * @return mixed Найденный элемент или значение по умолчанию.
		 *
		 * @example
		 * <code>
		 * $array = [100, 200, 300, 400];
		 * $last = Arr::last($array, function ($value) { return $value < 350; }); // 300
		 * </code>
		 */
		public static function last($array, callable $callback = null, $default = null)
		{
			if (is_null($callback)) {
				return empty($array)
					? self::resolveValue($default)
					: end($array);
			}

			return self::first(array_reverse($array, true), $callback, $default);
		}

		/**
		 * Преобразует многомерный массив в одномерный с "точечной" нотацией ключей.
		 *
		 * @param array $array Массив для преобразования.
		 * @param string $prepend Префикс для новых ключей.
		 * @return array Новый одномерный массив.
		 *
		 * @example
		 * <code>
		 * $array = ['user' => ['name' => 'John', 'age' => 30]];
		 * $dotted = Arr::dot($array);
		 * // ['user.name' => 'John', 'user.age' => 30]
		 * </code>
		 */
		public static function dot($array, $prepend = '')
		{
			$results = [];

			foreach ($array as $key => $value) {
				if (self::is($value) && ! empty($value)) {
					$results = array_merge($results, self::dot($value, $prepend . $key . '.'));
				} else {
					$results[$prepend . $key] = $value;
				}
			}

			return $results;
		}

		/**
		 * Конвертирует массив в объект (stdClass).
		 *
		 * @param array $array Массив для конвертации.
		 * @return \stdClass Конвертированный объект.
		 */
		public static function toObject($array)
		{
			if (is_array($array)) {
				$obj = new \StdClass();

				foreach ($array as $key => $val) {
					$obj->$key = $val;
				}
			} else {
				$obj = $array;
			}

			return $obj;
		}

		/**
		 * Рекурсивно конвертирует объект в массив.
		 *
		 * @param object|array $object Объект или массив для конвертации.
		 * @return array Конвертированный массив.
		 */
		public static function toArray($object)
		{
			$object = (array)$object;

			if ($object === []) {
				return $object;
			}

			foreach ($object as $key => &$value) {
				if ((is_object($value) || is_array($value))) {
					$object[$key] = self::toArray($value);
				}
			}

			return $object;
		}

		/**
		 * Конвертирует объект (в т.ч. SimpleXMLElement) в массив.
		 *
		 * Этот метод использует `json_encode`, а затем `json_decode` для преобразования.
		 *
		 * @param object $object Объект для конвертации.
		 * @return array Конвертированный массив.
		 */
		public static function objectToArray($object)
		{
			if (is_object($object)) {
				$object = json_decode(json_encode($object), true, 512);
			}

			return $object;
		}

		/**
		 * Сортирует многомерный массив по одному или нескольким ключам.
		 *
		 * @param array $array Массив для сортировки.
		 * @param string|array $key Ключ или массив ключей для сортировки.
		 * @param int $sort_flags Флаги сортировки PHP (например, SORT_REGULAR, SORT_NUMERIC).
		 * @param int $sort_way Направление сортировки (SORT_ASC или SORT_DESC).
		 * @return array Отсортированный массив.
		 */
		public static function multiSort($array, $key, $sort_flags = SORT_REGULAR, $sort_way = SORT_ASC)
		{
			if (! self::is($array) || count($array) === 0 || empty($key)) {
				return $array;
			}

			$dir = ($sort_way === SORT_DESC) ? -1 : 1;

			if (! self::is($key)) {
				// Одиночный ключ — asort с поддержкой sort_flags
				$mapping = [];
				foreach ($array as $k => $v) {
					$mapping[$k] = $v[$key];
				}

				if ($sort_way === SORT_DESC) {
					arsort($mapping, $sort_flags);
				} else {
					asort($mapping, $sort_flags);
				}

				$sorted = [];
				foreach ($mapping as $k => $v) {
					$sorted[] = $array[$k];
				}

				return $sorted;
			}

			// Множественные ключи — последовательная сортировка через usort
			$keys = $key;
			usort($array, function ($a, $b) use ($keys, $dir) {
				foreach ($keys as $k) {
					$av = isset($a[$k]) ? $a[$k] : null;
					$bv = isset($b[$k]) ? $b[$k] : null;
					$cmp = $av <=> $bv;
					if ($cmp !== 0) {
						return $cmp * $dir;
					}
				}

				return 0;
			});

			return $array;
		}

		/**
		 * Безопасно сериализует массив.
		 *
		 * Преобразует массив в строку и кодирует в Base64.
		 *
		 * @param array $array Массив для сериализации.
		 * @return string Сериализованная и закодированная в Base64 строка.
		 */
		public static function safeSerialize(array $array)
		{
			return base64_encode(serialize($array));
		}

		/**
		 * Безопасно десериализует строку в массив.
		 *
		 * Декодирует строку из Base64 и выполняет десериализацию,
		 * запрещая создание объектов.
		 *
		 * @param string $string Строка для десериализации.
		 * @return array|false Десериализованный массив или false в случае ошибки.
		 */
		public static function safeUnserialize(string $string)
		{
			return unserialize(base64_decode($string), ['allowed_classes' => false]);
		}

		/**
		 * Рекурсивно удаляет экранирующие слэши.
		 *
		 * Аналог `stripslashes`, но для многомерных массивов.
		 *
		 * @param array|string $array Массив или строка для обработки.
		 * @return array|string Обработанный массив или строка без слэшей.
		 */
		public static function stripslashesArray($array)
		{
			if (self::is($array)) {
				return array_map([__CLASS__, 'stripslashesArray'], $array);
			}

			return stripslashes($array);
		}

		/**
		 * Ищет элементы в массиве по заданному ключу и значению.
		 *
		 * @param array $array Массив, в котором ведется поиск.
		 * @param mixed $key Ключ для сравнения.
		 * @param mixed $value Значение для сравнения.
		 * @return array|int Возвращает массив найденных элементов или 0, если ничего не найдено.
		 */
		public static function find(array $array, $key, $value)
		{
			$result = [];

			if (self::is($array)) {
				foreach ($array as $val) {
					if ((is_object($val) ? ($val->$key == $value) : ($val[$key] == $value))) {
						$result[] = $val;
					}
				}
			}

			return (! empty($result)) ? $result : 0;
		}

		/**
		 * Ищет ключ элемента в массиве по значению другого ключа.
		 *
		 * @param mixed $value Значение для поиска.
		 * @param mixed $array Массив для поиска.
		 * @return bool|int|string Ключ найденного элемента или false, если не найдено.
		 */
		public static function searchForValue($value, $array)
		{
			if (is_array($array)) {
				return array_search($value, $array, true);
			}

			return false;
		}

		/**
		 * Ищет значение в массиве по ключу и значению другого поля.
		 *
		 * @param mixed $key Ключ для поиска.
		 * @param mixed $value Значение для поиска.
		 * @param mixed $return Имя ключа, значение которого нужно вернуть.
		 * @param \ArrayAccess|array $array Массив для поиска.
		 * @param bool $fullkey Если true, возвращает весь найденный элемент (массив/объект).
		 * @return bool|mixed Найденное значение, элемент или false.
		 */
		public static function searchForValueName($key, $value, $return, $array, bool $fullkey = false)
		{
			if (self::is($array)) {
				foreach ($array as $k => $val) {
					if (is_object($array)) {
						if ($val->$key === $value) {
							return $fullkey ? $val : $val->$return;
						}
					} elseif ($val[$key] === $value) {
						return $fullkey ? $val : $val[$return];
					}
				}
			}

			return false;
		}

		/**
		 * Подсчитывает количество элементов в массиве по ключу и значению.
		 *
		 * @param array $array Массив.
		 * @param string $key Ключ для проверки.
		 * @param string $value Значение для сравнения.
		 * @return int Количество совпадений.
		 */
		public static function countInArray(array $array, string $key, string $value)
		{
			$i = 0;
			if (self::is($array)) {
				foreach ($array as $k => $v) {
					if ((is_object($v) ? ($v->$key == $value) : ($v[$key] == $value))) {
						$i++;
					}
				}
			}

			return $i;
		}

		/**
		 * Сортирует массив по указанному полю или полям.
		 *
		 * @param array $data Массив данных для сортировки.
		 * @param string|array $field Поле или массив полей для сортировки.
		 * @return array Отсортированный массив с числовыми ключами.
		 *
		 * @example
		 * <code>
		 * $data = [['name' => 'John'], ['name' => 'Alex']];
		 * $sorted = Arr::sortArray($data, 'name');
		 * // $sorted[0] будет ['name' => 'Alex']
		 * </code>
		 */
		public static function sortArray($data, $field)
		{
			$field = (array)$field;

			uasort($data, static function ($a, $b) use ($field) {
				$retval = 0;
				foreach ($field as $fieldname) {
					if ($retval == 0) {
						$retval = strnatcmp($a[$fieldname], $b[$fieldname]);
					}
				}

				return $retval;
			});

			return array_values($data);
		}

		/**
		 * Сериализует массив.
		 *
		 * @param array $array Массив.
		 * @return string|false Сериализованная строка или false в случае ошибки.
		 */
		public static function arrayToSerial(array $array)
		{
			if (self::is($array)) {
				return serialize($array);
			}

			return false;
		}

		/**
		 * Десериализует строку в массив.
		 *
		 * @param string $string Сериализованная строка.
		 * @return mixed|false Массив или false в случае ошибки.
		 */
		public static function unserialToArray(string $string)
		{
			if ($string) {
				return unserialize($string, ['allowed_classes' => false]);
			}

			return false;
		}

		/**
		 * Применяет фильтр к значению для его типизации.
		 *
		 * @param mixed $value Значение для фильтрации.
		 * @param string|null $filter Тип фильтра ('str', 'int', 'float', 'bool', 'trim').
		 * @return mixed Отфильтрованное значение.
		 * @throws Exceptions Если указан неподдерживаемый тип фильтра.
		 */
		protected static function filter($value, $filter)
		{
			if (! is_null($filter)) {
				switch ($filter) {
					case 'str':
					case 'string':
					case 'strval':
						$value = is_scalar($value) ? (string)$value : '';
						break;
					case 'trim':
						$value = is_scalar($value) ? trim($value) : '';
						break;
					case 'int':
					case 'integer':
					case 'intval':
						$value = is_scalar($value) ? (int)$value : 0;
						break;
					case 'float':
					case 'floatval':
						$value = is_scalar($value) ? (float)$value : 0.0;
						break;
					case 'bool':
					case 'boolean':
					case 'boolval':
						$value = is_scalar($value) && (bool)$value;
						break;
					default:
						throw new Exceptions('Arr wrong \'%name\' filter name', ['%name' => $filter]);
				}
			}

			return $value;
		}

		/**
		 * Вычислить отложенное значение без зависимости от глобальных helpers.
		 *
		 * @param mixed $value
		 * @return mixed
		 */
		protected static function resolveValue($value)
		{
			return $value instanceof \Closure ? $value() : $value;
		}
	}
