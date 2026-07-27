<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;
	use App\Helpers\Response;

	/**
	 * Базовый контроллер новой админки.
	 *
	 * Даёт единый рендер через Twig (с автоподмешиванием view-globals модулей),
	 * JSON-ответы и редиректы. Авторизация/аудит/пользователь подключаются в
	 * Фазе 1 (Auth), когда появится модуль доступа.
	 */
	abstract class Controller
	{
		/**
		 * Отрендерить Twig-шаблон в строку.
		 *
		 * @param string $template имя шаблона (напр. '@adminx/dashboard.twig')
		 * @param array  $data     переменные шаблона
		 * @return string
		 */
		protected function render($template, array $data = array())
		{
			$globals = ModuleManager::viewGlobals();
			$data = array_merge($globals, $data);

			return Twig::twig()->render($template, $data);
		}

		/**
		 * Отрендерить Twig-шаблон со статусом ответа.
		 */
		protected function renderStatus($template, array $data = array(), $status = 200)
		{
			Response::setStatus($status);
			return $this->render($template, $data);
		}

		/**
		 * Отрендерить partial-фрагмент без layout.
		 *
		 * Используется Ajax-слоем для обновления частей страницы. Сам шаблон должен
		 * быть partial-ready: без html/head/body и без подключения assets.
		 */
		protected function partial($template, array $data = array(), $status = 200)
		{
			Response::setStatus($status);
			return $this->render($template, $data);
		}

		/**
		 * JSON-ответ.
		 *
		 * @param mixed $data
		 * @param int   $status
		 * @return string
		 */
		protected function json($data, $status = 200)
		{
			Response::json($data, $status);
		}

		// ------------------------------------------------------------------ //
		//  Ajax-first контракт (единый формат ответа, см. migration-review)
		// ------------------------------------------------------------------ //

		/** Запрос ждёт JSON (Ajax action). */
		protected function wantsJson()
		{
			$accept = (string) Request::header('Accept', '');

			return strpos($accept, 'application/json') !== false
				|| Request::isAjax()
				|| Request::getStr('format') === 'json';
		}

		/** Запрос ждёт partial-фрагмент (без layout). */
		protected function wantsPartial()
		{
			if (Request::getBool('partial', false)) {
				return true;
			}

			$accept = (string) Request::header('Accept', '');
			return strpos($accept, 'text/vnd.partial+html') !== false;
		}

		/**
		 * Успешный Ajax-ответ единого формата.
		 * $extra может содержать data, html:{...}, redirect, message.
		 */
		protected function success($message = '', array $extra = array(), $status = 200)
		{
			$message = defined('ACP') && ACP ? Language::translateSource($message) : $message;
			$payload = array_merge(array(
				'success'  => true,
				'message'  => $message,
				'data'     => new \stdClass(),
				'html'     => new \stdClass(),
				'redirect' => null,
				'errors'   => new \stdClass(),
			), $extra);

			return $this->json($payload, $status);
		}

		/**
		 * Ошибка Ajax (по умолчанию 422 validation).
		 * $errors — map поле → сообщение.
		 */
		protected function error($message = '', array $errors = array(), $status = 422)
		{
			if (defined('ACP') && ACP) {
				$message = Language::translateSource($message);
				foreach ($errors as $field => $error) {
					if (is_string($error)) {
						$errors[$field] = Language::translateSource($error);
					}
				}
			}

			return $this->json(array(
				'success'  => false,
				'message'  => $message,
				'data'     => new \stdClass(),
				'html'     => new \stdClass(),
				'redirect' => null,
				'errors'   => empty($errors) ? new \stdClass() : $errors,
			), $status);
		}

		/**
		 * Проверить CSRF: заголовок X-CSRF-Token (Ajax) или поле _csrf (форма).
		 * Бросает RuntimeException(419) при несовпадении.
		 */
		protected function verifyCsrf()
		{
			$token = (string) Request::header('X-CSRF-Token', '');
			if ($token === '') {
				$token = Request::postStr('_csrf', '');
			}

			Session::verifyCsrf($token);
		}

		/**
		 * Проверить CSRF и вернуть готовый Ajax-ответ при ошибке.
		 *
		 * Удобно для action-методов:
		 * if (($err = $this->csrfGuard()) !== null) { return $err; }
		 */
		protected function csrfGuard()
		{
			try {
				$this->verifyCsrf();
			} catch (\RuntimeException $e) {
				return $this->error('Сессия устарела, обновите страницу', array(), 403);
			}

			return null;
		}

		/** Shared CSRF and permission guard for state-changing admin actions. */
		protected function guardPermission($permission, $additionalPermission = '')
		{
			if (($error = $this->csrfGuard()) !== null) {
				return $error;
			}

			if (!Permission::check((string) $permission)
				|| ($additionalPermission !== '' && !Permission::check((string) $additionalPermission))) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			return null;
		}

		/**
		 * Редирект и завершение запроса.
		 *
		 * @param string $url
		 * @return void
		 */
		protected function redirect($url)
		{
			Request::redirect($url);
		}

		/** Базовый префикс админки (для ссылок/редиректов). */
		protected function base()
		{
			return defined('ADMINX_BASE') ? ADMINX_BASE : '';
		}
	}
