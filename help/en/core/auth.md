# Authorization (Auth)

← [Back to the “Kernel” section](README.md)

`App\Common\Auth` serves two related contexts of the same person:
system role of the panel and public profile of the site. Methods without a prefix include
access to the control panel, methods with the prefix `public*` - to profile, content, cart
and orders. The associated employee enters both circuits in one session; access
for a public user in the control panel is configured in its editor.

```php
use App\Common\Auth;
```

---

## Control panel administrator

| Method | Return |
| --- | --- |
| `check()` | Is the admin authorized (`bool`). |
| `user()` | Array of user data (or empty). |
| `id()` | User ID. |
| `role()` | Role code. |
| `is($role)` | Does the role match? |
| `systemUser()` | Full "system" user. |
| `systemUserCan($permission)` | Does he have the right? |
| `attempt($identifier, $password, array $options = [])` | Login attempt. |
| `login(array $user, array $options = [])` | Login (after verification). |
| `logout(array $options = [])` | Exit. |
| `ensureBrowserToken(array $options = [])` | Persistent "remember me" token. |

```php
if (!Auth::check()) { Response::unauthorized(); return ''; }

$userId = (int) Auth::id();
$name   = Auth::user()['name'] ?? '';

if (Auth::is('admin')) { /* только для роли admin */ }
```

Entrance:

```php
if (Auth::attempt($login, $password, array('remember' => true))) {
    // успех — сессия установлена
} else {
    // неверные данные / троттлинг (см. LoginThrottle)
}
```

> Check **specific rights** via [`Permission::check()`](permissions.md),
> and not just by role - it’s more flexible and takes into account the rights of modules.

---

## Site visitor (public context)

Standard public groups for a clean installation: `1` - administrators, `2` -
guests, `3` - moderators, `4` - site users. Public
the group determines access to the site content; control panel rights are determined by the associated
systemic role.

| Method | Return |
| --- | --- |
| `publicCheck()` | Is the visitor authorized? |
| `publicUser()` | Visitor data. |
| `publicAttempt($identifier, $password, $remember = false)` | Login to the site. |
| `publicLogout()` | Exit. |
| `publicRegistrationEnabled()` | Is registration allowed? |
| `publicBootstrap()` | Raise the public authorization context. |

```php
if (Auth::publicCheck()) {
    $customer = Auth::publicUser();
}
```

With `$remember = true` the browser receives a random 256-bit token. In the database
Only its SHA-256, creation time, absolute expiration and last
activity. A successful recovery changes the token; reusing old
values are rejected. Exiting removes both the cookie and the server entry.

## Where to manage registration

Email registration is part of AVE.cms and does not require a separate module.
Open `System -> Site Users -> Registration`. On one page
configured:

- registration availability and allowed methods: email, phone, or both;
- activation method for accounts created by email;
- the group into which the new user will be assigned;
- basic form fields, password length and terms of one-time links;
- prohibited email and domains;
- voluntary creation of an account when placing an order;
- agreement with the policy and a letter in which the buyer sets a password.

The markup of public forms is located in the adjacent `Login Pages` tab.
Additional registration and profile fields are collected in the `Profile Fields` tab.
VK ID, Yandex and SMS login are additional installable methods
login, but create or link the same site user account.

A phone account can exist without an email. Creating one requires an installed,
configured SMS provider that allows registration. The confirmed number is stored
in a normalized international format and is unique. The user logs in with a
one-time SMS code. An email can be added later in the profile, but email login
and password recovery become available only after confirmation. The field may
remain empty when email is not needed.

## Personal account pages

Login, registration, access recovery and account addresses are configured in
`System -> Site Users -> Login Pages`. They are edited there
Twig templates. In a clean system the following addresses are used:

| Page | Address |
| --- | --- |
| Cabinet overview | `/personal/overview` |
| Profile | `/personal` |
| Change password | `/personal/password` |
| Orders | `/personal/orders` |
| Favorites | `/personal/favorites` |
| Viewed Products | `/personal/viewed` |

The account overview shows the data of the current user and, if installed
Commerce, number of orders, paid orders, favorites and latest
orders. Commerce remains optional: without it, the review does not crash and serves
quickly jump to your profile.

The page template can be stored in the control panel or in the active theme. Priority of sources:

1. template saved in the control panel;
2.`templates/<theme>/views/system_auth/<page>.twig`;
3. standard template `system/App/Frontend/Auth/view/<page>.twig`.

Available for review are `user`, `auth_urls`, `orders_total`, `orders_paid`,
`favorites_count` and array `orders`. Addresses should be taken from `auth_urls`, and not
enter in lines: the administrator can change them in the settings.

## Registration after orderIf the cart module is installed, a new customer can place an order as a guest
or enable the creation of a personal account. For an already known email, the form will offer
log in without losing your current cart. A new email will require separate consent
with the data processing policy.

After a successful order, the system associates the order with a new account, opens this
account in the current browser and sends a one-time password setting link. Password
is not conveyed in a letter. Turning off the script does not block guest checkout.

The letter templates available are `user.firstname`, `user.lastname`, `user.email`,
`order.id`, `order.total`, `access_url` and `expires_hours`. Twig error is being checked
before saving the settings.

---

## Practice

- In **System -> Users**, select several accounts to enable or disable their
  access in one operation. The current administrator cannot be selected, so a
  bulk action cannot disable the active administrator session.
- In the panel controller, `Auth::id()` is usually sufficient for the “action author” and
  `Permission::check()` for access.
- Don't confuse the identifiers: `Auth::user()` returns the RBAC system entry,
  `Auth::publicUser()` - linked public profile.
- Session and cookies are shared between the site and the panel (host-only), so the login is saved when
  transitions - see [Sessions and CSRF](sessions-csrf.md).
- An authorized employee automatically logs in on public pages:
  profile, cart and order history use his linked public record.
- Do not read or write cookie `auth` from the module directly. Use
  `Auth::publicAttempt()`, `Auth::publicLoginById()` and `Auth::publicLogout()`.
