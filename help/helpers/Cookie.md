# Cookie — работа с cookie

`App\Helpers\Cookie` — тонкая обёртка над `setcookie`/`$_COOKIE`.

```php
use App\Helpers\Cookie;
```

---

## Методы

### `set($key, $value, $expire = 86400, $domain = '', $path = '/', $secure = false, $httpOnly = false, $sameSite = 'Lax')`
Установить cookie. По умолчанию живёт сутки. `$expire` — срок в **секундах** от
текущего момента. `$sameSite` принимает `Lax`, `Strict` или `None`; небезопасная
комбинация `None` без `$secure=true` автоматически становится `Lax`.
```php
// на 30 дней, только https, недоступна из JS
Cookie::set('theme', 'dark', 3600 * 24 * 30, '', '/', true, true);
```

### `get($key = null, $default = null)`
Прочитать cookie; без `$key` — все cookie.
```php
$theme = Cookie::get('theme', 'light');
```

### `has($key)` — есть ли cookie.
### `delete($key, $path = '/', $domain = '', $secure = null, $sameSite = 'Lax')` — удалить.
```php
if (Cookie::has('mp_auth')) { ... }
Cookie::delete('theme');
```

---

## Практика

- **Host-only cookie** (важно на поддоменах — чтобы кука не «утекала» на другой
  хост): оставляйте `$domain` **пустым**.
- **httpOnly** ставьте для всего, что не нужно читать из JS (сессии, токены) —
  снижает риск кражи через XSS.
- **secure** обязательно на боевом https.
- **SameSite=Lax** включён по умолчанию и подходит для сессии, actor и
  remember-cookie. `None` используйте только для действительно межсайтового
  сценария и обязательно вместе с `Secure`.

```php
// пример: персистентный вход
Cookie::set('mp_auth', $token, 3600 * 24 * 60, '', '/', true, true);
```
