# Field hooks

← [To the section “Hooks and events”](README.md)

Field events are executed for all staff writers and public runtime. For
own field form first use class `FieldType`; hook is needed for
module external policy that does not belong to the same type.

## Stages

| Event | Context and mutable result |
| --- | --- |
| `content.field.registry` | The type registry has been created. |
| `content.field.normalizing` | `type`, `value`, `field`, `document`; you can replace the input. |
| `content.field.normalized` | The normalized string is in `result()`. |
| `content.field.validating` | `type`, `field`, `value`; you can change the input or cancel the standard check. |
| `content.field.validated` | The error is located in `result()`: `null` or line. |
| `content.field.saving` | `type`, `field`, `document_id`, `value`; total in `result()`. |
| `content.field.saved` | The field is recorded; the value is in `result()`. |
| `content.field.rendering` | `type`, `mode`, `value`, `field`, `template_id`. |
| `content.field.rendered` | The HTML is in `result()`, and `renderer` is also available. |

Besides `content.field.registry`, the context is `LifecycleEvent`.

## Additional validation

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

At stage `validating`, the call to `cancel()` skips the native check; him
`result()` becomes the error text or `null`. At stage `validated` you can
add/replace final error via `setResult()`.

Always restrict the handler to your `type`, field ID, alias, or category. Otherwise
the subscription will silently change all project documents.

## Changing the render

```php
public static function afterRender(LifecycleEvent $event)
{
    if ($event->value('type') !== 'rating' || $event->value('mode') !== 'document') {
        return;
    }

    $event->setResult('<span class="rating">' . (string) $event->result() . '</span>');
}
```

HTML is generated on each suitable render. Don't do SQL and externals here
HTTP requests; prepare the data in advance or use a cached service.

Details of the type class, storage and templates are in section
[Document fields](../fields/README.md).
