<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentSaveEvents.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Helpers\Hooks;
	use DB;

	/** Stable integration boundary for modules reacting to document mutations. */
	class DocumentSaveEvents
	{
		public static function before($operation, $source, $documentId, $rubricId, $actorId, array &$data, array &$fields, array $previous = array())
		{
			$event = new DocumentSaveEvent('saving', $operation, $source, $documentId, $rubricId, $actorId, $data, $fields, self::fieldAliases($rubricId), $previous);
			$result = Hooks::action('content.document.saving', $event);
			if ($result instanceof DocumentSaveEvent) { $event = $result; }
			if ($event->failed()) {
				throw new DocumentSaveRejected($event->message() !== '' ? $event->message() : 'Сохранение документа отменено модулем', $event->errors());
			}

			return $event;
		}

		public static function after($operation, $source, $documentId, $rubricId, $actorId, array &$data, array &$fields, array $previous, array $snapshot)
		{
			$event = new DocumentSaveEvent('saved', $operation, $source, $documentId, $rubricId, $actorId, $data, $fields, self::fieldAliases($rubricId), $previous, $snapshot);
			self::dispatchAfter('content.document.saved', $event);
			self::dispatchAfter($operation === 'create' ? 'content.document.created' : 'content.document.updated', $event);
			$beforeStatus = isset($previous['document_status']) ? (int) $previous['document_status'] : 0;
			$afterStatus = (int) $event->value('document_status', 0);
			if (($operation === 'create' && $afterStatus === 1) || ($operation === 'update' && $beforeStatus === 0 && $afterStatus === 1)) {
				self::dispatchAfter('content.document.published', $event);
			} elseif ($operation === 'update' && $beforeStatus === 1 && $afterStatus === 0) {
				self::dispatchAfter('content.document.unpublished', $event);
			}

			return $event;
		}

		/**
		 * Dispatch integrations which must commit atomically with the document.
		 * A handler error intentionally aborts the surrounding transaction.
		 */
		public static function persisted($operation, $source, $documentId, $rubricId, $actorId, array &$data, array &$fields, array $previous = array())
		{
			$event = new DocumentSaveEvent('persisted', $operation, $source, $documentId, $rubricId, $actorId, $data, $fields, self::fieldAliases($rubricId), $previous);
			$result = Hooks::action('content.document.persisted', $event);
			return $result instanceof DocumentSaveEvent ? $result : $event;
		}

		protected static function dispatchAfter($name, DocumentSaveEvent $event)
		{
			try {
				Hooks::action($name, $event);
			} catch (\Throwable $e) {
				error_log('Document module hook [' . $name . '] #' . $event->documentId() . ': ' . $e->getMessage());
			}
		}

		protected static function fieldAliases($rubricId)
		{
			$rows = DB::query('SELECT Id,rubric_field_alias FROM ' . ContentTables::table('rubric_fields') . ' WHERE rubric_id=%i', (int) $rubricId)->getAll();
			$aliases = array();
			foreach ($rows ?: array() as $row) {
				$alias = trim((string) $row['rubric_field_alias']);
				if ($alias !== '') { $aliases[$alias] = (int) $row['Id']; }
			}

			return $aliases;
		}
	}
