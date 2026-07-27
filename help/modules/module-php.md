# `module.php`: descriptor модуля

← [К разделу «Модули»](README.md)

`module.php` возвращает PHP-массив без вывода HTML и побочных действий.
Система читает descriptor и регистрирует модуль в общих реестрах.

## Пример модуля панели управления

```php
<?php

use App\Adminx\Notes\DashboardWidget;
use App\Adminx\Notes\Controller;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'notes',
    'name' => 'Заметки',
    'version' => '1.0.0',
    'description' => 'Личные заметки администратора.',
    'author' => 'AVE.cms',

    'lifecycle' => array(
        'managed' => true,
        'uninstall' => array('migrations/uninstall.sql'),
    ),
    'requires' => array('users'),

    'permissions' => array(
        'key' => 'notes',
        'items' => array(
            array('code' => 'view_notes', 'group_code' => 'navigation',
                  'name' => 'Заметки: просмотр', 'sort_order' => 10),
            array('code' => 'manage_notes', 'group_code' => 'content',
                  'name' => 'Заметки: управление', 'sort_order' => 20),
        ),
        'icon' => 'ti ti-notes',
        'priority' => 46,
    ),
    'routes' => array(
        array('GET', '/notes', array(Controller::class, 'index')),
        array('POST', '/notes', array(Controller::class, 'store')),
    ),
    'migrations' => array(
        array('id' => '001_create_notes', 'file' => 'migrations/001_create_notes.sql'),
    ),
    'assets' => array(
        'styles' => array(
            array('url' => ADMINX_BASE . '/modules/Notes/assets/notes.css', 'priority' => 46),
        ),
        'scripts' => array(
            array('url' => ADMINX_BASE . '/modules/Notes/assets/notes.js', 'priority' => 46),
        ),
    ),
    'admin_extension' => array(
        'url' => '/notes',
        'icon' => 'ti ti-notes',
        'feature' => 'Личные заметки',
        'menu' => array(array(
            'code' => 'modules_notes', 'label' => 'Заметки', 'url' => '/notes',
            'icon' => 'ti ti-notes', 'permission' => 'view_notes',
            'group' => 'Система', 'parent' => 'modules', 'sort_order' => 9,
            'match' => array('/notes'),
        )),
        'dashboard' => array(
            'template' => '@notes/dashboard.twig',
            'provider' => array(DashboardWidget::class, 'data'),
            'permission' => 'view_notes',
            'sort_order' => 24,
        ),
    ),
);
```

Существующие модули панели могут держать `routes`, `assets`, `permissions` и
`navigation` на верхнем уровне. В составном пакете контекстные сервисы,
маршруты и хуки разделяются на `admin` и `public`.

## Идентификация

| Ключ | Назначение |
| --- | --- |
| `code` | Обязательный стабильный код модуля. |
| `name` | Человекочитаемое название. |
| `version` | Версия кода и миграций. |
| `description`, `author` | Метаданные страницы модулей. |

Не меняйте `code` после выпуска: на него ссылаются состояние, миграции,
настройки, права, зависимости и источники типов полей.

## Жизненный цикл и зависимости

```php
'lifecycle' => array(
    'managed' => true,
    'install' => array(Installer::class, 'install'),
    'update' => array(Installer::class, 'update'),
    'uninstall' => array(Installer::class, 'uninstall'),
),
'requires' => array('commerce'),
'package' => array('removable' => true),
```

- `managed` включает явные состояния установки и активности.
- `install`, `update`, `uninstall` принимают callable; вместо uninstall-callable
  можно передать список SQL- или PHP-файлов миграций.
- `adopt_existing` применяется только при контролируемом переносе уже
  существующего legacy-модуля и запрещён для нового ZIP.
- `requires` перечисляет обязательные установленные и включённые модули.
- `package.removable` разрешает физическое удаление деинсталлированного модуля:
  самостоятельного административного каталога либо составного пакета из
  `modules/<code>`. У системных разделов ядра этот флаг не задаётся.

Подробности: [операции](operations.md) и [миграции](migrations.md).

## Общие подключения

