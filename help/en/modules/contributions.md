# Module contributions to the interface

← [To the “Modules” section](README.md)

An installed and enabled module can declaratively add a menu item,
action in the header, dashboard widget and lines in the general notification bell.
All contributions belong to `admin_extension` to `module.php`:

```php
'admin_extension' => array(
    'url' => '/notes',
    'icon' => 'ti ti-notes',
    'feature' => 'Личные заметки',
    'menu' => array(array(
        'code' => 'modules_example', 'label' => 'Пример', 'url' => '/example',
        'permission' => 'view_example', 'parent' => 'modules',
    )),
    'header' => array(/* действие в шапке */),
    'dashboard' => array(/* виджет главной */),
    'notifications' => array(/* строки колокольчика */),
),
```

## Detailed Guides

| Contribution | Guide |
| --- | --- |
| Dashboard | [Widget development](dashboard-widgets.md) |
| Hat | [Button, dropdown and modal action](header-actions.md) |
| Bell | [Notification Provider](notifications.md) |
| Left menu | [Descriptor `module.php`](files.md) |

## General description of the contribution

`header`, `dashboard` and `notifications` accept one description or list
descriptions. General properties:

| Key | Destination |
| --- | --- |
| `code` | Stable contribution code inside the module. Particularly important for widget order. |
| `provider` | Data provider: callable with no arguments, returning an array. |
| `data` | Static array instead of data provider. |
| `permission` | A right without which the contribution and provider are not fulfilled. |
| `visible` | An optional callable with no arguments for additional visibility. |
| `sort_order` | Initial order; the smaller value comes first. |
| `label`, `description`, `icon` | Presentation of contributions in interface settings. |

The provider is called only after checking the right and `visible`. His exception is not
breaks the control panel, but the contribution will receive empty data. Don't consider this
how to handle errors: handle expected failures in provider and log without
secrets.

## Availability and management

- An uninstalled or disabled module does not add UI contributions.
- `permission` is checked for the current system user.
- On the **System → Modules** page, the administrator can separately disable
  module action in the header and its widget.
- In **Basic Settings → Interface** you can change the order and visibility
  dashboard elements.
- Notifications are shown only if `count` is positive; separate general
  There is no placement switch for them.

## Assets

The CSS/JS for the header and dashboard should be registered in the module descriptor: these
elements appear outside the module's own route.

```php
'assets' => array(
    'styles' => array(
        array('url' => ADMINX_BASE . '/modules/Notes/assets/notes.css', 'priority' => 46),
    ),
    'scripts' => array(
        array('url' => ADMINX_BASE . '/modules/Notes/assets/notes.js', 'priority' => 46),
    ),
),
```

Styles remain in `assets/<module>.less`, JavaScript - in the module file under
space `Adminx.<Module>`. Common primitives (`btn`, `dropdown`, `section-header`,
`icon-tile`, `Adminx.Ajax`, `Adminx.Toast`, `Adminx.Confirm`) reuse from
UI kit without creating parallel components.
