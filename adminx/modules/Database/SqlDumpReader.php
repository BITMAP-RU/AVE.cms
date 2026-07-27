<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/SqlDumpReader.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Database;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Потоково разбирает собственный SQL-дамп без загрузки файла целиком в память. */
	class SqlDumpReader
	{
		const MAX_UNCOMPRESSED_BYTES = 1073741824;
		const MAX_STATEMENT_BYTES = 67108864;
		const MAX_STATEMENTS = 5000000;

		public static function scan($path, callable $statementHandler = null)
		{
			$gzip = substr((string) $path, -3) === '.gz';
			$handle = $gzip ? @gzopen($path, 'rb') : @fopen($path, 'rb');
			if (!$handle) {
				throw new \RuntimeException('Не удалось прочитать файл резервной копии');
			}

			$meta = array(
				'format' => '',
				'database' => '',
				'prefixes' => '',
				'date' => '',
			);
			$summary = array(
				'bytes' => 0,
				'statements' => 0,
				'inserts' => 0,
				'tables' => array(),
			);
			$buffer = '';
			$quote = '';
			$escaped = false;

			try {
				do {
					$line = $gzip ? gzgets($handle, 1048576) : fgets($handle, 1048576);
					if ($line === false) { break; }
					$summary['bytes'] += strlen($line);
					if ($summary['bytes'] > self::MAX_UNCOMPRESSED_BYTES) {
						throw new \RuntimeException('Распакованный дамп превышает допустимый размер');
					}

					if ($quote === '' && trim($buffer) === '' && strpos(ltrim($line), '--') === 0) {
						self::readMetadata($line, $meta);
						continue;
					}

					$length = strlen($line);
					for ($i = 0; $i < $length; $i++) {
						$character = $line[$i];
						if ($quote !== '') {
							$buffer .= $character;
							if ($escaped) {
								$escaped = false;
							} elseif ($character === '\\') {
								$escaped = true;
							} elseif ($character === $quote) {
								$quote = '';
							}
						} elseif ($character === "'" || $character === '"' || $character === '`') {
							$quote = $character;
							$buffer .= $character;
						} elseif ($character === ';') {
							self::acceptStatement($buffer, $summary, $statementHandler);
							$buffer = '';
						} else {
							$buffer .= $character;
						}

						if (strlen($buffer) > self::MAX_STATEMENT_BYTES) {
							throw new \RuntimeException('Одна команда дампа превышает допустимый размер');
						}
					}
				} while (true);
			} finally {
				$gzip ? gzclose($handle) : fclose($handle);
			}

			if ($quote !== '' || trim($buffer) !== '') {
				throw new \RuntimeException('SQL-дамп оборван или содержит незавершённую команду');
			}

			if ($meta['format'] !== 'adminx full dump') {
				throw new \RuntimeException('Файл не является полным дампом AVE.cms');
			}

			if (!$summary['tables']) {
				throw new \RuntimeException('В резервной копии нет таблиц');
			}

			foreach ($summary['tables'] as $table => $state) {
				if (empty($state['drop']) || empty($state['create'])) {
					throw new \RuntimeException('Неполное описание таблицы в дампе: ' . $table);
				}
			}

			$summary['table_names'] = array_keys($summary['tables']);
			$summary['table_count'] = count($summary['tables']);
			unset($summary['tables']);

			return array_merge($meta, $summary);
		}

		public static function statementInfo($statement)
		{
			$statement = trim((string) $statement);
			if (preg_match('/^SET\s+NAMES\s+utf8mb4$/i', $statement)) {
				return array('type' => 'set', 'table' => '');
			}

			if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $statement)) {
				return array('type' => 'set', 'table' => '');
			}

			$patterns = array(
				'drop' => '/^DROP\s+TABLE\s+IF\s+EXISTS\s+`([A-Za-z0-9_]+)`$/i',
				'create' => '/^CREATE\s+TABLE\s+`([A-Za-z0-9_]+)`\s*\(/is',
				'insert' => '/^INSERT\s+INTO\s+`([A-Za-z0-9_]+)`\s*\(/is',
			);
			foreach ($patterns as $type => $pattern) {
				if (preg_match($pattern, $statement, $match)) {
					return array('type' => $type, 'table' => (string) $match[1]);
				}
			}

			throw new \RuntimeException('Дамп содержит неподдерживаемую SQL-команду');
		}

		/** Defaults for legacy ENUM columns where MySQL stored the implicit empty value. */
		public static function enumFallbacks($ddl)
		{
			$fallbacks = array();
			foreach (preg_split('/\r\n|\r|\n/', (string) $ddl) as $line) {
				if (!preg_match('/^\s*`([^`]+)`\s+enum\((.*)\)(.*?)(?:,)?\s*$/i', $line, $match)) {
					continue;
				}

				$options = array();
				foreach (self::splitSqlList($match[2]) as $token) {
					$options[] = self::decodeSqlString($token);
				}

				if (!$options || in_array('', $options, true)) {
					continue;
				}

				$fallback = '';
				if (preg_match('/\bDEFAULT\s+(\'(?:\\\\.|[^\'])*\')/i', $match[3], $defaultMatch)) {
					$fallback = self::decodeSqlString($defaultMatch[1]);
				}

				if ($fallback === '' || !in_array($fallback, $options, true)) {
					$fallback = (string) reset($options);
				}

				$fallbacks[(string) $match[1]] = $fallback;
			}

			return $fallbacks;
		}

		/** Normalize only the implicit empty ENUM value; all other SQL remains untouched. */
		public static function normalizeEnumInsert($statement, array $fallbacks)
		{
			if (!$fallbacks) {
				return (string) $statement;
			}

			if (!preg_match('/^(INSERT\s+INTO\s+`[^`]+`\s*\()(.*?)(\)\s+VALUES\s*\()(.*)(\)\s*)$/is', (string) $statement, $match)) {
				return (string) $statement;
			}

			preg_match_all('/`([^`]+)`/', $match[2], $columnMatches);
			$columns = isset($columnMatches[1]) ? $columnMatches[1] : array();
			$values = self::splitSqlList($match[4]);
			if (count($columns) !== count($values)) {
				throw new \RuntimeException('Не удалось проверить ENUM-значения в команде дампа');
			}

			foreach ($columns as $index => $column) {
				if (isset($fallbacks[$column]) && trim((string) $values[$index]) === "''") {
					$values[$index] = self::encodeSqlString($fallbacks[$column]);
				}
			}

			return $match[1] . $match[2] . $match[3] . implode(',', $values) . $match[5];
		}

		protected static function acceptStatement($buffer, array &$summary, $statementHandler)
		{
			$statement = self::resolvePortablePrefix(trim((string) $buffer));
			if ($statement === '') { return; }

			$summary['statements']++;
			if ($summary['statements'] > self::MAX_STATEMENTS) {
				throw new \RuntimeException('В дампе слишком много SQL-команд');
			}

			$info = self::statementInfo($statement);
			$table = $info['table'];
			if ($table !== '') {
				if (!Model::isManagedTableName($table)) {
					throw new \RuntimeException('Таблица не относится к текущей установке: ' . $table);
				}

				if (!isset($summary['tables'][$table])) {
					$summary['tables'][$table] = array('drop' => false, 'create' => false, 'inserts' => 0);
				}

				if ($info['type'] === 'drop' || $info['type'] === 'create') {
					if ($summary['tables'][$table][$info['type']]) {
						throw new \RuntimeException('Команда таблицы продублирована в дампе: ' . $table);
					}

					$summary['tables'][$table][$info['type']] = true;
				} elseif ($info['type'] === 'insert') {
					$summary['tables'][$table]['inserts']++;
					$summary['inserts']++;
				}
			}

			if ($statementHandler) {
				call_user_func($statementHandler, $statement, $info);
			}
		}

		protected static function resolvePortablePrefix($statement)
		{
			$statement = (string) $statement;
			$replacement = '`' . Model::basePrefix() . '_';
			if (preg_match('/^CREATE\s+TABLE\s+/i', $statement)) {
				//-- В DDL placeholder может встречаться в имени таблицы, constraint и REFERENCES.
				return str_replace('`{{prefix}}_', $replacement, $statement);
			}

			//-- В INSERT заменяем только имя целевой таблицы: значения строк неприкосновенны.
			return preg_replace(
				'/^(DROP\s+TABLE\s+IF\s+EXISTS\s+|INSERT\s+INTO\s+)`\{\{prefix\}\}_/i',
				'$1' . $replacement,
				$statement,
				1
			);
		}

		protected static function splitSqlList($value)
		{
			$items = array();
			$buffer = '';
			$quote = '';
			$escaped = false;
			$length = strlen((string) $value);
			for ($index = 0; $index < $length; $index++) {
				$character = $value[$index];
				if ($quote !== '') {
					$buffer .= $character;
					if ($escaped) {
						$escaped = false;
					} elseif ($character === '\\') {
						$escaped = true;
					} elseif ($character === $quote) {
						$quote = '';
					}
				} elseif ($character === "'" || $character === '"' || $character === '`') {
					$quote = $character;
					$buffer .= $character;
				} elseif ($character === ',') {
					$items[] = trim($buffer);
					$buffer = '';
				} else {
					$buffer .= $character;
				}
			}

			$items[] = trim($buffer);
			return $items;
		}

		protected static function decodeSqlString($token)
		{
			$token = trim((string) $token);
			if (strlen($token) < 2 || $token[0] !== "'" || substr($token, -1) !== "'") {
				return $token;
			}

			return stripcslashes(substr($token, 1, -1));
		}

		protected static function encodeSqlString($value)
		{
			return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
		}

		protected static function readMetadata($line, array &$meta)
		{
			$value = trim(substr(ltrim((string) $line), 2));
			if (strcasecmp($value, 'adminx full dump') === 0) {
				$meta['format'] = 'adminx full dump';
				return;
			}

			foreach (array('database', 'prefixes', 'date') as $key) {
				if (stripos($value, $key . ':') === 0) {
					$meta[$key] = trim(substr($value, strlen($key) + 1));
					return;
				}
			}
		}
	}
