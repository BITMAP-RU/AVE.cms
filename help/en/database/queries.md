# Requests

← [Back to DB section](README.md)

All requests go through `DB::query()` (or its variants). The first argument is SQL with
[placeholders](placeholders.md), then - values.

---

## `DB::query(...)` → `DB_Result`

Basic method. Returns the object [`DB_Result`](results.md) from which data is read.

```php
$res  = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5);
$rows = $res->getAll();          // все строки
$one  = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

---

## Options that immediately return an array

These methods retrieve the data themselves—`->getAll()` does not need to be called.

| Method | Returns |
| --- | --- |
| `queryAssoc(...)` | An array of **associative** strings. |
| `queryObjects(...)` | Array of **objects** (`$row->field`). |
| `queryAllLists(...)` | Array of **numeric** arrays (strings as lists). |
| `queryFullColumns(...)` | Assoc. rows with keys of the form `table.column` (for JOIN of fields of the same name). |
| `queryOneColumn($column = null, ...)` | **Flat** array of values ​​of one column (the first one by default). |
| `queryFirstColumn(...)` | Flat array of first column values. |

```php
// плоский список id
$ids = DB::queryOneColumn('Id', 'SELECT Id FROM ' . $t . ' WHERE rubric_id = %i', 5);
// [1, 2, 3, ...]

// список объектов
$rows = DB::queryObjects('SELECT * FROM ' . $t . ' LIMIT 10');
echo $rows[0]->document_title;

// колонки таблицы
$cols = DB::columnList($t);   // ['Id', 'document_title', ...]
```

---

## Raw and unbuffered

| Method | Feature |
| --- | --- |
| `queryRaw(...)` | Buffered raw result (`DB_Result`). |
| `queryRawUnbuf(...)` | **Unbuffered** - for very large samples read by stream. |

```php
$res = DB::queryRawUnbuf('SELECT * FROM huge_table');
while ($row = $res->getAssoc()) { /* обрабатываем по одной строке */ }
```

---

## Service information after the request

```php
DB::insertId();       // ID последней вставки (или DB::$insert_id)
DB::affectedRows();   // затронуто строк последним запросом
DB::numRows();        // строк в последней выборке
DB::numAllRows();     // строк без учёта LIMIT (SELECT FOUND_ROWS())
DB::getTables($like); // список таблиц (опц. LIKE)
DB::columnList($t);   // список колонок таблицы
```

---

## Debugging requests

```php
DB::debugQuery('... %i ...', 5);   // во что развернётся запрос (без выполнения)
DB::getLastQuery();                // последний выполненный SQL
DB::getQueries();                  // все запросы за запрос-цикл
DB::getDebugReport();              // HTML-отчёт (время, количество)
DB::getCaller();                   // где был вызван запрос
```

```php
// быстро подсмотреть последний SQL при отладке
Debug::echo(DB::getLastQuery());
```

---

## Pagination with total number of lines

```php
$rows = DB::query(
    'SELECT SQL_CALC_FOUND_ROWS * FROM ' . $t . ' WHERE rubric_id = %i LIMIT %i, %i',
    $rubricId, $offset, $limit
)->getAll() ?: array();

$total = DB::numAllRows();   // всего строк без LIMIT
```
