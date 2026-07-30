<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Events/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Events;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AuditLog;
	use App\Common\ReferrerLog;
	use App\Helpers\Json;
	use DB;

	class Model
	{
		public static function sources()
		{
			return array(
				'audit' => array(
					'code' => 'audit',
					'label' => 'Аудит',
					'description' => 'История административных действий с пользователями, объектами и техническими деталями.',
					'icon' => 'ti ti-shield-check',
					'tile_bg' => 'var(--blue-100)',
					'tile_fg' => 'var(--blue-600)',
					'clearable' => true,
				),
				'legacy' => array(
					'code' => 'legacy',
					'label' => 'Журнал приложения',
					'description' => 'Сообщения runtime, которые код сайта записывает в системный журнал.',
					'icon' => 'ti ti-history',
					'tile_bg' => 'var(--green-100)',
					'tile_fg' => 'var(--green-600)',
					'clearable' => true,
				),
				'404' => array(
					'code' => '404',
					'label' => 'Ошибки 404',
					'description' => 'Запросы к несуществующим страницам.',
					'icon' => 'ti ti-alert-circle',
					'tile_bg' => 'var(--amber-100)',
					'tile_fg' => 'var(--amber-600)',
					'clearable' => true,
				),
				'sql' => array(
					'code' => 'sql',
					'label' => 'SQL ошибки',
					'description' => 'Ошибки SQL-запросов сайта.',
					'icon' => 'ti ti-database-exclamation',
					'tile_bg' => 'var(--red-100)',
					'tile_fg' => 'var(--red-600)',
					'clearable' => true,
				),
				'referrers' => array(
					'code' => 'referrers',
					'label' => 'Переходы',
					'description' => 'Внешние источники, рекламные метки и страницы входа.',
					'icon' => 'ti ti-route',
					'tile_bg' => 'var(--cyan-100)',
					'tile_fg' => 'var(--cyan-700)',
					'clearable' => true,
				),
			);
		}

		public static function defaultSource()
		{
			return 'audit';
		}

		public static function states()
		{
			return array(
				'success' => array('label' => 'Успех', 'badge' => 'badge-green', 'icon' => 'ti ti-circle-check', 'class' => 'state-success'),
				'change' => array('label' => 'Изменение', 'badge' => 'badge-blue', 'icon' => 'ti ti-pencil', 'class' => 'state-change'),
				'warning' => array('label' => 'Внимание', 'badge' => 'badge-amber', 'icon' => 'ti ti-alert-triangle', 'class' => 'state-warning'),
				'error' => array('label' => 'Ошибка', 'badge' => 'badge-red', 'icon' => 'ti ti-alert-circle', 'class' => 'state-error'),
				'danger' => array('label' => 'Опасное', 'badge' => 'badge-red', 'icon' => 'ti ti-trash', 'class' => 'state-danger'),
				'info' => array('label' => 'Инфо', 'badge' => 'badge-gray', 'icon' => 'ti ti-info-circle', 'class' => 'state-info'),
			);
		}

		public static function source($code)
		{
			$sources = self::sources();
			$code = (string) $code;
			return isset($sources[$code]) ? $sources[$code] : $sources[self::defaultSource()];
		}

		public static function summaries()
		{
			$out = array();
			foreach (self::sources() as $code => $source) {
				$source['count'] = self::count($code);
				$source['size'] = self::sourceSize($code);
				$source['exists'] = in_array($code, array('audit', 'referrers'), true) ? true : is_file(self::path($code));
				$out[$code] = $source;
			}

			return $out;
		}

		public static function rows($source, $q = '', $limit = 300)
		{
			$source = self::source($source);
			$code = $source['code'];
			$limit = max(1, min(1000, (int) $limit));
			$q = trim((string) $q);

			if ($code === 'audit') {
				$rows = self::auditRows($limit);
			} elseif ($code === 'referrers') {
				$rows = self::referrerRows($limit, $q);
			} else {
				$rows = self::legacyRows($code, $limit);
			}

			if ($q !== '') {
				$rows = array_values(array_filter($rows, function ($row) use ($q) {
					$haystack = implode(' ', array(
						isset($row['message']) ? $row['message'] : '',
						isset($row['actor']) ? $row['actor'] : '',
						isset($row['ip']) ? $row['ip'] : '',
						isset($row['url']) ? $row['url'] : '',
						isset($row['details_text']) ? $row['details_text'] : '',
					));
					return stripos($haystack, $q) !== false;
				}));
			}

			return $rows;
		}

		public static function clear($source, $actorId = 0)
		{
			$source = self::source($source);
			if (empty($source['clearable'])) {
				return false;
			}

			if ($source['code'] === 'audit') {
				$count = (int) DB::query('SELECT COUNT(*) FROM ' . AuditLog::table())->getValue();
				DB::query('DELETE FROM ' . AuditLog::table());
				AuditLog::record('events.audit_cleared', array(
					'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
					'target_type' => 'audit_log',
					'meta' => array('deleted_rows' => $count, 'source' => $source['code']),
				));
				return true;
			}

			if ($source['code'] === 'referrers') {
				return ReferrerLog::clear();
			}

			$path = self::path($source['code']);
			if (is_file($path)) {
				unlink($path);
			}

			return true;
		}

		public static function csv($source, $q = '', $limit = 1000)
		{
			if (self::source($source)['code'] === 'referrers') {
				return self::referrerCsv($q, $limit);
			}

			$rows = self::rows($source, $q, $limit);
			$fh = fopen('php://temp', 'r+');
			fputcsv($fh, array('time', 'state', 'ip', 'actor', 'action', 'url', 'details'));
			foreach ($rows as $row) {
				fputcsv($fh, array(
					$row['time_display'],
					$row['state_label'],
					$row['ip'],
					$row['actor'],
					$row['message'],
					$row['url'],
					$row['details_text'],
				));
			}

			rewind($fh);
			$csv = stream_get_contents($fh);
			fclose($fh);
			return $csv;
		}

		protected static function count($source)
		{
			if ($source === 'audit') {
				return (int) DB::query('SELECT COUNT(*) FROM ' . AuditLog::table())->getValue();
			}

			if ($source === 'referrers') {
				return ReferrerLog::count();
			}

			return count(self::readCsv($source, 100000));
		}

		protected static function sourceSize($source)
		{
			if (in_array($source, array('audit', 'referrers'), true)) {
				return 'БД';
			}

			$path = self::path($source);
			return is_file($path) ? self::formatBytes(filesize($path)) : '0 Б';
		}

		protected static function auditRows($limit)
		{
			$rows = DB::query(
				'SELECT * FROM ' . AuditLog::table() . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit
			)->getAll() ?: array();

			$out = array();
			foreach ($rows as $row) {
				$meta = self::decodeMeta(isset($row['meta']) ? $row['meta'] : '');
				$state = self::auditState((string) $row['action']);
				$timestamp = strtotime((string) $row['created_at']);
				$out[] = array(
					'id' => (int) $row['id'],
					'source' => 'audit',
					'level' => $state,
					'time' => $timestamp,
					'time_display' => self::formatTime($timestamp),
					'date_group' => self::dateGroup($timestamp),
					'ip' => (string) $row['ip'],
					'actor' => (string) $row['actor_name'] !== ''
						? (string) $row['actor_name']
						: '#' . (int) $row['actor_id'],
					'message' => AuditLog::actionLabel($row['action']),
					'url' => trim((string) $row['target_type'] . ($row['target_id'] ? ' #' . $row['target_id'] : '')),
					'details' => $meta,
					'details_text' => self::detailsText($meta),
				) + self::state($state);
			}

			return $out;
		}

		protected static function dateGroup($timestamp)
		{
			$timestamp = (int) $timestamp;
			if ($timestamp <= 0) {
				return 'Без даты';
			}

			if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
				return 'Сегодня';
			}

			if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
				return 'Вчера';
			}

			return date('d.m.Y', $timestamp);
		}

		protected static function legacyRows($source, $limit)
		{
			$rows = self::readCsv($source, $limit);
			$out = array();
			foreach ($rows as $i => $event) {
				if ($source === 'legacy') {
					$out[] = self::legacyEventRow($event, $i);
				} elseif ($source === '404') {
					$out[] = self::notFoundRow($event, $i);
				} elseif ($source === 'sql') {
					$out[] = self::sqlRow($event, $i);
				}
			}

			return array_reverse($out);
		}

		protected static function legacyEventRow(array $event, $i)
		{
			$state = self::legacyState(self::at($event, 6));
			return array(
				'id' => $i,
				'source' => 'legacy',
				'level' => $state,
				'time' => (int) self::at($event, 0),
				'time_display' => self::formatTime(self::at($event, 0)),
				'ip' => (string) self::at($event, 1),
				'actor' => (string) self::at($event, 4, 'Anonymous'),
				'message' => (string) self::at($event, 5),
				'url' => (string) self::at($event, 2),
				'details' => array('type' => self::at($event, 6), 'rubric' => self::at($event, 7)),
				'details_text' => 'type: ' . self::at($event, 6) . ', rubric: ' . self::at($event, 7),
			) + self::state($state);
		}

		protected static function notFoundRow(array $event, $i)
		{
			$details = array(
				'user_agent' => self::at($event, 3),
				'referer' => self::at($event, 4),
				'query' => self::at($event, 2),
			);
			return array(
				'id' => $i,
				'source' => '404',
				'level' => 'warning',
				'time' => (int) self::at($event, 0),
				'time_display' => self::formatTime(self::at($event, 0)),
				'ip' => (string) self::at($event, 1),
				'actor' => 'Гость',
				'message' => '404: ' . (string) self::at($event, 5),
				'url' => (string) self::at($event, 5),
				'details' => $details,
				'details_text' => self::detailsText($details),
			) + self::state('warning');
		}

		protected static function sqlRow(array $event, $i)
		{
			$raw = base64_decode((string) self::at($event, 5), true);
			$data = $raw !== false ? @unserialize($raw, array('allowed_classes' => false)) : array();
			if (!is_array($data)) {
				$data = array('raw' => (string) self::at($event, 5));
			}

			return array(
				'id' => $i,
				'source' => 'sql',
				'level' => 'error',
				'time' => (int) self::at($event, 0),
				'time_display' => self::formatTime(self::at($event, 0)),
				'ip' => (string) self::at($event, 1),
				'actor' => (string) self::at($event, 4, 'Anonymous'),
				'message' => isset($data['sql_error']) ? (string) $data['sql_error'] : 'SQL ошибка',
				'url' => (string) self::at($event, 2),
				'details' => $data,
				'details_text' => self::detailsText($data),
			) + self::state('error');
		}

		protected static function referrerRows($limit, $query)
		{
			$types = array(
				'campaign' => array('label' => 'Кампания', 'badge' => 'badge-blue', 'state' => 'change'),
				'search' => array('label' => 'Поиск', 'badge' => 'badge-green', 'state' => 'success'),
				'social' => array('label' => 'Соцсеть', 'badge' => 'badge-cyan', 'state' => 'info'),
				'referral' => array('label' => 'Ссылка', 'badge' => 'badge-amber', 'state' => 'warning'),
			);
			$out = array();
			foreach (ReferrerLog::rows($limit, $query) as $row) {
				$type = isset($types[$row['source_type']]) ? $types[$row['source_type']] : $types['referral'];
				$tracking = Json::toArray(isset($row['tracking_json']) ? $row['tracking_json'] : '', array());
				$details = array(
					'referer' => isset($row['referer_url']) ? $row['referer_url'] : '',
					'first_seen' => self::formatTime(isset($row['first_seen_at']) ? $row['first_seen_at'] : 0),
					'last_seen' => self::formatTime(isset($row['last_seen_at']) ? $row['last_seen_at'] : 0),
					'tracking' => $tracking,
					'user_agent' => isset($row['user_agent']) ? $row['user_agent'] : '',
				);
				$out[] = array(
					'id' => (int) $row['id'],
					'source' => 'referrers',
					'level' => $type['state'],
					'time' => (int) $row['last_seen_at'],
					'time_display' => self::formatTime($row['last_seen_at']),
					'ip' => '',
					'actor' => (string) $row['source_name'],
					'message' => (string) $row['landing_path'],
					'url' => (string) $row['referer_url'],
					'details' => $details,
					'details_text' => self::detailsText($details),
					'source_name' => (string) $row['source_name'],
					'source_type_label' => $type['label'],
					'source_type_badge' => $type['badge'],
					'hits' => (int) $row['hits'],
				) + self::state($type['state']);
			}

			return $out;
		}

		protected static function referrerCsv($query, $limit)
		{
			$rows = self::referrerRows($limit, $query);
			$fh = fopen('php://temp', 'r+');
			fputcsv($fh, array('last_seen', 'type', 'source', 'landing_page', 'referer', 'hits', 'details'));
			foreach ($rows as $row) {
				fputcsv($fh, array(
					$row['time_display'],
					$row['source_type_label'],
					$row['source_name'],
					$row['message'],
					$row['url'],
					$row['hits'],
					$row['details_text'],
				));
			}

			rewind($fh);
			$csv = stream_get_contents($fh);
			fclose($fh);
			return $csv;
		}

		protected static function state($state)
		{
			$states = self::states();
			$state = isset($states[$state]) ? $state : 'info';
			return array(
				'state' => $state,
				'state_label' => $states[$state]['label'],
				'state_badge' => $states[$state]['badge'],
				'state_icon' => $states[$state]['icon'],
				'state_class' => $states[$state]['class'],
				'badge' => $states[$state]['badge'],
			);
		}

		protected static function auditState($action)
		{
			$action = strtolower((string) $action);

			if (preg_match('/(error|failed|failure|exception|denied|blocked)/', $action)) {
				return 'error';
			}

			if (preg_match('/(deleted|purged|clear|cleared|reset|revoked|disabled)/', $action)) {
				return 'danger';
			}

			if (preg_match('/(warning|expired|timeout|missing|invalid)/', $action)) {
				return 'warning';
			}

			if (preg_match('/(created|restored|enabled|login|logged)/', $action)) {
				return 'success';
			}

			if (preg_match('/(updated|changed|copied|saved|settings|access)/', $action)) {
				return 'change';
			}

			return 'info';
		}

		protected static function legacyState($type)
		{
			$type = (int) $type;
			if ($type < 0) {
				return 'error';
			}

			if ($type > 1) {
				return 'warning';
			}

			if ($type === 1) {
				return 'change';
			}

			return 'success';
		}

		protected static function readCsv($source, $limit)
		{
			$path = self::path($source);
			if (!is_file($path)) {
				return array();
			}

			$rows = array();
			if (($fh = fopen($path, 'rb')) !== false) {
				while (($row = fgetcsv($fh, 0, ',')) !== false) {
					if (!isset($row[0]) || $row[0] === '') {
						continue;
					}

					$rows[] = $row;
					if (count($rows) > $limit) {
						array_shift($rows);
					}
				}

				fclose($fh);
			}

			return $rows;
		}

		protected static function path($source)
		{
			$map = array(
				'legacy' => BASEPATH . '/tmp/logs/log.csv',
				'404' => BASEPATH . '/tmp/logs/404.csv',
				'sql' => BASEPATH . '/tmp/logs/sql.csv',
			);
			return isset($map[$source]) ? $map[$source] : '';
		}

		protected static function at(array $row, $index, $default = '')
		{
			return isset($row[$index]) ? $row[$index] : $default;
		}

		protected static function decodeMeta($json)
		{
			return Json::toArray($json, array());
		}

		protected static function detailsText($data)
		{
			if (!is_array($data) || empty($data)) {
				return '';
			}

			return Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function formatTime($value)
		{
			$ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
			if ($ts <= 0) {
				return '';
			}

			return date('d.m.Y H:i:s', $ts);
		}

		protected static function formatBytes($bytes)
		{
			$bytes = (int) $bytes;
			if ($bytes < 1024) return $bytes . ' Б';
			if ($bytes < 1048576) return round($bytes / 1024, 1) . ' КБ';
			return round($bytes / 1048576, 1) . ' МБ';
		}
	}
