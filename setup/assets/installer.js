(function (window, document) {
  'use strict';

  var translations = window.AveSetupI18n || {};
  var tr = function (text) {
    text = String(text == null ? '' : text);
    return Object.prototype.hasOwnProperty.call(translations, text) ? translations[text] : text;
  };

  var packagePanel = document.querySelector('[data-package-extract]');
  if (packagePanel) {
    var packageBusy = false;
    var packageEndpoint = packagePanel.getAttribute('data-endpoint') || './';
    var packageCsrf = packagePanel.getAttribute('data-csrf') || '';
    var packageTitle = packagePanel.querySelector('[data-package-title]');
    var packageDetail = packagePanel.querySelector('[data-package-detail]');
    var packageFile = packagePanel.querySelector('[data-package-file]');
    var packagePercent = packagePanel.querySelector('[data-package-percent]');
    var packageTrack = packagePanel.querySelector('[data-package-track]');
    var packageBar = packagePanel.querySelector('[data-package-bar]');
    var packageError = packagePanel.querySelector('[data-package-error]');
    var packageErrorMessage = packagePanel.querySelector('[data-package-error-message]');
    var packageRetry = packagePanel.querySelector('[data-package-retry]');

    var failPackage = function (message) {
      packageBusy = false;
      packagePanel.classList.add('is-error');
      packageTitle.textContent = tr('Не удалось распаковать систему');
      packageDetail.textContent = tr('Файлы установки не изменяются автоматически после ошибки.');
      packageErrorMessage.textContent = message || tr('Сервер прервал распаковку.');
      packageError.hidden = false;
    };
    var extractPackage = function () {
      if (packageBusy) { return; }
      packageBusy = true;
      packageError.hidden = true;
      var form = new FormData();
      form.append('extract_runtime', '1');
      form.append('_csrf', packageCsrf);
      fetch(packageEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: form })
        .then(function (response) {
          return response.json().catch(function () { throw new Error(tr('Сервер вернул некорректный ответ')); }).then(function (payload) {
            if (!response.ok || !payload.success) { throw new Error(payload.message || tr('Распаковка остановлена')); }
            return payload;
          });
        })
        .then(function (payload) {
          packageBusy = false;
          var value = Math.max(0, Math.min(100, parseInt(payload.progress, 10) || 0));
          packagePercent.textContent = value + '%';
          packageBar.style.width = value + '%';
          packageTrack.setAttribute('aria-valuenow', String(value));
          packageFile.textContent = payload.file || (tr('Файл') + ' ' + payload.current + ' ' + tr('из') + ' ' + payload.total);
          if (payload.complete) {
            packagePanel.classList.add('is-complete');
            packageTitle.textContent = tr('Файлы готовы');
            packageDetail.textContent = tr('Открываем мастер установки AVE.cms.');
            window.setTimeout(function () { window.location.replace(payload.redirect || './'); }, 450);
            return;
          }
          extractPackage();
        })
        .catch(function (error) { failPackage(error.message); });
    };

    if (packageRetry) { packageRetry.addEventListener('click', extractPackage); }
    extractPackage();
    return;
  }

  var form = document.querySelector('[data-installer-form]');
  var panel = document.querySelector('[data-installer-progress]');
  if (!form || !panel || !window.fetch || !window.TextDecoder) { return; }

  var shell = document.querySelector('.installer-shell');
  var intro = document.querySelector('.installer-intro');
  var submit = form.querySelector('[data-installer-submit]');
  var title = panel.querySelector('[data-progress-title]');
  var detail = panel.querySelector('[data-progress-detail]');
  var percent = panel.querySelector('[data-progress-percent]');
  var track = panel.querySelector('[data-progress-track]');
  var bar = panel.querySelector('[data-progress-bar]');
  var error = panel.querySelector('[data-progress-error]');
  var errorMessage = panel.querySelector('[data-progress-error-message]');
  var steps = Array.prototype.slice.call(panel.querySelectorAll('[data-progress-step]'));
	var prefixReset = form.querySelector('[data-prefix-reset]');
	var prefixResetToggle = form.querySelector('[data-prefix-reset-toggle]');
	var prefixResetConfirm = form.querySelector('[data-prefix-reset-confirm]');
	var prefixResetInput = form.querySelector('[data-prefix-reset-input]');
	var prefixResetName = form.querySelector('[data-prefix-reset-name]');
	var prefixResetPattern = form.querySelector('[data-prefix-reset-pattern]');
	var prefixResetDatabase = form.querySelector('[data-prefix-reset-database]');
	var databaseNameInput = form.elements.dbname || null;
	var prefixInput = form.elements.dbpref || null;
	var repositoryOptions = form.querySelector('[data-repository-options]');
	var repositoryProfile = form.querySelector('[data-repository-profile]');
  var completed = false;
	var destructiveReset = false;

	function currentDatabaseName() {
		if (databaseNameInput) { return String(databaseNameInput.value || '').trim(); }
		return prefixReset ? String(prefixReset.getAttribute('data-database-name') || '').trim() : '';
	}

	function currentPrefix() {
		if (prefixInput) { return String(prefixInput.value || '').trim(); }
		return prefixReset ? String(prefixReset.getAttribute('data-prefix') || '').trim() : '';
	}

	function updatePrefixReset() {
		if (!prefixReset || !prefixResetToggle || !prefixResetConfirm || !prefixResetInput) { return; }
		var active = prefixResetToggle.checked;
		var databaseName = currentDatabaseName();
		var prefix = currentPrefix();
		prefixReset.classList.toggle('is-active', active);
		prefixResetConfirm.hidden = !active;
		prefixResetInput.required = active;
		prefixResetInput.placeholder = prefix;
		if (prefixResetName) { prefixResetName.textContent = prefix || tr('префикс'); }
		if (prefixResetPattern) { prefixResetPattern.textContent = (prefix || 'prefix') + '_*'; }
		if (prefixResetDatabase) { prefixResetDatabase.textContent = databaseName || tr('выбранной базе'); }
		if (!active) { prefixResetInput.setCustomValidity(''); }
	}

	if (prefixResetToggle) {
		prefixResetToggle.addEventListener('change', updatePrefixReset);
	}
	if (databaseNameInput) {
		databaseNameInput.addEventListener('input', updatePrefixReset);
	}
	if (prefixInput) {
		prefixInput.addEventListener('input', updatePrefixReset);
	}
	if (prefixResetInput) {
		prefixResetInput.addEventListener('input', function () { this.setCustomValidity(''); });
	}
	updatePrefixReset();

	function updateRepositoryMode() {
		if (!repositoryOptions || !repositoryProfile) { return; }
		var selected = repositoryOptions.querySelector('input[name="repository_mode"]:checked');
		var custom = !!selected && selected.value === 'custom';
		repositoryProfile.hidden = !custom;
		var textarea = repositoryProfile.querySelector('textarea');
		if (textarea) { textarea.required = custom; }
		Array.prototype.forEach.call(repositoryOptions.querySelectorAll('.installer-repository-option'), function (option) {
			var input = option.querySelector('input[name="repository_mode"]');
			option.classList.toggle('is-selected', !!input && input.checked);
		});
	}

	if (repositoryOptions) {
		repositoryOptions.addEventListener('change', updateRepositoryMode);
	}
	updateRepositoryMode();

  function setProgress(value, heading, description) {
    value = Math.max(0, Math.min(100, parseInt(value, 10) || 0));
    title.textContent = heading || tr('Установка AVE.cms');
    detail.textContent = description || '';
    percent.textContent = value + '%';
    bar.style.width = value + '%';
    track.setAttribute('aria-valuenow', String(value));
    steps.forEach(function (step, index) {
      var threshold = parseInt(step.getAttribute('data-progress-step'), 10) || 0;
      var next = steps[index + 1];
      var nextThreshold = next ? parseInt(next.getAttribute('data-progress-step'), 10) : 101;
      step.classList.toggle('is-done', value >= nextThreshold || value === 100);
      step.classList.toggle('is-active', value >= threshold && value < nextThreshold && value < 100);
    });
  }

  function setBusy(busy) {
    Array.prototype.forEach.call(form.elements, function (field) { field.disabled = busy; });
    if (submit) { submit.disabled = busy; }
  }

  function setFormVisible(visible) {
    form.hidden = !visible;
    form.style.display = visible ? '' : 'none';
    if (intro) {
      intro.hidden = !visible;
      intro.style.display = visible ? '' : 'none';
    }
  }

  function showProgress() {
    completed = false;
    error.hidden = true;
    panel.classList.remove('is-error');
    panel.hidden = false;
    shell.classList.add('is-installing');
    setFormVisible(false);
    setBusy(true);
    setProgress(3, tr('Запускаем установку'), tr('Передаём параметры и готовим проверку окружения'));
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function showError(message) {
    panel.classList.add('is-error');
    title.textContent = tr('Не удалось завершить установку');
		detail.textContent = destructiveReset
			? tr('Была выбрана очистка префикса — проверьте его таблицы перед повтором')
			: tr('Изменения незавершённой чистой установки отменены');
    errorMessage.textContent = message || tr('Сервер прервал установку без сообщения.');
    error.hidden = false;
    setBusy(false);
  }

  function showComplete(payload) {
    completed = true;
    var template = document.querySelector('[data-installer-complete-template]');
    if (!template) { window.location.reload(); return; }
    var content = template.content.cloneNode(true);
    var admin = content.querySelector('[data-complete-admin]');
    var site = content.querySelector('[data-complete-site]');
	var repository = content.querySelector('[data-complete-repository]');
    var adminDirectory = form.elements.admin_directory ? form.elements.admin_directory.value : 'adminx';
    if (admin) { admin.href = payload.admin_url || '../' + encodeURIComponent(adminDirectory) + '/login'; }
    if (site) { site.href = payload.site_url || '../'; }
	if (repository) {
	  var summary = payload.summary || {};
	  repository.textContent = summary.repository_mode === 'disabled'
		? tr('Удалённые обновления и каталог модулей не подключены.')
		: tr('Подключён источник:') + ' ' + (summary.repository_name || tr('выбранный профиль')) + '. '
			+ tr('Проверка выполняется после входа в панель.');
	}
    setFormVisible(false);
    panel.replaceWith(content);
    shell.classList.remove('is-installing');
  }

  function consume(payload) {
    if (!payload || !payload.type) { return; }
    if (payload.type === 'progress') {
      setProgress(payload.percent, payload.title, payload.detail);
    } else if (payload.type === 'complete') {
      showComplete(payload);
    } else if (payload.type === 'error') {
      showError(payload.message);
    }
  }

  function consumeLine(line) {
    line = String(line || '').trim();
    if (!line) { return; }
    try { consume(JSON.parse(line)); }
    catch (e) { showError(tr('Установщик вернул некорректный ответ.')); }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
		updatePrefixReset();
		destructiveReset = !!(prefixResetToggle && prefixResetToggle.checked);
		if (destructiveReset && prefixResetInput) {
			var expectedPrefix = currentPrefix();
			if (String(prefixResetInput.value || '').trim() !== expectedPrefix) {
				prefixResetInput.setCustomValidity(tr('Введите точный префикс:') + ' ' + expectedPrefix);
			}
		}
		if (!form.reportValidity()) { return; }
		if (destructiveReset && !window.confirm(
			tr('Все таблицы и представления') + ' «' + currentPrefix() + '_*» '
				+ tr('в базе') + ' «' + currentDatabaseName() + '» '
				+ tr('будут удалены безвозвратно. Продолжить?')
		)) { return; }

		var formData = new FormData(form);
    showProgress();

    fetch(window.location.href, {
      method: 'POST',
	  body: formData,
      headers: { 'Accept': 'application/x-ndjson', 'X-Setup-Progress': '1' },
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.body || !response.body.getReader) {
        return response.text().then(function (body) {
          body.split(/\r?\n/).forEach(consumeLine);
        });
      }

      var reader = response.body.getReader();
      var decoder = new TextDecoder('utf-8');
      var buffer = '';
      function read() {
        return reader.read().then(function (result) {
          buffer += decoder.decode(result.value || new Uint8Array(), { stream: !result.done });
          var lines = buffer.split(/\r?\n/);
          buffer = lines.pop() || '';
          lines.forEach(consumeLine);
          if (!result.done) { return read(); }
          consumeLine(buffer);
        });
      }
      return read();
    }).then(function () {
      if (!completed && error.hidden) { showError(tr('Соединение завершилось до окончания установки.')); }
    }).catch(function () {
      showError(tr('Не удалось получить ответ установщика. Проверьте соединение и повторите попытку.'));
    });
  });

  panel.querySelector('[data-progress-back]').addEventListener('click', function () {
    shell.classList.remove('is-installing');
    panel.hidden = true;
    panel.classList.remove('is-error');
    error.hidden = true;
    setBusy(false);
    setFormVisible(true);
  });
}(window, document));
