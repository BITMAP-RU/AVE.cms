<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Themes/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Themes;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Frontend\ThemeAssets;
	use App\Frontend\ThemeSettings;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			CodeEditor::useCodeMirror('text/css');
			CodeEditor::useCodeMirror('text/javascript');
			CodeEditor::useCodeMirror('application/json');

			$themes = Model::themes();
			$theme = Request::getStr('theme', '');
			if ($theme === '' && !empty($themes)) { $theme = $themes[0]['code']; }
			$directory = Request::getStr('dir', '');
			$browser = $theme !== '' ? Model::directory($theme, $directory) : null;
			$stats = array('themes' => count($themes), 'files' => 0, 'size' => '0 Б', 'connections' => 0);
			if ($browser) {
				$stats['files'] = $browser['theme']['files_count'];
				$stats['size'] = $browser['theme']['size_label'];
				$stats['connections'] = $browser['theme']['styles_count'] + $browser['theme']['scripts_count'];
			}

			return $this->render('@themes/index.twig', array(
				'themes' => $themes,
				'selected_theme' => $theme,
				'browser' => $browser,
				'manifest' => $browser ? $browser['theme']['manifest'] : ThemeAssets::normalizeManifest(array(), ''),
				'theme_settings_group' => $browser ? ThemeSettings::group($theme) : array(),
				'theme_settings_fields' => $browser ? ThemeSettings::fields($theme) : array(),
				'available_assets' => $browser ? Model::availableAssets($theme) : array('styles' => array(), 'scripts' => array()),
				'revisions' => $browser ? Revisions::listing($theme) : array(),
				'stats' => $stats,
				'can_manage' => Permission::check('manage_themes'),
			));
		}

		public function file(array $params = array())
		{
			try { return $this->success('', array('data' => Model::file(Request::getStr('theme'), Request::getStr('path')))); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 404); }
		}

		public function saveFile(array $params = array())
		{
			return $this->mutate('theme.asset_saved', function () {
				$item = Model::saveFile(Request::postStr('theme'), Request::postStr('path'), Request::postStr('content'), Auth::id());
				return array('message' => 'Файл сохранён', 'data' => $item, 'meta' => array('theme' => $item['theme'], 'path' => $item['path']));
			});
		}

		public function createFile(array $params = array())
		{
			return $this->mutate('theme.asset_created', function () {
				$item = Model::createFile(Request::postStr('theme'), Request::postStr('directory'), Request::postStr('name'), Auth::id());
				return array('message' => 'Файл создан', 'data' => $item, 'reload' => true, 'meta' => array('theme' => $item['theme'], 'path' => $item['path']));
			});
		}

		public function createFolder(array $params = array())
		{
			return $this->mutate('theme.folder_created', function () {
				$theme = Request::postStr('theme');
				$name = Request::postStr('name');
				Model::createDirectory($theme, Request::postStr('directory'), $name);
				return array('message' => 'Каталог создан', 'reload' => true, 'meta' => array('theme' => $theme, 'name' => $name));
			});
		}

		public function upload(array $params = array())
		{
			return $this->mutate('theme.assets_uploaded', function () {
				$theme = Request::postStr('theme');
				$result = Model::upload($theme, Request::postStr('directory'), isset($_FILES['files']) ? $_FILES['files'] : array(), Auth::id());
				$message = count($result['uploaded']) . ' файл(ов) загружено';
				if ($result['errors']) { $message .= '. Ошибки: ' . implode('; ', $result['errors']); }
				return array('message' => $message, 'data' => $result, 'reload' => true, 'meta' => array('theme' => $theme, 'files' => $result['uploaded']));
			});
		}

		public function deletePath(array $params = array())
		{
			return $this->mutate('theme.asset_deleted', function () {
				$theme = Request::postStr('theme');
				$path = Request::postStr('path');
				Model::deletePath($theme, $path, Auth::id());
				return array('message' => 'Файл или каталог удалён', 'reload' => true, 'meta' => array('theme' => $theme, 'path' => $path));
			});
		}

		public function saveManifest(array $params = array())
		{
			return $this->mutate('theme.manifest_saved', function () {
				$theme = Request::postStr('theme');
				$manifest = Model::saveManifest($theme, Request::postAll(), Auth::id());
				return array('message' => 'Подключения темы сохранены', 'data' => $manifest, 'reload' => true, 'meta' => array('theme' => $theme));
			});
		}

		public function saveSettings(array $params = array())
		{
			return $this->mutate('theme.settings_saved', function () {
				$theme = Request::postStr('theme');
				$result = Model::saveSettings($theme, Request::postAll(), Auth::id());
				return array(
					'message' => 'Настройки темы сохранены',
					'data' => $result,
					'reload' => true,
					'meta' => array(
						'theme' => $theme,
						'presentation_mode' => $result['manifest']['presentation_mode'],
						'saved' => $result['saved'],
					),
				);
			});
		}

		public function createTheme(array $params = array())
		{
			return $this->mutate('theme.created', function () {
				$item = Model::createTheme(Request::postStr('code'), Request::postStr('name'), Auth::id());
				return array('message' => 'Тема создана', 'data' => $item, 'redirect' => $this->base() . '/themes?theme=' . rawurlencode($item['code']), 'meta' => array('theme' => $item['code']));
			});
		}

		public function activate(array $params = array())
		{
			return $this->mutate('theme.activated', function () {
				$item = Model::activate(Request::postStr('theme'));
				return array('message' => 'Тема активирована', 'data' => $item, 'reload' => true, 'meta' => array('theme' => $item['code']));
			});
		}

		public function export(array $params = array())
		{
			if (!Permission::check('view_themes')) { Response::forbidden(); return null; }
			try {
				$archive = Model::exportArchive(Request::getStr('theme'));
				register_shutdown_function(function () use ($archive) { @unlink($archive['path']); });
				Response::download($archive['path'], $archive['name'], 'application/zip');
				return null;
			} catch (\Throwable $e) {
				return $this->renderStatus('@adminx/error.twig', array('message' => $e->getMessage()), 422);
			}
		}

		public function import(array $params = array())
		{
			return $this->mutate('theme.imported', function () {
				$item = Model::importArchive(isset($_FILES['archive']) ? $_FILES['archive'] : array(), Request::postStr('code'), Auth::id());
				return array('message' => 'Тема импортирована', 'data' => $item, 'redirect' => $this->base() . '/themes?theme=' . rawurlencode($item['code']), 'meta' => array('theme' => $item['code']));
			});
		}

		public function deleteTheme(array $params = array())
		{
			return $this->mutate('theme.deleted', function () {
				$theme = Request::postStr('theme');
				Model::deleteTheme($theme);
				return array('message' => 'Тема удалена', 'redirect' => $this->base() . '/themes', 'meta' => array('theme' => $theme));
			});
		}

		public function revisions(array $params = array())
		{
			try { return $this->success('', array('data' => Revisions::listing(Request::getStr('theme'), Request::getStr('path')))); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function revision(array $params = array())
		{
			$item = Revisions::one(isset($params['id']) ? (int) $params['id'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Ревизия не найдена', array(), 404);
		}

		public function restoreRevision(array $params = array())
		{
			return $this->mutate('theme.revision_restored', function () use ($params) {
				$id = isset($params['id']) ? (int) $params['id'] : 0;
				$item = Revisions::restore($id, Auth::id());
				return array('message' => 'Ревизия восстановлена', 'data' => $item, 'reload' => true, 'meta' => array('revision_id' => $id, 'theme' => $item['theme'], 'path' => $item['path']));
			});
		}

		public function deleteRevision(array $params = array())
		{
			return $this->mutate('theme.revision_deleted', function () use ($params) {
				$id = isset($params['id']) ? (int) $params['id'] : 0;
				if (!Revisions::delete($id)) { throw new \RuntimeException('Ревизия не найдена'); }
				return array('message' => 'Ревизия удалена', 'reload' => true, 'meta' => array('revision_id' => $id));
			});
		}

		public function clearRevisions(array $params = array())
		{
			return $this->mutate('theme.revisions_cleared', function () {
				$theme = Request::postStr('theme');
				$path = Request::postStr('path');
				$count = $path !== '' ? Revisions::deleteForPath($theme, $path) : Revisions::deleteTheme($theme);
				return array('message' => 'Удалено ревизий: ' . (int) $count, 'reload' => true, 'meta' => array('theme' => $theme, 'path' => $path, 'count' => (int) $count));
			});
		}

		protected function mutate($action, callable $callback)
		{
			if (($error = $this->guardPermission('manage_themes')) !== null) { return $error; }
			try {
				$result = $callback();
				$this->audit($action, isset($result['meta']) ? $result['meta'] : array());
				$extra = array('data' => isset($result['data']) ? $result['data'] : new \stdClass());
				if (!empty($result['redirect'])) { $extra['redirect'] = $result['redirect']; }
				return $this->success(isset($result['message']) ? $result['message'] : 'Готово', $extra);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		protected function audit($action, array $meta)
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? $user['name'] : '',
				'target_type' => 'theme',
				'target_id' => null,
				'meta' => $meta,
			));
		}
	}
