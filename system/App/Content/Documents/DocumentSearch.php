<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentSearch.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\SearchText;

	/** Shared search conditions and relevance for documents and document pickers. */
	class DocumentSearch
	{
		public static function criteria($query, $alias = 'd')
		{
			$alias = self::alias($alias);
			return self::build(
				$query,
				array($alias . '.document_title', $alias . '.document_alias'),
				$alias . '.Id'
			);
		}

		public static function valueCriteria($query, array $columns)
		{
			$safe = array();
			foreach ($columns as $column) {
				$column = trim((string) $column);
				if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
					$safe[] = $column;
				}
			}

			return self::build($query, $safe, '');
		}

		protected static function build($query, array $columns, $idColumn)
		{
			$query = SearchText::normalize($query);
			if ($query === '' || !$columns) {
				return array(
					'query' => '',
					'where' => '',
					'where_args' => array(),
					'score' => '0',
					'score_args' => array(),
				);
			}

			$phraseVariants = array_slice(SearchText::variants($query), 0, 4);
			$termGroups = SearchText::termGroups($query);
			$whereGroups = array();
			$whereArgs = array();

			foreach ($termGroups as $variants) {
				$matches = self::matches($columns, $variants, $whereArgs);
				if ($matches) {
					$whereGroups[] = '(' . implode(' OR ', $matches) . ')';
				}
			}

			if (!$whereGroups) {
				$matches = self::matches($columns, $phraseVariants, $whereArgs);
				if ($matches) {
					$whereGroups[] = '(' . implode(' OR ', $matches) . ')';
				}
			}

			$where = '(' . implode(' AND ', $whereGroups) . ')';
			if ($idColumn !== '' && ctype_digit($query)) {
				$where = '(' . $where . ' OR ' . $idColumn . '=%i)';
				$whereArgs[] = (int) $query;
			}

			$score = array();
			$scoreArgs = array();
			if ($idColumn !== '' && ctype_digit($query)) {
				$score[] = 'IF(' . $idColumn . '=%i,1200,0)';
				$scoreArgs[] = (int) $query;
			}

			foreach ($phraseVariants as $variant) {
				$score[] = 'IF(' . $columns[0] . '=%s,1000,0)';
				$scoreArgs[] = $variant;
				$score[] = 'IF(' . $columns[0] . ' LIKE %ssb,700,0)';
				$scoreArgs[] = $variant;
				$score[] = 'IF(' . $columns[0] . ' LIKE %ss,500,0)';
				$scoreArgs[] = $variant;
				if (isset($columns[1])) {
					$score[] = 'IF(' . $columns[1] . '=%s,900,0)';
					$scoreArgs[] = $variant;
					$score[] = 'IF(' . $columns[1] . ' LIKE %ss,350,0)';
					$scoreArgs[] = $variant;
				}
			}

			foreach ($termGroups as $variants) {
				$matches = array();
				$matchesArgs = array();
				foreach ($variants as $variant) {
					$matches[] = $columns[0] . ' LIKE %ss';
					$matchesArgs[] = $variant;
				}

				if ($matches) {
					$score[] = 'IF((' . implode(' OR ', $matches) . '),50,0)';
					$scoreArgs = array_merge($scoreArgs, $matchesArgs);
				}
			}

			return array(
				'query' => $query,
				'where' => $where,
				'where_args' => $whereArgs,
				'score' => $score ? implode('+', $score) : '0',
				'score_args' => $scoreArgs,
			);
		}

		protected static function matches(array $columns, array $variants, array &$args)
		{
			$matches = array();
			foreach ($variants as $variant) {
				foreach ($columns as $column) {
					$matches[] = $column . ' LIKE %ss';
					$args[] = $variant;
				}
			}

			return $matches;
		}

		protected static function alias($alias)
		{
			return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $alias)
				? (string) $alias
				: 'd';
		}
	}
