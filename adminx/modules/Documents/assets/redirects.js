(function () {
  'use strict';

  window.Adminx = window.Adminx || {};
  Adminx.DocumentRedirects = {
    filterTimer: null,
    filterAbort: null,
    filterRequest: 0,
    base: function () { return window.ADMINX_BASE || Adminx.base(); },
    csrf: function () { var el = document.querySelector('[data-redirect-csrf]'); return el ? el.value : ''; },
    json: function (response) { return response.json().then(function (data) { if (!response.ok || data.success === false) { throw new Error(data.message || 'Ошибка запроса'); } return data; }); },
    esc: function (value) { var div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; },

    init: function () {
      var self = this;
      document.addEventListener('submit', function (event) {
        var filter = event.target.closest('[data-redirect-filter]');
        if (filter) { event.preventDefault(); self.load(filter.action + '?' + new URLSearchParams(new FormData(filter)).toString()); return; }
        var form = event.target.closest('[data-redirect-form]');
        if (form) { event.preventDefault(); self.save(form); }
      });
      document.addEventListener('change', function (event) {
        if (event.target.matches('[data-redirect-filter] select')) { event.target.form.requestSubmit(); }
      });
      document.addEventListener('input', function (event) {
        if (!event.target.matches('[data-redirect-filter] input[type="search"]')) { return; }
        var searchInput = event.target;
        clearTimeout(self.filterTimer);
        self.filterTimer = setTimeout(function () { if (searchInput.form) { searchInput.form.requestSubmit(); } }, 350);
      });
      document.addEventListener('click', function (event) {
        var page = event.target.closest('[data-redirect-page]');
        if (page) { event.preventDefault(); self.load(page.href); return; }
        if (event.target.closest('[data-redirect-filter-reset]')) { self.load(self.base() + '/documents/redirects'); return; }
        var pick = event.target.closest('[data-redirect-document-pick]');
        if (pick) { self.pickDocument(pick.closest('[data-redirect-form]')); return; }
        if (event.target.closest('[data-redirect-create]')) { self.open(); return; }
        var edit = event.target.closest('[data-redirect-edit]');
        if (edit) { self.open(edit.closest('[data-redirect-row]')); return; }
        var remove = event.target.closest('[data-redirect-delete]');
        if (remove) { self.remove(remove.closest('[data-redirect-row]')); return; }
        var copy = event.target.closest('[data-copy]');
        if (copy) { navigator.clipboard.writeText(copy.getAttribute('data-copy') || '').then(function () { Adminx.Toast.show('URL скопирован', 'success'); }); }
      });
      window.addEventListener('popstate', function () { self.load(location.href, false); });
    },

    load: function (url, push) {
      var self = this;
      clearTimeout(this.filterTimer);
      this.filterTimer = null;
      var requestId = ++this.filterRequest;
      var active = document.activeElement;
      var focusName = active && active.closest && active.closest('[data-redirect-filter]') ? active.getAttribute('name') : '';
      if (push !== false) { push = true; }
      if (this.filterAbort && typeof this.filterAbort.abort === 'function') { this.filterAbort.abort(); }
      this.filterAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
      Adminx.Loader.show();
      fetch(url, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin', signal: this.filterAbort ? this.filterAbort.signal : undefined })
        .then(function (response) { if (!response.ok) { throw new Error('Не удалось загрузить список'); } return response.text(); })
        .then(function (html) {
          if (requestId !== self.filterRequest) { return; }
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var next = doc.querySelector('[data-redirects-content]');
          var current = document.querySelector('[data-redirects-content]');
          if (!next || !current) { throw new Error('Некорректный ответ'); }
          current.replaceWith(next);
          if (push) { history.pushState({}, '', url); }
          if (focusName) {
            var nextFocus = document.querySelector('[data-redirect-filter] [name="' + focusName.replace(/"/g, '\\"') + '"]');
            if (nextFocus) { nextFocus.focus(); }
          }
        })
        .catch(function (error) { if (!error || error.name !== 'AbortError') { Adminx.Toast.show(error.message, 'error'); } })
        .finally(function () { if (requestId === self.filterRequest) { self.filterAbort = null; Adminx.Loader.hide(); } });
    },

    open: function (row) {
      var form = document.querySelector('[data-redirect-form]');
      if (!form) { return; }
      form.reset();
      form.elements.id.value = row ? row.getAttribute('data-id') : '0';
      this.setDocument(form, row ? row.getAttribute('data-document-id') : '', row ? row.getAttribute('data-document-title') : '');
      var picker = form.querySelector('[data-redirect-document-pick]');
      if (picker) { picker.disabled = !!row; }
      form.elements.alias.value = row ? row.getAttribute('data-alias') : '';
      form.elements.header.value = row ? row.getAttribute('data-header') : '301';
      var title = document.querySelector('[data-redirect-title]');
      var subtitle = document.querySelector('[data-redirect-subtitle]');
      if (title) { title.textContent = row ? 'Редактирование редиректа' : 'Новый редирект'; }
      if (subtitle) { subtitle.textContent = row ? ('#' + row.getAttribute('data-document-id') + ' · ' + row.getAttribute('data-document-title')) : 'Старый адрес существующего документа.'; }
      Adminx.Drawer.open('redirectDrawer');
      setTimeout(function () { (row ? form.elements.alias : picker).focus(); }, 100);
    },

    setDocument: function (form, id, title, alias) {
      if (!form) { return; }
      id = parseInt(id, 10) || 0;
      form.elements.document_id.value = id || '';
      var titleEl = form.querySelector('[data-redirect-document-title]');
      var metaEl = form.querySelector('[data-redirect-document-meta]');
      if (titleEl) { titleEl.textContent = id ? (title || ('Документ #' + id)) : 'Документ не выбран'; }
      if (metaEl) { metaEl.textContent = id ? ('#' + id + (alias ? ' · /' + String(alias).replace(/^\/+/, '') : '')) : 'Нажмите, чтобы найти документ'; }
    },

    pickDocument: function (form) {
      if (!form) { return; }
      var self = this;
      var overlay = document.createElement('div');
      overlay.className = 'overlay documents-picker-overlay';
      overlay.innerHTML = '<div class="modal picker-modal documents-relation-picker" role="dialog" aria-modal="true" aria-labelledby="redirectDocumentPickerTitle">'
        + '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div style="flex:1"><h3 id="redirectDocumentPickerTitle">Документ назначения</h3><p class="text-secondary" style="margin-top:4px">Найдите документ по ID, названию или alias.</p></div><button class="modal-close" type="button" data-redirect-picker-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
        + '<div class="modal-body"><div class="input-wrap documents-relation-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="ID, название или alias" data-redirect-picker-search></div><div class="documents-picker-status" data-redirect-picker-status>Загрузка...</div><div class="documents-relation-list" data-redirect-picker-list></div></div>'
        + '<div class="modal-footer"><div class="mf-left" data-redirect-picker-count></div><button class="btn btn-ghost" type="button" data-redirect-picker-close>Закрыть</button></div></div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });
      var search = overlay.querySelector('[data-redirect-picker-search]');
      var list = overlay.querySelector('[data-redirect-picker-list]');
      var status = overlay.querySelector('[data-redirect-picker-status]');
      var count = overlay.querySelector('[data-redirect-picker-count]');
      var timer = null;
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 160);
      };
      var onKey = function (event) { if (event.key === 'Escape') { close(); } };
      var load = function () {
        var params = new URLSearchParams();
        params.set('q', search.value.trim());
        params.set('limit', 30);
        status.hidden = false;
        status.textContent = 'Загрузка...';
        fetch(self.base() + '/documents/picker?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(self.json)
          .then(function (payload) {
            var items = ((payload.data || {}).items) || [];
            list.innerHTML = items.map(function (item) {
              return '<button class="documents-relation-item" type="button" data-redirect-picker-id="' + self.esc(item.id) + '" data-title="' + self.esc(item.title || '') + '" data-alias="' + self.esc(item.alias || '') + '"><span class="documents-relation-id">#' + self.esc(item.id) + '</span><span><b>' + self.esc(item.title || 'Без названия') + '</b><small>' + self.esc(item.alias || 'без alias') + '</small></span><em>' + self.esc(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</em></button>';
            }).join('');
            status.hidden = items.length > 0;
            status.textContent = search.value.trim() ? 'Ничего не найдено' : 'Документы не найдены';
            count.textContent = items.length ? 'Показано: ' + items.length : '';
          })
          .catch(function () { list.innerHTML = ''; status.hidden = false; status.textContent = 'Не удалось загрузить документы'; });
      };
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay || event.target.closest('[data-redirect-picker-close]')) { close(); return; }
        var item = event.target.closest('[data-redirect-picker-id]');
        if (!item) { return; }
        self.setDocument(form, item.getAttribute('data-redirect-picker-id'), item.getAttribute('data-title'), item.getAttribute('data-alias'));
        close();
        form.elements.alias.focus();
      });
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 220); });
      document.addEventListener('keydown', onKey);
      load();
      search.focus();
    },

    save: function (form) {
      var self = this;
      var documentId = parseInt(form.elements.document_id.value, 10) || 0;
      var id = parseInt(form.elements.id.value, 10) || 0;
      if (!documentId) { Adminx.Toast.show('Выберите документ назначения', 'error'); var picker = form.querySelector('[data-redirect-document-pick]'); if (picker) { picker.focus(); } return; }
      var data = new FormData(form); data.append('_csrf', this.csrf());
      Adminx.Loader.show();
      fetch(this.base() + '/documents/' + documentId + '/aliases' + (id ? '/' + id : ''), { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-CSRF-Token': this.csrf() }, credentials: 'same-origin' })
        .then(this.json)
        .then(function (payload) { Adminx.Toast.show(payload.message || 'Редирект сохранён', 'success'); Adminx.Drawer.close('redirectDrawer'); self.load(location.href, false); })
        .catch(function (error) { Adminx.Toast.show(error.message, 'error'); })
        .finally(function () { Adminx.Loader.hide(); });
    },

    remove: function (row) {
      if (!row) { return; }
      var self = this;
      Adminx.Confirm.open({ kind: 'danger', title: 'Удалить редирект?', message: 'Старый URL перестанет перенаправлять на документ.', confirmLabel: 'Удалить', onConfirm: function () {
        var data = new FormData(); data.append('_csrf', self.csrf());
        Adminx.Loader.show();
        fetch(self.base() + '/documents/' + row.getAttribute('data-document-id') + '/aliases/' + row.getAttribute('data-id') + '/delete', { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-CSRF-Token': self.csrf() }, credentials: 'same-origin' })
          .then(self.json)
          .then(function (payload) { Adminx.Toast.show(payload.message || 'Редирект удалён', 'success'); self.load(location.href, false); })
          .catch(function (error) { Adminx.Toast.show(error.message, 'error'); })
          .finally(function () { Adminx.Loader.hide(); });
      }});
    }
  };

  Adminx.DocumentRedirects.init();
}());
