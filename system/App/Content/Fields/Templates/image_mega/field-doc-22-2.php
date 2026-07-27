<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_mega/field-doc-22-2.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<?php if (!empty($items)): ?>
<div class="lightgallery-thumbs clearfix" id="lightgallery-thumbs">
<?php foreach ($items as $image): ?>
	<a href="<?= $template->escape($image['url']) ?>" class="lightgallery-link">
		<img class="lightgallery-image" src="<?= $template->escape($template->thumbnail($image['url'], 'c50x50')) ?>" alt="">
	</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
