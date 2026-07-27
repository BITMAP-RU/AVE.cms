# Initialization (bootstrap)

← [Back to the “Kernel” section](README.md)

The kernel is raised once per request by calling `App::init()` from
`system/bootstrap.php`. After it, the database, session, settings, Twig and hooks are available -
There is no need to initialize anything in controllers/models.

---

## Entry points

```php
// публичный сайт — index.php (упрощённо)
define('BASEPATH', __DIR__);
require_once BASEPATH . '/system/preload.php';
// ... поднятие ядра ...

// панель — <admin-directory>/index.php
define('ADMINX_PATH', __DIR__);
define('BASEPATH', dirname(ADMINX_PATH));
include BASEPATH . '/system/bootstrap.php';
App::init();
// далее — ModuleManager::loadAll(...) и Dispatcher
```

---

## What does `App::init()` do

In order (see `system/bootstrap.php`):

1. **Autoloader** - `App\Common\Loader\Load::init()` (PSR-like boot
   kernel classes and registered namespaces of modules).
2. **Logs** - `Logs::init()`.
3. **SQL Debugging** - `QueryDebug::init()` (if `SQL_DEBUGGING`).
4. **Session** - `Session::init()` (DB-backed, see [Sessions and CSRF](sessions-csrf.md)).
5. **Settings** — `Settings::init()` (values ​​from the database/config).
6. **Twig** - `Twig::init()` (templating engine).
7. **Hooks** - legacy `system_initialize` and stable lifecycle event
   `system.initialize` after raising the core.

The DB connection rises even earlier (`DB::getInstance($config)`), so queries
available immediately.

---

## Key environment constants

Set by the entry point before the core rises:

| Constant | Meaning |
| --- | --- |
| `BASEPATH` | The absolute path to the root of the current installation. |
| `ADMINX_PATH` | Absolute path to the panel directory to be renamed; exists only in her request. |
| `AVE_CMS`, `ACP` | Context markers (in the admin panel `ACP` = true). |
| `DS` | `DIRECTORY_SEPARATOR`. |
| `SQL_DEBUGGING` | Includes SQL log. |
| `REWRITE_MODE` | Is CNC routing enabled (affects `Url::rewrite()`). |
| `UPLOAD_URL` | Base download URL (for `Url::uploads()`). |

Common code that needs a path or URL for a panel outside of its entry point uses
`App\Common\AdminLocation`. The name is taken from `configs/public.config.php`, key
`admin_directory`; There is no need to hard assemble `/adminx` in public code.

---

## Extension via initialization hook

```php
use App\Helpers\Hooks;

Hooks::add('system.initialize', function ($event) {
    // Выполнится после подъёма ядра, до маршрутизации.
});
```

Declare a permanent subscription in the module manifest; the full contract is described in
[section about hooks](../hooks/README.md).

> In panel modules, their assets, routes and rights are registered not here, but through
> `module.php` - see future section “Modular system”.
