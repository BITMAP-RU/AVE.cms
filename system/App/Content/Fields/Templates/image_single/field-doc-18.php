<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_single/field-doc-18.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first(); ?>
<?php if (is_array($image) && !empty($image['url'])): ?>
<img class="img-fluid" src="<?= $template->escape($image['url']) ?>" alt="<?= $template->escape($image['description']) ?>" title="<?= $template->escape($image['description']) ?>">
<?php endif; ?>
