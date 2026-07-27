ALTER TABLE `{{prefix}}_rubrics`
  ADD COLUMN IF NOT EXISTS `rubric_og_template` TEXT NOT NULL AFTER `rubric_header_template`;
