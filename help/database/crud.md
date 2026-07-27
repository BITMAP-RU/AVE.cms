# CRUD-хелперы

← [Назад к разделу БД](README.md)

Обёртки над `INSERT/UPDATE/DELETE/REPLACE`, которые сами экранируют данные — SQL
руками писать не нужно. Данные передаются ассоциативным массивом `колонка => значение`.

---

## INSERT

### `DB::Insert($table, $data)`
Вставить строку. Возвращает результат запроса; **ID берут отдельно** через
`DB::insertId()`.
```php
DB::Insert($t, array(
    'document_title' => 'Кресло-коляска',
    'rubric_id'      => 5,
    'document_status'=> '1',
));
$id = (int) DB::insertId();   // или DB::$insert_id
```

### `DB::insertIgnore($table, $data)` — `INSERT IGNORE` (не падать на дубле уникального ключа).
### `DB::Replace($table, $data)` — `REPLACE INTO`.

---

## INSERT … ON DUPLICATE KEY UPDATE

### `DB::insertUpdate($table, $data, $update = null)`
Вставить или обновить при конфликте уникального ключа. Если `$update` не задан —
обновятся те же поля, что вставлялись (частый паттерн «upsert настройки/счётчика»).
```php
DB::insertUpdate('counters',
    array('key' => 'views', 'value' => 1),
    array('value' => DB::sqlEval('value + 1'))   // при дубле — инкремент
);
```

---

## UPDATE

### `DB::Update($table, $params, ...$where_args)`
`$params` — что обновить (ассоц. массив), далее — условие с
[плейсхолдерами](placeholders.md).
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
> Условие обязательно — не забудьте `WHERE`, иначе удалите всё.

---

## Пакетная вставка

`insertOrReplace()` (и `Insert` через него) принимает **массив строк**:
```php
DB::insertOrReplace('INSERT', 'log', array(
    array('doc_id' => 1, 'msg' => 'a'),
    array('doc_id' => 2, 'msg' => 'b'),
));
```

---

## Результат операций

```php
DB::insertId();       // ID последней вставки
DB::affectedRows();   // сколько строк реально затронуто (UPDATE/DELETE)
```
```php
DB::Update($t, array('x' => 1), 'id = %i', $id);
if (DB::affectedRows() === 0) { /* строка не найдена или значение не изменилось */ }
```

---

## Рецепт: сохранить (создать или обновить)

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

> Для согласованности с бизнес-логикой (поисковый индекс, кеш документа) в контенте
> используйте методы моделей (`Documents\Model::save`), а не прямой `DB::Update`.
