<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_single/field-doc-14.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$image = $template->first(); ?>
<?php if (is_array($image) && !empty($image['url'])): ?>
<div class="index_page_section">
	<div class="index_main_action">
		<div class="index_main_action_inner">
			<a href="/catalog/medicinskoe-oborudovanie/kislorodnye-koncentratory">
				<img class="--lazy" src="<?= $template->escape($image['url']) ?>" alt="<?= $template->escape($image['description']) ?>" title="<?= $template->escape($image['description']) ?>">
			</a>
		</div>
	</div>
</div>
<?php endif; ?>
