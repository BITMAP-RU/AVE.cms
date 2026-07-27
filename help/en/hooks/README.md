# Hooks and events

Hooks allow a module to interfere with the system's lifecycle without changing the kernel:
check or supplement data, launch integration, clear your own cache
or change the render result.

`App\Helpers\Hooks` is used for subscription. Most system events
passing mutable object `App\Common\LifecycleEvent`; saving a document
uses the specialized `DocumentSaveEvent`.

## Section map

| Page | Destination |
| --- | --- |
| [Subscription and context](lifecycle.md) | Manifest, priorities, `LifecycleEvent`, action/filter and custom events. |
| [Saving documents](documents.md) | Data and field changes, save rejection, publishing events. |
| [Field Events](fields.md) | Normalization, validation, recording and public rendering. |
| [Event Catalog](catalog.md) | Complete list of stable kernel extension points. |
| [Debugging and Reliability](debugging.md) | Public Debug, errors, performance and external services. |

## Basic rule

Declare a module subscription in its `module.php`, and place the handler in the class:

```php
'hooks' => array(
    array(
        'name' => 'content.document.saved',
        'handler' => array(Subscriber::class, 'onDocumentSaved'),
        'priority' => 20,
    ),
),
```

The handler is not connected manually: classes are loaded by namespace-loader, subscription
registers `ModuleManager`. Hooks from the `public` section only work on the site,
from `admin` - only in the control panel, top-level ones - in both runtimes.

Don't use a hook as a hidden controller call. The context should be small
predictable and documented, and long work needs to be taken out of critical
HTTP request.
