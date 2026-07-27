# Public debugging

AVE Public Debug is a secure diagnostic panel for a public website. She
is added to the completed HTML page and shows the data only to authorized users
to the user without changing the content for the average visitor.

The **AVE** button is located at the top of the public page. Rolled up
it shows response time, number of SQL queries, memory and number of errors.

## Tabs

| Tab | What shows |
| --- | --- |
| Review | HTTP status, duration, memory, SQL, active users and public modules. |
| Document | ID, category, template, publication, alias and basic data of the current document. |
| Data | Own structured values ​​from `Debug::panel()`. |
| Timeline | Stages of forming a public response and their duration. |
| SQL | Requests, execution time and place of call. |
| Cash | Cache read and write events for the current request. |
| HTTP | GET, POST, cookies and headers with secret key masking. |
| Session | System and public authorization, PHP session data. |
| Errors | PHP warning, notice and other intercepted errors of the current request. |
| Hooks | Raised events, handlers, priority, duration and exceptions. |
| Elements | Documents, blocks, queries, navigations and modular tags from which the page is assembled. |
| Files | PHP files included in the current request. |

An expanded panel blocks page scrolling. The active tab is remembered
in `sessionStorage`; `Escape` or close button returns to the page.

## Moving the panel

On a large screen, the panel can be dragged by the free area of its top
hats. The position is saved only in the current browser tab and automatically
limited to screen boundaries. The arrow button in the header returns the panel to
starting position.

Drag and drop is not triggered from buttons, tabs, links, fields, and code. On
On a mobile screen, the panel occupies the available area and is not dragged.

## What the page is made of

The **Items** tab shows managed entities in their actual order
output:

- current document;
- blocks;
- requests;
- navigation;
- results of tags of installed public modules.

The **Show on page** button enables visual mode. The inspector circles
each found area, without adding wrappers to the site's HTML:

| Type | Color |
| --- | --- |
| Document | green |
| Block | blue |
| Request | purple |
| Navigation | orange |
| Module | turquoise |

Clicking **Find** scrolls the page to the selected item. Button
**Edit** opens the corresponding section of the control panel in a new
tab. If you know the source tag, you can copy it using a separate button.A single logic gate is sometimes output in multiple places, e.g.
desktop and mobile theme containers. In the list it remains one entry, and the frame
appears at every visible area of it. Element without HTML safe area
remains in the list with the mark **No area**.

Sliders and other scripts can rearrange HTML after loading. In debug mode
the inspector places technical `data-*` anchors on the root elements in advance, therefore
The illumination remains even after cloning the cards. These attributes are not issued
ordinary visitors.

For a protected query with an active inspector, the engine does not use the full cache
pages and a cache of ready-made request elements. This is only necessary for the administrator to
see the actual composition of the current answer; regular visitors continue
receive cached pages and cards.

## Enable

Public debugging is configured in `configs/public.config.php`:

```php
'debug' => array(
    'enabled' => true,
    'groups' => array(),
),
```

- `enabled` - general switch. With `false` the panel is not accessible even
  administrator.
- `groups` — ID of **public user groups** to which Debug is allowed
  Toolbar without system rights.
- empty `groups` leaves access only to system users with the right
  `view_public_debug`.

For the administrator, the recommended option is empty `groups` and the right
**Public debug panel** in role settings. After logging into the control panel
go
to the site in the same browser: general system authorization will allow the panel to check
that's right.

Add public groups only for trusted employees. Regular
registered visitor should not have access to SQL, session and
structure of documents.

## Eigenvalues

To display the value in a separate tab, use only `Debug::panel()`:

```php
use App\Helpers\Debug;

Debug::panel($document, 'Документ после обработки');
Debug::panel($filters, 'Нормализованные фильтры');
Debug::panel($result, 'Ответ внешнего сервиса');
```

Open the **AVE → Data** panel. For each entry it will be shown:

- transmitted signature;
- value type;
- relative file path and calling line;
- a tree of an array or public properties of an object.

Detailed use cases and limitations are on the page
[Native Debug Calls](custom-data.md).

## When the panel does not appear

1. Check `debug.enabled` to `configs/public.config.php`.
2. Check the right `view_public_debug` of the current system role or public ID
   groups in `debug.groups`.
3. Make sure that you are logged into the control panel in the same browser and on the same
   same domain.
4. Verify that the response is a complete HTML page and contains `</body>`.
5. The panel is deliberately not added to Ajax, JSON, XML, downloadable files and mode
   `ONLYCONTENT`.
6. Direct route of the module, which itself completed the response without a common page shell,
   must also provide your own diagnostics or use
   deferred page.
7. In case of a fatal error, the panel may not have time to integrate before the final render;
   use the system error log.
8. The visual inspector only works for managed HTML in `body`.
   Contents of attributes, `head`, JavaScript, CSS, JSON and text editors
   deliberately not circled.

## Security

Toolbar recursively masks keys similar to `password`, `secret`, `token`,
`csrf`, `auth`, `cookie`, `api_key` and session ID. This is additional protection, and
not permission to pass secrets to the debugger.

Don't send the token to `Debug::panel()` with a simple line: scalar has no name
key by which it can be recognized. Transmit only secure data
or replace the secret with the value `***` in advance.

On production, keep `groups` empty and only issue `view_public_debug`
technical roles. After diagnosis, delete temporary calls that create
noise or process large amounts of data.
