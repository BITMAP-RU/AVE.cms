<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_mega/field-doc-22.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<?php foreach ($items as $image): ?>
	<div class="gallery_list_col">
		<div class="item_image">
			<div class="image_inner">
				<a href="<?= $template->escape($image['url']) ?>" class="image_link --gallery_image">
					<img class="image_img" data-src="/templates/public/images/noimage.png" src="<?= $template->escape($template->thumbnail($image['url'], 'f400x400')) ?>" alt="[tag:doc:document_title]" itemprop="image">
				</a>
				<div class="image_title"></div>
			</div>
		</div>
	</div>
<?php endforeach; ?>
