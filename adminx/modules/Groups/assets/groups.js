/**
 * JS раздела «Роли и права». Drawer создания/правки роли + назначение прав
 * (чекбоксы по модулям). Ajax по контракту success/error, подтверждение —
 * Adminx.Confirm (без нативных промтов).
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Groups = {
    form: null,

    init: function () {
      this.form = document.getElementById('roleForm');
      if (!this.form) { return; }
      var self = this;

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-role-new]')) { self.fillNew(); }
        var edit = e.target.closest('[data-role-edit]');
        if (edit) { self.fillEdit(edit.closest('tr')); }
        var del = e.target.closest('[data-role-delete]');
        if (del) { self.remove(del.closest('tr')); }
        if (e.target.closest('[data-perms-all]'))  { self.setAll(true); }
        if (e.target.closest('[data-perms-none]')) { self.setAll(false); }
      });

      this.form.addEventListener('submit', function (e) {
        e.preventDefault();
        self.submit();
      });
    },

    base: function () { return this.form.getAttribute('data-base') || Adminx.base(); },

    clearErrors: function () {
      this.form.querySelectorAll('[data-error]').forEach(function (s) { s.textContent = ''; });
      this.form.querySelectorAll('.is-invalid').forEach(function (i) { i.classList.remove('is-invalid'); });
    },

    field: function (name) { return this.form.querySelector('[name="' + name + '"]'); },

    checks: function () { return this.form.querySelectorAll('input[name="perms[]"]'); },

    setAll: function (on) {
      this.checks().forEach(function (c) { if (!c.disabled) { c.checked = on; } });
    },

    lockPerms: function (locked) {
      var wrap = this.form.querySelector('[data-perms-wrap]');
      var note = this.form.querySelector('[data-role-admin-note]');
      this.checks().forEach(function (c) { c.disabled = locked; });
      if (wrap) { wrap.style.opacity = locked ? '0.5' : ''; }
      // .alert{display:flex} перебивает атрибут hidden — переключаем через style.display
      if (note) { note.style.display = locked ? '' : 'none'; }
    },

    fillNew: function () {
      this.clearErrors();
      this.form.reset();
      this.field('id').value = '';
      var code = this.field('code');
      code.disabled = false;
      this.setAll(false);
      this.lockPerms(false);
      document.getElementById('roleDrawerTitle').textContent = 'Новая роль';
    },

    fillEdit: function (row) {
      if (!row) { return; }
      this.clearErrors();
      var self = this;
      this.field('id').value = row.dataset.id;
      this.field('name').value = row.dataset.name;
      var code = this.field('code');
      code.value = row.dataset.code;
      code.disabled = true; // код не меняем после создания
      document.getElementById('roleDrawerTitle').textContent = 'Роль: ' + row.dataset.name;
      this.setAll(false);
      this.lockPerms(false);

      // подтягиваем назначенные права
      Adminx.Ajax.request(this.base() + '/roles/' + row.dataset.id).then(function (payload) {
        var d = payload.data || {};
        var codes = (d.data && d.data.permissions) || [];
        var map = {};
        codes.forEach(function (c) { map[c] = true; });
        self.checks().forEach(function (c) { c.checked = !!map[c.value]; });
        if ((d.data && d.data.code) === 'admin') { self.lockPerms(true); }
      });
    },

    submit: function () {
      this.clearErrors();
      var id = (this.field('id').value || '').trim();
      var url = this.base() + '/roles' + (id ? '/' + id : '');
      var fd = new FormData(this.form);
      // отключённый code (при правке) не попадает в FormData — это ок, он неизменяем
      var self = this;

      Adminx.Loader.show();
      Adminx.Ajax.post(url, fd).then(function (payload) {
        Adminx.Loader.hide();
        var d = payload.data || {};
        if (d.success) {
          Adminx.Ajax.handle(payload);
          if (!d.redirect) { window.location.reload(); }
          return;
        }
        var errors = d.errors || {};
        Object.keys(errors).forEach(function (f) {
          var span = self.form.querySelector('[data-error="' + f + '"]');
          if (span) { span.textContent = errors[f]; }
          var input = self.field(f);
          if (input) { input.classList.add('is-invalid'); }
        });
        if (d.message) { Adminx.Toast.show(d.message, 'error'); }
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    remove: function (row) {
      var id = row.dataset.id;
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить роль?',
        message: 'Роль «' + row.dataset.name + '» будет удалена вместе с назначением прав.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/roles/' + id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) { row.remove(); Adminx.Toast.show(d.message, 'success'); }
            else { Adminx.Toast.show(d.message || 'Не удалось удалить', 'error'); }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Groups.init(); });
  } else {
    Adminx.Groups.init();
  }
})(window, document);
