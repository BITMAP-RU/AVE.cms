# Инициализация (bootstrap)

← [Назад к разделу «Ядро»](README.md)

Ядро поднимается один раз на запрос вызовом `App::init()` из
`system/bootstrap.php`. После него доступны БД, сессия, настройки, Twig и хуки —
в контроллерах/моделях ничего инициализировать не нужно.

---

## Точки входа

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

## Что делает `App::init()`

По порядку (см. `system/bootstrap.php`):

1. **Автозагрузчик** — `App\Common\Loader\Load::init()` (PSR-подобная загрузка
   классов ядра и зарегистрированных namespaces модулей).
2. **Логи** — `Logs::init()`.
3. **Отладка SQL** — `QueryDebug::init()` (если `SQL_DEBUGGING`).
4. **Сессия** — `Session::init()` (DB-backed, см. [Сессии и CSRF](sessions-csrf.md)).
5. **Настройки** — `Settings::init()` (значения из БД/конфига).
6. **Twig** — `Twig::init()` (шаблонизатор).
7. **Хуки** — legacy `system_initialize` и стабильное lifecycle-событие
   `system.initialize` после подъёма ядра.

БД-соединение поднимается ещё раньше (`DB::getInstance($config)`), поэтому запросы
доступны сразу.

---

## Ключевые константы окружения

Задаются точкой входа до подъёма ядра:

| Константа | Значение |
| --- | --- |
| `BASEPATH` | Абсолютный путь к корню текущей установки. |
| `ADMINX_PATH` | Абсолютный путь к переименовываемому каталогу панели; существует только в её запросе. |
| `AVE_CMS`, `ACP` | Маркеры контекста (в админке `ACP` = true). |
| `DS` | `DIRECTORY_SEPARATOR`. |
| `SQL_DEBUGGING` | Включает лог SQL. |
| `REWRITE_MODE` | Включён ли ЧПУ-роутинг (влияет на `Url::rewrite()`). |
| `UPLOAD_URL` | Базовый URL загрузок (для `Url::uploads()`). |

Общий код, которому нужен путь или URL панели вне её точки входа, использует
`App\Common\AdminLocation`. Имя берётся из `configs/public.config.php`, ключ
`admin_directory`; жёстко собирать `/adminx` в публичном коде не нужно.

---

## Расширение через хук инициализации

```php
use App\Helpers\Hooks;

Hooks::add('system.initialize', function ($event) {
    // Выполнится после подъёма ядра, до маршрутизации.
});
```

Постоянную подписку объявляйте в manifest модуля; полный контракт описан в
[разделе о хуках](../hooks/README.md).

> В модулях панели свои assets, маршруты и права регистрируются не здесь, а через
> `module.php` — см. будущий раздел «Модульная система».
