(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Console = {
    root: null,
    currentId: 0,

    init: function () {
      this.root = document.querySelector('[data-console]');
      if (!this.root) { return; }
      var self = this;
      this.select().addEventListener('change', function () { self.load(this.value); });
      document.addEventListener('click', function (event) {
        if (event.target.closest('[data-console-new]')) { self.reset(); }
        if (event.target.closest('[data-console-save]')) { self.save(); }
        if (event.target.closest('[data-console-delete]')) { self.remove(); }
        if (event.target.closest('[data-console-lint]')) { self.lint(); }
        if (event.target.closest('[data-console-execute]')) { self.execute(); }
        if (event.target.closest('[data-console-clear]')) { self.setCode(''); }
      });
    },

    base: function () { return this.root.getAttribute('data-base') || Adminx.base(); },
    select: function () { return this.root.querySelector('[data-console-snippets]'); },
    name: function () { return this.root.querySelector('[data-console-name]'); },
    textarea: function () { return this.root.querySelector('[data-console-code]'); },
    code: function () {
      var textarea = this.textarea();
      if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.save(); }
      return textarea.value;
    },
    setCode: function (value) {
      var textarea = this.textarea();
      textarea.value = value || '';
      if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.setValue(textarea.value); }
    },

    request: function (url, fd, method) {
      Adminx.Loader.show();
      return Adminx.Ajax.request(url, { method: method || 'POST', body: fd || null }).then(function (payload) {
        Adminx.Loader.hide();
        return payload;
      }).catch(function () {
        Adminx.Loader.hide();
        return { ok: false, data: { success: false, message: 'Ошибка сети' } };
      });
    },

    load: function (id) {
      if (!id) { this.reset(); return; }
      var self = this;
      this.request(this.base() + '/system/console/snippets/' + encodeURIComponent(id), null, 'GET').then(function (payload) {
        var data = payload.data && payload.data.data;
        if (!payload.ok || !data) { Adminx.Toast.show((payload.data && payload.data.message) || 'Сниппет не найден', 'error'); return; }
        self.currentId = parseInt(data.id, 10) || 0;
        self.name().value = data.name || '';
        self.setCode(data.code || '');
        self.toggleDelete();
      });
    },

    reset: function () {
      this.currentId = 0;
      this.select().value = '';
      this.name().value = '';
      this.setCode('echo "AVE.cms " . APP_VERSION . PHP_EOL;');
      this.toggleDelete();
      this.name().focus();
    },

    toggleDelete: function () {
      var button = this.root.querySelector('[data-console-delete]');
      if (button) { button.disabled = !this.currentId; }
    },

    save: function () {
      var self = this;
      var fd = new FormData();
      fd.append('id', this.currentId || 0);
      fd.append('name', this.name().value.trim());
      fd.append('code', this.code());
      this.request(this.base() + '/system/console/snippets', fd).then(function (payload) {
        var body = payload.data || {};
        Adminx.Toast.show(body.message || (payload.ok ? 'Сохранено' : 'Ошибка'), payload.ok ? 'success' : 'error');
        if (!payload.ok) { return; }
        self.currentId = parseInt(body.data.id, 10) || 0;
        self.renderOptions(body.data.snippets || []);
        self.select().value = String(self.currentId);
        self.toggleDelete();
      });
    },

    remove: function () {
      if (!this.currentId) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить сниппет?', message: this.name().value || 'Сниппет будет удалён.', confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () {
          self.request(self.base() + '/system/console/snippets/' + self.currentId + '/delete', new FormData()).then(function (payload) {
            var body = payload.data || {};
            Adminx.Toast.show(body.message || (payload.ok ? 'Удалено' : 'Ошибка'), payload.ok ? 'success' : 'error');
            if (payload.ok) { self.renderOptions(body.data.snippets || []); self.reset(); }
          });
        }
      });
    },

    lint: function () { this.runAction('lint'); },
    execute: function () { this.runAction('execute'); },

    runAction: function (action) {
      var self = this;
      var fd = new FormData();
      fd.append('code', this.code());
      this.setOutput(action === 'execute' ? 'Выполнение...' : 'Проверка...', '');
      this.request(this.base() + '/system/console/' + action, fd).then(function (payload) {
        var body = payload.data || {};
        var data = body.data || {};
        if (action === 'execute') {
          self.setOutput(data.output || body.message || '', data.duration_ms != null ? data.duration_ms + ' мс · код ' + data.exit_code : 'Ошибка');
        } else {
          self.setOutput(data.output || (body.errors && body.errors.code) || body.message || '', body.success ? 'Синтаксис корректен' : 'Ошибка синтаксиса');
        }
        Adminx.Toast.show(body.message || (payload.ok ? 'Готово' : 'Ошибка'), payload.ok ? 'success' : 'error');
      });
    },

    setOutput: function (text, meta) {
      var output = document.querySelector('[data-console-output]');
      var info = document.querySelector('[data-console-meta]');
      if (output) { output.textContent = text || '(вывод пуст)'; }
      if (info) { info.textContent = meta || ''; }
    },

    renderOptions: function (items) {
      var select = this.select();
      select.innerHTML = '<option value="">Новый сниппет</option>';
      items.forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.name;
        select.appendChild(option);
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Console.init(); });
  } else { Adminx.Console.init(); }
})(window, document);
