/** Реестр модулей: клиентские фильтры и Ajax lifecycle-действия. */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Modules = {
    init: function () {
      if (!document.querySelector('[data-modules-page]')) { return; }
      var self = this;
      var page = document.querySelector('[data-modules-page]');
      var search = document.querySelector('[data-module-search]');
      var state = document.querySelector('[data-module-state]');
      var sort = document.querySelector('[data-module-sort]');
      var repositorySort = document.querySelector('[data-repository-sort]');
      if (search) { search.addEventListener('input', function () { self.filter(); }); }
      if (state) { state.addEventListener('change', function () { self.filter(); }); }
      if (sort) { sort.addEventListener('change', function () { self.sortRows('[data-module-row]', sort.value); }); }
      if (repositorySort) { repositorySort.addEventListener('change', function () { self.sortRows('[data-repository-row]', repositorySort.value); }); }

      document.addEventListener('change', function (event) {
        var toggle = event.target.closest('[data-module-toggle]');
        if (toggle) { self.toggle(toggle); return; }
      });
      document.addEventListener('click', function (event) {
        var refresh = event.target.closest('[data-module-repository-refresh]');
        if (refresh) { self.refreshRepository(refresh); return; }
		var official = event.target.closest('[data-module-repository-official]');
		if (official) { self.restoreOfficialRepository(official); return; }
		var remoteInstall = event.target.closest('[data-module-repository-install]');
		if (remoteInstall) { self.installRepository(remoteInstall); return; }
		var repositoryMigration = event.target.closest('[data-module-repository-migrate]');
		if (repositoryMigration) { self.updateRepositoryMigration(repositoryMigration); return; }
        var presentation = event.target.closest('[data-module-presentation]');
        if (presentation) { self.openPresentation(presentation); return; }
        var button = event.target.closest('[data-module-action]');
        if (button) { self.action(button); }
      });
      var presentationForm = document.querySelector('[data-module-presentation-form]');
      if (presentationForm) {
        presentationForm.addEventListener('submit', function (event) { self.savePresentation(event); });
      }
      var repositoryForm = document.querySelector('[data-module-repository-form]');
      if (repositoryForm) {
        repositoryForm.addEventListener('submit', function (event) { self.saveRepository(event); });
      }
      if (new URLSearchParams(window.location.search).get('tab') === 'catalog' && Adminx.Tabs) {
        Adminx.Tabs.activate(page, 'catalog');
      }
      this.initArchive();
    },

    base: function () { return Adminx.base(); },

    filter: function () {
      var query = (document.querySelector('[data-module-search]').value || '').trim().toLowerCase();
      var state = document.querySelector('[data-module-state]').value;
      var shown = 0;
      document.querySelectorAll('[data-module-row]').forEach(function (row) {
        var matchQuery = !query || row.dataset.search.indexOf(query) !== -1;
        var matchState = state === 'all' || row.dataset.state === state;
        row.hidden = !(matchQuery && matchState);
        if (!row.hidden) { shown++; }
      });
      var empty = document.querySelector('[data-module-empty]');
      if (empty) { empty.hidden = shown !== 0; }
    },

    sortRows: function (selector, direction) {
      var rows = Array.prototype.slice.call(document.querySelectorAll(selector));
      if (rows.length < 2) { return; }
      var body = rows[0].parentNode;
      var empty = body.querySelector('[data-module-empty]');
      rows.sort(function (left, right) {
        var result = (left.dataset.name || '').localeCompare(right.dataset.name || '', 'ru', { numeric: true, sensitivity: 'base' });
        return direction === 'desc' ? -result : result;
      });
      rows.forEach(function (row) { empty ? body.insertBefore(row, empty) : body.appendChild(row); });
    },

    post: function (url, formData) {
      Adminx.Loader.show();
      return Adminx.Ajax.post(url, formData || new FormData()).then(function (payload) {
        Adminx.Loader.hide();
        return payload.data || {};
      }).catch(function (error) {
        Adminx.Loader.hide();
        Adminx.Toast.show(error && error.message ? error.message : 'Ошибка запроса', 'error');
        return { success: false };
      });
    },

    toggle: function (input) {
      var self = this;
      var row = input.closest('[data-module-row]');
      var enabled = input.checked;
      input.disabled = true;
      var data = new FormData();
      data.append('enabled', enabled ? '1' : '0');
      this.post(this.base() + '/modules/lifecycle/' + encodeURIComponent(row.dataset.code) + '/toggle', data).then(function (result) {
        input.disabled = false;
        if (!result.success) {
          input.checked = !enabled;
          Adminx.Toast.show(result.message || 'Не удалось изменить состояние модуля', 'error');
          return;
        }
        Adminx.Toast.show(result.message || (enabled ? 'Модуль включён' : 'Модуль отключён'), 'success');
        self.reloadSoon();
      });
    },

    action: function (button) {
      var self = this;
      var row = button.closest('[data-module-row]');
      var code = row.dataset.code;
      var action = button.dataset.moduleAction;
      var config = {
        install: { title: 'Установить модуль?', message: 'Будут выполнены SQL-команды установки и модуль будет включён.', label: 'Установить', kind: 'info' },
		update: { title: 'Обновить модуль?', message: 'Будут выполнены SQL-команды обновления до версии из файлов.', label: 'Обновить', kind: 'warning' },
        repair: { title: 'Восстановить операцию модуля?', message: 'Успешные миграции будут пропущены, а оборванный этап выполнится повторно. Перед продолжением проверьте указанную ошибку.', label: 'Восстановить', kind: 'warning' },
        reinstall: { title: 'Переустановить модуль?', message: 'Данные и настройки будут удалены, затем модуль установится заново. Это действие необратимо.', label: 'Переустановить', kind: 'error' },
        uninstall: { title: 'Деинсталлировать модуль?', message: 'Модуль, его данные, настройки, меню и права будут удалены. Это действие необратимо.', label: 'Деинсталлировать', kind: 'error' },
        remove: { title: 'Удалить файлы модуля?', message: 'Каталог пакета будет физически удалён. Для повторной установки понадобится ZIP-архив.', label: 'Удалить файлы', kind: 'error' }
      }[action];
      if (!config) { return; }

	  if (action === 'uninstall' || action === 'reinstall') {
		button.disabled = true;
		this.post(this.base() + '/modules/lifecycle/' + encodeURIComponent(code) + '/preflight/' + action, new FormData()).then(function (result) {
		  button.disabled = false;
		  if (!result.success) { return; }
		  var preflight = result.data || {};
		  self.confirmAction(button, code, action, config, preflight);
		});
		return;
	  }

	  this.confirmAction(button, code, action, config, null);
    },

	confirmAction: function (button, code, action, config, preflight) {
	  var self = this;
	  var message = this.preflightMessage(config.message, preflight && preflight.preview ? preflight.preview : {});
      Adminx.Confirm.open({
        kind: config.kind,
        title: config.title,
		message: message,
        confirmLabel: config.label,
        confirmClass: config.kind === 'error' ? 'btn-danger' : 'btn-primary',
        onConfirm: function () {
          button.disabled = true;
		  var data = new FormData();
		  if (preflight && preflight.required && preflight.token) {
			data.append('preflight_token', preflight.token);
		  }
		  self.post(self.base() + '/modules/lifecycle/' + encodeURIComponent(code) + '/' + action, data).then(function (result) {
            button.disabled = false;
            Adminx.Toast.show(result.message || (result.success ? 'Готово' : 'Операция не выполнена'), result.success ? 'success' : 'error');
            if (result.success) { self.reloadSoon(); }
          });
        }
      });
    },

	preflightMessage: function (fallback, preview) {
	  var parts = [];
	  if (preview && preview.message) { parts.push(String(preview.message)); }
	  var summary = preview && Array.isArray(preview.summary) ? preview.summary : [];
	  summary.forEach(function (item) {
		if (!item || !item.label) { return; }
		parts.push(String(item.label) + ': ' + String(item.count == null ? 0 : item.count));
	  });
	  return parts.length ? parts.join('. ') + '.' : fallback;
	},

	updateRepositoryMigration: function (button) {
	  var self = this;
	  var row = button.closest('[data-repository-row]');
	  if (!row || button.disabled) { return; }
	  var code = row.dataset.code || '';
	  var name = button.dataset.moduleName || code;
	  Adminx.Confirm.open({
		kind: 'warning',
		title: 'Применить миграции «' + name + '»?',
		message: 'Файлы модуля уже актуальны. AVE.cms выполнит ещё не применённые миграции и синхронизирует версию БД.',
		confirmLabel: 'Применить',
		confirmClass: 'btn-primary',
		onConfirm: function () {
		  button.disabled = true;
		  button.classList.add('is-loading');
		  self.post(self.base() + '/modules/lifecycle/' + encodeURIComponent(code) + '/update', new FormData()).then(function (result) {
			button.disabled = false;
			button.classList.remove('is-loading');
			Adminx.Toast.show(result.message || (result.success ? 'Миграции применены' : 'Не удалось применить миграции'), result.success ? 'success' : 'error');
			if (result.success) { window.setTimeout(function () { window.location.href = self.base() + '/modules?tab=catalog'; }, 350); }
		  });
		}
	  });
	},

    openPresentation: function (button) {
      var row = button.closest('[data-module-row]');
      var drawer = document.getElementById('modulePresentationDrawer');
      if (!row || !drawer) { return; }
      var form = drawer.querySelector('[data-module-presentation-form]');
      form.dataset.code = row.dataset.code || '';
      drawer.querySelector('[data-module-presentation-title]').textContent = button.dataset.moduleName || 'Размещение модуля';
      drawer.querySelector('[data-module-presentation-code]').textContent = row.dataset.code || '';

      var headerOption = drawer.querySelector('[data-module-header-option]');
      var dashboardOption = drawer.querySelector('[data-module-dashboard-option]');
      headerOption.hidden = button.dataset.hasHeader !== '1';
      dashboardOption.hidden = button.dataset.hasDashboard !== '1';
      headerOption.querySelector('input').checked = button.dataset.headerEnabled === '1';
      dashboardOption.querySelector('input').checked = button.dataset.dashboardEnabled === '1';
      Adminx.Drawer.open('modulePresentationDrawer');
    },

    savePresentation: function (event) {
      event.preventDefault();
      var self = this;
      var form = event.currentTarget;
      var code = form.dataset.code || '';
      if (!code) { return; }
      var submit = form.querySelector('[type="submit"]');
      var data = new FormData(form);
      data.set('header_enabled', form.elements.header_enabled.checked ? '1' : '0');
      data.set('dashboard_enabled', form.elements.dashboard_enabled.checked ? '1' : '0');
      submit.disabled = true;
      this.post(this.base() + '/modules/lifecycle/' + encodeURIComponent(code) + '/presentation', data).then(function (result) {
        submit.disabled = false;
        if (!result.success) {
          Adminx.Toast.show(result.message || 'Не удалось сохранить размещение', 'error');
          return;
        }
        Adminx.Toast.show(result.message || 'Размещение сохранено', 'success');
        Adminx.Drawer.close('modulePresentationDrawer');
        self.reloadSoon();
      });
    },

    saveRepository: function (event) {
      event.preventDefault();
      var self = this;
      var form = event.currentTarget;
      var submit = form.querySelector('[type="submit"]');
      var data = new FormData(form);
      data.set('enabled', form.elements.enabled.checked ? '1' : '0');
      submit.disabled = true;
      this.post(this.base() + '/modules/repository/settings', data).then(function (result) {
        submit.disabled = false;
        if (!result.success) {
          Adminx.Toast.show(result.message || 'Не удалось сохранить источник', 'error');
          return;
        }
        Adminx.Toast.show(result.message || 'Источник модулей сохранён', 'success');
        Adminx.Drawer.close('moduleRepositoryDrawer');
        self.reloadSoon();
      });
    },

	restoreOfficialRepository: function (button) {
	  var self = this;
	  Adminx.Confirm.open({
		kind: 'warning',
		title: 'Восстановить официальный каталог?',
		message: 'Текущий URL и публичный ключ модулей будут заменены данными из доверенного профиля сборки.',
		confirmLabel: 'Восстановить',
		confirmClass: 'btn-primary',
		onConfirm: function () {
		  button.disabled = true;
		  self.post(self.base() + '/modules/repository/settings/official', new FormData()).then(function (result) {
			button.disabled = false;
			Adminx.Toast.show(result.message || (result.success ? 'Официальный каталог восстановлен' : 'Не удалось восстановить каталог'), result.success ? 'success' : 'error');
			if (result.success) { self.reloadSoon(); }
		  });
		}
	  });
	},

    refreshRepository: function (button) {
      var self = this;
      if (button.disabled) { return; }
      button.disabled = true;
      button.classList.add('is-loading');
      this.post(this.base() + '/modules/repository/refresh', new FormData()).then(function (result) {
        button.disabled = false;
        button.classList.remove('is-loading');
        if (!result.success) {
          Adminx.Toast.show(result.message || 'Не удалось обновить каталог', 'error');
          return;
        }
        Adminx.Toast.show(result.message || 'Каталог обновлён', 'success');
        self.reloadSoon();
      });
    },

    installRepository: function (button) {
      var self = this;
      var row = button.closest('[data-repository-row]');
      if (!row || button.disabled) { return; }
      var code = row.dataset.code || '';
      var name = button.dataset.moduleName || code;
      var updating = button.dataset.moduleOperation === 'update';
      Adminx.Confirm.open({
        kind: updating ? 'warning' : 'info',
        title: (updating ? 'Обновить «' : 'Установить «') + name + '»?',
        message: updating
          ? 'AVE.cms скачает подписанный ZIP, проверит контрольные суммы и безопасно заменит файлы модуля. Если новой версии нужны миграции, они появятся отдельным следующим действием.'
          : 'AVE.cms скачает подписанный ZIP, проверит контрольные суммы, добавит файлы и выполнит миграции модуля.',
        confirmLabel: updating ? 'Обновить файлы' : 'Установить',
        confirmClass: 'btn-primary',
        onConfirm: function () {
          button.disabled = true;
          button.classList.add('is-loading');
          self.post(self.base() + '/modules/repository/' + encodeURIComponent(code) + '/install', new FormData()).then(function (result) {
            button.disabled = false;
            button.classList.remove('is-loading');
            if (!result.success) {
              Adminx.Toast.show(result.message || (updating ? 'Не удалось обновить модуль' : 'Не удалось установить модуль'), 'error');
              return;
            }
            Adminx.Toast.show(result.message || (updating ? 'Файлы модуля обновлены' : 'Модуль установлен'), 'success');
            window.setTimeout(function () { window.location.href = result.redirect || (Adminx.base() + '/modules'); }, 350);
          });
        }
      });
    },

    initArchive: function () {
      var self = this;
      var form = document.querySelector('[data-module-archive-form]');
      if (!form) { return; }
      var drop = form.querySelector('[data-module-archive-drop]');
      var input = form.querySelector('[data-module-archive-input]');
      var dragDepth = 0;

      input.addEventListener('change', function () { self.selectArchive(form, input.files && input.files[0]); });
      drop.addEventListener('dragenter', function (event) {
        event.preventDefault();
        dragDepth++;
        drop.classList.add('is-dragging');
      });
      drop.addEventListener('dragover', function (event) { event.preventDefault(); });
      drop.addEventListener('dragleave', function (event) {
        event.preventDefault();
        dragDepth = Math.max(0, dragDepth - 1);
        if (!dragDepth) { drop.classList.remove('is-dragging'); }
      });
      drop.addEventListener('drop', function (event) {
        event.preventDefault();
        dragDepth = 0;
        drop.classList.remove('is-dragging');
        self.selectArchive(form, event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null);
      });
      form.addEventListener('submit', function (event) { self.installArchive(event); });
    },

    selectArchive: function (form, file) {
      var title = form.querySelector('[data-module-archive-title]');
      var meta = form.querySelector('[data-module-archive-meta]');
      var submit = form.querySelector('[data-module-archive-submit]');
      form._moduleArchive = null;
      if (!file) {
        title.textContent = 'Перетащите ZIP сюда';
        meta.textContent = 'или нажмите, чтобы выбрать файл до 25 МБ';
        submit.disabled = true;
        return;
      }
      if (!/\.zip$/i.test(file.name || '')) {
        Adminx.Toast.show('Выберите ZIP-архив модуля', 'error');
        this.selectArchive(form, null);
        return;
      }
      if (file.size > 25 * 1024 * 1024) {
        Adminx.Toast.show('ZIP-архив превышает 25 МБ', 'error');
        this.selectArchive(form, null);
        return;
      }
      form._moduleArchive = file;
      title.textContent = file.name;
      meta.textContent = this.fileSize(file.size) + ' · готов к проверке и установке';
      submit.disabled = false;
    },

    installArchive: function (event) {
      event.preventDefault();
      var form = event.currentTarget;
      if (form.getAttribute('aria-busy') === 'true') { return; }
      var file = form._moduleArchive || (form.elements.module_archive.files && form.elements.module_archive.files[0]);
      if (!file) { this.selectArchive(form, null); return; }
      var submit = form.querySelector('[data-module-archive-submit]');
      var progress = form.querySelector('[data-module-archive-progress]');
      var progressTitle = form.querySelector('[data-module-archive-progress-title]');
      var data = new FormData();
      data.append('module_archive', file, file.name);
      form.setAttribute('aria-busy', 'true');
      form.classList.add('is-installing');
      submit.disabled = true;
      submit.classList.add('is-loading');
      if (progress) { progress.hidden = false; }
      if (progressTitle) { progressTitle.textContent = 'Устанавливаем ' + file.name; }
      this.post(this.base() + '/modules/archive/install', data).then(function (result) {
        if (!result.success) {
          form.removeAttribute('aria-busy');
          form.classList.remove('is-installing');
          submit.disabled = false;
          submit.classList.remove('is-loading');
          if (progress) { progress.hidden = true; }
          Adminx.Toast.show(result.message || 'Не удалось установить модуль', 'error');
          return;
        }
        if (progressTitle) { progressTitle.textContent = 'Модуль установлен'; }
        Adminx.Toast.show(result.message || 'Модуль установлен', 'success');
        window.setTimeout(function () { window.location.href = result.redirect || (Adminx.base() + '/modules'); }, 350);
      });
    },

    fileSize: function (bytes) {
      if (bytes < 1024) { return bytes + ' Б'; }
      if (bytes < 1024 * 1024) { return Math.round(bytes / 1024) + ' КБ'; }
      return (bytes / 1024 / 1024).toFixed(1).replace('.0', '') + ' МБ';
    },

    reloadSoon: function () {
      window.setTimeout(function () { window.location.reload(); }, 350);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Modules.init(); });
  } else {
    Adminx.Modules.init();
  }
})(window, document);
