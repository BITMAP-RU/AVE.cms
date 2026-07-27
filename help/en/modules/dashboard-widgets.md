# Development of a dashboard widget

← [To the overview of UI contributions](contributions.md)

The widget consists of a description in `module.php`, a data provider (`provider`),
Twig template and module styles. It automatically disappears when the module is turned off
or lack of right.

## 1. Description in `module.php`

```php
use App\Adminx\Notes\DashboardWidget;

'admin_extension' => array(
    'icon' => 'ti ti-notes',
    'dashboard' => array(
        'code' => 'recent',
        'label' => 'Мои заметки',
        'description' => 'Последние и закреплённые заметки пользователя.',
        'icon' => 'ti ti-notes',
        'template' => '@notes/dashboard.twig',
        'provider' => array(DashboardWidget::class, 'data'),
        'permission' => 'view_notes',
        'sort_order' => 24,
    ),
),
```

`code` generates a stable layout code
`module.notes.dashboard.recent`. Don't change it after release: order settings
and visibility is stored at this value. If code is not specified, the system uses
ordinal number of the contribution, which is less stable when adding new widgets.

`label`, `description` and `icon` are shown in the interface settings. They don't
replace the header inside Twig.

## 2. Provider

```php
<?php

namespace App\Adminx\Notes;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Common\Auth;
use App\Common\Permission;

class DashboardWidget
{
    public static function data()
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return array();
        }

        return array(
            'items' => Model::recent($userId, 6),
            'summary' => Model::summary($userId),
            'can_manage' => Permission::check('manage_notes'),
        );
    }
}
```

Provider is called when building a dashboard and receives no arguments. Return
View data only, not HTML. Limit the selection, choose the ones you need
columns and avoid separate SQL for each element.

`permission` in the description already protects the contribution itself. Additional check
`manage_*` in provider is needed for change buttons inside the widget.

## 3. Twig

The template gets:

- `module_data` — provider array;
- `module_widget` - normalized description with module/layout code, label,
  icon and sort order;
- general panel variables: `ADMINX_BASE`, `csrf_token`, user and assets.

```twig
<section class="dashboard-section notes-dashboard">
  <header class="section-header">
    <div class="section-icon"><i class="ti ti-notes"></i></div>
    <div>
      <div class="section-eyebrow">Заметки</div>
      <h2>Последние заметки</h2>
      <p class="section-desc">{{ module_data.summary.total|default(0) }} всего</p>
    </div>
    <div class="section-header-right">
      <a class="btn btn-icon btn-secondary" href="{{ ADMINX_BASE }}/notes"
         data-tooltip="Открыть заметки" aria-label="Открыть заметки">
        <i class="ti ti-arrow-right"></i>
      </a>
    </div>
  </header>

  <div class="notes-dashboard-list">
    {% for item in module_data.items|default([]) %}
      <a href="{{ ADMINX_BASE }}/notes?edit={{ item.id }}">{{ item.title }}</a>
    {% else %}
      <div class="ax-empty">Заметок пока нет</div>
    {% endfor %}
  </div>
</section>
```

The widget must have an empty state and work correctly without
optional keys provider.

## 4. Grid size

The dashboard uses 12 columns. Regular `.dashboard-section` takes 4/12 on
wide screen, 6/12 on medium and full width on mobile. For standard
widget `6/12` use the generic class `dashboard-half`:

```twig
<section class="dashboard-section dashboard-half notes-dashboard">
  ...
</section>
```

On mobile it will automatically take up the entire width. For a complete row, set
`grid-column: 1 / -1` in the LESS module. Do not add the name of the new module to
global `dashboard.less`: general dimensions are specified by primitives, special dimensions
remain in the module.

## 5. Order and shutdown

`sort_order` specifies only the initial position. After custom
settings, the saved order from **Basic settings → Interface** is valid.
On the module page, the **Place in interface** action allows you to fully
disable its dashboard contribution.

The description can contain a list of several widgets. Ask everyone
unique `code`, `label`, `template` and suitable rights.

## Check

- a role without `permission` does not see the widget and provider is not executed;
- empty data shows a neat state;
- order and visibility are preserved after reboot;
- turning off the module removes the widget;
- desktop/tablet/mobile do not allow horizontal scrolling;
- links, forms and Ajax re-check eligibility on the server.
