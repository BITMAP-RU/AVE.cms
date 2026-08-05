<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/migrations/007_customer_center.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Content\BasketTables;

	return function (array $context) {
		$notes = SystemTables::prefix() . '_customer_notes';
		if (!DatabaseSchema::tableExists($notes)) {
			DB::query('CREATE TABLE `' . $notes . '` ('
				. '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` INT UNSIGNED NOT NULL,`author_id` INT UNSIGNED NOT NULL DEFAULT 0,'
				. '`note` TEXT NOT NULL,`created_at` INT UNSIGNED NOT NULL,PRIMARY KEY (`id`),KEY `idx_customer_notes` (`user_id`,`created_at`)'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		}

		$orders = BasketTables::table('module_basket_history');
		if (DatabaseSchema::tableExists($orders) && !DatabaseSchema::indexExists($orders, 'idx_order_user_date')) {
			DB::query('ALTER TABLE `' . $orders . '` ADD KEY `idx_order_user_date` (`order_user_id`,`order_published`)');
		}

		return 1;
	};
