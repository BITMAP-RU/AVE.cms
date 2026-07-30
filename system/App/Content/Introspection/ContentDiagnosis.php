<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Introspection/ContentDiagnosis.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Introspection;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Converts a page context package into concise, actionable findings. */
	class ContentDiagnosis
	{
		public function page(array $context)
		{
			$findings = array();
			$document = $context['document'];
			$rubric = $context['rubric'];
			$templates = $context['templates'];

			if (!empty($document['deleted'])) {
				$findings[] = $this->finding('document_deleted', 'error', 'Документ удалён',
					'Публичный сайт должен отвечать 404 для этого документа.', $document, 'Восстановите документ.', $document['editor_url']);
			} elseif ($document['status'] === 'delayed') {
				$findings[] = $this->finding('document_delayed', 'warning', 'Документ вне срока публикации',
					'Ограничение по дате сейчас скрывает страницу от обычного посетителя.', $document,
					'Проверьте даты начала и окончания публикации.', $document['editor_url']);
			} elseif (empty($document['published'])) {
				$findings[] = $this->finding('document_unpublished', 'info', 'Документ снят с публикации',
					'Он доступен по прямой ссылке, но исключён из обычных подборок.', $document,
					'Опубликуйте документ, если он должен вернуться в списки.', $document['editor_url']);
			}

			if (trim((string) $document['meta']['description']) === '') {
				$findings[] = $this->finding('meta_description_empty', 'warning', 'Не заполнено метаописание',
					'У страницы нет собственного description.', array('document_id' => $document['id']),
					'Заполните SEO-описание документа.', $document['editor_url']);
			}

			if (empty($templates['site'])) {
				$findings[] = $this->finding('site_template_missing', 'error', 'Не найден шаблон сайта',
					'Рубрика ссылается на отсутствующий общий шаблон.', array('template_id' => $rubric['template_id']),
					'Назначьте существующий шаблон сайта.', $rubric['editor_url']);
			}

			if (!empty($templates['rubric']['content']['empty'])
				&& (empty($templates['alternate']) || !empty($templates['alternate']['content']['empty']))) {
				$findings[] = $this->finding('rubric_template_empty', 'error', 'Пустой шаблон материала',
					'Ни основной, ни дополнительный шаблон не содержит разметку документа.', array('rubric_id' => $rubric['id']),
					'Заполните шаблон рубрики.', $templates['rubric']['editor_url']);
			}

			foreach ($context['fields'] as $field) {
				if (!empty($field['required']) && !empty($field['empty'])) {
					$findings[] = $this->finding('required_field_empty', 'warning', 'Не заполнено обязательное поле',
						$field['title'] . ' (' . ($field['alias'] !== '' ? $field['alias'] : '#' . $field['id']) . ')',
						array('field_id' => $field['id'], 'field_type' => $field['type']),
						'Заполните поле в документе.', $document['editor_url']);
				}
			}

			foreach ($context['components'] as $component) {
				if ($component['component_status'] === 'broken') {
					$findings[] = $this->finding('component_broken', 'error', 'Компонент не найден',
						$component['tag'] . ' используется в «' . $component['source_title'] . '».',
						array('source' => $component['source_type'] . ':' . $component['source_id'], 'tag' => $component['tag']),
						'Исправьте тег или восстановите компонент.', $component['source_url']);
				} elseif ($component['component_status'] === 'disabled') {
					$findings[] = $this->finding('component_disabled', 'warning', 'Подключён выключенный компонент',
						$component['component_title'] . ' подключён, но сейчас выключен.',
						array('tag' => $component['tag']), 'Включите компонент или удалите его тег.', $component['component_url']);
				}
			}

			if (!$findings) {
				$findings[] = $this->finding('page_ok', 'ok', 'Явных проблем не найдено',
					'Документ, шаблоны и найденные связи выглядят согласованно.', array(),
					'Дополнительных действий не требуется.', $document['editor_url']);
			}

			return $findings;
		}

		protected function finding($code, $level, $title, $message, array $evidence, $action, $editorUrl)
		{
			return array(
				'code' => $code,
				'level' => $level,
				'title' => $title,
				'message' => $message,
				'evidence' => IntrospectionSanitizer::value($evidence),
				'action' => $action,
				'editor_url' => (string) $editorUrl,
			);
		}
	}
