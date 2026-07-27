# Permission

← [Back to the “Kernel” section](README.md)

`App\Common\Permission` - granular permissions for actions in the admin panel. Everyone
the module declares its rights; roles receive a set of rights; controller checks the right
before action.

```php
use App\Common\Permission;
```

---

## Checking eligibility

###`check($perm): bool`
Whether the current user has permission.

```php
if (!Permission::check('view_notes')) { Response::forbidden(); return ''; }

$canManage = Permission::check('manage_notes');   // передать во view для кнопок
```

### `checkAcp($perm)` - check in the context of ACP (panel).
### `perm($perm)` - low-level permission access.

Rights are string codes (`view_notes`, `manage_notes`, `execute_document_jobs`),
according to the convention: `view_*` - view, `manage_*` - change, special (`execute_*`).

Executable content is separated from regular editing. For example,
`manage_blocks` allows you to work with visual blocks, and
`manage_php_blocks` separately opens PHP mode. For operations with documents
`manage_document_jobs` is responsible for the job code, and `execute_document_jobs` is responsible for
its launch. These rights should not be granted to the regular content manager role.

---

## Declaring rights by a module

The module lists the rights in its `Permissions::items()` and in `module.php`
(`'permissions' => [...]`), and also starts them with SQL migration. Example from the module:

```php
class Permissions
{
    public static function items()
    {
        return array(
            array('code' => 'view_notes',   'group_code' => 'navigation',
                  'name' => 'Заметки: просмотр', 'sort_order' => 10),
            array('code' => 'manage_notes', 'group_code' => 'content',
                  'name' => 'Заметки: управление', 'sort_order' => 20),
        );
    }
}
```

Dynamic registration (less common):

```php
Permission::add('notes', $permissionArray, 'ti ti-notes', 46);
```

---

## Roles

| Method | Destination |
| --- | --- |
| `forRole($roleCode)` | Rights assigned to roles. |
| `setForRole($roleCode)` | Assign role rights. |
| `all()` / `get()` | All registered rights / current set. |
| `set($permissions)` / `setArray($permissions)` | Set set (string/array). |
| `syncRegistryToDb()` | Synchronize the register of rights in the database. |

---

## How is this related

```
module.php (permissions) + миграция → права появляются в системе
        │
        ▼
роль (в разделе «Роли и права») получает набор прав
        │
        ▼
Permission::check('view_notes') в контроллере/шаблоне → доступ разрешён/запрещён
```

---

## Practice

- **Every** controller action protect `Permission::check()` - do not rely
  only on hidden buttons in the UI.
- Pass `can_manage = Permission::check('manage_x')` to the template and using it
  show/hide controls.
- Permission to **read** and **change** post (`view_*` / `manage_*`) —
  This is how the “watch only” role is configured without crutches.
- The right checks **permission**, and `Auth::is($role)` checks **role**; prefer
  checking eligibility (more flexible and survives role changes).
