# Table names (resolvers)

← [Back to DB section](README.md)

Prefixes differ for different data domains (content, admin system tables,
module tables, public shell), so **table name is always obtained via
resolver**, rather than writing it as a line. This gives the correct prefix and protection against typos.

---

## Resolvers

| Class | Domain | Example result |
| --- | --- | --- |
| `App\Content\ContentTables::table($s)` | Content: documents, headings, fields | `marketplace_documents` |
| `App\Common\SystemTables::table($s)` | Administrator system tables (by whitelist) | `marketplace_admin_notes` |
| `App\Content\ExtensionTables::table($s)` | Core Content Tables | `marketplace_todos` |
| `App\Content\PublicShellTables::table($s)` | Public shell (sessions, etc.) | `marketplace_public_sessions` |

```php
use App\Content\ContentTables;

$t = ContentTables::table('documents');
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

---

## Suffix validation

- `ContentTables::table()` checks the suffix with the regular `^[a-z0-9_]+$` and throws
  exception for invalid name.
- `SystemTables::table()` additionally checks the suffix with **whitelist** - new
  The system table must first be added to the allowed list.

```php
ContentTables::table('documents');       // ok
ContentTables::table('users; DROP …');   // InvalidArgumentException
```

---

## Why not hardcode

```php
// ПЛОХО — префикс зашит, сломается в другом окружении/домене данных
$rows = DB::query('SELECT * FROM marketplace_documents')->getAll();

// ХОРОШО — префикс подставит резолвер
$t = ContentTables::table('documents');
$rows = DB::query('SELECT * FROM ' . $t)->getAll();
```

---

## Frequent trick: table constant in the model

```php
class Model
{
    public static function documentsTable() { return ContentTables::table('documents'); }
    public static function rubricsTable()   { return ContentTables::table('rubrics'); }

    public static function one($id)
    {
        return DB::query(
            'SELECT d.*, r.rubric_title FROM ' . self::documentsTable() . ' d'
            . ' LEFT JOIN ' . self::rubricsTable() . ' r ON r.Id = d.rubric_id'
            . ' WHERE d.Id = %i',
            (int) $id
        )->getAssoc();
    }
}
```

> The list of available content tables is in the schema sources; system - in whitelist
> inside `SystemTables`.
