ALTER TABLE `{{prefix}}_documents`
  ADD COLUMN IF NOT EXISTS `document_is_technical` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `document_in_search`,
  ADD COLUMN IF NOT EXISTS `document_in_sitemap` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `document_sitemap_pr`,
  ADD KEY IF NOT EXISTS `idx_public_visibility` (`document_is_technical`,`document_in_sitemap`);
