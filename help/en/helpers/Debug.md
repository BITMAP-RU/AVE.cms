# Debug - debugging

`App\Helpers\Debug` - structured data of the public Debug Toolbar,
legacy dumps, time/memory measurements and SQL error output.

```php
use App\Helpers\Debug;
```

---

## Public Debug Toolbar

###`panel($var, $label = '')`

Stores the value only for the duration of the current request and displays it in
**AVE Public Debug → Data**. Prints nothing in HTML and returns the original
meaning.

```php
Debug::panel($document, 'Документ после обработки');
Debug::panel($filters, 'Фильтры каталога');
```

The panel displays the signature, type, value tree, file and call string.
Enabling, rights and security rules are described in the section
[Public Debugging](../debug/README.md).

## Legacy dumps

###`echo($var, $exit = false, $bg = null, $echo = true)`
Readable variable output; `$exit = true` - stop execution after output.

```php
Debug::echo($data);         // посмотреть
Debug::echo($data, true);   // посмотреть и die()
```

### `print(...)`, `exp(...)`, `_($var)`

Various formats for direct HTML output. Don't use them on a public page:
the visitor can see the diagnostics.

###`dump(...)`

Writes an HTML dump to the root `debug.html`; This is not a Toolbar tab.

---

## Performance measurements

### `startTime($name = '')` / `endTime($name = '')` - code section timer.
### `startMemory($name = '')` / `endMemory($name = '')` - memory measurement.
### `benchmark(callable $callable, array $params = [])` — run and measure the call.
### `elapsed($start)` — elapsed time from the mark.
### `getStatistic($type = null)` - statistics (time/memory/number of requests).

```php
Debug::startTime('render');
$html = renderPage();
Debug::endTime('render');           // выведет длительность

$res = Debug::benchmark(function () { return heavyQuery(); });
```

---

## Errors and tracing

| Method | Destination |
| --- | --- |
| `errorMsg(string $msg)` | Show error message. |
| `errorSql($header, $sql, $caller = null, $exit = false)` | Nicely display the SQL error. |
| `dumpTrace()` / `debugBacktrace()` | Stack trace. |
| `fileLines($filepath, $line, $highlight = true, $padding = 3)` | A file fragment around a line. |

---

## Recipes

**View controller input without changing the answer:**

```php
Debug::panel(Request::all(), 'HTTP-вход контроллера');
```

**Find bottleneck:**

```php
Debug::startTime('audit');
$report = Model::audit();
Debug::endTime('audit');
```

Remove temporary HTML dumps and noisy `panel()` calls before committing. Never
Do not pass passwords, tokens, or payment keys to the debugger.
