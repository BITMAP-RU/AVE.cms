<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_single/field-req-159.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first(); ?>
<?php if (is_array($image)): ?>
<picture>
	<source data-srcset="<?= $template->escape($template->webp($image['url'])) ?>" type="image/webp">
	<img class="index_main_slider_slide_img lazyload --lazy" src="/uploads/slider/left_default.png" data-src="<?= $template->escape($image['url']) ?>" alt="<?= $template->escape($image['description']) ?>" title="<?= $template->escape($image['description']) ?>">
</picture>
<?php endif; ?>
