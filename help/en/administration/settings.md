#Settings

← [To system management](README.md)

The settings section is divided into separate pages. Core stores only common ones
parameters; payment keys, OAuth and other secrets belong to those being installed
modules.

## Basic

Here you can find the name and address of the site, mail, document parameters, bread
crumbs and other general public runtime values. After changing, check
site page, sending a test letter and canonical URL.

### Development mode

In the 'Site Access' group you can temporarily close the public part. In this mode:

- documents, public pages of modules, sitemap and API return HTTP 503;
- the answer contains `Retry-After`, `X-Robots-Tag: noindex, nofollow, noarchive`
  and does not go into the public cache;
- visit analytics and background web tasks for a closed request do not start;
- an employee with the right “View site in development mode” sees the real site
  and a small service indicator;
- the control panel and its routes continue to work.

An administrator with full access gains the right automatically. For the editor or
Assign a content manager in the 'Roles and Rights' section. Check the plug in
in a separate browser without administrative authorization. After completion of work
Be sure to return the `Site open` mode.

Direct static files, such as CSS and images from `uploads`, this mode does not
hides: they are needed to preview the employee. To close files from direct
access requires a separate web server policy or private storage.

The letter to a new user and the signature of the letter have safe values according to
default, but before enabling registration they need to be adapted to the project.

## Interface

Controls the order and visibility of the left menu and dashboard widgets. This is personalization
ideas, not a system of rights. Route protection remains in roles and permissions.

## Constants

A constant has a name, type, group, description, value, and valid options.
The editor first shows the purpose and type, then a separate selection control
meanings.

- bool is edited by a switch;
- number - a numeric value without accidental change by the wheel where it is
  critical;
- option - a human-readable list;
- path and line - with the corresponding text control.

System constants cannot be deleted as user constants. New constant
add only if this is really a runtime setup of several subsystems;
the configuration of one module should live in `ModuleSettings`.

## Security

Here are the general policies: rate limit, re-confirmation with password for
hazardous operations and associated restrictions. IP blocking is separate
screen for manually blocking and unlocking addresses.In a clean installation, password re-verification is disabled. It can be turned on
for production projects where several employees have access to PHP code,
templates and module installation. Permission and CSRF checks work in both
modes.

The administrator can be exempted from the public rate limit in local work, but
production endpoints should still be protected from abuse.

## Pagination

Pagination stores the wrapper, regular and active links, separators, and captions. After
changes check first, middle and last page as well as mobile
width. Pagination and breadcrumbs are different entities and should not separate the template.

## Maintenance

Clears specific types of cache and derived data. Don't use "clear all"
as a standard method of publication: the usual saving of the entity itself invalidates
your keys.

Before clearing previews, be aware that images will be regenerated
only for allowed presets. See [Media and Thumbnails](../media/README.md).

## Diagnostics

Checks PHP, extensions, recording paths, database, mail, tables and public runtime.
Checks for the installed module should appear only with it; core not
should swear at the missing payment system or form.

## System files

The built-in editor is designed for a small set of permitted files. He doesn't
is a file manager. Before changing, make a backup copy and
check the syntax; configuration error or `.htaccess` may close the entire
website and panel.
