ALTER TABLE `{{prefix}}_documents`
	ADD INDEX IF NOT EXISTS `idx_short_alias` (`document_short_alias`);
