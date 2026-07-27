<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/QueryExecutor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use DB_Result;
	use App\Helpers\Debug;

	/**
	 * Исполнитель SQL-запросов
	 */
	class QueryExecutor
	{
		/**
		 * Выполнить SQL-запрос через активный драйвер
		 * @param string $sql        Готовый SQL (параметры уже подставлены QueryBuilder)
		 * @param bool   $is_buffered true = MYSQLI_STORE_RESULT
		 * @return DB_Result|bool
		 */
		public static function query($sql, $is_buffered = true)
		{
			$collectProfile = QueryLogger::isCollecting();
			if ($collectProfile) {
				Debug::startTime('queryReal');
			}

			$driver = ConnectionManager::getDriver();
			if (!$driver) {
				$driver = ConnectionManager::connect();
			}

			$result = $driver->query($sql, $is_buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);

			$time = $collectProfile ? Debug::endTime('queryReal') : 0.0;

			QueryLogger::setLastQuery($sql);

			$caller = QueryLogger::needsCallerTrace() ? QueryLogger::getCaller() : array();
			QueryLogger::logQuery($sql, $time, $driver->affectedRows(), QueryCache::getTtl(), $caller);

			if (!$result) {
				ErrorHandler::typeError($sql);
				return new DB_Result(false);
			}

			if (is_object($result) && $result instanceof \mysqli_result) {
				return new DB_Result($result);
			}

			return $result;
		}
	}
