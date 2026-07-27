# Debugging and reliability of hooks

← [To the section “Hooks and events”](README.md)

## Public debugger tab

For an authorized administrator, the public Debug Toolbar shows a tab
**Hooks**. It shows:

- name and type of call (`action`/`filter`);
- handler and priority;
- duration;
- status and exclusion;
- number of calls and nesting.

Trace is only enabled with a protected debugger and is limited to 1500
handlers for the request. Excess is shown as discarded records to
The diagnostic itself did not exhaust the memory.

Turning on the panel and outputting eigenvalues via `Debug::panel()` is described in
[section "Public debugging"](../debug/README.md).

## Handler diagnostics

1. Make sure the module is installed and turned on.
2. Check in which section the hook is declared: `admin`, `public` or the top level.
3. Check the event name and class namespace.
4. Look at the **Hooks** tab: whether the call was made and which handler was executed.
5. Match the time with SQL/HTTP/timeline.
6. Check filtering by type/rubric/source inside the handler.

`Hooks::exists()`, `has()` and `current()` are suitable for local diagnostics.
`Hooks::debug()` prints HTML and should not be used in production code;
for structured values, use the secure debug panel.

## Exceptions

The regular `Hooks::action()` and `filter()` throw an exception to the calling code.
The behavior of a specific lifecycle point can be stricter:

- error/`fail()` in `content.document.saving` rejects saving;
- the exception in the after-event of the document is logged, but the commit is not rolled back;
- a rendering error may stop the public response.

Catch only expected errors. Don't hide the exception with empty `catch`: write it down
context without secrets and give the operation a controlled retry status.

## Performance

- Do not execute the same SQL on each field or list element.
- Do not clear the entire cache if the entity tag or key is known.
- Do not call external HTTP in a save transaction.
- For integrations use outbox, idempotent key and limited web-runner.
- The handler must complete quickly if the event does not belong to its category,
  type or operation.

## Security

The context can contain user data, HTML, email and service
identifiers. Do not transfer it entirely to an external service, do not write it down
passwords/tokens in the log and escape the result if the hook returns HTML.
