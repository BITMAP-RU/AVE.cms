/**
 * JS раздела «Системные блоки»: drawer-CRUD, копирование и очистка кеша.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  Adminx.Blocks = {
    form: null,
    groupForm: null,
    currentRevisionId: 0,
    currentRevisionBlockId: 0,
    filterTimer: null,
    aliasTimer: null,
    aliasState: 'empty',
    dragGroupRow: null,
    activeEditorMode: 'php',

    init: function () {
      this.form = document.getElementById('blockForm');
      this.groupForm = document.getElementById('blockGroupForm');
      this.bindTabs();
      var self = this;

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-block-new]')) { self.fillNew(); }
        if (e.target.closest('[data-blocks-filter-reset]')) { self.resetFilters(); }
        if (e.target.closest('[data-block-lint]')) { self.lintCode(); }
        if (e.target.closest('[data-block-code-fullscreen]')) { self.toggleCodeFullscreen(); }
        if (e.target.closest('[data-block-submit-stay]')) { self.submit(true); }
        var edit = e.target.closest('[data-block-edit]');
        if (edit) { self.fillEdit(edit.closest('[data-block-row]')); }
        var tagCopy = e.target.closest('[data-block-tag-copy]');
        if (tagCopy) { self.copyTag(tagCopy.getAttribute('data-block-tag-copy')); }
        var copy = e.target.closest('[data-block-copy]');
        if (copy) { self.copy(copy.closest('[data-block-row]')); }
        var cache = e.target.closest('[data-block-cache]');
        if (cache) { self.clearCache(cache.closest('[data-block-row]')); }
        var revisions = e.target.closest('[data-block-revisions]');
        if (revisions) { self.openRevisions(revisions.closest('[data-block-row]')); }
        var del = e.target.closest('[data-block-delete]');
        if (del) { self.remove(del.closest('[data-block-row]')); }

        var revisionDelete = e.target.closest('[data-revision-delete]');
        if (revisionDelete) { self.deleteRevision(revisionDelete.getAttribute('data-revision-delete')); return; }
        if (e.target.closest('[data-revisions-clear]')) { self.clearRevisions(); return; }
        if (e.target.closest('[data-revision-restore]')) { self.restoreRevision(); }
        var revisionOpen = e.target.closest('[data-revision-open]');
        if (revisionOpen) { self.loadRevision(revisionOpen.getAttribute('data-revision-open')); }

        if (e.target.closest('[data-block-group-new]')) { self.fillGroupNew(); }
        var groupEdit = e.target.closest('[data-block-group-edit]');
        if (groupEdit) { self.fillGroupEdit(groupEdit.closest('[data-block-group-row]')); }
        var groupDelete = e.target.closest('[data-block-group-delete]');
        if (groupDelete) { self.removeGroup(groupDelete.closest('[data-block-group-row]')); }
      });

      if (this.form) {
        this.form.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submit();
        });
      }
      document.addEventListener('submit', function (e) {
        var filter = e.target.closest('.blocks-filter');
        if (!filter) { return; }
        e.preventDefault();
        self.applyFilters(filter, true);
      });
      document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-block-editor-mode]')) {
          self.setEditorMode(e.target.value);
          return;
        }
        var filter = e.target.closest('.blocks-filter');
        if (!filter || !e.target.matches('select')) { return; }
        self.applyFilters(filter, true);
      });
      document.addEventListener('input', function (e) {
        var filter = e.target.closest('.blocks-filter');
        if (!filter || !e.target.matches('input[type="search"]')) { return; }
        clearTimeout(self.filterTimer);
        self.filterTimer = setTimeout(function () { self.applyFilters(filter, true); }, 350);
      });
      document.addEventListener('input', function (e) {
        if (!self.form || e.target !== self.field('sysblock_alias')) { return; }
        self.scheduleAliasCheck();
      });
      document.addEventListener('blur', function (e) {
        if (!self.form || e.target !== self.field('sysblock_alias')) { return; }
        self.checkAlias(true);
      }, true);
      window.addEventListener('popstate', function () {
        var form = document.querySelector('.blocks-filter');
        if (!form) { return; }
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
        if (e.key === 'Escape' && document.querySelector('.blocks-code-field.is-fullscreen')) {
          self.toggleCodeFullscreen(false);
        }
      });
      document.addEventListener('dragstart', function (e) { self.groupDragStart(e); });
      document.addEventListener('dragover', function (e) { self.groupDragOver(e); });
      document.addEventListener('drop', function (e) { self.groupDrop(e); });
      document.addEventListener('dragend', function () { self.groupDragEnd(); });
      if (this.groupForm) {
        this.groupForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitGroup();
        });
      }
      this.openFromLocation();
    },

    openFromLocation: function () {
      var id = parseInt(new URLSearchParams(window.location.search).get('edit'), 10) || 0;
      if (!id) { return; }
      var row = document.querySelector('[data-block-row][data-id="' + id + '"]');
      if (!row) { return; }
      if (Adminx.Drawer) { Adminx.Drawer.open('blockDrawer'); }
      this.fillEdit(row);
    },

    base: function () { return (this.form && this.form.getAttribute('data-base')) || Adminx.base(); },

    filterUrl: function (form) {
      var params = new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function (key) {
        var value = String(params.get(key) || '');
        if (value === '' || value === '0') { params.delete(key); }
      });
      var query = params.toString();
      return (form.getAttribute('action') || (this.base() + '/blocks')) + (query ? '?' + query : '');
    },

    applyFilters: function (form, push) {
      if (!form) { return; }
      var url = this.filterUrl(form);
      this.applyFilterUrl(url, push);
    },

    replaceBlocksList: function (html, url, push) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var active = document.querySelector('[data-blocks-panel].active');
      var activeName = active ? active.getAttribute('data-blocks-panel') : 'blocks';
      var selectors = [
        '.blocks-summary',
        '.blocks-import-card',
        '.blocks-tabs',
        '[data-blocks-panel="blocks"]',
        '[data-blocks-panel="groups"]'
      ];
      var replaced = 0;

      selectors.forEach(function (selector) {
        var next = doc.querySelector(selector);
        var current = document.querySelector(selector);
        if (next && current) {
          current.replaceWith(next);
          replaced++;
        }
      });

      if (!replaced) {
        window.location.href = url;
        return;
      }

      if (push && window.history && window.history.pushState) {
        window.history.pushState({ adminxBlocksFilters: true }, '', url);
      }
      this.bindTabs();
      this.activateTab(activeName);
    },

    activateTab: function (name) {
      name = name || 'blocks';
      document.querySelectorAll('[data-blocks-tab]').forEach(function (tab) {
        tab.setAttribute('aria-selected', tab.getAttribute('data-blocks-tab') === name ? 'true' : 'false');
      });
      document.querySelectorAll('[data-blocks-panel]').forEach(function (panel) {
        panel.classList.toggle('active', panel.getAttribute('data-blocks-panel') === name);
      });
    },

    isEditorDrawerOpen: function () {
      var drawer = document.getElementById('blockDrawer');
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
      var form = document.querySelector('.blocks-filter');
      if (!form) { return; }
      form.reset();
      Array.prototype.forEach.call(form.querySelectorAll('input[type="search"]'), function (input) { input.value = ''; });
      Array.prototype.forEach.call(form.querySelectorAll('select'), function (select) { select.selectedIndex = 0; });
      this.applyFilters(form, true);
    },

    applyFilterUrl: function (url, push) {
      var self = this;
      Adminx.Loader.show();
      fetch(url, {
        method: 'GET',
        headers: { 'Accept': 'text/html' },
        credentials: 'same-origin'
      }).then(function (res) {
        return res.text().then(function (html) {
          if (!res.ok) { throw new Error('HTTP ' + res.status); }
          self.replaceBlocksList(html, url, push);
        });
      }).catch(function () {
        Adminx.Toast.show('Не удалось применить фильтры', 'error');
      }).finally(function () {
        Adminx.Loader.hide();
      });
    },

    bindTabs: function () {
      document.querySelectorAll('[data-blocks-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
          var name = tab.getAttribute('data-blocks-tab');
          document.querySelectorAll('[data-blocks-tab]').forEach(function (t) {
            t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
          });
          document.querySelectorAll('[data-blocks-panel]').forEach(function (panel) {
            panel.classList.toggle('active', panel.getAttribute('data-blocks-panel') === name);
          });
          if (window.Adminx.CodeEditor) { setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 40); }
        });
      });
    },

    field: function (name) { return this.form.querySelector('[name="' + name + '"]'); },
    groupField: function (name) { return this.groupForm.querySelector('[name="' + name + '"]'); },

    clearErrors: function () {
      this.form.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
      this.form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    },

    setLintResult: function (message, kind) {
      var el = document.querySelector('[data-block-lint-result]');
      if (!el) { return; }
      el.textContent = message || '';
      el.classList.remove('is-ok', 'is-error');
      if (kind) { el.classList.add(kind === 'error' ? 'is-error' : 'is-ok'); }
    },

    submitButton: function () {
      return this.form ? this.form.querySelector('[data-block-submit]') : null;
    },

    submitButtons: function () {
      return this.form ? this.form.querySelectorAll('[data-block-submit], [data-block-submit-stay]') : [];
    },

    setAliasState: function (state, message) {
      this.aliasState = state;
      var input = this.field('sysblock_alias');
      var hint = this.form ? this.form.querySelector('[data-alias-hint]') : null;
      var error = this.form ? this.form.querySelector('[data-error="sysblock_alias"]') : null;
      if (input) { input.classList.toggle('is-invalid', state === 'invalid' || state === 'taken' || state === 'error'); }
      if (hint) {
        hint.textContent = message || 'A-Z, 0-9, - и _';
        hint.classList.remove('is-ok', 'is-checking');
        if (state === 'ok') { hint.classList.add('is-ok'); }
        if (state === 'checking') { hint.classList.add('is-checking'); }
      }
      if (error) {
        error.textContent = (state === 'invalid' || state === 'taken' || state === 'error') ? (message || '') : '';
      }
      this.updateSubmitState();
    },

    updateSubmitState: function () {
      var disabled = !(this.aliasState === 'ok');
      Array.prototype.forEach.call(this.submitButtons(), function (button) {
        button.disabled = disabled;
      });
    },

    localAliasError: function (value) {
      value = String(value || '').trim();
      if (value === '') { return 'Укажите алиас'; }
      if (!/^[A-Za-z0-9-_]{1,20}$/i.test(value) || isFinite(Number(value))) {
        return 'Только A-Z, 0-9, дефис и подчёркивание, до 20 символов; не число';
      }
      return '';
    },

    scheduleAliasCheck: function () {
      clearTimeout(this.aliasTimer);
      var value = this.field('sysblock_alias') ? this.field('sysblock_alias').value : '';
      var local = this.localAliasError(value);
      if (local) {
        this.setAliasState('invalid', local);
        return;
      }
      this.setAliasState('checking', 'Проверяем уникальность...');
      var self = this;
      this.aliasTimer = setTimeout(function () { self.checkAlias(false); }, 300);
    },

    checkAlias: function (immediate) {
      clearTimeout(this.aliasTimer);
      var input = this.field('sysblock_alias');
      if (!input) { return; }
      var value = input.value || '';
      var local = this.localAliasError(value);
      if (local) {
        this.setAliasState('invalid', local);
        return;
      }
      this.setAliasState('checking', immediate ? 'Проверяем уникальность...' : 'Проверяем уникальность...');
      var id = this.field('id') ? this.field('id').value : '';
      var url = this.base() + '/blocks/alias-check?alias=' + encodeURIComponent(value.trim()) + '&id=' + encodeURIComponent(id || '0');
      var self = this;
      Adminx.Ajax.request(url).then(function (payload) {
        var d = payload.data || {};
        var data = d.data || {};
        if (!d.success) {
          self.setAliasState('error', d.message || 'Не удалось проверить алиас');
          return;
        }
        if (!data.valid) {
          self.setAliasState('invalid', d.message || 'Некорректный алиас');
          return;
        }
        if (!data.available) {
          self.setAliasState('taken', d.message || 'Такой алиас уже используется');
          return;
        }
        self.setAliasState('ok', d.message || 'Алиас свободен');
      }).catch(function () {
        self.setAliasState('error', 'Не удалось проверить алиас');
      });
    },

    setChecked: function (name, value) {
      var el = this.field(name);
      if (el) { el.checked = String(value) === '1'; }
    },

    setEditorMode: function (mode) {
      mode = ['php', 'html', 'rich', 'text'].indexOf(mode) !== -1 ? mode : 'text';
      var textarea = this.field('sysblock_text');
      var plain = this.form ? this.form.querySelector('[data-block-plain-editor]') : null;
      var codeWrap = this.form ? this.form.querySelector('[data-block-code-editor]') : null;
      var richTextarea = this.form ? this.form.querySelector('[data-block-rich-editor] textarea') : null;
      var richWrap = this.form ? this.form.querySelector('[data-block-rich-editor]') : null;
      var currentValue = '';
      var editor = this.editor();
      if (this.activeEditorMode === 'text' && plain) {
        currentValue = plain.value || '';
        if (textarea) { textarea.value = currentValue; }
        if (editor) { editor.setValue(currentValue); editor.save(); }
      } else if (this.activeEditorMode === 'rich' && richTextarea) {
        currentValue = this.richValue();
        if (textarea) { textarea.value = currentValue; }
        if (editor) { editor.setValue(currentValue); editor.save(); }
      } else {
        if (editor) { editor.save(); }
        currentValue = textarea ? textarea.value : '';
        if (plain) { plain.value = currentValue; }
      }

      if (richTextarea && mode === 'rich') { this.setRichValue(currentValue); }
      if (plain && mode === 'text') { plain.value = currentValue; }

      this.setChecked('sysblock_eval', mode === 'php' ? '1' : '0');
      this.setChecked('sysblock_visual', mode === 'rich' ? '1' : '0');
      var select = this.form ? this.form.querySelector('[data-block-editor-mode]') : null;
      var lint = this.form ? this.form.querySelector('[data-block-lint]') : null;
      var title = this.form ? this.form.querySelector('[data-block-editor-title]') : null;
      var hint = this.form ? this.form.querySelector('[data-block-editor-mode-hint]') : null;
      var labels = {
        php: { title: 'PHP-код блока', hint: 'CodeMirror для PHP; код выполняется на сайте' },
        html: { title: 'HTML и теги AVE.cms', hint: 'CodeMirror для HTML; PHP не выполняется, теги AVE.cms обрабатываются' },
        rich: { title: 'Содержимое блока', hint: 'Визуальное редактирование HTML; PHP не выполняется, теги AVE.cms обрабатываются' },
        text: { title: 'Текст блока', hint: 'Обычное текстовое поле без подсветки; PHP не выполняется' }
      };
      if (select) { select.value = mode; }
      if (lint) { lint.hidden = mode !== 'php'; }
      if (title) { title.textContent = labels[mode].title; }
      if (hint) { hint.textContent = labels[mode].hint; }
      if (codeWrap) { codeWrap.hidden = mode === 'text' || mode === 'rich'; }
      if (richWrap) { richWrap.hidden = mode !== 'rich'; }
      if (plain) { plain.hidden = mode !== 'text'; }
      this.activeEditorMode = mode;
      if (editor) {
        editor.setOption('mode', mode === 'php' ? 'application/x-httpd-php' : (mode === 'html' ? 'htmlmixed' : 'text/plain'));
        if (mode === 'php' || mode === 'html') { setTimeout(function () { editor.refresh(); }, 20); }
      }
    },

    syncEditorMode: function () {
      var modeField = this.field('sysblock_editor');
      var evalField = this.field('sysblock_eval');
      var visualField = this.field('sysblock_visual');
      var mode = modeField ? modeField.value : '';
      if (['php', 'html', 'rich', 'text'].indexOf(mode) === -1) {
        mode = evalField && evalField.checked ? 'php' : (visualField && visualField.checked ? 'rich' : 'html');
      }
      this.setEditorMode(mode);
    },

    editor: function () {
      var textarea = this.field('sysblock_text');
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    richValue: function () {
      var textarea = this.form ? this.form.querySelector('[data-block-rich-editor] textarea') : null;
      if (!textarea) { return ''; }
      if (textarea._adminxTiptap) { return textarea._adminxTiptap.getHTML(); }
      return textarea.value || '';
    },

    setRichValue: function (value) {
      var textarea = this.form ? this.form.querySelector('[data-block-rich-editor] textarea') : null;
      if (!textarea) { return; }
      value = value || '';
      textarea.value = value;
      textarea.removeAttribute('data-rich-editor-dirty');
      if (textarea._adminxTiptap && textarea._adminxTiptap.getHTML() !== value) {
        textarea._adminxTiptap.commands.setContent(value || '<p></p>');
        textarea.removeAttribute('data-rich-editor-dirty');
      }
    },

    setCode: function (value) {
      var textarea = this.field('sysblock_text');
      var editor = this.editor();
      var plain = this.form ? this.form.querySelector('[data-block-plain-editor]') : null;
      if (textarea) { textarea.value = value || ''; }
      if (plain) { plain.value = value || ''; }
      this.setRichValue(value || '');
      if (editor) { editor.setValue(value || ''); setTimeout(function () { editor.refresh(); }, 40); }
    },

    saveCode: function () {
      var editor = this.editor();
      var textarea = this.field('sysblock_text');
      var plain = this.form ? this.form.querySelector('[data-block-plain-editor]') : null;
      if (this.activeEditorMode === 'text' && plain) {
        if (textarea) { textarea.value = plain.value || ''; }
        if (editor) { editor.setValue(plain.value || ''); editor.save(); }
      } else if (this.activeEditorMode === 'rich') {
        if (textarea) { textarea.value = this.richValue(); }
        if (editor) { editor.setValue(textarea ? textarea.value : ''); editor.save(); }
      } else if (editor) {
        editor.save();
        if (plain) { plain.value = textarea ? textarea.value : ''; }
      }
    },

    lintCode: function () {
      if (!this.form) { return; }
      this.saveCode();
      this.setLintResult('Проверка...', '');
      var fd = new FormData();
      fd.set('_csrf', Adminx.csrf());
      fd.set('sysblock_eval', this.field('sysblock_eval') && this.field('sysblock_eval').checked ? '1' : '0');
      fd.set('sysblock_text', this.field('sysblock_text') ? this.field('sysblock_text').value : '');
      var self = this;
      Adminx.Ajax.post(this.base() + '/blocks/lint', fd).then(function (payload) {
        var d = payload.data || {};
        if (d.success) {
          self.setLintResult(d.message || 'Синтаксис PHP без ошибок.', 'ok');
          Adminx.Toast.show(d.message || 'Синтаксис PHP без ошибок.', 'success');
          return;
        }
        var detail = d.errors && d.errors.sysblock_text ? d.errors.sysblock_text : (d.message || 'В PHP-коде есть ошибка.');
        self.setLintResult(detail, 'error');
        Adminx.Toast.show(d.message || 'В PHP-коде есть ошибка.', 'error');
      }).catch(function () {
        self.setLintResult('Не удалось выполнить проверку.', 'error');
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    toggleCodeFullscreen: function (force) {
      var field = document.querySelector('.blocks-code-field');
      var button = document.querySelector('[data-block-code-fullscreen]');
      if (!field) { return; }
      var next = typeof force === 'boolean' ? force : !field.classList.contains('is-fullscreen');
      field.classList.toggle('is-fullscreen', next);
      document.body.classList.toggle('blocks-code-fullscreen-open', next);
      if (button) {
        button.setAttribute('data-tooltip', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.setAttribute('aria-label', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.innerHTML = next ? '<i class="ti ti-arrows-minimize"></i>' : '<i class="ti ti-arrows-maximize"></i>';
      }
      var editor = this.editor();
      if (editor) {
        setTimeout(function () {
          editor.refresh();
          if (next) { editor.setSize('100%', '100%'); }
          else { editor.setSize('100%', 520); }
        }, 40);
      }
      var richTextarea = this.form ? this.form.querySelector('[data-block-rich-editor] textarea') : null;
      if (richTextarea && richTextarea._adminxTiptap && this.activeEditorMode === 'rich' && next) {
        setTimeout(function () { richTextarea._adminxTiptap.commands.focus(); }, 40);
      }
    },

    revisionEditor: function () {
      var textarea = document.querySelector('[data-revision-code]');
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    setRevisionCode: function (value) {
      var textarea = document.querySelector('[data-revision-code]');
      var editor = this.revisionEditor();
      if (textarea) { textarea.value = value || ''; }
      if (editor) { editor.setValue(value || ''); setTimeout(function () { editor.refresh(); }, 40); }
    },

    fillNew: function () {
      this.clearErrors();
      this.form.reset();
      this.field('id').value = '';
      this.setAliasState('empty', 'A-Z, 0-9, - и _');
      this.setChecked('sysblock_active', '1');
      this.setChecked('sysblock_eval', '1');
      this.setChecked('sysblock_external', '0');
      this.setChecked('sysblock_ajax', '0');
      this.setChecked('sysblock_visual', '0');
		var editorMode = this.field('sysblock_editor');
		editorMode.value = editorMode.querySelector('option[value="php"]') ? 'php' : 'html';
      this.syncEditorMode();
      this.setCode('');
      this.setLintResult('', '');
      this.toggleCodeFullscreen(false);
      document.getElementById('blockDrawerTitle').textContent = 'Новый блок';
      if (window.Adminx.CodeEditor) { setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 80); }
    },

    fillEdit: function (row) {
      if (!row) { return; }
      this.clearErrors();
      this.setLintResult('', '');
      this.toggleCodeFullscreen(false);
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/blocks/' + row.dataset.id).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (!d.success || !d.data) {
          Adminx.Toast.show(d.message || 'Не удалось загрузить блок', 'error');
          return;
        }
        var item = d.data;
        self.field('id').value = item.id || '';
        self.field('sysblock_name').value = item.sysblock_name || '';
        self.field('sysblock_alias').value = item.sysblock_alias || '';
        self.setAliasState('ok', 'Текущий алиас');
        self.field('sysblock_description').value = item.sysblock_description || '';
        self.field('sysblock_group_id').value = item.sysblock_group_id || '0';
        self.setChecked('sysblock_active', item.sysblock_active);
        self.setChecked('sysblock_eval', item.sysblock_eval);
        self.setChecked('sysblock_external', item.sysblock_external);
        self.setChecked('sysblock_ajax', item.sysblock_ajax);
        self.setChecked('sysblock_visual', item.sysblock_visual);
        self.field('sysblock_editor').value = item.sysblock_editor || '';
        self.syncEditorMode();
        self.setCode(item.sysblock_text || '');
        document.getElementById('blockDrawerTitle').textContent = 'Блок: ' + (item.sysblock_name || ('#' + item.id));
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    submit: function (stay) {
      this.clearErrors();
      this.saveCode();
      if (this.aliasState !== 'ok') {
        this.checkAlias(true);
        Adminx.Toast.show('Проверьте алиас блока', 'error');
        return;
      }
      var id = (this.field('id').value || '').trim();
      var url = this.base() + '/blocks' + (id ? '/' + id : '');
      var fd = new FormData(this.form);
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.post(url, fd).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (d.success) {
          if (stay && d.data && d.data.id) {
            self.field('id').value = d.data.id;
            document.getElementById('blockDrawerTitle').textContent = 'Блок: ' + (self.field('sysblock_name').value || ('#' + d.data.id));
          }
          if (stay) { self.setAliasState('ok', 'Текущий алиас'); }
          self.ajaxRefresh(d.message || 'Сохранено', !!stay);
          return;
        }
        Object.keys(d.errors || {}).forEach(function (field) {
          var span = self.form.querySelector('[data-error="' + field + '"]');
          var input = self.field(field);
          if (span) { span.textContent = d.errors[field]; }
          if (input) { input.classList.add('is-invalid'); }
          if (field === 'sysblock_alias') { self.setAliasState('taken', d.errors[field]); }
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
        title: 'Создать копию блока?',
        message: 'Будет создана отключённая копия «' + row.dataset.name + '» с новым алиасом.',
        confirmLabel: 'Создать копию',
        onConfirm: function () {
          var fd = new FormData();
          fd.set('_csrf', Adminx.csrf());
          fd.set('name', row.dataset.name + ' (копия)');
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/blocks/' + row.dataset.id + '/copy', fd).then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Blocks.ajaxRefresh(d.message || 'Копия создана'); }
            else { Adminx.Toast.show(d.message || 'Не удалось создать копию', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    copyTag: function (text) {
      text = String(text || '');
      if (!text) { return; }
      var ok = function () { Adminx.Toast.show('Тег скопирован', 'success'); };
      var fallback = function () {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          if (document.execCommand('copy')) {
            ok();
          } else {
            Adminx.Toast.show(text, 'info');
          }
        } catch (e) {
          Adminx.Toast.show(text, 'info');
        }
        textarea.remove();
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(ok).catch(fallback);
        return;
      }
      fallback();
    },

    clearCache: function (row) {
      if (!row) { return; }
      Adminx.Ajax.post(this.base() + '/blocks/' + row.dataset.id + '/clear-cache').then(function (payload) {
        var d = payload.data || {};
        Adminx.Toast.show(d.message || (d.success ? 'Кеш очищен' : 'Не удалось очистить кеш'), d.success ? 'success' : 'error');
      }).catch(function () {
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    openRevisions: function (row) {
      if (!row) { return; }
      this.currentRevisionId = 0;
      this.currentRevisionBlockId = row.dataset.id || 0;
      var title = document.getElementById('blockRevisionsDrawerTitle');
      var subtitle = document.getElementById('blockRevisionsDrawerSubtitle');
      var list = document.querySelector('[data-revisions-list]');
      var count = document.querySelector('[data-revisions-count]');
      var revisionTitle = document.querySelector('[data-revision-title]');
      var revisionMeta = document.querySelector('[data-revision-meta]');
      var fields = document.querySelector('[data-revision-fields]');
      var restore = document.querySelector('[data-revision-restore]');
      var remove = document.querySelector('[data-revision-delete]');
      var clear = document.querySelector('[data-revisions-clear]');

      if (title) { title.textContent = 'Ревизии: ' + (row.dataset.name || ('#' + row.dataset.id)); }
      if (subtitle) { subtitle.textContent = '[tag:sysblock:' + (row.dataset.alias || '') + ']'; }
      if (list) { list.innerHTML = '<div class="empty-state">Загрузка...</div>'; }
      if (count) { count.textContent = 'Загрузка...'; }
      if (revisionTitle) { revisionTitle.textContent = 'Выберите ревизию'; }
      if (revisionMeta) { revisionMeta.textContent = 'Код и метаданные появятся справа после выбора снимка.'; }
      if (fields) { fields.innerHTML = ''; }
      if (restore) { restore.disabled = true; }
      if (remove) { remove.disabled = true; remove.removeAttribute('data-revision-delete'); }
      if (clear) { clear.disabled = true; }
      this.setRevisionCode('');
      if (Adminx.Drawer) { Adminx.Drawer.open('blockRevisionsDrawer'); }
      if (window.Adminx.CodeEditor) { setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 80); }

      this.refreshRevisions();
    },

    refreshRevisions: function () {
      if (!this.currentRevisionBlockId) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/blocks/' + self.currentRevisionBlockId + '/revisions').then(function (payload) {
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
      var list = document.querySelector('[data-revisions-list]');
      var count = document.querySelector('[data-revisions-count]');
      var clear = document.querySelector('[data-revisions-clear]');
      if (count) { count.textContent = items.length ? (items.length + ' снимков') : 'История пока пустая'; }
      if (clear) { clear.disabled = !items.length; }
      if (!list) { return; }
      if (!items.length) {
        list.innerHTML = '<div class="empty-state">Ревизий пока нет. Первый снимок появится после сохранения блока.</div>';
        this.currentRevisionId = 0;
        this.showRevisionEmpty();
        return;
      }
      list.innerHTML = items.map(function (item) {
        return '<div class="list-row blocks-revision-row" data-revision-open="' + item.id + '">' +
          '<span class="icon-tile blocks-revision-icon"><i class="ti ti-history"></i></span>' +
          '<div class="blocks-revision-row-main">' +
          '<b>Ревизия #' + item.id + '</b><span class="badge ' + esc(item.badge || 'badge-gray') + '">' + esc(item.action_label || item.action) + '</span>' +
          '<div class="text-muted text-xs">' + esc(item.created_label || '-') + (item.author_name ? ' · ' + esc(item.author_name) : '') + (item.comment ? ' · ' + esc(item.comment) : '') + '</div>' +
          '</div>' +
          '<button class="btn btn-ghost btn-icon btn-sm blocks-revision-delete" type="button" data-revision-delete="' + item.id + '" data-tooltip="Удалить ревизию" aria-label="Удалить ревизию"><i class="ti ti-trash"></i></button>' +
          '</div>';
      }).join('');
      this.loadRevision(items[0].id);
    },

    showRevisionEmpty: function () {
      var title = document.querySelector('[data-revision-title]');
      var meta = document.querySelector('[data-revision-meta]');
      var fields = document.querySelector('[data-revision-fields]');
      var restore = document.querySelector('[data-revision-restore]');
      var remove = document.querySelector('[data-revision-delete]');
      if (title) { title.textContent = 'Выберите ревизию'; }
      if (meta) { meta.textContent = 'Код и метаданные появятся справа после выбора снимка.'; }
      if (fields) { fields.innerHTML = ''; }
      if (restore) { restore.disabled = true; }
      if (remove) { remove.disabled = true; remove.removeAttribute('data-revision-delete'); }
      this.setRevisionCode('');
    },

    loadRevision: function (id) {
      id = parseInt(id, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/blocks/revisions/' + id).then(function (payload) {
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
      document.querySelectorAll('[data-revision-open]').forEach(function (row) {
        row.classList.toggle('is-active', row.getAttribute('data-revision-open') === String(item.id));
      });

      var snapshot = item.snapshot || {};
      var title = document.querySelector('[data-revision-title]');
      var meta = document.querySelector('[data-revision-meta]');
      var fields = document.querySelector('[data-revision-fields]');
      var restore = document.querySelector('[data-revision-restore]');
      var remove = document.querySelector('[data-revision-delete]');

      if (title) { title.textContent = '#' + item.id + ' · ' + (item.action_label || item.action || 'Ревизия'); }
      if (meta) {
        meta.textContent = (item.created_label || '-') + (item.author_name ? ' · ' + item.author_name : '') + (item.text_size_label ? ' · ' + item.text_size_label : '');
      }
      if (fields) {
        fields.innerHTML =
          '<span><b>Название</b><em>' + esc(snapshot.sysblock_name || '-') + '</em></span>' +
          '<span><b>Алиас</b><em class="mono">[tag:sysblock:' + esc(snapshot.sysblock_alias || '-') + ']</em></span>' +
          '<span><b>Флаги</b><em>' +
          (String(snapshot.sysblock_active) === '1' ? 'активен' : 'выкл') +
          (String(snapshot.sysblock_eval) === '1' ? ', eval' : '') +
          (String(snapshot.sysblock_external) === '1' ? ', external' : '') +
          (String(snapshot.sysblock_ajax) === '1' ? ', ajax' : '') +
          (String(snapshot.sysblock_visual) === '1' ? ', visual' : '') +
          '</em></span>';
      }
      if (restore) { restore.disabled = !this.currentRevisionId; }
      if (remove) {
        remove.disabled = !this.currentRevisionId;
        if (this.currentRevisionId) { remove.setAttribute('data-revision-delete', String(this.currentRevisionId)); }
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
          Adminx.Ajax.post(self.base() + '/blocks/revisions/' + id + '/delete').then(function (payload) {
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
      if (!this.currentRevisionBlockId) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить все ревизии?',
        message: 'Будет очищена вся история снимков этого блока.',
        confirmLabel: 'Удалить все',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/blocks/' + self.currentRevisionBlockId + '/revisions/delete').then(function (payload) {
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
        message: 'Текущее состояние блока будет сохранено отдельным снимком перед восстановлением.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/blocks/revisions/' + id + '/restore').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Blocks.ajaxRefresh(d.message || 'Блок восстановлен'); }
            else { Adminx.Toast.show(d.message || 'Не удалось восстановить ревизию', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    remove: function (row) {
      if (!row) { return; }
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить системный блок?',
        message: '«' + row.dataset.name + '» будет удалён из таблицы, кеш блока будет очищен.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/blocks/' + row.dataset.id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Blocks.ajaxRefresh(d.message || 'Системный блок удалён'); }
            else { Adminx.Toast.show(d.message || 'Не удалось удалить', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    },

    fillGroupNew: function () {
      if (!this.groupForm) { return; }
      this.groupForm.reset();
      this.groupField('id').value = '';
      document.getElementById('blockGroupDrawerTitle').textContent = 'Новая группа';
    },

    fillGroupEdit: function (row) {
      if (!row || !this.groupForm) { return; }
      var self = this;
      Adminx.Ajax.request(this.base() + '/blocks/groups/' + row.dataset.id).then(function (payload) {
        var d = payload.data || {};
        if (!d.success || !d.data) { Adminx.Toast.show(d.message || 'Группа не найдена', 'error'); return; }
        self.groupField('id').value = d.data.id || '';
        self.groupField('title').value = d.data.title || '';
        self.groupField('description').value = d.data.description || '';
        document.getElementById('blockGroupDrawerTitle').textContent = 'Группа: ' + (d.data.title || '');
      });
    },

    groupDragStart: function (e) {
      var handle = e.target.closest('[data-group-drag-handle]');
      var row = handle ? handle.closest('[data-block-group-row]') : null;
      if (!handle || !row || !row.closest('[data-block-groups-sortable]')) {
        return;
      }
      this.dragGroupRow = row;
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.id || '');
      }
      setTimeout(function () { row.classList.add('blocks-group-row-dragging'); }, 0);
    },

    groupDragOver: function (e) {
      if (!this.dragGroupRow) { return; }
      var list = e.target.closest('[data-block-groups-sortable]');
      if (!list) { return; }
      e.preventDefault();
      var row = e.target.closest('[data-block-group-row]');
      this.clearGroupDragMarkers();
      if (!row || row === this.dragGroupRow) { return; }
      var rect = row.getBoundingClientRect();
      row.classList.add(e.clientY > rect.top + rect.height / 2 ? 'drag-over-bottom' : 'drag-over-top');
    },

    groupDrop: function (e) {
      if (!this.dragGroupRow) { return; }
      var list = e.target.closest('[data-block-groups-sortable]');
      if (!list) { return; }
      e.preventDefault();
      var row = e.target.closest('[data-block-group-row]');
      if (row && row !== this.dragGroupRow) {
        var rect = row.getBoundingClientRect();
        var after = e.clientY > rect.top + rect.height / 2;
        list.insertBefore(this.dragGroupRow, after ? row.nextSibling : row);
      } else if (!row) {
        list.appendChild(this.dragGroupRow);
      }
      this.groupDragEnd();
      this.updateGroupPositions();
      this.persistGroupOrder();
    },

    groupDragEnd: function () {
      if (this.dragGroupRow) { this.dragGroupRow.classList.remove('blocks-group-row-dragging'); }
      this.dragGroupRow = null;
      this.clearGroupDragMarkers();
    },

    clearGroupDragMarkers: function () {
      document.querySelectorAll('.blocks-groups-table .drag-over-top, .blocks-groups-table .drag-over-bottom').forEach(function (row) {
        row.classList.remove('drag-over-top', 'drag-over-bottom');
      });
    },

    updateGroupPositions: function () {
      document.querySelectorAll('[data-block-groups-sortable] [data-block-group-row]').forEach(function (row, index) {
        var cell = row.querySelector('[data-group-position]');
        row.dataset.position = String(index + 1);
        if (cell) { cell.textContent = String(index + 1); }
      });
    },

    persistGroupOrder: function () {
      var rows = document.querySelectorAll('[data-block-groups-sortable] [data-block-group-row]');
      if (!rows.length) { return; }
      var fd = new FormData();
      fd.set('_csrf', Adminx.csrf());
      rows.forEach(function (row) { fd.append('order[]', row.dataset.id || ''); });
      Adminx.Loader.show();
      Adminx.Ajax.post(this.base() + '/blocks/groups/reorder', fd).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (d.success) { Adminx.Toast.show(d.message || 'Порядок групп сохранён', 'success'); }
        else {
          Adminx.Toast.show(d.message || 'Не удалось сохранить порядок групп', 'error');
          Adminx.Blocks.ajaxRefresh('');
        }
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
        Adminx.Blocks.ajaxRefresh('');
      });
    },

    submitGroup: function () {
      var id = (this.groupField('id').value || '').trim();
      var url = this.base() + '/blocks/groups' + (id ? '/' + id : '');
      Adminx.Loader.show();
      Adminx.Ajax.post(url, new FormData(this.groupForm)).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (d.success) { Adminx.Blocks.ajaxRefresh(d.message || 'Группа сохранена'); }
        else { Adminx.Toast.show(d.message || 'Не удалось сохранить группу', 'error'); }
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    removeGroup: function (row) {
      if (!row) { return; }
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Удалить группу?',
        message: 'Блоки из «' + row.dataset.title + '» будут перенесены в «Без группы».',
        confirmLabel: 'Удалить группу',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/blocks/groups/' + row.dataset.id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { Adminx.Blocks.ajaxRefresh(d.message || 'Группа удалена'); }
            else { Adminx.Toast.show(d.message || 'Не удалось удалить группу', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Blocks.init(); });
  } else {
    Adminx.Blocks.init();
  }
})(window, document);
