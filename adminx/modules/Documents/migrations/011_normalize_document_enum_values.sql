UPDATE `{{content_prefix}}_documents`
SET `document_alias_history` = '0'
WHERE `document_alias_history` = '';

UPDATE `{{content_prefix}}_documents`
SET `document_in_search` = '0'
WHERE `document_in_search` = '';

UPDATE `{{content_prefix}}_documents`
SET `document_status` = '0'
WHERE `document_status` = '';

UPDATE `{{content_prefix}}_documents`
SET `document_deleted` = '0'
WHERE `document_deleted` = '';

UPDATE `{{content_prefix}}_document_fields`
SET `document_in_search` = '0'
WHERE `document_in_search` = '';
