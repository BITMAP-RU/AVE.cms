# Работа с базой данных

Движок работает с MySQL/MariaDB через тонкий фасад `DB` (обёртка над `mysqli`).
Класс — `App\Common\Db\DB`, но везде используется короткий глобальный алиас `DB`.
Результат запроса возвращается объектом `DB_Result`.

```php
$doc = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

## Карта раздела

| Страница | О чём |
| --- | --- |
| [Имена таблиц (резолверы)](tables.md) | Как правильно получать имя таблицы (`ContentTables`, `SystemTables`, …) вместо хардкода. |
| [Плейсхолдеры](placeholders.md) | Полный разбор `%i %s %ss %li %hc …` с примерами каждого. |
| [Запросы](queries.md) | `DB::query()` и варианты (`queryAssoc`, `queryOneColumn` …), отладка. |
| [Чтение результата (DB_Result)](results.md) | `getAll / getAssoc / getValue / getOne …`, семантика и подводные камни. |
| [CRUD-хелперы](crud.md) | `Insert / Update / Delete / Replace / insertUpdate`, `insertId`, `affectedRows`. |
| [Транзакции](transactions.md) | `startTransaction / commit / rollback`. |
| [Кеш запросов](cache.md) | `setTtl / setCache / setTags / clearTags`. |
| [Безопасность и экранирование](security.md) | Защита от инъекций, `escape / quote / safe`. |

---

## Подключение

Соединение поднимается один раз в `system/bootstrap.php` (`DB::getInstance($config)`),
поэтому в контроллерах/моделях ничего инициализировать не нужно — сразу
`DB::query(...)`.

```php
DB::connection('default');   // именованное соединение
DB::setPrefix('marketplace'); // префикс (обычно из настроек)
DB::mysqli();                 // «сырой» mysqli при необходимости
DB::version();                // версия сервера
DB::getDatabase();            // имя текущей БД
```

---

## Быстрый старт

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

## Три правила

1. **Имя таблицы — через резолвер** (`ContentTables::table('documents')`), не строкой.
   → [tables.md](tables.md)
2. **Значения — только через плейсхолдеры** (`%i`, `%s`, …), никогда не склеивать
   пользовательский ввод в SQL. → [placeholders.md](placeholders.md), [security.md](security.md)
3. **`getAll()` на пустой выборке возвращает `false`**, а не `[]` — пишите `?: array()`.
   → [results.md](results.md)
