# Panel work tools

← [Back to the “Modules” section](README.md)

Todo and Kanban support collaborative work. Notes and Reminders remain private
to their owner.

##Todo

Module `todo` is a checklist with an assignee, due date, visibility, and an
optional document link. The author can edit or delete a task; the author and
assignee can complete it. Overdue tasks appear in notifications.

- `view_todos` - list, action in the header and widget;
- `manage_todos` - creation, execution and deletion.

## Kanban

Module `kanban` has configurable columns and cards. Cards may have an assignee,
due date, team visibility, and document link. Columns remain on the author's
board; recipients see assigned/shared cards in a separate list.

- `view_kanban` — view the board and widget;
- `manage_kanban` - columns, cards and movement.

## Notes

Module `notes` stores text stickers. Color, pinning and search are available.
Quick creation opens from the header, the full list and editing are in
module section.

- `view_notes` - list and widget;
- `manage_notes` - creation, modification, assignment and deletion.

## Reminders

Module `reminders` adds a date to the task. Overdue and upcoming entries
are highlighted and appear in the widget and the general list of notifications.

- `view_reminders` - list, widget and notifications;
- `manage_reminders` - creation, modification, execution and deletion.

Disabling any of these modules hides its interface and saves data.
Uninstallation deletes the module tables and all personal entries without the ability
recovery using the panel.
