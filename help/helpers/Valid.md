# Valid — валидация

`App\Helpers\Valid` — валидация значений. Два режима:

1. **Пакетная проверка** массива по правилам → массив ошибок.
2. **Одиночные** статические проверки → `bool`.

```php
use App\Helpers\Valid;
```

---

## Режим 1: пакетная проверка

```php
Valid::check(array $data, array $rules, array $labels = []): array
```

Возвращает `['поле' => 'сообщение об ошибке']`; **пустой массив = всё валидно**.
Для поля берётся первая сработавшая ошибка.

**Формат правил:** строка через `|` (или массив правил). Параметры — через `:`,
несколько параметров — через `,`. Пустое значение при отсутствии `required`
пропускает остальные правила поля.

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

Доступные имена правил: `required`, `email`, `url`, `ip`, `numeric`,
`int`/`integer`, `float`, `alpha`, `alphaNum`, `slug`, `phone`, `date:FORMAT`,
`min:N`, `max:N`, `minLength:N`, `maxLength:N`, `length:N`, `regex:PATTERN`,
`inArray:a,b,c`.

Свои тексты ошибок:
```php
Valid::setMessages([
    'required'  => ':label обязательно для заполнения',
    'maxLength' => ':label — не длиннее :param символов',
]);
```

> ⚠️ **Нюанс:** правило `int`/`integer` в `check()` проверяет только «это целое»
> и **игнорирует** диапазон (`int:1,120` не сработает как границы). Для диапазона
> добавляйте отдельные `min:` / `max:`. Диапазон у `int()` работает только при
> прямом статическом вызове (см. ниже).

---

## Режим 2: одиночные проверки (`bool`)

| Метод | Проверяет |
| --- | --- |
| `required($v)` | Не пусто. |
| `email($v)` / `url($v)` / `ip($v)` / `ipv4($v)` / `ipv6($v)` | Форматы. |
| `numeric($v)` | Число (в т.ч. строкой). |
| `int($v, $min = null, $max = null)` | Целое **с диапазоном**. |
| `float($v)` | Дробное. |
| `alpha($v)` / `alphaNum($v)` | Только буквы / буквы+цифры. |
| `slug($v)` | Корректный ЧПУ-slug. |
| `minLength/maxLength/length($v, $n)` | Длина строки. |
| `regex($v, $pattern)` | По регулярке. |
| `inArray($v, array $allowed)` | Из белого списка. |
| `phone($v)` | Телефон. |
| `date($v, $format = 'Y-m-d')` | Дата по формату. |
| `min($v, $min)` / `max($v, $max)` | Числовые границы. |

```php
if (!Valid::email($email))          { $err['email'] = 'Неверный e-mail'; }
if (!Valid::int($age, 1, 120))      { $err['age']   = 'Возраст 1–120'; }   // диапазон работает
if (!Valid::inArray($sort, ['asc', 'desc'])) { $sort = 'asc'; }             // нормализация
```

---

## Рецепты

**Валидация формы в контроллере с ответом 422:**
```php
$errors = Valid::check($_POST, [
    'title'     => 'required|maxLength:255',
    'rubric_id' => 'required|int',
]);
if ($errors) { return $this->error('Проверьте поля', $errors); }
```

**Быстрая проверка одного значения перед использованием:**
```php
$email = Valid::email($raw) ? $raw : '';
```
