# Valid - validation

`App\Helpers\Valid` - value validation. Two modes:

1. **Batch checking** an array according to the rules → array of errors.
2. **Single** static checks → `bool`.

```php
use App\Helpers\Valid;
```

---

## Mode 1: batch checking

```php
Valid::check(array $data, array $rules, array $labels = []): array
```

Returns `['field' => 'error message']`; **empty array = everything is valid**.
The first triggered error is taken for the field.

**Rules format:** string through `|` (or an array of rules). Parameters - via `:`,
several parameters - via `,`. Empty value if there is no `required`
skips the rest of the field rules.

```php
$errors = Valid::check($_POST, [
    'email' => 'required|email',
    'name'  => 'required|maxLength:255',
    'age'   => 'required|int|min:1|max:120',
    'slug'  => 'slug',
    'when'  => 'date:Y-m-d',
], [
    'email' => 'E-mail',        // подписи полей для сообщений
    'name'  => 'Имя',
]);

if ($errors) {
    // например, отдать в форму / вернуть 422
    return $this->error('Проверьте поля', $errors);
}
```

Available rule names: `required`, `email`, `url`, `ip`, `numeric`,
`int`/`integer`, `float`, `alpha`, `alphaNum`, `slug`, `phone`, `date:FORMAT`,
`min:N`, `max:N`, `minLength:N`, `maxLength:N`, `length:N`, `regex:PATTERN`,
`inArray:a,b,c`.

Your error texts:

```php
Valid::setMessages([
    'required'  => ':label обязательно для заполнения',
    'maxLength' => ':label — не длиннее :param символов',
]);
```

> ⚠️ **Nuance:** the rule `int`/`integer` in `check()` only checks “this is an integer”
> and **ignores** the range (`int:1,120` will not work as boundaries). For range
> add separate `min:` / `max:`. The range for `int()` only works when
> direct static call (see below).

---

## Mode 2: single checks (`bool`)

| Method | Checks |
| --- | --- |
| `required($v)` | Not empty. |
| `email($v)` / `url($v)` / `ip($v)` / `ipv4($v)` / `ipv6($v)` | Formats. |
| `numeric($v)` | Number (including a string). |
| `int($v, $min = null, $max = null)` | Integer **with range**. |
| `float($v)` | Fractional. |
| `alpha($v)` / `alphaNum($v)` | Only letters/letters+numbers. |
| `slug($v)` | Correct CNC slug. |
| `minLength/maxLength/length($v, $n)` | Line length. |
| `regex($v, $pattern)` | On a regular basis. |
| `inArray($v, array $allowed)` | From the white list. |
| `phone($v)` | Telephone. |
| `date($v, $format = 'Y-m-d')` | Date by format. |
| `min($v, $min)` / `max($v, $max)` | Numerical boundaries. |

```php
if (!Valid::email($email))          { $err['email'] = 'Неверный e-mail'; }
if (!Valid::int($age, 1, 120))      { $err['age']   = 'Возраст 1–120'; }   // диапазон работает
if (!Valid::inArray($sort, ['asc', 'desc'])) { $sort = 'asc'; }             // нормализация
```

---

## Recipes

**Form validation in controller with response 422:**

```php
$errors = Valid::check($_POST, [
    'title'     => 'required|maxLength:255',
    'rubric_id' => 'required|int',
]);
if ($errors) { return $this->error('Проверьте поля', $errors); }
```

**Quick check of one value before use:**

```php
$email = Valid::email($raw) ? $raw : '';
```
