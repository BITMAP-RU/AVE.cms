<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/BulkActionExecutor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ErrorReport;

	/**
	 * Единый безопасный цикл массовых административных действий.
	 *
	 * Контроллер по-прежнему отвечает за CSRF, права и аудит, а executor
	 * нормализует ID, ограничивает размер пачки и изолирует ошибку одной записи.
	 */
	class BulkActionExecutor
	{
		public static function execute($action, $rawIds, array $actions, $limit = 200)
		{
			$action = trim((string) $action);
			if ($action === '' || empty($actions[$action]) || !is_callable($actions[$action])) {
				throw new \InvalidArgumentException('Неизвестное массовое действие');
			}

			$ids = self::normalizeIds($rawIds, $limit);
			$result = array(
				'action' => $action,
				'requested' => count($ids),
				'done' => 0,
				'skipped' => 0,
				'errors' => array(),
			);

			foreach ($ids as $id) {
				try {
					$outcome = call_user_func($actions[$action], $id);
					if ($outcome === false || (is_array($outcome) && isset($outcome['done']) && !$outcome['done'])) {
						$result['skipped']++;
						continue;
					}

					$result['done']++;
				} catch (\Throwable $e) {
					$result['errors'][] = '#' . $id . ': ' . ErrorReport::publicMessage(
						'Не удалось обработать запись',
						$e,
						'BULK',
						array('action' => $action, 'id' => $id)
					);
				}
			}

			return $result;
		}

		public static function normalizeIds($rawIds, $limit = 200)
		{
			$limit = max(1, min(1000, (int) $limit));
			if (is_string($rawIds)) {
				$rawIds = trim($rawIds);
				$decoded = $rawIds !== '' && $rawIds[0] === '[' ? json_decode($rawIds, true) : null;
				$rawIds = is_array($decoded) ? $decoded : preg_split('/[\s,;]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY);
			}

			$ids = array();
			foreach (is_array($rawIds) ? $rawIds : array() as $rawId) {
				$id = (int) $rawId;
				if ($id > 0) {
					$ids[$id] = $id;
				}
			}

			$ids = array_values($ids);
			if (!$ids || count($ids) > $limit) {
				throw new \InvalidArgumentException('Выберите от 1 до ' . $limit . ' записей');
			}

			return $ids;
		}
	}
