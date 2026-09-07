<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Security/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Security;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\IpBlocker;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_ip_blocks')) {
				Response::forbidden();
				return '';
			}

			$type = Request::getStr('type', 'ip') === 'user_agent' ? 'user_agent' : 'ip';
			$query = Request::getStr('q', '');
			AdminAssets::addStyle($this->base() . '/modules/Security/assets/security.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Security/assets/security.js', 50);
			return $this->render('@security/index.twig', array(
				'rows' => $type === 'user_agent' ? IpBlocker::allUserAgents($query) : IpBlocker::all($query),
				'q' => $query,
				'active_type' => $type,
				'current_ip' => Request::ip(),
				'can_manage' => Permission::check('manage_ip_blocks'),
			));
		}

		public function block(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try {
				$ip = Request::postStr('ip', '');
				$reason = Request::postStr('reason', '');
				$hours = Request::postInt('hours', 0);
				$expires = $hours > 0 ? time() + min($hours, 87600) * 3600 : null;
				$row = IpBlocker::block($ip, $reason, $expires, Auth::id());
				AuditLog::record('security.ip_blocked', array('actor_id' => Auth::id(), 'target_type' => 'ip', 'target_id' => $ip, 'meta' => array('reason' => $reason, 'expires_at' => $expires)));
				return $this->success('IP-адрес заблокирован', array('redirect' => $this->base() . '/security/ip-blocks', 'data' => array('block' => $row)));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function unblock(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			IpBlocker::unblock($id);
			AuditLog::record('security.ip_unblocked', array('actor_id' => Auth::id(), 'target_type' => 'ip_block', 'target_id' => $id));
			return $this->success('IP-адрес разблокирован', array('redirect' => $this->base() . '/security/ip-blocks'));
		}

		public function blockUserAgent(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			try {
				$pattern = Request::postStr('pattern', '');
				$matchType = Request::postStr('match_type', 'contains');
				$reason = Request::postStr('reason', '');
				$hours = Request::postInt('hours', 0);
				$expires = $hours > 0 ? time() + min($hours, 87600) * 3600 : null;
				$row = IpBlocker::blockUserAgent($pattern, $reason, $expires, Auth::id(), $matchType);
				AuditLog::record('security.user_agent_blocked', array(
					'actor_id' => Auth::id(), 'target_type' => 'user_agent', 'target_id' => $pattern,
					'meta' => array('reason' => $reason, 'expires_at' => $expires, 'match_type' => $matchType),
				));
				return $this->success('User-Agent заблокирован', array(
					'redirect' => $this->base() . '/security/ip-blocks?type=user_agent', 'data' => array('block' => $row),
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function unblockUserAgent(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			IpBlocker::unblockUserAgent($id);
			AuditLog::record('security.user_agent_unblocked', array(
				'actor_id' => Auth::id(), 'target_type' => 'user_agent_block', 'target_id' => $id,
			));
			return $this->success('User-Agent разблокирован', array(
				'redirect' => $this->base() . '/security/ip-blocks?type=user_agent',
			));
		}

		protected function guard()
		{
			return $this->guardPermission('manage_ip_blocks');
		}
	}
