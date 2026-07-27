# `module.php`: module descriptor

← [To the “Modules” section](README.md)

`module.php` returns a PHP array without HTML output or side effects.
The system reads the descriptor and registers the module in common registries.

## Example control panel module

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

Existing panel modules can hold `routes`, `assets`, `permissions` and
`navigation` at the top level. In a composite package there are contextual services,
routes and hooks are divided into `admin` and `public`.

## Identification

| Key | Destination |
| --- | --- |
| `code` | Mandatory stable module code. |
| `name` | Human readable name. |
| `version` | Code version and migrations. |
| `description`, `author` | Module page metadata. |

Do not change `code` after release: it is referenced by status, migrations,
settings, rights, dependencies and sources of field types.

## Lifecycle and dependencies

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

- `managed` includes explicit install and activity states.
- `install`, `update`, `uninstall` accept callable; instead of uninstall-callable
  You can pass a list of SQL or PHP migration files.
- `adopt_existing` only applies to controlled transfers already
  existing legacy module and is prohibited for the new ZIP.
- `requires` lists required modules installed and enabled.
- `package.removable` allows physical removal of the uninstalled module:
  a separate administrative directory or a composite package of
  `modules/<code>`. This flag is not set for system kernel partitions.

Details: [operations](operations.md) and [migrations](migrations.md).

## General connections

| Key | Destination |
| --- | --- |
| `namespaces` | Additional pairs `Namespace` → relative directory. |
| `config` | Module configuration. |
| `settings` | Guided settings diagram. |
| `services`, `helpers`, `files` | PHP files that can be included at any runtime. |
| `view_globals` | Common Twig values. |
| `migrations` | Versioned SQL schema files and PHP data conversion files. |
| `hook_definitions` | Description of new module expansion points. |
| `hooks` | General event subscriptions. |
| `field_types` | Provided field type classes. |

General connections are also available when the installed module is turned off, if needed
to read existing data. Don't post routes and UI here that
should disappear when turned off.

## Admin and public

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

The admin section is loaded only in the control panel, the public section is loaded only on the public
website. When the module is turned off, both sections are skipped.

## Public tags and visual inspector

The public tag is registered in `services.php`. Optional key `inspect`
tells the protected public debugger what to name the element and where to go
to configure it:

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

- `module` - stable package code;
- `title` - a human-readable name;
- `edit_url` — path inside the control panel without the name of its physical folder;
- `edit_url` can be omitted if the tag does not have its own editor.

The full URL builds `AdminLocation`, so renaming the panel directory does not
breaks the transition. Do not pass element contents to `inspect`, custom
data or secrets.

## Rights and routes

`permissions` describes the rights group and elements. Routes must additionally
check the right in the controller: having a menu item is not a protection.

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

Keep static rights, menus and routes in `module.php`. Separate provider
or `routes.php` is needed only for calculated or reused logic.

## Hooks and custom events

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

The full contract is in the [Hooks and Events](../hooks/README.md) section.

## Field types

```php
'field_types' => array(
    array('class' => RatingField::class, 'creatable' => true),
),
```

`creatable = false` leaves a handler for existing data, but hides
type when creating a new field. Type development is described in section
[Document fields](../fields/development.md).

## Contributions to the panel interface

`admin_extension` can provide a menu, header action, dashboard widget and
notifications. For each contribution, provider/template, right and order are specified.
Detailed diagrams and examples: [UI Contributions](contributions.md).
