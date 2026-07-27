<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/multi_links/field-doc-137.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<ul>
	<?php foreach ($items as $item): ?>
		<li><?= $template->escape($item['param']) ?>: <?= $template->escape($item['value']) ?></li>
	<?php endforeach; ?>
</ul>
