UPDATE `{{prefix}}_sysblocks`
SET `sysblock_text` = CONVERT(FROM_BASE64('PD9waHAKCSRkb2NJZCA9IChpbnQpICdbc3lzOnBhcmFtOmRvY2lkXSc7CgkkY2xhc3MgPSBwcmVnX3JlcGxhY2UoJy9bXkEtWmEtejAtOSBfLV0vJywgJycsIChzdHJpbmcpICdbc3lzOnBhcmFtOmNsYXNzXScpOwoKCWlmICgkZG9jSWQgPiAwKQoJewoJCWVjaG8gJzxzcGFuIGRhdGEtYWRtaW54LWVkaXQtcGxhY2Vob2xkZXIgZGF0YS1kb2N1bWVudC1pZD0iJyAuICRkb2NJZCAuICciIGRhdGEtZWRpdC1jbGFzcz0iJyAuIGh0bWxzcGVjaWFsY2hhcnMoJGNsYXNzLCBFTlRfUVVPVEVTLCAnVVRGLTgnKSAuICciPjwvc3Bhbj4nOwoJfQo/Pg==') USING utf8mb4)
WHERE `sysblock_alias` = 'edit_item';

UPDATE `{{prefix}}_request`
SET `request_changed_elements` = UNIX_TIMESTAMP()
WHERE `request_template_item` LIKE '%sysblock:edit_item%'
   OR `request_template_item` LIKE '%sysblock:item_list%';
