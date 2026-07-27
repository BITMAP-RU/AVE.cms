# Sessions and CSRF

← [Back to the “Kernel” section](README.md)

`App\Common\Session` - working with session and CSRF token. Session **stored in the database**
(DB-backed handler), rises to `App::init()` through `Session::init()`. Website and
the panel uses one session and one cookie.

```php
use App\Common\Session;
```

---

## Session data

| Method | Destination |
| --- | --- |
| `get($key)` | Read the value. |
| `set($key, $value)` | Record the value. |
| `check(...$keys)` | Is there a key(s). |
| `del(...$keys)` | Remove key(s). |

```php
Session::set('wizard.step', 2);
$step = (int) Session::get('wizard.step');
if (Session::check('cart')) { ... }
Session::del('wizard.step');
```

---

## Session management

| Method | Destination |
| --- | --- |
| `init()` / `start()` | Initialization/start (does the kernel). |
| `isActive()` | Is the session active? |
| `regenerateId($deleteOld = false)` | Change ID (after login - against session fixation). |
| `getId()` / `setId()` / `getName()` / `setName()` | Session ID/name. |
| `setDomain($domain = '')` | Cookie domain (empty = host-only, important on subdomains). |
| `destroy()` | Destroy the session (on exit). |

---

##CSRF

### Token

```php
$token = Session::csrfToken();   // стабильный per-session токен
```

The token is **stable** within a session (not rotated for each request), comparison -
via `hash_equals`. Forced reset: `Session::regenerateCsrf()`.

### In HTML form

```html
<form method="post" action="/notes">
  <input type="hidden" name="_csrf" value="{{ csrf_token }}">
  ...
</form>
```

### In an AJAX request

Pass the token with the `X-CSRF-Token` header (`Adminx.Ajax` does this automatically
for non-GET requests):

```js
fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
```

### Check on the server

In the controller - through the wrapper (see [Controllers](controllers.md)):

```php
public function store(array $params = array())
{
    if (($err = $this->csrfGuard()) !== null) { return $err; }  // читает X-CSRF-Token или _csrf
    // ...
}
```

Low level:

```php
Session::verifyCsrf($token);   // бросит исключение при несовпадении
```

---

## Practice

- **Check CSRF on all POST/PUT/DELETE** - not necessary on read (GET).
- After successful login, call `regenerateId(true)` (protection against session fixation).
- For subdomains, keep the **host-only** cookie (`setDomain('')`) - otherwise session
  “leak” to another host.
- Do not put large amounts of data into the session - it is in the database, this is an extra load.
