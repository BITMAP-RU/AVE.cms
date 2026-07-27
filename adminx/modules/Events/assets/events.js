/**
 * JS раздела «Системные события»: раскрытие деталей и очистка legacy-логов.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Events = {
    init: function () {
      var self = this;
      document.addEventListener('click', function (e) {
        var details = e.target.closest('[data-events-details]');
        if (details) {
          self.toggleDetails(details);
          return;
        }

        var clear = e.target.closest('[data-events-clear]');
        if (clear) {
          self.clear(clear.getAttribute('data-events-clear'));
        }
      });
    },

    base: function () {
      return Adminx.base();
    },

    toggleDetails: function (button) {
      var row = button.closest('tr');
      var details = row ? row.nextElementSibling : null;
      if (!details || !details.classList.contains('events-details-row')) { return; }
      var hidden = details.hasAttribute('hidden');
      if (hidden) {
        details.removeAttribute('hidden');
        button.classList.add('active');
      } else {
        details.setAttribute('hidden', 'hidden');
        button.classList.remove('active');
      }
    },

    clear: function (source) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Очистить журнал?',
		message: 'Все записи выбранного журнала будут удалены. В аудите останется только запись о самой очистке.',
        confirmLabel: 'Очистить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/events/' + encodeURIComponent(source) + '/clear').then(function (payload) {
            Adminx.Loader.hide();
            Adminx.Ajax.handle(payload);
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Events.init(); });
  } else {
    Adminx.Events.init();
  }
})(window, document);
