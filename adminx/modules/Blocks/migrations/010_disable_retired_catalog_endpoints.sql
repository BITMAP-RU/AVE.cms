UPDATE `{{content_prefix}}_sysblocks`
SET `sysblock_external` = '0', `sysblock_ajax` = '0'
WHERE `sysblock_alias` IN ('request', 'menu_catalog')
   OR (`id` = 29 AND `sysblock_alias` = '');
