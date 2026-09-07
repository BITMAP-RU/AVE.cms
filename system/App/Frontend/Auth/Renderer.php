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
			$fullUser = $user ? (new UserRepository())->find((int) $user['id']) : null;
			$systemUser = Auth::systemUser();
			$displayUser = $user ?: $systemUser;
			$accountLinks = Hooks::filter('auth.account.links', array());
			if (!is_array($accountLinks)) { $accountLinks = array(); }
			$context = array(
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
				'password_reset_enabled' => Feature::passwordRecoveryEnabled(),
				'password_login_enabled' => Feature::passwordLoginEnabled(),
				'password_change_enabled' => $fullUser ? Feature::passwordChangeEnabled($fullUser) : false,
				'auth_urls' => Feature::urls(),
				'login_page' => Feature::page('login'),
				'oauth_providers' => ProviderRegistry::publicItems(),
				'phone_providers' => PhoneProviderRegistry::publicItems(),
				'account_extension_links' => $accountLinks,
				'error' => (string) $error,
			);
			$html = (new FormTemplateRepository())->render('panel', $context);
			if (empty($context['password_login_enabled'])
				&& preg_match('/name\s*=\s*["\']user_login["\']/i', $html)) {
				$html = Twig::twig()->render('@system_auth/panel.twig', $context);
			}

			$html = self::withProtection('login', $html, $context);

			if (!$includeAssets) {
				return $html;
			}

			return '<link rel="stylesheet" href="' . $base . '/system/App/Frontend/Auth/assets/auth.css?v=' . self::assetVersion('auth.css') . '">'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/phone-mask.js?v=' . self::assetVersion('phone-mask.js') . '" defer></script>'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/auth.js?v=' . self::assetVersion('auth.js') . '" defer></script>'
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
				'password_login_enabled' => Feature::passwordLoginEnabled(),
				'email_registration_enabled' => Feature::emailRegistrationEnabled(),
				'phone_registration_enabled' => Feature::phoneRegistrationEnabled(),
				'registration_phone_providers' => Feature::phoneRegistrationProviders(),
			), $data);
			if (!array_key_exists('password_change_enabled', $context)) {
				$context['password_change_enabled'] = isset($context['user']) && is_array($context['user'])
					? Feature::passwordChangeEnabled($context['user']) : false;
			}

			$html = (new FormTemplateRepository())->render($template, $context);
			if ($template === 'login' && empty($context['password_login_enabled'])
				&& preg_match('/name\s*=\s*["\']user_login["\']/i', $html)) {
				$html = Twig::twig()->render('@system_auth/login.twig', $context);
			}

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
				if (($template === 'login' || $template === 'register') && strpos($html, 'data-phone-auth-request') !== false) {
					$html = self::withProtection('phone', $html, $context);
				}
			}

			if (!empty($context['preview'])) {
				$html = preg_replace('/<form\b/i', '<form data-auth-preview-form onsubmit="return false;"', $html);
				$html = '<div class="system-auth-preview"><b>Предпросмотр формы</b><span>Отправка данных отключена.</span></div>' . $html;
			}

			$html = '<link rel="stylesheet" href="' . $base . '/system/App/Frontend/Auth/assets/auth.css?v=' . self::assetVersion('auth.css') . '">'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/phone-mask.js?v=' . self::assetVersion('phone-mask.js') . '" defer></script>'
				. '<script src="' . $base . '/system/App/Frontend/Auth/assets/auth.js?v=' . self::assetVersion('auth.js') . '" defer></script>'
				. $html;
			PublicModuleRuntime::deferPage($html, array(
				'title' => isset($context['title']) ? (string) $context['title'] : (isset($page['title']) ? (string) $page['title'] : ''),
				'description' => isset($context['description']) ? (string) $context['description'] : (isset($page['description']) ? (string) $page['description'] : ''),
				'robots' => 'noindex,follow',
				'template_id' => isset($page['template_id']) ? (int) $page['template_id'] : 1,
			));
			return '';
		}

		protected static function assetVersion($name)
		{
			$file = __DIR__ . '/assets/' . basename((string) $name);
			$modified = is_file($file) ? @filemtime($file) : false;
			return $modified === false ? '1' : (string) $modified;
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
				'phone' => 'phone_auth',
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

			$targets = array(
				'login' => '/\bdata-auth-login\b|name\s*=\s*["\']user_login["\']/i',
				'register' => '/\bdata-auth-register\b|name\s*=\s*["\']password_confirm["\']/i',
				'remember' => '/\bdata-auth-remember\b/i',
				'reset' => '/\bdata-auth-reset\b/i',
				'phone' => '/\bdata-phone-auth-request\b/i',
			);
			$target = $targets[$template];
			$fields = (string) $protection['html'];
			$inserted = false;
			return preg_replace_callback('/<form\b[^>]*>.*?<\/form>/is', function ($match) use ($target, $fields, &$inserted) {
				if ($inserted || !preg_match($target, $match[0])) { return $match[0]; }
				$inserted = true;
				$position = strripos($match[0], '</form>');
				return substr($match[0], 0, $position) . $fields . substr($match[0], $position);
			}, (string) $html);
		}
	}
