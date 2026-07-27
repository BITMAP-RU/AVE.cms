UPDATE `{{prefix}}_rubrics`
SET `rubric_template` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  `rubric_template`,
  '$AVE_DB->Query', 'DB::query'),
  '$AVE_DB->EscStr', 'DB::escape'),
  '->FetchAssocArray()', '->getAssoc()'),
  '->FetchRow()', '->getObject()'),
  '->GetCell()', '->getValue()'),
  '->getCell()', '->getValue()')
WHERE `rubric_template` LIKE '%$AVE_DB%';

UPDATE `{{prefix}}_rubric_templates`
SET `template` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  `template`,
  '$AVE_DB->Query', 'DB::query'),
  '$AVE_DB->EscStr', 'DB::escape'),
  '->FetchAssocArray()', '->getAssoc()'),
  '->FetchRow()', '->getObject()'),
  '->GetCell()', '->getValue()'),
  '->getCell()', '->getValue()')
WHERE `template` LIKE '%$AVE_DB%';
