(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Themes = {
    root: null,
	    currentRevisionId: 0,
	    draggedRow: null,
	    settingsDrag: null,

    init: function () {
      this.root = document.querySelector('[data-themes-root]');
      if (!this.root) { return; }
      this.bindThemePicker();
      this.bindActions();
	      this.bindForms();
	      this.bindManifest();
	      this.bindSettings();
	      this.restoreTab();
    },

    base: function () { return this.root ? this.root.getAttribute('data-base') : Adminx.base(); },
    theme: function () { return this.root ? this.root.getAttribute('data-theme') : ''; },

    bindThemePicker: function () {
      var select = document.querySelector('[data-theme-select]');
      if (!select) { return; }
      select.addEventListener('change', function () {
        window.location.href = Adminx.base() + '/themes?theme=' + encodeURIComponent(select.value);
      });
    },

    post: function (url, formData, options) {
      options = options || {};
      Adminx.Loader.show();
      return Adminx.Ajax.post(url, formData).then(function (payload) {
        Adminx.Loader.hide();
        var data = payload.data || {};
        if (!data.success) {
          Adminx.Toast.show(data.message || 'Не удалось выполнить действие', 'error');
          return null;
        }
        if (data.message) { Adminx.Toast.show(data.message, 'success'); }
        if (data.redirect) { window.location.href = data.redirect; return data; }
        if (options.reload) { window.location.reload(); }
        return data;
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Сервер недоступен', 'error');
        return null;
      });
    },

    formData: function (values) {
      var data = new FormData();
      Object.keys(values || {}).forEach(function (key) { data.set(key, values[key]); });
      return data;
    },

    bindActions: function () {
      var self = this;
      document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-tab-target]');
        if (tab && tab.closest('[data-themes-root]')) {
          window.history.replaceState(null, '', '#' + tab.getAttribute('data-tab-target'));
        }

        var edit = event.target.closest('[data-theme-file-edit]');
        if (edit) { self.openFile(edit.closest('[data-theme-path]')); return; }

        var remove = event.target.closest('[data-theme-path-delete]');
        if (remove) { self.deletePath(remove.closest('[data-theme-path]')); return; }

        var kind = event.target.closest('[data-create-kind]');
        if (kind) { self.configureCreateAsset(kind.getAttribute('data-create-kind')); return; }

        var activate = event.target.closest('[data-theme-activate]');
        if (activate) { self.activate(); return; }

        if (event.target.closest('[data-theme-delete]')) { self.deleteTheme(); return; }

        var revision = event.target.closest('[data-theme-revision-open]');
        if (revision) { self.openRevision(revision.closest('[data-revision-id]')); return; }

        var deleteRevision = event.target.closest('[data-theme-revision-delete]');
        if (deleteRevision) { self.deleteRevision(deleteRevision.closest('[data-revision-id]')); return; }

        if (event.target.closest('[data-theme-revisions-clear]')) { self.clearRevisions(); return; }
        if (event.target.closest('[data-theme-revision-restore]')) { self.restoreRevision(); }
      });

      var upload = document.querySelector('[data-theme-upload]');
      if (upload) {
        upload.addEventListener('change', function () {
          if (!upload.files.length) { return; }
          var data = new FormData();
          data.set('theme', self.theme());
          data.set('directory', self.root.getAttribute('data-directory') || '');
          Array.prototype.forEach.call(upload.files, function (file) { data.append('files[]', file); });
          self.post(self.base() + '/themes/upload', data, { reload: true });
        });
      }
    },

    restoreTab: function () {
      var name = window.location.hash.replace(/^#/, '');
	      if (!/^(files|connections|settings|revisions)$/.test(name)) { return; }
      var tabs = this.root.querySelector('[data-tabs]');
      if (tabs && Adminx.Tabs) { Adminx.Tabs.activate(tabs, name); }
    },

    bindForms: function () {
      var self = this;
      var fileForm = document.querySelector('[data-theme-file-form]');
      if (fileForm) {
        fileForm.addEventListener('submit', function (event) {
          event.preventDefault();
          if (Adminx.CodeEditor) { Adminx.CodeEditor.syncAll(fileForm); }
          self.post(self.base() + '/themes/file', new FormData(fileForm)).then(function (data) {
            if (data) { fileForm.dataset.clean = fileForm.elements.content.value; }
          });
        });
      }

      this.bindAjaxForm('[data-theme-create-asset]', function (form) {
        return form.elements.kind.value === 'folder' ? '/themes/folders/create' : '/themes/files/create';
      }, true);
      this.bindAjaxForm('[data-theme-create-form]', function () { return '/themes/create'; });
      this.bindAjaxForm('[data-theme-import-form]', function () { return '/themes/import'; });

	      var manifest = document.querySelector('[data-theme-manifest-form]');
      if (manifest) {
        manifest.addEventListener('submit', function (event) {
          event.preventDefault();
          self.indexManifest(manifest);
	          self.post(self.base() + '/themes/manifest', new FormData(manifest), { reload: true });
	        });
	      }

	      var settings = document.querySelector('[data-theme-settings-form]');
	      if (settings) {
	        settings.addEventListener('submit', function (event) {
	          event.preventDefault();
	          settings.querySelectorAll('[data-theme-order]').forEach(function (list) { self.syncThemeOrder(list); });
	          self.post(self.base() + '/themes/settings', new FormData(settings), { reload: true });
	        });
	      }
	    },

    bindAjaxForm: function (selector, path, reload) {
      var self = this;
      var form = document.querySelector(selector);
      if (!form) { return; }
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        self.post(self.base() + path(form), new FormData(form), { reload: !!reload });
      });
    },

    configureCreateAsset: function (kind) {
      var form = document.querySelector('[data-theme-create-asset]');
      if (!form) { return; }
      var folder = kind === 'folder';
      form.reset();
      form.elements.theme.value = this.theme();
      form.elements.directory.value = this.root.getAttribute('data-directory') || '';
      form.elements.kind.value = folder ? 'folder' : 'file';
      document.querySelector('[data-asset-create-title]').textContent = folder ? 'Создать папку' : 'Создать файл';
      document.querySelector('[data-asset-create-hint]').textContent = folder ? 'Имя без точки и служебных символов.' : 'Расширения: css, js, json, svg, xml, txt.';
      form.elements.name.placeholder = folder ? 'components' : 'styles.css';
    },

    openFile: function (row) {
      if (!row) { return; }
      var self = this;
      var url = this.base() + '/themes/file?theme=' + encodeURIComponent(this.theme()) + '&path=' + encodeURIComponent(row.dataset.themePath);
      Adminx.Loader.show();
      Adminx.Ajax.request(url).then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!response.success) { Adminx.Toast.show(response.message || 'Файл не найден', 'error'); return; }
        var item = response.data || {};
        var form = document.querySelector('[data-theme-file-form]');
        form.elements.path.value = item.path || '';
        form.elements.content.value = item.content || '';
        form.dataset.clean = item.content || '';
        document.querySelector('[data-file-title]').textContent = item.name || 'Редактор файла';
        document.querySelector('[data-file-meta]').textContent = (item.path || '') + ' · ' + (item.size_label || '') + ' · ' + (item.modified_label || '');
        var textarea = form.elements.content;
        textarea.setAttribute('data-mode', item.mode || 'text/plain');
        if (textarea._adminxCodeMirror) {
          textarea._adminxCodeMirror.setOption('mode', item.mode || 'text/plain');
          textarea._adminxCodeMirror.setValue(item.content || '');
        }
        Adminx.Drawer.open('themeFileDrawer');
        window.setTimeout(function () { if (Adminx.CodeEditor) { Adminx.CodeEditor.refreshAll(); } }, 80);
      }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Сервер недоступен', 'error'); });
    },

    deletePath: function (row) {
      if (!row) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'error', title: row.dataset.themeType === 'directory' ? 'Удалить папку?' : 'Удалить файл?',
        message: '«' + row.dataset.themeName + '» будет удалён из темы. Текстовая версия останется в ревизиях.',
        confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () {
          self.post(self.base() + '/themes/path/delete', self.formData({ theme: self.theme(), path: row.dataset.themePath }), { reload: true });
        }
      });
    },

    activate: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning', title: 'Активировать тему?',
        message: 'Публичный сайт начнёт использовать тему «' + this.theme() + '». Подключения применятся только там, где установлены новые теги темы.',
        confirmLabel: 'Активировать',
        onConfirm: function () { self.post(self.base() + '/themes/activate', self.formData({ theme: self.theme() }), { reload: true }); }
      });
    },

    deleteTheme: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить тему?',
        message: 'Каталог «' + this.theme() + '», его ассеты и ревизии будут удалены без возможности восстановления.',
        confirmLabel: 'Удалить тему', confirmClass: 'btn-danger',
        onConfirm: function () { self.post(self.base() + '/themes/delete', self.formData({ theme: self.theme() })); }
      });
    },

	    bindManifest: function () {
      var self = this;
      document.addEventListener('click', function (event) {
        var add = event.target.closest('[data-manifest-add]');
        if (add) {
          var kind = add.getAttribute('data-manifest-add');
          var template = document.getElementById(kind === 'style' ? 'themeStyleRow' : 'themeScriptRow');
          var list = document.querySelector('[data-manifest-list="' + kind + '"]');
          if (template && list) { list.appendChild(template.content.cloneNode(true)); }
          return;
        }
        var remove = event.target.closest('[data-manifest-remove]');
        if (remove) { remove.closest('[data-manifest-row]').remove(); }
      });

      document.querySelectorAll('[data-manifest-row]').forEach(function (row) { self.makeDraggable(row); });
      document.addEventListener('mouseover', function (event) {
        var row = event.target.closest('[data-manifest-row]');
        if (row && !row.hasAttribute('draggable')) { self.makeDraggable(row); }
	      });
	    },

	    bindSettings: function () {
	      var self = this;
	      var form = document.querySelector('[data-theme-settings-form]');
	      if (!form) { return; }

	      form.addEventListener('change', function (event) {
	        if (event.target.matches('input[name="presentation_mode"]')) {
	          form.querySelectorAll('.themes-mode-card').forEach(function (card) {
	            var radio = card.querySelector('input[name="presentation_mode"]');
	            card.classList.toggle('is-selected', !!(radio && radio.checked));
	          });
	        }

	        if (event.target.matches('[data-theme-order-visible]')) {
	          var row = event.target.closest('[data-theme-order-item]');
	          row.classList.toggle('is-disabled', !event.target.checked);
	          self.syncThemeOrder(row.parentNode);
	        }
	      });

	      form.addEventListener('click', function (event) {
	        var button = event.target.closest('[data-theme-order-move]');
	        if (!button || button.disabled) { return; }
	        var row = button.closest('[data-theme-order-item]');
	        if (button.getAttribute('data-theme-order-move') === 'up' && row.previousElementSibling) {
	          row.parentNode.insertBefore(row, row.previousElementSibling);
	        } else if (button.getAttribute('data-theme-order-move') === 'down' && row.nextElementSibling) {
	          row.parentNode.insertBefore(row.nextElementSibling, row);
	        }
	        self.syncThemeOrder(row.parentNode);
	      });

	      form.addEventListener('dragstart', function (event) {
	        var handle = event.target.closest('[data-theme-order-handle]');
	        var row = handle ? handle.closest('[data-theme-order-item]') : null;
	        if (!row || row.getAttribute('draggable') !== 'true') { event.preventDefault(); return; }
	        self.settingsDrag = row;
	        row.classList.add('is-dragging');
	        if (event.dataTransfer) {
	          event.dataTransfer.effectAllowed = 'move';
	          event.dataTransfer.setData('text/plain', row.getAttribute('data-code') || '');
	        }
	      });

	      form.addEventListener('dragover', function (event) {
	        if (!self.settingsDrag) { return; }
	        var target = event.target.closest('[data-theme-order-item]');
	        var list = event.target.closest('[data-theme-order]');
	        if (!target || !list || target === self.settingsDrag || self.settingsDrag.parentNode !== list) { return; }
	        event.preventDefault();
	        var box = target.getBoundingClientRect();
	        list.insertBefore(self.settingsDrag, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
	        self.syncThemeOrder(list);
	      });

	      form.addEventListener('dragend', function () {
	        if (!self.settingsDrag) { return; }
	        var list = self.settingsDrag.parentNode;
	        self.settingsDrag.classList.remove('is-dragging');
	        self.settingsDrag = null;
	        self.syncThemeOrder(list);
	      });
	    },

	    syncThemeOrder: function (list) {
	      if (!list) { return; }
	      var input = list.parentNode.querySelector('[data-theme-order-value]');
	      if (!input) { return; }
	      input.value = JSON.stringify(Array.prototype.map.call(list.querySelectorAll('[data-theme-order-item]'), function (row) {
	        var toggle = row.querySelector('[data-theme-order-visible]');
	        return { code: row.getAttribute('data-code') || '', visible: !!(toggle && toggle.checked) };
	      }));
	    },

	    makeDraggable: function (row) {
      var self = this;
      row.setAttribute('draggable', 'true');
      row.addEventListener('dragstart', function () { self.draggedRow = row; row.classList.add('is-dragging'); });
      row.addEventListener('dragend', function () { row.classList.remove('is-dragging'); self.draggedRow = null; });
      row.addEventListener('dragover', function (event) {
        if (!self.draggedRow || self.draggedRow.parentNode !== row.parentNode || self.draggedRow === row) { return; }
        event.preventDefault();
        var box = row.getBoundingClientRect();
        row.parentNode.insertBefore(self.draggedRow, event.clientY < box.top + box.height / 2 ? row : row.nextSibling);
      });
    },

    indexManifest: function (form) {
      form.querySelectorAll('[data-manifest-list]').forEach(function (list) {
        var kind = list.getAttribute('data-manifest-list') === 'style' ? 'styles' : 'scripts';
        list.querySelectorAll('[data-manifest-row]').forEach(function (row, index) {
          row.querySelectorAll('[data-field]').forEach(function (field) {
            field.name = kind + '[' + index + '][' + field.getAttribute('data-field') + ']';
          });
        });
      });
    },

    openRevision: function (row) {
      if (!row) { return; }
      var self = this;
      Adminx.Ajax.request(this.base() + '/themes/revisions/' + row.dataset.revisionId).then(function (payload) {
        var response = payload.data || {};
        if (!response.success) { Adminx.Toast.show(response.message || 'Ревизия не найдена', 'error'); return; }
        var item = response.data || {};
        self.currentRevisionId = item.id || 0;
        document.querySelector('[data-revision-title]').textContent = item.path || 'Ревизия';
        document.querySelector('[data-revision-meta]').textContent = (item.action_label || '') + ' · ' + (item.author_name || 'Система') + ' · ' + (item.created_label || '');
        var textarea = document.querySelector('[data-revision-content]');
        textarea.value = item.content || '';
        if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.setValue(item.content || ''); }
        Adminx.Drawer.open('themeRevisionDrawer');
        window.setTimeout(function () { if (Adminx.CodeEditor) { Adminx.CodeEditor.refreshAll(); } }, 80);
      });
    },

    restoreRevision: function () {
      if (!this.currentRevisionId) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning', title: 'Восстановить ревизию?', message: 'Текущее содержимое файла сначала будет сохранено как отдельная ревизия.',
        confirmLabel: 'Восстановить',
        onConfirm: function () { self.post(self.base() + '/themes/revisions/' + self.currentRevisionId + '/restore', new FormData(), { reload: true }); }
      });
    },

    deleteRevision: function (row) {
      if (!row) { return; }
      var self = this;
      Adminx.Confirm.open({ kind: 'error', title: 'Удалить ревизию?', message: 'Версию файла нельзя будет восстановить.', confirmLabel: 'Удалить', confirmClass: 'btn-danger', onConfirm: function () { self.post(self.base() + '/themes/revisions/' + row.dataset.revisionId + '/delete', new FormData(), { reload: true }); } });
    },

    clearRevisions: function () {
      var self = this;
      Adminx.Confirm.open({ kind: 'error', title: 'Удалить все ревизии темы?', message: 'История текстовых файлов будет очищена без возможности восстановления.', confirmLabel: 'Удалить все', confirmClass: 'btn-danger', onConfirm: function () { self.post(self.base() + '/themes/revisions/clear', self.formData({ theme: self.theme(), path: '' }), { reload: true }); } });
    }
  };

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { Adminx.Themes.init(); }); }
  else { Adminx.Themes.init(); }
})(window, document);
