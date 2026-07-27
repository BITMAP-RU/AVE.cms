# Cookie - working with cookies

`App\Helpers\Cookie` is a thin wrapper over `setcookie`/`$_COOKIE`.

```php
use App\Helpers\Cookie;
```

---

## Methods

###`set($key, $value, $expire = 86400, $domain = '', $path = '/', $secure = false, $httpOnly = false, $sameSite = 'Lax')`
Set cookies. By default it lasts for a day. `$expire` — period in **seconds** from
the current moment. `$sameSite` accepts `Lax`, `Strict` or `None`; unsafe
the combination `None` without `$secure=true` automatically becomes `Lax`.

```php
// на 30 дней, только https, недоступна из JS
Cookie::set('theme', 'dark', 3600 * 24 * 30, '', '/', true, true);
```

###`get($key = null, $default = null)`
Read cookies; without `$key` - all cookies.

```php
$theme = Cookie::get('theme', 'light');
```

### `has($key)` - whether there is a cookie.
### `delete($key, $path = '/', $domain = '', $secure = null, $sameSite = 'Lax')` - delete.

```php
if (Cookie::has('mp_auth')) { ... }
Cookie::delete('theme');
```

---

## Practice

- **Host-only cookie** (important on subdomains - so that the cookie does not “leak” to another
  host): leave `$domain` **empty**.
- **httpOnly** set for everything that does not need to be read from JS (sessions, tokens) -
  reduces the risk of theft via XSS.
- **secure** is required on combat https.
- **SameSite=Lax** is enabled by default and is suitable for session, actor and
  remember-cookie. `None` only use for truly cross-site
  script and always together with `Secure`.

```php
// пример: персистентный вход
Cookie::set('mp_auth', $token, 3600 * 24 * 60, '', '/', true, true);
```
