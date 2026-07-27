CREATE TABLE IF NOT EXISTS `{{prefix}}_document_relation_edges` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_document_id` INT UNSIGNED NOT NULL,
  `source_field_id` MEDIUMINT UNSIGNED NOT NULL,
  `target_document_id` INT UNSIGNED NOT NULL,
  `relation_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_relation_edge` (`source_document_id`,`source_field_id`,`target_document_id`),
  KEY `idx_relation_outgoing` (`source_document_id`,`source_field_id`,`position`),
  KEY `idx_relation_incoming` (`target_document_id`,`relation_type`,`source_document_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
