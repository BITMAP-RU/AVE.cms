# Core and request lifecycle

How the request passes through the engine: from `index.php` to the response. Here are the basic ones
bricks on which both the public site and the control panel stand. Her catalog
by default it is called `/adminx`, but can be renamed.

---

## Life cycle (simplified)

```
index.php / adminx/index.php
      │
      ▼
system/bootstrap.php → App::init()      // автозагрузчик, БД, сессия, настройки, Twig, хуки
      │
      ▼
Router::match()                          // найти маршрут по пути и методу
      │
      ▼
handler (Controller::method / Closure)   // выполнить обработчик, получить строку/JSON
      │
      ▼
Response                                 // отдать HTML/JSON + статус
```

In the admin panel, the entry point `adminx/index.php` additionally raises modules
(`ModuleManager`) and routing via `Dispatcher`.

---

## Section map

| Page | What about |
| --- | --- |
| [Initialization (bootstrap)](bootstrap.md) | What raises `App::init()`: autoloading, database, session, settings, Twig, hooks. |
| [Routing (Router)](routing.md) | `get/post/put/delete`, parameters `{id}`, groups, `_method`. |
| [Controllers](controllers.md) | Basic `App\Common\Controller`: `render / json / success / error / csrfGuard`. |
| [Authorization](auth.md) | Admin user and site visitor: `check / user / id / attempt`. |
| [Sessions and CSRF](sessions-csrf.md) | `Session`: Storage, CSRF token, form validation and AJAX. |
| [Permission](permissions.md) | Checking permissions, roles, registering rights by modules. |

---

## Two contexts

The engine serves **two independent contexts** in one codebase:

| | Public site | Admin `/adminx` |
| --- | --- | --- |
| Login | `index.php` | `adminx/index.php` |
| User | `Auth::publicUser()` (visitor) | `Auth::user()` / `systemUser()` (admin) |
| Rights | — | `Permission::check()` |
| Modules | public modules | panel modules (`ModuleManager`) |

Session and CSRF mechanics are common (one DB session and cookie), which saves login when
transitions between the site and the panel.

Extensions register namespaces and runtime contributions declaratively via
[module descriptor](../modules/module-php.md); manually changing the bootloader for
no regular module required.
