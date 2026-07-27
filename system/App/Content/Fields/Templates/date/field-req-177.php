<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Templates/date/field-req-177.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	$timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
	if ($timestamp > 0): ?>
<?= $template->escape(date('d.m.Y', $timestamp)) ?>
<?php endif; ?>
