(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.ViewOverrides = {
    root: null,
    form: null,
    field: null,
    clean: '',
    dirty: false,

    init: function () {
      this.root = document.querySelector('[data-view-overrides]');
      this.form = document.querySelector('[data-view-override-form]');
      this.field = this.form ? this.form.elements.content : null;
      if (!this.root || !this.form || !this.field) { return; }
      this.clean = this.field.value || '';
      this.bind();
    },

    base: function () { return this.root.getAttribute('data-base') || Adminx.base(); },
    route: function () { return this.root.getAttribute('data-route') || ''; },
    code: function () { return this.form.getAttribute('data-code') || ''; },

    bind: function () {
      var self = this;
      this.form.addEventListener('submit', function (event) {
        event.preventDefault();
        self.save();
      });
      this.form.addEventListener('input', function () { self.setDirty((self.field.value || '') !== self.clean); });
      this.form.addEventListener('click', function (event) {
        if (event.target.closest('[data-view-override-lint]')) { self.lint(); return; }
        if (event.target.closest('[data-view-override-fullscreen]')) { self.fullscreen(); return; }
        if (event.target.closest('[data-view-override-delete]')) { self.removeOverride(); }
      });
      window.addEventListener('beforeunload', function (event) {
        if (!self.dirty) { return; }
        event.preventDefault();
        event.returnValue = '';
      });
    },

    request: function (url, data) {
      return Adminx.Ajax.post(url, data).then(function (response) {
        var payload = response.data || {};
        if (!response.ok || !payload.success) {
          var error = new Error(payload.message || 'Не удалось выполнить действие');
          error.payload = payload;
          throw error;
        }
        return payload;
      });
    },

    sync: function () {
      if (Adminx.CodeEditor) { Adminx.CodeEditor.syncAll(this.form); }
    },

    save: function () {
      var self = this;
      this.sync();
      Adminx.Loader.show();
      this.request(this.base() + this.route() + '/' + encodeURIComponent(this.code()), new FormData(this.form))
        .then(function (payload) {
          self.clean = self.field.value || '';
          self.setDirty(false);
          Adminx.Toast.show(payload.message || 'Шаблон сохранён', 'success');
          window.setTimeout(function () { window.location.reload(); }, 350);
        })
        .catch(function (error) { self.fail(error); })
        .finally(function () { Adminx.Loader.hide(); });
    },

    lint: function () {
      var self = this;
      var result = this.form.querySelector('[data-view-override-lint-result]');
      this.sync();
      var data = new FormData();
      data.set('_csrf', this.form.elements._csrf.value);
      data.set('content', this.field.value || '');
      if (result) { result.className = 'field-hint view-override-lint'; result.textContent = 'Проверяем Twig...'; }
      this.request(this.base() + this.route() + '/lint', data)
        .then(function (payload) {
          if (result) { result.classList.add('is-ok'); result.textContent = payload.message || 'Twig-синтаксис корректен'; }
        })
        .catch(function (error) {
          if (result) { result.classList.add('is-error'); result.textContent = error.message; }
          self.fail(error, false);
        });
    },

    fullscreen: function () {
      if (!this.field._adminxCodeMirror || !Adminx.CodeEditor) { return; }
      Adminx.CodeEditor.toggleFullscreen(this.field._adminxCodeMirror);
    },

    removeOverride: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Вернуться к резервному шаблону?',
        message: 'Файл активной темы будет удалён. Его последняя версия останется в ревизиях темы, а сайт сразу начнёт использовать резерв компонента.',
        confirmLabel: 'Вернуться к резерву',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', self.form.elements._csrf.value);
          Adminx.Loader.show();
          self.request(self.base() + self.route() + '/' + encodeURIComponent(self.code()) + '/delete', data)
            .then(function (payload) {
              self.setDirty(false);
              Adminx.Toast.show(payload.message || 'Переопределение удалено', 'success');
              window.setTimeout(function () { window.location.reload(); }, 350);
            })
            .catch(function (error) { self.fail(error); })
            .finally(function () { Adminx.Loader.hide(); });
        }
      });
    },

    setDirty: function (dirty) {
      this.dirty = !!dirty;
      var state = this.form.querySelector('[data-view-override-save-state]');
      if (!state) { return; }
      state.classList.toggle('is-dirty', this.dirty);
      state.textContent = this.dirty ? 'Есть несохранённые изменения' : '';
    },

    fail: function (error, toast) {
      if (toast !== false) { Adminx.Toast.show(error && error.message ? error.message : 'Не удалось выполнить действие', 'error'); }
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.ViewOverrides.init(); });
  } else {
    Adminx.ViewOverrides.init();
  }
})(window, document);
