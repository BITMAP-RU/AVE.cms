ALTER TABLE `{{prefix}}_rubrics`
  ADD COLUMN IF NOT EXISTS `rubric_is_technical` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `rubric_docs_active`;
