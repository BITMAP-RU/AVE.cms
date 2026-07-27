# Module actions in the header

← [To the overview of UI contributions](contributions.md)

`admin_extension.header` adds its own action to the right side of the header
module. This could be a command icon, a dropdown with a short list, or a button,
which opens a modal window.

Don't use header input instead of notifications: events that require attention
are added to the general [bell](notifications.md).

## 1. Description in `module.php`

```php
use App\Adminx\Todo\Topbar;

'header' => array(
    'code' => 'quick_add',
    'label' => 'Быстрое создание задачи',
    'icon' => 'ti ti-list-check',
    'template' => '@todo/topbar.twig',
    'modal_template' => '@todo/modal.twig',
    'provider' => array(Topbar::class, 'data'),
    'permission' => 'view_todos',
    'sort_order' => 20,
),
```

- `template` is included directly inside `.topbar-right`.
- `modal_template` is optional and is included at the end of `<body>`, where the modal
  the window is not limited by the size of the header.
- both templates receive the same `module_data` and `module_header`.
- contributions can be disabled by placing the module in the interface.

## 2. Provider

The header data provider runs on **every dashboard page**,
therefore there must be
cheap and take into account the current user:

```php
class Topbar
{
    public static function data()
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return array('enabled' => false);
        }

        return array(
            'enabled' => true,
            'pending' => Model::pendingCount($userId),
            'items' => Model::pending($userId, 5),
            'can_manage' => Permission::check('manage_todos'),
        );
    }
}
```

Do not upload a complete list of entries. For badge you need `COUNT`, for dropdown -
small `LIMIT`. A single provider should not make external HTTP requests.

## 3. Button and dropdown

```twig
{% if module_data.enabled %}
<div class="dropdown todo-topbar" id="todoMenu">
  <button class="btn btn-ghost btn-icon" type="button" data-dropdown
          aria-haspopup="true" aria-expanded="false"
          aria-label="Задачи" data-tooltip="Задачи">
    <i class="ti ti-list-check"></i>
    {% if module_data.pending > 0 %}
      <span class="topbar-badge">{{ module_data.pending > 99 ? '99+' : module_data.pending }}</span>
    {% endif %}
  </button>
  <div class="dropdown-menu right todo-menu" role="dialog" aria-label="Задачи">
    {% for item in module_data.items %}
      <a class="dd-item" href="{{ ADMINX_BASE }}/todo?edit={{ item.id }}">{{ item.title }}</a>
    {% else %}
      <div class="notif-empty">Открытых задач нет</div>
    {% endfor %}
  </div>
</div>
{% endif %}
```

Use `btn-icon`, Tabler icon, `data-tooltip` and `aria-label`. Badge
limit the display to `99+` so that the number does not change the geometry of the header.
Dropdown opens general `Adminx.Dropdown`; custom click handler for
no regular opening required.

## 4. Modal action

`modal_template` is suitable for quickly creating a record. The form must have
server route with CSRF and recheck `manage_*`:

```twig
{% if module_data.can_manage %}
<div class="overlay todo-create-overlay" id="todoCreateOverlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="todoCreateTitle">
    <form action="{{ ADMINX_BASE }}/todo" method="post" data-todo-create>
      <input type="hidden" name="_csrf" value="{{ csrf_token }}">
      <div class="modal-header">
        <span class="dialog-icon info"><i class="ti ti-list-check"></i></span>
        <div><h3 id="todoCreateTitle">Новая задача</h3></div>
        <button class="modal-close" type="button" data-todo-close aria-label="Закрыть">
          <i class="ti ti-x"></i>
        </button>
      </div>
      <div class="modal-body">...</div>
      <div class="modal-footer">
        <button class="btn btn-ghost" type="button" data-todo-close>Отмена</button>
        <button class="btn btn-primary" type="submit"><i class="ti ti-plus"></i>Создать</button>
      </div>
    </form>
  </div>
</div>
{% endif %}
```

The module's JavaScript submits the form via a common Ajax contract, shows Toast,
updates badge/dropdown and closes the window only after a successful response. Not
use `window.alert`, `confirm` or `prompt`.

## 5. Assets and several actions

Since the header is present on all pages, its CSS/JS is registered in
manifest, and not just the section controller. The ID and data attribute names must be
contain the module code so that several contributions do not conflict.

`header` can be a list of descriptions. Every action must have
unique `code`, `sort_order`, templates and minimum required rights.

## Check

- the action is not visible without `permission`;
- provider does not create N+1 and runs quickly on any page;
- dropdown is closed by a common mechanism and is not cut off;
- the modal window is accessible from the keyboard and closes after success;
- POST checks CSRF and entitlement regardless of button visibility;
- badge and long text do not move the rest of the header elements;
- turning off placement or module completely removes the contribution.
