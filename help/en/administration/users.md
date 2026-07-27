# Users and access

← [To system management](README.md)

AVE.cms has two related but different tasks: employee access to the dashboard and
public visitor accounts.

## Panel users

The **Users** section stores employees. The user has a name, login, email,
phone, activity and role. The role determines the sections and actions available.

- the administrator cannot disable his own active account;
- the administrator should not deprive himself of his administrative role;
- disabling prohibits new entry, but preserves the document author and audit;
- deleting an employee should not destroy the content created by him.

The system administrator uses general authorization for the site and panel. After login
in one context he should not re-register or enter a password in
friend; access to the panel is still checked by a separate right `admin_panel`.

## Roles and rights

Rights are registered by the kernel and installed modules, then roles are assigned.
Issue the minimum set:

- view the section;
- data management;
- individual dangerous actions, if they are provided for by the module.

The content manager can allow documents of specific categories without opening the database,
system files, PHP code and module installation. System administrator role
retains full access.

## Site users

The **Site Users** section manages registration, personal account and
public profile. A public user can be a buyer, an author,
community member or subscriber - the section name does not bind the system
to the store.

Here are the settings:

- enable registration and group of new accounts;
- minimum password length and confirmation email;
- password reset;
- URL of login, registration, recovery and account pages;
- HTML templates for public forms;
- additional profile fields and their participation in registration/account;
- installed OAuth and SMS gates.

Assigned URLs are reserved in a public registry, so a document with such
alias cannot be created.

## Additional profile fields

A field has a type, label, mandatory, order, and display areas. Don't collect
data “for the future”: add only what is actually used in the profile,
orders or integrations.

Changing the workfield type requires migrating existing values. First
create a new field and transfer the data, then disable the old one.

## OAuth

Login via Yandex and VK ID comes as physically installed modules. After
The installation provider is displayed in the public authorization settings. OAuth is not
creates a separate user type: it links an external account to the same
public account and AVE.cms session.

## Login by phoneOne-time codes and phone identity are part of general authorization
AVE.cms, and specific SMS sending is enabled by the installed module. After
settings `Login via SMS (SMSC)`, configure the API key, send a test message
and only then enable the public form.

A verified phone number is unique. A new number can create a regular public
user in the group selected in the registration settings. The employee does not need
second account: if the same phone number is listed on his linked public account
profile, the SMS will open an existing shared session.
