# Security and shielding

← [Back to DB section](README.md)

The main protection against SQL injections is **placeholders**. Manual screening is only necessary in
in rare cases (dynamic SQL that cannot be expressed by a placeholder).

---

## Rule #1 - placeholders

```php
// ХОРОШО — значение экранируется QueryBuilder
DB::query('SELECT * FROM p WHERE title = %s AND rubric_id = %i', $title, $rid);

// ОПАСНО — прямая склейка пользовательского ввода
DB::query("SELECT * FROM p WHERE title = '$title'");   // ← SQL-инъекция
```

The full list of placeholders is [placeholders.md](placeholders.md).

---

## Manual escaping (when you can’t do without it)

| Method | What does |
| --- | --- |
| `DB::escape($value)` | Escape the string (without quotes). |
| `DB::quote($value)` | Escape **and** wrap in quotes. |
| `DB::safe($value)` | Safe casting. |

```php
$name = DB::quote($userInput);         // 'экранированное_значение'
DB::query('... WHERE name = ' . $name);
```

> If you can express it through `%s`/`%i`/`%li`, do it, and not manually `quote`.

---

## Table and field names

Identifiers (table/column names) **cannot** be parameterized as values -
for them there are `%b` / `%lb` (wrapped in backquotes) and
[table name resolvers](tables.md).

```php
DB::query('SELECT * FROM %b WHERE %b = %i', $table, $column, $id);
```

If the field name comes from outside (for example, “search by selected field”) - **check with
whitelist** of valid fields before substitution, even with `%b`.

```php
$allowed = array('title', 'article', 'alias');
if (!in_array($field, $allowed, true)) { $field = 'title'; }
DB::query('SELECT * FROM p WHERE %b LIKE %ss', $field, $q);
```

---

## `%l` - raw value

`%l` inserts the value **as is**, without escaping. Use only for
trusted, software-generated fragments (for example, pre-assembled
conditions), never for user input.

---

## Checklist

- [ ] All values are through placeholders (`%i`, `%s`, `%ss`, `%li`, ...).
- [ ] Table names - via resolvers; field names - via `%b` + whitelist.
- [ ] No concatenation of `$_GET/$_POST` into an SQL string.
- [ ] `%l`/`safe` - only for trusted data.
- [ ] On a post that changes state (POST) - CSRF check at the controller level.
