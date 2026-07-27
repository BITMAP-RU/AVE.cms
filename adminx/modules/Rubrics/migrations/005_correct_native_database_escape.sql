UPDATE `{{prefix}}_rubrics`
SET `rubric_template` = REPLACE(`rubric_template`, 'DB::escape(', 'DB::safe(')
WHERE `rubric_template` LIKE '%DB::escape(%';

UPDATE `{{prefix}}_rubric_templates`
SET `template` = REPLACE(`template`, 'DB::escape(', 'DB::safe(')
WHERE `template` LIKE '%DB::escape(%';
