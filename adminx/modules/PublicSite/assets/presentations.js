(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.PublicPresentations = {
    form: null,
    drawer: null,
    dirty: false,
    loading: false,
    needsReload: false,
    currentRevision: 0,
    activeEditor: 'draft_item_markup',
    targetCatalog: {},

    init: function () {
      this.form = document.getElementById('publicPresentationForm');
      this.drawer = document.getElementById('publicPresentationDrawer');
      if (!this.form || !this.drawer) { return; }
      this.targetCatalog = this.readJson('[data-presentation-targets]');
      this.targetTypeChanged();
      var self = this;

      document.addEventListener('click', function (event) {
        var target = event.target;
        if (target.closest('[data-presentation-new]')) { self.openNew(); return; }
        var edit = target.closest('[data-presentation-edit]');
        if (edit) { self.open(Number(edit.getAttribute('data-presentation-edit'))); return; }
        var history = target.closest('[data-presentation-revisions]');
        if (history) { self.open(Number(history.getAttribute('data-presentation-revisions')), 'revisions'); return; }
        var copy = target.closest('[data-presentation-copy]');
        if (copy) { self.copy(Number(copy.getAttribute('data-presentation-copy'))); return; }
        var remove = target.closest('[data-presentation-delete]');
        if (remove) { self.remove(Number(remove.getAttribute('data-presentation-delete'))); return; }
        var tab = target.closest('[data-presentation-tab]');
        if (tab) { self.tab(tab.getAttribute('data-presentation-tab')); return; }
        if (target.closest('[data-presentation-save]')) { self.save().catch(function () {}); return; }
        if (target.closest('[data-presentation-publish]')) { self.publish(); return; }
        if (target.closest('[data-presentation-lint]')) { self.lint(); return; }
        if (target.closest('[data-presentation-preview]')) { self.preview(); return; }
        if (target.closest('[data-presentation-diagnose]')) { self.diagnose(); return; }
        var fullscreen = target.closest('[data-presentation-fullscreen]');
        if (fullscreen) { self.fullscreen(fullscreen.getAttribute('data-presentation-fullscreen')); return; }
        var variable = target.closest('[data-presentation-variable]');
        if (variable) { self.insertVariable(variable.getAttribute('data-presentation-variable')); return; }
        if (target.closest('[data-presentation-assignment-save]')) { self.saveAssignment(); return; }
        var assignmentDelete = target.closest('[data-presentation-assignment-delete]');
        if (assignmentDelete) { self.deleteAssignment(Number(assignmentDelete.getAttribute('data-presentation-assignment-delete'))); return; }
        var revision = target.closest('[data-presentation-revision]');
        if (revision) { self.showRevision(Number(revision.getAttribute('data-presentation-revision'))); return; }
        if (target.closest('[data-presentation-revision-restore]')) { self.restoreRevision(); return; }
        if (target.closest('[data-presentation-revision-delete]')) { self.deleteRevision(); return; }
        if (target.closest('[data-presentation-revisions-clear]')) { self.clearRevisions(); }
      });

      document.addEventListener('click', function (event) {
        var close = event.target.closest('[data-close-drawer]');
        if (!close || !self.drawer.contains(close)) { return; }
        if (self.dirty) {
          event.preventDefault();
          event.stopImmediatePropagation();
          self.confirmDiscard();
          return;
        }
        if (self.needsReload) { window.location.reload(); }
      }, true);

      document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 's' && self.drawer.classList.contains('show')) {
          event.preventDefault();
          self.save().catch(function () {});
          return;
        }
        if (event.key === 'Escape' && self.dirty && self.drawer.classList.contains('show')) {
          event.preventDefault();
          event.stopImmediatePropagation();
          self.confirmDiscard();
        }
      }, true);

      this.form.addEventListener('input', function (event) {
        if (!self.loading && !event.target.closest('.public-presentation-assignment-form')) { self.setDirty(true); }
      });
      this.form.addEventListener('change', function (event) {
        if (!self.loading && !event.target.closest('.public-presentation-assignment-form') && event.target.name !== 'preview_document_id') {
          self.setDirty(true);
        }
        if (event.target.name === 'assignment_target_type') { self.targetTypeChanged(); }
      });
      this.form.addEventListener('submit', function (event) {
        event.preventDefault();
        self.save().catch(function () {});
      });
      setTimeout(function () { self.bindEditors(); }, 120);
    },

    base: function () { return this.form.getAttribute('data-base') || ''; },
    resource: function () { return this.base() + '/public-site/presentations'; },
    revisionResource: function () { return this.base() + '/public-site/presentation-revisions'; },
    assignmentResource: function () { return this.base() + '/public-site/presentation-assignments'; },
    id: function () { return Number(this.field('id').value) || 0; },
    field: function (name) { return this.form.querySelector('[name="' + name + '"]'); },
    editor: function (name) {
      var field = this.field(name);
      return field && field._adminxCodeMirror ? field._adminxCodeMirror : null;
    },

    request: function (url, options) {
      return Adminx.Ajax.request(url, options || {}).then(function (response) {
        var payload = response.data || {};
        if (!response.ok || !payload.success) {
          var error = new Error(payload.message || ('HTTP ' + response.status));
          error.payload = payload;
          throw error;
        }
        return payload;
      });
    },

    bindEditors: function () {
      var self = this;
      ['draft_item_markup', 'draft_wrapper_markup', 'draft_empty_markup', 'draft_css'].forEach(function (name) {
        var editor = self.editor(name);
        if (!editor || editor._publicPresentationBound) { return; }
        editor._publicPresentationBound = true;
        editor.on('focus', function () { self.activeEditor = name; });
        editor.on('change', function () {
          if (!self.loading) { self.setDirty(true); }
        });
      });
    },

    openNew: function () {
      this.loading = true;
      this.form.reset();
      this.field('id').value = '';
      this.field('title').value = 'Новое представление';
      this.field('code').value = 'presentation_' + String(Date.now()).slice(-8);
      this.field('kind').value = 'card';
      this.setCode('draft_item_markup', this.defaultValue('item'));
      this.setCode('draft_wrapper_markup', this.defaultValue('wrapper'));
      this.setCode('draft_empty_markup', this.defaultValue('empty'));
      this.setCode('draft_css', '');
      this.loading = false;
      this.needsReload = false;
      this.currentRevision = 0;
      this.setStatus({is_published: false, version: 0, has_changes: true});
      this.renderAssignments([]);
      this.clearRevisionView();
      this.clearPreview();
      this.clearDiagnostics();
      this.setDirty(false);
      this.tab('item');
      Adminx.Drawer.open('publicPresentationDrawer');
      this.refreshEditors();
    },

    open: function (id, panel) {
      var self = this;
      Adminx.Loader.show();
      this.request(this.resource() + '/' + id).then(function (payload) {
        self.fill(payload.data || {});
        self.tab(panel || 'item');
        Adminx.Drawer.open('publicPresentationDrawer');
        self.refreshEditors();
        if (panel === 'revisions') { self.loadRevisions(); }
      }).catch(function (error) {
        self.fail(error);
      }).finally(function () {
        Adminx.Loader.hide();
      });
    },

    fill: function (item) {
      this.loading = true;
      ['id', 'title', 'code', 'kind', 'description', 'draft_settings_json'].forEach(function (name) {
        var field = this.field(name);
        if (field) { field.value = item[name] == null ? (name === 'draft_settings_json' ? '{}' : '') : item[name]; }
      }, this);
      ['draft_item_markup', 'draft_wrapper_markup', 'draft_empty_markup', 'draft_css'].forEach(function (name) {
        this.setCode(name, item[name] || '');
      }, this);
      this.loading = false;
      this.needsReload = false;
      this.currentRevision = 0;
      this.setStatus(item);
      this.renderAssignments(item.assignments || []);
      this.clearRevisionView();
      this.clearPreview();
      this.clearDiagnostics();
      this.setDirty(false);
    },

    defaultValue: function (name) {
      var field = document.querySelector('[data-presentation-default="' + name + '"]');
      return field ? field.value : '';
    },

    setCode: function (name, value) {
      var field = this.field(name), editor = this.editor(name);
      if (field) { field.value = value || ''; }
      if (editor) { editor.setValue(value || ''); }
    },

    sync: function () {
      if (Adminx.CodeEditor) { Adminx.CodeEditor.syncAll(this.form); }
    },

    refreshEditors: function () {
      this.bindEditors();
      setTimeout(function () {
        if (Adminx.CodeEditor) { Adminx.CodeEditor.refreshAll(); }
      }, 80);
    },

    tab: function (name) {
      this.drawer.querySelectorAll('[data-presentation-tab]').forEach(function (button) {
        var active = button.getAttribute('data-presentation-tab') === name;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      this.drawer.querySelectorAll('[data-presentation-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-presentation-panel') !== name;
      });
      if (name === 'item') { this.activeEditor = 'draft_item_markup'; }
      if (name === 'wrapper') { this.activeEditor = 'draft_wrapper_markup'; }
      if (name === 'empty') { this.activeEditor = 'draft_empty_markup'; }
      if (name === 'css') { this.activeEditor = 'draft_css'; }
      if (name === 'revisions') { this.loadRevisions(); }
      this.refreshEditors();
    },

    save: function (quiet) {
      var self = this;
      this.sync();
      var id = this.id();
      this.setSaveState('Сохранение...', '');
      return this.request(this.resource() + (id ? '/' + id : ''), {
        method: 'POST',
        body: new FormData(this.form)
      }).then(function (payload) {
        var savedId = Number((payload.data || {}).id) || id;
        self.field('id').value = savedId;
        self.needsReload = true;
        self.setDirty(false);
        self.setSaveState('Черновик сохранён', 'ok');
        if (!quiet) { Adminx.Toast.show(payload.message || 'Черновик сохранён', 'success'); }
        return savedId;
      }).catch(function (error) {
        self.setSaveState('Не сохранено', 'error');
        self.fail(error);
        throw error;
      });
    },

    publish: function () {
      var self = this;
      this.save(true).then(function (id) {
        var data = new FormData();
        data.set('_csrf', Adminx.csrf());
        return self.request(self.resource() + '/' + id + '/publish', {method: 'POST', body: data});
      }).then(function (payload) {
        self.setStatus(payload.data || {});
        self.setSaveState('Опубликовано', 'ok');
        self.needsReload = true;
        Adminx.Toast.show(payload.message || 'Представление опубликовано', 'success');
      }).catch(function () {});
    },

    lint: function () {
      var self = this;
      this.sync();
      var data = new FormData(this.form);
      this.setLint('Проверка...', '');
      this.request(this.resource() + '/lint', {method: 'POST', body: data}).then(function (payload) {
        self.setLint(payload.message || 'Twig-синтаксис корректен', 'ok');
      }).catch(function (error) {
        self.setLint(error.message, 'error');
      });
    },

    preview: function () {
      var self = this;
      this.sync();
      Adminx.Loader.show();
      this.request(this.resource() + '/preview', {method: 'POST', body: new FormData(this.form)}).then(function (payload) {
        var result = payload.data || {};
        self.drawer.querySelector('[data-presentation-preview-frame]').srcdoc = result.html || '';
        self.drawer.querySelector('[data-presentation-preview-json]').textContent = result.json || '{}';
        var state = self.drawer.querySelector('[data-presentation-preview-state] span');
        if (state) {
          state.textContent = result.document && result.document.title
            ? 'Показан материал «' + result.document.title + '» · ID ' + result.document.id
            : 'Предпросмотр обновлён.';
        }
      }).catch(function (error) {
        self.fail(error);
      }).finally(function () {
        Adminx.Loader.hide();
      });
    },

    clearPreview: function () {
      var frame = this.drawer.querySelector('[data-presentation-preview-frame]');
      if (frame) { frame.removeAttribute('srcdoc'); }
      var json = this.drawer.querySelector('[data-presentation-preview-json]');
      if (json) { json.textContent = 'После запуска здесь появятся данные, доступные шаблону.'; }
      var state = this.drawer.querySelector('[data-presentation-preview-state] span');
      if (state) { state.textContent = 'Выберите материал и запустите предпросмотр.'; }
    },

    fullscreen: function (name) {
      var editor = this.editor(name);
      if (editor && Adminx.CodeEditor) { Adminx.CodeEditor.toggleFullscreen(editor); }
    },

    insertVariable: function (value) {
      var editorName = this.activeEditor === 'draft_css' ? 'draft_item_markup' : this.activeEditor;
      var tab = {draft_item_markup: 'item', draft_wrapper_markup: 'wrapper', draft_empty_markup: 'empty'}[editorName] || 'item';
      this.tab(tab);
      var editor = this.editor(editorName);
      if (editor) {
        editor.replaceSelection(value || '');
        editor.focus();
        editor.save();
      }
    },

    saveAssignment: function () {
      var id = this.id(), self = this;
      if (!id) {
        Adminx.Toast.show('Сначала сохраните представление', 'warning');
        return;
      }
      var data = new FormData();
      data.set('_csrf', Adminx.csrf());
      data.set('context_code', this.field('assignment_context').value);
      data.set('target_type', this.field('assignment_target_type').value);
      data.set('target_key', this.field('assignment_target_key').value || '0');
      data.set('mode', this.field('assignment_mode').value);
      this.request(this.resource() + '/' + id + '/assignments', {method: 'POST', body: data}).then(function (payload) {
        self.renderAssignments((payload.data || {}).assignments || []);
        self.needsReload = true;
        Adminx.Toast.show(payload.message || 'Назначение сохранено', 'success');
      }).catch(function (error) {
        self.fail(error);
      });
    },

    deleteAssignment: function (id) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Удалить назначение?',
        message: 'Само представление и публичный шаблон не изменятся.',
        confirmLabel: 'Удалить',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', Adminx.csrf());
          self.request(self.assignmentResource() + '/' + id + '/delete', {method: 'POST', body: data}).then(function () {
            self.reloadAssignments();
            self.needsReload = true;
          }).catch(function (error) {
            self.fail(error);
          });
        }
      });
    },

    reloadAssignments: function () {
      var self = this, id = this.id();
      if (!id) { return; }
      this.request(this.resource() + '/' + id).then(function (payload) {
        self.renderAssignments((payload.data || {}).assignments || []);
      }).catch(function (error) {
        self.fail(error);
      });
    },

    renderAssignments: function (items) {
      var list = this.drawer.querySelector('[data-presentation-assignment-list]');
      var contexts = {
        content_list: 'Список материалов',
        compact_list: 'Компактный список',
        slider: 'Слайдер',
        search_results: 'Результаты поиска',
        related: 'Похожие материалы',
        favorites: 'Избранное',
        viewed: 'Просмотренные материалы',
        catalog_filters: 'Фильтры каталога',
        document_page: 'Страница материала'
      };
      if (!items.length) {
        list.innerHTML = '<div class="empty-state">Назначений пока нет.</div>';
        return;
      }
      var self = this;
      list.innerHTML = items.map(function (item) {
        var modeClass = item.mode === 'native' ? 'badge-green' : (item.mode === 'preview' ? 'badge-amber' : 'badge-gray');
        var targetClass = item.target_available === false ? ' is-missing' : '';
        var target = '<span class="public-presentation-assignment-target' + targetClass + '"><b>' + self.escape(item.target_title || item.target_key) + '</b><small>' + self.escape(item.target_meta || '') + '</small></span>';
        if (item.target_url) {
          target = '<a class="public-presentation-assignment-target" href="' + self.escape(self.base() + item.target_url) + '"><b>' + self.escape(item.target_title || item.target_key) + '</b><small>' + self.escape(item.target_meta || '') + '</small></a>';
        }
        return '<div class="public-presentation-assignment"><span><b>' + self.escape(contexts[item.context_code] || item.context_code) + '</b><small>' + self.escape(item.updated_label || '') + '</small></span>' + target + '<span class="badge ' + modeClass + '">' + self.escape(item.mode_label || item.mode) + '</span><button class="btn btn-ghost btn-icon btn-sm is-danger" type="button" data-presentation-assignment-delete="' + Number(item.id) + '" aria-label="Удалить"><i class="ti ti-trash"></i></button></div>';
      }).join('');
    },

    targetTypeChanged: function () {
      var type = this.field('assignment_target_type').value || 'global';
      var select = this.field('assignment_target_key');
      var current = select.value;
      var items = this.targetCatalog[type] || [];
      var self = this;
      select.innerHTML = items.map(function (item) {
        return '<option value="' + self.escape(item.key) + '">' + self.escape(item.title) + ' · ' + self.escape(item.meta) + '</option>';
      }).join('');
      if (!items.length) {
        select.innerHTML = '<option value="">Доступных целей нет</option>';
        select.disabled = true;
      } else {
        select.disabled = false;
        select.value = items.some(function (item) { return String(item.key) === String(current); }) ? current : items[0].key;
      }
      var hint = this.drawer.querySelector('[data-presentation-target-hint]');
      if (hint) {
        hint.textContent = type === 'global'
          ? 'Общее назначение для всех подходящих мест.'
          : (items.length ? 'Выберите цель по названию. ID вводить не нужно.' : 'Сначала создайте или включите подходящую цель.');
      }
    },

    diagnose: function () {
      var self = this;
      this.save(true).then(function (id) {
        self.sync();
        self.setDiagnosticState('Проверяем...', 'loading');
        return self.request(self.resource() + '/' + id + '/diagnose', {
          method: 'POST',
          body: new FormData(self.form)
        });
      }).then(function (payload) {
        self.renderDiagnostics(payload.data || {});
        self.tab('diagnostics');
      }).catch(function (error) {
        self.setDiagnosticState('Проверка не выполнена', 'error');
        self.fail(error);
      });
    },

    renderDiagnostics: function (result) {
      var summary = result.summary || {};
      var list = this.drawer.querySelector('[data-presentation-diagnostic-list]');
      var self = this;
      this.setDiagnosticState(
        result.ready
          ? (Number(summary.warning || 0) > 0
            ? 'Ошибок нет · предупреждений: ' + Number(summary.warning || 0)
            : 'Готово к публикации')
          : 'Ошибок: ' + Number(summary.error || 0) + ' · предупреждений: ' + Number(summary.warning || 0),
        result.ready ? (Number(summary.warning || 0) > 0 ? 'warning' : 'ok') : 'error'
      );
      list.innerHTML = (result.checks || []).map(function (check) {
        var icon = check.status === 'ok' ? 'ti-circle-check' : (check.status === 'error' ? 'ti-alert-circle' : 'ti-alert-triangle');
        var action = check.url ? '<a class="btn btn-ghost btn-sm" href="' + self.escape(self.base() + check.url) + '"><i class="ti ti-arrow-up-right"></i>Открыть</a>' : '';
        return '<article class="public-presentation-diagnostic is-' + self.escape(check.status) + '"><i class="ti ' + icon + '"></i><span><b>' + self.escape(check.title) + '</b><small>' + self.escape(check.message) + '</small></span>' + action + '</article>';
      }).join('') || '<div class="empty-state">Результатов проверки нет.</div>';
    },

    clearDiagnostics: function () {
      this.setDiagnosticState('Проверка ещё не запускалась', '');
      var list = this.drawer.querySelector('[data-presentation-diagnostic-list]');
      if (list) { list.innerHTML = '<div class="empty-state">Сохраните представление и запустите диагностику.</div>'; }
    },

    setDiagnosticState: function (message, state) {
      var summary = this.drawer.querySelector('[data-presentation-diagnostic-summary]');
      if (!summary) { return; }
      var badge = state === 'ok'
        ? 'badge-green'
        : (state === 'warning' ? 'badge-amber' : (state === 'error' ? 'badge-red' : (state === 'loading' ? 'badge-blue' : 'badge-gray')));
      summary.innerHTML = '<span class="badge ' + badge + '">' + this.escape(message || '') + '</span>';
    },

    copy: function (id) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'info',
        title: 'Создать копию?',
        message: 'Будет создан новый неопубликованный черновик без назначений.',
        confirmLabel: 'Создать копию',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', Adminx.csrf());
          self.request(self.resource() + '/' + id + '/copy', {method: 'POST', body: data}).then(function () {
            window.location.reload();
          }).catch(function (error) {
            self.fail(error);
          });
        }
      });
    },

    remove: function (id) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить представление?',
        message: 'Черновик, опубликованная версия и ревизии будут удалены. Назначенное представление удалить нельзя.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', Adminx.csrf());
          self.request(self.resource() + '/' + id + '/delete', {method: 'POST', body: data}).then(function () {
            window.location.reload();
          }).catch(function (error) {
            self.fail(error);
          });
        }
      });
    },

    loadRevisions: function () {
      var id = this.id(), self = this;
      var list = this.drawer.querySelector('[data-presentation-revision-list]');
      if (!id) {
        list.innerHTML = '<div class="empty-state">Сначала сохраните представление.</div>';
        return;
      }
      list.innerHTML = '<div class="empty-state">Загрузка...</div>';
      this.request(this.resource() + '/' + id + '/revisions').then(function (payload) {
        var items = (payload.data || {}).revisions || [];
        self.drawer.querySelector('[data-presentation-revisions-count]').textContent = items.length ? ('Снимков: ' + items.length) : 'Снимков пока нет.';
        self.drawer.querySelector('[data-presentation-revisions-clear]').disabled = !items.length;
        list.innerHTML = items.length ? items.map(function (item) {
          return '<button type="button" data-presentation-revision="' + Number(item.id) + '"><span><b>' + self.escape(item.action_label) + '</b><small>' + self.escape(item.comment || item.author_name || '') + '</small></span><span><em class="badge ' + self.escape(item.badge) + '">' + self.escape(item.action_label) + '</em><small class="mono">' + self.escape(item.created_label) + '</small></span></button>';
        }).join('') : '<div class="empty-state">Ревизий пока нет.</div>';
      }).catch(function (error) {
        self.fail(error);
      });
    },

    showRevision: function (id) {
      var self = this;
      this.request(this.revisionResource() + '/' + id).then(function (payload) {
        var item = payload.data || {}, snapshot = item.snapshot || {};
        self.currentRevision = Number(item.id) || 0;
        self.drawer.querySelectorAll('[data-presentation-revision]').forEach(function (button) {
          button.classList.toggle('is-active', Number(button.getAttribute('data-presentation-revision')) === self.currentRevision);
        });
        self.drawer.querySelector('[data-presentation-revision-meta]').innerHTML = '<b>' + self.escape(item.action_label || '') + '</b><small>' + self.escape(item.created_label || '') + ' · ' + self.escape(item.author_name || 'system') + '</small>';
        self.drawer.querySelector('[data-presentation-revision-code]').textContent = snapshot.draft_item_markup || '';
        self.drawer.querySelector('[data-presentation-revision-restore]').disabled = false;
        self.drawer.querySelector('[data-presentation-revision-delete]').disabled = false;
      }).catch(function (error) {
        self.fail(error);
      });
    },

    restoreRevision: function () {
      if (!this.currentRevision) { return; }
      var self = this, revision = this.currentRevision;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Восстановить черновик?',
        message: 'Опубликованная версия публичного сайта не изменится.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', Adminx.csrf());
          self.request(self.revisionResource() + '/' + revision + '/restore', {method: 'POST', body: data}).then(function () {
            self.needsReload = true;
            self.open(self.id(), 'revisions');
            Adminx.Toast.show('Ревизия восстановлена в черновик', 'success');
          }).catch(function (error) {
            self.fail(error);
          });
        }
      });
    },

    deleteRevision: function () {
      if (!this.currentRevision) { return; }
      var self = this, data = new FormData();
      data.set('_csrf', Adminx.csrf());
      this.request(this.revisionResource() + '/' + this.currentRevision + '/delete', {method: 'POST', body: data}).then(function () {
        self.clearRevisionView();
        self.loadRevisions();
      }).catch(function (error) {
        self.fail(error);
      });
    },

    clearRevisions: function () {
      var self = this, id = this.id();
      if (!id) { return; }
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить все ревизии?',
        message: 'Текущий черновик и опубликованная версия сохранятся.',
        confirmLabel: 'Удалить все',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', Adminx.csrf());
          self.request(self.resource() + '/' + id + '/revisions/delete', {method: 'POST', body: data}).then(function () {
            self.clearRevisionView();
            self.loadRevisions();
          }).catch(function (error) {
            self.fail(error);
          });
        }
      });
    },

    clearRevisionView: function () {
      this.currentRevision = 0;
      var meta = this.drawer.querySelector('[data-presentation-revision-meta]');
      var code = this.drawer.querySelector('[data-presentation-revision-code]');
      if (meta) { meta.textContent = 'Выберите снимок выше.'; }
      if (code) { code.textContent = ''; }
      this.drawer.querySelectorAll('[data-presentation-revision-restore],[data-presentation-revision-delete]').forEach(function (button) {
        button.disabled = true;
      });
    },

    setStatus: function (item) {
      var badge = this.drawer.querySelector('[data-presentation-status]');
      badge.textContent = item.is_published ? ('Опубликовано · v' + (item.version || 1)) : 'Черновик';
      badge.className = 'badge ' + (item.is_published ? (item.has_changes ? 'badge-amber' : 'badge-green') : 'badge-gray');
      this.drawer.querySelector('[data-presentation-title]').textContent = item.title || this.field('title').value || 'Новое представление';
    },

    setDirty: function (value) {
      this.dirty = !!value;
      this.drawer.classList.toggle('has-unsaved-changes', this.dirty);
      if (this.dirty) { this.setSaveState('Есть несохранённые изменения', 'dirty'); }
    },

    setSaveState: function (message, state) {
      var element = this.drawer.querySelector('[data-presentation-save-state]');
      element.textContent = message || '';
      element.className = 'public-presentation-save-state' + (state ? ' is-' + state : '');
    },

    setLint: function (message, state) {
      this.drawer.querySelectorAll('[data-presentation-lint-result]').forEach(function (element) {
        element.textContent = message || '';
        element.className = 'field-hint' + (state ? ' is-' + state : '');
      });
    },

    confirmDiscard: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Закрыть без сохранения?',
        message: 'Изменения текущего черновика будут потеряны.',
        confirmLabel: 'Закрыть',
        onConfirm: function () {
          self.setDirty(false);
          if (self.needsReload) { window.location.reload(); return; }
          Adminx.Drawer.close(self.drawer);
        }
      });
    },

    readJson: function (selector) {
      var node = document.querySelector(selector);
      if (!node) { return {}; }
      try { return JSON.parse(node.textContent || '{}'); }
      catch (error) { return {}; }
    },

    escape: function (value) {
      var node = document.createElement('div');
      node.textContent = value == null ? '' : String(value);
      return node.innerHTML;
    },

    fail: function (error) {
      Adminx.Toast.show((error && error.message) || 'Не удалось выполнить действие', 'error');
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.PublicPresentations.init(); });
  } else {
    Adminx.PublicPresentations.init();
  }
})(window, document);
