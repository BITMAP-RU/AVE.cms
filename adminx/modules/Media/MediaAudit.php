<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/MediaAudit.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use App\Helpers\Dir;
	use App\Helpers\File;
	use App\Helpers\Number;
	use App\Frontend\Media\ThumbnailUrl;
	use DB;

	/**
	 * Read-only inventory of uploads and references to them.
	 *
	 * An unused file is only a review candidate: a project may build its path
	 * dynamically. The analyzer never removes or rewrites files.
	 */
	class MediaAudit
	{
		const REPORT_VERSION = 1;
		const LARGE_FILE_BYTES = 5242880;
		const LARGE_IMAGE_SIDE = 4000;
		const MAX_SOURCE_FILE_BYTES = 2097152;

		protected static $imageExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');

		public static function report()
		{
			$path = self::reportPath();
			if (!is_file($path)) {
				return null;
			}

			$data = json_decode((string) File::getContent($path), true);
			return is_array($data) && isset($data['version']) && (int) $data['version'] === self::REPORT_VERSION
				? $data
				: null;
		}

		/** Read current references for one file or folder without trusting a saved report. */
		public static function inspectUsage($path, $limit = 50)
		{
			$rows = self::inspectUsageMany(array($path), $limit);
			if (!$rows) {
				throw new \RuntimeException('Файл или папка не найдены');
			}

			return reset($rows);
		}

		/** Read current references for several files in one database/source pass. */
		public static function inspectUsageMany(array $paths, $limit = 50, $includeHistorical = true)
		{
			$targets = array();
			foreach ($paths as $path) {
				$path = Model::normalize($path, '');
				$absolute = $path !== '' ? Model::abs($path) : '';
				if ($path === '' || (!is_file($absolute) && !is_dir($absolute))) { continue; }
				$targets[$path] = array(
					'is_directory' => is_dir($absolute),
					'uses' => array(),
					'seen' => array(),
					'count' => 0,
				);
			}

			if (!$targets) { return array(); }

			$references = self::databaseReferences((bool) $includeHistorical);
			self::sourceReferences($references);
			$limit = max(1, (int) $limit);
			foreach ($references as $reference) {
				$candidate = self::normalizeReference(isset($reference['path']) ? $reference['path'] : '');
				if ($candidate === '') { continue; }
				$reference['path'] = $candidate;
				$key = sha1(json_encode($reference));
				foreach ($targets as $path => &$target) {
					if (!self::referenceMatches($candidate, $path, $target['is_directory']) || isset($target['seen'][$key])) { continue; }
					$target['seen'][$key] = true;
					$target['count']++;
					if (count($target['uses']) < $limit) { $target['uses'][] = $reference; }
				}

				unset($target);
			}

			$checkedAt = time();
			$result = array();
			foreach ($targets as $path => $target) {
				$result[$path] = array(
					'checked' => true,
					'live' => true,
					'path' => $path,
					'use_count' => (int) $target['count'],
					'uses' => $target['uses'],
					'report' => array(
						'available' => true,
						'generated_at' => $checkedAt,
						'generated_label' => date('d.m.Y H:i:s', $checkedAt),
					),
				);
			}

			return $result;
		}

		public static function run()
		{
			$started = microtime(true);
			$inventory = self::inventory();
			$references = self::databaseReferences();
			self::sourceReferences($references);

			$usage = array();
			$invalid = array();
			foreach ($references as $reference) {
				$normalized = self::normalizeReference($reference['path']);
				if ($normalized === '') {
					$reference['reason'] = 'Путь содержит недопустимые сегменты или не относится к uploads';
					$invalid[] = $reference;
					continue;
				}

				$reference['path'] = $normalized;
				if (!isset($usage[$normalized])) {
					$usage[$normalized] = array();
				}

				$usage[$normalized][] = $reference;
			}

			self::propagateCompanionUsage($usage, $inventory['files']);

			$missingOriginals = array();
			foreach ($usage as $path => $items) {
				if (!isset($inventory['files'][$path])) {
					$missingOriginals[] = array(
						'path' => $path,
						'uses' => $items,
						'use_count' => count($items),
					);
				}
			}

			foreach ($inventory['orphan_previews'] as $preview) {
				$missingOriginals[] = array(
					'path' => $preview['original'],
					'preview' => $preview['path'],
					'uses' => array(),
					'use_count' => 0,
				);
			}

			$unused = array();
			$missingPreviews = array();
			$large = array();
			$files = array();
			foreach ($inventory['files'] as $path => $file) {
				$file['uses'] = isset($usage[$path]) ? $usage[$path] : array();
				$file['use_count'] = count($file['uses']);
				$files[] = $file;

				if ($file['use_count'] === 0 && !self::reservedFile($path)) {
					$unused[] = $file;
				}

				if ($file['is_image'] && empty($inventory['preview_counts'][$path])) {
					$missingPreviews[] = $file;
				}

				if ($file['size'] >= self::LARGE_FILE_BYTES
					|| ($file['is_image'] && max($file['width'], $file['height']) >= self::LARGE_IMAGE_SIDE)) {
					$large[] = $file;
				}
			}

			usort($unused, array(__CLASS__, 'sortBySize'));
			usort($large, array(__CLASS__, 'sortBySize'));
			usort($missingOriginals, array(__CLASS__, 'sortByPath'));
			usort($invalid, array(__CLASS__, 'sortByPath'));
			usort($files, array(__CLASS__, 'sortByPath'));

			$duplicates = self::duplicates($inventory['files']);
			$duplicateBytes = 0;
			foreach ($duplicates as $group) {
				$duplicateBytes += (int) $group['recoverable_bytes'];
			}

			$report = array(
				'version' => self::REPORT_VERSION,
				'generated_at' => time(),
				'generated_label' => date('d.m.Y H:i:s'),
				'duration' => round(microtime(true) - $started, 2),
				'thresholds' => array(
					'large_file' => Number::formatSize(self::LARGE_FILE_BYTES),
					'large_side' => self::LARGE_IMAGE_SIDE . ' px',
				),
				'summary' => array(
					'files' => count($inventory['files']),
					'images' => $inventory['images'],
					'previews' => $inventory['previews'],
					'references' => count($references),
					'used_files' => count($inventory['files']) - count($unused),
					'unused' => count($unused),
					'duplicate_groups' => count($duplicates),
					'duplicate_bytes' => $duplicateBytes,
					'duplicate_size' => Number::formatSize($duplicateBytes),
					'missing_originals' => count($missingOriginals),
					'missing_previews' => count($missingPreviews),
					'large' => count($large),
					'invalid' => count($invalid),
				),
				'unused' => $unused,
				'duplicates' => $duplicates,
				'missing_originals' => $missingOriginals,
				'missing_previews' => $missingPreviews,
				'large' => $large,
				'invalid' => $invalid,
				'files' => $files,
			);

			MediaUsageIndex::rebuild($files, $report['generated_at']);
			MediaSearchIndex::rebuild($files);

			$directory = dirname(self::reportPath());
			if (!Dir::create($directory)
				|| !File::putAtomic(
					self::reportPath(),
					json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
				)) {
				throw new \RuntimeException('Не удалось сохранить отчёт анализатора медиа');
			}

			return $report;
		}

		protected static function inventory()
		{
			$result = array(
				'files' => array(),
				'preview_counts' => array(),
				'orphan_previews' => array(),
				'images' => 0,
				'previews' => 0,
			);
			$root = rtrim(BASEPATH, '/\\') . '/uploads';
			if (!is_dir($root)) {
				return $result;
			}

			$thumbnailDirectory = ThumbnailUrl::directoryName();
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			$previewRows = array();
			foreach ($iterator as $item) {
				if (!$item->isFile() || $item->isLink()) {
					continue;
				}

				$absolute = str_replace('\\', '/', $item->getPathname());
				$relative = '/uploads/' . ltrim(substr($absolute, strlen(str_replace('\\', '/', $root))), '/');
				if (self::hiddenPath($relative)) {
					continue;
				}

				if (strpos('/' . trim($relative, '/') . '/', '/' . $thumbnailDirectory . '/') !== false) {
					$result['previews']++;
					$previewRows[] = array(
						'path' => $relative,
						'original' => self::previewOriginal($relative, $thumbnailDirectory),
					);
					continue;
				}

				$extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
				$isImage = in_array($extension, self::$imageExtensions, true);
				$width = 0;
				$height = 0;
				if ($isImage) {
					$dimensions = @getimagesize($absolute);
					if (is_array($dimensions)) {
						$width = (int) $dimensions[0];
						$height = (int) $dimensions[1];
					}

					$result['images']++;
				}

				$size = (int) $item->getSize();
				$result['files'][$relative] = array(
					'path' => $relative,
					'name' => basename($relative),
					'extension' => $extension,
					'is_image' => $isImage,
					'size' => $size,
					'size_label' => Number::formatSize($size),
					'width' => $width,
					'height' => $height,
					'dimensions' => $width > 0 && $height > 0 ? $width . ' × ' . $height : '',
					'modified_at' => (int) $item->getMTime(),
					'modified_label' => date('d.m.Y H:i', (int) $item->getMTime()),
				);
			}

			foreach ($previewRows as $preview) {
				if ($preview['original'] !== '' && isset($result['files'][$preview['original']])) {
					if (!isset($result['preview_counts'][$preview['original']])) {
						$result['preview_counts'][$preview['original']] = 0;
					}

					$result['preview_counts'][$preview['original']]++;
					continue;
				}

				$result['orphan_previews'][] = $preview;
			}

			return $result;
		}

		protected static function databaseReferences($includeHistorical = true)
		{
			$sources = array(
				array('table' => ContentTables::table('document_fields'), 'id' => 'Id', 'columns' => array('field_value'), 'label' => 'Поле документа', 'link' => '/documents/{document_id}/edit', 'select' => array('document_id')),
				array('table' => ContentTables::table('document_fields_text'), 'id' => 'Id', 'columns' => array('field_value'), 'label' => 'Текстовое поле документа', 'link' => '/documents/{document_id}/edit', 'select' => array('document_id')),
				array('table' => ContentTables::table('documents'), 'id' => 'Id', 'columns' => array('document_excerpt', 'document_meta_description', 'document_property', 'module_catalog'), 'label' => 'Документ', 'link' => '/documents/{Id}/edit'),
				array('table' => ContentTables::table('rubric_fields'), 'id' => 'Id', 'columns' => array('rubric_field_default', 'rubric_field_settings', 'rubric_field_template', 'rubric_field_template_request', 'rubric_field_description'), 'label' => 'Поле рубрики', 'link' => '/rubrics/fields/{Id}'),
				array('table' => ContentTables::table('rubrics'), 'id' => 'Id', 'columns' => array('rubric_template', 'rubric_start_code', 'rubric_code_start', 'rubric_code_end', 'rubric_teaser_template', 'rubric_header_template', 'rubric_og_template', 'rubric_footer_template', 'rubric_description'), 'label' => 'Рубрика', 'link' => '/rubrics/{Id}'),
				array('table' => ContentTables::table('rubric_templates'), 'id' => 'id', 'columns' => array('template'), 'label' => 'Шаблон рубрики', 'link' => '/rubrics/templates/{id}'),
				array('table' => ContentTables::table('templates'), 'id' => 'Id', 'columns' => array('template_text'), 'label' => 'Шаблон сайта', 'link' => '/templates/{Id}'),
				array('table' => ContentTables::table('sysblocks'), 'id' => 'id', 'columns' => array('sysblock_text', 'sysblock_description'), 'label' => 'Блок', 'link' => '/blocks/{id}'),
				array('table' => ContentTables::table('navigation'), 'id' => 'navigation_id', 'columns' => array('level1', 'level2', 'level3', 'level1_active', 'level2_active', 'level3_active', 'level1_begin', 'level1_end', 'level2_begin', 'level2_end', 'level3_begin', 'level3_end', 'begin', 'end'), 'label' => 'Шаблон навигации', 'link' => '/navigation/{navigation_id}'),
				array('table' => ContentTables::table('navigation_items'), 'id' => 'navigation_item_id', 'columns' => array('alias', 'description', 'image', 'css_style'), 'label' => 'Пункт навигации', 'link' => '/navigation/items/{navigation_item_id}'),
				array('table' => ContentTables::table('request'), 'id' => 'Id', 'columns' => array('request_template_item', 'request_template_main', 'request_description', 'request_result_contract'), 'label' => 'Запрос', 'link' => '/requests/{Id}'),
				array('table' => SystemTables::table('settings'), 'id' => 'param', 'columns' => array('value'), 'label' => 'Настройка', 'link' => '/settings'),
				array('table' => CatalogTables::table('catalog_product_index'), 'id' => 'product_id', 'columns' => array('image', 'images_json', 'card_fields_json'), 'label' => 'Товарный индекс', 'link' => '/catalog/products/{product_id}'),
			);
			if ($includeHistorical) {
				$sources[] = array('table' => ContentTables::table('document_rev'), 'id' => 'Id', 'columns' => array('doc_data'), 'label' => 'Ревизия документа', 'link' => '/documents/{doc_id}/edit', 'select' => array('doc_id'));
			}

			$result = array();
			foreach ($sources as $source) {
				if (!DatabaseSchema::tableExists($source['table'])
					|| !DatabaseSchema::columnExists($source['table'], $source['id'])) {
					continue;
				}

				$columns = array();
				foreach ($source['columns'] as $column) {
					if (DatabaseSchema::columnExists($source['table'], $column)) {
						$columns[] = $column;
					}
				}

				if (!$columns) {
					continue;
				}

				self::scanDatabaseSource($source, $columns, $result);
			}

			self::scanCombinedDocumentFields($result);

			return $result;
		}

		/** Long legacy field values are split between the regular and text tables. */
		protected static function scanCombinedDocumentFields(array &$result)
		{
			$fields = ContentTables::table('document_fields');
			$text = ContentTables::table('document_fields_text');
			if (!DatabaseSchema::tableExists($fields) || !DatabaseSchema::tableExists($text)) {
				return;
			}

			$cursor = 0;
			do {
				$rows = DB::query(
					'SELECT f.Id,f.document_id,f.rubric_field_id,'
						. ' CONCAT(f.field_value,COALESCE(t.field_value,\'\')) field_value'
						. ' FROM ' . $fields . ' f LEFT JOIN ' . $text . ' t'
						. ' ON t.document_id=f.document_id AND t.rubric_field_id=f.rubric_field_id'
						. ' WHERE f.Id>%i AND (LOCATE(\'uploads/\',f.field_value)>0 OR LOCATE(\'uploads/\',t.field_value)>0)'
						. ' ORDER BY f.Id ASC LIMIT 500',
					$cursor
				)->getAll();
				foreach ($rows ?: array() as $row) {
					$cursor = max($cursor, (int) $row['Id']);
					foreach (self::extractPaths($row['field_value']) as $path) {
						$result[] = array(
							'path' => $path,
							'source' => 'Полное значение поля документа',
							'source_id' => (string) $row['Id'],
							'column' => 'field_value',
							'link' => '/documents/' . (int) $row['document_id'] . '/edit',
						);
					}
				}
			} while (count($rows ?: array()) === 500);
		}

		/** Generated WebP/JPEG/PNG twins follow the usage of the referenced source. */
		protected static function propagateCompanionUsage(array &$usage, array $files)
		{
			$stems = array();
			foreach ($files as $path => $file) {
				if (empty($file['is_image'])) { continue; }
				$stem = strtolower(dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME));
				$stems[$stem][] = $path;
			}

			foreach (array_keys($usage) as $path) {
				$stem = strtolower(dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME));
				if (empty($stems[$stem])) { continue; }
				foreach ($stems[$stem] as $companion) {
					if (!isset($usage[$companion])) {
						$usage[$companion] = $usage[$path];
					}
				}
			}
		}

		protected static function scanDatabaseSource(array $source, array $columns, array &$result)
		{
			$cursor = 0;
			$numericId = $source['id'] !== 'param';
			$select = array_unique(array_merge(array($source['id']), isset($source['select']) ? $source['select'] : array(), $columns));
			$quoted = array();
			foreach ($select as $column) {
				if (DatabaseSchema::columnExists($source['table'], $column)) {
					$quoted[] = '`' . $column . '`';
				}
			}

			$conditions = array();
			foreach ($columns as $column) {
				$conditions[] = "LOCATE('/uploads/', `" . $column . '`) > 0';
				$conditions[] = "LOCATE('uploads/', `" . $column . '`) > 0';
			}

			do {
				$sql = 'SELECT ' . implode(',', $quoted) . ' FROM `' . $source['table'] . '` WHERE ';
				if ($numericId) {
					$rows = DB::query(
						$sql . '`' . $source['id'] . '` > %i AND (' . implode(' OR ', $conditions) . ')'
							. ' ORDER BY `' . $source['id'] . '` ASC LIMIT 500',
						$cursor
					)->getAll();
				} else {
					$rows = DB::query(
						$sql . '(' . implode(' OR ', $conditions) . ')'
							. ' ORDER BY `' . $source['id'] . '` ASC LIMIT 5000'
					)->getAll();
				}

				foreach ($rows ?: array() as $row) {
					$row = (array) $row;
					if ($numericId) {
						$cursor = max($cursor, (int) $row[$source['id']]);
					}

					$link = self::replaceTokens($source['link'], $row);
					foreach ($columns as $column) {
						foreach (self::extractPaths(isset($row[$column]) ? $row[$column] : '') as $path) {
							$result[] = array(
								'path' => $path,
								'source' => $source['label'],
								'source_id' => (string) $row[$source['id']],
								'column' => $column,
								'link' => $link,
							);
						}
					}
				}
			} while ($numericId && count($rows ?: array()) === 500);
		}

		protected static function sourceReferences(array &$result)
		{
			foreach (array('templates', 'modules') as $rootName) {
				$root = rtrim(BASEPATH, '/\\') . '/' . $rootName;
				if (!is_dir($root)) {
					continue;
				}

				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveCallbackFilterIterator(
						new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
						function ($item) {
							return !$item->isLink() && $item->getFilename() !== 'vendor';
						}
					),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ($iterator as $item) {
					if (!$item->isFile() || $item->getSize() > self::MAX_SOURCE_FILE_BYTES
						|| !preg_match('/\.(?:php|inc|twig|html?|css|less|js|json)$/i', $item->getFilename())) {
						continue;
					}

					$content = File::getContent($item->getPathname());
					foreach (self::extractPaths($content) as $path) {
						$relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen(BASEPATH))), '/');
						$result[] = array(
							'path' => $path,
							'source' => $rootName === 'templates' ? 'Файл темы' : 'Файл модуля',
							'source_id' => 0,
							'column' => $relative,
							'link' => '',
						);
					}
				}
			}
		}

		protected static function duplicates(array $files)
		{
			$bySize = array();
			foreach ($files as $file) {
				if ($file['size'] <= 0) {
					continue;
				}

				$bySize[$file['size']][] = $file;
			}

			$groups = array();
			foreach ($bySize as $size => $candidates) {
				if (count($candidates) < 2) {
					continue;
				}

				$byHash = array();
				foreach ($candidates as $file) {
					$absolute = rtrim(BASEPATH, '/\\') . $file['path'];
					$hash = is_file($absolute) ? hash_file('sha256', $absolute) : '';
					if ($hash !== '') {
						$byHash[$hash][] = $file;
					}
				}

				foreach ($byHash as $hash => $duplicates) {
					if (count($duplicates) < 2) {
						continue;
					}

					$groups[] = array(
						'hash' => $hash,
						'hash_short' => substr($hash, 0, 12),
						'size' => (int) $size,
						'size_label' => Number::formatSize((int) $size),
						'count' => count($duplicates),
						'recoverable_bytes' => ((int) $size) * (count($duplicates) - 1),
						'recoverable_label' => Number::formatSize(((int) $size) * (count($duplicates) - 1)),
						'files' => $duplicates,
					);
				}
			}

			usort($groups, function ($left, $right) {
				return $right['recoverable_bytes'] - $left['recoverable_bytes'];
			});
			return $groups;
		}

		protected static function extractPaths($value)
		{
			$value = html_entity_decode(str_replace('\/', '/', (string) $value), ENT_QUOTES, 'UTF-8');
			if ($value === '' || stripos($value, 'uploads/') === false) {
				return array();
			}

			preg_match_all(
				'~(?:https?://[^\s"\'<>]+)?(/?uploads/[^\s"\'<>?#\)\]\},;|\\\\]+)~iu',
				$value,
				$matches
			);
			$result = array();
			foreach (isset($matches[1]) ? $matches[1] : array() as $path) {
				$path = '/' . ltrim(rawurldecode((string) $path), '/');
				$result[$path] = $path;
			}

			return array_values($result);
		}

		protected static function normalizeReference($path)
		{
			$path = str_replace('\\', '/', trim((string) $path));
			$path = preg_replace('#/+#', '/', $path);
			if ($path === '' || strpos($path, "\0") !== false || strpos($path, '/../') !== false
				|| strpos($path, '/uploads/') !== 0) {
				return '';
			}

			return $path;
		}

		protected static function referenceMatches($candidate, $path, $isDirectory)
		{
			if ($candidate === $path) { return true; }
			if ($isDirectory) { return strpos($candidate, rtrim($path, '/') . '/') === 0; }

			$candidateExtension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
			$pathExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			if (!in_array($candidateExtension, self::$imageExtensions, true)
				|| !in_array($pathExtension, self::$imageExtensions, true)) {
				return false;
			}

			$candidateStem = strtolower(dirname($candidate) . '/' . pathinfo($candidate, PATHINFO_FILENAME));
			$pathStem = strtolower(dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME));
			return $candidateStem === $pathStem;
		}

		protected static function previewOriginal($path, $directory)
		{
			$path = str_replace('/' . trim($directory, '/') . '/', '/', (string) $path);
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			$filename = pathinfo($path, PATHINFO_FILENAME);
			$filename = preg_replace('/-[rcfts]\d+x\d+r?$/i', '', $filename);
			return dirname($path) . '/' . $filename . ($extension !== '' ? '.' . $extension : '');
		}

		protected static function hiddenPath($path)
		{
			$basename = strtolower(basename((string) $path));
			return $basename === 'index.php' || $basename === '.htaccess'
				|| strpos((string) $path, '/.drafts/') !== false
				|| strpos((string) $path, '/.quarantine/') !== false
				|| strpos((string) $path, '/.temp/') !== false;
		}

		protected static function reservedFile($path)
		{
			return preg_match('#^/uploads/(?:images/)?(?:noimage|placeholder)\.[a-z0-9]+$#i', (string) $path) === 1;
		}

		protected static function replaceTokens($template, array $row)
		{
			foreach ($row as $key => $value) {
				$template = str_replace('{' . $key . '}', rawurlencode((string) $value), $template);
			}

			return $template;
		}

		protected static function reportPath()
		{
			return rtrim(BASEPATH, '/\\') . '/storage/reports/media-audit.json';
		}

		protected static function sortBySize($left, $right)
		{
			if ((int) $left['size'] === (int) $right['size']) {
				return strcmp((string) $left['path'], (string) $right['path']);
			}

			return (int) $left['size'] < (int) $right['size'] ? 1 : -1;
		}

		protected static function sortByPath($left, $right)
		{
			return strcmp((string) $left['path'], (string) $right['path']);
		}
	}
