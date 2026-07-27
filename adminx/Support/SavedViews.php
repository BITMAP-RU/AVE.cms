<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/SavedViews.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use DB;

	/** Персональные именованные наборы фильтров для рабочих списков Adminx. */
	class SavedViews
	{
		const MAX_VIEWS = 20;

		public static function all($scope, $userId, array $allowedFields)
		{
			$scope = self::scope($scope);
			$userId = self::userId($userId);
			$rows = DB::query(
				'SELECT id,title,filters_json,updated_at FROM ' . self::table() . ' WHERE user_id=%i AND scope=%s ORDER BY title,id',
				$userId,
				$scope
			)->getAll();

			$result = array();
			foreach ((array) $rows as $row) {
				$id = is_array($row) && isset($row['id']) ? self::id($row['id']) : '';
				$title = is_array($row) && isset($row['title']) ? self::title($row['title']) : '';
				if ($id === '' || $title === '') {
					continue;
				}

				$filters = json_decode(isset($row['filters_json']) ? (string) $row['filters_json'] : '{}', true);
				$result[] = array(
					'id' => $id,
					'title' => $title,
					'filters' => self::filters(is_array($filters) ? $filters : array(), $allowedFields),
					'updated_at' => isset($row['updated_at']) ? (int) $row['updated_at'] : 0,
				);
			}

			return array_slice($result, 0, self::MAX_VIEWS);
		}

		public static function save($scope, $userId, $title, array $filters, array $allowedFields)
		{
			$title = self::title($title);
			if ($title === '') {
				throw new \InvalidArgumentException('Укажите название представления');
			}

			$scope = self::scope($scope);
			$userId = self::userId($userId);
			$rows = self::all($scope, $userId, $allowedFields);
			$normalized = self::filters($filters, $allowedFields);
			$matchedId = '';
			foreach ($rows as $row) {
				if (mb_strtolower($row['title'], 'UTF-8') !== mb_strtolower($title, 'UTF-8')) {
					continue;
				}

				$matchedId = (string) $row['id'];
				break;
			}

			$data = array(
				'title' => $title,
				'filters_json' => self::json($normalized),
				'updated_at' => time(),
			);
			if ($matchedId !== '') {
				DB::Update(self::table(), $data, 'id=%s AND user_id=%i AND scope=%s', $matchedId, $userId, $scope);
			} else {
				if (count($rows) >= self::MAX_VIEWS) {
					throw new \InvalidArgumentException('Можно сохранить не более ' . self::MAX_VIEWS . ' представлений');
				}

				DB::Insert(self::table(), array_merge($data, array(
					'id' => self::newId(),
					'user_id' => $userId,
					'scope' => $scope,
				)));
			}

			return self::all($scope, $userId, $allowedFields);
		}

		public static function delete($scope, $userId, $id, array $allowedFields)
		{
			$id = self::id($id);
			if ($id === '') {
				throw new \InvalidArgumentException('Представление не найдено');
			}

			$scope = self::scope($scope);
			$userId = self::userId($userId);
			$exists = (int) DB::query(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE id=%s AND user_id=%i AND scope=%s',
				$id,
				$userId,
				$scope
			)->getValue();
			if (!$exists) {
				throw new \InvalidArgumentException('Представление не найдено');
			}

			DB::Delete(self::table(), 'id=%s AND user_id=%i AND scope=%s', $id, $userId, $scope);
			return self::all($scope, $userId, $allowedFields);
		}

		protected static function filters($filters, array $allowedFields)
		{
			if (!is_array($filters)) {
				return array();
			}

			$allowed = array_fill_keys($allowedFields, true);
			$result = array();
			foreach ($filters as $name => $value) {
				$name = (string) $name;
				if (!isset($allowed[$name]) || is_array($value) || is_object($value)) {
					continue;
				}

				$value = trim((string) $value);
				if (mb_strlen($value, 'UTF-8') > 500) {
					$value = mb_substr($value, 0, 500, 'UTF-8');
				}

				$result[$name] = $value;
			}

			foreach ($allowedFields as $name) {
				if (!array_key_exists($name, $result)) {
					$result[$name] = '';
				}
			}

			return $result;
		}

		protected static function table()
		{
			return SystemTables::table('admin_saved_views');
		}

		protected static function scope($scope)
		{
			$scope = strtolower(trim((string) $scope));
			if (!preg_match('/^[a-z0-9_.-]{2,64}$/', $scope)) {
				throw new \InvalidArgumentException('Некорректная область представления');
			}

			return $scope;
		}

		protected static function userId($userId)
		{
			$userId = (int) $userId;
			if ($userId <= 0) {
				throw new \InvalidArgumentException('Пользователь не определён');
			}

			return $userId;
		}

		protected static function title($title)
		{
			$title = preg_replace('/\s+/u', ' ', trim((string) $title));
			return mb_substr($title, 0, 60, 'UTF-8');
		}

		protected static function id($id)
		{
			$id = strtolower(trim((string) $id));
			return preg_match('/^[a-f0-9]{12}$/', $id) ? $id : '';
		}

		protected static function newId()
		{
			try {
				return bin2hex(random_bytes(6));
			} catch (\Throwable $e) {
				return substr(sha1(uniqid('', true) . mt_rand()), 0, 12);
			}
		}

		protected static function json(array $value)
		{
			$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) {
				throw new \InvalidArgumentException('Не удалось подготовить фильтры');
			}

			return $json;
		}
	}
