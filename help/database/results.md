# Чтение результата — `DB_Result`

← [Назад к разделу БД](README.md)

`DB::query()` возвращает объект `DB_Result`. Методы ниже извлекают из него данные.
Класс — `App\Common\Db\DB_Result`.

---

## Много строк

### `getAll()`
**Все строки** — массив ассоциативных массивов. На пустой выборке возвращает
**`false`**, а не `[]`.
```php
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5)->getAll() ?: array();
foreach ($rows as $row) {
    echo $row['document_title'];
}
```
> ⚠️ Всегда пишите `?: array()` — иначе `foreach` по `false` даст предупреждение.

---

## Одна строка

| Метод | Возвращает | Пусто |
| --- | --- | --- |
| `getAssoc()` | Ассоциативный массив (`$row['field']`) | `false` |
| `getObject()` | Объект (`$row->field`) | `false` |
| `getArray()` | Числовой массив (`$row[0]`) | `false` |
| `getMixed()` | И числовые, и строковые ключи | `false` |

Все они читают **текущую** строку и сдвигают внутренний указатель — можно вызывать в
цикле для потоковой обработки.

```php
$doc = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
if ($doc) { echo $doc['document_title']; }

// потоково по одной строке (экономит память на больших выборках)
$res = DB::queryRawUnbuf('SELECT Id, document_title FROM ' . $t);
while ($row = $res->getAssoc()) { process($row); }
```

> Отдельного `getRow()` **нет** — для одной строки используйте `getAssoc()`.

---

## Одно значение (скаляр)

### `getValue()`
Первый столбец **текущей** строки; сдвигает указатель. Пусто → `false`.
```php
$count = (int) DB::query('SELECT COUNT(*) FROM ' . $t)->getValue();
$title = DB::query('SELECT document_title FROM ' . $t . ' WHERE Id = %i', 42)->getValue();
```

### `getOne()`
Первый столбец первой строки; **перематывает** результат к началу. Пусто → `null`.
```php
$max = DB::query('SELECT MAX(position) FROM ' . $t)->getOne();
```

> `getValue()` двигает указатель (удобно для агрегатов), `getOne()` всегда берёт
> самую первую ячейку — разница важна, если читаете результат несколько раз.

---

## Навигация и метаданные

| Метод | Назначение |
| --- | --- |
| `count()` / `numRows()` | Число строк в выборке. |
| `numAllRows($result)` | Число строк без `LIMIT` (`SQL_CALC_FOUND_ROWS`). |
| `dataSeek($i)` | Переместить указатель на строку `$i`. |
| `numFields()` / `fetchFields()` / `fieldName($i)` | Метаданные полей. |
| `Close()` | Освободить результат. |

```php
$res = DB::query('SELECT * FROM ' . $t . ' LIMIT 10');
echo $res->count();        // 10
$res->dataSeek(0);         // вернуться к началу
while ($row = $res->getAssoc()) { ... }
```

---

## Шпаргалка «что вернуть»

| Нужно | Метод |
| --- | --- |
| Список строк | `getAll()` (+ `?: array()`) |
| Одна строка (ассоц.) | `getAssoc()` |
| Одна строка (объект) | `getObject()` |
| Число/строка одним значением | `getValue()` |
| Самая первая ячейка | `getOne()` |
| Плоский список одного столбца | [`DB::queryOneColumn()`](queries.md) |
