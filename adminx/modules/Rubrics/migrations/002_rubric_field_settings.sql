-- Фаза 2 контент-ядра полей (docs/development/fields-migration.md):
-- JSON-настройки типа поля как источник правды. Пусто => читается с fallback
-- из legacy rubric_field_default. Идемпотентность обеспечивает ModuleMigrator
-- (учёт применённых миграций по checksum); повторно не выполняется.
ALTER TABLE `{{prefix}}_rubric_fields`
  ADD COLUMN `rubric_field_settings` LONGTEXT NULL DEFAULT NULL AFTER `rubric_field_default`;
