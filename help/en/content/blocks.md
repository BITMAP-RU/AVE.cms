# Blocks

← [To the section “How the site is assembled”](README.md)

A block is a reusable fragment of content or design logic. One block
can be inserted into a site template, category, request or other block.

## Creating a block

For the block, the name, unique alias, group, description, activity and
editor mode. The group is needed only to organize the list and does not affect
public challenge.

| Mode | When to use |
| --- | --- |
| Plain text | Lines without HTML editing. |
| Rich text | Content that the editor changes without working with markup. |
| HTML | Managed markup, tags for fields, queries and other blocks. |
| PHP | Small project logic that actually needs server code. |

Switching the mode changes the editor and the way it is processed. PHP code passes
syntax check before saving. For large or reusable
logic create a service and module hook.

The common law `manage_blocks` controls HTML, rich text and plain text blocks.
Executable mode additionally requires `manage_php_blocks`: without it PHP block
cannot be opened, edited, copied or restored from a revision. Changes
are recorded in the general audit log without recording the source code in the event.

## Inserting into a template

The main public tag uses alias:

```text
[tag:sysblock:footer]
```

The old `[tag:block:...]` is maintained for compatibility, but the new code follows
write via `sysblock`.

The block may contain:

```text
[tag:request:latest_news]
[tag:sysblock:contacts]
[tag:fld:cover]
[tag:breadcrumb]
```

Document fields are only available when the block is rendered in document context.

## Options

Parameters are passed by a JSON object in a tag:

```text
[tag:sysblock:banner:{"theme":"dark","limit":4}]
```

Inside the block the value reads like this:

```text
[sys:param:theme]
[sys:param:limit]
```

Do not substitute a parameter directly into a SQL or PHP executable without checking the type and
acceptable values.

## External and AJAX call

The external call flag allows the block to be received by a separate public request. AJAX
may further restrict such a call to the expected mode. Don't turn these on
flags for inner block unnecessarily: outer endpoint increases
public surface of the system.

## Nesting and recursion

Blocks can include other blocks, but must not refer to themselves directly
or along the chain. Runtime stops re-entry and limits maximum
depth, but the cyclic scheme is still a design mistake.

## Cache and revisions

After saving, copying, deleting or restoring, the system clears the cache
by block ID and alias. Manual cleaning is needed after external changes to the database or for
diagnostics

Revisions save the content and basic settings. Before restoring the old one
version, a snapshot of the current one is created. Deleting all revisions does not delete the block itself.

## Block check

1. Perform a syntax check.
2. Save and open the page where the block is actually used.
3. Check the guest and authorized user if there are access conditions.
4. Check subqueries and document fields.
5. For the external unit, check the prohibited and allowed calls separately.
