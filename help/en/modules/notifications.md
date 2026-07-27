# Module notifications in the header

← [To the overview of UI contributions](contributions.md)

`admin_extension.notifications` adds elements to the general panel bell
AVE.cms controls. The module does not draw another button: the system summarizes everything
data providers, shows a common badge and a single list.

Notice here means a current condition that requires attention: new orders,
unread requests, overdue reminders. This is not a Toast after the action and
not a constant audit of events.

## 1. Description in `module.php`

```php
use App\Adminx\Reminders\Notifications;

'admin_extension' => array(
    'notifications' => array(
        'code' => 'overdue',
        'provider' => array(Notifications::class, 'data'),
        'permission' => 'view_reminders',
        'sort_order' => 30,
    ),
),
```

The right is checked before the provider is called. A disabled or uninstalled module
does not participate in the general summary.

## 2. Provider

```php
<?php

namespace App\Adminx\Reminders;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Common\Auth;

class Notifications
{
    public static function data()
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return array();
        }

        $count = Model::overdueCount($userId);
        if ($count < 1) {
            return array();
        }

        return array(array(
            'key' => 'reminders_overdue',
            'title' => 'Просроченные напоминания',
            'text' => 'Срок прошёл — требуют внимания',
            'url' => '/reminders?state=overdue',
            'count' => $count,
            'icon' => 'ti ti-alarm',
            'bg' => 'var(--red-100)',
            'fg' => 'var(--red-600)',
        ));
    }
}
```

The supplier can return the list directly or `array('items' => $items)`. If
there are no elements, return an empty array.

## 3. Element format

| Key | Destination |
| --- | --- |
| `title` | Required short title. |
| `text` | Explanation of status. |
| `url` | Link. The path with `/` automatically receives the control panel prefix. |
| `count` | Positive number for general and string badge. `0` hides the element. |
| `icon` | Tabler-icon class, default `ti ti-bell`. |
| `bg`, `fg` | Tile background and icon color. |
| `key` | Optional aggregation key in the general summary. |

The system automatically limits the visual badge to the value `99+`. Not
format `count` as a string and do not re-include the number in `title`.

Use a semantic color: red for expired/critical, amber for
pending action, blue for new information element, green for
positive state. Color should not be the only carrier of meaning.

## 4. Performance

The notification provider runs when each panel page is built
management.
The correct provider makes one indexable `COUNT` for the current user
or uses a short-lived cache.

You can't:

- load all lines only for the sake of `count()` in PHP;
- perform an external HTTP request;
- make a separate request for each object;
- throw an exception if the optional source table is not normally present;
- show elements with `count = 0`.

For a frequently changing counter, invalidate the address cache when the entity changes,
rather than clearing the entire system cache.

## 5. Read and hidden

The general bell does not store read status. Every time he shows
what provider returned. Following the link does not in itself reduce `count`.

If the module needs "read", "deferred" or personal hiding, create
your own status table and take it into account in the supplier. POST action like this
The mechanism must check the CSRF, the right, and the ownership of the record by the user.

## 6. Multiple types of notifications

One provider can return several rows, for example, separately critical and
ordinary tasks. You can also declare a list of notification descriptions with different
rights and suppliers. Specify unique `code` and `key`, and use `sort_order`
for a predictable order between modules.

## Check

- without the right the supplier is not executed;
- a null result does not leave an empty line;
- the total badge is equal to the sum `count` of all visible elements;
- a relative link leads to the current control panel;
- the provider takes into account the current user and executes the indexed query;
- disabling a module removes its lines and reduces the overall badge;
- source errors do not break the head and are diagnosed without revealing secrets.
