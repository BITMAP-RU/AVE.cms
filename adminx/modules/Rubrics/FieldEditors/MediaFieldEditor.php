<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/MediaFieldEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	class MediaFieldEditor extends AbstractFieldEditor
	{
		public function parseValue($type, $value)
		{
			$type = (string) $type;
			$value = (string) $value;

			if (in_array($type, array('image_single', 'download'), true)) {
				$json = $this->decodeJsonValue($value);
				if (is_array($json)) {
					return array(
						'raw' => $value,
						'url' => isset($json['url']) ? (string) $json['url'] : (isset($json['path']) ? (string) $json['path'] : ''),
						'description' => isset($json['description']) ? (string) $json['description'] : (isset($json['descr']) ? (string) $json['descr'] : ''),
					);
				}

				$parts = explode('|', $value);
				return array(
					'raw' => $value,
					'url' => isset($parts[0]) ? (string) $parts[0] : '',
					'description' => isset($parts[1]) ? implode('|', array_slice($parts, 1)) : '',
				);
			}

			if ($type === 'youtube') {
				$parts = explode('|', $value);
				return array(
					'raw' => $value,
					'url' => isset($parts[0]) ? (string) $parts[0] : '',
					'width' => isset($parts[1]) ? (string) $parts[1] : '',
					'height' => isset($parts[2]) ? (string) $parts[2] : '',
					'fullscreen' => isset($parts[3]) ? (string) $parts[3] : '',
					'source' => isset($parts[4]) ? (string) $parts[4] : '',
				);
			}

			if ($type === 'image_multi') {
				$items = $this->decodeListValue($value);
				$out = array();
				foreach ($items as $item) {
					if (is_array($item)) {
						$url = isset($item['url']) ? (string) $item['url'] : (isset($item['path']) ? (string) $item['path'] : '');
						if ($url === '') {
							continue;
						}

						$out[] = array(
							'url' => $url,
							'description' => isset($item['description']) ? (string) $item['description'] : (isset($item['descr']) ? (string) $item['descr'] : ''),
						);
						continue;
					}

					$parts = explode('|', (string) $item);
					$url = isset($parts[0]) ? trim((string) $parts[0]) : '';
					if ($url !== '') {
						$out[] = array(
							'url' => $url,
							'description' => isset($parts[1]) ? implode('|', array_slice($parts, 1)) : '',
						);
					}
				}

				return array('raw' => $value, 'items' => $out);
			}

			if ($type === 'image_mega') {
				$items = $this->decodeListValue($value);
				$out = array();
				foreach ($items as $item) {
					if (is_array($item)) {
						$url = isset($item['url']) ? (string) $item['url'] : (isset($item['path']) ? (string) $item['path'] : '');
						if ($url === '') {
							continue;
						}

						$out[] = array(
							'url' => $url,
							'title' => isset($item['title']) ? (string) $item['title'] : (isset($item['name']) ? (string) $item['name'] : ''),
							'description' => isset($item['description']) ? (string) $item['description'] : (isset($item['descr']) ? (string) $item['descr'] : ''),
							'link' => isset($item['link']) ? (string) $item['link'] : '',
						);
						continue;
					}

					$parts = explode('|', (string) $item);
					$url = isset($parts[0]) ? trim((string) $parts[0]) : '';
					if ($url !== '') {
						$out[] = array(
							'url' => $url,
							'title' => isset($parts[1]) ? (string) $parts[1] : '',
							'description' => isset($parts[2]) ? (string) $parts[2] : '',
							'link' => isset($parts[3]) ? (string) $parts[3] : '',
						);
					}
				}

				return array('raw' => $value, 'items' => $out);
			}

			if ($type === 'doc_files') {
				$items = $this->decodeListValue($value);
				$out = array();
				foreach ($items as $item) {
					if (is_array($item)) {
						$url = isset($item['url']) ? (string) $item['url'] : (isset($item['path']) ? (string) $item['path'] : '');
						if ($url === '') {
							continue;
						}

						$out[] = array(
							'name' => isset($item['name']) ? (string) $item['name'] : '',
							'description' => isset($item['descr']) ? (string) $item['descr'] : (isset($item['description']) ? (string) $item['description'] : ''),
							'url' => $url,
						);
						continue;
					}

					$parts = explode('|', (string) $item);
					$url = count($parts) > 2 ? (string) $parts[2] : (string) $item;
					$url = trim($url);
					if ($url !== '') {
						$out[] = array(
							'name' => isset($parts[0]) && count($parts) > 2 ? (string) $parts[0] : basename($url),
							'description' => isset($parts[1]) && count($parts) > 2 ? (string) $parts[1] : '',
							'url' => $url,
						);
					}
				}

				return array('raw' => $value, 'items' => $out);
			}

			return parent::parseValue($type, $value);
		}

		public function serializeValue($type, $value)
		{
			$type = (string) $type;
			if (!is_array($value)) {
				return (string) $value;
			}

			if ($type === 'image_single') {
				$url = trim(isset($value['url']) ? (string) $value['url'] : '');
				$description = trim(isset($value['description']) ? (string) $value['description'] : '');
				return $url === '' ? '' : htmlspecialchars($url . '|' . $description, ENT_QUOTES);
			}

			if ($type === 'download') {
				$url = trim(isset($value['url']) ? (string) $value['url'] : '');
				$description = trim(isset($value['description']) ? (string) $value['description'] : '');
				return $url === '' ? '' : htmlspecialchars($url . ($description !== '' ? '|' . $description : ''), ENT_QUOTES);
			}

			if ($type === 'youtube') {
				$url = trim(isset($value['url']) ? (string) $value['url'] : '');
				if ($url === '') {
					return '';
				}

				return htmlspecialchars(implode('|', array(
					$url,
					isset($value['width']) ? (string) $value['width'] : '',
					isset($value['height']) ? (string) $value['height'] : '',
					isset($value['fullscreen']) ? (string) $value['fullscreen'] : '',
					isset($value['source']) ? (string) $value['source'] : '',
				)), ENT_QUOTES);
			}

			if ($type === 'image_multi') {
				$items = isset($value['items']) && is_array($value['items']) ? $value['items'] : array();
				$out = array();
				foreach ($items as $item) {
					if (!is_array($item)) {
						continue;
					}

					$url = trim(isset($item['url']) ? (string) $item['url'] : '');
					$description = trim(isset($item['description']) ? (string) $item['description'] : '');
					if ($url !== '') {
						$out[] = $url . ($description !== '' ? '|' . $description : '');
					}
				}

				return !empty($out) ? serialize($out) : '';
			}

			if ($type === 'image_mega') {
				$items = isset($value['items']) && is_array($value['items']) ? $value['items'] : array();
				$out = array();
				foreach ($items as $item) {
					if (!is_array($item)) {
						continue;
					}

					$url = trim(isset($item['url']) ? (string) $item['url'] : '');
					if ($url === '') {
						continue;
					}

					$out[] = $url
						. '|' . stripslashes(htmlspecialchars(isset($item['title']) ? (string) $item['title'] : '', ENT_QUOTES))
						. '|' . stripslashes(htmlspecialchars(isset($item['description']) ? (string) $item['description'] : '', ENT_QUOTES))
						. '|' . ltrim(isset($item['link']) ? (string) $item['link'] : '', '/');
				}

				return !empty($out) ? serialize($out) : '';
			}

			if ($type === 'doc_files') {
				$items = isset($value['items']) && is_array($value['items']) ? $value['items'] : array();
				$out = array();
				foreach ($items as $item) {
					if (!is_array($item)) {
						continue;
					}

					$url = trim(isset($item['url']) ? (string) $item['url'] : '');
					if ($url === '') {
						continue;
					}

					$out[] = array(
						'name' => isset($item['name']) ? (string) $item['name'] : '',
						'descr' => isset($item['description']) ? (string) $item['description'] : '',
						'url' => $url,
					);
				}

				return !empty($out) ? serialize($out) : '';
			}

			return parent::serializeValue($type, $value);
		}

		public function renderEdit(array $field)
		{
			$type = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
			$kind = isset($field['editor_kind']) ? (string) $field['editor_kind'] : 'media';
			$parsed = $this->value($field);
			$parsed = is_array($parsed) ? $parsed : array();

			if ($type === 'youtube') {
				return $this->renderYoutube($field, $parsed);
			}

			if ($type === 'text_to_image') {
				$value = isset($parsed['value']) ? (string) $parsed['value'] : (isset($parsed['raw']) ? (string) $parsed['raw'] : '');
				return '<input class="input" type="text" name="' . $this->e($this->fname($field, '[raw]'))
					. '" value="' . $this->e($value) . '">';
			}

			if ($kind === 'media_list') {
				return $this->renderMediaList($field, $type, $parsed);
			}

			return $this->renderSingleMedia($field, $type, $parsed);
		}

		protected function renderYoutube(array $field, array $p)
		{
			$url = isset($p['url']) ? (string) $p['url'] : '';
			$width = isset($p['width']) ? (string) $p['width'] : '';
			$height = isset($p['height']) ? (string) $p['height'] : '';
			$fs = isset($p['fullscreen']) ? (string) $p['fullscreen'] : '';
			$src = isset($p['source']) ? (string) $p['source'] : '';

			return '<div class="documents-youtube-field">'
				. '<label class="field"><span class="field-label">URL ролика</span>'
				. '<input class="input" type="url" name="' . $this->e($this->fname($field, '[url]')) . '" value="' . $this->e($url) . '" placeholder="https://www.youtube.com/watch?v=..."></label>'
				. '<div class="documents-inline-grid">'
				. '<label class="field"><span class="field-label">Ширина</span><input class="input" type="number" min="0" name="' . $this->e($this->fname($field, '[width]')) . '" value="' . $this->e($width) . '" placeholder="640"></label>'
				. '<label class="field"><span class="field-label">Высота</span><input class="input" type="number" min="0" name="' . $this->e($this->fname($field, '[height]')) . '" value="' . $this->e($height) . '" placeholder="360"></label>'
				. '<label class="field"><span class="field-label">Fullscreen</span><select class="select" name="' . $this->e($this->fname($field, '[fullscreen]')) . '">'
				. '<option value="true"' . ($fs === 'true' ? ' selected' : '') . '>Разрешён</option>'
				. '<option value="false"' . ($fs === 'false' ? ' selected' : '') . '>Запрещён</option></select></label>'
				. '<label class="field"><span class="field-label">Метод</span><select class="select" name="' . $this->e($this->fname($field, '[source]')) . '">'
				. '<option value="embed"' . (($src === '' ? 'embed' : $src) === 'embed' ? ' selected' : '') . '>Embed</option>'
				. '<option value="iframe"' . ($src === 'iframe' ? ' selected' : '') . '>Iframe</option></select></label>'
				. '</div></div>';
		}

		protected function renderSingleMedia(array $field, $type, array $p)
		{
			$accept = $type === 'download' ? 'all' : 'image';
			$isImage = $type !== 'download';
			$url = isset($p['url']) ? (string) $p['url'] : '';
			$description = isset($p['description']) ? (string) $p['description'] : '';
			$placeholder = $type === 'download' ? '/uploads/file.pdf' : '/uploads/file.jpg';
			$base = defined('ADMINX_BASE') ? ADMINX_BASE : '';
			$uploadDir = isset($field['media_upload_dir']) && $field['media_upload_dir'] !== '' ? (string) $field['media_upload_dir'] : '/uploads';
			$targetDir = isset($field['media_target_dir']) ? (string) $field['media_target_dir'] : '/uploads';
			$pickerDir = isset($field['media_picker_dir']) && $field['media_picker_dir'] !== '' ? (string) $field['media_picker_dir'] : '/uploads';
			$fileAccept = $accept === 'image' ? ' accept="image/*"' : '';

			return '<div class="documents-media-card documents-single-media-field" data-document-media-single data-field-id="' . (int) $field['Id']
				. '" data-media-accept="' . $this->e($accept) . '" data-upload-url="' . $this->e($base . '/media/upload')
				. '" data-upload-dir="' . $this->e($uploadDir) . '" data-target-dir="' . $this->e($targetDir)
				. '" data-picker-dir="' . $this->e($pickerDir) . '">'
				. $this->mediaThumb($url, $isImage)
				. '<div class="documents-media-card-body">'
				. $this->mediaPath($url, $isImage)
				. '<div class="documents-picker-input">'
				. '<input class="input mono" type="text" name="' . $this->e($this->fname($field, '[url]')) . '" value="' . $this->e($url)
				. '" placeholder="' . $placeholder . '" data-document-media-url data-document-picker-type="' . $this->e($accept) . '">'
				. '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-document-media-pick data-tooltip="Выбрать файл" aria-label="Выбрать файл"><i class="ti ti-photo-plus"></i></button>'
				. '</div>'
				. '<div class="documents-media-card-actions">'
				. '<button class="btn btn-ghost btn-sm" type="button" data-document-media-upload><i class="ti ti-upload"></i>Загрузить</button>'
				. '<input class="visually-hidden" type="file"' . $fileAccept . ' data-document-media-files>'
				. '</div>'
				. '<input class="input" type="text" name="' . $this->e($this->fname($field, '[description]')) . '" value="' . $this->e($description)
				. '" placeholder="' . ($type === 'download' ? 'Название или описание файла' : 'Описание изображения') . '">'
				. '</div></div>';
		}

		/** Квадратное превью медиа (картинка или иконка файла). */
		protected function mediaThumb($url, $isImage)
		{
			$url = (string) $url;
			if ($isImage) {
				$inner = $url !== '' ? '<img src="' . $this->e($url) . '" alt="">' : '<span><i class="ti ti-photo"></i></span>';
			} else {
				$inner = '<span><i class="ti ti-file"></i></span>';
			}

			return '<button class="documents-media-thumb" type="button" data-document-media-pick aria-label="Выбрать файл">' . $inner . '</button>';
		}

		/** Подпись «куда сохранится» — путь к файлу. */
		protected function mediaPath($url, $isImage = true)
		{
			$url = (string) $url;
			$body = $url !== ''
				? '<code>' . $this->e($url) . '</code>'
				: '<span class="documents-media-path-empty">' . ($isImage ? 'Изображение не выбрано' : 'Файл не выбран') . '</span>';
			return '<div class="documents-media-path" data-media-path>' . $body . '</div>';
		}

		protected function renderMediaList(array $field, $type, array $parsed)
		{
			$items = isset($parsed['items']) && is_array($parsed['items']) ? $parsed['items'] : array();
			$accept = $type === 'doc_files' ? 'all' : 'image';
			$base = defined('ADMINX_BASE') ? ADMINX_BASE : '';
			$uploadDir = isset($field['media_upload_dir']) && $field['media_upload_dir'] !== '' ? (string) $field['media_upload_dir'] : '/uploads';
			$targetDir = isset($field['media_target_dir']) ? (string) $field['media_target_dir'] : '/uploads';
			$pickerDir = isset($field['media_picker_dir']) && $field['media_picker_dir'] !== '' ? (string) $field['media_picker_dir'] : '/uploads';

			$isImage = $type !== 'doc_files';
			$rows = '';
			foreach ($items as $idx => $item) {
				$item = is_array($item) ? $item : array();
				$url = isset($item['url']) ? (string) $item['url'] : '';
				$rows .= '<div class="documents-media-card documents-media-row documents-media-row-' . $this->e($type) . '" data-document-media-row>'
					. $this->mediaThumb($url, $isImage)
					. '<div class="documents-media-card-body">'
					. $this->mediaPath($url, $isImage)
					. '<div class="documents-media-row-fields">' . $this->mediaRowFields($field, $type, (int) $idx, $item) . '</div>'
					. '<div class="documents-media-card-actions">'
					. '<button class="btn btn-ghost btn-icon btn-sm documents-drag-handle" type="button" data-doc-drag draggable="true" data-tooltip="Перетащить" aria-label="Перетащить"><i class="ti ti-grip-vertical"></i></button>'
					. '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-document-media-up data-tooltip="Выше" aria-label="Выше"><i class="ti ti-arrow-up"></i></button>'
					. '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-document-media-down data-tooltip="Ниже" aria-label="Ниже"><i class="ti ti-arrow-down"></i></button>'
					. '<button class="btn btn-ghost btn-icon btn-sm documents-media-remove" type="button" data-document-media-remove data-tooltip="Удалить" aria-label="Удалить"><i class="ti ti-trash"></i></button>'
					. '</div>'
					. '</div></div>';
			}

			$fileAccept = $accept === 'image' ? ' accept="image/*"' : '';
			return '<div class="documents-media-list" data-document-media-list data-field-id="' . (int) $field['Id']
				. '" data-field-type="' . $this->e($type) . '" data-media-accept="' . $this->e($accept)
				. '" data-upload-url="' . $this->e($base . '/media/upload') . '" data-upload-dir="' . $this->e($uploadDir)
				. '" data-target-dir="' . $this->e($targetDir) . '" data-picker-dir="' . $this->e($pickerDir) . '">'
				. '<input type="hidden" name="' . $this->e($this->fname($field, '[media_present]')) . '" value="1">'
				. '<div class="documents-media-list-actions">'
				. '<button class="btn btn-secondary btn-sm" type="button" data-document-media-add><i class="ti ti-plus"></i>Добавить</button>'
				. '<button class="btn btn-ghost btn-sm" type="button" data-document-media-upload><i class="ti ti-upload"></i>Загрузить</button>'
				. '<button class="btn btn-ghost btn-sm" type="button" data-document-media-import-folder><i class="ti ti-folder-plus"></i>Из папки</button>'
				. '<button class="btn btn-ghost btn-sm" type="button" data-document-media-clear><i class="ti ti-trash"></i>Очистить</button>'
				. '<input class="visually-hidden" type="file" multiple' . $fileAccept . ' data-document-media-files>'
				. '</div>'
				. '<div class="documents-media-dropzone" data-document-media-drop><i class="ti ti-cloud-upload"></i><span>Перетащите файлы сюда — после сохранения: <code data-document-media-target>' . $this->e($targetDir) . '</code></span></div>'
				. '<div class="documents-media-list-items" data-document-media-items>' . $rows . '</div>'
				. '<div class="documents-media-empty"' . (!empty($items) ? ' hidden' : '') . ' data-document-media-empty>Нет добавленных элементов.</div>'
				. '</div>';
		}

		protected function mediaRowFields(array $field, $type, $idx, array $item)
		{
			$base = $this->fname($field, '[items][' . $idx . ']');
			$url = isset($item['url']) ? (string) $item['url'] : '';
			$description = isset($item['description']) ? (string) $item['description'] : '';

			$pickerUrl = function ($sub, $val, $ph, $pickerType, $icon) use ($base) {
				return '<div class="documents-picker-input"><input class="input mono" type="text" name="' . $this->e($base . $sub)
					. '" data-media-key="' . trim($sub, '[]') . '" value="' . $this->e($val) . '" placeholder="' . $this->e($ph)
					. '" data-document-media-url data-document-picker-type="' . $pickerType . '">'
					. '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-document-media-pick data-tooltip="Выбрать файл" aria-label="Выбрать файл"><i class="ti ' . $icon . '"></i></button></div>';
			};
			$textInput = function ($sub, $val, $ph) use ($base) {
				return '<input class="input" type="text" name="' . $this->e($base . $sub) . '" data-media-key="' . trim($sub, '[]')
					. '" value="' . $this->e($val) . '" placeholder="' . $this->e($ph) . '">';
			};
			$textarea = function ($sub, $val, $ph) use ($base) {
				return '<textarea class="textarea" name="' . $this->e($base . $sub) . '" data-media-key="' . trim($sub, '[]')
					. '" rows="2" placeholder="' . $this->e($ph) . '">' . $this->e($val) . '</textarea>';
			};
			// Поле «Ссылка»: файл или документ (две кнопки выбора).
			$linkField = function ($sub, $val) use ($base) {
				return '<div class="documents-picker-input documents-picker-input-link"><input class="input mono" type="text" name="' . $this->e($base . $sub)
					. '" data-media-key="' . trim($sub, '[]') . '" value="' . $this->e($val) . '" placeholder="Ссылка или документ" data-document-media-url data-document-picker-type="all">'
					. '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-document-media-pick data-tooltip="Выбрать файл" aria-label="Выбрать файл"><i class="ti ti-paperclip"></i></button>'
					. '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-document-media-doc-pick data-tooltip="Выбрать документ" aria-label="Выбрать документ"><i class="ti ti-file-search"></i></button></div>';
			};

			if ($type === 'image_mega') {
				$title = isset($item['title']) ? (string) $item['title'] : '';
				$link = isset($item['link']) ? (string) $item['link'] : '';
				return $pickerUrl('[url]', $url, '/uploads/image.jpg', 'image', 'ti-photo-plus')
					. $textInput('[title]', $title, 'Заголовок изображения')
					. $textarea('[description]', $description, 'Описание')
					. $linkField('[link]', $link);
			}

			if ($type === 'doc_files') {
				$name = isset($item['name']) ? (string) $item['name'] : '';
				return $textInput('[name]', $name, 'Название файла')
					. $textarea('[description]', $description, 'Описание')
					. $pickerUrl('[url]', $url, '/uploads/file.pdf', 'all', 'ti-paperclip');
			}

			return $pickerUrl('[url]', $url, '/uploads/image.jpg', 'image', 'ti-photo-plus')
				. $textarea('[description]', $description, 'Описание');
		}

		protected function decodeListValue($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			$serialized = @unserialize($value, array('allowed_classes' => false));
			if (is_array($serialized)) {
				return $serialized;
			}

			$json = $this->decodeJsonValue($value);
			if (is_array($json)) {
				if (isset($json['items']) && is_array($json['items'])) {
					return $json['items'];
				}

				if (isset($json[0])) {
					return $json;
				}

				if (isset($json['url']) || isset($json['path'])) {
					return array($json);
				}
			}

			$lines = preg_split('/\r\n|\r|\n/', $value);
			if (count($lines) <= 1 && strpos($value, ',') !== false) {
				$lines = preg_split('/,/', $value);
			}

			$out = array();
			foreach ($lines ?: array() as $line) {
				$line = trim((string) $line);
				if ($line !== '') {
					$out[] = $line;
				}
			}

			return $out;
		}

		protected function decodeJsonValue($value)
		{
			$value = trim((string) $value);
			if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
				return null;
			}

			try {
				$json = Json::decode($value);
				return is_array($json) ? $json : null;
			} catch (\RuntimeException $e) {
				return null;
			}
		}

		protected function definitions()
		{
			return array(
				'image_single' => array(
					'title' => 'Изображение',
					'icon' => 'photo',
					'summary' => 'Одно изображение из медиабраузера.',
					'default_label' => 'Путь по умолчанию',
					'default_hint' => 'Обычно пусто. В документе будет использоваться медиабраузер.',
					'default_kind' => 'media',
					'entity_fields' => array('url', 'description'),
				),
				'image_multi' => array(
					'title' => 'Галерея',
					'icon' => 'photo-scan',
					'summary' => 'Набор изображений из медиабраузера.',
					'default_label' => 'Пути по умолчанию',
					'default_hint' => 'Один путь на строку, если нужно предзаполнить галерею.',
					'default_kind' => 'media_list',
					'entity_fields' => array('items[].url', 'items[].description'),
				),
				'image_mega' => array(
					'title' => 'Расширенная галерея',
					'icon' => 'photo-cog',
					'summary' => 'Сложное поле изображений. Редактор панели управления работает поверх медиабраузера.',
					'default_label' => 'Настройки по умолчанию',
					'default_kind' => 'media_list',
					'entity_fields' => array('items[].url', 'items[].title', 'items[].description', 'items[].link'),
				),
				'download' => array(
					'title' => 'Файл',
					'icon' => 'paperclip',
					'summary' => 'Файл для скачивания.',
					'default_label' => 'Путь к файлу по умолчанию',
					'default_kind' => 'media',
					'entity_fields' => array('url', 'description'),
				),
				'doc_files' => array(
					'title' => 'Файлы документа',
					'icon' => 'files',
					'summary' => 'Список файлов, привязанных к документу.',
					'default_label' => 'Файлы по умолчанию',
					'default_hint' => 'Один путь на строку.',
					'default_kind' => 'media_list',
					'entity_fields' => array('items[].name', 'items[].description', 'items[].url'),
				),
				'youtube' => array(
					'title' => 'YouTube',
					'icon' => 'brand-youtube',
					'summary' => 'Видео YouTube с дополнительными параметрами.',
					'default_label' => 'Видео по умолчанию',
					'default_hint' => 'URL или ID ролика. Дополнительные части legacy хранит через |.',
					'default_kind' => 'url',
					'entity_fields' => array('url', 'width', 'height', 'fullscreen', 'source'),
				),
				'text_to_image' => array(
					'title' => 'Текст в изображение',
					'icon' => 'photo-edit',
					'summary' => 'Текстовое значение, которое legacy превращает в изображение.',
					'default_label' => 'Текст по умолчанию',
					'default_hint' => 'Обычный текст; параметры вывода задаются шаблоном поля.',
					'default_kind' => 'text',
				),
			);
		}
	}
