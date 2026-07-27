# Dir - directories

`App\Helpers\Dir` - operations with directories.

```php
use App\Helpers\Dir;
```

---

## Methods

###`create($dir, $chmod = 0775)`
Create a directory recursively (like `mkdir -p`).

```php
Dir::create(BASEPATH . '/uploads/2026/07');
```

### `exists($dir)` / `writable($path)` / `checkPerm($dir)`
Exists/writable/permissions check.

###`scan($dir)`
List of directory contents.

```php
foreach (Dir::scan($dir) as $entry) { ... }
```

###`size($path)`
Total directory size in bytes (for display - via `Number::formatSize()`).

```php
echo Number::formatSize(Dir::size($uploads));   // '250 МБ'
```

### `copy($src, $dst)` - recursive copying.
### `delete($dir)` - delete the directory **with all contents** (carefully).

---

## Recipes

**Guarantee directory before writing file:**

```php
$dir = dirname($path);
if (!Dir::exists($dir)) { Dir::create($dir); }
File::putAtomic($path, $content);
```

> ⚠️ `Dir::delete()` recursively deletes everything inside - check the path before calling it.
