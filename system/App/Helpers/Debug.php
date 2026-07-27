<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Debug.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	use App\Common\DebugChannel;

	class Debug
	{
		protected static $time   = [];
		protected static $memory = [];

		public static $max_nesting_level = 5;
		public static $js_toggle_open    = false;
		public static $_document_content = '';

		protected static $js_displayed  = false;
		protected static $css_displayed = false;
		protected static $files         = [];


		protected function __construct() {}
		protected function __clone()    {}

		public static function _echo($var, $exit = false, $bg = null, $echo = true)
		{
			return self::echo($var, (bool) $exit, $bg, (bool) $echo);
		}

		public static function _print($var, $exit = false, $bg = null, $echo = true)
		{
			return self::print($var, (bool) $exit, $bg, (bool) $echo);
		}

		public static function _dump($var, $exit = false, $bg = null, $append = true, $fileName = '')
		{
			self::dump($var, (bool) $exit, $bg, (bool) $append);
		}

		public static function _($var, $bg = null, $from = '')
		{
			return self::echo($var, false, $bg, true);
		}

		public static function formatSize($size)
		{
			return Number::formatSize((float) $size);
		}

		public static function getStatistic($type = null)
		{
			$queries = method_exists('DB', 'getQueries') ? (array) \DB::getQueries() : array();
			$queryTime = 0.0;
			foreach ($queries as $query) {
				$queryTime += isset($query['time']) ? (float) $query['time'] : 0.0;
			}

			switch ($type) {
				case 'time':
					$start = defined('START_MICROTIME') ? self::timestamp(START_MICROTIME) : microtime(true);
					return number_format(microtime(true) - $start, 3, ',', ' ');
				case 'memory':
					return self::formatSize(memory_get_usage() - (defined('START_MEMORY') ? START_MEMORY : 0));
				case 'peak': return self::formatSize(memory_get_peak_usage());
				case 'sqlcount':
				case 'sqltrace': return count($queries);
				case 'sqltime': return $queryTime;
			}

			return null;
		}

		public static function elapsed($start)
		{
			return max(0.0, microtime(true) - self::timestamp($start));
		}

		protected static function timestamp($value)
		{
			$parts = explode(' ', trim((string) $value), 2);
			return count($parts) === 2 ? (float) $parts[1] + (float) $parts[0] : (float) $value;
		}


		// ── Assets ──────────────────────────────────────────────────────────────

		public static function addCss(): void
		{
			if (self::$css_displayed) {
				return;
			}

			self::$css_displayed = true;

			echo '<style>
			.debug-block  { border:1px solid #d0e0e3; margin:10px; font-size:11px; font-family:Consolas,Verdana,Arial; border-radius:5px; background:#f2f6fa; box-shadow:0 2px 4px rgba(0,0,0,.28); }
			.debug-bg     { color:#242a2f; background:#eaedf7; }
			.debug-header { color:#fff; font-weight:bold; padding:10px 15px; cursor:pointer; text-shadow:0 1px 1px rgba(0,0,0,.75); }
			.debug-content{ padding-bottom:10px; }
			.debug-box    { margin:10px 10px 0; padding:4px; background:#fff; border:1px solid #dedcdc; overflow:hidden; }
			.debug-error  { border-color:#f00 !important; }
		</style>';
		}

		public static function addJs(): void
		{
			if (self::$js_displayed) {
				return;
			}

			self::$js_displayed = true;

			echo '<script>function toggleDebug(el){var c=el.nextElementSibling;c.style.display=c.style.display=="none"?"block":"none";}</script>';
		}

		private static function ensureAssets(): void
		{
			if (!self::$css_displayed) {
				self::addCss();
			}

			if (!self::$js_displayed) {
				self::addJs();
			}
		}


		// ── Private helpers ──────────────────────────────────────────────────────

		/**
		 * Возвращает файл и строку места вызова Debug::method().
		 */
		private static function callerInfo(): array
		{
			$bt   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
			$caller = $bt[2] ?? $bt[1] ?? [];

			$file = $caller['file'] ?? '';
			$line = (int) ($caller['line'] ?? 0);

			if ($file && preg_match('/([^\(]*)\(/', $file, $m)) {
				$file = $m[1];
			}

			return ['file' => $file, 'line' => $line];
		}

		/**
		 * Пытается извлечь имя переменной из исходной строки файла.
		 */
		private static function varName(string $file, int $line, string $funcName): string
		{
			if (!$file || !is_readable($file)) {
				return 'UNKNOWN';
			}

			$fh = fopen($file, 'rb');
			if ($fh === false) {
				return 'UNKNOWN';
			}

			$code = '';
			$i    = 0;
			while (++$i <= $line) {
				$code = fgets($fh);
			}

			fclose($fh);

			preg_match('/' . preg_quote($funcName, '/') . '\s*\((.*)\)\s*;/u', $code, $m);
			if (!empty($m[1])) {
				return trim(explode(',', $m[1])[0]);
			}

			return 'EVAL';
		}

		/**
		 * Захватывает var_dump / print_r / var_export и возвращает отформатированный HTML.
		 */
		private static function captureVar($var, string $func): string
		{
			ob_start();

			switch ($func) {
				case 'print_r':    print_r($var);    break;
				case 'var_export': var_export($var);  break;
				default:           var_dump($var);   break;
			}

			$out = ob_get_clean();
			$out = preg_replace('/=>(\s+|\s$)/', ' => ', $out);
			$out = htmlspecialchars($out);
			$out = preg_replace('/(=&gt;)/', '<span style="color:#ff8c00;">$1</span>', $out);

			return $out;
		}


		// ── Template ─────────────────────────────────────────────────────────────

		public static function _debug_tpl(array $arg): string
		{
			$color = ltrim($arg['DEBUG_COLOR'] ?? '43648c', '#');

			$html  = '<div class="debug-block" style="border-color:#' . $color . '">';
			$html .= '<div class="debug-bg">';
			$html .= '<div class="debug-header" onclick="toggleDebug(this);" style="background:#' . $color . ';">' . ($arg['DEBUG_HEADER'] ?? '') . '</div>';
			$html .= '<div class="debug-content">';

			if (!empty($arg['DEBUG_MSG_BOX'])) {
				$html .= '<div class="debug-box"><pre style="background:#fff;color:#3d4b52;margin:0;padding:5px;border:0;">' . $arg['DEBUG_MSG_BOX'] . '</pre></div>';
			}

			if (!empty($arg['DEBUG_MSG_TEXT'])) {
				$html .= '<div class="debug-box">' . $arg['DEBUG_MSG_TEXT'] . '</div>';
			}

			if (!empty($arg['DEBUG_SQL_QUERY'])) {
				$html .= '<div class="debug-box"><strong>SQL:</strong></div>';
				$html .= '<div class="debug-box"><pre style="background:#fff;color:#3d4b52;margin:0;padding:5px;border:0;">' . htmlspecialchars($arg['DEBUG_SQL_QUERY']) . '</pre></div>';
			}

			if (!empty($arg['DEBUG_CLASS_LINE'])) {
				$html .= $arg['DEBUG_CLASS_LINE'];
			}

			if (!empty($arg['DEBUG_CODE_LINE'])) {
				$html .= '<div class="debug-box">' . $arg['DEBUG_CODE_LINE'] . '</div>';
			}

			if (!empty($arg['DEBUG_CALLER'])) {
				$html .= '<div class="debug-box"><pre style="background:#fff;color:#3d4b52;margin:0;padding:5px;border:0;">' . $arg['DEBUG_CALLER'] . '</pre></div>';
			}

			$html .= '</div></div></div>';

			return $html;
		}


		// ── Public dump methods ───────────────────────────────────────────────────

		/**
		 * Сохраняет значение в защищённой панели публичной отладки без вывода в HTML.
		 */
		public static function panel($var, $label = '')
		{
			$info = self::callerInfo();
			if (trim((string) $label) === '') {
				$label = self::varName($info['file'], $info['line'], __FUNCTION__);
			}

			DebugChannel::push($var, $label, $info['file'], $info['line']);
			return $var;
		}

		/**
		 * var_dump переменной прямо в страницу.
		 */
		public static function echo($var, bool $exit = false, $bg = null, bool $echo = true)
		{
			self::ensureAssets();

			$info    = self::callerInfo();
			$varName = self::varName($info['file'], $info['line'], __FUNCTION__);
			$dump    = self::captureVar($var, 'var_dump');

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => $bg ?: '43648c',
				'DEBUG_HEADER'     => 'Debug: var_dump(<strong>' . $varName . '</strong>)',
				'DEBUG_CLASS_LINE' => self::dumpTrace(),
				'DEBUG_CODE_LINE'  => self::fileLines($info['file'], $info['line']),
				'DEBUG_MSG_BOX'    => $dump,
			]);

			if (!$echo) {
				return $html;
			}

			echo $html;

			if ($exit) {
				exit;
			}

			return false;
		}

		/**
		 * print_r переменной прямо в страницу.
		 */
		public static function print($var, bool $exit = false, $bg = null, bool $echo = true)
		{
			self::ensureAssets();

			$info    = self::callerInfo();
			$varName = self::varName($info['file'], $info['line'], __FUNCTION__);
			$dump    = self::captureVar($var, 'print_r');

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => $bg ?: '4e5665',
				'DEBUG_HEADER'     => 'Debug: print_r(<strong>' . $varName . '</strong>)',
				'DEBUG_CLASS_LINE' => self::dumpTrace(),
				'DEBUG_CODE_LINE'  => self::fileLines($info['file'], $info['line']),
				'DEBUG_MSG_BOX'    => $dump,
			]);

			if (!$echo) {
				return $html;
			}

			echo $html;

			if ($exit) {
				exit;
			}

			return false;
		}

		/**
		 * var_export переменной прямо в страницу.
		 */
		public static function exp($var, bool $exit = false, $bg = null, bool $echo = true)
		{
			self::ensureAssets();

			$info    = self::callerInfo();
			$varName = self::varName($info['file'], $info['line'], __FUNCTION__);
			$dump    = self::captureVar($var, 'var_export');

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => $bg ?: '333a4d',
				'DEBUG_HEADER'     => 'Debug: var_export(<strong>' . $varName . '</strong>)',
				'DEBUG_CLASS_LINE' => self::dumpTrace(),
				'DEBUG_CODE_LINE'  => self::fileLines($info['file'], $info['line']),
				'DEBUG_MSG_BOX'    => $dump,
			]);

			if (!$echo) {
				return $html;
			}

			echo $html;

			if ($exit) {
				exit;
			}

			return false;
		}

		/**
		 * Записывает var_dump в debug.html (не в страницу).
		 */
		public static function dump($var, bool $exit = false, $bg = null, bool $append = true): void
		{
			self::ensureAssets();

			$info    = self::callerInfo();
			$varName = self::varName($info['file'], $info['line'], __FUNCTION__);
			$dump    = self::captureVar($var, 'var_dump');

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => $bg ?: '43648c',
				'DEBUG_HEADER'     => 'Debug::dump(<strong>' . $varName . '</strong>)',
				'DEBUG_CLASS_LINE' => self::dumpTrace(),
				'DEBUG_CODE_LINE'  => self::fileLines($info['file'], $info['line']),
				'DEBUG_MSG_BOX'    => $dump,
			]);

			$flag = $append ? FILE_APPEND : 0;
			file_put_contents(BASEPATH . '/debug.html', $html, $flag);

			if ($exit) {
				exit;
			}
		}

		/**
		 * Произвольное сообщение в страницу.
		 */
		public static function errorMsg(string $msg): void
		{
			self::ensureAssets();

			$info = self::callerInfo();

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => '7350e6',
				'DEBUG_HEADER'     => 'Message',
				'DEBUG_CLASS_LINE' => self::dumpTrace(),
				'DEBUG_CODE_LINE'  => self::fileLines($info['file'], $info['line']),
				'DEBUG_MSG_BOX'    => htmlspecialchars($msg),
			]);

			echo $html;
		}

		/**
		 * Вывод ошибки MySQL: текст, SQL-запрос, стек вызовов.
		 * Вызывается из Database при ошибке запроса.
		 */
		public static function errorSql(string $header, string $sql, $caller = null, bool $exit = false): void
		{
			self::ensureAssets();

			$html = self::_debug_tpl([
				'DEBUG_COLOR'      => 'cc0000',
				'DEBUG_HEADER'     => '<strong>MySQL Error</strong>',
				'DEBUG_MSG_TEXT'   => htmlspecialchars(preg_replace('/\s+/', ' ', $header)),
				'DEBUG_SQL_QUERY'  => $sql,
				'DEBUG_CALLER'     => $caller !== null ? self::prePrint($caller) : '',
			]);

			echo $html;

			if ($exit) {
				exit;
			}
		}


		// ── Trace helpers ─────────────────────────────────────────────────────────

		public static function dumpTrace(): string
		{
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

			$trace = $bt[2] ?? $bt[1] ?? [];
			$upper = $bt[3] ?? [];

			$file     = isset($trace['file']) ? File::cutRootPath($trace['file']) : '—';
			$line     = $trace['line'] ?? '—';
			$class    = $upper['class']    ?? 'None';
			$type     = $upper['type']     ?? '::';
			$function = $upper['function'] ?? 'None';

			return sprintf(
				'<div class="debug-box">Class: <strong>%s</strong> | Type: <strong>%s</strong> | Function: <strong>%s</strong></div>'
				. '<div class="debug-box">File: <strong>%s</strong> line <strong>%s</strong></div>',
				htmlspecialchars($class),
				htmlspecialchars($type),
				htmlspecialchars($function),
				htmlspecialchars($file),
				$line
			);
		}

		public static function debugBacktrace(): array
		{
			$_trace = [];

			$_backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			array_shift($_backtrace);

			foreach ($_backtrace as $history) {
				if (isset($history['file'], $history['line'])) {
					$_trace[] = [
						'file'     => File::cutRootPath($history['file']),
						'line'     => $history['line'],
						'function' => (isset($history['class'], $history['type'])
							? $history['class'] . $history['type']
							: '') . $history['function'],
					];
				}
			}

			return $_trace;
		}

		public static function prePrint($var): string
		{
			ob_start();
			print_r($var);
			$out = htmlspecialchars(ob_get_clean());
			$out = preg_replace('/(=&gt;)/', '<span style="color:#ff8c00;">$1</span>', $out);

			return $out;
		}


		// ── File source viewer ────────────────────────────────────────────────────

		public static function fileLines(string $filepath, int $line_num, bool $highlight = true, int $padding = 3)
		{
			if (!$filepath
				|| strpos($filepath, "eval()'d code") !== false
				|| strpos($filepath, 'runtime-created function') !== false
			) {
				return '';
			}

			if (!isset(static::$files[$filepath])) {
				if (!File::exists($filepath)) {
					return false;
				}

				static::$files[$filepath] = file($filepath, FILE_IGNORE_NEW_LINES);
				array_unshift(static::$files[$filepath], '');
			}

			$start  = max(0, $line_num - $padding);
			$length = ($line_num - $start) + $padding + 1;

			if (($start + $length) > count(static::$files[$filepath]) - 1) {
				$length = null;
			}

			$debug_lines = array_slice(static::$files[$filepath], $start, $length, true);

			if ($highlight) {
				foreach ($debug_lines as $key => $line) {
					$isActive = ($key === $line_num);

					$rowStyle = $isActive
						? 'display:block;color:#000;background:#fffbeb;border-left:3px solid #f59e0b;margin-left:-4px;padding-left:4px;'
						: 'display:block;color:#000;background:#fff;border-left:3px solid transparent;margin-left:-4px;padding-left:4px;';

					$lineNumStyle = 'display:inline-block;min-width:32px;padding-right:10px;margin-right:8px;'
						. 'text-align:right;color:#94a3b8;border-right:2px solid #e2e8f0;'
						. 'user-select:none;font-style:normal;'
						. ($isActive ? 'color:#b45309;font-weight:700;' : '');

					$lineNum = '<span style="' . $lineNumStyle . '">' . $key . '</span>';

					$line = str_replace(
						['<code>', '</code>', '<span style="color: #0000BB">&lt;?php&nbsp;', "\n", '<span style="color: #000000">'],
						['', '', '<span style="color:#0000bb">' . $lineNum, '', '<span style="' . $rowStyle . '">'],
						highlight_string('<?php ' . $line, true)
					);

					$debug_lines[$key] = $line;
				}
			}

			return implode('', $debug_lines);
		}


		// ── Timers & memory ───────────────────────────────────────────────────────

		public static function startTime(string $name = ''): void
		{
			self::$time[$name] = microtime(true);
		}

		public static function endTime(string $name = '')
		{
			return isset(self::$time[$name])
				? microtime(true) - self::$time[$name]
				: false;
		}

		public static function startMemory(string $name = ''): void
		{
			self::$memory[$name] = memory_get_usage();
		}

		public static function endMemory(string $name = '')
		{
			return isset(self::$memory[$name])
				? Number::formatSize(memory_get_usage() - self::$memory[$name])
				: false;
		}


		// ── Benchmark ─────────────────────────────────────────────────────────────

		public static function benchmark(callable $callable, array $params = []): array
		{
			$before = microtime(true);
			$result = is_callable($callable) ? call_user_func_array($callable, $params) : null;
			$after  = microtime(true);

			return [
				'time'   => sprintf('%1.6f', $after - $before),
				'result' => $result,
			];
		}
	}
