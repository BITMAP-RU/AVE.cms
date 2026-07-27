<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Functions/custom.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	function isRussian ($text = false)
	{
		return \App\Helpers\SearchText::containsRussian($text);
	}

	function transliterateen ($input)
	{
		return \App\Helpers\SearchText::toRussian($input);
	}

	?>
