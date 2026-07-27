# SoftDelete — мягкое удаление

`App\Helpers\SoftDelete` — генерирует **фрагменты SQL-условий** для двух принятых в
проекте конвенций мягкого удаления:

- булев флаг: `is_deleted` / легаси `deleted` (`'1'`/`'0'`);
- nullable-метка: `deleted_at` (`NULL` = активна).

```php
use App\Helpers\SoftDelete;
```

---

## Методы

### `active($alias = null, $column = 'deleted_at')`
Условие «запись **не** удалена».
```php
$sql = 'SELECT * FROM ' . $t . ' u WHERE ' . SoftDelete::active('u');
// ... u.deleted_at IS NULL
```

### `deleted($alias = null, $column = 'deleted_at')`
Условие «запись удалена».

### `documentActive($alias = null)` / `documentDeleted($alias = null)`
Условия по конвенции **документов** (`document_deleted`).
```php
$where .= ' AND ' . SoftDelete::documentActive('d');
// ... d.document_deleted != '1'
```

### `condition($alias, $column, $deleted)`
Произвольное условие: собрать под конкретную колонку.

---

## Рецепт

```php
use App\Content\ContentTables;
use App\Helpers\SoftDelete;

$t = ContentTables::table('documents');
$rows = DB::query(
    'SELECT * FROM ' . $t . ' d WHERE d.rubric_id = %i AND ' . SoftDelete::documentActive('d'),
    $rubricId
)->getAll();
```

> Возвращает **строку** — подставляется прямо в `WHERE`. Значения не параметризуются
> (это статические условия), пользовательский ввод в них не попадает.
