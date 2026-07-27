UPDATE `{{prefix}}_rubrics`
SET `rubric_template` = REPLACE(
  `rubric_template`,
  '$AVE_Core->curentdoc',
  CONCAT(CHAR(92), 'App', CHAR(92), 'Frontend', CHAR(92), 'PublicPageContext::document()')
)
WHERE `rubric_template` LIKE '%$AVE_Core->curentdoc%';

UPDATE `{{prefix}}_rubric_templates`
SET `template` = REPLACE(
  `template`,
  '$AVE_Core->curentdoc',
  CONCAT(CHAR(92), 'App', CHAR(92), 'Frontend', CHAR(92), 'PublicPageContext::document()')
)
WHERE `template` LIKE '%$AVE_Core->curentdoc%';
