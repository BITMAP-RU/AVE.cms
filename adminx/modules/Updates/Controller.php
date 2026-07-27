<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Updates/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Updates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\CoreUpdate\PatchPackage;
	use App\Common\CoreUpdate\ReleaseState;
	use App\Common\CoreUpdate\UpdateJob;
	use App\Common\CoreUpdate\UpdateRepository;
	use App\Common\Permission;
	use App\Common\UploadPolicy;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Updates/assets/updates.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Updates/assets/updates.js', 50);
			$job = UpdateJob::latest();
			if ($job && in_array($job['status'], array('prepared', 'backed_up'), true)) { $job['preflight'] = UpdateJob::preflight($job); }
			return $this->render('@updates/index.twig', array(
				'current' => ReleaseState::installed(),
				'source' => ReleaseState::source(),
				'repository' => UpdateRepository::catalog(false),
				'repository_settings' => UpdateRepository::settings(),
				'official_repository' => UpdateRepository::officialSettings(),
				'job' => $job,
				'can_manage' => Permission::check('manage_core_updates'),
				'can_install' => Permission::check('install_core_updates'),
			));
		}

		public function settings(array $params = array())
		{
			if (($guard = $this->guard('manage_core_updates', 'install_core_updates')) !== null) { return $guard; }
			try {
				$settings = UpdateRepository::saveSettings(array('enabled' => Request::postBool('enabled', false), 'url' => Request::postStr('url', ''), 'public_key' => Request::postStr('public_key', '')));
				$this->audit('core_update.repository_settings', array('enabled' => $settings['enabled'], 'url' => $settings['url']));
				return $this->success('Источник обновлений сохранён', array('data' => array('settings' => $settings)));
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function officialSettings(array $params = array())
		{
			if (($guard = $this->guard('manage_core_updates', 'install_core_updates')) !== null) { return $guard; }
			try {
				$settings = UpdateRepository::restoreOfficial();
				$this->audit('core_update.repository_official_restored', array('url' => $settings['url']));
				return $this->success('Официальный источник обновлений восстановлен', array('data' => array('settings' => $settings)));
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function refresh(array $params = array())
		{
			if (($guard = $this->guard('view_core_updates')) !== null) { return $guard; }
			$catalog = UpdateRepository::catalog(true);
			return empty($catalog['error']) ? $this->success('Каталог обновлений проверен', array('data' => array('catalog' => $catalog))) : $this->error($catalog['error'], array(), 422);
		}

		public function upload(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try {
				$file = isset($_FILES['patch']) && is_array($_FILES['patch']) ? $_FILES['patch'] : array();
				if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || (int) $file['error'] !== UPLOAD_ERR_OK) { throw new \RuntimeException('Архив патча не загружен'); }
				UploadPolicy::assertAllowed($file['tmp_name'], isset($file['name']) ? $file['name'] : 'update.zip', isset($file['size']) ? $file['size'] : 0, '', 'core_update');
				if ((int) $file['size'] > PatchPackage::MAX_ARCHIVE_BYTES) { throw new \RuntimeException('Архив патча превышает 50 МБ'); }
				$key = UpdateRepository::settings()['public_key'];
				if ($key === '') { throw new \RuntimeException('Сначала сохраните публичный ключ обновлений'); }
				$job = UpdateJob::create($file['tmp_name'], $key, Auth::id());
				$this->audit('core_update.prepared', array('job_id' => $job['id'], 'patch_id' => $job['manifest']['id']));
				return $this->success('Патч проверен', array('data' => array('job' => $job)));
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function download(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try {
				$job = UpdateRepository::download(isset($params['id']) ? $params['id'] : '', Auth::id());
				$this->audit('core_update.downloaded', array('job_id' => $job['id'], 'patch_id' => $job['manifest']['id']));
				return $this->success('Патч загружен и проверен', array('data' => array('job' => $job)));
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function step(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try {
				$job = UpdateJob::step(isset($params['id']) ? $params['id'] : '');
				if ($job['status'] === 'completed') { $this->audit('core_update.completed', array('job_id' => $job['id'], 'patch_id' => $job['manifest']['id'], 'to' => $job['manifest']['to'])); }
				return $this->success($job['message'], array('data' => array('job' => $job)));
			} catch (\Throwable $e) { $this->audit('core_update.failed', array('job_id' => isset($params['id']) ? $params['id'] : '', 'error' => $e->getMessage())); return $this->error($e->getMessage(), array(), 422); }
		}

		public function rollback(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try { $job = UpdateJob::rollback(isset($params['id']) ? $params['id'] : ''); $this->audit('core_update.rolled_back', array('job_id' => $job['id'])); return $this->success($job['message'], array('data' => array('job' => $job))); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		public function discard(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try { $job = UpdateJob::discard(isset($params['id']) ? $params['id'] : ''); $this->audit('core_update.discarded', array('job_id' => $job['id'])); return $this->success($job['message'], array('data' => array('job' => $job))); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
		}

		protected function guard($permission = 'install_core_updates', $additional = '') { return $this->guardPermission($permission, $additional); }
		protected function audit($action, array $meta) { $user = Auth::user(); AuditLog::record($action, array('actor_id' => Auth::id(), 'actor_name' => $user && isset($user['name']) ? $user['name'] : '', 'target_type' => 'core_update', 'target_id' => null, 'meta' => $meta)); }
	}
