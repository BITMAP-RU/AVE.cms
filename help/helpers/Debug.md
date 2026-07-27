# Debug — отладка

`App\Helpers\Debug` — структурированные данные публичного Debug Toolbar,
legacy-дампы, замеры времени/памяти и вывод SQL-ошибок.

```php
use App\Helpers\Debug;
```

---

## Публичный Debug Toolbar

### `panel($var, $label = '')`

Сохраняет значение только на время текущего запроса и показывает его в
**AVE Public Debug → Данные**. Ничего не печатает в HTML и возвращает исходное
значение.

```php
Debug::panel($document, 'Документ после обработки');
Debug::panel($filters, 'Фильтры каталога');
```

В панели отображаются подпись, тип, дерево значения, файл и строка вызова.
Включение, права и правила безопасности описаны в разделе
[Публичная отладка](../debug/README.md).

## Legacy-дампы

### `echo($var, $exit = false, $bg = null, $echo = true)`
Читаемый вывод переменной; `$exit = true` — остановить выполнение после вывода.
```php
Debug::echo($data);         // посмотреть
Debug::echo($data, true);   // посмотреть и die()
```

### `print(...)`, `exp(...)`, `_($var)`

Разные форматы прямого HTML-вывода. Не используйте их на публичной странице:
диагностику может увидеть посетитель.

### `dump(...)`

Записывает HTML-дамп в корневой `debug.html`; это не вкладка Toolbar.

---

## Замеры производительности

### `startTime($name = '')` / `endTime($name = '')` — таймер участка кода.
### `startMemory($name = '')` / `endMemory($name = '')` — замер памяти.
### `benchmark(callable $callable, array $params = [])` — прогнать и замерить вызов.
### `elapsed($start)` — прошедшее время от метки.
### `getStatistic($type = null)` — статистика (время/память/кол-во запросов).

```php
Debug::startTime('render');
$html = renderPage();
Debug::endTime('render');           // выведет длительность

$res = Debug::benchmark(function () { return heavyQuery(); });
```

---

## Ошибки и трассировка

| Метод | Назначение |
| --- | --- |
| `errorMsg(string $msg)` | Показать сообщение об ошибке. |
| `errorSql($header, $sql, $caller = null, $exit = false)` | Красиво вывести SQL-ошибку. |
| `dumpTrace()` / `debugBacktrace()` | Трассировка стека. |
| `fileLines($filepath, $line, $highlight = true, $padding = 3)` | Фрагмент файла вокруг строки. |

---

## Рецепты

**Посмотреть вход контроллера без изменения ответа:**
```php
Debug::panel(Request::all(), 'HTTP-вход контроллера');
```

**Найти узкое место:**
```php
Debug::startTime('audit');
$report = Model::audit();
Debug::endTime('audit');
```

Перед коммитом удаляйте временные HTML-дампы и шумные `panel()`-вызовы. Никогда
не передавайте в отладчик пароли, токены и платёжные ключи.
