<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentViewTracker.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session;
	use App\Content\ContentTables;
	use DB;

	/** Persists public document counters once per visitor session. */
	class DocumentViewTracker
	{
		public function trackPrint($documentId)
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0) {
				return;
			}

			DB::query(
				'UPDATE %b SET document_count_print = document_count_print + 1 WHERE Id = %i',
				ContentTables::table('documents'),
				$documentId
			);
		}

		public function trackView($documentId, $timestamp = null)
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0) {
				return;
			}

			$timestamp = $timestamp === null ? time() : (int) $timestamp;
			$this->trackTotalOnce($documentId, $timestamp);
			$this->trackDailyOnce($documentId, $timestamp);
		}

		protected function trackTotalOnce($documentId, $timestamp)
		{
			$views = Session::get('doc_view');
			$views = is_array($views) ? $views : array();
			if (isset($views[$documentId])) {
				return;
			}

			DB::query(
				'UPDATE %b SET document_count_view = document_count_view + 1 WHERE Id = %i',
				ContentTables::table('documents'),
				$documentId
			);
			$views[$documentId] = $timestamp;
			Session::set('doc_view', $views);
		}

		protected function trackDailyOnce($documentId, $timestamp)
		{
			$dayId = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp), (int) date('Y', $timestamp));
			$daily = Session::get('doc_view_daily');
			$daily = is_array($daily) ? $daily : array();
			$legacyKey = 'doc_view_dayly[' . $dayId . '][' . $documentId . ']';

			if (isset($daily[$dayId][$documentId]) || Session::check($legacyKey)) {
				return;
			}

			DB::query(
				'UPDATE %b SET count = count + 1 WHERE document_id = %i AND day_id = %i',
				ContentTables::table('view_count'),
				$documentId,
				$dayId
			);

			if (DB::affectedRows() === 0) {
				DB::query(
					'INSERT INTO %b (document_id, day_id, count) VALUES (%i, %i, 1)',
					ContentTables::table('view_count'),
					$documentId,
					$dayId
				);
			}

			if (!isset($daily[$dayId]) || !is_array($daily[$dayId])) {
				$daily[$dayId] = array();
			}

			$daily[$dayId][$documentId] = $timestamp;
			Session::set('doc_view_daily', $daily);
		}
	}
