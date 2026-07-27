/*
|--------------------------------------------------------------------------------------
| AVE.cms — модуль «Регистрационные удостоверения» (adminx)
|--------------------------------------------------------------------------------------
| Adminx.Registrations: сабмит формы, «бессрочно», удаление — единый Ajax/Toast/Confirm.
*/
(function () {
  'use strict';

  if (typeof window.Adminx === 'undefined') { return; }
  var Adminx = window.Adminx;

  Adminx.Registrations = {
    init: function () {
      this.bindPerpetual();
      this.bindForm();
      this.bindDelete();
    },

    bindPerpetual: function () {
      var toggle = document.querySelector('[data-reg-perpetual]');
      var valid = document.querySelector('[data-reg-valid]');
      if (!toggle || !valid) { return; }
      var sync = function () { valid.disabled = toggle.checked; if (toggle.checked) { valid.value = ''; } };
      toggle.addEventListener('change', sync);
      sync();
    },

    bindForm: function () {
      var form = document.querySelector('[data-reg-form]');
      if (!form) { return; }

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var submit = form.querySelector('[type="submit"]');
        if (submit) { submit.disabled = true; }

        Adminx.Ajax.post(form.getAttribute('data-action'), new FormData(form)).then(function (res) {
          var body = res.data || {};
          if (res.ok && body.success) {
            Adminx.Toast.show(body.message || 'Сохранено', 'success');
            var redirect = form.getAttribute('data-redirect') || (body.data && body.data.redirect);
            if (redirect) {
              window.setTimeout(function () { window.location.href = redirect; }, 350);
            }
            return;
          }

          Adminx.Toast.show(body.message || 'Не удалось сохранить', 'error');
        }).catch(function () {
          Adminx.Toast.show('Сервер недоступен. Повторите попытку.', 'error');
        }).then(function () {
          if (submit) { submit.disabled = false; }
        });
      });
    },

    bindDelete: function () {
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-reg-delete]');
        if (!trigger) { return; }
        event.preventDefault();

        var id = trigger.getAttribute('data-reg-delete');
        Adminx.Confirm.open({
          kind: 'error',
          title: 'Удалить удостоверение?',
          message: 'Запись будет удалена из реестра без возможности восстановления.',
          confirmLabel: 'Удалить',
          confirmClass: 'btn-danger',
          onConfirm: function () {
            Adminx.Ajax.post(Adminx.base() + '/registrations/' + id + '/delete', new FormData()).then(function (res) {
              var body = res.data || {};
              if (res.ok && body.success) {
                var row = document.querySelector('tr[data-id="' + id + '"]');
                if (row) { row.remove(); }
                Adminx.Toast.show(body.message || 'Удалено', 'success');
                return;
              }

              Adminx.Toast.show(body.message || 'Не удалось удалить', 'error');
            }).catch(function () {
              Adminx.Toast.show('Сервер недоступен. Повторите попытку.', 'error');
            });
          }
        });
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Registrations.init(); });
  } else {
    Adminx.Registrations.init();
  }
})();
