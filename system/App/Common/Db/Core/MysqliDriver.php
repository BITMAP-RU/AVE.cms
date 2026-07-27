<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/MysqliDriver.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use mysqli;
	use Exception;
	use App\Helpers\Debug;

	/**
	 * MySQLi драйвер для работы с базой данных
	 * Обертка над расширением MySQLi с дополнительным функционалом
	 */
	class MysqliDriver
	{
		const SQL_MODE = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

		/** @var mysqli Экземпляр MySQLi */
		protected $mysqli;

		/** @var array Конфигурация подключения */
		protected $config;

		/**
		 * Конструктор драйвера
		 * @param array $config Конфигурация подключения (dbhost, dbuser, dbpass, dbname, dbport, dbsock, dbchar)
		 */
		public function __construct(array $config)
		{
			$this->config = $config;
			$this->connect();
		}

		/**
		 * Установить соединение с MySQL
		 * Выбирает базу данных, устанавливает кодировку и режимы SQL
		 * @throws Exception Если не задан хост или сокет
		 * @throws Exception Если ошибка подключения к MySQL
		 */
		protected function connect()
		{
			$pro = $this->config;

			if (empty($pro['dbhost']) && empty($pro['dbsock'])) {
				throw new Exception('MySQL host or socket is not set');
			}

			$this->mysqli = new mysqli($pro['dbhost'], $pro['dbuser'], $pro['dbpass'], null, $pro['dbport'], $pro['dbsock']);

			if ($this->mysqli->connect_error) {
				throw new Exception('Connect Error ' . $this->mysqli->connect_errno . ': ' . $this->mysqli->connect_error, $this->mysqli->connect_errno);
			}

			if (!empty($pro['dbchar'])) {
				$this->mysqli->set_charset($pro['dbchar']);
			}

			if (!empty($pro['dbname'])) {
				$this->mysqli->select_db($pro['dbname']);
			}

			if (!$this->mysqli->query("SET SESSION sql_mode = '" . self::SQL_MODE . "'")) {
				throw new Exception('Unable to enable supported MySQL strict mode: ' . $this->mysqli->error);
			}

		}

		/**
		 * Переподключиться к MySQL при необходимости
		 * Проверяет соединение через ping, при провале пересоздает соединение
		 */
		public function reconnect()
		{
			if (!$this->ping()) {
				$this->close();
				$this->connect();
			}
		}

		/**
		 * Получить экземпляр MySQLi
		 * @return mysqli Экземпляр MySQLi
		 */
		public function getMysqli(): mysqli
		{
			return $this->mysqli;
		}

		/**
		 * Выполнить SQL-запрос
		 * @param string $query SQL-запрос
		 * @param int $resultmode Режим результата (MYSQLI_STORE_RESULT или MYSQLI_USE_RESULT)
		 * @return mysqli_result|bool Результат запроса
		 */
		public function query($query, $resultmode = MYSQLI_STORE_RESULT)
		{
			return @mysqli_query($this->mysqli, $query, $resultmode);
		}

		/**
		 * Получить результат с использованием use_result
		 * @return mysqli_result Результат запроса
		 */
		public function useResult()
		{
			return $this->mysqli->use_result();
		}

		/**
		 * Проверить наличие еще результатов
		 * @return bool true если есть еще результаты
		 */
		public function moreResults()
		{
			return $this->mysqli->more_results();
		}

		/**
		 * Перейти к следующему результату
		 * @return bool true если следующий результат доступен
		 */
		public function nextResult()
		{
			return $this->mysqli->next_result();
		}

		/**
		 * Получить последний вставленный ID
		 * @return int ID последней вставленной записи
		 */
		public function insertId()
		{
			return $this->mysqli->insert_id;
		}

		/**
		 * Получить количество затронутых строк
		 * @return int Количество строк
		 */
		public function affectedRows()
		{
			return $this->mysqli->affected_rows;
		}

		/**
		 * Получить последнюю ошибку MySQL
		 * @return string Сообщение об ошибке
		 */
		public function error()
		{
			return $this->mysqli->error;
		}

		/**
		 * Получить код последней ошибки
		 * @return int Код ошибки
		 */
		public function errno()
		{
			return $this->mysqli->errno;
		}

		/**
		 * Экранировать строку для использования в SQL
		 * @param string $string Строка для экранирования
		 * @return string Экранированная строка
		 */
		public function escape($string)
		{
			return $this->mysqli->real_escape_string($string);
		}

		/**
		 * Проверить соединение с сервером
		 * @return bool true если соединение активно
		 */
		public function ping()
		{
			return $this->mysqli->ping();
		}

		/**
		 * Закрыть соединение с MySQL
		 * @return bool true если соединение закрыто
		 */
		public function close()
		{
			return $this->mysqli->close();
		}

		/**
		 * Включить/выключить автокоммит
		 * @param bool $mode true для автокоммита, false для транзакций
		 * @return bool true если успешно
		 */
		public function autocommit($mode)
		{
			return $this->mysqli->autocommit($mode);
		}

		/**
		 * Подтвердить транзакцию
		 * @return bool true если коммит успешен
		 */
		public function commit()
		{
			return $this->mysqli->commit();
		}

		/**
		 * Откатить транзакцию
		 * @return bool true если откат успешен
		 */
		public function rollback()
		{
			return $this->mysqli->rollback();
		}

		/**
		 * Получить информацию о версии сервера
		 * @return string Версия MySQL
		 */
		public function getServerInfo()
		{
			return $this->mysqli->server_info;
		}
	}
