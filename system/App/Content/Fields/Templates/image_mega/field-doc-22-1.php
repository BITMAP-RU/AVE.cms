<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_mega/field-doc-22-1.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first();
	$url = is_array($image) && !empty($image['url']) ? $image['url'] : '/uploads/default.png'; ?>
<a href="<?= $template->escape($url) ?>" class="--gallery_image" data-rel="lightcase:Gallery" data-lc-options='{"maxWidth":1600,"maxHeight":1200}'>
	<img title="[tag:doc:document_title]" class="product_main_img" src="<?= $template->escape($template->thumbnail($url, 't450x450')) ?>" alt="[tag:doc:document_title]">
</a>
