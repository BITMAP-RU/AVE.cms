# CRUD helpers

← [Back to DB section](README.md)

Wrappers over `INSERT/UPDATE/DELETE/REPLACE` that themselves escape data - SQL
There is no need to write by hand. The data is transferred by the associative array `column => value`.

---

## INSERT

###`DB::Insert($table, $data)`
Insert line. Returns the result of a query; **ID is taken separately** via
`DB::insertId()`.

```php
DB::Insert($t, array(
    'document_title' => 'Кресло-коляска',
    'rubric_id'      => 5,
    'document_status'=> '1',
));
$id = (int) DB::insertId();   // или DB::$insert_id
```

### `DB::insertIgnore($table, $data)` - `INSERT IGNORE` (do not fall on the unique key double).
### `DB::Replace($table, $data)` - `REPLACE INTO`.

---

## INSERT … ON DUPLICATE KEY UPDATE

###`DB::insertUpdate($table, $data, $update = null)`
Insert or update if there is a unique key conflict. If `$update` is not specified -
the same fields that were inserted will be updated (a common pattern is “upsert settings/counter”).

```php
DB::insertUpdate('counters',
    array('key' => 'views', 'value' => 1),
    array('value' => DB::sqlEval('value + 1'))   // при дубле — инкремент
);
```

---

## UPDATE

###`DB::Update($table, $params, ...$where_args)`
`$params` - what to update (associative array), then - the condition with
[placeholders](placeholders.md).

```php
DB::Update($t, array('document_title' => 'Новый'), 'Id = %i', $id);

DB::Update($t,
    array('document_status' => '0', 'document_changed' => time()),
    'rubric_id = %i AND document_status = %s', 5, '1'
);
```



---

## DELETE

### `DB::Delete($table, ...$where_args)`

```php
DB::Delete($t, 'Id = %i', $id);
DB::Delete($t, 'rubric_id = %i AND document_deleted = %s', 5, '1');
```

> The condition is required - don't forget `WHERE`, otherwise delete everything.

---

## Batch insert

`insertOrReplace()` (and `Insert` through it) accepts an **array of strings**:

```php
DB::insertOrReplace('INSERT', 'log', array(
    array('doc_id' => 1, 'msg' => 'a'),
    array('doc_id' => 2, 'msg' => 'b'),
));
```

---

## Result of operations

```php
DB::insertId();       // ID последней вставки
DB::affectedRows();   // сколько строк реально затронуто (UPDATE/DELETE)
```

```php
DB::Update($t, array('x' => 1), 'id = %i', $id);
if (DB::affectedRows() === 0) { /* строка не найдена или значение не изменилось */ }
```

---

## Recipe: save (create or update)

```php
public static function save($id, array $data)
{
    if ((int) $id > 0) {
        DB::Update(self::table(), $data, 'id = %i', (int) $id);
        return (int) $id;
    }
    DB::Insert(self::table(), $data);
    return (int) DB::insertId();
}
```

> For consistency with business logic (search index, document cache) in the content
> use model methods (`Documents\Model::save`) rather than direct `DB::Update`.
