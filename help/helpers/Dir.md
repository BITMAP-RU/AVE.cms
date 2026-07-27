# Dir — каталоги

`App\Helpers\Dir` — операции с директориями.

```php
use App\Helpers\Dir;
```

---

## Методы

### `create($dir, $chmod = 0775)`
Создать каталог рекурсивно (как `mkdir -p`).
```php
Dir::create(BASEPATH . '/uploads/2026/07');
```

### `exists($dir)` / `writable($path)` / `checkPerm($dir)`
Существует / доступен на запись / проверка прав.

### `scan($dir)`
Список содержимого каталога.
```php
foreach (Dir::scan($dir) as $entry) { ... }
```

### `size($path)`
Суммарный размер каталога в байтах (для отображения — через `Number::formatSize()`).
```php
echo Number::formatSize(Dir::size($uploads));   // '250 МБ'
```

### `copy($src, $dst)` — рекурсивное копирование.
### `delete($dir)` — удалить каталог **со всем содержимым** (осторожно).

---

## Рецепты

**Гарантировать каталог перед записью файла:**
```php
$dir = dirname($path);
if (!Dir::exists($dir)) { Dir::create($dir); }
File::putAtomic($path, $content);
```

> ⚠️ `Dir::delete()` рекурсивно удаляет всё внутри — проверяйте путь перед вызовом.
