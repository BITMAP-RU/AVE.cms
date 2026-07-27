<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Blocks;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_blocks')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/Blocks/assets/blocks.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Blocks/assets/blocks.js', 50);
			CodeEditor::useRichEditor();

			$q = Request::getStr('q', '');
			$group = Request::getInt('group', 0);
			$state = Request::getStr('state', '');
			$state = in_array($state, array('', 'active', 'inactive', 'external', 'ajax', 'visual'), true) ? $state : '';
			$blocks = Model::all($q, $group, $state);
			$groups = Model::groups();

			return $this->render('@blocks/index.twig', array(
				'blocks' => $blocks,
				'block_groups' => Model::groupedBlocks($blocks, $groups),
				'groups' => $groups,
				'group_map' => Model::groupMap(),
				'stats' => Model::stats(),
				'filters' => array('q' => $q, 'group' => $group, 'state' => $state),
				'can_manage' => Permission::check('manage_blocks'),
				'can_manage_php' => Permission::check('manage_php_blocks'),
				'initial_tab' => Request::getStr('tab', '') === 'groups' ? 'groups' : 'blocks',
			));
		}

		public function show(array $params = array())
		{
			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			if (($err = $this->guardPhpItem($item)) !== null) { return $err; }

			return $this->success('', array('data' => $item));
		}

		public function aliasCheck(array $params = array())
		{
			if (!Permission::check('manage_blocks')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$alias = trim(Request::getStr('alias', ''));
			$id = Request::getInt('id', 0);
			$valid = true;
			$available = true;
			$message = 'Алиас свободен';

			if ($alias === '') {
				$valid = false;
				$available = false;
				$message = 'Укажите алиас';
			} elseif (preg_match('/^[A-Za-z0-9-_]{1,20}$/i', $alias) !== 1 || is_numeric($alias)) {
				$valid = false;
				$available = false;
				$message = 'Только A-Z, 0-9, дефис и подчёркивание, до 20 символов; не число';
			} elseif (Model::aliasExists($alias, $id)) {
				$available = false;
				$message = 'Такой алиас уже используется';
			}

			return $this->success($message, array(
				'data' => array(
					'alias' => $alias,
					'id' => $id,
					'valid' => $valid,
					'available' => $available,
				),
			));
		}

		public function store(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$input = Request::postAll();
			if (($err = $this->guardPhpInput($input)) !== null) { return $err; }
			$errors = $this->validate($input, 0);
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			$id = Model::save(0, $input, Auth::id());
			$this->audit('block.created', $id, array('editor' => $this->editor($input), 'php' => $this->editor($input) === 'php'));
			return $this->success('Системный блок создан', array(
				'data' => array('id' => $id),
				'redirect' => $this->base() . '/blocks',
			));
		}

		public function update(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$before = Model::one($id);
			if (!$before) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			if (($err = $this->guardPhpItem($before)) !== null) { return $err; }

			$input = Request::postAll();
			if (($err = $this->guardPhpInput($input)) !== null) { return $err; }
			$errors = $this->validate($input, $id);
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			Model::save($id, $input, Auth::id());
			$this->audit('block.updated', $id, array(
				'editor_before' => (string) $before['sysblock_editor'],
				'editor_after' => $this->editor($input),
				'code_changed' => !hash_equals(hash('sha256', (string) $before['sysblock_text']), hash('sha256', isset($input['sysblock_text']) ? (string) $input['sysblock_text'] : '')),
			));
			return $this->success('Системный блок сохранён', array('redirect' => $this->base() . '/blocks'));
		}

		public function copy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$source = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$source) { return $this->error('Системный блок не найден', array(), 404); }
			if (($err = $this->guardPhpItem($source)) !== null) { return $err; }
			try {
				$newId = Model::copy((int) $source['id'], Request::postStr('name', ''), Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('block.copied', $newId, array('source_id' => (int) $source['id'], 'editor' => (string) $source['sysblock_editor']));

			return $this->success('Копия создана', array('redirect' => $this->base() . '/blocks'));
		}

		public function revisions(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::one($id);
			if (!$item) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			if (($err = $this->guardPhpItem($item)) !== null) { return $err; }

			return $this->success('', array(
				'data' => array(
					'block' => array(
						'id' => $item['id'],
						'name' => $item['sysblock_name'],
						'alias' => $item['sysblock_alias'],
					),
					'revisions' => Revisions::listForBlock($id),
				),
			));
		}

		public function revision(array $params = array())
		{
			$revision = Revisions::one(isset($params['revision']) ? (int) $params['revision'] : 0);
			if (!$revision) {
				return $this->error('Ревизия не найдена', array(), 404);
			}

			if (($err = $this->guardPhpSnapshot(isset($revision['snapshot']) ? $revision['snapshot'] : array())) !== null) { return $err; }

			return $this->success('', array('data' => $revision));
		}

		public function restoreRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$revisionId = isset($params['revision']) ? (int) $params['revision'] : 0;
			$revision = Revisions::one($revisionId);
			if (!$revision) { return $this->error('Ревизия не найдена', array(), 404); }
			if (($err = $this->guardPhpSnapshot(isset($revision['snapshot']) ? $revision['snapshot'] : array())) !== null) { return $err; }
			try {
				$blockId = Revisions::restore($revisionId, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('block.revision_restored', $blockId, array('revision_id' => $revisionId));

			return $this->success('Блок восстановлен из ревизии', array(
				'data' => array('id' => $blockId),
				'redirect' => $this->base() . '/blocks',
			));
		}

		public function deleteRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$revisionId = isset($params['revision']) ? (int) $params['revision'] : 0;
			$revision = Revisions::one($revisionId);
			if (!$revision) { return $this->error('Ревизия не найдена', array(), 404); }
			if (($err = $this->guardPhpSnapshot(isset($revision['snapshot']) ? $revision['snapshot'] : array())) !== null) { return $err; }
			$blockId = Revisions::delete($revisionId);
			if (!$blockId) {
				return $this->error('Ревизия не найдена', array(), 404);
			}

			return $this->success('Ревизия удалена', array('data' => array('id' => $blockId)));
		}

		public function deleteRevisions(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::one($id);
			if (!$item) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			if (($err = $this->guardPhpItem($item)) !== null) { return $err; }

			$count = Revisions::deleteForBlock($id);
			return $this->success('Ревизии удалены', array('data' => array('id' => $id, 'count' => $count)));
		}

		public function lint(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$result = Syntax::check(Request::postStr('sysblock_text', ''), Request::postBool('sysblock_eval', false) ? '1' : '0');
			if (!$result['ok']) {
				return $this->error($result['message'], array('sysblock_text' => $result['output']), 422);
			}

			return $this->success($result['message'], array('data' => $result));
		}

		public function clearCache(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			Model::clearCache($item['id'], $item['sysblock_alias']);
			return $this->success('Кеш системного блока очищен');
		}

		public function destroy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::one($id);
			if (!$item) { return $this->error('Системный блок не найден', array(), 404); }
			if (($err = $this->guardPhpItem($item)) !== null) { return $err; }
			if (!Model::delete($id, Auth::id())) {
				return $this->error('Системный блок не найден', array(), 404);
			}

			$this->audit('block.deleted', $id, array('editor' => (string) $item['sysblock_editor']));

			return $this->success('Системный блок удалён');
		}

		public function group(array $params = array())
		{
			$item = Model::group(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Группа не найдена', array(), 404);
			}

			return $this->success('', array('data' => $item));
		}

		public function storeGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$id = Model::saveGroup(0, Request::postAll());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Группа создана', array('data' => array('id' => $id), 'redirect' => $this->base() . '/blocks'));
		}

		public function updateGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				Model::saveGroup(isset($params['id']) ? (int) $params['id'] : 0, Request::postAll());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Группа сохранена', array('redirect' => $this->base() . '/blocks'));
		}

		public function reorderGroups(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$order = Request::post('order', array());
				$ids = is_array($order) ? $order : array();
				$count = Model::reorderGroups($ids);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Порядок групп сохранён', array('data' => array('count' => $count)));
		}

		public function destroyGroup(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			if (!Model::deleteGroup(isset($params['id']) ? (int) $params['id'] : 0)) {
				return $this->error('Группа не найдена', array(), 404);
			}

			return $this->success('Группа удалена', array('redirect' => $this->base() . '/blocks'));
		}

		protected function guard()
		{
			return $this->guardPermission('manage_blocks');
		}

		protected function guardPhpInput(array $input)
		{
			return $this->editor($input) === 'php' && !Permission::check('manage_php_blocks')
				? $this->error('Недостаточно прав для изменения исполняемого PHP-блока', array(), 403)
				: null;
		}

		protected function guardPhpItem(array $item)
		{
			return isset($item['sysblock_editor']) && (string) $item['sysblock_editor'] === 'php'
				? $this->guardPhpInput(array('sysblock_editor' => 'php'))
				: null;
		}

		protected function guardPhpSnapshot($snapshot)
		{
			return is_array($snapshot) ? $this->guardPhpItem($snapshot) : null;
		}

		protected function editor(array $input)
		{
			$editor = isset($input['sysblock_editor']) ? (string) $input['sysblock_editor'] : '';
			return in_array($editor, array('php', 'html', 'rich', 'text'), true) ? $editor : 'html';
		}

		protected function audit($action, $targetId, array $meta)
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'block',
				'target_id' => (int) $targetId,
				'meta' => $meta,
			));
		}

		protected function validate(array $input, $id)
		{
			$errors = array();
			$name = trim(isset($input['sysblock_name']) ? (string) $input['sysblock_name'] : '');
			$alias = trim(isset($input['sysblock_alias']) ? (string) $input['sysblock_alias'] : '');

			if ($name === '') {
				$errors['sysblock_name'] = 'Укажите название';
			}

			$aliasState = $this->validateAlias($alias, (int) $id);
			if (!$aliasState['valid'] || !$aliasState['available']) {
				$errors['sysblock_alias'] = $aliasState['message'];
			}

			$syntax = Syntax::check(
				isset($input['sysblock_text']) ? (string) $input['sysblock_text'] : '',
				!empty($input['sysblock_eval']) ? '1' : '0'
			);
			if (!empty($syntax['checked']) && empty($syntax['ok'])) {
				$errors['sysblock_text'] = $syntax['line'] > 0
					? 'PHP-синтаксис: ошибка на строке ' . (int) $syntax['line']
					: 'PHP-синтаксис: ошибка в коде блока';
			}

			return $errors;
		}

		protected function validateAlias($alias, $id)
		{
			if ($alias === '') {
				return array('valid' => false, 'available' => false, 'message' => 'Укажите алиас');
			}

			if (preg_match('/^[A-Za-z0-9-_]{1,20}$/i', $alias) !== 1 || is_numeric($alias)) {
				return array('valid' => false, 'available' => false, 'message' => 'Только A-Z, 0-9, дефис и подчёркивание, до 20 символов; не число');
			}

			if (Model::aliasExists($alias, (int) $id)) {
				return array('valid' => true, 'available' => false, 'message' => 'Такой алиас уже используется');
			}

			return array('valid' => true, 'available' => true, 'message' => 'Алиас свободен');
		}
	}
