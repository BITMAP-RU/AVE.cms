/**
 * JS раздела Dashboard. Подключается точечно (только на страницах модуля)
 * через AdminAssets::addScript() из контроллера. Объявляет свой объект в
 * неймспейсе Adminx (ТЗ §4).
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Dashboard = {
    init: function () {
      // Лёгкая анимация счётчиков stat-карточек.
      var nodes = document.querySelectorAll('[data-count]');
      nodes.forEach(function (node) {
        var target = parseInt((node.textContent || '').replace(/\D/g, ''), 10);
        if (!target || target < 1) { return; }
        var start = 0;
        var step = Math.max(1, Math.round(target / 24));
        var timer = setInterval(function () {
          start += step;
          if (start >= target) { start = target; clearInterval(timer); }
          node.textContent = start.toLocaleString('ru-RU');
        }, 24);
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Adminx.Dashboard.init);
  } else {
    Adminx.Dashboard.init();
  }
})(window, document);
