<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/ImagePresets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use DB;

	/**
	 * Справочник «видов изображений»: именованные наборы параметров обрезки
	 * (режим + размер + формат + качество), которые подставляются в редактор
	 * медиа и привязываются к полям-картинкам рубрик.
	 */
	class ImagePresets
	{
		const MAX_SIDE = 2400;

		public static function table()
		{
			return ContentTables::table('media_image_presets');
		}

		public static function available()
		{
			try { return DatabaseSchema::tableExists(self::table()); }
			catch (\Throwable $e) { return false; }
		}

		public static function modes()
		{
			return array(
				'cover' => 'Обрезать под размер',
				'contain' => 'Вписать целиком',
				'fit' => 'Уменьшить (без обрезки)',
				'stretch' => 'Растянуть',
			);
		}

		public static function formats()
		{
			return array(
				'original' => 'Как есть',
				'jpg' => 'JPG',
				'png' => 'PNG',
				'webp' => 'WebP',
			);
		}

		public static function all()
		{
			if (!self::available()) { return array(); }
			$rows = DB::query(
				'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, title ASC, id ASC'
			)->getAll() ?: array();

			return array_map(array(__CLASS__, 'format'), $rows);
		}

		public static function one($id)
		{
			if (!self::available()) { return null; }
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::format($row) : null;
		}

		public static function byCode($code)
		{
			$code = self::slug($code);
			if ($code === '' || !self::available()) { return null; }
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE code=%s LIMIT 1', $code)->getAssoc();
			return $row ? self::format($row) : null;
		}

		public static function count()
		{
			if (!self::available()) { return 0; }
			return (int) DB::query('SELECT COUNT(*) FROM ' . self::table())->getValue();
		}

		/**
		 * Проверить и нормализовать вход. Возвращает [errors, data].
		 */
		public static function validate(array $input, $id = 0)
		{
			$errors = array();
			$title = trim((string) (isset($input['title']) ? $input['title'] : ''));
			if ($title === '') { $errors['title'] = 'Укажите название'; }

			$code = self::slug(isset($input['code']) && trim((string) $input['code']) !== '' ? $input['code'] : $title);
			if ($code === '') { $errors['code'] = 'Не удалось построить код из названия'; }

			$mode = (string) (isset($input['mode']) ? $input['mode'] : 'cover');
			if (!array_key_exists($mode, self::modes())) { $errors['mode'] = 'Неизвестный режим'; }

			$format = (string) (isset($input['format']) ? $input['format'] : 'original');
			if (!array_key_exists($format, self::formats())) { $errors['format'] = 'Неизвестный формат'; }

			$width = (int) (isset($input['width']) ? $input['width'] : 0);
			$height = (int) (isset($input['height']) ? $input['height'] : 0);
			foreach (array('width' => $width, 'height' => $height) as $key => $value) {
				if ($value < 0 || $value > self::MAX_SIDE) {
					$errors[$key] = 'Размер от 0 до ' . self::MAX_SIDE;
				}
			}

			// «Вписать/растянуть/обрезать» требуют обе стороны, «уменьшить» — хотя бы одну.
			if ($mode === 'fit') {
				if ($width < 1 && $height < 1) { $errors['width'] = 'Для «уменьшить» задайте ширину или высоту'; }
			} elseif ($width < 1 || $height < 1) {
				$errors['width'] = 'Задайте ширину и высоту';
			}

			$quality = (int) (isset($input['quality']) ? $input['quality'] : 82);
			if ($quality < 1 || $quality > 100) { $errors['quality'] = 'Качество 1–100'; }

			if (empty($errors) && self::codeExists($code, (int) $id)) {
				$errors['code'] = 'Вид с таким кодом уже есть';
			}

			return array($errors, array(
				'code' => $code,
				'title' => mb_substr($title, 0, 190, 'UTF-8'),
				'group_label' => mb_substr(trim((string) (isset($input['group_label']) ? $input['group_label'] : '')), 0, 120, 'UTF-8'),
				'mode' => $mode,
				'width' => $width,
				'height' => $height,
				'format' => $format,
				'quality' => $quality,
				'webp_twin' => empty($input['webp_twin']) ? 0 : 1,
				'sort_order' => max(0, min(9999, (int) (isset($input['sort_order']) ? $input['sort_order'] : 100))),
			));
		}

		public static function save($id, array $data)
		{
			$id = (int) $id;
			$now = time();
			if ($id > 0) {
				$data['updated_at'] = $now;
				DB::Update(self::table(), $data, 'id=%i', $id);
				return $id;
			}

			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			DB::Insert(self::table(), $data);
			return (int) DB::insertId();
		}

		public static function delete($id)
		{
			if (!self::available()) { return false; }
			$exists = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE id=%i', (int) $id)->getValue();
			if ($exists < 1) { return false; }
			DB::Delete(self::table(), 'id=%i', (int) $id);
			return true;
		}

		/** Опции для выпадающих списков (редактор, настройки поля). */
		public static function options()
		{
			$out = array();
			foreach (self::all() as $preset) {
				$out[] = array(
					'code' => $preset['code'],
					'title' => $preset['title'],
					'group_label' => $preset['group_label'],
					'mode' => $preset['mode'],
					'width' => $preset['width'],
					'height' => $preset['height'],
					'format' => $preset['format'],
					'quality' => $preset['quality'],
					'webp_twin' => $preset['webp_twin'],
					'label' => $preset['label'],
				);
			}

			return $out;
		}

		protected static function codeExists($code, $exceptId = 0)
		{
			if (!self::available()) { return false; }
			return (bool) DB::query(
				'SELECT id FROM ' . self::table() . ' WHERE code=%s AND id!=%i LIMIT 1',
				self::slug($code),
				(int) $exceptId
			)->getValue();
		}

		protected static function format(array $row)
		{
			$modes = self::modes();
			$width = (int) (isset($row['width']) ? $row['width'] : 0);
			$height = (int) (isset($row['height']) ? $row['height'] : 0);
			$mode = (string) (isset($row['mode']) ? $row['mode'] : 'cover');
			$size = $width && $height ? ($width . '×' . $height) : ($width ? ($width . '×авто') : ($height ? ('авто×' . $height) : 'авто'));
			return array(
				'id' => (int) $row['id'],
				'code' => (string) $row['code'],
				'title' => (string) $row['title'],
				'group_label' => (string) (isset($row['group_label']) ? $row['group_label'] : ''),
				'mode' => $mode,
				'mode_label' => isset($modes[$mode]) ? $modes[$mode] : $mode,
				'width' => $width,
				'height' => $height,
				'format' => (string) (isset($row['format']) ? $row['format'] : 'original'),
				'quality' => (int) (isset($row['quality']) ? $row['quality'] : 82),
				'webp_twin' => (int) (isset($row['webp_twin']) ? $row['webp_twin'] : 1),
				'sort_order' => (int) (isset($row['sort_order']) ? $row['sort_order'] : 100),
				'label' => $row['title'] . ' · ' . $size,
			);
		}

		protected static function slug($value)
		{
			$value = mb_strtolower(trim((string) $value), 'UTF-8');
			$map = array(
				'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
				'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
				'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
			);
			$value = strtr($value, $map);
			$value = preg_replace('/[^a-z0-9]+/', '-', $value);
			return substr(trim((string) $value, '-'), 0, 64);
		}
	}
