# SoftDelete - soft deletion

`App\Helpers\SoftDelete` - generates **fragments of SQL conditions** for two accepted in
draft soft removal conventions:

- boolean flag: `is_deleted` / legacy `deleted` (`'1'`/`'0'`);
- nullable label: `deleted_at` (`NULL` = active).

```php
use App\Helpers\SoftDelete;
```

---

## Methods

###`active($alias = null, $column = 'deleted_at')`
The condition “record **not** deleted”.

```php
$sql = 'SELECT * FROM ' . $t . ' u WHERE ' . SoftDelete::active('u');
// ... u.deleted_at IS NULL
```

###`deleted($alias = null, $column = 'deleted_at')`
The “record deleted” condition.

### `documentActive($alias = null)` / `documentDeleted($alias = null)`
Conditions according to the convention **documents** (`document_deleted`).

```php
$where .= ' AND ' . SoftDelete::documentActive('d');
// ... d.document_deleted != '1'
```

###`condition($alias, $column, $deleted)`
Arbitrary condition: collect for a specific column.

---

## Recipe

```php
use App\Content\ContentTables;
use App\Helpers\SoftDelete;

$t = ContentTables::table('documents');
$rows = DB::query(
    'SELECT * FROM ' . $t . ' d WHERE d.rubric_id = %i AND ' . SoftDelete::documentActive('d'),
    $rubricId
)->getAll();
```

> Returns **string** - substituted directly into `WHERE`. Values are not parameterizable
> (these are static conditions), user input does not fall into them.
