<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Introspection/ContractRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Introspection;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Transport-neutral descriptors for the first read-only introspection tools. */
	class ContractRegistry
	{
		public static function all()
		{
			return array(
				'version' => 1,
				'mode' => 'read-only',
				'contracts' => array(
					'page_context' => array(
						'name' => 'diagnose.page',
						'contract' => 'ave.page-context',
						'contract_version' => 1,
						'description' => 'Показывает документ, поля, шаблоны, компоненты и кеш публичной страницы.',
						'scopes' => array('content.read', 'structure.read'),
						'permissions' => array('view_documents', 'view_public_site'),
						'input_schema' => array(
							'type' => 'object',
							'required' => array('target'),
							'properties' => array(
								'target' => array(
									'type' => 'string',
									'description' => 'Публичный URL, путь или ID документа.',
									'maxLength' => 2048,
								),
							),
							'additionalProperties' => false,
						),
					),
					'request_explanation' => array(
						'name' => 'requests.explain',
						'contract' => 'ave.request-explanation',
						'contract_version' => 1,
						'description' => 'Объясняет включение документа в сохранённый запрос с параметрами страницы.',
						'scopes' => array('content.read', 'structure.read'),
						'permissions' => array('view_documents', 'view_public_site'),
						'input_schema' => array(
							'type' => 'object',
							'required' => array('request_id', 'document_id'),
							'properties' => array(
								'request_id' => array('type' => 'integer', 'minimum' => 1),
								'document_id' => array('type' => 'integer', 'minimum' => 1),
								'parameters' => array(
									'type' => 'object',
									'description' => 'Безопасные параметры публичной страницы.',
									'additionalProperties' => true,
								),
							),
							'additionalProperties' => false,
						),
					),
				),
				'errors' => array(
					'authentication_required',
					'scope_denied',
					'permission_denied',
					'origin_denied',
					'validation_failed',
					'entity_not_found',
					'rate_limit_exceeded',
					'response_too_large',
					'internal_error',
				),
			);
		}
	}
