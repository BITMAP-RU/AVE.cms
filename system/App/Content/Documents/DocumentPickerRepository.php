<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentPickerRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/** Shared document search for control-panel pickers. */
	class DocumentPickerRepository
	{
		public function search($query = '', $rubricIds = array(), $limit = 20)
		{
			$limit = max(1, min(50, (int) $limit));
			$score = '0';
			$scoreArgs = array();
			$args = array('1');
			$query = trim((string) $query);
			$where = '';
			if ($query !== '') {
				$criteria = DocumentSearch::criteria($query, 'd');
				$where = ' AND ' . $criteria['where'];
				$args = array_merge($args, $criteria['where_args']);
				$score = $criteria['score'];
				$scoreArgs = $criteria['score_args'];
			}

			$sql = 'SELECT d.Id,d.rubric_id,d.document_title,d.document_alias,d.document_status,r.rubric_title'
				. ',(' . $score . ') AS search_relevance'
				. ' FROM ' . ContentTables::table('documents') . ' d'
				. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id=d.rubric_id'
				. ' WHERE d.document_deleted!=%s'
				. $where;
			$rubricIds = $this->rubricIds($rubricIds);
			if ($rubricIds) {
				$sql .= ' AND d.rubric_id IN (' . implode(',', $rubricIds) . ')';
			}

			$sql .= ' ORDER BY search_relevance DESC,d.document_changed DESC,d.Id DESC LIMIT ' . $limit;
			$rows = call_user_func_array(
				array('DB', 'query'),
				array_merge(array($sql), $scoreArgs, $args)
			)->getAll();
			$result = array();
			foreach ($rows as $row) {
				$result[] = array(
					'id' => (int) $row['Id'],
					'rubric_id' => (int) $row['rubric_id'],
					'title' => $this->decode($row['document_title']),
					'alias' => (string) $row['document_alias'],
					'status' => (int) $row['document_status'],
					'rubric_title' => $this->decode(isset($row['rubric_title']) ? $row['rubric_title'] : ''),
				);
			}

			return $result;
		}

		protected function rubricIds($value)
		{
			if (!is_array($value)) {
				$value = explode(',', (string) $value);
			}

			$result = array();
			foreach ($value as $rubricId) {
				$rubricId = (int) trim((string) $rubricId);
				if ($rubricId > 0) {
					$result[$rubricId] = $rubricId;
				}
			}

			return array_values($result);
		}

		protected function decode($value)
		{
			return htmlspecialchars_decode((string) $value, ENT_QUOTES);
		}
	}
