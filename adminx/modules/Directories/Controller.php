<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Directories/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Directories;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Content\Directories\DirectoryRepository;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_rubrics')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/Rubrics/assets/rubrics.css', 49);
			AdminAssets::addStyle($this->base() . '/modules/Directories/assets/directories.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Directories/assets/directories.js', 50);
			$query = Request::getStr('q', '');
			$directories = Model::all($query);
			$selectedId = max(0, Request::getInt('id', 0));
			$selected = $selectedId > 0 ? DirectoryRepository::find($selectedId) : null;
			if ($selected) {
				$selectedUsage = null;
				foreach ($directories as $directory) {
					if ((int) $directory['id'] === $selectedId) {
						$selectedUsage = (int) $directory['usage_count'];
						break;
					}
				}

				$selected['usage_count'] = $selectedUsage === null
					? DirectoryRepository::usageCount($selectedId)
					: $selectedUsage;
			}

			return $this->render('@directories/index.twig', array(
				'directories' => $directories,
				'selected' => $selected,
				'items' => $selected ? DirectoryRepository::items($selectedId) : array(),
				'stats' => Model::stats(),
				'schema_ready' => Model::schemaReady(),
				'filters' => array('q' => $query),
				'can_manage' => Permission::check('manage_rubrics'),
			));
		}

		public function store(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			return $this->saveDirectory(0, 'Справочник создан', 'directory.created');
		}

		public function update(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			return $this->saveDirectory(isset($params['id']) ? (int) $params['id'] : 0, 'Справочник сохранён', 'directory.updated');
		}

		public function delete(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				if (!DirectoryRepository::delete($id)) {
					return $this->error('Справочник не найден', array(), 404);
				}

				$this->audit('directory.deleted', $id);
				return $this->success('Справочник удалён', array(
					'redirect' => $this->base() . '/directories',
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function storeItem(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			return $this->saveItem(isset($params['id']) ? (int) $params['id'] : 0, 0, 'Значение добавлено', 'directory.item_created');
		}

		public function updateItem(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			return $this->saveItem(
				isset($params['id']) ? (int) $params['id'] : 0,
				isset($params['item']) ? (int) $params['item'] : 0,
				'Значение сохранено',
				'directory.item_updated'
			);
		}

		public function deleteItem(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			$directoryId = isset($params['id']) ? (int) $params['id'] : 0;
			$itemId = isset($params['item']) ? (int) $params['item'] : 0;
			if (!DirectoryRepository::deleteItem($directoryId, $itemId)) {
				return $this->error('Значение не найдено', array(), 404);
			}

			$this->audit('directory.item_deleted', $directoryId, array('item_id' => $itemId));
			return $this->success('Значение удалено', array(
				'redirect' => $this->base() . '/directories?id=' . $directoryId,
			));
		}

		protected function saveDirectory($id, $message, $action)
		{
			try {
				$directory = DirectoryRepository::save($id, Request::postAll());
				$this->audit($action, $directory['id'], array('code' => $directory['code']));
				return $this->success($message, array(
					'data' => $directory,
					'redirect' => $this->base() . '/directories?id=' . $directory['id'],
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		protected function saveItem($directoryId, $itemId, $message, $action)
		{
			try {
				$item = DirectoryRepository::saveItem($directoryId, $itemId, Request::postAll());
				$this->audit($action, $directoryId, array('item_id' => $item['id'], 'key' => $item['item_key']));
				return $this->success($message, array(
					'data' => $item,
					'redirect' => $this->base() . '/directories?id=' . $directoryId,
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		protected function manageGuard()
		{
			return $this->guardPermission('manage_rubrics');
		}

		protected function audit($action, $directoryId, array $meta = array())
		{
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'target_type' => 'directory',
				'target_id' => (int) $directoryId,
				'meta' => $meta,
			));
		}
	}