| Ключ | Назначение |
| --- | --- |
| `namespaces` | Дополнительные пары `Namespace` → относительный каталог. |
| `config` | Конфигурация модуля. |
| `settings` | Схема управляемых настроек. |
| `services`, `helpers`, `files` | PHP-файлы, подключаемые в любом runtime. |
| `view_globals` | Общие значения Twig. |
| `migrations` | Версионированные SQL-файлы схемы и PHP-файлы преобразования данных. |
| `hook_definitions` | Описание новых точек расширения модуля. |
| `hooks` | Общие подписки на события. |
| `field_types` | Поставляемые классы типов полей. |

Общие подключения доступны и у выключенного установленного модуля, если нужны
для чтения существующих данных. Не размещайте здесь маршруты и UI, которые
должны исчезнуть при выключении.

## Admin и public

```php
'admin' => array(
    'services' => array('services.php'),
    'routes' => array(
        array('GET', '/example', array(AdminController::class, 'index')),
    ),
    'hooks' => array(
        array(
            'name' => 'content.document.saved',
            'handler' => array(AdminSubscriber::class, 'afterSave'),
            'priority' => 20,
        ),
    ),
    'assets' => array(/* styles и scripts */),
),
'public' => array(
    'services' => array('services.php'),
    'routes' => array(
        array('GET', '/example/{id}', array(PublicController::class, 'show')),
    ),
    'hooks' => array(/* публичные обработчики */),
),
```

Admin-секция загружается только в панели управления, public-секция — только на публичном
сайте. У выключенного модуля обе секции пропускаются.

## Public-теги и визуальный инспектор

Public-тег регистрируется в `services.php`. Необязательный ключ `inspect`
сообщает защищённому публичному отладчику, как назвать элемент и куда перейти
для его настройки:

```php
ModuleTagRegistry::register(
    'gallery-widget',
    '#\\[mod_gallery:([1-9][0-9]*)\\]#i',
    array(Tags::class, 'render'),
    20,
    array(
        'cacheable' => false,
        'inspect' => array(
            'module' => 'galleries',
            'title' => 'Галереи',
            'edit_url' => 'modules/galleries',
        ),
    )
);
```

- `module` — стабильный код пакета;
- `title` — понятное человеку название;
- `edit_url` — путь внутри панели управления без имени её физической папки;
- `edit_url` можно не указывать, если у тега нет собственного редактора.

Полный URL строит `AdminLocation`, поэтому переименование каталога панели не
ломает переход. Не передавайте в `inspect` содержимое элемента, пользовательские
данные или секреты.

## Права и маршруты

`permissions` описывает группу и элементы прав. Маршруты должны дополнительно
проверять право в контроллере: наличие пункта меню не является защитой.

```php
'permissions' => array(
    'key' => 'notes',
    'items' => array(
        array('code' => 'view_notes', 'group_code' => 'navigation',
              'name' => 'Заметки: просмотр', 'sort_order' => 10),
        array('code' => 'manage_notes', 'group_code' => 'content',
              'name' => 'Заметки: управление', 'sort_order' => 20),
    ),
    'icon' => 'ti ti-notes',
    'priority' => 46,
),
'routes' => array(
    array('GET', '/notes', array(Controller::class, 'index')),
    array('POST', '/notes', array(Controller::class, 'store')),
),
```

Статические права, меню и маршруты держите в `module.php`. Отдельный provider
или `routes.php` нужен только для вычисляемой либо переиспользуемой логики.

## Хуки и свои события

```php
'hook_definitions' => array(
    array(
        'name' => 'reviews.review.approved',
        'kind' => 'action',
        'domain' => 'reviews',
        'description' => 'Отзыв прошёл модерацию',
        'context' => ReviewEvent::class,
        'mutable' => false,
    ),
),
'hooks' => array(
    array(
        'name' => 'content.document.saved',
        'handler' => array(Subscriber::class, 'onDocumentSaved'),
        'priority' => 20,
    ),
),
```

Полный контракт находится в разделе [Хуки и события](../hooks/README.md).

## Типы полей

```php
'field_types' => array(
    array('class' => RatingField::class, 'creatable' => true),
),
```

`creatable = false` оставляет обработчик для существующих данных, но скрывает
тип при создании нового поля. Разработка типа описана в разделе
[Поля документов](../fields/development.md).

## Вклады в интерфейс панели

`admin_extension` может предоставить меню, действие в шапке, виджет дашборда и
уведомления. Для каждого вклада задаются provider/template, право и порядок.
Подробные схемы и примеры: [Вклады в UI](contributions.md).
