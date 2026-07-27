UPDATE `{{public_user_prefix}}_users`
SET `last_visit` = 0
WHERE `last_visit` > 0
  AND `last_visit` < 946684800;
