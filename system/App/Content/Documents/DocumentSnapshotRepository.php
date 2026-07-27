<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentSnapshotRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Registry;

	/** Read-through repository: stale or broken snapshots are rebuilt from DB. */
	class DocumentSnapshotRepository
	{
		protected $store;
		protected $builder;
		protected $schemas;
		protected static $cache=array();

		public function __construct(DocumentSnapshotStore $store=null,DocumentSnapshotBuilder $builder=null,RubricSchemaBuilder $schemas=null)
		{
			$this->store=$store?:new DocumentSnapshotStore();$this->schemas=$schemas?:new RubricSchemaBuilder($this->store);$this->builder=$builder?:new DocumentSnapshotBuilder($this->store,$this->schemas);
		}

		public function find($documentId)
		{
			$documentId=(int)$documentId;if($documentId<=0){return null;}if(array_key_exists($documentId,self::$cache)){return self::$cache[$documentId];}
			$snapshot=$this->store->read($documentId);$source='snapshot';
			if(!$this->valid($snapshot)){$source='database';try{$snapshot=$this->builder->build($documentId);}catch(\Throwable $e){error_log('Document snapshot #'.$documentId.': '.$e->getMessage());$snapshot=null;}}
			Registry::set('document_snapshot_source',$source);Registry::set('document_snapshot_document_id',$documentId);self::$cache[$documentId]=$snapshot?:null;return self::$cache[$documentId];
		}

		public function findMany(array $documentIds)
		{
			$ids = array_values(array_unique(array_filter(array_map('intval', $documentIds), function ($id) {
				return $id > 0;
			})));
			if (!$ids) {
				return array();
			}

			$result = array();
			$missing = array();
			foreach ($ids as $documentId) {
				if (array_key_exists($documentId, self::$cache)) {
					$result[$documentId] = self::$cache[$documentId];
					continue;
				}

				$snapshot = $this->store->read($documentId);
				if ($this->valid($snapshot)) {
					self::$cache[$documentId] = $snapshot;
					$result[$documentId] = $snapshot;
					continue;
				}

				$missing[] = $documentId;
			}

			if ($missing) {
				$built = array();
				foreach (array_chunk($missing, 100) as $chunk) {
					try {
						$built += $this->builder->buildMany($chunk);
					} catch (\Throwable $e) {
						error_log('Document snapshot batch: ' . $e->getMessage());
						foreach ($chunk as $documentId) {
							try {
								$built[$documentId] = $this->builder->build($documentId);
							} catch (\Throwable $documentError) {
								error_log('Document snapshot #' . $documentId . ': ' . $documentError->getMessage());
								$built[$documentId] = null;
							}
						}
					}
				}

				foreach ($missing as $documentId) {
					self::$cache[$documentId] = isset($built[$documentId]) ? $built[$documentId] : null;
					$result[$documentId] = self::$cache[$documentId];
				}
			}

			$ordered = array();
			foreach ($ids as $documentId) {
				$ordered[$documentId] = isset($result[$documentId]) ? $result[$documentId] : null;
			}

			Registry::set('document_snapshot_batch_count', count($ids));
			Registry::set('document_snapshot_batch_built', count($missing));

			return $ordered;
		}

		public function schema(array $snapshot)
		{
			$rubricId=isset($snapshot['document']['rubric_id'])?(int)$snapshot['document']['rubric_id']:0;return $rubricId>0?$this->schemas->get($rubricId):null;
		}

		public static function reset($documentId=null){if($documentId===null){self::$cache=array();}else{unset(self::$cache[(int)$documentId]);}}

		protected function valid($snapshot)
		{
			if(!is_array($snapshot)||!isset($snapshot['format'],$snapshot['schema_version'],$snapshot['document']['rubric_id'],$snapshot['document']['excerpt'],$snapshot['record'],$snapshot['record']['document_breadcrumb_title'],$snapshot['fields'])||(string)$snapshot['format']!==DocumentSnapshotStore::FORMAT||(int)$snapshot['schema_version']!==DocumentSnapshotStore::VERSION){return false;}
			try{$schema=$this->schemas->get((int)$snapshot['document']['rubric_id']);}catch(\Throwable $e){return false;}
			return isset($snapshot['rubric_schema_version'],$snapshot['template_set_version'])&&(string)$snapshot['rubric_schema_version']===(string)$schema['version']&&(string)$snapshot['template_set_version']===FieldTemplateManifest::version();
		}
	}
