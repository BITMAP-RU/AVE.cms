# PHP console

← [To system management](README.md)

PHP console executes administrative diagnostic snippet in context
AVE.cms. This is not a mechanism for background jobs, migrations, or public business logic.

## Valid tasks

- check the value of the setting or the operation of the helper;
- execute a secure read-only request;
- explore the data structure during development;
- save frequently used diagnostic snippet.

To change the schema use module migration, for bulk change
documents - a specialized operation with preview, for
constant logic - service/hook.

## Security

Execution requires a separate right and may require re-confirmation
password. The code runs on the server with web process rights, so it can
change the database and files.

Before the changing script:

1. make a backup;
2. limit the sample and output the expected changes first;
3. use the transaction if the operation allows it;
4. do not display secrets, tokens and passwords;
5. After execution, check the audit and remove the dangerous temporary snippet.

The production site should not depend on manual launch of the console or availability
CLI. All mandatory operations must have a regular web/lifecycle mechanism.
