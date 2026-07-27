<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_multi/field-req-22.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first();
	$url = is_array($image) && !empty($image['url']) ? $image['url'] : '/uploads/default.png'; ?>
<img src="<?= $template->escape($template->thumbnail($url, 'f400x400')) ?>" alt="[tag:doc:document_title]" class="img-responsive">
