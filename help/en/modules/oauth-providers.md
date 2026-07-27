# Login via VK ID and Yandex

← [Back to the “Modules” section](README.md)

Packages `vk_oauth` and `yandex_oauth` add external login methods to a single
public account AVE.cms. They share a common OAuth process with `state` and
short-lived session data; VK ID additionally uses PKCE.

## General setup procedure

1. Install the required package.
2. Create an OAuth application in the provider's account.
3. Copy the callback URL from the AVE.cms settings page without changes.
4. Enter the application ID and secret, then enable the provider.
5. Decide whether it is possible to create new accounts and link them using the same email.
6. Check the login in a private browser window.

Secrets are stored separately from regular settings in
`storage/secrets/modules/<code>.php`. Environment values ​​take precedence:

```text
VK_OAUTH_ENABLED
VK_OAUTH_CLIENT_ID
VK_OAUTH_CLIENT_SECRET
YANDEX_OAUTH_ENABLED
YANDEX_OAUTH_CLIENT_ID
YANDEX_OAUTH_CLIENT_SECRET
```

The account creation option allows user registration at the first external
entrance. Email matching links an external profile to an existing one
an account with the same address; enable it only when you trust your email,
returned by the provider.

An authorized user sees the enabled services in their personal account. For more
unlinked service, the **Link** button is displayed. She launches a separate
OAuth process and adds an external identifier to the current account; new
the user is not created. Already linked Yandex or VK is marked as
connected.

## VK ID

Public addresses:

```text
/auth/oauth/vk
/auth/oauth/vk/callback
```

The rights `view_vk_oauth` and `manage_vk_oauth` separate viewing and editing
settings.

## Yandex

Public addresses:

```text
/auth/oauth/yandex
/auth/oauth/yandex/callback
```

The rights `view_yandex_oauth` and `manage_yandex_oauth` separate viewing and editing
settings.

Callback depends on the current domain, base path and HTTPS. After moving the site
update it in your provider's account. Disabling the module hides the login button.
Uninstallation removes the provider's registration and its local secret, but retains
public user accounts.
