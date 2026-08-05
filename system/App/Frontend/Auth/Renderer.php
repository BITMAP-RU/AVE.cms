<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Renderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\AdminLocation;
	use App\Common\PublicModuleRuntime;
	use App\Common\Session;
	use App\Common\Twig;
	use App\Frontend\Auth\OAuth\ProviderRegistry;
	use App\Frontend\Auth\Phone\ProviderRegistry as PhoneProviderRegistry;
	use App\Helpers\Hooks;
	use App\Helpers\Request;

	class Renderer
	{
		public static function panel($includeAssets = false, $error = '')
		{
			$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
			$user = Auth::publicUser();
			$systemUser = Auth::systemUser();
			$displayUser = $user ?: $systemUser;
			$accountLinks = Hooks::filter('auth.account.links', array());
			if (!is_array($accountLinks)) { $accountLinks = array(); }
			$html = (new FormTemplateRepository())->render('panel', array(
				'base' => $base,
				'csrf' => Session::csrfToken(),
				'return_url' => self::returnUrl(),
				'logged_in' => $displayUser !== null,
				'user_name' => $user
					? $user['name']
					: ($systemUser ? (!empty($systemUser['name']) ? $systemUser['name'] : $systemUser['email']) : ''),
				'system_identity' => $user === null && $systemUser !== null,
				'can_admin' => Auth::systemUserCan('admin_panel'),
				'admin_url' => AdminLocation::url('/'),
				'registration_enabled' => Feature::registrationFormEnabled(),
				'password_reset_enabled' => !empty(Feature::config()['password_reset_enabled']),
				'auth_urls' => Feature::urls(),
				'login_page' => Feature::page('login'),
				'oauth_providers' => ProviderRegistry::publicItems(),
				'phone_providers' => PhoneProviderRegistry::publicItems(),
				'account_extension_links' => $accountLinks,
				'error' => (string) $error,
			));

			if (!$includeAssets) {
				return $html;
			}

			return '<link rel="stylesheet" href="' . $base . '/system/App/Frontend/Auth/assets/auth.css">'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/auth.js" defer></script>'
				. $html;
		}

		public static function page($template, array $data = array())
		{
			$base = defined('ABS_PATH') ? rtrim((string) ABS_PATH, '/') : '';
			$page = Feature::page($template);
			$context = array_merge(array(
				'base' => $base,
				'csrf' => Session::csrfToken(),
				'auth_urls' => Feature::urls(),
				'page' => $page,
				'config' => Feature::config(),
				'registration_enabled' => Feature::registrationFormEnabled(),
				'preview' => false,
				'oauth_providers' => ProviderRegistry::publicItems(),
				'phone_providers' => PhoneProviderRegistry::publicItems(),
				'email_registration_enabled' => Feature::emailRegistrationEnabled(),
				'phone_registration_enabled' => Feature::phoneRegistrationEnabled(),
				'registration_phone_providers' => Feature::phoneRegistrationProviders(),
			), $data);
			$html = (new FormTemplateRepository())->render($template, $context);
			if ($template === 'register' && empty($context['email_registration_enabled'])
				&& preg_match('/name\s*=\s*["\']email["\']/i', $html)) {
				$html = Twig::twig()->render('@system_auth/register.twig', $context);
			}

			if ($template === 'register' && !empty($context['phone_registration_enabled'])
				&& !empty($context['registration_phone_providers']) && strpos($html, 'data-phone-auth') === false) {
				$phoneContext = $context;
				$phoneContext['phone_providers'] = $context['registration_phone_providers'];
				$phoneContext['phone_auth_registration'] = true;
				$phoneContext['return_url'] = Feature::url('profile');
				$html .= (new FormTemplateRepository())->render('phone', $phoneContext);
			}

			if ($template === 'login' && !empty($context['oauth_providers']) && strpos($html, 'data-auth-oauth') === false) {
				$html .= (new FormTemplateRepository())->render('oauth', $context);
			}

			if ($template === 'login' && !empty($context['phone_providers']) && strpos($html, 'data-phone-auth') === false) {
				$html .= (new FormTemplateRepository())->render('phone', $context);
			}

			if ($template === 'profile' && !empty($context['oauth_connections']) && strpos($html, 'data-auth-connections') === false) {
				$html .= (new FormTemplateRepository())->render('oauth_connections', $context);
			}

			if (empty($context['preview'])) {
				$html = self::withProtection($template, $html, $context);
			}

			if (!empty($context['preview'])) {
				$html = preg_replace('/<form\b/i', '<form data-auth-preview-form onsubmit="return false;"', $html);
				$html = '<div class="system-auth-preview"><b>Предпросмотр формы</b><span>Отправка данных отключена.</span></div>' . $html;
			}

			$html = '<link rel="stylesheet" href="' . $base . '/system/App/Frontend/Auth/assets/auth.css">'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/auth.js" defer></script>'
				. $html;
			PublicModuleRuntime::deferPage($html, array(
				'title' => isset($context['title']) ? (string) $context['title'] : (isset($page['title']) ? (string) $page['title'] : ''),
				'description' => isset($context['description']) ? (string) $context['description'] : (isset($page['description']) ? (string) $page['description'] : ''),
				'robots' => 'noindex,follow',
				'template_id' => isset($page['template_id']) ? (int) $page['template_id'] : 1,
			));
			return '';
		}

		protected static function returnUrl()
		{
			$query = Request::queryString();
			return Request::path() . ($query !== '' ? '?' . $query : '');
		}

		protected static function withProtection($template, $html, array $context)
		{
			$profiles = array(
				'login' => 'login',
				'register' => 'registration',
				'remember' => 'password_reset',
				'reset' => 'password_reset',
			);
			if (!isset($profiles[$template]) || stripos((string) $html, '</form>') === false) {
				return $html;
			}

			$protection = Hooks::filter('public.form.protection.render', array(
				'profile' => $profiles[$template],
				'html' => '',
				'context' => array('form' => $template, 'data' => $context),
				'handled' => false,
			));
			if (!is_array($protection) || empty($protection['handled']) || empty($protection['html'])) {
				return $html;
			}

			$fields = (string) $protection['html'];
			return preg_replace_callback('/<\/form>/i', function () use ($fields) {
				return $fields . '</form>';
			}, (string) $html, 1);
		}
	}
