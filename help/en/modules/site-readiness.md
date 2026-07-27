# Site readiness

← [To the modular system](README.md)

The **Site Readiness** module collects a single report before publishing the project. He
does not change documents, templates and files: automatic checks only read
configuration and indicate the screen where to fix the problem.

## Installation

Open **Modules → Management**, find “Site Readiness” and click
**Install**. After installation the following will appear:

- item **Modules → Site readiness**;
- widget `6/12` on the dashboard next to file security;
- viewing, launching and management rights;
- notification if the latest report contains critical errors.

When uninstalling, the history, confirmations and settings of the module are deleted. Simple
turning off saves data.

## Profiles

On the **Profile** tab, select the purpose of the project:

| Profile | When to use |
| --- | --- |
| Content site | Pages, articles, news, forms and personal account. |
| Catalog | Content site with a structured catalog. |
| Shop | Catalog, shopping cart, orders and payment methods. |
| Own | Only explicitly enabled check groups. |

The absence of a shopping cart is not considered a content site error. For your own
profile, you can separately enable system, security, content, navigation,
media, modules, catalog, sales and manual checklist.

## Report

The **Run check** button creates a new, unchangeable report. States:

- **Error** - a critical obstacle, the site is not considered ready;
- **Attention** - setting or manual action requires verification;
- **Tip** - optional improvement;
- **Passed** — automatic check is successful;
- **Confirmed**—the manual checklist item has been accepted by the administrator.

The rating helps compare runs, but does not eliminate critical errors. Report
can be filtered, opened from history and downloaded in JSON.

## Manual checklist

Manual items confirm visual check, form operation, backup,
analytics and test order. The check mark button saves the user and time.
Pressing it again clears the confirmation and creates an updated report.

## Checks for another module

A package can add its own checks via the `site_readiness.checks` hook:

```php
Hooks::add('site_readiness.checks', function ($payload) {
    $payload['checks'][] = array(
        'code' => 'reviews.configuration',
        'group' => 'modules',
        'title' => 'Модуль отзывов настроен',
        'description' => 'Проверяет обязательные параметры публикации.',
        'profiles' => array('content', 'catalog', 'commerce', 'custom'),
        'manual' => false,
        'fix_url' => '/modules/reviews',
        'handler' => function () {
            $ready = ReviewsSettings::isReady();
            return array(
                'status' => $ready ? 'passed' : 'warning',
                'message' => $ready ? 'Настройки заполнены' : 'Заполните получателя',
                'details' => array(),
            );
        },
    );

    return $payload;
});
```

The verification code must be stable and unique. Valid states
automatic processor: `passed`, `error`, `warning`, `recommendation`.
A handler exception is logged as a specific check failure and does not break
the rest of the report.
