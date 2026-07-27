<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/image_multi/field-doc-123.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<div class="exhibition-gallery" data-exhibition-gallery>
	<?php foreach ($items as $image): ?>
		<?php
			$url = (string) $image['url'];
			$available = strpos($url, '/uploads/') !== 0 || is_file(BASEPATH . $url);
			$source = $available ? $url : '/templates/medmos/assets/images/no-photo.svg';
		?>
		<a class="exhibition-gallery__item" href="<?= $template->escape($source) ?>">
			<img src="<?= $template->escape($source) ?>" data-fallback-src="/templates/medmos/assets/images/no-photo.svg" alt="<?= $template->escape($image['description']) ?>" loading="lazy">
		</a>
	<?php endforeach; ?>
</div>
