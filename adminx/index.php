<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/index.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	/**
	 * Точка входа новой админ-панели /adminx.
	 *
	 * Поднимает ядро (App::init через system/bootstrap.php), автозагружает модули из
	 * adminx/modules/*, проверяет доступ, срезает монтажный префикс ADMINX_BASE и
	 * диспатчит запрос в контроллер модуля. Никакого legacy class/ или Smarty здесь
	 * нет — только слой App\Common.
	 *
	 * Принцип изоляции (ТЗ §1): весь код панели живёт в собственном переименовываемом
	 * каталоге; удаление этого каталога не ломает сайт и system/App.
	 */

	define('START_MICROTIME', microtime());
	define('START_MEMORY', memory_get_usage());
	define('AVE_CMS', true);
	define('ACP', true);

	@ini_set('default_charset', 'UTF-8');
	mb_internal_encoding('UTF-8');

	define('DS', DIRECTORY_SEPARATOR);
	define('ADMINX_PATH', str_replace('\\', '/', __DIR__));
	define('BASEPATH', str_replace('\\', '/', dirname(ADMINX_PATH)));

	if (!@filesize(BASEPATH . '/configs/db.config.php')) {
		if (!is_file(BASEPATH . '/storage/installed.lock')) {
			$script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/' . basename(ADMINX_PATH) . '/index.php';
			$publicRoot = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
			header('Location: ' . $publicRoot . '/setup/', true, 302);
			exit;
		}

		http_response_code(503);
		header('Content-Type: text/plain; charset=UTF-8');
		echo 'Конфигурация базы данных отсутствует. Восстановите configs/db.config.php.';
		exit;
	}

	if (PHP_VERSION_ID < 70300) {
		exit('AVE.cms requires PHP 7.3 or higher.');
	}

	header('X-Robots-Tag: noindex, nofollow, noarchive');

	//-- Adminx и публичный сайт используют одну DB-сессию и один cookie. Это
	//-- сохраняет авторизацию и CSRF при переходах между сайтом и панелью.

	//-- Ядро: автозагрузчик, БД, сессии, настройки, Twig, хуки.
	include BASEPATH . DS . 'system' . DS . 'bootstrap.php';
	App::init();

	use App\Common\AdminAssets;
	use App\Common\Auth;
	use App\Common\Cache;
	use App\Common\Dispatcher;
	use App\Common\Loader\Load;
	use App\Common\ModuleManager;
	use App\Common\Navigation;
	use App\Common\Permission;
	use App\Common\Session;
	use App\Common\Settings;
	use App\Common\Twig;
	use App\Helpers\Request;
	use App\Helpers\Response;

	//-- Монтажный префикс админки (обычно /adminx). Модульные роуты «чистые»
	//-- (/dashboard, /login), префикс срезается ниже перед матчингом и добавляется
	//-- к ссылкам в шаблонах.
	define('ADMINX_BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));

	//-- Базовые права ядра админки (с человекочитаемой расшифровкой).
	Permission::add('admin', require ADMINX_PATH . DS . 'core-permissions.php', 'ti ti-shield', 0);

	//-- Shared-классы админки, не привязанные к бизнес-модулям.
	Load::regNamespace('App\\Adminx\\Support', ADMINX_PATH . DS . 'Support');

	//-- Автозагрузка модулей: <admin-directory>/modules/<Module>/module.php,
	//-- namespace App\Adminx\<Module>. Код ModuleManager не меняется — только путь.
	ModuleManager::loadAll(ADMINX_PATH . DS . 'modules', 'admin', 'App\\Adminx');

	//-- Устанавливаемые пакеты могут владеть одновременно public- и Adminx-частью.
	//-- Их admin manifest находится в modules/<code>/admin и не связывает public с adminx/.
	\App\Common\PackageModuleRuntime::loadAdminModules();

	//-- Язык панели независим от одноязычного публичного сайта. После загрузки
	//-- модулей доступны и их собственные language/<locale>/*.xml.
	\App\Adminx\Support\AdminLocale::boot(ModuleManager::all());

	//-- Декларативные UI-вклады нативных модулей: меню и синхронизация прав.
	//-- Support остается частью adminx, ядро ничего не знает о его layout.
	\App\Adminx\Support\ModuleExtensions::boot();

	//-- Twig: layout-каркас под @adminx, view каждого модуля — под его code.
	Twig::twig()->addExtension(new \App\Adminx\Support\Twig\AdminLocaleExtension());
	Twig::addPath(ADMINX_PATH . DS . 'view', 'adminx');
	foreach (ModuleManager::all() as $module) {
		$viewPath = rtrim((string) $module['base_path'], '/\\') . DS . 'view';
		if ($module['code'] !== '' && is_dir($viewPath)) {
			Twig::addPath($viewPath, $module['code']);
		}
	}

	//-- Общие ассеты каркаса (грузятся на каждой странице админки).
	AdminAssets::addStyle(ADMINX_BASE . '/assets/css/adminkit.css', 1);
	AdminAssets::addScript(ADMINX_BASE . '/assets/js/adminx.js', 1);
	AdminAssets::addScript(ADMINX_BASE . '/assets/js/media-picker.js', 2);
	AdminAssets::addScript(ADMINX_BASE . '/assets/js/saved-views.js', 3);

	//-- Cache-busting ассетов: ?v=<filemtime>. На время разработки браузер не держит
	//-- устаревший CSS/JS — при правке файла (или пересборке CSS) меняется mtime и URL.
	Twig::twig()->addFunction(new \Twig\TwigFunction('asset_ver', 'adminx_asset_ver'));

	//-- Глобальные переменные шаблонов каркаса.
	Twig::addGlobals([
		'ADMINX_BASE'   => ADMINX_BASE,
		'ABS_PATH'      => ABS_PATH,
		'current_lang'  => \App\Adminx\Support\AdminLocale::current(),
		'admin_locales' => \App\Adminx\Support\AdminLocale::locales(),
		'csrf_token'    => Session::csrfToken(),
		'is_ajax'       => Request::isAjax(),
		'popup_mode'    => Request::getBool('pop', false),
		'lang'          => \App\Common\Language::get(),
		'adminx_i18n'   => \App\Adminx\Support\AdminLocale::clientDictionary(),
		'adminx_phrases' => \App\Adminx\Support\AdminLocale::clientSourceTranslations(ModuleManager::all()),
		'site_development_mode' => (string) Settings::get('site_access_mode', 'public') === 'development',
	]);

	//-- Текущий путь без монтажного префикса (/adminx/dashboard → /dashboard).
	$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$requestPath = rawurldecode((string) $requestPath);
	if (ADMINX_BASE !== '' && strpos($requestPath, ADMINX_BASE) === 0) {
		$requestPath = substr($requestPath, strlen(ADMINX_BASE));
	}

	$requestPath = '/' . ltrim($requestPath, '/');

	Twig::addGlobal('current_path', $requestPath);
	Twig::addGlobal('section_help', \App\Adminx\Support\SectionHelp::forPath($requestPath));

	//-- Доступ: публичные роуты (логин) открыты; всё прочее требует admin_panel.
	//-- Современный Auth API (SystemTables users: email/password_hash/role);
	//-- Auth::user() восстанавливает сессию/cookie и подгружает права роли.
	$isPublic = adminx_is_public($requestPath);
	$authUser = Auth::user();
	if ($authUser === null) {
		$linkedUser = Auth::systemUser();
		if ($linkedUser) {
			Auth::login($linkedUser);
			$authUser = Auth::user();
		}
	}

	$authUser && Auth::ensureBrowserToken();
	$canAdmin = $authUser !== null && Permission::checkAcp('admin_panel');

	//-- Пункты меню для layout: фильтр по правам + активный пункт + абсолютный href.
	$navFlat = [];
	$navigationItems = \App\Adminx\Support\InterfaceSettings::applyNavigation(Navigation::forCurrentUser());
	$navigationItems = \App\Adminx\Support\AdminLocale::translateNavigation($navigationItems);
	foreach ($navigationItems as $navItem) {
		$navItem['active'] = Navigation::isActive($navItem, $requestPath);
		$navItem['href']   = ADMINX_BASE . ($navItem['url'] === '#' ? '' : $navItem['url']);
		$navItem['children'] = [];
		$navFlat[$navItem['code']] = $navItem;
	}

	$navItems = [];
	foreach (array_keys($navFlat) as $code) {
		$parent = isset($navFlat[$code]['parent']) ? (string) $navFlat[$code]['parent'] : '';
		if ($parent !== '' && isset($navFlat[$parent])) {
			$navFlat[$parent]['children'][] = &$navFlat[$code];
			if ($navFlat[$code]['active']) { $navFlat[$parent]['active'] = true; }
		} else {
			$navItems[] = &$navFlat[$code];
		}
	}

	$canClearCache = Permission::check('manage_settings');
	$cacheSizes = array();
	if ($canClearCache) {
		$cacheSizes = Cache::remember('adminx.cache.sizes.' . md5((string) ADMINX_BASE), 30, function () {
			$out = array();
			foreach (\App\Adminx\Settings\Model::cacheRows() as $row) {
				$out[$row['code']] = $row['size'];
			}

			return $out;
		});
	}

	Twig::addGlobals([
		'nav_items'  => $navItems,
		'user_name'  => $authUser['name'] ?? '',
		'user_email' => $authUser['email'] ?? '',
		'user_role'  => $authUser['role'] ?? '',
		'module_header_actions' => $canAdmin
			? \App\Adminx\Support\ModuleExtensions::headerActions()
			: array(),
		'can_clear_cache' => $canClearCache,
		'cache_sizes' => $cacheSizes,
	]);

	//-- Ссылка на публичный сайт (корень установки) для шапки и меню пользователя.
	Twig::addGlobal('public_url', adminx_public_url());

	//-- Сводка уведомлений в шапке (новые заказы/письма) — только для админа.
	$notifications = array('total' => 0, 'orders' => 0, 'messages' => 0, 'items' => array());
	if ($canAdmin) {
		$notificationKey = 'adminx.notifications.' . (int) Auth::id() . '.' . md5((string) ADMINX_BASE);
		$notifications = Cache::remember($notificationKey, 15, function () {
			$summary = \App\Adminx\Support\Notifications::summary(ADMINX_BASE);
			return \App\Adminx\Support\ModuleExtensions::notifications(ADMINX_BASE, $summary);
		});
	}

	Twig::addGlobal('notifications', $notifications);

	if (!$isPublic && !$canAdmin) {
		if (Request::isAjax()) {
			Response::unauthorized();
		}

		Request::redirect(ADMINX_BASE . '/login');
	}

	//-- Уже вошедшего с экрана логина отправляем на дашборд.
	if ($canAdmin && $requestPath === '/login') {
		Request::redirect(ADMINX_BASE . '/');
	}

	//-- Диспатч.
	$result = Dispatcher::handle($requestPath, Request::method(), function (array $route) use ($isPublic) {
		\App\Adminx\Support\RouteGuard::enforce($route, $isPublic);
	});

	if (!$result['matched']) {
		Response::setStatus(404);
		try {
			echo Twig::twig()->render('@adminx/404.twig', ModuleManager::viewGlobals());
		} catch (\Throwable $e) {
			echo 'Страница не найдена';
		}

		exit;
	}

	echo $result['output'];

	/**
	 * URL публичного сайта (корень установки над монтажным префиксом админки):
	 * для ADMINX_BASE «/adminx» вернёт «/», для «/sub/adminx» — «/sub/».
	 */
	function adminx_public_url()
	{
		$parent = rtrim(str_replace('\\', '/', dirname(ADMINX_BASE)), '/');
		return ($parent === '' || $parent === '.') ? '/' : $parent . '/';
	}

	/**
	 * Публичный ли путь (доступен без авторизации): точный роут или префикс,
	 * объявленные модулями через public_routes / public_prefixes.
	 */
	function adminx_is_public($path)
	{
		if (in_array($path, ModuleManager::publicRoutes(), true)) {
			return true;
		}

		foreach (ModuleManager::publicPrefixes() as $prefix) {
			if ($prefix !== '' && strpos($path, $prefix) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Добавить к URL локального ассета версию по mtime файла (cache-busting).
	 * Внешние (CDN) и уже версионированные URL не трогаются.
	 */
	function adminx_asset_ver($url)
	{
		if (!is_string($url) || $url === '') {
			return $url;
		}

		//-- Только локальные пути текущей панели (CDN пропускаем).
		if (preg_match('#^https?://#i', $url)) {
			return $url;
		}

		$rel = (string) parse_url($url, PHP_URL_PATH);
		if (ADMINX_BASE !== '' && strpos($rel, ADMINX_BASE) === 0) {
			$rel = substr($rel, strlen(ADMINX_BASE));
		}

		if (ADMINX_BASE !== '' && strpos((string) parse_url($url, PHP_URL_PATH), ADMINX_BASE) === 0) {
			$file = ADMINX_PATH . str_replace('/', DS, '/' . ltrim($rel, '/'));
		} else {
			$file = BASEPATH . str_replace('/', DS, '/' . ltrim($rel, '/'));
		}

		$ver  = is_file($file) ? filemtime($file) : time();
		$sep  = strpos($url, '?') !== false ? '&' : '?';

		return $url . $sep . 'v=' . $ver;
	}
