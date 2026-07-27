UPDATE `{{prefix}}_sysblocks`
SET `sysblock_text` = REPLACE(
  `sysblock_text`,
  '$AVE_Core->curentdoc',
  CONCAT(CHAR(92), 'App', CHAR(92), 'Frontend', CHAR(92), 'PublicPageContext::document()')
)
WHERE `sysblock_text` LIKE '%$AVE_Core->curentdoc%';
