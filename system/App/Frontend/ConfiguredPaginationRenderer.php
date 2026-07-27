<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ConfiguredPaginationRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	class ConfiguredPaginationRenderer
	{
		private static $types = [
			'page', 'apage', 'artpage'
		];


		protected function __construct()
		{
			//
		}


		// Получаем номер текущей страницы
		public static function currentPage ($type = 'page')
		{
			if (! in_array($type, self::$types, true))
			{
				return 1;
			}

			return PaginationRenderer::currentPage($type);
		}


		public static function render ($total_pages, $type, $template_label, $pagination_id = 1, $pagination_box_ext = '')
		{
			if (! in_array($type, self::$types, true))
			{
				$type = 'page';
			}

			$pagination = '';

			$containers = self::containers($pagination_id);

			$curent_page = self::currentPage($type);

			if ($curent_page     == 1)
			{
				$pages = [$curent_page, $curent_page + 1, $curent_page + 2, $curent_page + 3, $curent_page + 4];
			}
			elseif ($curent_page   == 2)
			{
				$pages = [$curent_page - 1, $curent_page, $curent_page + 1, $curent_page + 2, $curent_page + 3];
			}
			elseif ($curent_page + 1 == $total_pages)
			{
				$pages = [$curent_page - 3, $curent_page - 2, $curent_page - 1, $curent_page, $curent_page + 1];
			}
			elseif ($curent_page == $total_pages)
			{
				$pages = [$curent_page - 4, $curent_page - 3, $curent_page - 2, $curent_page - 1, $curent_page];
			}
			else
			{
				$pages = [$curent_page - 2, $curent_page - 1, $curent_page, $curent_page + 1, $curent_page + 2];
			}

			$pages = array_unique($pages);

			$pagination_link_box = '';						// Контенйнер для ссылок %s
			$pagination_separator_box = '';					// Контенйнер для метки о наличии страниц кроме видимых %s
			$pagination_active_link_box = '';				// Контенйнер для активного элемента %s
			$pagination_start_label = '';					// Текст ссылки "Первая"
			$pagination_end_label = '';						// Текст ссылки "Последняя"
			$pagination_separator_label = '';				// Текст метки о наличии страниц кроме видимых
			$pagination_next_label = '';					// Текст ссылки "Следующая"
			$pagination_prev_label = '';					// Текст ссылки "Предыдущая"
			$pagination_link_template = '';					// Шаблон ссылки
			$pagination_link_active_template = '';			// Шаблон активной ссылки
			$pagination_box = '';							// Общий контейнер

			extract($containers, EXTR_OVERWRITE);

			// Первая
			if ($total_pages > 5 && $curent_page > 3)
			{
				$search = ['[link]', '[page]', '[name]'];
				$replace = [$template_label, 1, $pagination_start_label];

				$first = str_replace($search, $replace, $pagination_link_template);

				$pagination .= sprintf($pagination_link_box, str_replace(['{s}', '{t}'], $pagination_start_label, str_replace(['&amp;'. $type .'={s}', '&' . $type .'={s}', '/' . $type . '-{s}'], '', $first)));

				// Если есть шаблон метки о наличии страниц, добавляем
				if ($pagination_separator_label != '')
				{
					$pagination .= sprintf($pagination_separator_box, $pagination_separator_label);
				}
			}

			// Предыдущая
			if ($curent_page > 1)
			{
				// Если равна 2
				$search = ['[link]', '[page]', '[name]'];
				$replace = [$template_label, $curent_page-1, $pagination_prev_label];
				$link = str_replace($search, $replace, $pagination_link_template);

				if ($curent_page - 1 == 1)
				{

					$pagination .= sprintf($pagination_link_box, str_replace('{t}', $pagination_prev_label, str_replace(['&amp;' . $type . '={s}', '&' . $type . '={s}', '/' . $type . '-{s}'], '', $link)));
				}

				// Если больше 2х
				else
				{

					$pagination .= sprintf($pagination_link_box, str_replace('{t}', $pagination_prev_label, str_replace('{s}', ($curent_page - 1), $link)));
				}
			}

			foreach ($pages as $page)
			{
				if ($page >= 1 && $page <= $total_pages)
				{
					// Текущий номер страницы (активная страница)
					$search = ['[link]', '[page]', '[name]'];

					if ($curent_page == $page && $curent_page != 1)
					{
						$replace = [$template_label, $curent_page, $curent_page];

						$link = str_replace($search, $replace, $pagination_link_active_template);

						$pagination .= sprintf($pagination_active_link_box, str_replace('{s}', ($curent_page), $link));
					}
					else
					{
						// Страница номер 1
						$replace = [$template_label, $page, $page];

						if ($page == 1)
						{
							if ($curent_page !== 1)
							{
								$link = str_replace($search, $replace, $pagination_link_template);
								$pagination .= sprintf($pagination_link_box, str_replace(['{s}', '{t}'], $page, str_replace(['&amp;' . $type . '={s}', '&' . $type . '={s}', '/' . $type . '-{s}'], '', $link)));
							}
							else
							{
								$link = str_replace($search, $replace, $pagination_link_active_template);
								$pagination .= sprintf($pagination_active_link_box, str_replace(['{s}', '{t}'], $page, str_replace(['&amp;' . $type . '={s}', '&' . $type . '={s}', '/' . $type . '-{s}'], '', $link)));
							}
						}

						// Остальные неактивные номера страниц
						else
						{
							$link = str_replace($search, $replace, $pagination_link_template);
							$pagination .= sprintf($pagination_link_box, str_replace(['{s}', '{t}'], $page, $link));
						}
					}
				}
			}


			// Следующая
			if ($curent_page < $total_pages)
			{
				$search = ['[link]', '[page]', '[name]'];
				$replace = [$template_label, $curent_page + 1, $pagination_next_label];

				$link = str_replace($search, $replace, $pagination_link_template);

				$pagination .= sprintf($pagination_link_box, str_replace('{t}', $pagination_next_label, str_replace('{s}', ($curent_page + 1), $link)));
			}

			// Последняя
			if ($total_pages > 5 && ($curent_page < $total_pages - 2))
			{
				// Если есть шаблон метки о наличии страниц, добавляем
				if ($pagination_separator_label != '')
				{
					$pagination .= sprintf($pagination_separator_box, $pagination_separator_label);
				}

				$search = ['[link]', '[page]', '[name]'];
				$replace = [$template_label, $total_pages, $pagination_end_label];

				$last = str_replace($search, $replace, $pagination_link_template);

				$pagination .= sprintf($pagination_link_box, str_replace('{t}', $pagination_end_label, str_replace('{s}', $total_pages, $last)));
			}

			// Общий контейнер
			if ($pagination != '')
			{
				// Если пришел внешний контейнер для
				if ($pagination_box_ext != '')
				{
					$pagination = sprintf($pagination_box_ext, $pagination);
				}
				else if ($pagination_box != '')
				{
					$pagination = sprintf($pagination_box, $pagination);
				}
			}

			return $pagination;
		}


		public static function containers ($id)
		{
			$sql = "SELECT * FROM " . SystemTables::table('paginations') . " WHERE id=%i";

			$sql = DB::setTtl(-1)->setCache('paginations', '.paginations')->setTags(array('paginations'))->Query($sql, $id);

			return $sql->getAssoc();
		}


		protected function __clone ()
		{
			//
		}


		protected function __wakeup ()
		{
			//
		}
	}
