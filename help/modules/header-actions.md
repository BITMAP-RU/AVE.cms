# Действия модуля в шапке

← [К обзору UI-вкладов](contributions.md)

`admin_extension.header` добавляет в правую часть шапки собственное действие
модуля. Это может быть иконка-команда, dropdown с кратким списком или кнопка,
которая открывает модальное окно.

Не используйте header-вклад вместо уведомлений: события, требующие внимания,
добавляются в общий [колокольчик](notifications.md).

## 1. Описание в `module.php`

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

- `template` включается непосредственно внутри `.topbar-right`.
- `modal_template` необязателен и включается в конце `<body>`, где модальное
  окно не ограничено размерами шапки.
- оба шаблона получают одинаковые `module_data` и `module_header`.
- вклад можно отключить через размещение модуля в интерфейсе.

## 2. Provider

Поставщик данных шапки выполняется на **каждой странице панели управления**,
поэтому должен быть
дешёвым и учитывать текущего пользователя:

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

Не загружайте полный список записей. Для badge нужен `COUNT`, для dropdown —
небольшой `LIMIT`. Один provider не должен выполнять внешние HTTP-запросы.

## 3. Кнопка и dropdown

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

Используйте `btn-icon`, Tabler-иконку, `data-tooltip` и `aria-label`. Badge
ограничивайте отображением `99+`, чтобы число не меняло геометрию шапки.
Dropdown открывает общий `Adminx.Dropdown`; собственный обработчик клика для
обычного открытия не нужен.

## 4. Модальное действие

`modal_template` подходит для быстрого создания записи. Форма обязана иметь
серверный маршрут с CSRF и повторной проверкой `manage_*`:

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

JavaScript модуля отправляет форму через общий Ajax-контракт, показывает Toast,
обновляет badge/dropdown и закрывает окно только после успешного ответа. Не
используйте `window.alert`, `confirm` или `prompt`.

## 5. Assets и несколько действий

Так как шапка присутствует на всех страницах, её CSS/JS регистрируются в
manifest, а не только контроллером раздела. Имена ID и data-атрибутов должны
содержать код модуля, чтобы несколько вкладов не конфликтовали.

`header` может быть списком описаний. У каждого действия должны быть
уникальные `code`, `sort_order`, шаблоны и минимально необходимое право.

## Проверка

- действие не видно без `permission`;
- provider не создаёт N+1 и выполняется быстро на любой странице;
- dropdown закрывается общим механизмом и не обрезается;
- модальное окно доступно с клавиатуры и закрывается после успеха;
- POST проверяет CSRF и право независимо от видимости кнопки;
- badge и длинный текст не сдвигают остальные элементы шапки;
- выключение размещения или модуля полностью убирает вклад.
