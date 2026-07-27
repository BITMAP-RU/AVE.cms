# Имена таблиц (резолверы)

← [Назад к разделу БД](README.md)

Префиксы у разных доменов данных различаются (контент, системные таблицы админки,
таблицы модулей, публичная оболочка), поэтому **имя таблицы всегда получают через
резолвер**, а не пишут строкой. Это даёт правильный префикс и защиту от опечаток.

---

## Резолверы

| Класс | Домен | Пример результата |
| --- | --- | --- |
| `App\Content\ContentTables::table($s)` | Контент: документы, рубрики, поля | `marketplace_documents` |
| `App\Common\SystemTables::table($s)` | Системные таблицы админки (по whitelist) | `marketplace_admin_notes` |
| `App\Content\ExtensionTables::table($s)` | Таблицы ядра контента | `marketplace_todos` |
| `App\Content\PublicShellTables::table($s)` | Публичная оболочка (сессии и т. п.) | `marketplace_public_sessions` |

```php
use App\Content\ContentTables;

$t = ContentTables::table('documents');
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

---

## Валидация суффикса

- `ContentTables::table()` проверяет суффикс регуляркой `^[a-z0-9_]+$` и бросает
  исключение при недопустимом имени.
- `SystemTables::table()` дополнительно сверяет суффикс с **whitelist** — новую
  системную таблицу нужно сперва добавить в список разрешённых.

```php
ContentTables::table('documents');       // ok
ContentTables::table('users; DROP …');   // InvalidArgumentException
```

---

## Почему не хардкод

```php
// ПЛОХО — префикс зашит, сломается в другом окружении/домене данных
$rows = DB::query('SELECT * FROM marketplace_documents')->getAll();

// ХОРОШО — префикс подставит резолвер
$t = ContentTables::table('documents');
$rows = DB::query('SELECT * FROM ' . $t)->getAll();
```

---

## Частый приём: константа таблицы в модели

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

> Список доступных таблиц контента — в исходниках схемы; системные — в whitelist
> внутри `SystemTables`.
