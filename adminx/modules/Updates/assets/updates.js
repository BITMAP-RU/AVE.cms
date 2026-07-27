/** Signed core update catalog and resumable installation flow. */
(function (window, document) {
  'use strict';
  var Adminx = window.Adminx || (window.Adminx = {});
  var page = document.querySelector('[data-updates-page]');
  if (!page) { return; }

  function base() { return Adminx.base() + '/system/updates'; }
  function post(url, data) {
    Adminx.Loader.show();
    return Adminx.Ajax.post(url, data || new FormData()).then(function (payload) {
      Adminx.Loader.hide();
      if (!payload.data || !payload.data.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Операция не выполнена'); }
      return payload.data;
    }).catch(function (error) { Adminx.Loader.hide(); Adminx.Toast.show(error.message || 'Ошибка обновления', 'error'); throw error; });
  }
  function reload(tab) { window.setTimeout(function () { window.location.href = base() + (tab ? '?tab=' + tab : ''); }, 350); }
	function rememberRecovery(token) {
	  if (!token) { return; }
	  try {
		var adminBase = Adminx.base();
		var publicBase = adminBase.substring(0, adminBase.lastIndexOf('/'));
		window.localStorage.setItem('ave-core-update-recovery', window.location.origin + publicBase + '/recovery.php?token=' + token);
	  } catch (error) {}
	}
  function renderJob(job) {
    page.dataset.jobId = job.id; page.dataset.jobStatus = job.status;
    var status = document.querySelector('[data-update-job-status]');
    var message = document.querySelector('[data-update-job-message]');
    var progress = document.querySelector('[data-update-progress]');
    if (status) { status.textContent = job.status; }
    if (message) { message.textContent = job.message || ''; }
    if (progress) { progress.style.width = Number(job.progress || 0) + '%'; }
	if (job.status === 'completed' || job.status === 'rolled_back') {
	  try { window.localStorage.removeItem('ave-core-update-recovery'); } catch (error) {}
	}
	rememberRecovery(job.recovery_token);
  }
  function runStep() {
    var id = page.dataset.jobId || '';
    if (!id) { return; }
    post(base() + '/jobs/' + encodeURIComponent(id) + '/step', new FormData()).then(function (result) {
      var job = result.data.job; renderJob(job); Adminx.Toast.show(job.message || 'Этап выполнен', 'success');
      if (job.status === 'backing_up' || job.status === 'backed_up' || job.status === 'applying' || job.status === 'applied') { window.setTimeout(runStep, 450); return; }
      reload('job');
    }).catch(function () { reload('job'); });
  }

  document.addEventListener('click', function (event) {
    var upload = event.target.closest('[data-update-upload-open]');
    if (upload) { document.querySelector('[data-update-file]').click(); return; }
    var refresh = event.target.closest('[data-update-refresh]');
    if (refresh) { post(base() + '/refresh', new FormData()).then(function () { reload('updates'); }); return; }
	var official = event.target.closest('[data-update-official]');
	if (official) { Adminx.Confirm.open({ kind: 'warning', title: 'Восстановить официальный источник?', message: 'Текущий URL и публичный ключ обновлений будут заменены данными из доверенного профиля сборки.', confirmLabel: 'Восстановить', confirmClass: 'btn-primary', onConfirm: function () { post(base() + '/settings/official', new FormData()).then(function () { Adminx.Toast.show('Официальный источник восстановлен', 'success'); reload('settings'); }); } }); return; }
    var download = event.target.closest('[data-update-download]');
    if (download) { Adminx.Confirm.open({ kind: 'warning', title: 'Подготовить обновление?', message: 'Патч будет загружен и проверен. Файлы сайта пока не изменятся.', confirmLabel: 'Подготовить', confirmClass: 'btn-primary', onConfirm: function () { post(base() + '/download/' + encodeURIComponent(download.dataset.updateDownload), new FormData()).then(function () { reload('job'); }); } }); return; }
    var start = event.target.closest('[data-update-continue]');
    if (start) { Adminx.Confirm.open({ kind: 'warning', title: 'Установить обновление ядра?', message: 'AVE.cms создаст резервную копию, временно закроет публичный сайт и заменит системные файлы.', confirmLabel: 'Установить', confirmClass: 'btn-primary', onConfirm: runStep }); return; }
    var rollback = event.target.closest('[data-update-rollback]');
    if (rollback) { Adminx.Confirm.open({ kind: 'error', title: 'Откатить обновление?', message: 'Файлы и резервная копия БД будут восстановлены из journal этого задания.', confirmLabel: 'Откатить', confirmClass: 'btn-danger', onConfirm: function () { post(base() + '/jobs/' + encodeURIComponent(page.dataset.jobId) + '/rollback', new FormData()).then(function () { reload('job'); }); } }); }
	var discard = event.target.closest('[data-update-discard]');
	if (discard) { Adminx.Confirm.open({ kind: 'warning', title: 'Отменить подготовку?', message: 'Загруженный ZIP и staging будут удалены. Файлы сайта ещё не изменялись.', confirmLabel: 'Отменить патч', confirmClass: 'btn-danger', onConfirm: function () { post(base() + '/jobs/' + encodeURIComponent(page.dataset.jobId) + '/discard', new FormData()).then(function () { reload('job'); }); } }); }
  });
  var file = document.querySelector('[data-update-file]');
  if (file) { file.addEventListener('change', function () { if (!file.files.length) { return; } var data = new FormData(); data.append('patch', file.files[0]); post(base() + '/upload', data).then(function () { reload('job'); }); }); }
  var settings = document.querySelector('[data-update-settings]');
  if (settings) { settings.addEventListener('submit', function (event) { event.preventDefault(); post(base() + '/settings', new FormData(settings)).then(function () { Adminx.Toast.show('Источник обновлений сохранён', 'success'); reload('settings'); }); }); }
  var tab = new URLSearchParams(window.location.search).get('tab');
	rememberRecovery(page.dataset.recoveryToken);
  if (tab && Adminx.Tabs) { Adminx.Tabs.activate(page, tab); }
})(window, document);
