# File security

The installed “File Security” module controls changes to source files,
checks downloads and helps isolate suspicious user files.

## First launch

1. Open `Modules` and install the “File Security” module.
2. Check the project source code from a trusted source.
3. Open the module section and click the main button 'Create standard'.
4. Once complete, run a normal source code check.

The standard stores the SHA-256, size, and modification time of the file. It doesn't store content
sources and configuration secrets.

While the source reference is empty, normal source code checking is blocked. This
does not allow you to get a false report in which each file is marked as new.

## Find types

- **Integrity control** - the file has appeared, disappeared or differs from the standard.
- **Heuristics** - dangerous combination of functions detected, obfuscation, PHP inside
  downloads, double extension or server handler changes.
- **Scanner error** - the file could not be read or its SHA-256 could not be calculated.

Heuristics can work on legitimate technical code. Open the file and
check the context before making a decision.

## Actions

- `Accept as standard` - trust the current version of the modified source.
- `Ignore` - leave the find in the journal, but remove it from the open ones.
- `Return to work` - show the find as open again.
- `Quarantine` - move the download outside the public directory.
- `Restore` - return the quarantine file to its original path.

System PHP files cannot be automatically quarantined: moving them
can stop the site immediately. Restore such files from a verified
release or backup.

## Automatic check

Three levels are available for manual and automatic start:

- **Quick** - checks public and administrative entry points, `.htaccess`,
  main configs and boot rules files. Suitable for frequent monitoring.
- **System** - checks executable files of the kernel, panel, modules, installer
  and templates, without bypassing heavy static libraries. This is the recommended mode
  periodic inspection.
- **Full** - crawls all files in the selected area. Launch it after
  updating, moving the site or when investigating an incident.

The reference standard is always built in full mode. Partial check compares
only files related to their level and does not mark other files of the standard
like disappeared.Web-runner does not require a CLI. After a specified interval, the public request creates
verification, and subsequent requests continue it in portions. Work is done after
forming a public response and is limited in time, so scanning is not
should hold up the page. When the security section of the browser is open
sends portions in a row and shows live progress.

Without public requests and without an open web-runner module page, execute code
can't. If there is little traffic, choose fast auto-check, and system or
run the full one manually and wait for the indicator to complete. At the same time
Only one launch works. Automatic mode can be turned off regardless of
download protection.

When the module is turned off, scans, download protection, notifications, and
widget, but the data remains. Uninstallation deletes the standard, history, finds,
settings and quarantine. After reinstallation, the module starts working from scratch
state and requires creating a new standard.
