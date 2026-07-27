UPDATE `{{prefix}}_documents` AS d
LEFT JOIN `{{prefix}}_rubric_templates` AS t
	ON t.id = d.rubric_tmpl_id
	AND t.rubric_id = d.rubric_id
SET d.rubric_tmpl_id = 0
WHERE d.rubric_tmpl_id > 0
	AND t.id IS NULL;
