<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentHookRuntime.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\StoredPhpRuntime;

	/** Execution boundary for PHP stored in rubric document lifecycle hooks. */
	class DocumentHookRuntime
	{
		public static function execute($code, DocumentHookContext $hook, array &$legacyContext, $owner = null)
		{
			$code = (string) $code;
			if (trim($code) === '') {
				return array('executed' => false, 'duration' => 0.0, 'output' => '');
			}

			$legacyContext['hook'] = $hook;
			$legacyContext['phase'] = $hook->phase();
			$legacyContext['is_new'] = $hook->isNew();
			$legacyContext['actor_id'] = $hook->actorId();
			$started = microtime(true);

			try {
				$output = StoredPhpRuntime::evaluateMutable(' ?>' . $code . '<?php ', $legacyContext, $owner);
				if (trim($output) !== '') {
					$hook->log('Вывод lifecycle-кода', array('output' => trim($output)));
				}

				return array(
					'executed' => true,
					'duration' => microtime(true) - $started,
					'output' => $output,
				);
			} catch (\Throwable $error) {
				$wrapped = DocumentHookException::fromThrowable($error, $hook);
				error_log($wrapped->getMessage());
				throw $wrapped;
			}
		}
	}
