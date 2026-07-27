<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicPageRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Debug;
	use App\Helpers\Request;
	use App\Common\AdminLocation;
	use App\Frontend\Debug\PublicLayoutInspector;

	/** Builds the public page from a resolved document and registered renderers. */
	class PublicPageRenderer
	{
		/** @var object|null Kept for stored rubric PHP compatibility. */
		public $curentdoc = null;

		public $_doc_not_found = '<h1>HTTP Error 404: Page not found</h1>';
		public $_rubric_template_empty = '<h1>Ошибка</h1><br />Не задан шаблон рубрики.';
		public $_doc_not_published = 'Запрашиваемый документ запрещен к публикации.';
		public $_doc_outside_publication_window = 'Документ недоступен посетителям: срок публикации ещё не наступил или уже завершён.';

		public function resolveTemplate($template = null, $fetched = null, $templateId = null)
		{
			$resolver = new PageTemplateResolver();
			$result = $resolver->resolve($this->curentdoc, array(
				'only_content' => defined('ONLYCONTENT'),
				'pop' => isset($_REQUEST['pop']) && $_REQUEST['pop'] == 1,
				'fetched' => $fetched,
				'template' => $template,
				'template_id' => $templateId,
			));

			if (!empty($result['used_document_template'])) {
				unset($this->curentdoc->template_text);
			}

			if (!defined('THEME_FOLDER')) {
				define('THEME_FOLDER', $result['theme']);
			}

			return $result['text'];
		}

		public function resolveRubricPermissions($rubricId)
		{
			unset($_SESSION[$rubricId . '_docread']);
			$resolver = new RubricPermissionResolver();
			$embedded = isset($this->curentdoc->rubric_permission)
				? $this->curentdoc->rubric_permission
				: null;
			$permissions = $resolver->resolve($rubricId, UGROUP, $embedded);
			foreach ($permissions as $permission) {
				$_SESSION[$rubricId . '_' . $permission] = 1;
			}

			return $permissions;
		}

		public function respondNotFound()
		{
			$responder = new PublicNotFoundResponder();
			$responder->redirectToDocument(PAGE_NOT_FOUND_ID, $this->_doc_not_found);
		}

		public function documentIsAvailable()
		{
			$policy = new DocumentAccessPolicy();
			$status = $policy->status($this->curentdoc, PAGE_NOT_FOUND_ID);
			if ($status === DocumentAccessPolicy::AVAILABLE) {
				return true;
			}

			if (in_array($status, array(DocumentAccessPolicy::DELETED, DocumentAccessPolicy::DELAYED), true)
				&& (isset($_SESSION['adminpanel']) || isset($_SESSION['alles']))) {
				$message = $status === DocumentAccessPolicy::DELAYED
					? $this->_doc_outside_publication_window
					: $this->_doc_not_published;
				echo PublicNotice::render($message);
				return true;
			}

			$this->curentdoc = false;
			return false;
		}

			public function render($id, $rubricId = '')
			{
					Debug::startTime('DOC_' . $id);
					\App\Common\ModuleTagRegistry::deferPrivate(true);

				$cacheCompile = false;
				$isAjax = Request::isAjax();
				$renderCache = new DocumentRenderCache();
					$documentFieldRepository = new DocumentFieldRepository();
					$isRevisionPreview = DocumentFieldRepository::hasRevision((int) $id);
					$componentRenderer = new PageComponentRenderer();
					$shellRenderer = new PageShellRenderer();
					$out = '';

				// Определяем рубрику
					define('RUB_ID', !empty($rubricId)
						? $rubricId
					: $this->curentdoc->rubric_id);

				$main_content = '';

					if (! \App\Frontend\PublicPageContext::hasDeferredPage() && (defined('CACHE_DOC_FULL') && CACHE_DOC_FULL)
						&& !$isAjax && !PublicLayoutInspector::enabled())
				{
						if ($out = $renderCache->readPage(
							$this->curentdoc, UGROUP, RUB_ID, $_REQUEST, $isAjax, PaginationRenderer::currentPage()
						))
						{
							$this->resolveRubricPermissions(RUB_ID);

						// Выполняем Код рубрики До загрузки документа
						\App\Common\StoredPhpRuntime::evaluate(
							' ?>' . $this->curentdoc->rubric_start_code . '<?php ',
							get_defined_vars(),
							$this
						);

						$cacheCompile = true;
					}

					//$out = '';
				}

				if (! $out)
				{
					$externalDispatcher = new \App\Frontend\ExternalContentDispatcher();
					$externalContent = $externalDispatcher->dispatch(
						isset($_REQUEST['sysblock']) ? $_REQUEST['sysblock'] : null,
						isset($_REQUEST['request']) ? $_REQUEST['request'] : null,
						$isAjax,
						PAGE_NOT_FOUND_ID,
						$this->_doc_not_found
					);
					if ($externalContent['handled'])
					{
						$out = $externalContent['content'];
					}

					// В противном случае начинаем вывод документа
					else
					{
						// проверяем параметры публикации документа
						if (!$this->documentIsAvailable())
							$this->respondNotFound();

						\App\Frontend\PublicPageContext::setDocument($this->curentdoc);

						$rubricPermissions = $this->resolveRubricPermissions(RUB_ID);

						// Выполняем Код рубрики До загрузки документа
						\App\Common\StoredPhpRuntime::evaluate(
							' ?>' . $this->curentdoc->rubric_start_code . '<?php ',
							get_defined_vars(),
							$this
						);

							// Runtime-страницы могут выбрать собственную оболочку сайта.
							$runtimePage = \App\Frontend\PublicPageContext::hasDeferredPage()
								? \App\Frontend\PublicPageContext::deferredPage()
								: array();
							$runtimeTemplateId = isset($runtimePage['template_id']) ? (int) $runtimePage['template_id'] : 0;
							if ($runtimeTemplateId > 0) {
								$runtimeTemplate = (new \App\Frontend\PageTemplateRepository())->findText($runtimeTemplateId);
								$out = $this->resolveTemplate(null, $runtimeTemplate, $runtimeTemplateId);
							} else {
								$out = $this->resolveTemplate(null, null, $this->curentdoc->rubric_template_id);
							}

						$rubricAccess = new \App\Frontend\RubricPermissionResolver();
						if (! $rubricAccess->allows($rubricPermissions, 'docread'))
						{	// читать запрещено - извлекаем ругательство и отдаём вместо контента
							$main_content = PublicSettings::get('message_forbidden');
						}
						else
						{
							if ($isRevisionPreview)
							{
								// Preview не влияет на статистику документа.
							}
							elseif (isset ($_REQUEST['print']) && $_REQUEST['print'] == 1)
							{
								$viewTracker = new \App\Frontend\DocumentViewTracker();
								$viewTracker->trackPrint($id);
							}
							elseif (\App\Frontend\PublicPageContext::hasDeferredPage())
							{
								$runtimePage = \App\Frontend\PublicPageContext::deferredPage();
								$main_content = (string) $runtimePage['html'];
							}
							elseif (!$isRevisionPreview)
							{
								$viewTracker = new \App\Frontend\DocumentViewTracker();
								$viewTracker->trackView($id);
							}

								// Runtime-страница использует документ только как оболочку сайта.
								// Ее содержимое нельзя заменять кэшем или шаблоном рубрики.
								if (! \App\Frontend\PublicPageContext::hasDeferredPage())
								{
									// Извлекаем скомпилированный шаблон документа из кэша
									if (!$isRevisionPreview && defined('CACHE_DOC_TPL') && CACHE_DOC_TPL) // && empty ($_POST)
									{
										$documentFieldRepository->all((int) $this->curentdoc->Id);
										$main_content = $renderCache->readTemplate($this->curentdoc, UGROUP, RUB_ID);
									}
									else
										// Кэширование запрещено
										$main_content = false;

									// Собираем контент
									// Если в кеше нет контента, то
									if (empty($main_content))
									{
										// Основной/дополнительный шаблон уже разрешён DocumentRepository.
										$rubTmpl = isset($this->curentdoc->rubric_template)
											? (string) $this->curentdoc->rubric_template
											: '';

										$rubTmpl = trim($rubTmpl);

										// Собираем шаблон рубрики
										if (empty($rubTmpl))
										{
											// Если не задан шаблон рубрики, выводим сообщение
											$main_content = $this->_rubric_template_empty;
										}
										else
										{
											// Обрабатываем основные поля рубрики
											$renderer = new \App\Frontend\RubricTemplateRenderer();
											$main_content = $renderer->render($rubTmpl, $this->curentdoc);
										}

										if (!$isRevisionPreview) {
											$renderCache->writeTemplate($this->curentdoc, UGROUP, RUB_ID, $main_content);
										}
									}
								}
						}

						$inspectedMainContent = PublicLayoutInspector::annotate($main_content, 'document', array(
							'id' => isset($this->curentdoc->Id) ? (int) $this->curentdoc->Id : $id,
							'title' => isset($this->curentdoc->document_title) ? (string) $this->curentdoc->document_title : 'Документ #' . $id,
							'alias' => isset($this->curentdoc->document_alias) ? (string) $this->curentdoc->document_alias : '',
							'tag' => '[tag:maincontent]',
							'edit_url' => AdminLocation::url('documents/' . (int) $id . '/edit'),
						));
						$out = str_replace('[tag:maincontent]', $inspectedMainContent, $out);

						unset ($this->curentdoc->rubric_template, $this->curentdoc->template);
					}

					//-- Конец вывода документа

					// Тут мы вводим в хеадер и футер иньекцию скриптов.
					if (defined('RUB_ID'))
					{
						$rubricHeader = isset($this->curentdoc->rubric_header_template)
							? (string) $this->curentdoc->rubric_header_template
							: '';
						$rubricOpenGraph = isset($this->curentdoc->rubric_og_template)
							? trim((string) $this->curentdoc->rubric_og_template)
							: '';
						if ($rubricOpenGraph !== '') {
							$rubricHeader = rtrim($rubricHeader) . "\n" . $rubricOpenGraph;
						}

						$replace = [
							'[tag:rubheader]' => $rubricHeader,
							'[tag:rubfooter]' => $this->curentdoc->rubric_footer_template
						];

						$out = str_replace(array_keys($replace), array_values($replace), $out);

						unset ($replace);
					}

						$themeMainContent = isset($inspectedMainContent) ? $inspectedMainContent : $main_content;
						$out = (new ThemePageShellRenderer())->render($out, $themeMainContent, $this->curentdoc);
						$out = $componentRenderer->render($out, $id, $this, $this->curentdoc);

						$out = $shellRenderer->render($out, $main_content, $this->curentdoc);
						unset($main_content);

					PublicProfiler::record(array('DOCUMENT', 'FETCH'), Debug::endTime('DOC_' . $id));

					if ($cacheCompile == false && !PublicLayoutInspector::enabled())
					{
						$renderCache->writePage(
							$this->curentdoc, UGROUP, RUB_ID, $_REQUEST, $isAjax, PaginationRenderer::currentPage(), $out
						);
					}
				}

					\App\Common\ModuleTagRegistry::deferPrivate(false);
					$out = (new ModuleTagRenderer())->renderPrivate($out, $this, $this->curentdoc);

					// Выводим собранный документ
					echo $out;
			}

		}
	?>
