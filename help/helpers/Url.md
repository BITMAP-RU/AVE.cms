# Url — ссылки и редиректы

`App\Helpers\Url` — построение ссылок, ЧПУ-преобразование, сбор целей редиректа.

```php
use App\Helpers\Url;
```

> Большинство методов возвращают **строку-ссылку** — они не выполняют переход сами.
> Сам редирект делайте через `Request::redirect($url)` / заголовок `Location`.

---

## Построение ссылок

### `to($path = '')`
Нормализованный путь от корня сайта (всегда с ведущим `/`).
```php
Url::to('catalog/products');   // '/catalog/products'
Url::to('/media');             // '/media'
```

### `asset($path)` — то же, что `to()` (для статики).

### `uploads($path = '')`
Ссылка в каталог загрузок (базой служит константа `UPLOAD_URL`, по умолчанию `/uploads`).
```php
Url::uploads('articles/doc_37/1.jpg');   // '/uploads/articles/doc_37/1.jpg'
```

### `site()` / `home()`
Базовый URL сайта / ссылка на главную.

---

## ЧПУ и нормализация

### `rewrite($value)`
ЧПУ-преобразование значения — **работает только если включён `REWRITE_MODE`**,
иначе возвращает значение как есть.

### `prepare($url)` / `prepareUrl($url)` — нормализовать URL.
### `canonical($url)` — canonical-ссылку.

---

## Редиректы и реферер

| Метод | Возврат |
| --- | --- |
| `redirect($exclude = '')` | Строка-цель редиректа (можно исключить параметр). |
| `getRedirectLink($exclude = '')` | То же, без побочных эффектов. |
| `referer()` / `getRefererLink()` | Реферер. |
| `printLink()` | Ссылка версии для печати. |

```php
$back = Url::getRedirectLink();
// затем выполнить переход:
Request::redirect($back);
```

---

## Рецепты

**Ссылка на изображение товара в шаблоне:**
```php
$img = Url::uploads($product['image']);
```

**Кнопка «назад» после действия:**
```php
Request::redirect(Url::getRefererLink() ?: Url::home());
```
