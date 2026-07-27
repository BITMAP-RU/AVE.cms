<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/TransactionManager.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	/**
	 * Менеджер транзакций
	 * Управляет началом, подтверждением и откатом транзакций
	 * Поддерживает вложенные транзакции (условно)
	 */
	class TransactionManager
	{
		/** @var bool Находится ли сейчас в транзакции */
		public static $transaction_in_progress = false;

		/** @var bool Разрешены ли вложенные транзакции */
		public static $nested_transactions = false;

		/** @var int Счетчик вложенных транзакций */
		public static $nested_transactions_count = 0;

		/**
		 * Начать транзакцию
		 * При включенных вложенных транзакциях только увеличивает счетчик без коммита
		 */
		public static function startTransaction()
		{
			$driver = ConnectionManager::getDriver();
			if (!$driver)
				return;

			if (self::$nested_transactions && self::$transaction_in_progress) {
				self::$nested_transactions_count++;
				return;
			}

			$driver->autocommit(false);
			self::$transaction_in_progress = true;

			// We can't easily register shutdown function here in static context efficiently
			// without risking duplicates, but let's follow original pattern if possible
			// or rely on the Facade to preserve that behavior.
			// For now, we will handle the logic.
		}

		/**
		 * Подтвердить транзакцию
		 * @return bool|null Результат коммита или null если транзакция не начата
		 */
		public static function commit()
		{
			$driver = ConnectionManager::getDriver();
			if (!$driver)
				return false;

			if (self::$nested_transactions && self::$nested_transactions_count > 0) {
				self::$nested_transactions_count--;
				return true;
			}

			$result = $driver->commit();

			self::$transaction_in_progress = false;
			$driver->autocommit(true);

			return $result;
		}

		/**
		 * Откатить транзакцию
		 * @return bool|null Результат отката или null если транзакция не начата
		 */
		public static function rollback()
		{
			$driver = ConnectionManager::getDriver();
			if (!$driver)
				return false;

			if (self::$nested_transactions && self::$nested_transactions_count > 0) {
				self::$nested_transactions_count--;
				return true;
			}

			$result = $driver->rollback();

			self::$transaction_in_progress = false;
			$driver->autocommit(true);

			return $result;
		}

		/**
		 * Проверить статус транзакции и откатить при необходимости
		 * Используется как shutdown-функция для автоматического отката при ошибке
		 */
		public static function checkStatus()
		{
			if (!self::$transaction_in_progress) {
				return;
			}

			self::rollback();
		}
	}
