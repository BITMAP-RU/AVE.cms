<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/doc_from_rub_search/field-req-137.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.'); ?>
<?php foreach ($items as $item): ?>
[ <a href="/<?= $template->escape(ltrim((string) $item['document_alias'], '/')) ?>">(<?= (int) $item['Id'] ?>) <?= $template->escape($item['document_title']) ?></a> ]&nbsp;&nbsp;&nbsp;
<?php endforeach; ?>
