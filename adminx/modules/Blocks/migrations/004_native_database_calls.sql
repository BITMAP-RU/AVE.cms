UPDATE `{{prefix}}_sysblocks`
SET `sysblock_text` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  `sysblock_text`,
  '$AVE_DB->Query', 'DB::query'),
  '$AVE_DB->EscStr', 'DB::escape'),
  '->FetchAssocArray()', '->getAssoc()'),
  '->FetchRow()', '->getObject()'),
  '->GetCell()', '->getValue()'),
  '->getCell()', '->getValue()')
WHERE `sysblock_text` LIKE '%$AVE_DB%';
