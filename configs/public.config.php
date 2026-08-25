<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         configs/public.config.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		// Имя каталога панели управления относительно корня сайта. При физическом
		// переименовании каталога измените это значение на то же имя.
		'admin_directory' => 'adminx',
		// Публичная часть одноязычная; значение нужно только старым locale/lang-файлам.
		'public_language' => 'ru',
		// Начальные значения для чистого развёртывания. После первого запуска
		// регистрацией управляет PublicAuthSettings через Adminx → Покупатели.
		'auth_registration' => array(
			'enabled' => false,
			'gate' => 'email',
			'default_group' => 4,
			'verification_ttl' => 86400,
			'reset_ttl' => 3600,
		),
		// Миграционный SSO: только привилегированные legacy-группы могут связаться
		// с активным системным пользователем по совпадающему email.
		'system_identity_public_groups' => array(1, 3),
		// Forwarded-заголовки учитываются только от перечисленных proxy/CIDR.
		// На сервере без reverse proxy оставьте список пустым.
		'trusted_proxies' => array(),
		'trusted_proxy_headers' => array('x-forwarded-for'),
		// Дополнительные домены, разрешённые помимо PUBLIC_SITE_URL.
		'allowed_hosts' => array(),
		'debug' => array(
			'groups' => array(1),
		),
	);
