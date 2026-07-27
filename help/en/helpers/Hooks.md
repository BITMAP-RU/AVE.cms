# Hooks - low-level dispatcher

`App\Helpers\Hooks` - low-level registration and sending of events/filters.
Stable system points, contexts and connection via module are described in
[section “Hooks and events”](../hooks/README.md).
Two types:

- **action** - “event”: handlers perform side effects, do nothing
  return.
- **filter** - “filter”: the value is passed through the chain and returned
  changed.

```php
use App\Helpers\Hooks;
```

---

## Subscribe

###`add($name, $function, $priority = 10)`
Sign the handler. **Lower priority = earlier** executed.
### `once($name, $function, $priority = 10)` - will work once.
### `remove($name, $function, $priority = 10)` — unsubscribe.

```php
Hooks::add('reviews.title', function ($title) {
    return trim((string) $title);
}, 10);
```

---

## Challenge

###`action($name, $arguments = '')`
Execute all event handlers. If there are no subscribers, it will return `$arguments` as is.

```php
Hooks::action('reviews.rebuilt', $summary);
```

###`filter($name, $value)`
Pass `$value` through the filter chain and get the result. No subscribers -
will return the original value.

```php
Hooks::add('document.title', function ($t) { return trim($t); });
Hooks::add('document.title', function ($t) { return mb_strtoupper($t, 'UTF-8'); }, 20);

$title = Hooks::filter('document.title', $rawTitle);
```

---

## Introspection

| Method | Destination |
| --- | --- |
| `exists($name)` | Is the hook registered? |
| `has($hook, $priority = 10)` | Is there a priority handler? |
| `current()` | The name of the currently executing hook. |
| `debug()` | Debug dump of subscriptions. |
| `trace()`, `enableTrace()` | Structured runtime trace for a secure debugger. |
| `resetRuntime()` | Clear the runtime log between requests of a long-lived process. |
| `init()` | System initialization (called by the kernel). |

---

## action vs filter - how to choose

| Need... | Method |
| --- | --- |
| Do something in response to an event (clear cache, log) | `action` |
| Change/add a value and return it | `filter` |

> The order of execution is determined by priority (ascending). Same
> priority - in order of addition.
