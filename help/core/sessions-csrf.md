# Сессии и CSRF

← [Назад к разделу «Ядро»](README.md)

`App\Common\Session` — работа с сессией и токеном CSRF. Сессия **хранится в БД**
(DB-backed handler), поднимается в `App::init()` через `Session::init()`. Сайт и
панель используют одну сессию и один cookie.

```php
use App\Common\Session;
```

---

## Данные сессии

| Метод | Назначение |
| --- | --- |
| `get($key)` | Прочитать значение. |
| `set($key, $value)` | Записать значение. |
| `check(...$keys)` | Есть ли ключ(и). |
| `del(...$keys)` | Удалить ключ(и). |

```php
Session::set('wizard.step', 2);
$step = (int) Session::get('wizard.step');
if (Session::check('cart')) { ... }
Session::del('wizard.step');
```

---

## Управление сессией

| Метод | Назначение |
| --- | --- |
| `init()` / `start()` | Инициализация/старт (делает ядро). |
| `isActive()` | Активна ли сессия. |
| `regenerateId($deleteOld = false)` | Сменить ID (после логина — против фиксации сессии). |
| `getId()` / `setId()` / `getName()` / `setName()` | ID/имя сессии. |
| `setDomain($domain = '')` | Домен cookie (пусто = host-only, важно на поддоменах). |
| `destroy()` | Уничтожить сессию (при выходе). |

---

## CSRF

### Токен

```php
$token = Session::csrfToken();   // стабильный per-session токен
```

Токен **стабилен** в пределах сессии (не ротируется на каждый запрос), сравнение —
через `hash_equals`. Сбросить принудительно: `Session::regenerateCsrf()`.

### В HTML-форме

```html
<form method="post" action="/notes">
  <input type="hidden" name="_csrf" value="{{ csrf_token }}">
  ...
</form>
```

### В AJAX-запросе

Передавайте токен заголовком `X-CSRF-Token` (это делает `Adminx.Ajax` автоматически
для не-GET запросов):

```js
fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': token }, body: fd });
```

### Проверка на сервере

В контроллере — через обёртку (см. [Контроллеры](controllers.md)):

```php
public function store(array $params = array())
{
    if (($err = $this->csrfGuard()) !== null) { return $err; }  // читает X-CSRF-Token или _csrf
    // ...
}
```

Низкоуровнево:
```php
Session::verifyCsrf($token);   // бросит исключение при несовпадении
```

---

## Практика

- **Проверяйте CSRF на всех POST/PUT/DELETE** — на чтении (GET) не нужно.
- После успешного логина вызывайте `regenerateId(true)` (защита от session fixation).
- Для поддоменов держите cookie **host-only** (`setDomain('')`) — иначе сессия
  «утечёт» на другой хост.
- Не кладите в сессию большие объёмы данных — она в БД, это лишняя нагрузка.
