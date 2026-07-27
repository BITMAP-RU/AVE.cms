<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Template.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	/**
	* Класс Template отвечает за работу с шаблонами в приложении.
	* Использует паттерн адаптер для поддержки различных движков шаблонов.
	*/
	class Template {
	/** @var object $adaptor Адаптер для работы с конкретным движком шаблонов */
	private $adaptor;

	/**
	 * Конструктор класса Template
	 * Инициализирует адаптер для работы с движком шаблонов
	 *
	 * @param	string	$adaptor Название адаптера для движка шаблонов
	 * @throws \Exception Если не удалось загрузить указанный адаптер
	 */
	public function __construct($adaptor) {
		$class = 'Template\\' . $adaptor;

		if (class_exists($class)) {
			$this->adaptor = new $class();
		} else {
			throw new \Exception('Error: Could not load template adaptor ' . $adaptor . '!');
		}
	}

	/**
	 * Устанавливает значение переменной для шаблона
	 * Этот метод позволяет передавать данные в шаблон для отображения
	 *
	 * @param	string	$key Ключ переменной
	 * @param	mixed	$value Значение переменной
	 * @return	void
	 */
	public function set($key, $value) {
		$this->adaptor->set($key, $value);
	}

	/**
	 * Отображает шаблон с переданными данными
	 * Основной метод для генерации HTML-кода на основе шаблона
	 *
	 * @param	string	$template Путь к файлу шаблона
	 * @param	bool	$cache Флаг использования кэширования
	 * @return	string Сгенерированный HTML-код шаблона
	 */
	public function render($template, $cache = false) {
		return $this->adaptor->render($template, $cache);
	}
	}
