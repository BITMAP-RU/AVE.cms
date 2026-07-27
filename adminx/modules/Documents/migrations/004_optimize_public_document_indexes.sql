ALTER TABLE `{{prefix}}_documents`
  ADD INDEX IF NOT EXISTS `idx_public_listing`
    (`rubric_id`, `document_status`, `document_deleted`, `document_published`, `Id`);

ALTER TABLE `{{prefix}}_document_fields`
  ADD INDEX IF NOT EXISTS `idx_public_field_text`
    (`rubric_field_id`, `field_value`(100), `document_id`);
