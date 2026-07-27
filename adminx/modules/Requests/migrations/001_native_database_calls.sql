UPDATE `{{prefix}}_request_conditions`
SET `condition_value` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  `condition_value`,
  '$AVE_DB->Query', 'DB::query'),
  '$AVE_DB->EscStr', 'DB::escape'),
  '->FetchAssocArray()', '->getAssoc()'),
  '->FetchRow()', '->getObject()'),
  '->GetCell()', '->getValue()'),
  '->getCell()', '->getValue()')
WHERE `condition_value` LIKE '%$AVE_DB%';

UPDATE `{{prefix}}_request`
SET `request_where_cond` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  `request_where_cond`,
  '$AVE_DB->Query', 'DB::query'),
  '$AVE_DB->EscStr', 'DB::escape'),
  '->FetchAssocArray()', '->getAssoc()'),
  '->FetchRow()', '->getObject()'),
  '->GetCell()', '->getValue()'),
  '->getCell()', '->getValue()')
WHERE `request_where_cond` LIKE '%$AVE_DB%';
