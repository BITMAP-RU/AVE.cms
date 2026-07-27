<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/NavigationRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\StoredPhpRuntime;
	use App\Common\AdminLocation;
	use App\Frontend\Debug\PublicLayoutInspector;
	use App\Helpers\Debug;
	use App\Helpers\Url;

	/** Renders database-managed public navigation without legacy globals. */
	class NavigationRenderer
	{
		protected $repository;

		public function __construct(NavigationRepository $repository = null)
		{
			$this->repository = $repository ?: new NavigationRepository();
		}

		public function render($key, $levelList = '')
		{
			$navigation = $this->repository->find($key);
			if (!$navigation || !$this->isAllowed($navigation)) {
				return '';
			}

			Debug::startTime('NAVIAGTION_' . $key);

			$levels = array_values(array_filter(array_map('intval', explode(',', (string) $levelList))));
			$active = $this->repository->activePath($navigation->navigation_id, PublicPageContext::document());
			$data = $this->repository->items($key, $navigation, $active, $levels);
			$templates = array(
				1 => array('inactive' => $navigation->level1, 'active' => $navigation->level1_active),
				2 => array('inactive' => $navigation->level2, 'active' => $navigation->level2_active),
				3 => array('inactive' => $navigation->level3, 'active' => $navigation->level3_active),
			);

			$html = '';
			if ($data['items']) {
				$html = (string) $navigation->begin
					. $this->renderLevel($navigation, $data['items'], $active['way'], $templates, $data['parent'])
					. (string) $navigation->end;
			}

			$html = Url::rewrite($html);
			$html = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $html);
			$html = str_replace(array("\n", "\r"), '', $html);

			PublicProfiler::record(array('NAVIGATIONS', $key), Debug::endTime('NAVIAGTION_' . $key));
			return PublicLayoutInspector::annotate($html, 'navigation', array(
				'id' => isset($navigation->navigation_id) ? (int) $navigation->navigation_id : $key,
				'title' => isset($navigation->title) ? (string) $navigation->title : 'Навигация ' . $key,
				'alias' => isset($navigation->alias) ? (string) $navigation->alias : (string) $key,
				'tag' => '[tag:navigation:' . $key . ($levelList !== '' ? ':' . $levelList : '') . ']',
				'edit_url' => isset($navigation->navigation_id)
					? AdminLocation::url('navigation?edit=' . (int) $navigation->navigation_id)
					: AdminLocation::url('navigation'),
			));
		}

		public function renderTag(array $tag)
		{
			return $this->render(isset($tag[1]) ? $tag[1] : '', isset($tag[2]) ? $tag[2] : '');
		}

		public function canView($key)
		{
			$navigation = $this->repository->find($key);
			return $navigation ? $this->isAllowed($navigation) : false;
		}

		public function renderLevel($navigation, array $items, array $activeWay, array $templates, $parent = 0)
		{
			$parent = (int) $parent;
			if (empty($items[$parent])) {
				return '';
			}

			$level = (int) $items[$parent][0]['level'];
			if (!isset($templates[$level])) {
				return '';
			}

			$html = '';
			$itemNumber = 0;
			$itemCount = count($items[$parent]);

			foreach ($items[$parent] as $row) {
				$itemNumber++;
				$lastItem = $itemNumber === $itemCount;
				$active = in_array((int) $row['navigation_item_id'], $activeWay);
				$item = (string) $templates[$level][$active ? 'active' : 'inactive'];
				$item = $this->replaceConditionTags($item);
				$item = $this->replaceItemTags($item, $row);

				$item = '<?php $item_num = ' . var_export($itemNumber, true) . '; ?>' . $item;
				$item = '<?php $last_item = ' . var_export($lastItem, true) . '; ?>' . $item;

				if ($level === 1) {
					$tag = '[tag:level:2]';
					$existsTag = '[tag:level:2:exist]';
				} elseif ($level === 2) {
					$tag = '[tag:level:3]';
					$existsTag = '[tag:level:3:exist]';
				} else {
					$tag = '';
					$existsTag = '';
				}

				$childId = (int) $row['navigation_item_id'];
				if (!empty($items[$childId])) {
					$item = '<?php $exist_level = true; ?>' . $item;
					$item = str_replace(
						$tag,
						$this->renderLevel($navigation, $items, $activeWay, $templates, $childId),
						$item
					);
					$item = str_replace($existsTag, '1', $item);
				} else {
					$item = '<?php $exist_level = false; ?>' . $item;
					$item = str_replace($tag, '', $item);
					$item = str_replace($existsTag, '0', $item);
				}

				$html .= StoredPhpRuntime::evaluate(' ?>' . $item . '<?php ', get_defined_vars());
			}

			$beginField = 'level' . $level . '_begin';
			$endField = 'level' . $level . '_end';
			$begin = (string) $navigation->{$beginField};
			$content = strpos($begin, '[tag:content]') !== false
				? str_replace('[tag:content]', $html, $begin)
				: $begin . $html;

			return $content . (string) $navigation->{$endField};
		}

		protected function replaceItemTags($item, array $row)
		{
			$item = str_replace(
				array('[tag:linkid]', '[tag:linkname]', '[tag:path]', '[tag:target]', '[tag:desc]', '[tag:img]'),
				array(
					$row['navigation_item_id'],
					$row['title'],
					ABS_PATH,
					$row['target'],
					stripslashes($row['description']),
					stripslashes($row['image']),
				),
				$item
			);

			if (!empty($row['document_id'])) {
				$alias = $row['alias'] !== '' ? trim($row['alias']) : Url::prepare($row['title']);
				$link = 'index.php?id=' . (int) $row['document_id'] . '&amp;doc=' . $alias;
				$item = str_replace('[tag:link]', $link, $item);
				$item = str_ireplace('"//"', '"/"', str_ireplace('///', '/', Url::rewrite($item)));
			} else {
				$item = str_replace('[tag:link]', $row['alias'], $item);
			}

			if (strpos((string) $row['alias'], 'www.') === 0) {
				$item = str_replace('www.', 'http://www.', $item);
			}

			$activeImage = '';
			if ((string) $row['image'] !== '') {
				$parts = explode('.', $row['image']);
				$activeImage = $parts[0] . '_act.' . (isset($parts[1]) ? $parts[1] : '');
			}

			$item = str_replace(
				array('[tag:img_act]', '[tag:css_id]', '[tag:css_class]', '[tag:css_style]'),
				array(
					stripslashes($activeImage),
					!empty($row['css_id']) ? stripslashes($row['css_id']) : '',
					!empty($row['css_class']) ? stripslashes($row['css_class']) : '',
					!empty($row['css_style']) ? stripslashes($row['css_style']) : '',
				),
				$item
			);

			return preg_replace('/\[tag:([a-zA-Z0-9-_]+)\]/', '', $item);
		}

		protected function replaceConditionTags($item)
		{
			$item = str_replace('[tag:if_first]', '<?php if(isset($item_num) && $item_num===1) { ?>', $item);
			$item = str_replace('[tag:if_not_first]', '<?php if(isset($item_num) && $item_num!==1) { ?>', $item);
			$item = str_replace('[tag:if_last]', '<?php if(isset($last_item) && $last_item) { ?>', $item);
			$item = str_replace('[tag:if_not_last]', '<?php if(isset($item_num) && !$last_item) { ?>', $item);
			$item = str_replace('[tag:if_sub_level]', '<?php if (isset($exist_level) && $exist_level) { ?>', $item);
			$item = str_replace('[tag:if_no_sub_level]', '<?php if (isset($exist_level) && !$exist_level) { ?>', $item);
			$item = preg_replace('/\[tag:if_every:([0-9-]+)\]/u', '<?php if(isset($item_num) && !($item_num % $1)){ ?>', $item);
			$item = preg_replace('/\[tag:if_not_every:([0-9-]+)\]/u', '<?php if(isset($item_num) && ($item_num % $1)){ ?>', $item);
			$item = str_replace('[tag:/if]', '<?php } ?>', $item);
			return str_replace('[tag:if_else]', '<?php }else{ ?>', $item);
		}

		protected function isAllowed($navigation)
		{
			$groupId = defined('UGROUP') ? (int) UGROUP : 2;
			return in_array($groupId, (array) $navigation->user_group);
		}
	}
