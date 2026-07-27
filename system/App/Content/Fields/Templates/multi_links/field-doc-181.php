<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/multi_links/field-doc-181.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<table class="table table-params table-no-border">
	<tr><td class="table-header">Документация</td></tr>
	<?php if (!empty($items)): ?>
		<?php foreach ($items as $item): ?>
			<tr><td><a href="<?= $template->escape($item['value']) ?>" target="_blank" rel="noopener"><i class="fa fa-file-pdf-o"></i>&nbsp;<?= $template->escape($item['param']) ?></a></td></tr>
		<?php endforeach; ?>
	<?php else: ?>
		<tr><td><div class="alert alert-warning">Нет файлов для скачивания</div></td></tr>
	<?php endif; ?>
</table>
