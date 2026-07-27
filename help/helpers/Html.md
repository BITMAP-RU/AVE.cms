# Html — HTML-вывод

`App\Helpers\Html` — минификация/сжатие вывода, мелкие преобразования, сборка
query-строк.

```php
use App\Helpers\Html;
```

---

## Вывод и сжатие

### `compressHtml(string $data)` — минифицировать HTML (убрать лишние пробелы/переносы).
### `compress(string $data)` — сжать данные.
### `setGzip()` — включить gzip-сжатие вывода.
### `output(string $data)` — отдать данные в вывод.

```php
echo Html::compressHtml($renderedPage);
```

---

## Преобразования

### `nl2br($string)` — переводы строк → `<br>`.
### `htmlEncode($string)` / `htmlDecode($string)` — кодирование/декодирование сущностей.
```php
echo Html::nl2br(Str::escape($comment));   // безопасный многострочный вывод
```

---

## Query-строки

### `buildQuery($query)` — собрать query-строку из массива.
### `appendQuery($url, $query)` — добавить параметры к URL (с учётом существующих).
```php
$next = Html::appendQuery('/catalog?rubric=5', ['page' => 2]);
// '/catalog?rubric=5&page=2'
```

---

## Рецепты

**Ссылка на следующую страницу с сохранением фильтров:**
```php
$href = Html::appendQuery(Request::path() . '?' . Request::queryString(), ['page' => $page + 1]);
```

> Для экранирования отдельных значений — `Str::escape()` или Twig `{{ }}`.
