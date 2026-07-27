<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Registration/EmailRegistrationGate.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Registration;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicAuthSettings;
	use App\Common\Twig;
	use App\Frontend\Auth\Feature;
	use App\Frontend\PublicMailer;

	class EmailRegistrationGate implements RegistrationGateInterface, CheckoutRegistrationGateInterface
	{
		public function code()
		{
			return 'email';
		}

		public function normalize($identifier)
		{
			return mb_strtolower(trim((string) $identifier));
		}

		public function valid($identifier)
		{
			return (bool) filter_var($identifier, FILTER_VALIDATE_EMAIL);
		}

		public function send(array $user, $token)
		{
			$url = rtrim(defined('HOST') ? HOST : '', '/') . Feature::url('verify') . '?token=' . rawurlencode((string) $token);
			$name = trim((string) $user['firstname']);
			$html = '<h2>Подтверждение регистрации</h2><p>'
				. ($name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ', ' : '')
				. 'подтвердите адрес электронной почты.</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Подтвердить email</a></p>';
			$sent = PublicMailer::send((string) $user['email'], $html, 'Подтверждение регистрации', '', '', 'text/html', array(), false, false);
			if ($sent !== true) { throw new \RuntimeException('Не удалось отправить письмо подтверждения.'); }
		}

		public function checkoutOptions()
		{
			$settings = PublicAuthSettings::all();
			return array(
				'enabled' => !empty($settings['checkout_registration_enabled']),
				'policy_url' => (string) $settings['checkout_policy_url'],
				'policy_label' => (string) $settings['checkout_policy_label'],
			);
		}

		public function sendCheckoutAccess(array $user, $token, array $context = array())
		{
			$settings = PublicAuthSettings::all();
			$ttl = max(300, (int) (isset($context['ttl']) ? $context['ttl'] : 3600));
			$url = rtrim(defined('HOST') ? HOST : '', '/') . Feature::url('reset') . '?token=' . rawurlencode((string) $token);
			$data = array_merge($context, array(
				'user' => $user,
				'access_url' => $url,
				'expires_hours' => max(1, (int) ceil($ttl / 3600)),
			));
			try {
				$html = Twig::twig()->createTemplate((string) $settings['checkout_access_template'], 'checkout_access')->render($data);
			} catch (\Throwable $e) {
				error_log('Checkout access email template: ' . $e->getMessage());
				$html = Twig::twig()->createTemplate(PublicAuthSettings::defaultCheckoutAccessTemplate(), 'checkout_access_default')->render($data);
			}

			$sent = PublicMailer::send((string) $user['email'], $html, (string) $settings['checkout_access_subject'], '', '', 'text/html', array(), false, false);
			if ($sent !== true) { throw new \RuntimeException('Не удалось отправить письмо с доступом к личному кабинету.'); }
		}
	}
