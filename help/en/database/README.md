# Working with the database

The engine works with MySQL/MariaDB through the thin façade `DB` (a wrapper over `mysqli`).
The class is `App\Common\Db\DB`, but the short global alias `DB` is used everywhere.
The query result is returned as object `DB_Result`.

```php
$doc = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

## Section map

| Page | What about |
| --- | --- |
| [Table names (resolvers)](tables.md) | How to correctly get the table name (`ContentTables`, `SystemTables`, ...) instead of the hardcode. |
| [Placeholders](placeholders.md) | Full analysis of `%i %s %ss %li %hc …` with examples of each. |
| [Queries](queries.md) | `DB::query()` and variants (`queryAssoc`, `queryOneColumn` ...), debugging. |
| [Read result (DB_Result)](results.md) | `getAll / getAssoc / getValue / getOne …`, semantics and pitfalls. |
| [CRUD helpers](crud.md) | `Insert / Update / Delete / Replace / insertUpdate`, `insertId`, `affectedRows`. |
| [Transactions](transactions.md) | `startTransaction / commit / rollback`. |
| [Query cache](cache.md) | `setTtl / setCache / setTags / clearTags`. |
| [Safety and Shielding](security.md) | Injection protection, `escape / quote / safe`. |

---

## Connection

The connection is raised once at `system/bootstrap.php` (`DB::getInstance($config)`),
therefore, there is no need to initialize anything in controllers/models - right away
`DB::query(...)`.

```php
DB::connection('default');   // именованное соединение
DB::setPrefix('marketplace'); // префикс (обычно из настроек)
DB::mysqli();                 // «сырой» mysqli при необходимости
DB::version();                // версия сервера
DB::getDatabase();            // имя текущей БД
```

---

## Quick start

```php
use App\Content\ContentTables;
$t = ContentTables::table('documents');   // → 'marketplace_documents'

// список (массив ассоц. строк; на пустой выборке — false)
$items = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i ORDER BY Id DESC', 5)->getAll() ?: array();

// одна строка
$doc = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();

// один скаляр
$count = (int) DB::query('SELECT COUNT(*) FROM ' . $t)->getValue();

// вставка
DB::Insert($t, array('document_title' => 'Тест', 'rubric_id' => 5));
$id = (int) DB::insertId();

// обновление / удаление
DB::Update($t, array('document_title' => 'Новый'), 'Id = %i', $id);
DB::Delete($t, 'Id = %i', $id);
```

---

## Three rules

1. **Table name - via resolver** (`ContentTables::table('documents')`), not a string.
   → [tables.md](tables.md)
2. **Values - only through placeholders** (`%i`, `%s`, ...), never merge
   user input in SQL. → [placeholders.md](placeholders.md), [security.md](security.md)
3. **`getAll()` on an empty sample returns `false`**, and not `[]` - write `?: array()`.
   → [results.md](results.md)
