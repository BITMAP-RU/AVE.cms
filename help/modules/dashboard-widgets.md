# Разработка виджета дашборда

← [К обзору UI-вкладов](contributions.md)

Виджет состоит из описания в `module.php`, поставщика данных (`provider`),
Twig-шаблона и стилей модуля. Он автоматически исчезает при выключении модуля
или отсутствии права.

## 1. Описание в `module.php`

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

`code` формирует стабильный layout-код
`module.notes.dashboard.recent`. Не меняйте его после выпуска: настройки порядка
и видимости сохраняются по этому значению. Если code не задан, система использует
порядковый номер вклада, что менее устойчиво при добавлении новых виджетов.

`label`, `description` и `icon` показываются в настройках интерфейса. Они не
заменяют заголовок внутри Twig.

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

Provider вызывается при построении дашборда и не получает аргументов. Возвращайте
только данные представления, не HTML. Ограничивайте выборку, выбирайте нужные
колонки и избегайте отдельного SQL на каждый элемент.

`permission` в описании уже защищает сам вклад. Дополнительная проверка
`manage_*` в provider нужна для кнопок изменения внутри виджета.

## 3. Twig

Шаблон получает:

- `module_data` — массив provider;
- `module_widget` — нормализованное описание с module/layout code, label,
  icon и sort order;
- общие переменные панели: `ADMINX_BASE`, `csrf_token`, пользователь и assets.

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

У виджета обязательно должны быть пустое состояние и корректная работа без
необязательных ключей provider.

## 4. Размер в сетке

Дашборд использует 12 колонок. Обычный `.dashboard-section` занимает 4/12 на
широком экране, 6/12 на среднем и всю ширину на мобильном. Для стандартного
виджета `6/12` используйте общий класс `dashboard-half`:

```twig
<section class="dashboard-section dashboard-half notes-dashboard">
  ...
</section>
```

На мобильном он автоматически займёт всю ширину. Для полного ряда задайте
`grid-column: 1 / -1` в LESS модуля. Не добавляйте имя нового модуля в
глобальный `dashboard.less`: общие размеры задаются примитивами, особые размеры
остаются в модуле.

## 5. Порядок и выключение

`sort_order` задаёт только первоначальное положение. После пользовательской
настройки действует сохранённый порядок из **Основные настройки → Интерфейс**.
На странице модуля действие **Размещение в интерфейсе** позволяет полностью
отключить его dashboard-вклад.

Описание может содержать список нескольких виджетов. Каждому задайте
уникальные `code`, `label`, `template` и подходящее право.

## Проверка

- роль без `permission` не видит виджет и provider не выполняется;
- пустые данные показывают аккуратное состояние;
- порядок и видимость сохраняются после перезагрузки;
- выключение модуля убирает виджет;
- desktop/tablet/mobile не дают горизонтальной прокрутки;
- ссылки, формы и Ajax повторно проверяют право на сервере.
