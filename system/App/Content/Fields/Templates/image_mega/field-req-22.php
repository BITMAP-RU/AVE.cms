<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_mega/field-req-22.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first(); ?>
<?php if (is_array($image) && !empty($image['url'])): ?>
<meta itemprop="image" content="<?= $template->escape($template->host() . $image['url']) ?>">
<?php else: ?>
<img class="card_img" src="<?= $template->escape($template->thumbnail('/uploads/default.png', 'f600x600')) ?>" alt="[tag:doc:document_title]">
<?php endif; ?>
