<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/DB_Result.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	if (!defined("BASEPATH")) {
		die('Direct access to this location is not allowed.');
	}



	use App\Helpers\Arr;


	class DB_Result implements IteratorAggregate, Countable
	{
		/*
		|----------------------------------------------------------------------------------
		| Конечный результат выполнения запроса
		|----------------------------------------------------------------------------------
		|
		| @var \mysqli_result|array
		|
		*/
		private $_result;


		/*
		|----------------------------------------------------------------------------------
		| Конструктор, возвращает объект с указателем на результат выполнения SQL-запроса
		|----------------------------------------------------------------------------------
		|
		| @param \mysqli_result|array $_result
		| @return \DB_Result object
		|
		*/
		public function __construct($_result)
		{
			$this->_result = $_result;
		}

		/**
		 * Get Iterator for foreach loops
		 * @return Traversable
		 */
		public function getIterator()
		{
			if (is_array($this->_result)) {
				return new ArrayIterator($this->_result);
			}

			return $this->_result;
		}

		/**
		 * Countable interface
		 * @return int
		 */
		public function count()
		{
			return $this->numRows();
		}

		/**
		|----------------------------------------------------------------------------------
		| Возвращает объект результата _result
		|----------------------------------------------------------------------------------
		|
		| @return \mysqli_result|array
		|
		 */
		public function getResult()
		{
			return $this->_result;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает как ассоциативный, так и численный массив.
		|----------------------------------------------------------------------------------
		|
		| @return array|bool
		|
		*/
		public function getMixed()
		{
			if (is_array($this->_result)) {
				$a = current($this->_result);
				next($this->_result);

				if (!is_array($a)) {
					return false;
				}

				// Merge numeric keys with string keys
				return array_merge(array_values($a), $a);
			}

			return ($this->_result instanceof \mysqli_result) ? $this->_result->fetch_array() : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает численный массив.
		|----------------------------------------------------------------------------------
		|
		| @return array|bool
		|
		*/
		public function getArray()
		{
			if (is_array($this->_result)) {
				$a = current($this->_result);
				next($this->_result);
				if (is_array($a)) {
					return array_values($a);
				}

				return false;
			}

			return ($this->_result instanceof \mysqli_result) ? $this->_result->fetch_row() : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает только ассоциативный массив.
		|----------------------------------------------------------------------------------
		|
		| @return array|bool
		|
		*/
		public function getAssoc()
		{
			if (is_array($this->_result)) {
				$a = current($this->_result);
				next($this->_result);
				return $a;
			}

			return ($this->_result instanceof \mysqli_result) ? $this->_result->fetch_assoc() : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает данные в виде объекта.
		|----------------------------------------------------------------------------------
		|
		| @return object|bool
		|
		*/
		public function getObject()
		{
			if (is_array($this->_result)) {
				$a = $this->getAssoc();
				return $a ? Arr::toObject($a) : false;
			}

			return ($this->_result instanceof \mysqli_result) ? $this->_result->fetch_object() : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает данные в виде строки.
		|----------------------------------------------------------------------------------
		|
		| @return string|mixed|null
		|
		*/
		public function getOne()
		{
			if (is_array($this->_result) && isset($this->_result[0])) {
				// First row, first column
				$row = $this->_result[0];
				return is_array($row) ? current($row) : $row;
			}

			if ($this->_result instanceof \mysqli_result) {
				// Reset to 0 just in case
				$this->dataSeek(0);
				$row = $this->_result->fetch_row();
				return $row ? $row[0] : null;
			}

			// If result itself is the value (edge case)
			if ($this->_result && !is_array($this->_result) && !is_object($this->_result)) {
				return $this->_result;
			}

			return null;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для обработки результата запроса.
		| Возвращает данные в виде строки.
		|----------------------------------------------------------------------------------
		|
		| @return mixed
		|
		*/
		public function getValue()
		{
			if (is_array($this->_result)) {
				$a = current($this->_result);
				next($this->_result);

				if (is_array($a)) {
					return current($a);
				}

				return false;
			}

			if ($this->_result instanceof \mysqli_result) {
				$row = $this->_result->fetch_row();
				return $row ? $row[0] : false;
			}

			return false;
		}


		/*
		|----------------------------------------------------------------------------------
		|  Метод, предназначенный для обработки результата запроса.
		|  Возвращает полный ассоциативный массив.
		|----------------------------------------------------------------------------------
		|
		| @return array|bool
		|
		*/
		public function getAll()
		{
			if (!$this->_result) {
				return false;
			}

			if (is_array($this->_result)) {
				// Return full array regardless of pointer? Usually getAll implies full fetch.
				return $this->_result;
			}

			// Rewind if possible
			$this->dataSeek(0);

			// Fetch all
			$array = [];
			if (method_exists($this->_result, 'fetch_all')) {
				$array = $this->_result->fetch_all(MYSQLI_ASSOC);
			} else {
				while ($row = $this->_result->fetch_assoc()) {
					$array[] = $row;
				}
			}

			return $array;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для перемещения внутреннего указателя в результате запроса
		|----------------------------------------------------------------------------------
		|
		| @param int $id - номер ряда результатов запроса
		| @return bool
		|
		*/
		public function dataSeek($id = 0)
		{
			if (is_array($this->_result)) {
				reset($this->_result);
				for ($x = 0; $x < $id; $x++) {
					if (next($this->_result) === false)
						break;
				}

				return true;
			}

			return ($this->_result instanceof \mysqli_result) ? @mysqli_data_seek($this->_result, $id) : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для получения количества рядов результата запроса
		|----------------------------------------------------------------------------------
		|
		| @return int
		|
		*/
		public function numRows()
		{
			if (is_array($this->_result)) {
				return count($this->_result);
			}

			return ($this->_result instanceof \mysqli_result) ? (int) $this->_result->num_rows : 0;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для получения количества полей результата запроса
		|----------------------------------------------------------------------------------
		|
		| @return int
		|
		*/
		public function numFields()
		{
			if (is_array($this->_result)) {
				$a = current($this->_result);
				return is_array($a) ? count($a) : 0;
			}

			return ($this->_result instanceof \mysqli_result) ? (int) $this->_result->field_count : 0;
		}


		public function numAllRows($result): int
		{
			if (!$result instanceof \mysqli_result) {
				return 0;
			}

			// For unbuffered results, count manually
			$count = 0;
			while ($result->fetch_assoc()) {
				$count++;
			}

			return $count;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для получения названия указанной колонки результата запроса
		|----------------------------------------------------------------------------------
		|
		| @param int $i - индекс колонки
		| @return array|bool
		|
		*/
		public function fetchFields()
		{
			return ($this->_result instanceof \mysqli_result) ? $this->_result->fetch_fields() : false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для получения названия указанной колонки результата запроса
		|----------------------------------------------------------------------------------
		|
		| @param int $i - индекс колонки
		| @return string|bool
		|
		*/
		public function fieldName($i)
		{
			if (is_array($this->_result)) {
				// Reset to get first row keys?
				$keys = array_keys(current($this->_result));
				return isset($keys[$i]) ? $keys[$i] : false;
			}

			if ($this->_result instanceof \mysqli_result) {
				$this->_result->field_seek($i);
				$field = $this->_result->fetch_field();
				return $field ? $field->name : false;
			}

			return false;
		}


		/*
		|----------------------------------------------------------------------------------
		| Метод, предназначенный для освобождения памяти от результата запроса
		|----------------------------------------------------------------------------------
		|
		| @return bool
		|
		*/
		public function Close()
		{
			if ($this->_result instanceof \mysqli_result) {
				$this->_result->free();
			}

			$this->_result = null;

			return true;
		}


		/*
		|----------------------------------------------------------------------------------
		| Удаляем объект
		|----------------------------------------------------------------------------------
		|
		| @internal param $void
		| @return resource
		|
		*/
		public function __destruct()
		{
			$this->Close();
		}
	}
