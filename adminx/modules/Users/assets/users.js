/**
 * JS раздела «Пользователи». Drawer-форма (создание/правка), toggle активности,
 * удаление — через общий Adminx.Ajax по контракту success/error. Подключается
 * точечно из Users\Controller (ТЗ §4).
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Users = {
    form: null,

    init: function () {
      this.form = document.getElementById('userForm');
      if (!this.form) { return; }
      var self = this;

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-user-new]'))  { self.fillNew(); }
        var editBtn = e.target.closest('[data-user-edit]');
        if (editBtn) { self.fillEdit(editBtn.closest('tr')); }
        var delBtn = e.target.closest('[data-user-delete]');
        if (delBtn) { self.remove(delBtn.closest('tr')); }
        var pwBtn = e.target.closest('[data-pw-toggle]');
        if (pwBtn) { self.togglePw(pwBtn); }
        if (e.target.closest('[data-pw-generate]')) { self.generatePassword(); }
        if (e.target.closest('[data-pw-copy]')) { self.copyPassword(); }
      });

      document.addEventListener('change', function (e) {
        var t = e.target.closest('[data-user-toggle]');
        if (t) { self.toggle(t); }
      });

      this.form.addEventListener('input', function (e) {
        var pw = e.target.closest('[data-pw-input]');
        if (pw) { self.strength(pw.value || ''); }
        if (e.target.matches('[name="name"], [name="email"]')) { self.updatePreview(); }
      });

      this.form.addEventListener('change', function (e) {
        if (e.target.matches('[name="role"]')) { self.updatePreview(); }
      });

      this.form.addEventListener('submit', function (e) {
        e.preventDefault();
        self.submit();
      });
    },

    base: function () { return this.form.getAttribute('data-base') || Adminx.base(); },

    clearErrors: function () {
      this.form.querySelectorAll('[data-error]').forEach(function (s) { s.textContent = ''; });
      this.form.querySelectorAll('.input, .select').forEach(function (i) { i.classList.remove('is-invalid'); });
    },

    setField: function (name, value) {
      var el = this.form.querySelector('[name="' + name + '"]');
      if (el) { el.value = value; }
    },

    fillNew: function () {
      this.clearErrors();
      this.form.reset();
      this.setField('id', '');
      this.form.querySelector('[name="is_active"]').checked = true;
      this.setSelfLock(false);
      this.resetPassword();
      document.getElementById('userDrawerTitle').textContent = 'Новый пользователь';
      var hint = this.form.querySelector('[data-pass-hint]');
      if (hint) { hint.textContent = 'Пароль обязателен для новой учётной записи.'; }
      var dates = this.form.querySelector('[data-user-dates]');
      if (dates) { dates.hidden = true; }
      this.updatePreview();
    },

    fillEdit: function (row) {
      if (!row) { return; }
      this.clearErrors();
      this.setField('id', row.dataset.id);
      this.setField('name', row.dataset.name);
      this.setField('email', row.dataset.email);
      this.setField('login', row.dataset.login || '');
      this.setField('phone', row.dataset.phone || '');
      this.setField('role', row.dataset.role);
      this.setField('password', '');
      this.resetPassword();
      this.form.querySelector('[name="is_active"]').checked = row.dataset.active === '1';
      this.setSelfLock(row.hasAttribute('data-self'));
      document.getElementById('userDrawerTitle').textContent = 'Изменение: ' + row.dataset.name;
      var hint = this.form.querySelector('[data-pass-hint]');
      if (hint) { hint.textContent = 'Оставьте пароль пустым, если его не нужно менять.'; }
      var dates = this.form.querySelector('[data-user-dates]');
      if (dates) { dates.hidden = false; }
      var created = this.form.querySelector('[data-user-created]');
      var updated = this.form.querySelector('[data-user-updated]');
      if (created) { created.textContent = this.formatDate(row.dataset.created); }
      if (updated) { updated.textContent = this.formatDate(row.dataset.updated); }
      this.updatePreview(row.dataset.roleLabel || row.dataset.role);
    },

    setSelfLock: function (locked) {
      var role = this.form.querySelector('[name="role"]');
      var active = this.form.querySelector('[name="is_active"]');
      var activeCard = active ? active.closest('.users-active-card') : null;
      var roleHint = this.form.querySelector('[data-user-self-role-hint]');
      var activeHint = this.form.querySelector('[data-user-active-hint]');
      if (role) { role.disabled = !!locked; }
      if (active) { active.disabled = !!locked; }
      if (activeCard) { activeCard.classList.toggle('is-locked', !!locked); }
      if (roleHint) { roleHint.hidden = !locked; }
      if (activeHint) { activeHint.textContent = locked ? 'Собственную учётную запись отключить нельзя.' : 'Отключённый пользователь не сможет войти.'; }
    },

    formatDate: function (value) {
      if (!value) { return '—'; }
      return String(value).replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2})/, '$3.$2.$1').slice(0, 16);
    },

    updatePreview: function (roleLabel) {
      var name = this.form.querySelector('[name="name"]').value.trim() || 'Новый пользователь';
      var email = this.form.querySelector('[name="email"]').value.trim() || 'Учётная запись ещё не сохранена';
      var role = this.form.querySelector('[name="role"]');
      var roleText = roleLabel || (role && role.options[role.selectedIndex] ? role.options[role.selectedIndex].text : 'Пользователь');
      var avatar = this.form.querySelector('[data-user-preview-avatar]');
      var nameNode = this.form.querySelector('[data-user-preview-name]');
      var meta = this.form.querySelector('[data-user-preview-meta]');
      var roleNode = this.form.querySelector('[data-user-preview-role]');
      if (avatar) { avatar.textContent = name.slice(0, 2).toUpperCase(); }
      if (nameNode) { nameNode.textContent = name; }
      if (meta) { meta.textContent = email; }
      if (roleNode) { roleNode.textContent = roleText; }
    },

    // --- Пароль: показ/скрытие + индикатор надёжности (компоненты AdminKit) ---
    PW_HINT: 'Минимум 6 символов, желательно буквы разных регистров и цифры',

    togglePw: function (btn) {
      var wrap = btn.closest('.input-wrap');
      var input = wrap ? wrap.querySelector('input') : null;
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      var icon = btn.querySelector('i');
      if (icon) { icon.className = show ? 'ti ti-eye-off' : 'ti ti-eye'; }
    },

    strength: function (value) {
      var meter = this.form.querySelector('.pw-strength');
      var hint  = this.form.querySelector('[data-pw-hint]');
      if (!meter) { return; }

      if (value === '') {
        meter.dataset.level = '';
        if (hint) { hint.textContent = this.PW_HINT; }
        return;
      }

      var s = 0;
      if (value.length >= 6) { s++; }
      if (value.length >= 10) { s++; }
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { s++; }
      if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) { s++; }
      if (s < 1) { s = 1; }
      if (s > 4) { s = 4; }

      meter.dataset.level = String(s);
      var labels = { 1: 'слабый', 2: 'средний', 3: 'хороший', 4: 'отличный' };
      if (hint) { hint.textContent = 'Надёжность: ' + labels[s]; }
    },

    randomPassword: function (len) {
      var lower = 'abcdefghijkmnpqrstuvwxyz';
      var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
      var digit = '23456789';
      var sym   = '!@#$%^&*-_=+';
      var all   = lower + upper + digit + sym;
      var rnd = function (max) {
        var a = new Uint32Array(1);
        (window.crypto || window.msCrypto).getRandomValues(a);
        return a[0] % max;
      };
      var pick = function (set) { return set.charAt(rnd(set.length)); };
      var out = [pick(lower), pick(upper), pick(digit), pick(sym)];
      for (var i = out.length; i < len; i++) { out.push(pick(all)); }
      for (var j = out.length - 1; j > 0; j--) { // перемешать
        var k = rnd(j + 1);
        var t = out[j]; out[j] = out[k]; out[k] = t;
      }
      return out.join('');
    },

    generatePassword: function () {
      var input = this.form.querySelector('[data-pw-input]');
      if (!input) { return; }
      input.value = this.randomPassword(14);
      input.type = 'text'; // показываем, чтобы можно было скопировать
      var icon = this.form.querySelector('[data-pw-toggle] i');
      if (icon) { icon.className = 'ti ti-eye-off'; }
      this.strength(input.value);
      Adminx.Toast.show('Пароль сгенерирован', 'success');
    },

    copyPassword: function () {
      var input = this.form.querySelector('[data-pw-input]');
      var val = input ? input.value : '';
      if (!val) {
        Adminx.Toast.show('Сначала введите или сгенерируйте пароль', 'error');
        return;
      }
      var ok = function () { Adminx.Toast.show('Пароль скопирован в буфер', 'success'); };
      var fail = function () { Adminx.Toast.show('Не удалось скопировать', 'error'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(ok).catch(function () {
          if (!Adminx.Users.legacyCopy(input)) { fail(); } else { ok(); }
        });
      } else if (Adminx.Users.legacyCopy(input)) {
        ok();
      } else {
        fail();
      }
    },

    legacyCopy: function (input) {
      try {
        var wasPassword = input.type === 'password';
        if (wasPassword) { input.type = 'text'; }
        input.focus();
        input.select();
        var done = document.execCommand('copy');
        if (wasPassword) { input.type = 'password'; }
        return done;
      } catch (e) {
        return false;
      }
    },

    resetPassword: function () {
      var meter = this.form.querySelector('.pw-strength');
      if (meter) { meter.dataset.level = ''; }
      var hint = this.form.querySelector('[data-pw-hint]');
      if (hint) { hint.textContent = this.PW_HINT; }
      var input = this.form.querySelector('[data-pw-input]');
      if (input) { input.type = 'password'; }
      var icon = this.form.querySelector('[data-pw-toggle] i');
      if (icon) { icon.className = 'ti ti-eye'; }
    },

    submit: function () {
      this.clearErrors();
      var id = (this.form.querySelector('[name="id"]').value || '').trim();
      var url = this.base() + '/users' + (id ? '/' + id : '');
      var fd = new FormData(this.form);
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
        // ошибки валидации по полям
        var errors = d.errors || {};
        Object.keys(errors).forEach(function (field) {
          var span = self.form.querySelector('[data-error="' + field + '"]');
          if (span) { span.textContent = errors[field]; }
          var input = self.form.querySelector('[name="' + field + '"]');
          if (input) { input.classList.add('is-invalid'); }
        });
        if (d.message) { Adminx.Toast.show(d.message, 'error'); }
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    toggle: function (checkbox) {
      var row = checkbox.closest('tr');
      var id = row.dataset.id;
      //-- Нельзя отключать собственную учётную запись (дублирует запрет в контроллере).
      if (row.hasAttribute('data-self')) {
        checkbox.checked = true;
        Adminx.Toast.show('Нельзя отключить свою учётную запись', 'error');
        return;
      }
      Adminx.Ajax.post(this.base() + '/users/' + id + '/toggle').then(function (payload) {
        var d = payload.data || {};
        if (d.success) {
          row.dataset.active = String((d.data && d.data.is_active) || 0);
          Adminx.Toast.show(d.message, 'success');
        } else {
          checkbox.checked = !checkbox.checked;
          Adminx.Toast.show(d.message || 'Не удалось', 'error');
        }
      }).catch(function () {
        checkbox.checked = !checkbox.checked;
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    remove: function (row) {
      var id = row.dataset.id;
      var base = this.base();
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить пользователя?',
        message: '«' + row.dataset.name + '» (' + row.dataset.email + ') будет удалён без возможности восстановления.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/users/' + id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var d = payload.data || {};
            if (d.success) {
              row.remove();
              Adminx.Toast.show(d.message, 'success');
            } else {
              Adminx.Toast.show(d.message || 'Не удалось удалить', 'error');
            }
          }).catch(function () {
            Adminx.Loader.hide();
            Adminx.Toast.show('Ошибка сети', 'error');
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Users.init(); });
  } else {
    Adminx.Users.init();
  }
})(window, document);
