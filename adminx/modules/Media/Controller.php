<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Content\Documents\DocumentMediaDraft;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Media/assets/media.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Media/assets/media.js', 50);

			$dir = Model::normalize(Request::getStr('dir', Model::ROOT), Model::ROOT);
			$q = trim(Request::getStr('q', ''));
			$type = Request::getStr('type', '');
			$type = in_array($type, array('image', 'file'), true) ? $type : '';
			$view = Request::getStr('view', 'grid') === 'table' ? 'table' : 'grid';
			$page = max(1, Request::getInt('page', 1));
			$perPage = 48;

			$listing = Model::listing($dir, $q, $type, $page, $perPage);

			return $this->render('@media/index.twig', array(
				'title' => 'Медиа',
				'dir' => $listing['dir'],
				'breadcrumbs' => Model::breadcrumbs($listing['dir']),
				'parent_dir' => Model::parentDir($listing['dir']),
				'folders' => $listing['folders'],
				'files' => $listing['files'],
				'stats' => $listing['stats'],
				'filters' => array('q' => $q, 'type' => $type, 'view' => $view),
				'page' => $listing['page'],
				'pages' => $listing['pages'],
				'total' => $listing['total'],
				'per_page' => $perPage,
				'can_manage' => Permission::check('manage_media'),
			));
		}

		public function file(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Media/assets/media.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Media/assets/media.js', 50);

			$file = Model::file(Request::getStr('path', ''));
			if (!$file) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Файл не найден'), 404);
			}

			return $this->render('@media/file.twig', array(
				'title' => 'Медиа: ' . $file['name'],
				'file' => $file,
				'parent_dir' => Model::parentDir($file['path']),
				'can_manage' => Permission::check('manage_media'),
				'supports_webp' => Model::supportsWebp(),
			));
		}

		public function picker(array $params = array())
		{
			$dir = Model::normalize(Request::getStr('dir', Model::ROOT), Model::ROOT);
			$q = trim(Request::getStr('q', ''));
			$type = Request::getStr('type', 'image');
			$type = in_array($type, array('image', 'file', 'all'), true) ? $type : 'image';
			$listingType = $type === 'all' ? '' : $type;
			$page = max(1, Request::getInt('page', 1));
			$perPage = Request::getInt('per_page', 36);
			$perPage = max(12, min(60, $perPage));

			$listing = Model::listing($dir, $q, $listingType, $page, $perPage);
			$folderListing = Model::listing($dir, $q, '', 1, 1);

			return $this->success('', array(
				'data' => array(
					'dir' => $listing['dir'],
					'type' => $type,
					'parent_dir' => Model::parentDir($listing['dir']),
					'breadcrumbs' => Model::breadcrumbs($listing['dir']),
					'folders' => $this->pickerFolders($folderListing['folders']),
					'files' => $this->pickerFiles($listing['files']),
					'page' => $listing['page'],
					'pages' => $listing['pages'],
					'total' => $listing['total'],
					'per_page' => $perPage,
				),
			));
		}

		public function folderFiles(array $params = array())
		{
			$dir = Model::normalize(Request::getStr('dir', Model::ROOT), Model::ROOT);
			$type = Request::getStr('type', 'image');
			$type = in_array($type, array('image', 'file', 'all'), true) ? $type : 'image';
			$limit = Request::getInt('limit', 500);
			$limit = max(1, min(1000, $limit));

			try {
				$files = Model::folderFiles($dir, $type === 'all' ? '' : $type, $limit);
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('', array(
				'data' => array(
					'dir' => $dir,
					'type' => $type,
					'files' => $this->pickerFiles($files),
					'count' => count($files),
				),
			));
		}

		public function upload(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			if (empty($_FILES['files']) || empty($_FILES['files']['name'])) {
				return $this->error('Выберите файлы для загрузки');
			}

			try {
				$draftToken = Request::postStr('media_draft_token', '');
				$dir = $draftToken !== ''
					? DocumentMediaDraft::uploadDirectory(
						$draftToken,
						Request::postInt('field_id', 0),
						Auth::id(),
						Request::postInt('document_id', 0)
					)
					: Request::postStr('dir', Model::ROOT);
				$result = Model::upload($dir, $_FILES['files']);
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			if (empty($result['uploaded'])) {
				return $this->error('Файлы не загружены', array('files' => implode(', ', $result['errors'])));
			}

			$message = 'Загружено файлов: ' . count($result['uploaded']);
			if (!empty($result['errors'])) {
				$message .= '. Пропущено: ' . count($result['errors']);
			}

			return $this->success($message, array(
				'data' => array('files' => $result['uploaded']),
				'redirect' => $this->base() . '/media?dir=' . rawurlencode($result['dir']),
			));
		}

		public function createFolder(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$path = Model::createFolder(Request::postStr('dir', Model::ROOT), Request::postStr('name', ''));
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Папка создана', array(
				'redirect' => $this->base() . '/media?dir=' . rawurlencode($path),
			));
		}

		public function rename(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$target = Model::rename(Request::postStr('path', ''), Request::postStr('name', ''));
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$redirect = is_file(Model::abs($target))
				? $this->base() . '/media/file?path=' . rawurlencode($target)
				: $this->base() . '/media?dir=' . rawurlencode($target);

			return $this->success('Переименовано', array('redirect' => $redirect));
		}

		public function delete(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$path = Request::postStr('path', '');
			$parent = Model::parentDir($path);
			try {
				Model::delete($path);
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Удалено', array(
				'redirect' => $this->base() . '/media?dir=' . rawurlencode($parent ?: Model::ROOT),
			));
		}

		public function clearThumbnails(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$dir = Request::postStr('path', Model::ROOT);
			try {
				$result = Model::clearThumbnails($dir);
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$message = $result['files'] > 0
				? 'Удалено превью: ' . $result['files']
				: 'В этой папке превью не найдены';

			return $this->success($message, array(
				'data' => $result,
				'redirect' => $this->base() . '/media?dir=' . rawurlencode($dir),
			));
		}

		public function transform(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$path = Request::postStr('path', '');
			try {
				$out = Model::transform($path, array(
					'mode' => Request::postStr('mode', 'fit'),
					'width' => Request::postInt('width', 320),
					'height' => Request::postInt('height', 240),
					'format' => Request::postStr('format', 'original'),
					'quality' => Request::postInt('quality', 82),
					'crop_enabled' => Request::postStr('crop_enabled', ''),
					'crop_x' => Request::postInt('crop_x', 0),
					'crop_y' => Request::postInt('crop_y', 0),
					'crop_w' => Request::postInt('crop_w', 0),
					'crop_h' => Request::postInt('crop_h', 0),
				));
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Изображение создано', array(
				'data' => array('path' => $out),
				'redirect' => $this->base() . '/media?dir=' . rawurlencode(Model::parentDir($out)),
			));
		}

		public function preview(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$path = Request::postStr('path', '');
			try {
				$preview = Model::previewTransform($path, array(
					'mode' => Request::postStr('mode', 'fit'),
					'width' => Request::postInt('width', 320),
					'height' => Request::postInt('height', 240),
					'format' => Request::postStr('format', 'original'),
					'quality' => Request::postInt('quality', 82),
					'crop_enabled' => Request::postStr('crop_enabled', ''),
					'crop_x' => Request::postInt('crop_x', 0),
					'crop_y' => Request::postInt('crop_y', 0),
					'crop_w' => Request::postInt('crop_w', 0),
					'crop_h' => Request::postInt('crop_h', 0),
				));
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$meta = trim(($preview['width'] && $preview['height'] ? $preview['width'] . 'x' . $preview['height'] : '')
				. ' · ' . strtoupper($preview['format'])
				. ($preview['size_label'] !== '' ? ' · ' . $preview['size_label'] : ''), ' ·');
			return $this->success('', array('data' => array(
				'path' => $preview['path'],
				'url' => $preview['path'],
				'meta' => $meta,
			)));
		}

		public function previewClear(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			Model::deletePreview(Request::postStr('path', ''));
			return $this->success('');
		}

		public function convertWebp(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$out = Model::convertWebp(Request::postStr('path', ''), Request::postInt('quality', 82));
			} catch (\RuntimeException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('WebP-копия создана', array(
				'data' => array('path' => $out),
				'redirect' => $this->base() . '/media/file?path=' . rawurlencode($out),
			));
		}

		protected function guard()
		{
			return $this->guardPermission('manage_media');
		}

		protected function pickerFolders(array $folders)
		{
			$out = array();
			foreach ($folders as $folder) {
				$out[] = array(
					'name' => isset($folder['name']) ? $folder['name'] : '',
					'path' => isset($folder['path']) ? $folder['path'] : '',
					'count' => isset($folder['count']) ? (int) $folder['count'] : 0,
				);
			}

			return $out;
		}

		protected function pickerFiles(array $files)
		{
			$out = array();
			foreach ($files as $file) {
				$out[] = array(
					'name' => isset($file['name']) ? $file['name'] : '',
					'path' => isset($file['path']) ? $file['path'] : '',
					'url' => isset($file['url']) ? $file['url'] : '',
					'thumb_url' => isset($file['thumb_url']) ? $file['thumb_url'] : '',
					'preview_url' => isset($file['preview_url']) ? $file['preview_url'] : '',
					'extension' => isset($file['extension']) ? $file['extension'] : '',
					'size_label' => isset($file['size_label']) ? $file['size_label'] : '',
					'width' => isset($file['width']) ? (int) $file['width'] : 0,
					'height' => isset($file['height']) ? (int) $file['height'] : 0,
					'webp_url' => !empty($file['has_webp']) && !empty($file['webp_path']) ? $file['webp_path'] : '',
				);
			}

			return $out;
		}
	}
