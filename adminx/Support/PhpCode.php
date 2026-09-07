<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/PhpCode.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	class PhpCode
	{
		const LINT_TIMEOUT = 3;

		public static function source($code)
		{
			$code = trim((string) $code);
			if (preg_match('/^<\?(?:php|=)?/i', $code)) {
				return $code;
			}

			return "<?php\n" . $code . "\n";
		}

		public static function lint($code)
		{
			$directory = BASEPATH . '/tmp/console';
			if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
				return array('ok' => false, 'message' => 'Не удалось создать временный файл.', 'output' => '', 'line' => 0);
			}

			$file = $directory . '/lint-' . bin2hex(random_bytes(8)) . '.php';
			if (@file_put_contents($file, self::source($code)) === false) {
				@unlink($file);
				return array('ok' => false, 'message' => 'Не удалось записать временный файл.', 'output' => '', 'line' => 0);
			}

			if (!function_exists('proc_open')) {
				@unlink($file);
				return array('ok' => false, 'message' => 'Проверка PHP недоступна на сервере.', 'output' => 'Функция proc_open отключена хостингом.', 'line' => 0);
			}

			$php = self::resolvePhpBinary();
			if ($php === '') {
				@unlink($file);
				return array('ok' => false, 'message' => 'PHP CLI не найден.', 'output' => 'Укажите путь к PHP CLI в ADMINX_PHP_BINARY.', 'line' => 0);
			}

			$result = self::runProcess(
				escapeshellarg($php) . ' -l ' . escapeshellarg($file),
				self::LINT_TIMEOUT
			);
			@unlink($file);
			if ($result['timed_out']) {
				return array('ok' => false, 'message' => 'Проверка PHP превысила лимит времени.', 'output' => 'Проверьте ADMINX_PHP_BINARY: требуется консольный PHP, а не php-fpm.', 'line' => 0);
			}

			$text = trim($result['output']);
			$text = str_replace($file, 'код', $text);
			$line = 0;
			if (preg_match('/ on line ([0-9]+)/i', $text, $match)) {
				$line = (int) $match[1];
			}

			return array(
				'ok' => $result['exit_code'] === 0 && $text !== '',
				'message' => $result['exit_code'] === 0 ? 'Синтаксис PHP без ошибок.' : 'В PHP-коде есть синтаксическая ошибка.',
				'output' => $text,
				'line' => $line,
			);
		}

		protected static function resolvePhpBinary()
		{
			$candidates = array();
			$configured = trim((string) getenv('ADMINX_PHP_BINARY'));
			if ($configured !== '') { $candidates[] = $configured; }
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

			foreach (array_unique($candidates) as $candidate) {
				$result = self::runProcess(
					escapeshellarg((string) $candidate) . ' -r ' . escapeshellarg('echo PHP_VERSION_ID;'),
					1
				);
				if (!$result['timed_out'] && $result['exit_code'] === 0 && preg_match('/^\d{5,6}$/', trim($result['output']))) {
					return (string) $candidate;
				}
			}

			return '';
		}

		protected static function runProcess($command, $timeout)
		{
			$pipes = array();
			try {
				$process = @proc_open($command, array(
					0 => array('pipe', 'r'),
					1 => array('pipe', 'w'),
					2 => array('pipe', 'w'),
				), $pipes);
			} catch (\Throwable $e) {
				return array('exit_code' => 1, 'timed_out' => false, 'output' => $e->getMessage());
			}

			if (!is_resource($process)) {
				return array('exit_code' => 1, 'timed_out' => false, 'output' => 'Не удалось запустить PHP CLI.');
			}

			fclose($pipes[0]);
			stream_set_blocking($pipes[1], false);
			stream_set_blocking($pipes[2], false);
			$started = microtime(true);
			$output = '';
			$error = '';
			$timedOut = false;
			$knownExitCode = null;
			do {
				$output .= (string) stream_get_contents($pipes[1]);
				$error .= (string) stream_get_contents($pipes[2]);
				$status = proc_get_status($process);
				if (!$status['running']) {
					$knownExitCode = isset($status['exitcode']) ? (int) $status['exitcode'] : null;
					break;
				}

				if (microtime(true) - $started >= max(1, (int) $timeout)) {
					$timedOut = true;
					proc_terminate($process, 9);
					break;
				}

				usleep(20000);
			} while (true);

			$output .= (string) stream_get_contents($pipes[1]);
			$error .= (string) stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$closedExitCode = proc_close($process);
			$exitCode = $knownExitCode !== null && $knownExitCode >= 0 ? $knownExitCode : $closedExitCode;

			return array(
				'exit_code' => $exitCode,
				'timed_out' => $timedOut,
				'output' => $output . ($error !== '' ? ($output !== '' ? "\n" : '') . $error : ''),
			);
		}
	}
