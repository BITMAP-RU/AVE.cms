# Подписка и контекст события

← [К разделу «Хуки и события»](README.md)

## Приоритет и область действия

```php
'public' => array(
    'hooks' => array(
        array(
            'name' => 'frontend.response.rendering',
            'handler' => array(Subscriber::class, 'beforeResponse'),
            'priority' => 30,
        ),
    ),
),
```

Меньший `priority` выполняется раньше; значение по умолчанию — `10`. Одинаковый
callable с тем же именем и приоритетом повторно не регистрируется.

Вне descriptor доступны `Hooks::add()`, `once()` и `remove()`, но для постоянной
подписки модуля manifest предпочтительнее: она автоматически следует состоянию
и runtime модуля.

## LifecycleEvent

```php
use App\Common\LifecycleEvent;

public static function beforeResponse(LifecycleEvent $event)
{
    $html = (string) $event->value('html', '');
    $event->setValue('html', $html);
}
```

| Метод | Значение |
| --- | --- |
| `subject()` | Сущность: `document`, `field`, `response` и т. п. |
| `operation()` | Текущий этап или операция. |
| `identifier()` | ID/alias сущности, если известен. |
| `source()` | Компонент, который создал событие. |
| `data()`, `value($key, $default)` | Входной контекст. |
| `setValue($key, $value)`, `replaceData()` | Изменение контекста. |
| `result()`, `setResult()` | Текущий или заменённый результат. |
| `metadata()`, `meta()`, `setMeta()` | Служебные метаданные. |
| `cancel($message)`, `cancelled()` | Запрос отмены операции. |
| `debugData()` | Безопасное структурированное представление для отладки. |

Конкретный вызывающий сервис определяет, какие ключи и отмена имеют смысл.
Ориентируйтесь на [каталог](catalog.md) и контракт соответствующего события.

## Низкоуровневые action и filter

```php
use App\Helpers\Hooks;

Hooks::add('reviews.title', function ($value) {
    return trim((string) $value);
}, 10);

$title = Hooks::filter('reviews.title', $title);
```

- `Hooks::action($name, $argument)` передаёт один аргумент по ссылке; не-`null`
  return обработчика заменяет его для следующего обработчика.
- `Hooks::filter($name, $value)` передаёт текущее значение по цепочке; `null`
  означает «оставить без изменения».
- `exists()`, `has()`, `current()` дают runtime-интроспекцию.

Для системных lifecycle-событий не вызывайте `Hooks::action()` самостоятельно.
Используйте штатный сервис, который формирует правильный контекст.

## Собственное событие модуля

Сначала опишите стабильный контракт:

```php
'hook_definitions' => array(
    array(
        'name' => 'reviews.review.approved',
        'kind' => 'action',
        'domain' => 'reviews',
        'description' => 'Отзыв прошёл модерацию',
        'context' => LifecycleEvent::class,
        'mutable' => false,
    ),
),
```

Затем отправьте событие:

```php
$event = Lifecycle::event(
    'reviews.review.approved',
    'review',
    'approved',
    $reviewId,
    array('document_id' => $documentId),
    null,
    array(),
    'reviews'
);
```

Имя начинается с домена модуля, контекст не должен содержать секреты и
неожиданно тяжёлые объекты. Новые ключи добавляйте обратно совместимо; удаление
или смена смысла ключа требует новой версии контракта.
