<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationRevisionRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Revisions\JsonRevisionStore;

	class PresentationRevisionRepository
	{
		protected static $store;

		public static function all($presentationId, $limit = 100)
		{
			$rows = self::store()->listing($presentationId, $limit);
			return array_map(function ($row) { return self::format($row, false); }, $rows);
		}

		public static function one($id)
		{
			$row = self::store()->one($id);
			return $row ? self::format($row, true) : null;
		}

		public static function capture($presentationId, $action, $authorId, $comment)
		{
			$row = PresentationRepository::raw((int) $presentationId);
			if (!$row) { return 0; }
			return self::store()->capture(
				$presentationId,
				$action,
				PresentationRepository::snapshot($row),
				$authorId,
				$comment
			);
		}

		public static function restore($revisionId, $authorId)
		{
			$revision = self::one((int) $revisionId);
			if (!$revision || !$revision['snapshot']) { throw new \RuntimeException('Ревизия не найдена'); }
			$id = (int) $revision['presentation_id'];
			self::capture($id, 'restore_backup', $authorId, 'Снимок перед восстановлением');
			PresentationRepository::applySnapshot($id, $revision['snapshot'], $authorId);
			self::capture($id, 'restore', $authorId, 'Восстановлен черновик ревизии #' . (int) $revisionId);
			return $id;
		}

		public static function delete($id)
		{
			return self::store()->delete($id);
		}

		public static function deleteAll($presentationId)
		{
			return self::store()->deleteFor($presentationId);
		}

		protected static function format(array $row, $snapshot)
		{
			$row = self::store()->formatRow($row, $snapshot);
			return array(
				'id' => (int) $row['id'],
				'presentation_id' => (int) $row['presentation_id'],
				'action' => $row['action'],
				'action_label' => $row['action_label'],
				'badge' => $row['badge'],
				'comment' => (string) $row['comment'],
				'author_name' => (string) $row['author_name'],
				'created_label' => $row['created_label'],
				'snapshot' => $row['snapshot'],
			);
		}

		protected static function store()
		{
			if (!self::$store) {
				self::$store = new JsonRevisionStore(
					PresentationRepository::revisionsTable(),
					'presentation_id',
					array(
						'create' => array('Создание', 'badge-green'),
						'update' => array('Черновик', 'badge-blue'),
						'publish' => array('Публикация', 'badge-violet'),
						'restore' => array('Восстановление', 'badge-cyan'),
						'restore_backup' => array('Перед восстановлением', 'badge-gray'),
					)
				);
			}

			return self::$store;
		}
	}
