<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/RouteGuard.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ModuleManager;
	use App\Common\Permission;
	use App\Common\Session;
	use App\Common\Settings;
	use App\Common\Twig;
	use App\Helpers\Request;
	use App\Helpers\Response;

	/** Enforces route permissions and CSRF before an Adminx controller is invoked. */
	class RouteGuard
	{
		public static function enforce(array $route, $isPublic = false)
		{
			$options = isset($route['options']) && is_array($route['options']) ? $route['options'] : array();
			try {
				SensitiveRoutes::validate($route);
			} catch (\InvalidArgumentException $e) {
				self::deny(500, 'Ошибка политики маршрута', $e->getMessage());
			}

			$permission = isset($options['permission']) ? trim((string) $options['permission']) : '';
			if (!$isPublic && $permission !== '' && !Permission::checkAcp($permission)) {
				self::deny(403, 'Доступ запрещён', 'У вашей роли нет права на просмотр этого раздела.');
			}

			$method = isset($route['method']) ? strtoupper((string) $route['method']) : Request::method();
			$csrfRequired = !isset($options['csrf']) || $options['csrf'] !== false;
			if ($csrfRequired && !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
				$token = (string) Request::header('X-CSRF-Token', '');
				if ($token === '') {
					$token = Request::postStr('_csrf', '');
				}

				try {
					Session::verifyCsrf($token);
				} catch (\Throwable $e) {
					self::deny(419, 'Сессия устарела', 'Обновите страницу и повторите действие.');
				}
			}

			if (!$isPublic && !empty($options['reauth']) && (bool) Settings::get('adminx_reauth_enabled', false)) {
				$config = is_array($options['reauth']) ? $options['reauth'] : array();
				$ttl = isset($config['ttl']) ? ReAuth::normalizeTtl($config['ttl']) : ReAuth::DEFAULT_TTL;
				if (!ReAuth::valid($ttl)) {
					$reason = isset($config['reason']) && trim((string) $config['reason']) !== ''
						? trim((string) $config['reason'])
						: 'Это действие изменяет исполняемый код системы.';
					self::deny(428, 'Подтвердите пароль', 'Для продолжения подтвердите пароль текущего пользователя.', array(
						'reauth_required' => true,
						'reauth_url' => (defined('ADMINX_BASE') ? ADMINX_BASE : '') . '/reauth',
						'reason' => $reason,
						'ttl' => $ttl,
					));
				}
			}
		}

		protected static function deny($status, $title, $message, array $data = array())
		{
			$accept = strtolower((string) Request::header('Accept', ''));
			if (Request::isAjax() || strpos($accept, 'application/json') !== false) {
				Response::json(array(
					'success' => false,
					'message' => (string) $message,
					'data' => empty($data) ? new \stdClass() : $data,
					'errors' => new \stdClass(),
				), (int) $status);
			}

			Response::setStatus((int) $status);
			echo Twig::twig()->render('@adminx/error.twig', array_merge(ModuleManager::viewGlobals(), array(
				'status' => (int) $status,
				'title' => (string) $title,
				'message' => (string) $message,
			)));
			Request::shutDown();
		}
	}
