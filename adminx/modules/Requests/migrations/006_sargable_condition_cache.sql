UPDATE `{{prefix}}_request`
SET `request_where_cond` = ''
WHERE `request_where_cond` IS NOT NULL AND `request_where_cond` != '';
