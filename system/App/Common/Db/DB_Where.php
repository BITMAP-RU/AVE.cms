<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/DB_Where.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	class DB_Where
	{
		public $type = 'and'; //AND or OR
		public $negate = false;
		public $clauses = [];


		public function __construct($type)
		{
			$type = strtolower($type);

			if ($type !== 'or' && $type !== 'and') {
				DB::nonSqlError('you must use either DB_Where(and) or DB_Where(or)');
			}

			$this->type = $type;
		}


		public function add()
		{
			$args = func_get_args();
			$sql = array_shift($args);

			if ($sql instanceof DB_Where) {
				$this->clauses[] = $sql;
			} else {
				$this->clauses[] = array('sql' => $sql, 'args' => $args);
			}
		}


		public function negateLast()
		{
			$i = count($this->clauses) - 1;

			if (!isset($this->clauses[$i])) {
				return;
			}

			if ($this->clauses[$i] instanceof self) {
				$this->clauses[$i]->negate();
			} else {
				$this->clauses[$i]['sql'] = 'NOT (' . $this->clauses[$i]['sql'] . ')';
			}
		}


		public function negate()
		{
			$this->negate = !$this->negate;
		}


		public function addClause($type)
		{
			$r = new DB_Where($type);

			$this->add($r);

			return $r;
		}


		public function count()
		{
			return count($this->clauses);
		}


		public function textAndArgs()
		{
			$sql = [];
			$args = [];

			if (count($this->clauses) == 0) {
				return ['(1)', $args];
			}

			foreach ($this->clauses as $clause) {
				if ($clause instanceof self) {
					[$clause_sql, $clause_args] = $clause->textAndArgs();
				} else {
					$clause_sql = $clause['sql'];
					$clause_args = $clause['args'];
				}

				$sql[] = "($clause_sql)";

				$args = array_merge($args, $clause_args);
			}

			if ($this->type == 'and') {
				$sql = implode(' AND ', $sql);
			} else {
				$sql = implode(' OR ', $sql);
			}

			if ($this->negate) {
				$sql = '(NOT ' . $sql . ')';
			}

			return [$sql, $args];
		}


		// backwards compatability
		// we now return full DB_Where object here and evaluate it in preparseQueryParams
		public function text()
		{
			return $this;
		}

		public function __toString()
		{
			[$sql,] = $this->textAndArgs();
			return $sql;
		}
	}
