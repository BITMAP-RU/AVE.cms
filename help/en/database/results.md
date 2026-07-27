# Read the result - `DB_Result`

← [Back to DB section](README.md)

`DB::query()` returns the object `DB_Result`. The methods below extract data from it.
Class - `App\Common\Db\DB_Result`.

---

## Many lines

###`getAll()`
**All lines** is an array of associative arrays. Returns on an empty sample
**`false`**, not `[]`.

```php
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5)->getAll() ?: array();
foreach ($rows as $row) {
    echo $row['document_title'];
}
```

> ⚠️ Always write `?: array()` - otherwise `foreach` to `false` will give a warning.

---

## One line

| Method | Returns | Empty |
| --- | --- | --- |
| `getAssoc()` | Associative Array (`$row['field']`) | `false` |
| `getObject()` | Object (`$row->field`) | `false` |
| `getArray()` | Numeric array (`$row[0]`) | `false` |
| `getMixed()` | Both numeric and string keys | `false` |

They all read the **current** line and shift the internal pointer - can be called in
loop for stream processing.

```php
$doc = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
if ($doc) { echo $doc['document_title']; }

// потоково по одной строке (экономит память на больших выборках)
$res = DB::queryRawUnbuf('SELECT Id, document_title FROM ' . $t);
while ($row = $res->getAssoc()) { process($row); }
```

> There is no separate `getRow()` - for one line use `getAssoc()`.

---

## Single value (scalar)

###`getValue()`
First column of the **current** row; moves the pointer. Empty → `false`.

```php
$count = (int) DB::query('SELECT COUNT(*) FROM ' . $t)->getValue();
$title = DB::query('SELECT document_title FROM ' . $t . ' WHERE Id = %i', 42)->getValue();
```

###`getOne()`
First column of first row; **rewinds** the result to the beginning. Empty → `null`.

```php
$max = DB::query('SELECT MAX(position) FROM ' . $t)->getOne();
```

> `getValue()` moves the pointer (convenient for units), `getOne()` always takes
> the very first cell - the difference is important if you read the result several times.

---

## Navigation and metadata

| Method | Destination |
| --- | --- |
| `count()` / `numRows()` | Number of rows in the sample. |
| `numAllRows($result)` | Number of rows without `LIMIT` (`SQL_CALC_FOUND_ROWS`). |
| `dataSeek($i)` | Move the pointer to the line `$i`. |
| `numFields()` / `fetchFields()` / `fieldName($i)` | Field metadata. |
| `Close()` | Release the result. |

```php
$res = DB::query('SELECT * FROM ' . $t . ' LIMIT 10');
echo $res->count();        // 10
$res->dataSeek(0);         // вернуться к началу
while ($row = $res->getAssoc()) { ... }
```

---

## Cheat sheet “what to return”

| Need | Method |
| --- | --- |
| List of strings | `getAll()` (+ `?: array()`) |
| One line (associate) | `getAssoc()` |
| One line (object) | `getObject()` |
| Number/string with one value | `getValue()` |
| The very first cell | `getOne()` |
| Single column flat list | [`DB::queryOneColumn()`](queries.md) |
