/**
 * JS раздела «Навигация»: drawer-CRUD, импорт legacy, фильтры и копирование тегов.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Navigation = {
    form: null,
    itemForm: null,
    filterTimer: null,
    aliasTimer: null,
    aliasState: 'empty',
    currentNavigationId: 0,
    currentNavigationFlat: [],
    currentItemId: 0,
    dragItem: null,
    dragGroup: [],
    dragPlaceholder: null,
    dragGhost: null,
    dragPointerId: null,
    dragStartX: 0,
    dragStartY: 0,
    dragStarted: false,
    dragOrderSnapshot: '',
    orderSaving: false,
    activeTemplateEditor: null,

    init: function () {
      this.form = document.getElementById('navigationForm');
      this.itemForm = document.getElementById('navigationItemForm');
      var self = this;

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-navigation-new]')) { self.fillNew(); }
        if (e.target.closest('[data-navigation-filter-reset]')) { self.resetFilters(); }

        var edit = e.target.closest('[data-navigation-edit]');
        if (edit) { self.fillEdit(edit.closest('[data-navigation-row]')); }

        var items = e.target.closest('[data-navigation-items]');
        if (items) { self.openItems(items.closest('[data-navigation-row]')); }

        if (e.target.closest('[data-navigation-item-new]')) { self.fillItemNew(); }
        if (e.target.closest('[data-navigation-item-reset]')) { self.reloadCurrentItem(); }
        var itemEdit = e.target.closest('[data-navigation-item-edit]');
        if (itemEdit) { self.fillItemEdit(itemEdit.getAttribute('data-navigation-item-edit')); }
        var itemPick = e.target.closest('[data-navigation-builder-item]');
        if (itemPick && !e.target.closest('button, .navigation-drag-handle')) { self.fillItemEdit(itemPick.getAttribute('data-navigation-builder-item')); }
        var itemToggle = e.target.closest('[data-navigation-item-toggle]');
        if (itemToggle) { self.toggleItem(itemToggle.getAttribute('data-navigation-item-toggle')); }
        var itemDelete = e.target.closest('[data-navigation-item-delete]');
        if (itemDelete) { self.deleteItem(itemDelete.getAttribute('data-navigation-item-delete')); }
        var itemIndent = e.target.closest('[data-navigation-item-indent]');
        if (itemIndent) { self.changeItemLevel(itemIndent.closest('[data-navigation-item-node]'), parseInt(itemIndent.getAttribute('data-navigation-item-indent'), 10) || 0); }
        if (e.target.closest('[data-navigation-document-pick]')) { self.openDocumentPicker(); }
        if (e.target.closest('[data-navigation-document-clear]')) { self.clearPickedDocument(); }
        if (e.target.closest('[data-navigation-image-pick]')) { self.openImagePicker(); }
        if (e.target.closest('[data-navigation-image-clear]')) { self.applyPickedImage(''); }

        var tagsToggle = e.target.closest('[data-navigation-tags-toggle]');
        if (tagsToggle) { e.preventDefault(); self.openTagPalette(tagsToggle.closest('.navigation-code-field')); }
        var templateTab = e.target.closest('[data-navigation-template-tab]');
        if (templateTab) { self.activateTemplateSection(templateTab.getAttribute('data-navigation-template-tab')); }
        if (e.target.closest('[data-navigation-tags-close]')) { self.closeTagPalette(); }
        var tagTab = e.target.closest('[data-navigation-tag-tab]');
        if (tagTab) { self.activateTagGroup(tagTab.getAttribute('data-navigation-tag-tab')); }
        var templateTag = e.target.closest('[data-navigation-template-tag]');
        if (templateTag) { self.insertTemplateTag(templateTag); }

        var copy = e.target.closest('[data-navigation-copy]');
        if (copy) { self.copy(copy.closest('[data-navigation-row]')); }

        var cache = e.target.closest('[data-navigation-cache]');
        if (cache) { self.clearCache(cache.closest('[data-navigation-row]')); }

        var del = e.target.closest('[data-navigation-delete]');
        if (del) { self.remove(del.closest('[data-navigation-row]')); }

        if (e.target.closest('[data-navigation-submit-stay]')) { self.submit(true); }
      });

      if (this.form) {
        this.form.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submit(false);
        });
      }
      if (this.itemForm) {
        this.itemForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitItem();
        });
        this.itemForm.addEventListener('input', function (e) {
          if (e.target && e.target.matches('[data-navigation-image-input]')) { self.renderPickedImage(e.target.value); }
        });
      }

      document.addEventListener('submit', function (e) {
        var filter = e.target.closest('.navigation-filter');
        if (!filter) { return; }
        e.preventDefault();
        self.applyFilters(filter, true);
      });

      document.addEventListener('input', function (e) {
        if (e.target.matches('[data-navigation-tags-search]')) {
          self.filterTags(e.target.value);
          return;
        }
        var filter = e.target.closest('.navigation-filter');
        if (!filter || !e.target.matches('input[type="search"]')) { return; }
        clearTimeout(self.filterTimer);
        self.filterTimer = setTimeout(function () { self.applyFilters(filter, true); }, 350);
      });

      document.addEventListener('focusin', function (e) {
        if (e.target && e.target.matches && e.target.matches('#navigationForm textarea[data-code-editor]')) {
          self.activeTemplateEditor = e.target._adminxCodeMirror || null;
        }
      });

      document.addEventListener('input', function (e) {
        if (!self.form || e.target !== self.field('alias')) { return; }
        self.scheduleAliasCheck();
      });

      document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-navigation-group-option]')) {
          self.syncGroupAccess();
        }
      });

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-navigation-groups-all]')) {
          self.selectAllGroups(true);
        } else if (e.target.closest('[data-navigation-groups-none]')) {
          self.selectAllGroups(false);
        }
      });

      document.addEventListener('blur', function (e) {
        if (!self.form || e.target !== self.field('alias')) { return; }
        self.checkAlias(true);
      }, true);

      window.addEventListener('popstate', function () {
        self.applyFilterUrl(window.location.href, false);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && self.isTagPaletteOpen()) {
          self.closeTagPalette();
          return;
        }
        if (e.target && e.target.closest && e.target.closest('.CodeMirror')) {
          return;
        }
        if ((e.ctrlKey || e.metaKey) && String(e.key || '').toLowerCase() === 's' && self.isDrawerOpen()) {
          e.preventDefault();
          self.submit(true);
        }
      });
      document.addEventListener('pointerdown', function (e) { self.itemDragStart(e); });
      document.addEventListener('pointermove', function (e) { self.itemDragOver(e); });
      document.addEventListener('pointerup', function (e) { self.itemDrop(e); });
      document.addEventListener('pointercancel', function () { self.itemDragEnd(); });
      this.openFromLocation();
    },

    openFromLocation: function () {
      var id = parseInt(new URLSearchParams(window.location.search).get('edit'), 10) || 0;
      if (!id) { return; }
      var row = document.querySelector('[data-navigation-row][data-id="' + id + '"]');
      if (!row) { return; }
      if (Adminx.Drawer) { Adminx.Drawer.open('navigationDrawer'); }
      this.fillEdit(row);
    },

    base: function () { return (this.form && this.form.getAttribute('data-base')) || Adminx.base(); },
    field: function (name) { return this.form.querySelector('[name="' + name + '"]'); },
    itemField: function (name) { return this.itemForm.querySelector('[name="' + name + '"]'); },

    filterUrl: function (form) {
      var params = new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function (key) {
        if (String(params.get(key) || '') === '') { params.delete(key); }
      });
      var query = params.toString();
      return (form.getAttribute('action') || (this.base() + '/navigation')) + (query ? '?' + query : '');
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
      ['.navigation-summary', '.navigation-import-card', '.navigation-panel'].forEach(function (selector) {
        var next = doc.querySelector(selector);
        var current = document.querySelector(selector);
        if (next && current) { current.replaceWith(next); }
      });
      if (push && window.history && window.history.pushState) {
        window.history.pushState({ adminxNavigationFilters: true }, '', url);
      }
    },

    resetFilters: function () {
      var form = document.querySelector('.navigation-filter');
      if (!form) { return; }
      form.reset();
      this.applyFilters(form, true);
    },

    isDrawerOpen: function () {
      var drawer = document.getElementById('navigationDrawer');
      return !!(drawer && !drawer.hidden && this.form);
    },

    fillNew: function () {
      if (!this.form) { return; }
      this.closeTagPalette();
      this.form.reset();
      this.field('id').value = '';
      this.field('title').value = '';
      this.field('alias').value = '';
      this.field('expand_ext').value = '1';
      this.selectAllGroups(true);
      var templates = {
        begin: '<nav class="site-navigation" aria-label="Основная навигация">',
        end: '</nav>',
        level1_begin: '<ul class="site-navigation-list">[tag:content]',
        level1: '<li class="site-navigation-item"><a target="[tag:target]" href="[tag:link]">[tag:linkname]</a></li>',
        level1_active: '<li class="site-navigation-item is-active"><a target="[tag:target]" href="[tag:link]" aria-current="page">[tag:linkname]</a></li>',
        level1_end: '</ul>'
      };
      Object.keys(templates).forEach(function (key) { this.field(key).value = templates[key]; }, this);
      this.activateTemplateSection('base');
      this.syncEditors();
      this.clearErrors();
      this.setAliasState('', '');
      document.getElementById('navigationDrawerTitle').textContent = 'Новая навигация';
      setTimeout(function () { if (Adminx.CodeEditor) { Adminx.CodeEditor.refreshAll(); } }, 120);
    },

    fillEdit: function (row) {
      if (!row) { return; }
      this.closeTagPalette();
      var self = this;
      var id = row.getAttribute('data-id');
      Adminx.Loader.show();
      fetch(this.base() + '/navigation/' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(this.json)
        .then(function (payload) {
          var item = payload.data || {};
          self.form.reset();
          Object.keys(item).forEach(function (key) {
            var field = self.field(key);
            if (field) { field.value = item[key] == null ? '' : item[key]; }
          });
          self.field('id').value = item.navigation_id || '';
          self.setGroupAccess(item.user_group || '');
          document.getElementById('navigationDrawerTitle').textContent = item.title || 'Навигация';
          self.clearErrors();
          self.setAliasState('ok', item.alias ? 'Алиас сохранён' : '');
          self.activateTemplateSection('base');
          self.syncEditors();
          setTimeout(function () { if (Adminx.CodeEditor) { Adminx.CodeEditor.refreshAll(); } }, 120);
        })
        .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось загрузить навигацию', 'error'); })
        .finally(function () { Adminx.Loader.hide(); });
    },

    setGroupAccess: function (value) {
      var selected = String(value || '').split(',').map(function (id) { return String(parseInt(id, 10) || ''); });
      document.querySelectorAll('[data-navigation-group-option]').forEach(function (option) {
        option.checked = selected.indexOf(String(option.value)) !== -1;
      });
      this.syncGroupAccess();
    },

    selectAllGroups: function (checked) {
      document.querySelectorAll('[data-navigation-group-option]').forEach(function (option) {
        option.checked = !!checked;
      });
      this.syncGroupAccess();
    },

    syncGroupAccess: function () {
      if (!this.form) { return; }
      var options = Array.prototype.slice.call(document.querySelectorAll('[data-navigation-group-option]'));
      var selected = options.filter(function (option) { return option.checked; });
      var input = this.field('user_group');
      var summary = document.querySelector('[data-navigation-groups-summary]');
      var detail = document.querySelector('[data-navigation-groups-detail]');
      var count = document.querySelector('[data-navigation-groups-count]');
      if (input) { input.value = selected.map(function (option) { return option.value; }).join(','); }
      if (count) { count.textContent = selected.length + ' из ' + options.length; }
      if (!summary || !detail) { return; }
      if (!selected.length) {
        summary.textContent = 'Никому не доступна';
        detail.textContent = 'Выберите хотя бы одну группу';
      } else if (selected.length === options.length) {
        summary.textContent = 'Все группы';
        detail.textContent = selected.map(function (option) { return option.getAttribute('data-group-name'); }).join(', ');
      } else if (selected.length === 1) {
        summary.textContent = selected[0].getAttribute('data-group-name') || ('Группа #' + selected[0].value);
        detail.textContent = 'Выбрана 1 группа';
      } else {
        summary.textContent = 'Выбрано групп: ' + selected.length;
        detail.textContent = selected.map(function (option) { return option.getAttribute('data-group-name'); }).join(', ');
      }
    },

    syncEditors: function () {
      var self = this;
      Array.prototype.forEach.call(this.form.querySelectorAll('textarea[data-code-editor]'), function (textarea) {
        if (textarea._adminxCodeMirror) {
          textarea._adminxCodeMirror.setValue(textarea.value || '');
          textarea._adminxCodeMirror.on('focus', function (cm) { self.activeTemplateEditor = cm; });
          setTimeout(function () { textarea._adminxCodeMirror.refresh(); }, 40);
        }
      });
    },

    activateTemplateSection: function (name) {
      var panel = null;
      name = String(name || 'base');
      this.closeTagPalette();
      document.querySelectorAll('[data-navigation-template-tab]').forEach(function (tab) {
        var active = tab.getAttribute('data-navigation-template-tab') === name;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      document.querySelectorAll('[data-navigation-template-panel]').forEach(function (item) {
        var active = item.getAttribute('data-navigation-template-panel') === name;
        item.hidden = !active;
        if (active) { panel = item; }
      });
      if (panel) {
        setTimeout(function () {
          panel.querySelectorAll('textarea[data-code-editor]').forEach(function (textarea) {
            if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.refresh(); }
          });
        }, 30);
      }
    },

    isTagPaletteOpen: function () {
      var palette = document.querySelector('[data-navigation-tags]');
      return !!(palette && !palette.hidden);
    },

    openTagPalette: function (field) {
      var palette = document.querySelector('[data-navigation-tags]');
      var textarea = field ? field.querySelector('textarea[data-code-editor]') : null;
      var anchor = textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror.getWrapperElement() : textarea;
      var search;
      if (!palette || !field || !textarea) { return; }
      document.querySelectorAll('.navigation-code-field.is-tags-open').forEach(function (item) { item.classList.remove('is-tags-open'); });
      field.classList.add('is-tags-open');
      this.activeTemplateEditor = textarea._adminxCodeMirror || this.activeTemplateEditor;
      field.insertBefore(palette, anchor || field.firstChild);
      palette.hidden = false;
      search = palette.querySelector('[data-navigation-tags-search]');
      if (search) {
        search.value = '';
        this.filterTags('');
        search.focus();
      }
    },

    closeTagPalette: function () {
      var palette = document.querySelector('[data-navigation-tags]');
      if (palette) { palette.hidden = true; }
      document.querySelectorAll('.navigation-code-field.is-tags-open').forEach(function (item) { item.classList.remove('is-tags-open'); });
    },

    activateTagGroup: function (name) {
      var palette = document.querySelector('[data-navigation-tags]');
      if (!palette) { return; }
      palette.querySelectorAll('[data-navigation-tag-tab]').forEach(function (tab) {
        tab.classList.toggle('is-active', tab.getAttribute('data-navigation-tag-tab') === String(name));
      });
      palette.querySelectorAll('[data-navigation-tag-panel]').forEach(function (group) {
        group.classList.toggle('is-active', group.getAttribute('data-navigation-tag-panel') === String(name));
      });
    },

    filterTags: function (query) {
      var palette = document.querySelector('[data-navigation-tags]');
      var shown = 0;
      if (!palette) { return; }
      query = String(query || '').trim().toLowerCase();
      palette.classList.toggle('is-searching', query !== '');
      palette.querySelectorAll('[data-navigation-template-tag]').forEach(function (button) {
        var visible = query === '' || button.textContent.toLowerCase().indexOf(query) !== -1;
        button.hidden = !visible;
        if (visible) { shown++; }
      });
      palette.querySelectorAll('[data-navigation-tag-group]').forEach(function (group) {
        group.hidden = query !== '' && !group.querySelector('[data-navigation-template-tag]:not([hidden])');
      });
      var empty = palette.querySelector('[data-navigation-tags-empty]');
      if (empty) { empty.hidden = shown > 0; }
    },

    insertTemplateTag: function (button) {
      var value = button.getAttribute('data-navigation-template-tag') || '';
      var select = button.getAttribute('data-navigation-template-select') || value;
      var editor = this.activeTemplateEditor || this.editorFallback();
      if (!value || !editor) { return; }
      editor.replaceSelection(value);
      var cursor = editor.getCursor();
      if (select && value.indexOf(select) !== -1) {
        var from = editor.indexFromPos(cursor) - value.length + value.indexOf(select);
        editor.setSelection(editor.posFromIndex(from), editor.posFromIndex(from + select.length));
      }
      editor.focus();
      editor.save();
      this.closeTagPalette();
    },

    editorFallback: function () {
      var textarea = this.form ? this.form.querySelector('textarea[data-code-editor]') : null;
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    saveEditors: function () {
      Array.prototype.forEach.call(this.form.querySelectorAll('textarea[data-code-editor]'), function (textarea) {
        if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.save(); }
      });
    },

    scheduleAliasCheck: function () {
      var self = this;
      clearTimeout(this.aliasTimer);
      this.setAliasState('pending', 'Проверяем алиас...');
      this.aliasTimer = setTimeout(function () { self.checkAlias(false); }, 350);
    },

    checkAlias: function (force) {
      if (!this.form) { return Promise.resolve(false); }
      var alias = this.field('alias').value || '';
      var id = this.field('id').value || '0';
      if (!alias && !force) {
        this.setAliasState('empty', '');
        return Promise.resolve(false);
      }
      var self = this;
      return fetch(this.base() + '/navigation/alias-check?alias=' + encodeURIComponent(alias) + '&id=' + encodeURIComponent(id), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(this.json).then(function (payload) {
        var data = payload.data || {};
        self.setAliasState(data.valid && data.available ? 'ok' : 'error', payload.message || '');
        return !!(data.valid && data.available);
      }).catch(function () {
        self.setAliasState('error', 'Не удалось проверить алиас');
        return false;
      });
    },

    setAliasState: function (state, message) {
      this.aliasState = state || 'empty';
      var node = document.querySelector('[data-navigation-alias-state]');
      var field = this.form ? this.field('alias') : null;
      if (node) {
        node.textContent = message || '';
        node.className = 'field-hint navigation-alias-state is-' + this.aliasState;
      }
      if (field) {
        field.classList.toggle('is-invalid', this.aliasState === 'error');
      }
    },

    submit: function (stay) {
      if (!this.form) { return; }
      var self = this;
      this.saveEditors();
      this.clearErrors();
      this.checkAlias(true).then(function (ok) {
        if (!ok) {
          Adminx.Toast.show('Проверьте алиас перед сохранением', 'error');
          return;
        }
        var id = self.field('id').value;
        var url = id ? self.base() + '/navigation/' + encodeURIComponent(id) : self.base() + '/navigation';
        self.post(url, new FormData(self.form))
          .then(function (payload) {
            if (stay && payload.data && payload.data.id) {
              self.field('id').value = payload.data.id;
            }
            self.ajaxRefresh(payload.message || 'Сохранено', stay);
          })
          .catch(function (err) { self.showErrors(err); });
      });
    },

    copy: function (row) {
      if (!row) { return; }
      var id = row.getAttribute('data-id');
      var data = new FormData();
      data.append('_csrf', this.csrf());
      data.append('title', (row.getAttribute('data-title') || 'Навигация') + ' (копия)');
      var self = this;
      this.post(this.base() + '/navigation/' + encodeURIComponent(id) + '/copy', data)
        .then(function (payload) { self.ajaxRefresh(payload.message || 'Копия создана'); })
        .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось создать копию', 'error'); });
    },

    clearCache: function (row) {
      if (!row) { return; }
      var data = new FormData();
      data.append('_csrf', this.csrf());
      var self = this;
      this.post(this.base() + '/navigation/' + encodeURIComponent(row.getAttribute('data-id')) + '/clear-cache', data)
        .then(function (payload) { self.ajaxRefresh(payload.message || 'Кеш очищен', true); })
        .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось очистить кеш', 'error'); });
    },

    remove: function (row) {
      if (!row) { return; }
      var data = new FormData();
      data.append('_csrf', this.csrf());
      var self = this;
      var run = function () {
        self.post(self.base() + '/navigation/' + encodeURIComponent(row.getAttribute('data-id')) + '/delete', data)
          .then(function (payload) { self.ajaxRefresh(payload.message || 'Удалено'); })
          .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось удалить', 'error'); });
      };
      if (Adminx.Confirm) {
        Adminx.Confirm.open({
          kind: 'danger',
          title: 'Удалить навигацию',
          message: 'Будут удалены шаблон меню и все его пункты.',
          confirmLabel: 'Удалить',
          onConfirm: run
        });
      } else {
        run();
      }
    },

    openItems: function (row) {
      if (!row) { return; }
      this.currentNavigationId = parseInt(row.getAttribute('data-id'), 10) || 0;
      this.currentItemId = 0;
      this.setItemFormMode('empty');
      this.loadItems();
    },

    loadItems: function () {
      var self = this;
      if (!this.currentNavigationId) { return; }
      Adminx.Loader.show();
      fetch(this.base() + '/navigation/' + encodeURIComponent(this.currentNavigationId) + '/items', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(this.json).then(function (payload) {
        var data = payload.data || {};
        self.currentNavigationFlat = data.flat || [];
        var title = document.getElementById('navigationItemsDrawerTitle');
        var subtitle = document.querySelector('[data-navigation-items-subtitle]');
        var count = document.querySelector('[data-navigation-items-count]');
        if (title) { title.textContent = data.navigation ? data.navigation.title : 'Пункты навигации'; }
        if (subtitle) { subtitle.textContent = data.navigation ? data.navigation.tag : 'Структура меню'; }
        if (count) { count.textContent = String(self.currentNavigationFlat.length); }
        self.renderItems(data.items || []);
        if (self.currentItemId) {
          self.markSelectedItem(self.currentItemId);
        }
      }).catch(function (err) {
        Adminx.Toast.show(err.message || 'Не удалось загрузить пункты', 'error');
      }).finally(function () { Adminx.Loader.hide(); });
    },

    renderItems: function (items) {
      var root = document.querySelector('[data-navigation-items-tree]');
      if (!root) { return; }
      if (!items.length) {
        root.innerHTML = '<div class="empty-state">Пункты не найдены.</div>';
        return;
      }
      var flat = [];
      this.flattenItems(items, flat);
      root.innerHTML = '<ol>' + flat.map(this.itemHtml.bind(this)).join('') + '</ol>';
      this.normalizeItemHierarchy();
    },

    flattenItems: function (items, out) {
      items.forEach(function (item) {
        out.push(item);
        if (item.children && item.children.length) { this.flattenItems(item.children, out); }
      }, this);
    },

    itemHtml: function (item) {
      var level = Math.max(0, Math.min(2, (parseInt(item.level, 10) || 1) - 1));
      var status = String(item.status) === '1' ? '<span class="badge badge-green">активен</span>' : '<span class="badge badge-gray">выкл</span>';
      return '<li data-navigation-item-node="' + item.navigation_item_id + '" data-parent-id="' + (item.parent_id || 0) + '" data-level="' + level + '"><div class="sortable-item menu-builder-item navigation-tree-item mb-level-' + level + (String(item.status) === '1' ? '' : ' mb-off') + '" data-navigation-builder-item="' + item.navigation_item_id + '">'
        + '<button class="navigation-drag-handle" type="button" data-navigation-drag-handle data-tooltip="Перетащить" data-tooltip-position="right" aria-label="Перетащить пункт"><i class="ti ti-grip-vertical"></i></button>'
        + '<span class="si-pos mono">' + this.esc(item.position || '') + '</span>'
        + '<div class="navigation-tree-main"><b>' + this.esc(item.title || ('#' + item.navigation_item_id)) + '</b><span class="mono">' + this.esc(item.alias || '') + '</span></div>'
        + '<div class="navigation-tree-meta"><span class="badge badge-blue" data-navigation-level-badge>уровень ' + (level + 1) + '</span>' + status + (item.document_id > 0 ? '<span class="badge badge-cyan">doc #' + item.document_id + '</span>' : '') + '</div>'
        + '<div class="navigation-tree-actions">'
        + '<button class="btn btn-ghost btn-icon btn-sm navigation-action-level" type="button" data-navigation-item-indent="-1" data-tooltip="Уменьшить уровень" aria-label="Уменьшить уровень"><i class="ti ti-indent-decrease"></i></button>'
        + '<button class="btn btn-ghost btn-icon btn-sm navigation-action-level" type="button" data-navigation-item-indent="1" data-tooltip="Увеличить уровень" aria-label="Увеличить уровень"><i class="ti ti-indent-increase"></i></button>'
        + '<button class="btn btn-ghost btn-icon btn-sm navigation-action-edit" type="button" data-navigation-item-edit="' + item.navigation_item_id + '" data-tooltip="Изменить" aria-label="Изменить"><i class="ti ti-pencil"></i></button>'
        + '<button class="btn btn-ghost btn-icon btn-sm navigation-action-cache" type="button" data-navigation-item-toggle="' + item.navigation_item_id + '" data-tooltip="Вкл/выкл" aria-label="Вкл/выкл"><i class="ti ti-power"></i></button>'
        + '<button class="btn btn-ghost btn-icon btn-sm navigation-action-danger" type="button" data-navigation-item-delete="' + item.navigation_item_id + '" data-tooltip="Удалить" aria-label="Удалить"><i class="ti ti-trash"></i></button>'
        + '</div></div></li>';
    },

    fillItemNew: function () {
      if (!this.itemForm) { return; }
      this.itemForm.reset();
      this.currentItemId = 0;
      this.itemField('navigation_id').value = this.currentNavigationId || '';
      this.itemField('navigation_item_id').value = '';
      this.itemField('target').value = '_self';
      this.renderPickedDocument(null);
      this.applyPickedImage('');
      this.populateParents(0);
      this.clearItemErrors();
      this.setItemFormMode('new', 'Новый пункт меню');
      this.markSelectedItem(0);
      var title = this.itemField('title');
      if (title) { setTimeout(function () { title.focus(); }, 40); }
    },

    fillItemEdit: function (id) {
      var self = this;
      fetch(this.base() + '/navigation/items/' + encodeURIComponent(id), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(this.json).then(function (payload) {
        var item = payload.data || {};
        self.itemForm.reset();
        Object.keys(item).forEach(function (key) {
          var field = self.itemField(key);
          if (field) { field.value = item[key] == null ? '' : item[key]; }
        });
        self.currentItemId = parseInt(item.navigation_item_id, 10) || 0;
        self.itemField('navigation_item_id').value = item.navigation_item_id || '';
        self.itemField('navigation_id').value = item.navigation_id || self.currentNavigationId || '';
        self.populateParents(item.parent_id || 0, item.navigation_item_id || 0);
        self.renderPickedDocument(item);
        self.applyPickedImage(item.image || '');
        self.clearItemErrors();
        self.setItemFormMode('edit', item.title || 'Пункт меню');
        self.markSelectedItem(self.currentItemId);
      }).catch(function (err) {
        Adminx.Toast.show(err.message || 'Не удалось загрузить пункт', 'error');
      });
    },

    reloadCurrentItem: function () {
      if (this.currentItemId) {
        this.fillItemEdit(this.currentItemId);
      } else {
        this.fillItemNew();
      }
    },

    setItemFormMode: function (mode, title) {
      var empty = document.querySelector('[data-navigation-item-empty]');
      var fields = document.querySelector('[data-navigation-item-fields]');
      var state = document.querySelector('[data-navigation-item-form-state]');
      var titleNode = document.querySelector('[data-navigation-item-form-title]');
      var save = document.querySelector('[data-navigation-item-save]');
      var reset = document.querySelector('[data-navigation-item-reset]');
      var active = mode === 'new' || mode === 'edit';
      if (empty) { empty.hidden = active; }
      if (fields) { fields.hidden = !active; }
      if (titleNode) { titleNode.textContent = title || 'Свойства пункта'; }
      if (state) {
        state.textContent = mode === 'new' ? 'новый' : (mode === 'edit' ? 'редактирование' : 'не выбран');
        state.className = 'badge ' + (mode === 'new' ? 'badge-green' : (mode === 'edit' ? 'badge-blue' : 'badge-gray'));
      }
      if (save) { save.disabled = !active; }
      if (reset) { reset.disabled = !active; }
    },

    markSelectedItem: function (id) {
      document.querySelectorAll('[data-navigation-builder-item]').forEach(function (node) {
        node.classList.toggle('is-selected', String(node.getAttribute('data-navigation-builder-item')) === String(id));
      });
    },

    populateParents: function (selected, exclude) {
      var select = this.itemField('parent_id');
      if (!select) { return; }
      var html = '<option value="0">Корень меню</option>';
      this.currentNavigationFlat.forEach(function (item) {
        if (String(item.navigation_item_id) === String(exclude) || parseInt(item.level, 10) >= 3 || this.itemIsDescendant(item.navigation_item_id, exclude)) { return; }
        html += '<option value="' + item.navigation_item_id + '">' + Array(parseInt(item.level, 10)).join('— ') + this.esc(item.title || ('#' + item.navigation_item_id)) + '</option>';
      }, this);
      select.innerHTML = html;
      select.value = String(selected || 0);
    },

    itemIsDescendant: function (candidateId, ancestorId) {
      candidateId = parseInt(candidateId, 10) || 0;
      ancestorId = parseInt(ancestorId, 10) || 0;
      if (!candidateId || !ancestorId) { return false; }
      var byId = {};
      this.currentNavigationFlat.forEach(function (item) { byId[parseInt(item.navigation_item_id, 10) || 0] = item; });
      var cursor = candidateId;
      var seen = {};
      while (cursor && byId[cursor] && !seen[cursor]) {
        if (parseInt(byId[cursor].parent_id, 10) === ancestorId) { return true; }
        seen[cursor] = true;
        cursor = parseInt(byId[cursor].parent_id, 10) || 0;
      }
      return false;
    },

    openDocumentPicker: function () {
      var self = this;
      var overlay = document.createElement('div');
      overlay.className = 'overlay navigation-picker-overlay';
      overlay.innerHTML = '<div class="modal picker-modal navigation-document-picker" role="dialog" aria-modal="true" aria-labelledby="navigationDocumentPickerTitle">'
        + '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div><h3 id="navigationDocumentPickerTitle">Выбрать документ</h3><p class="text-secondary">Название и ссылка заполнятся автоматически.</p></div><button class="modal-close" type="button" data-navigation-picker-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
        + '<div class="modal-body"><div class="input-wrap navigation-document-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="ID, название или alias" data-navigation-document-search></div><div class="navigation-picker-status" data-navigation-document-status>Загрузка...</div><div class="navigation-document-list" data-navigation-document-list></div></div>'
        + '<div class="modal-footer"><div class="mf-left navigation-picker-count" data-navigation-document-count></div><button class="btn btn-ghost" type="button" data-navigation-picker-close>Закрыть</button></div>'
        + '</div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });
      this.bindDocumentPicker(overlay);
    },

    bindDocumentPicker: function (overlay) {
      var self = this;
      var list = overlay.querySelector('[data-navigation-document-list]');
      var status = overlay.querySelector('[data-navigation-document-status]');
      var search = overlay.querySelector('[data-navigation-document-search]');
      var count = overlay.querySelector('[data-navigation-document-count]');
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 160);
      };
      var render = function (items) {
        list.innerHTML = '';
        (items || []).forEach(function (item) {
          list.insertAdjacentHTML('beforeend', '<button class="navigation-document-item" type="button" data-navigation-document-option data-id="' + self.esc(item.id) + '" data-title="' + self.esc(item.title || '') + '" data-alias="' + self.esc(item.alias || '') + '">'
            + '<span class="navigation-document-id">#' + self.esc(item.id) + '</span>'
            + '<span class="navigation-document-main"><b>' + self.esc(item.title || 'Без названия') + '</b><small>/' + self.esc(String(item.alias || '').replace(/^\/+/, '')) + '</small></span>'
            + '<span class="badge badge-blue">' + self.esc(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</span>'
            + '<span class="badge ' + (String(item.status) === '1' ? 'badge-green' : 'badge-gray') + '">' + (String(item.status) === '1' ? 'активен' : 'выключен') + '</span>'
            + '</button>');
        });
        status.hidden = items && items.length > 0;
        status.textContent = search.value.trim() ? 'Ничего не найдено' : 'Документы не найдены';
        count.textContent = items && items.length ? 'Документов: ' + items.length : '';
      };
      var load = function () {
        var params = new URLSearchParams();
        params.set('q', search.value.trim());
        params.set('limit', 30);
        status.textContent = 'Загрузка...';
        status.hidden = false;
        fetch(self.base() + '/navigation/documents/picker?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(self.json)
          .then(function (payload) { render((payload.data || {}).items || []); })
          .catch(function () { list.innerHTML = ''; status.textContent = 'Не удалось загрузить документы'; status.hidden = false; });
      };
      var timer = null;
      var onKey = function (e) { if (e.key === 'Escape') { close(); } };
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-navigation-picker-close]')) { close(); return; }
        var item = e.target.closest('[data-navigation-document-option]');
        if (item) {
          self.applyPickedDocument({ id: item.getAttribute('data-id'), title: item.getAttribute('data-title'), alias: item.getAttribute('data-alias') });
          close();
        }
      });
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 220); });
      document.addEventListener('keydown', onKey);
      load();
      search.focus();
    },

    applyPickedDocument: function (item) {
      var id = item ? parseInt(item.id || item.document_id, 10) || 0 : 0;
      var title = item ? String(item.title || item.document_title || '') : '';
      var alias = item ? String(item.alias || item.document_alias || '').replace(/^\/+/, '') : '';
      var input = this.itemField('document_id');
      if (input) { input.value = id || ''; }
      this.renderPickedDocument(id ? { document_id: id, document_title: title, document_alias: alias } : null);
      if (!id) { return; }
      var titleInput = this.itemField('title');
      var aliasInput = this.itemField('alias');
      if (titleInput) { titleInput.value = title; titleInput.dispatchEvent(new Event('input', { bubbles: true })); }
      if (aliasInput) { aliasInput.value = alias; aliasInput.dispatchEvent(new Event('input', { bubbles: true })); }
    },

    renderPickedDocument: function (item) {
      var id = item ? parseInt(item.document_id || item.id, 10) || 0 : 0;
      var title = item ? String(item.document_title || item.title || '') : '';
      var display = document.querySelector('[data-navigation-document-display]');
      var clear = document.querySelector('[data-navigation-document-clear]');
      if (display) { display.value = id ? ('#' + id + (title ? ' · ' + title : '')) : ''; }
      if (clear) { clear.hidden = !id; }
    },

    clearPickedDocument: function () {
      var input = this.itemField('document_id');
      if (input) { input.value = ''; }
      this.renderPickedDocument(null);
    },

    openImagePicker: function () {
      var self = this;
      Adminx.MediaPicker.open({
        type: 'image',
        title: 'Выбрать изображение',
        description: 'Файл из медиабраузера будет привязан к пункту меню.',
        onPick: function (file) { self.applyPickedImage((file && file.url) || ''); }
      });
    },

    applyPickedImage: function (url) {
      var input = this.itemField('image');
      url = String(url || '');
      if (input) { input.value = url; input.dispatchEvent(new Event('input', { bubbles: true })); }
      this.renderPickedImage(url);
    },

    renderPickedImage: function (url) {
      var selection = document.querySelector('[data-navigation-image-selection]');
      var image = selection ? selection.querySelector('img') : null;
      var name = document.querySelector('[data-navigation-image-name]');
      var clear = document.querySelector('[data-navigation-image-clear]');
      url = String(url || '');
      if (selection) { selection.hidden = !url; }
      if (image) { image.src = url; }
      if (name) { name.textContent = url ? url.split('/').pop() : ''; }
      if (clear) { clear.hidden = !url; }
    },

    submitItem: function () {
      var id = this.itemField('navigation_item_id').value;
      var navId = this.itemField('navigation_id').value || this.currentNavigationId;
      var url = id ? this.base() + '/navigation/items/' + encodeURIComponent(id) : this.base() + '/navigation/' + encodeURIComponent(navId) + '/items';
      var self = this;
      this.clearItemErrors();
      this.post(url, new FormData(this.itemForm)).then(function (payload) {
        Adminx.Toast.show(payload.message || 'Пункт сохранён', 'success');
        if (payload.data && payload.data.id) {
          self.currentItemId = parseInt(payload.data.id, 10) || self.currentItemId;
          self.itemField('navigation_item_id').value = payload.data.id;
        }
        self.loadItems();
        self.applyFilterUrl(window.location.href, false);
      }).catch(function (err) {
        self.showItemErrors(err);
      });
    },

    toggleItem: function (id) {
      var data = new FormData();
      data.append('_csrf', this.csrf());
      var self = this;
      this.post(this.base() + '/navigation/items/' + encodeURIComponent(id) + '/toggle', data)
        .then(function (payload) { Adminx.Toast.show(payload.message || 'Статус изменён', 'success'); self.loadItems(); })
        .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось изменить статус', 'error'); });
    },

    deleteItem: function (id) {
      var data = new FormData();
      data.append('_csrf', this.csrf());
      var self = this;
      var run = function () {
        self.post(self.base() + '/navigation/items/' + encodeURIComponent(id) + '/delete', data)
          .then(function (payload) {
            Adminx.Toast.show(payload.message || 'Пункт удалён', 'success');
            if (String(self.currentItemId) === String(id)) {
              self.currentItemId = 0;
              self.setItemFormMode('empty');
            }
            self.loadItems();
            self.applyFilterUrl(window.location.href, false);
          })
          .catch(function (err) { Adminx.Toast.show(err.message || 'Не удалось удалить пункт', 'error'); });
      };
      if (Adminx.Confirm) {
        Adminx.Confirm.open({
          kind: 'danger',
          title: 'Удалить пункт меню',
          message: 'Если у пункта есть дочерние элементы, он будет выключен.',
          confirmLabel: 'Удалить',
          onConfirm: run
        });
      } else {
        run();
      }
    },

    itemDragStart: function (e) {
      var handle = e.target.closest('[data-navigation-drag-handle]');
      var root = handle ? handle.closest('[data-navigation-items-tree]') : null;
      if (!handle || !root || this.orderSaving || (typeof e.button === 'number' && e.button !== 0)) { return; }
      this.dragItem = handle.closest('[data-navigation-item-node]');
      if (!this.dragItem) { return; }
      this.dragPointerId = e.pointerId;
      this.dragStartX = e.clientX;
      this.dragStartY = e.clientY;
      this.dragStarted = false;
      this.dragOrderSnapshot = this.itemOrderSignature();
      if (handle.setPointerCapture) { handle.setPointerCapture(e.pointerId); }
      e.preventDefault();
    },

    beginItemDrag: function (e) {
      if (!this.dragItem || this.dragStarted) { return; }
      var row = this.dragItem.querySelector('[data-navigation-builder-item]');
      var firstRect = this.dragItem.getBoundingClientRect();
      var rowRect = row.getBoundingClientRect();
      this.dragStarted = true;
      this.dragGroup = this.itemBranch(this.dragItem);
      this.dragPlaceholder = document.createElement('li');
      this.dragPlaceholder.className = 'navigation-drag-placeholder';
      this.dragPlaceholder.setAttribute('aria-hidden', 'true');
      var lastRect = this.dragGroup[this.dragGroup.length - 1].getBoundingClientRect();
      this.dragPlaceholder.style.height = Math.max(48, lastRect.bottom - firstRect.top) + 'px';
      this.dragItem.parentNode.insertBefore(this.dragPlaceholder, this.dragItem);
      this.dragGhost = row.cloneNode(true);
      this.dragGhost.classList.remove('is-selected', 'mb-level-0', 'mb-level-1', 'mb-level-2');
      this.dragGhost.classList.add('navigation-drag-ghost');
      this.dragGhost.style.width = Math.min(rowRect.width, 440, Math.max(1, window.innerWidth - 24)) + 'px';
      document.body.appendChild(this.dragGhost);
      this.positionItemDragGhost(e);
      document.body.classList.add('navigation-item-dragging');
      this.dragItem.closest('[data-navigation-items-tree]').classList.add('is-drag-active');
      this.dragGroup.forEach(function (node, index) {
        node.classList.add(index === 0 ? 'is-dragging' : 'is-dragging-child');
      });
    },

    itemDragOver: function (e) {
      if (!this.dragItem || e.pointerId !== this.dragPointerId) { return; }
      if (!this.dragStarted) {
        if (Math.abs(e.clientX - this.dragStartX) < 5 && Math.abs(e.clientY - this.dragStartY) < 5) { return; }
        this.beginItemDrag(e);
      }
      e.preventDefault();
      this.positionItemDragGhost(e);
      var root = this.dragItem.closest('[data-navigation-items-tree]');
      if (!root) { return; }
      this.scrollItemTree(root, e.clientY);

      var target = null;
      var bottom = false;
      var self = this;
      Array.prototype.some.call(root.querySelectorAll(':scope > ol > [data-navigation-item-node]'), function (node) {
        if (self.dragGroup.indexOf(node) !== -1) { return false; }
        var candidate = node.querySelector('[data-navigation-builder-item]');
        var candidateRect = candidate ? candidate.getBoundingClientRect() : null;
        if (!candidateRect || e.clientY >= candidateRect.bottom) { return false; }
        target = node;
        bottom = e.clientY > candidateRect.top + candidateRect.height / 2;
        return true;
      });

      document.querySelectorAll('.navigation-tree-item.drag-over-top, .navigation-tree-item.drag-over-bottom').forEach(function (node) {
        node.classList.remove('drag-over-top', 'drag-over-bottom');
      });
      if (!target) {
        root.querySelector('ol').appendChild(this.dragPlaceholder);
        return;
      }

      var row = target.querySelector('[data-navigation-builder-item]');
      var anchor = bottom ? this.itemBranch(target).slice(-1)[0].nextSibling : target;
      if (anchor !== this.dragPlaceholder) { target.parentNode.insertBefore(this.dragPlaceholder, anchor); }
      row.classList.toggle('drag-over-top', !bottom);
      row.classList.toggle('drag-over-bottom', bottom);
    },

    itemDrop: function (e) {
      if (!this.dragItem || e.pointerId !== this.dragPointerId) { return; }
      e.preventDefault();
      if (!this.dragStarted || !this.dragPlaceholder || !this.dragPlaceholder.parentNode) {
        this.itemDragEnd();
        return;
      }
      var placeholder = this.dragPlaceholder;
      this.dragGroup.forEach(function (node) {
        placeholder.parentNode.insertBefore(node, placeholder);
      });
      placeholder.parentNode.removeChild(placeholder);
      this.dragPlaceholder = null;
      this.normalizeItemHierarchy();
      var changed = this.itemOrderSignature() !== this.dragOrderSnapshot;
      this.itemDragEnd();
      if (changed) { this.persistItemOrder(); }
    },

    itemDragEnd: function () {
      this.dragGroup.forEach(function (node) {
        node.classList.remove('is-dragging', 'is-dragging-child');
      });
      if (this.dragPlaceholder && this.dragPlaceholder.parentNode) {
        this.dragPlaceholder.parentNode.removeChild(this.dragPlaceholder);
      }
      document.querySelectorAll('.navigation-tree-item.drag-over-top, .navigation-tree-item.drag-over-bottom').forEach(function (node) {
        node.classList.remove('drag-over-top', 'drag-over-bottom');
      });
      document.querySelectorAll('[data-navigation-items-tree].is-drag-active').forEach(function (node) {
        node.classList.remove('is-drag-active');
      });
      if (this.dragGhost && this.dragGhost.parentNode) { this.dragGhost.parentNode.removeChild(this.dragGhost); }
      document.body.classList.remove('navigation-item-dragging');
      this.dragItem = null;
      this.dragGroup = [];
      this.dragPlaceholder = null;
      this.dragGhost = null;
      this.dragPointerId = null;
      this.dragStarted = false;
      this.dragOrderSnapshot = '';
    },

    positionItemDragGhost: function (e) {
      if (!this.dragGhost) { return; }
      var left = Math.min(e.clientX + 14, window.innerWidth - this.dragGhost.offsetWidth - 12);
      var top = Math.min(e.clientY + 12, window.innerHeight - this.dragGhost.offsetHeight - 12);
      this.dragGhost.style.transform = 'translate3d(' + Math.max(12, left) + 'px,' + Math.max(12, top) + 'px,0)';
    },

    scrollItemTree: function (root, clientY) {
      var rect = root.getBoundingClientRect();
      var edge = 54;
      if (clientY < rect.top + edge) { root.scrollTop -= Math.ceil((rect.top + edge - clientY) / 5); }
      if (clientY > rect.bottom - edge) { root.scrollTop += Math.ceil((clientY - rect.bottom + edge) / 5); }
    },

    itemOrderSignature: function () {
      return Array.prototype.map.call(document.querySelectorAll('[data-navigation-items-tree] > ol > [data-navigation-item-node]'), function (node) {
        return node.getAttribute('data-navigation-item-node') + ':' + node.getAttribute('data-level');
      }).join('|');
    },

    itemBranch: function (node) {
      if (!node) { return []; }
      var branch = [node];
      var level = parseInt(node.getAttribute('data-level'), 10) || 0;
      var next = node.nextElementSibling;
      while (next) {
        if (next === this.dragPlaceholder) {
          next = next.nextElementSibling;
          continue;
        }
        if (parseInt(next.getAttribute('data-level'), 10) <= level) { break; }
        branch.push(next);
        next = next.nextElementSibling;
      }
      return branch;
    },

    changeItemLevel: function (node, delta) {
      if (!node || !delta) { return; }
      var level = parseInt(node.getAttribute('data-level'), 10) || 0;
      var nextLevel = Math.max(0, Math.min(2, level + delta));
      if (delta > 0 && !this.canIndentItem(node)) { return; }
      if (nextLevel === level) { return; }
      node.setAttribute('data-level', String(nextLevel));
      this.normalizeItemHierarchy();
      this.persistItemOrder();
    },

    canIndentItem: function (node) {
      var level = parseInt(node.getAttribute('data-level'), 10) || 0;
      if (level >= 2) { return false; }
      var previous = node.previousElementSibling;
      while (previous) {
        var previousLevel = parseInt(previous.getAttribute('data-level'), 10) || 0;
        if (previousLevel < level) { return false; }
        if (previousLevel === level) { return true; }
        previous = previous.previousElementSibling;
      }
      return false;
    },

    normalizeItemHierarchy: function () {
      var nodes = document.querySelectorAll('[data-navigation-items-tree] > ol > [data-navigation-item-node]');
      var parents = [];
      var positions = {};
      var previousLevel = 0;
      var self = this;
      nodes.forEach(function (node, index) {
        var level = Math.max(0, Math.min(2, parseInt(node.getAttribute('data-level'), 10) || 0));
        if (index === 0) { level = 0; }
        if (level > previousLevel + 1) { level = previousLevel + 1; }
        var parentId = level > 0 && parents[level - 1] ? parents[level - 1] : 0;
        if (level > 0 && !parentId) { level = 0; }
        parentId = level > 0 && parents[level - 1] ? parents[level - 1] : 0;
        var id = parseInt(node.getAttribute('data-navigation-item-node'), 10) || 0;
        var positionKey = String(parentId);
        positions[positionKey] = (positions[positionKey] || 0) + 1;
        parents[level] = id;
        parents.length = level + 1;
        previousLevel = level;
        node.setAttribute('data-level', String(level));
        node.setAttribute('data-parent-id', String(parentId));
        node.setAttribute('data-position', String(positions[positionKey]));
        var row = node.querySelector('[data-navigation-builder-item]');
        if (row) {
          row.classList.remove('mb-level-0', 'mb-level-1', 'mb-level-2');
          row.classList.add('mb-level-' + level);
        }
        var pos = node.querySelector('.si-pos');
        if (pos) { pos.textContent = String(positions[positionKey]); }
        var badge = node.querySelector('[data-navigation-level-badge]');
        if (badge) { badge.textContent = 'уровень ' + (level + 1); }
      });
      nodes.forEach(function (node) {
        var level = parseInt(node.getAttribute('data-level'), 10) || 0;
        var decrease = node.querySelector('[data-navigation-item-indent="-1"]');
        var increase = node.querySelector('[data-navigation-item-indent="1"]');
        if (decrease) { decrease.disabled = level === 0; }
        if (increase) { increase.disabled = !self.canIndentItem(node); }
      });
    },

    persistItemOrder: function () {
      var order = [];
      document.querySelectorAll('[data-navigation-items-tree] > ol > [data-navigation-item-node]').forEach(function (node) {
        var parentId = parseInt(node.getAttribute('data-parent-id'), 10) || 0;
        order.push({
          id: parseInt(node.getAttribute('data-navigation-item-node'), 10) || 0,
          parent_id: parentId,
          position: parseInt(node.getAttribute('data-position'), 10) || 1
        });
      });
      var data = new FormData();
      data.append('_csrf', this.csrf());
      data.append('order', JSON.stringify(order));
      var self = this;
      var root = document.querySelector('[data-navigation-items-tree]');
      this.orderSaving = true;
      if (root) { root.classList.add('is-order-saving'); }
      this.post(this.base() + '/navigation/' + encodeURIComponent(this.currentNavigationId) + '/items/reorder', data)
        .then(function (payload) {
          Adminx.Toast.show(payload.message || 'Порядок сохранён', 'success');
          self.loadItems();
        })
        .catch(function (err) {
          Adminx.Toast.show(err.message || 'Не удалось сохранить порядок', 'error');
          self.loadItems();
        }).finally(function () {
          self.orderSaving = false;
          var currentRoot = document.querySelector('[data-navigation-items-tree]');
          if (currentRoot) { currentRoot.classList.remove('is-order-saving'); }
        });
    },

    ajaxRefresh: function (message, keepOpen) {
      if (message) { Adminx.Toast.show(message, 'success'); }
      if (!keepOpen && Adminx.Drawer) { Adminx.Drawer.close(); }
      this.applyFilterUrl(window.location.href, false);
      if (keepOpen && window.Adminx.CodeEditor) {
        setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 120);
      }
    },

    clearErrors: function () {
      if (!this.form) { return; }
      this.form.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
      this.form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    },

    clearItemErrors: function () {
      if (!this.itemForm) { return; }
      this.itemForm.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
      this.itemForm.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    },

    showErrors: function (err) {
      if (err && err.errors) {
        Object.keys(err.errors).forEach(function (key) {
          var node = document.querySelector('[data-error="' + key + '"]');
          var field = document.querySelector('[name="' + key + '"]');
          if (node) { node.textContent = err.errors[key]; }
          if (field) { field.classList.add('is-invalid'); }
        });
      }
      Adminx.Toast.show((err && err.message) || 'Проверьте поля формы', 'error');
    },

    showItemErrors: function (err) {
      if (err && err.errors) {
        Object.keys(err.errors).forEach(function (key) {
          var node = document.querySelector('[data-error="' + key + '"]');
          if (node) { node.textContent = err.errors[key]; }
        });
      }
      Adminx.Toast.show((err && err.message) || 'Проверьте поля пункта', 'error');
    },

    esc: function (value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
      });
    },

    csrf: function () {
      var token = this.form ? this.form.querySelector('[name="_csrf"]') : document.querySelector('[name="_csrf"]');
      return token ? token.value : '';
    },

    post: function (url, data) {
      return fetch(url, {
        method: 'POST',
        body: data,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(this.json);
    },

    json: function (res) {
      return res.json().then(function (payload) {
        if (!res.ok || payload.success === false) {
          var err = new Error(payload.message || ('HTTP ' + res.status));
          err.errors = payload.errors || {};
          throw err;
        }
        return payload;
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Navigation.init(); });
  } else {
    Adminx.Navigation.init();
  }
})(window, document);
