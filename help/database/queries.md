# Запросы

← [Назад к разделу БД](README.md)

Все запросы идут через `DB::query()` (или его варианты). Первый аргумент — SQL с
[плейсхолдерами](placeholders.md), далее — значения.

---

## `DB::query(...)` → `DB_Result`

Базовый метод. Возвращает объект [`DB_Result`](results.md), с которого читают данные.

```php
$res  = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5);
$rows = $res->getAll();          // все строки
$one  = DB::query('SELECT * FROM ' . $t . ' WHERE Id = %i', 42)->getAssoc();
```

---

## Варианты, возвращающие сразу массив

Эти методы сами извлекают данные — `->getAll()` вызывать не нужно.

| Метод | Возвращает |
| --- | --- |
| `queryAssoc(...)` | Массив **ассоциативных** строк. |
| `queryObjects(...)` | Массив **объектов** (`$row->field`). |
| `queryAllLists(...)` | Массив **числовых** массивов (строки как списки). |
| `queryFullColumns(...)` | Ассоц. строки с ключами вида `table.column` (при JOIN одноимённых полей). |
| `queryOneColumn($column = null, ...)` | **Плоский** массив значений одного столбца (по умолчанию — первого). |
| `queryFirstColumn(...)` | Плоский массив значений первого столбца. |

```php
// плоский список id
$ids = DB::queryOneColumn('Id', 'SELECT Id FROM ' . $t . ' WHERE rubric_id = %i', 5);
// [1, 2, 3, ...]

// список объектов
$rows = DB::queryObjects('SELECT * FROM ' . $t . ' LIMIT 10');
echo $rows[0]->document_title;

// колонки таблицы
$cols = DB::columnList($t);   // ['Id', 'document_title', ...]
```

---

## Сырые и небуферизованные

| Метод | Особенность |
| --- | --- |
| `queryRaw(...)` | Буферизованный «сырой» результат (`DB_Result`). |
| `queryRawUnbuf(...)` | **Небуферизованный** — для очень больших выборок, читаемых потоково. |

```php
$res = DB::queryRawUnbuf('SELECT * FROM huge_table');
while ($row = $res->getAssoc()) { /* обрабатываем по одной строке */ }
```

---

## Служебные сведения после запроса

```php
DB::insertId();       // ID последней вставки (или DB::$insert_id)
DB::affectedRows();   // затронуто строк последним запросом
DB::numRows();        // строк в последней выборке
DB::numAllRows();     // строк без учёта LIMIT (SELECT FOUND_ROWS())
DB::getTables($like); // список таблиц (опц. LIKE)
DB::columnList($t);   // список колонок таблицы
```

---

## Отладка запросов

```php
DB::debugQuery('... %i ...', 5);   // во что развернётся запрос (без выполнения)
DB::getLastQuery();                // последний выполненный SQL
DB::getQueries();                  // все запросы за запрос-цикл
DB::getDebugReport();              // HTML-отчёт (время, количество)
DB::getCaller();                   // где был вызван запрос
```

```php
// быстро подсмотреть последний SQL при отладке
Debug::echo(DB::getLastQuery());
```

---

## Пагинация с общим числом строк

```php
$rows = DB::query(
    'SELECT SQL_CALC_FOUND_ROWS * FROM ' . $t . ' WHERE rubric_id = %i LIMIT %i, %i',
    $rubricId, $offset, $limit
)->getAll() ?: array();

$total = DB::numAllRows();   // всего строк без LIMIT
```
