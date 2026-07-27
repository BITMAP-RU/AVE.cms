UPDATE `{{prefix}}_sysblocks`
SET `sysblock_text` = REPLACE(`sysblock_text`, 'DB::escape(', 'DB::safe(')
WHERE `sysblock_text` LIKE '%DB::escape(%';
