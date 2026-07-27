UPDATE `{{prefix}}_request_conditions`
SET `condition_value` = REPLACE(`condition_value`, 'DB::escape(', 'DB::safe(')
WHERE `condition_value` LIKE '%DB::escape(%';

UPDATE `{{prefix}}_request`
SET `request_where_cond` = REPLACE(`request_where_cond`, 'DB::escape(', 'DB::safe(')
WHERE `request_where_cond` LIKE '%DB::escape(%';
