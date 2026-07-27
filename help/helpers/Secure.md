# Secure — очистка ввода и XSS

`App\Helpers\Secure` — санитизация пользовательского ввода на **входе** (перед
сохранением/обработкой). Для безопасного **вывода** в HTML достаточно Twig `{{ }}`
или `Str::escape()`.

```php
use App\Helpers\Secure;
```

---

## Методы

### `sanitize($string, $trim = false)`
Базовая очистка: `FILTER_SANITIZE_STRING` + `trim` + `stripslashes` + `strip_tags`
+ замена части символов. Возвращает «плоскую» безопасную строку без тегов.
```php
Secure::sanitize('<b>Привет</b>  ');   // 'Привет'
```

### `cleanOut($string)`
Декодирует HTML-сущности и снимает слэши (обратная подготовка для повторного
использования значения).
```php
Secure::cleanOut('Цена &lt;100&gt;');   // 'Цена <100>'
```

### `cleanSanitize($string, $trim = false, $end_char = '…')`
`cleanOut` + `sanitize` в одном вызове (для «грязного» входа из legacy).

### `arrayXss($array, $stripslashes = false)`
Рекурсивная XSS-очистка **всего массива** (например, `$_POST`).
```php
$clean = Secure::arrayXss($_POST);
```

### `decodeXss($string)`
Декодировать ранее закодированное значение.

### `sanitizePhoneNumber($phone)`
Нормализовать телефон (убрать лишние символы).
```php
Secure::sanitizePhoneNumber('+7 (999) 123-45-67');   // '79991234567'
```

---

## Рецепты

**Очистить заголовок перед записью:**
```php
$title = Secure::sanitize(Request::postStr('title'), true);
```

**Массовая очистка формы:**
```php
$data = Secure::arrayXss($_POST);
```

> Не используйте `Secure::sanitize()` для полей, где HTML допустим (описания,
> WYSIWYG) — он вырежет теги. Там нужна отдельная HTML-очистка/белый список.
