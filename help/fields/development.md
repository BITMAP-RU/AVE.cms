# Разработка собственного типа поля

← [К разделу «Поля документов»](README.md)

Новый прикладной тип поставляется устанавливаемым модулем. Так его классы,
права, миграции и возможность удаления остаются одной управляемой единицей.

## 1. Класс типа

Пример числового рейтинга:

```php
<?php

namespace App\Modules\Reviews\Fields;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Content\Fields\AbstractFieldType;
use App\Content\Fields\FieldContext;

class RatingField extends AbstractFieldType
{
    public function code()
    {
        return 'rating';
    }

    public function name()
    {
        return 'Рейтинг';
    }

    public function isNumeric()
    {
        return true;
    }

    public function settingsSchema()
    {
        return array(
            array('key' => 'maximum', 'type' => 'int',
                'label' => 'Максимум', 'default' => 5),
        );
    }

    public function renderEdit(FieldContext $ctx)
    {
        $max = max(1, min(100, (int) $ctx->setting('maximum', 5)));
        return '<input class="input" type="number" min="0" max="' . $max
            . '" name="' . $this->attr($ctx->inputName())
            . '" value="' . $this->attr($ctx->value) . '">';
    }

    public function renderView(FieldContext $ctx)
    {
        if (trim((string) $ctx->value) === '') {
            return '';
        }
        return $this->e($ctx->value) . '&nbsp;/&nbsp;'
            . max(1, (int) $ctx->setting('maximum', 5));
    }

    public function save(FieldContext $ctx)
    {
        $raw = trim(str_replace(',', '.', (string) $ctx->value));
        if ($raw === '') {
            return '';
        }
        if (!is_numeric($raw)) {
            throw new \RuntimeException('Рейтинг должен быть числом');
        }
        return (string) (0 + $raw);
    }
}
```

`AbstractFieldType` предоставляет безопасные `e()`/`attr()`, стандартную
валидацию и пустые реализации фильтров. Код должен быть уникальным, состоять из
латинских букв, цифр и `_` и после выпуска не меняться.

## 2. Размещение в пакете

```text
modules/reviews/
├── app/
│   ├── module.php
│   └── Fields/
│       └── RatingField.php
└── admin/
    └── module.php
```

Класс лежит в `app`, а тип нужно объявить в **обоих** runtime-manifest. Публичный
`app/module.php` обслуживает вывод сайта:

```php
<?php

use App\Modules\Reviews\Fields\RatingField;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'reviews',
    'name' => 'Отзывы',
    'version' => '1.0.0',
    'lifecycle' => array('managed' => true),
    'field_types' => array(
        array('class' => RatingField::class, 'creatable' => true),
    ),
);
```

Административный `admin/module.php` регистрирует тот же класс для конструктора
рубрик и редактора документов:

```php
<?php

use App\Modules\Reviews\Fields\RatingField;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'reviews',
    'name' => 'Отзывы',
    'version' => '1.0.0',
    'lifecycle' => array('managed' => true),
    'package' => array('removable' => true),
    'field_types' => array(
        array('class' => RatingField::class, 'creatable' => true),
    ),
    'admin_extension' => array(
        'url' => '/reviews',
        'icon' => 'ti ti-message-star',
        'feature' => 'Отзывы и тип поля рейтинга',
    ),
);
```

Для составного пакета namespace `App\Modules\Reviews` автоматически указывает
на `modules/reviews/app` и доступен обоим runtime. Ручной `require` не нужен.
Если тип объявить только в `app/module.php`, публичный сайт прочитает его, но
Панель управления не предложит его в конструкторе; только в `admin/module.php` — обратная
ошибка.

`creatable = false` используют только для типа, который должен продолжить
обслуживать существующие поля, но больше не должен предлагаться для создания.

## 3. Установка и включение

1. Установите пакет в **Система → Модули**.
2. Откройте **Рубрики и поля → Типы полей**.
3. Включите новый тип для создания.
4. Создайте тестовую рубрику и поле.
5. Проверьте пустое, корректное и некорректное значения.
6. Проверьте вывод как на странице документа, так и в запросе.
7. Проверьте JSON API документа: тип и нормализованное значение должны быть
   предсказуемыми для внешней интеграции.

## 4. Когда нужны фильтры

Тип участвует в каталоге, если переопределяет:

```php
public function getFilterTypes();
public function buildFilterOptions($fieldId, $rubricId);
public function normalizeFilterValue($value);
public function applyFilter($fieldId, $selected);
```

`applyFilter()` возвращает `array('sql' => ' ...', 'args' => array(...))`.
Нельзя вставлять пользовательское значение прямо в SQL. Используйте
плейсхолдеры DB и ограничивайте разрешённые варианты.

## 5. Проверка перед выпуском

- весь PHP проходит `php -l` на PHP 7.3;
- `code()` не конфликтует с установленными типами;
- scalar хранится строкой, структура — JSON;
- `renderEdit()` использует `$ctx->inputName()`;
- пользовательские данные экранируются в HTML;
- `save()` нормализует одинаковый ввод одинаково;
- пустое значение не превращается в фиктивный `0` или пустой объект;
- модуль нельзя удалить, пока тип используется рубрикой;
- новая версия хранения сопровождается миграцией и описанием обновления.
