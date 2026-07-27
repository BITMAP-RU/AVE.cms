# Устройство и хранение полей

← [К разделу «Поля документов»](README.md)

## Определение и значение

Описание поля хранится в контентной таблице `rubric_fields`. Основные свойства:

| Свойство | Назначение |
| --- | --- |
| `Id` | Стабильный ID поля. |
| `rubric_id` | Рубрика, которой принадлежит поле. |
| `rubric_field_type` | Код зарегистрированного типа. |
| `rubric_field_alias` | Человекочитаемый стабильный ключ для API и шаблонов. |
| `rubric_field_settings` | JSON с настройками и правилами валидации. |
| `rubric_field_default` | Значение по умолчанию; у старых типов может содержать legacy-конфигурацию. |
| `rubric_field_numeric` | Необходимость числового индекса. |
| `rubric_field_template` | Точечный вывод в документе. |
| `rubric_field_template_request` | Точечный вывод в запросе/списке. |

Значения документа хранятся по паре `document_id + rubric_field_id` в
`document_fields`. Длинный хвост значения может лежать в `document_fields_text`.
Писать в эти таблицы напрямую не нужно: панель управления и JSON API используют единый
writer, который нормализует поле, обновляет обе части и перестраивает снимок.

## Scalar и JSON

Метод `save()` по контракту возвращает строку. Простое поле возвращает
каноническое scalar-значение, а структурное кодирует массив в JSON без
экранирования Unicode и URL.

```php
public function save(FieldContext $ctx)
{
    return Json::encode(array(
        'width' => (float) $ctx->value['width'],
        'height' => (float) $ctx->value['height'],
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
```

При чтении `FieldValueCodec::decodeStructured()` понимает JSON и временно
понимает старый PHP `serialize` без разрешения классов.

Не храните JSON вручную в scalar-поле ради нескольких визуальных параметров.
Настройки представления принадлежат `rubric_field_settings`, а значение документа
должно содержать только данные конкретного документа.

## Числовой индекс

Если `isNumeric()` возвращает `true`, writer дополнительно записывает число в
`field_number_value`. Этот индекс используется сортировкой, условиями запросов и
range-фильтрами. `save()` такого типа обязан возвращать каноническое число с
точкой без пробелов и единиц измерения.

## FieldContext

Каждый метод типа получает `FieldContext`:

```php
$ctx->value;                 // текущее значение
$ctx->definition;            // строка rubric_fields
$ctx->document;              // документ, если доступен на этом этапе
$ctx->rubric;                // рубрика, если доступна
$ctx->mode;                  // edit, view, filter или save
$ctx->extra;                 // дополнительные данные вызывающего кода

$ctx->fieldId();
$ctx->alias();
$ctx->type();
$ctx->rubricId();
$ctx->inputName();           // fields[123]
$ctx->inputName('[]');       // fields[123][]
$ctx->settings();
$ctx->setting('unit', '');
$ctx->legacyDefault();
```

## Порядок сохранения

1. `content.field.normalizing` может изменить входные значение и определение.
2. Вызывается `FieldType::save()`.
3. `FieldValueCodec` приводит результат к строке хранения.
4. `content.field.normalized` может заменить нормализованный результат.
5. `FieldValidator` проверяет правила поля.
6. `content.field.saving` может заменить значение или отменить запись поля.
7. Writer обновляет short/text части и числовой индекс.
8. Вызывается `content.field.saved`.
9. После сохранения документа перестраивается его JSON-снимок.

Чтобы все эти этапы выполнялись, модуль не должен самостоятельно обновлять
таблицы значений через SQL.
