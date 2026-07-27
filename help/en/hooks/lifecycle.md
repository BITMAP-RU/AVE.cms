# Subscription and event context

← [To the section “Hooks and events”](README.md)

## Priority and scope

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

The smaller `priority` is executed earlier; the default value is `10`. Same
callable with the same name and priority is not registered again.

Outside the descriptor, `Hooks::add()`, `once()` and `remove()` are available, but for constant
manifest module subscriptions are preferable: they automatically follow the state
and runtime module.

##LifecycleEvent

```php
use App\Common\LifecycleEvent;

public static function beforeResponse(LifecycleEvent $event)
{
    $html = (string) $event->value('html', '');
    $event->setValue('html', $html);
}
```

| Method | Meaning |
| --- | --- |
| `subject()` | Entity: `document`, `field`, `response`, etc. |
| `operation()` | Current stage or operation. |
| `identifier()` | Entity ID/alias, if known. |
| `source()` | The component that generated the event. |
| `data()`, `value($key, $default)` | Input context. |
| `setValue($key, $value)`, `replaceData()` | Changing the context. |
| `result()`, `setResult()` | Current or replaced result. |
| `metadata()`, `meta()`, `setMeta()` | Service metadata. |
| `cancel($message)`, `cancelled()` | Request to cancel an operation. |
| `debugData()` | Secure structured representation for debugging. |

The specific calling service determines which switches and cancellations make sense.
Refer to [catalog](catalog.md) and the contract of the corresponding event.

## Low-level action and filter

```php
use App\Helpers\Hooks;

Hooks::add('reviews.title', function ($value) {
    return trim((string) $value);
}, 10);

$title = Hooks::filter('reviews.title', $title);
```

- `Hooks::action($name, $argument)` passes one argument by reference; non-`null`
  The handler's return replaces it for the next handler.
- `Hooks::filter($name, $value)` transmits the current value along the chain; `null`
  means "to leave unchanged."
- `exists()`, `has()`, `current()` give runtime introspection.

For system lifecycle events, do not call `Hooks::action()` yourself.
Use a regular service that creates the right context.

## Custom module event

First describe the stable contract:

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

Then send the event:

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

The name starts with the module domain, the context must not contain secrets and
unexpectedly heavy objects. Add new keys in a backwards compatible manner; deletion
or changing the meaning of the key requires a new version of the contract.
