# Request placeholders

← [Back to DB section](README.md)

Values are inserted into the request **only** through placeholders - like `QueryBuilder`
It screens data itself and protects against SQL injections. Values are passed
**positional arguments** (variadic), in the order of placeholders.

```php
DB::query('SELECT * FROM users WHERE id = %i AND status = %s', 10, 'active');
```

---

## Scalar

| Placeholder | Meaning | Substitution example |
| --- | --- | --- |
| `%s` | Escaped string (exact comparison) | `... = 'active'` |
| `%i` | Integer | `... = 10` |
| `%d` | Fractional number | `... = 12.50` |
| `%b` | Table/field name (in back quotes) | `` `documents` `` |
| `%t` | Timestamp → `'Y-m-d H:i:s'` | `'2026-07-14 12:30:00'` |
| `%l` | Raw value **without escaping** | as is - **caution** |

```php
DB::query('SELECT * FROM p WHERE title = %s', 'Кресло');   // точное совпадение
DB::query('SELECT * FROM p WHERE price >= %d', 1000.0);
DB::query('SELECT * FROM %b WHERE id = %i', 'products', 5); // %b — имя таблицы
```

---

## LIKE lines

| Placeholder | Wraps | For |
| --- | --- | --- |
| `%ss` | `%value%` | search by occurrence |
| `%ssb` | `value%` | begins with |
| `%sse` | `%value` | ends with |

```php
DB::query('SELECT * FROM p WHERE title LIKE %ss', $q);   // '%кресло%'
DB::query('SELECT * FROM p WHERE alias LIKE %ssb', 'ek'); // 'ek%'
```

> ⚠️ Common mistake: `%s` is an **exact** comparison (`= 'x'`), and `%ss` is
> wraps in `%...%` for `LIKE`. If you mix it up, the `=` query will not find anything.

---

## Arrays (for `IN (...)` and more)

| Placeholder | Meaning |
| --- | --- |
| `%ls` | Array of strings (escaped) |
| `%li` | Array of integers |
| `%ld` | Array of fractions |
| `%lb` | Array of table/field names |
| `%ll` | Array of raw values ​​|
| `%lt` | timestamps array |

```php
DB::query('SELECT * FROM p WHERE id IN (%li)', array(1, 2, 3));        // IN (1,2,3)
DB::query('SELECT * FROM p WHERE alias IN (%ls)', array('a', 'b'));    // IN ('a','b')
```

---

## Hash (key → value)

| Placeholder | Gluing | For |
| --- | --- | --- |
| `%hc` | `k=v, k=v` separated by commas | `SET` in UPDATE |
| `%ha` | `k=v AND k=v` | conditions by AND |
| `%ho` | `k=v OR k=v` | OR conditions |

```php
DB::query('UPDATE p SET %hc WHERE id = %i', array('title' => 'X', 'qty' => 5), 42);
DB::query('SELECT * FROM p WHERE %ha', array('rubric_id' => 5, 'active' => 1));
// ... WHERE `rubric_id`=5 AND `active`=1
```

> In practice, there is no need to write `%hc` by hand - it is used by [`DB::Update()`](crud.md).

---

## Sanitized (basic mode)

| Placeholder | Meaning |
| --- | --- |
| `%?` | Single value (type autodetection) |
| `%l?` | List of values ​​|
| `%ll?` | List of lists (batch insert) |

They are used by CRUD helpers (`Insert`, `insertOrReplace`) under the hood.

---

## Passing an array of arguments

If the SQL and arguments are collected in an array, expand via `call_user_func_array`:

```php
$args = array_merge(array($sql), $bindings);
$rows = call_user_func_array(array('DB', 'query'), $args)->getAll();
```

---

## Checking the result of substitution

To see what the request will turn into (without execution):

```php
echo DB::debugQuery('SELECT * FROM p WHERE id = %i AND t LIKE %ss', 5, 'кресло');
// SELECT * FROM p WHERE id = 5 AND t LIKE '%кресло%'
```
