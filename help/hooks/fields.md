# Хуки полей

← [К разделу «Хуки и события»](README.md)

События поля выполняются для всех штатных writer и публичного runtime. Для
собственной формы поля сначала используйте класс `FieldType`; hook нужен для
внешней политики модуля, которая не принадлежит одному типу.

## Стадии

| Событие | Контекст и изменяемый результат |
| --- | --- |
| `content.field.registry` | Реестр типов сформирован. |
| `content.field.normalizing` | `type`, `value`, `field`, `document`; можно заменить вход. |
| `content.field.normalized` | Нормализованная строка находится в `result()`. |
| `content.field.validating` | `type`, `field`, `value`; можно изменить вход или отменить стандартную проверку. |
| `content.field.validated` | Ошибка находится в `result()`: `null` или строка. |
| `content.field.saving` | `type`, `field`, `document_id`, `value`; итог в `result()`. |
| `content.field.saved` | Поле записано; значение находится в `result()`. |
| `content.field.rendering` | `type`, `mode`, `value`, `field`, `template_id`. |
| `content.field.rendered` | HTML находится в `result()`, также доступен `renderer`. |

Кроме `content.field.registry`, контекстом является `LifecycleEvent`.

## Дополнительная валидация

```php
public static function validate(LifecycleEvent $event)
{
    if ($event->value('type') !== 'rating') {
        return;
    }

    $value = (int) $event->value('value', 0);
    if ($value < 0 || $value > 10) {
        $event->setResult('Рейтинг должен быть от 0 до 10');
    }
}
```

На стадии `validating` вызов `cancel()` пропускает нативную проверку; его
`result()` становится текстом ошибки или `null`. На стадии `validated` можно
добавить/заменить окончательную ошибку через `setResult()`.

Всегда ограничивайте обработчик своим `type`, ID поля, alias или рубрикой. Иначе
подписка незаметно изменит все документы проекта.

## Изменение рендера

```php
public static function afterRender(LifecycleEvent $event)
{
    if ($event->value('type') !== 'rating' || $event->value('mode') !== 'document') {
        return;
    }

    $event->setResult('<span class="rating">' . (string) $event->result() . '</span>');
}
```

HTML формируется на каждом подходящем рендере. Не делайте здесь SQL и внешние
HTTP-запросы; подготовьте данные заранее или используйте кешируемый сервис.

Подробности класса типа, хранения и шаблонов находятся в разделе
[Поля документов](../fields/README.md).
