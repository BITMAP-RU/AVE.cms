/**
 * JS раздела «Шаблоны»: drawer-CRUD, ревизии, lint и импорт legacy.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  Adminx.Templates = {
    form: null,
    currentRevisionId: 0,
    currentRevisionTemplateId: 0,
    filterTimer: null,

    init: function () {
      this.form = document.getElementById('templateForm');
      var self = this;

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-template-new]')) { self.fillNew(); }
        if (e.target.closest('[data-templates-filter-reset]')) { self.resetFilters(); }
        if (e.target.closest('[data-template-lint]')) { self.lintCode(); }
        if (e.target.closest('[data-template-code-fullscreen]')) { self.toggleCodeFullscreen(); }
        if (e.target.closest('[data-template-submit-stay]')) { self.submit(true); }
        if (e.target.closest('[data-template-tags-toggle]')) { self.toggleTagPalette(); }
        if (e.target.closest('[data-template-tags-close]')) { self.toggleTagPalette(false); }
        var tab = e.target.closest('[data-template-tag-tab]');
        if (tab) { self.activateTagGroup(tab.getAttribute('data-template-tag-tab')); }
        var tag = e.target.closest('[data-template-tag]');
        if (tag) { self.insertTag(tag); }

        var edit = e.target.closest('[data-template-edit]');
        if (edit) { self.fillEdit(edit.closest('[data-template-row]')); }
        var copy = e.target.closest('[data-template-copy]');
        if (copy) { self.copy(copy.closest('[data-template-row]')); }
        var cache = e.target.closest('[data-template-cache]');
        if (cache) { self.clearCache(cache.closest('[data-template-row]')); }
        var revisions = e.target.closest('[data-template-revisions]');
        if (revisions) { self.openRevisions(revisions.closest('[data-template-row]')); }
        var del = e.target.closest('[data-template-delete]');
        if (del) { self.remove(del.closest('[data-template-row]')); }

        var revisionDelete = e.target.closest('[data-template-revision-delete]');
        if (revisionDelete) { self.deleteRevision(revisionDelete.getAttribute('data-template-revision-delete')); return; }
        if (e.target.closest('[data-template-revisions-clear]')) { self.clearRevisions(); return; }
        if (e.target.closest('[data-template-revision-restore]')) { self.restoreRevision(); }
        var revisionOpen = e.target.closest('[data-template-revision-open]');
        if (revisionOpen) { self.loadRevision(revisionOpen.getAttribute('data-template-revision-open')); }
      });

      if (this.form) {
        this.form.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submit();
        });
      }

      document.addEventListener('submit', function (e) {
        var filter = e.target.closest('.templates-filter');
        if (!filter) { return; }
        e.preventDefault();
        self.applyFilters(filter, true);
      });

      document.addEventListener('input', function (e) {
        var filter = e.target.closest('.templates-filter');
        if (!filter || !e.target.matches('input[type="search"]')) { return; }
        clearTimeout(self.filterTimer);
        self.filterTimer = setTimeout(function () { self.applyFilters(filter, true); }, 350);
      });
      document.addEventListener('input', function (e) {
        if (!e.target.matches('[data-template-tags-search]')) { return; }
        self.filterTagPalette(e.target.value || '');
      });

      window.addEventListener('popstate', function () {
        self.applyFilterUrl(window.location.href, false);
      });

      document.addEventListener('keydown', function (e) {
        if (e.target && e.target.closest && e.target.closest('.CodeMirror')) {
          return;
        }
        if ((e.ctrlKey || e.metaKey) && String(e.key || '').toLowerCase() === 's' && self.isEditorDrawerOpen()) {
          e.preventDefault();
          self.submit(true);
          return;
        }
        if (e.key === 'Escape') {
          if (document.querySelector('.templates-code-field.is-fullscreen')) { self.toggleCodeFullscreen(false); }
          else {
            var tagsPanel = document.querySelector('[data-template-tags-panel]');
            if (tagsPanel && !tagsPanel.hidden) { self.toggleTagPalette(false); }
          }
        }
      });
      this.openFromLocation();
    },

    base: function () { return (this.form && this.form.getAttribute('data-base')) || Adminx.base(); },
    field: function (name) { return this.form.querySelector('[name="' + name + '"]'); },

    openFromLocation: function () {
      var id = parseInt(new URLSearchParams(window.location.search).get('edit'), 10) || 0;
      if (!id) { return; }
      var row = document.querySelector('[data-template-row][data-id="' + id + '"]');
      if (!row) { return; }
      if (Adminx.Drawer) { Adminx.Drawer.open('templateDrawer'); }
      this.fillEdit(row);
    },

    filterUrl: function (form) {
      var params = new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function (key) {
        if (String(params.get(key) || '') === '') { params.delete(key); }
      });
      var query = params.toString();
      return (form.getAttribute('action') || (this.base() + '/templates')) + (query ? '?' + query : '');
    },

    applyFilters: function (form, push) {
      if (!form) { return; }
      this.applyFilterUrl(this.filterUrl(form), push);
    },

    applyFilterUrl: function (url, push) {
      var self = this;
      Adminx.Loader.show();
      fetch(url, { method: 'GET', headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
        .then(function (res) {
          return res.text().then(function (html) {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            self.replaceList(html, url, push);
          });
        })
        .catch(function () { Adminx.Toast.show('Не удалось применить фильтры', 'error'); })
        .finally(function () { Adminx.Loader.hide(); });
    },

    replaceList: function (html, url, push) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      ['.templates-summary', '.templates-import-card', '.templates-panel'].forEach(function (selector) {
        var next = doc.querySelector(selector);
        var current = document.querySelector(selector);
        if (next && current) { current.replaceWith(next); }
      });
      if (push && window.history && window.history.pushState) {
        window.history.pushState({ adminxTemplatesFilters: true }, '', url);
      }
    },

    isEditorDrawerOpen: function () {
      var drawer = document.getElementById('templateDrawer');
      return !!(drawer && !drawer.hidden && this.form);
    },

    ajaxRefresh: function (message, keepOpen) {
      if (message) { Adminx.Toast.show(message, 'success'); }
      if (!keepOpen && Adminx.Drawer) { Adminx.Drawer.close(); }
      this.applyFilterUrl(window.location.href, false);
      if (keepOpen && window.Adminx.CodeEditor) {
        setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 120);
      }
    },

    resetFilters: function () {
      var form = document.querySelector('.templates-filter');
      if (!form) { return; }
      form.reset();
      Array.prototype.forEach.call(form.querySelectorAll('input[type="search"]'), function (input) { input.value = ''; });
      this.applyFilters(form, true);
    },

    clearErrors: function () {
      if (!this.form) { return; }
      this.form.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
      this.form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    },

    editor: function () {
      var textarea = this.field('template_text');
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    setCode: function (value) {
      var textarea = this.field('template_text');
      var editor = this.editor();
      if (textarea) { textarea.value = value || ''; }
      if (editor) { editor.setValue(value || ''); setTimeout(function () { editor.refresh(); }, 40); }
    },

    saveCode: function () {
      var editor = this.editor();
      if (editor) { editor.save(); }
    },

    toggleTagPalette: function (force) {
      var panel = document.querySelector('[data-template-tags-panel]');
      var button = document.querySelector('[data-template-tags-toggle]');
      if (!panel) { return; }
      var next = typeof force === 'boolean' ? force : panel.hidden;
      panel.hidden = !next;
      if (button) {
        button.classList.toggle('is-active', next);
        button.setAttribute('aria-expanded', next ? 'true' : 'false');
      }
      if (next) {
        var search = panel.querySelector('[data-template-tags-search]');
        if (search) {
          search.value = '';
          this.filterTagPalette('');
          setTimeout(function () { search.focus(); }, 40);
        }
      }
    },

    activateTagGroup: function (index) {
      var panel = document.querySelector('[data-template-tags-panel]');
      if (!panel) { return; }
      var search = panel.querySelector('[data-template-tags-search]');
      if (search && search.value) {
        search.value = '';
        this.filterTagPalette('');
      }
      panel.querySelectorAll('[data-template-tag-tab]').forEach(function (tab) {
        var active = tab.getAttribute('data-template-tag-tab') === String(index);
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panel.querySelectorAll('[data-template-tag-panel]').forEach(function (group) {
        group.classList.toggle('is-active', group.getAttribute('data-template-tag-panel') === String(index));
      });
    },

    filterTagPalette: function (query) {
      var panel = document.querySelector('[data-template-tags-panel]');
      if (!panel) { return; }
      query = String(query || '').trim().toLowerCase();
      panel.classList.toggle('is-searching', query !== '');
      var visibleCount = 0;
      panel.querySelectorAll('[data-template-tag]').forEach(function (button) {
        var text = button.textContent.toLowerCase();
        var visible = query === '' || text.indexOf(query) !== -1;
        button.hidden = !visible;
        if (visible) { visibleCount++; }
      });
      panel.querySelectorAll('[data-template-tag-group]').forEach(function (group) {
        group.hidden = query !== '' && !group.querySelector('[data-template-tag]:not([hidden])');
      });
      var empty = panel.querySelector('[data-template-tags-empty]');
      if (empty) { empty.hidden = visibleCount > 0; }
    },

    insertTag: function (button) {
      var value = button.getAttribute('data-template-tag') || '';
      var select = button.getAttribute('data-template-tag-select') || '';
      var editor = this.editor();
      if (!value) { return; }

      if (editor) {
        var start = editor.getCursor();
        var baseIndex = editor.indexFromPos(start);
        editor.replaceSelection(value, 'around');
        editor.focus();
        if (select && value.indexOf(select) !== -1) {
          var from = editor.posFromIndex(baseIndex + value.indexOf(select));
          var to = editor.posFromIndex(baseIndex + value.indexOf(select) + select.length);
          editor.setSelection(from, to);
        } else {
          editor.setCursor(editor.posFromIndex(baseIndex + value.length));
        }
        editor.save();
        this.toggleTagPalette(false);
        return;
      }

      var textarea = this.field('template_text');
      if (!textarea) { return; }
      var fromIndex = textarea.selectionStart || 0;
      var toIndex = textarea.selectionEnd || fromIndex;
      textarea.value = textarea.value.slice(0, fromIndex) + value + textarea.value.slice(toIndex);
      textarea.focus();
      if (select && value.indexOf(select) !== -1) {
        textarea.selectionStart = fromIndex + value.indexOf(select);
        textarea.selectionEnd = textarea.selectionStart + select.length;
      } else {
        textarea.selectionStart = textarea.selectionEnd = fromIndex + value.length;
      }
      this.toggleTagPalette(false);
    },

    setLintResult: function (message, kind) {
      var el = document.querySelector('[data-template-lint-result]');
      if (!el) { return; }
      el.textContent = message || '';
      el.classList.remove('is-ok', 'is-error');
      if (kind) { el.classList.add(kind === 'error' ? 'is-error' : 'is-ok'); }
    },

    lintCode: function () {
      if (!this.form) { return; }
      this.saveCode();
      this.setLintResult('Проверка...', '');
      var fd = new FormData();
      fd.set('_csrf', Adminx.csrf());
      fd.set('template_text', this.field('template_text') ? this.field('template_text').value : '');
      var self = this;
      Adminx.Ajax.post(this.base() + '/templates/lint', fd).then(function (payload) {
        var d = payload.data || {};
        if (d.success) {
          self.setLintResult(d.message || 'Синтаксис PHP без ошибок.', 'ok');
          Adminx.Toast.show(d.message || 'Синтаксис PHP без ошибок.', 'success');
          return;
        }
        var detail = d.errors && d.errors.template_text ? d.errors.template_text : (d.message || 'В PHP-коде есть ошибка.');
        self.setLintResult(detail, 'error');
        Adminx.Toast.show(d.message || 'В PHP-коде есть ошибка.', 'error');
      }).catch(function () {
        self.setLintResult('Не удалось выполнить проверку.', 'error');
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    toggleCodeFullscreen: function (force) {
      var field = document.querySelector('.templates-code-field');
      var button = document.querySelector('[data-template-code-fullscreen]');
      if (!field) { return; }
      var next = typeof force === 'boolean' ? force : !field.classList.contains('is-fullscreen');
      field.classList.toggle('is-fullscreen', next);
      document.body.classList.toggle('templates-code-fullscreen-open', next);
      if (button) {
        button.setAttribute('data-tooltip', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.setAttribute('aria-label', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.innerHTML = next ? '<i class="ti ti-arrows-minimize"></i>' : '<i class="ti ti-arrows-maximize"></i>';
      }
      var editor = this.editor();
      if (editor) {
        setTimeout(function () {
          editor.refresh();
          editor.setSize('100%', next ? '100%' : 560);
        }, 40);
      }
    },

    revisionEditor: function () {
      var textarea = document.querySelector('[data-template-revision-code]');
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    setRevisionCode: function (value) {
      var textarea = document.querySelector('[data-template-revision-code]');
      var editor = this.revisionEditor();
      if (textarea) { textarea.value = value || ''; }
      if (editor) { editor.setValue(value || ''); setTimeout(function () { editor.refresh(); }, 40); }
    },

    fillNew: function () {
      if (!this.form) { return; }
      this.clearErrors();
      this.form.reset();
      this.field('id').value = '';
      this.setCode('');
      this.setLintResult('', '');
      this.toggleCodeFullscreen(false);
      document.getElementById('templateDrawerTitle').textContent = 'Новый шаблон';
      if (window.Adminx.CodeEditor) { setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 80); }
    },

    fillEdit: function (row) {
      if (!row) { return; }
      this.clearErrors();
      this.setLintResult('', '');
      this.toggleCodeFullscreen(false);
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/templates/' + row.dataset.id).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (!d.success || !d.data) {
          Adminx.Toast.show(d.message || 'Не удалось загрузить шаблон', 'error');
          return;
        }
        var item = d.data;
        self.field('id').value = item.Id || '';
        self.field('template_title').value = item.template_title || '';
        self.setCode(item.template_text || '');
        document.getElementById('templateDrawerTitle').textContent = 'Шаблон: ' + (item.template_title || ('#' + item.Id));
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    submit: function (stay) {
      this.clearErrors();
      this.saveCode();
      var id = (this.field('id').value || '').trim();
      var url = this.base() + '/templates' + (id ? '/' + id : '');
      var fd = new FormData(this.form);
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.post(url, fd).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (d.success) {
          if (stay && d.data && d.data.id) {
            self.field('id').value = d.data.id;
            document.getElementById('templateDrawerTitle').textContent = 'Шаблон: ' + (self.field('template_title').value || ('#' + d.data.id));
          }
          self.ajaxRefresh(d.message || 'Сохранено', !!stay);
          return;
        }
        Object.keys(d.errors || {}).forEach(function (field) {
          var span = self.form.querySelector('[data-error="' + field + '"]');
          var input = self.field(field);
          if (span) { span.textContent = d.errors[field]; }
          if (input) { input.classList.add('is-invalid'); }
        });
        Adminx.Toast.show(d.message || 'Не удалось сохранить', 'error');
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    copy: function (row) {
      if (!row) { return; }
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'info',
        title: 'Создать копию шаблона?',
        message: 'Будет создана копия «' + row.dataset.title + '» с новым названием.',
        confirmLabel: 'Создать копию',
        onConfirm: function () {
          var fd = new FormData();
          fd.set('_csrf', Adminx.csrf());
          fd.set('title', row.dataset.title + ' (копия)');
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/templates/' + row.dataset.id + '/copy', fd).then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Templates.ajaxRefresh(d.message || 'Копия создана'); }
            else { Adminx.Toast.show(d.message || 'Не удалось создать копию', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    clearCache: function (row) {
      if (!row) { return; }
      Adminx.Ajax.post(this.base() + '/templates/' + row.dataset.id + '/clear-cache').then(function (payload) {
        var d = payload.data || {};
        Adminx.Toast.show(d.message || (d.success ? 'Кеш очищен' : 'Не удалось очистить кеш'), d.success ? 'success' : 'error');
      }).catch(function () {
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    remove: function (row) {
      if (!row) { return; }
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить шаблон?',
        message: '«' + row.dataset.title + '» будет удалён из таблицы шаблонов. Если шаблон используется, сервер отменит удаление.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/templates/' + row.dataset.id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Templates.ajaxRefresh(d.message || 'Шаблон удалён'); }
            else { Adminx.Toast.show(d.message || 'Не удалось удалить', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    openRevisions: function (row) {
      if (!row) { return; }
      this.currentRevisionId = 0;
      this.currentRevisionTemplateId = row.dataset.id || 0;
      var title = document.getElementById('templateRevisionsDrawerTitle');
      var subtitle = document.getElementById('templateRevisionsDrawerSubtitle');
      var list = document.querySelector('[data-template-revisions-list]');
      var count = document.querySelector('[data-template-revisions-count]');
      var revisionTitle = document.querySelector('[data-template-revision-title]');
      var revisionMeta = document.querySelector('[data-template-revision-meta]');
      var fields = document.querySelector('[data-template-revision-fields]');
      var restore = document.querySelector('[data-template-revision-restore]');
      var remove = document.querySelector('[data-template-revision-delete]');
	  var comparison = item.comparison || {};
	  var changed = comparison.summary ? parseInt(comparison.summary.total_changed || 0, 10) : 0;
      var clear = document.querySelector('[data-template-revisions-clear]');

      if (title) { title.textContent = 'Ревизии: ' + (row.dataset.title || ('#' + row.dataset.id)); }
      if (subtitle) { subtitle.textContent = 'Шаблон #' + (row.dataset.id || ''); }
      if (list) { list.innerHTML = '<div class="empty-state">Загрузка...</div>'; }
      if (count) { count.textContent = 'Загрузка...'; }
      if (revisionTitle) { revisionTitle.textContent = 'Выберите ревизию'; }
      if (revisionMeta) { revisionMeta.textContent = 'Код и метаданные появятся после выбора снимка.'; }
      if (fields) { fields.innerHTML = ''; }
      if (restore) { restore.disabled = true; }
      if (remove) { remove.disabled = true; remove.removeAttribute('data-template-revision-delete'); }
      if (clear) { clear.disabled = true; }
      this.setRevisionCode('');
      if (Adminx.Drawer) { Adminx.Drawer.open('templateRevisionsDrawer'); }
      if (window.Adminx.CodeEditor) { setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 80); }

      this.refreshRevisions();
    },

    refreshRevisions: function () {
      if (!this.currentRevisionTemplateId) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/templates/' + self.currentRevisionTemplateId + '/revisions').then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (!d.success || !d.data) {
          Adminx.Toast.show(d.message || 'Не удалось загрузить ревизии', 'error');
          return;
        }
        self.renderRevisions(d.data.revisions || []);
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    renderRevisions: function (items) {
      var list = document.querySelector('[data-template-revisions-list]');
      var count = document.querySelector('[data-template-revisions-count]');
      var clear = document.querySelector('[data-template-revisions-clear]');
      if (count) { count.textContent = items.length ? (items.length + ' снимков') : 'История пока пустая'; }
      if (clear) { clear.disabled = !items.length; }
      if (!list) { return; }
      if (!items.length) {
        list.innerHTML = '<div class="empty-state">Ревизий пока нет. Первый снимок появится после сохранения шаблона.</div>';
        this.currentRevisionId = 0;
        this.showRevisionEmpty();
        return;
      }
      list.innerHTML = items.map(function (item) {
        return '<div class="list-row templates-revision-row" data-template-revision-open="' + item.id + '">' +
          '<span class="icon-tile templates-revision-icon"><i class="ti ti-history"></i></span>' +
          '<div class="templates-revision-row-main">' +
          '<b>Ревизия #' + item.id + '</b><span class="badge ' + esc(item.badge || 'badge-gray') + '">' + esc(item.action_label || item.action) + '</span>' +
          '<div class="text-muted text-xs">' + esc(item.created_label || '-') + (item.author_name ? ' · ' + esc(item.author_name) : '') + (item.comment ? ' · ' + esc(item.comment) : '') + '</div>' +
          '</div>' +
          '<button class="btn btn-ghost btn-icon btn-sm templates-revision-delete" type="button" data-template-revision-delete="' + item.id + '" data-tooltip="Удалить ревизию" aria-label="Удалить ревизию"><i class="ti ti-trash"></i></button>' +
          '</div>';
      }).join('');
      this.loadRevision(items[0].id);
    },

    showRevisionEmpty: function () {
      var title = document.querySelector('[data-template-revision-title]');
      var meta = document.querySelector('[data-template-revision-meta]');
      var fields = document.querySelector('[data-template-revision-fields]');
      var restore = document.querySelector('[data-template-revision-restore]');
      var remove = document.querySelector('[data-template-revision-delete]');
      if (title) { title.textContent = 'Выберите ревизию'; }
      if (meta) { meta.textContent = 'Код и метаданные появятся после выбора снимка.'; }
      if (fields) { fields.innerHTML = ''; }
      if (restore) { restore.disabled = true; }
      if (remove) { remove.disabled = true; remove.removeAttribute('data-template-revision-delete'); }
      this.setRevisionCode('');
    },

    loadRevision: function (id) {
      id = parseInt(id, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/templates/revisions/' + id).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (!d.success || !d.data) {
          Adminx.Toast.show(d.message || 'Не удалось загрузить ревизию', 'error');
          return;
        }
        self.showRevision(d.data);
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    showRevision: function (item) {
      this.currentRevisionId = parseInt(item.id, 10) || 0;
      document.querySelectorAll('[data-template-revision-open]').forEach(function (row) {
        row.classList.toggle('is-active', row.getAttribute('data-template-revision-open') === String(item.id));
      });

      var snapshot = item.snapshot || {};
      var title = document.querySelector('[data-template-revision-title]');
      var meta = document.querySelector('[data-template-revision-meta]');
      var fields = document.querySelector('[data-template-revision-fields]');
      var restore = document.querySelector('[data-template-revision-restore]');
      var remove = document.querySelector('[data-template-revision-delete]');

      if (title) { title.textContent = '#' + item.id + ' · ' + (item.action_label || item.action || 'Ревизия'); }
      if (meta) {
        meta.textContent = (item.created_label || '-') + (item.author_name ? ' · ' + item.author_name : '') + (item.text_size_label ? ' · ' + item.text_size_label : '') + ' · ' + (changed ? ('изменений: ' + changed) : 'совпадает с текущим');
      }
      if (fields) {
        fields.innerHTML =
          '<span><b>Название</b><em>' + esc(snapshot.template_title || '-') + '</em></span>' +
          '<span><b>ID</b><em class="mono">#' + esc(snapshot.Id || '-') + '</em></span>' +
          '<span><b>Размер</b><em>' + esc(item.text_size_label || '-') + '</em></span>';
      }
      if (restore) { restore.disabled = !this.currentRevisionId || !changed; }
      if (remove) {
        remove.disabled = !this.currentRevisionId;
        if (this.currentRevisionId) { remove.setAttribute('data-template-revision-delete', String(this.currentRevisionId)); }
      }
      this.setRevisionCode(item.code || '');
    },

    deleteRevision: function (id) {
      id = parseInt(id || this.currentRevisionId, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить ревизию?',
        message: 'Снимок будет удалён без восстановления.',
        confirmLabel: 'Удалить',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/templates/revisions/' + id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) {
              Adminx.Toast.show(d.message || 'Ревизия удалена', 'success');
              self.currentRevisionId = 0;
              self.refreshRevisions();
            } else {
              Adminx.Toast.show(d.message || 'Не удалось удалить ревизию', 'error');
            }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    clearRevisions: function () {
      if (!this.currentRevisionTemplateId) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить все ревизии?',
        message: 'Будет очищена вся история снимков этого шаблона.',
        confirmLabel: 'Удалить все',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/templates/' + self.currentRevisionTemplateId + '/revisions/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) {
              Adminx.Toast.show(d.message || 'Ревизии удалены', 'success');
              self.currentRevisionId = 0;
              self.refreshRevisions();
            } else {
              Adminx.Toast.show(d.message || 'Не удалось удалить ревизии', 'error');
            }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    restoreRevision: function () {
      if (!this.currentRevisionId) { return; }
      var base = this.base();
      var id = this.currentRevisionId;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Восстановить ревизию?',
        message: 'Текущее состояние шаблона будет сохранено отдельным снимком перед восстановлением.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/templates/revisions/' + id + '/restore').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Templates.ajaxRefresh(d.message || 'Шаблон восстановлен'); }
            else { Adminx.Toast.show(d.message || 'Не удалось восстановить ревизию', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Templates.init(); });
  } else {
    Adminx.Templates.init();
  }
})(window, document);
