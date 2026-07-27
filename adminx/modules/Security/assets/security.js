(function (window, document) {
  'use strict';
  var Adminx = window.Adminx;
  function post(url, data) {
    Adminx.Loader.show();
    return Adminx.Ajax.post(url, data).then(function (payload) { Adminx.Loader.hide(); Adminx.Ajax.handle(payload); }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
  }
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-ip-block-form]');
    if (!form) { return; }
    event.preventDefault(); post(form.action, new FormData(form));
  });
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-ip-unblock]');
    if (!button) { return; }
    Adminx.Confirm.open({ kind: 'error', title: 'Разблокировать IP?', message: 'Доступ с этого адреса будет восстановлен сразу.', confirmLabel: 'Разблокировать', onConfirm: function () { post(Adminx.base() + '/security/ip-blocks/' + button.dataset.ipUnblock + '/delete'); } });
  });
})(window, document);
