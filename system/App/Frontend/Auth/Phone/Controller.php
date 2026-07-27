<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Phone/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Phone;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\Session;
	use App\Helpers\Hooks;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller
	{
		public function requestCode(array $params = array())
		{
			if (Auth::publicCheck()) {
				return Response::jsonBody(array('success' => false, 'message' => 'Вы уже вошли в аккаунт.'), 409);
			}

			if (!$this->verifyCsrf()) {
				return Response::jsonBody(array('success' => false, 'message' => 'Сессия устарела. Обновите страницу.'), 403);
			}

			if (!$this->verifyProtection('phone_auth')) {
				return Response::jsonBody(array('success' => false, 'message' => 'Проверка защиты не пройдена.'), 422);
			}

			try {
				$result = (new PhoneAuthService())->request(
					Request::postStr('provider', ''),
					Request::postStr('phone', ''),
					Request::postBool('consent', false),
					Request::postStr('_return', '/')
				);
				return Response::jsonBody(array(
					'success' => true,
					'message' => 'Код отправлен на ' . $result['phone_mask'],
					'data' => $result,
				), 200);
			} catch (\InvalidArgumentException $e) {
				return Response::jsonBody(array('success' => false, 'message' => $e->getMessage()), 422);
			} catch (\Throwable $e) {
				error_log('Phone auth request: ' . $e->getMessage());
				return Response::jsonBody(array('success' => false, 'message' => 'Не удалось отправить код. Повторите позже.'), 503);
			}
		}

		public function verifyCode(array $params = array())
		{
			if (Auth::publicCheck()) {
				return Response::jsonBody(array('success' => true, 'redirect' => '/'), 200);
			}

			if (!$this->verifyCsrf()) {
				return Response::jsonBody(array('success' => false, 'message' => 'Сессия устарела. Обновите страницу.'), 403);
			}

			try {
				$result = (new PhoneAuthService())->verify(
					Request::postStr('provider', ''),
					Request::postStr('challenge', ''),
					Request::postStr('code', ''),
					Request::postBool('keep_in', false)
				);
				return Response::jsonBody(array(
					'success' => true,
					'message' => !empty($result['created']) ? 'Аккаунт создан.' : 'Вход выполнен.',
					'redirect' => $result['return_url'],
					'data' => array('created' => !empty($result['created'])),
				), 200);
			} catch (\InvalidArgumentException $e) {
				return Response::jsonBody(array('success' => false, 'message' => $e->getMessage()), 422);
			} catch (\Throwable $e) {
				error_log('Phone auth verify: ' . $e->getMessage());
				return Response::jsonBody(array('success' => false, 'message' => 'Не удалось завершить вход. Повторите позже.'), 500);
			}
		}

		protected function verifyCsrf()
		{
			try {
				Session::verifyCsrf(Request::postStr('_csrf', ''));
				return true;
			} catch (\Throwable $e) {
				return false;
			}
		}

		protected function verifyProtection($profile)
		{
			$result = Hooks::filter('public.form.protection.verify', array(
				'profile' => (string) $profile,
				'input' => Request::postAll(),
				'context' => array('ip' => Request::ip()),
				'allowed' => true,
				'handled' => false,
			));
			return !is_array($result) || empty($result['handled']) || !empty($result['allowed']);
		}
	}
