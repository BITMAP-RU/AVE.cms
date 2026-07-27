UPDATE `{{content_prefix}}_rubric_fields`
SET rubric_field_settings = REPLACE(
  REPLACE(rubric_field_settings, '"width":12', '"width":"full"'),
  '"width":"12"',
  '"width":"full"'
)
WHERE rubric_field_settings LIKE '%"width":12%'
   OR rubric_field_settings LIKE '%"width":"12"%';
