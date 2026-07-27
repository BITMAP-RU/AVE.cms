<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/RubricSchemaBuilder.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Fields\FieldSettings;
	use App\Helpers\Json;
	use DB;

	/** Shared rubric definition used by every document snapshot in the rubric. */
	class RubricSchemaBuilder
	{
		protected $store;
		protected static $cache=array();

		public function __construct(DocumentSnapshotStore $store = null)
		{
			$this->store = $store ?: new DocumentSnapshotStore();
		}

		public function get($rubricId)
		{
			$rubricId = (int) $rubricId;
			if(isset(self::$cache[$rubricId])){return self::$cache[$rubricId];}
			$stored = $this->store->readSchema($rubricId);
			$changed=(int)DB::query('SELECT rubric_changed FROM '.ContentTables::table('rubrics').' WHERE Id=%i LIMIT 1',$rubricId)->getValue();
			if(is_array($stored)&&isset($stored['rubric']['rubric_changed'],$stored['template_set_version'])&&(int)$stored['rubric']['rubric_changed']===$changed&&(string)$stored['template_set_version']===FieldTemplateManifest::version()){
				self::$cache[$rubricId]=$stored;return $stored;
			}

			$current = $this->build($rubricId);
			if (!is_array($stored) || !isset($stored['version']) || (string) $stored['version'] !== (string) $current['version']) {
				$this->store->writeSchema($rubricId, $current);
			}

			self::$cache[$rubricId]=$current;return $current;
		}

		public function build($rubricId)
		{
			$rubric = DB::query('SELECT * FROM ' . ContentTables::table('rubrics') . ' WHERE Id=%i LIMIT 1', (int) $rubricId)->getAssoc();
			if (!$rubric) { throw new \RuntimeException('Рубрика для JSON-снимка не найдена'); }
			$groups = DB::query('SELECT * FROM ' . ContentTables::table('rubric_fields_group') . ' WHERE rubric_id=%i ORDER BY group_position,Id', (int) $rubricId)->getAll();
			$rows = DB::query('SELECT * FROM ' . ContentTables::table('rubric_fields') . ' WHERE rubric_id=%i ORDER BY rubric_field_group,rubric_field_position,Id', (int) $rubricId)->getAll();
			$fields = array();$aliases=array();
			foreach($rows?:array() as $row){$id=(int)$row['Id'];$row['Id']=$id;$row['settings']=FieldSettings::effective($row);$fields[(string)$id]=$row;$alias=trim((string)$row['rubric_field_alias']);if($alias!==''){$aliases[$alias]=$id;}}
			$versionSource=array('rubric'=>$rubric,'groups'=>$groups?:array(),'fields'=>$fields);
			return array(
				'format'=>'ave.rubric-schema','schema_version'=>1,'rubric_id'=>(int)$rubricId,
				'generated_at'=>time(),'version'=>'sha256:'.hash('sha256',Json::encode($versionSource,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
				'template_set_version'=>FieldTemplateManifest::version(),'rubric'=>$rubric,'groups'=>$groups?:array(),
				'fields'=>$fields,'field_aliases'=>$aliases,
			);
		}

		public static function reset($rubricId=null){if($rubricId===null){self::$cache=array();}else{unset(self::$cache[(int)$rubricId]);}}
	}
