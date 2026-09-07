<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Console/Runner.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Console;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\PhpCode;
	use App\Common\PublicConfiguration;
	use App\Common\TemporaryDirectory;

	class Runner
	{
		const TIMEOUT = 5;
		const OUTPUT_LIMIT = 262144;

		public static function enabled()
		{
			$value = getenv('ADMINX_CONSOLE_ENABLED');
			if ($value !== false && $value !== '') {
				return filter_var($value, FILTER_VALIDATE_BOOLEAN);
			}

			$config = PublicConfiguration::value('admin_console', array());
			return is_array($config) && !empty($config['enabled']);
		}

		public static function execute($code)
		{
			if (!self::enabled()) {
				throw new \RuntimeException('Выполнение PHP-консоли отключено окружением.');
			}

			if (!function_exists('proc_open')) {
				throw new \RuntimeException('proc_open недоступен: изолированный запуск невозможен.');
			}

			$lint = PhpCode::lint($code);
			if (!$lint['ok']) {
				throw new \RuntimeException($lint['message'] . ($lint['line'] ? ' Строка ' . $lint['line'] . '.' : ''));
			}

			$dir = TemporaryDirectory::create('console');

			$codeFile = $dir . '/snippet.php';
			$runnerFile = $dir . '/runner.php';
			$runner = "<?php\n"
				. "define('START_MICROTIME', microtime());\n"
				. "define('START_MEMORY', memory_get_usage());\n"
				. "define('AVE_CMS', true);\n"
				. "define('DS', DIRECTORY_SEPARATOR);\n"
				. "define('BASEPATH', \$argv[1]);\n"
				. "define('SESSION_SAVE_HANDLER', 'Files');\n"
				. "\$_COOKIE = array();\n"
				. "unset(\$_SERVER['HTTP_COOKIE']);\n"
				. "include BASEPATH . DS . 'system' . DS . 'bootstrap.php';\n"
				. "App::init();\n"
				. "include \$argv[2];\n";
			@file_put_contents($codeFile, PhpCode::source($code));
			@file_put_contents($runnerFile, $runner);

			$php = self::resolvePhpBinary();
			$command = escapeshellarg($php)
				. ' -d display_errors=1 -d memory_limit=128M'
				. ' -d disable_functions=exec,shell_exec,system,passthru,popen'
				. ' ' . escapeshellarg($runnerFile)
				. ' ' . escapeshellarg(BASEPATH)
				. ' ' . escapeshellarg($codeFile);
			$pipes = array();
			$process = @proc_open($command, array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w'),
			), $pipes, $dir);
			if (!is_resource($process)) {
				self::cleanup($dir);
				throw new \RuntimeException('Не удалось запустить PHP-процесс.');
			}

			fclose($pipes[0]);
			stream_set_blocking($pipes[1], false);
			stream_set_blocking($pipes[2], false);
			$started = microtime(true);
			$stdout = '';
			$stderr = '';
			$timedOut = false;
			$knownExitCode = null;
			do {
				$stdout .= (string) stream_get_contents($pipes[1]);
				$stderr .= (string) stream_get_contents($pipes[2]);
				$status = proc_get_status($process);
				if (!$status['running']) {
					$knownExitCode = isset($status['exitcode']) ? (int) $status['exitcode'] : null;
					break;
				}

				if (microtime(true) - $started >= self::TIMEOUT) {
					$timedOut = true;
					proc_terminate($process, 9);
					break;
				}

				usleep(20000);
			} while (true);
			$stdout .= (string) stream_get_contents($pipes[1]);
			$stderr .= (string) stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$closedExitCode = proc_close($process);
			$exitCode = $knownExitCode !== null && $knownExitCode >= 0 ? $knownExitCode : $closedExitCode;
			self::cleanup($dir);

			$output = $stdout . ($stderr !== '' ? ($stdout !== '' ? "\n" : '') . $stderr : '');
			$truncated = strlen($output) > self::OUTPUT_LIMIT;
			if ($truncated) {
				$output = substr($output, 0, self::OUTPUT_LIMIT) . "\n[вывод сокращён]";
			}

			return array(
				'ok' => !$timedOut && $exitCode === 0,
				'exit_code' => $exitCode,
				'timed_out' => $timedOut,
				'truncated' => $truncated,
				'duration_ms' => (int) round((microtime(true) - $started) * 1000),
				'output' => $output,
			);
		}

		/** Find a CLI binary compatible with the application runtime. */
		protected static function resolvePhpBinary()
		{
			$candidates = array();
			$configured = trim((string) getenv('ADMINX_PHP_BINARY'));
			if ($configured !== '') {
				$candidates[] = $configured;
			}

			$config = PublicConfiguration::value('admin_console', array());
			if (is_array($config) && !empty($config['php_binary'])) {
				$candidates[] = trim((string) $config['php_binary']);
			}

			if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
				$candidates[] = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php';
			}

			if (defined('PHP_BINARY') && PHP_BINARY !== '') {
				$candidates[] = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php';
				$candidates[] = PHP_BINARY;
			}

			$candidates[] = 'php';
			$candidates = array_values(array_unique($candidates));
			$detected = array();
			foreach ($candidates as $candidate) {
				$version = self::probePhpBinary($candidate);
				if ($version >= 70300) {
					return $candidate;
				}

				if ($version > 0) {
					$detected[] = $candidate . ' (PHP ' . self::formatVersionId($version) . ')';
				}
			}

			$message = 'Для PHP-консоли нужен CLI-интерпретатор PHP 7.3 или новее.';
			if ($detected) {
				$message .= ' Найдены несовместимые: ' . implode(', ', $detected) . '.';
			}

			throw new \RuntimeException($message . ' Укажите путь в ADMINX_PHP_BINARY.');
		}

		protected static function probePhpBinary($binary)
		{
			$command = escapeshellarg((string) $binary) . ' -r ' . escapeshellarg('echo PHP_VERSION_ID;');
			$pipes = array();
			$process = @proc_open($command, array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w'),
			), $pipes);
			if (!is_resource($process)) {
				return 0;
			}

			fclose($pipes[0]);
			stream_set_blocking($pipes[1], false);
			stream_set_blocking($pipes[2], false);
			$started = microtime(true);
			$output = '';
			do {
				$output .= (string) stream_get_contents($pipes[1]);
				$status = proc_get_status($process);
				if (!$status['running']) {
					break;
				}

				if (microtime(true) - $started >= 2) {
					proc_terminate($process, 9);
					break;
				}

				usleep(10000);
			} while (true);
			$output .= (string) stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);

			$output = trim($output);
			return preg_match('/^\d{5,6}$/', $output) === 1 ? (int) $output : 0;
		}

		protected static function formatVersionId($version)
		{
			$version = (int) $version;
			return (int) floor($version / 10000)
				. '.' . (int) floor(($version % 10000) / 100)
				. '.' . (int) ($version % 100);
		}

		protected static function cleanup($dir)
		{
			TemporaryDirectory::remove($dir);
		}
	}
