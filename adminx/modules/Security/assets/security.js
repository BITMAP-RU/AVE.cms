(function (window, document) {
  'use strict';
  var Adminx = window.Adminx;
  function post(url, data) {
    Adminx.Loader.show();
    return Adminx.Ajax.post(url, data).then(function (payload) { Adminx.Loader.hide(); Adminx.Ajax.handle(payload); }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
  }
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-ip-block-form], [data-user-agent-block-form]');
    if (!form) { return; }
    event.preventDefault(); post(form.action, new FormData(form));
  });
  document.addEventListener('click', function (event) {
	var button = event.target.closest('[data-ip-unblock], [data-user-agent-unblock]');
	if (!button) { return; }
	var isAgent = button.hasAttribute('data-user-agent-unblock');
	var id = isAgent ? button.dataset.userAgentUnblock : button.dataset.ipUnblock;
	Adminx.Confirm.open({
	  kind: 'error',
	  title: isAgent ? 'Разблокировать User-Agent?' : 'Разблокировать IP?',
	  message: 'Доступ для этого правила будет восстановлен сразу.',
	  confirmLabel: 'Разблокировать',
	  onConfirm: function () { post(Adminx.base() + (isAgent ? '/security/user-agent-blocks/' : '/security/ip-blocks/') + id + '/delete'); }
	});
  });
})(window, document);
