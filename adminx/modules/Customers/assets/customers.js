(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Customers = {
    draggedField: null,
    authTemplateKey: '',

    init: function () {
      var self = this;

      document.addEventListener('change', function (event) {
        var customer = event.target.closest('[data-customer-toggle]');
        if (customer) {
          self.request(customer.getAttribute('data-url'), new FormData()).catch(function () { customer.checked = !customer.checked; });
          return;
        }
        var fieldToggle = event.target.closest('[data-field-toggle]');
        if (fieldToggle) { self.toggleField(fieldToggle); return; }
        if (event.target.matches('[data-customer-field] [name="type"]')) { self.updateOptionsVisibility(); }
        if (event.target.matches('[data-auth-show]')) { self.syncAuthFieldRows(); }
        if (event.target.matches('[data-registration-mode]')) { self.syncAuthMode(); }
        if (event.target.matches('[data-customer-admin-access]')) { self.syncAdminAccess(); }
      });

      document.addEventListener('input', function (event) {
        if (event.target.closest('[data-field-options]')) { self.syncOptions(); }
      });

      document.addEventListener('click', function (event) {
        var customerEdit = event.target.closest('[data-customer-edit]');
        if (customerEdit) { event.preventDefault(); self.openCustomer(customerEdit); return; }
        var customerDelete = event.target.closest('[data-customer-delete]');
        if (customerDelete) { event.preventDefault(); self.deleteCustomer(customerDelete); return; }
        if (event.target.closest('[data-field-new]')) { self.fieldForm(null); }
        var edit = event.target.closest('[data-field-edit]');
        if (edit) { self.fieldForm(JSON.parse(edit.closest('[data-field]').getAttribute('data-field'))); }
        var remove = event.target.closest('[data-field-delete]');
        if (remove) { event.preventDefault(); self.deleteField(remove); }
        if (event.target.closest('[data-field-option-add]')) { self.addOption(''); }
        var optionRemove = event.target.closest('[data-field-option-remove]');
        if (optionRemove) { optionRemove.closest('[data-field-option]').remove(); self.syncOptions(); }
        var formEdit = event.target.closest('[data-auth-form-edit]');
        if (formEdit) { event.preventDefault(); self.openAuthTemplate(formEdit.getAttribute('data-auth-form-edit')); }
        if (event.target.closest('[data-auth-form-reset]')) { self.resetAuthTemplate(); }
        var templateTag = event.target.closest('[data-auth-template-tag]');
        if (templateTag) { self.insertAuthTemplateTag(templateTag.getAttribute('data-auth-template-tag')); }
        if (event.target.closest('[data-auth-template-fullscreen]')) { self.toggleAuthTemplateFullscreen(); }
        var checkoutDefault = event.target.closest('[data-checkout-template-default]');
        if (checkoutDefault) { self.setCheckoutTemplate(checkoutDefault.getAttribute('data-template') || ''); }
      });

      var form = document.querySelector('[data-customer-field]');
      if (form) {
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          self.syncOptions();
          var id = form.dataset.id || '0';
          self.request(form.dataset.base + '/system/customers/fields/' + id, new FormData(form)).then(function () { window.location.reload(); });
        });
      }
      var customerForm = document.querySelector('[data-customer-editor]');
      if (customerForm) {
        customerForm.addEventListener('submit', function (event) {
          event.preventDefault();
          var id = customerForm.dataset.id || '0';
          self.request(customerForm.dataset.base + '/system/customers/users/' + id, new FormData(customerForm)).then(function (response) {
            if (response.data && response.data.user) { self.updateCustomerRow(response.data.user); }
            if (Adminx.Drawer) { Adminx.Drawer.close('customerEditorDrawer'); }
          });
        });
      }
      var authForm = document.querySelector('[data-customer-auth]');
      if (authForm) {
        authForm.addEventListener('submit', function (event) {
          event.preventDefault();
          var checkoutTemplate = authForm.elements.checkout_access_template;
          if (checkoutTemplate && checkoutTemplate._adminxCodeMirror) { checkoutTemplate._adminxCodeMirror.save(); }
          self.request(authForm.dataset.base + '/system/customers/auth', new FormData(authForm)).then(function () { window.location.reload(); });
        });
      }
      var pagesForm = document.querySelector('[data-customer-pages]');
      if (pagesForm) {
        pagesForm.addEventListener('submit', function (event) {
          event.preventDefault();
          self.request(pagesForm.dataset.base + '/system/customers/pages', new FormData(pagesForm)).then(function () { window.location.reload(); });
        });
      }
      var authTemplateForm = document.querySelector('[data-auth-form-template]');
      if (authTemplateForm) {
        authTemplateForm.addEventListener('submit', function (event) {
          event.preventDefault();
          self.saveAuthTemplate();
        });
      }
      this.syncAuthFieldRows();
      this.syncAuthMode();
      this.initSortable();
    },

    request: function (url, body) {
      Adminx.Loader.show();
      return Adminx.Ajax.request(url, { method: 'POST', body: body }).then(function (payload) {
        Adminx.Loader.hide();
        if (!payload.ok || !payload.data.success) { throw new Error(payload.data.message || 'Не удалось выполнить действие'); }
        Adminx.Toast.show(payload.data.message || 'Сохранено', 'success');
        return payload.data;
      }).catch(function (error) {
        Adminx.Loader.hide();
        Adminx.Toast.show(error.message, 'error');
        throw error;
      });
    },

    openCustomer: function (button) {
      var form = document.querySelector('[data-customer-editor]');
      var self = this;
      if (!form) { return; }
      Adminx.Loader.show();
      Adminx.Ajax.request(button.getAttribute('data-url'), { method: 'GET' }).then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!payload.ok || !response.success || !response.data || !response.data.user) { throw new Error(response.message || 'Не удалось загрузить профиль'); }
        self.fillCustomerForm(response.data);
        if (Adminx.Drawer) { Adminx.Drawer.open('customerEditorDrawer'); }
      }).catch(function (error) {
        Adminx.Loader.hide();
        Adminx.Toast.show(error.message || 'Не удалось загрузить профиль', 'error');
      });
    },

    fillCustomerForm: function (data) {
      var form = document.querySelector('[data-customer-editor]');
      var user = data.user || {};
      var extra = data.extra || {};
      if (!form) { return; }
      form.reset();
      form.dataset.id = user.id || '0';
      form.dataset.current = data.is_current ? '1' : '0';
      ['firstname', 'lastname', 'email', 'user_name', 'phone', 'company', 'birthday', 'description', 'city', 'street', 'street_nr', 'zipcode', 'user_group'].forEach(function (name) {
        if (form.elements[name]) { form.elements[name].value = user[name] == null ? '' : user[name]; }
      });
      if (!form.elements.user_name.value) { form.elements.user_name.value = user.email || user.phone || ''; }
      form.elements.password.value = '';
      form.elements.status.checked = String(user.status) === '1';
      form.elements.email_verified.checked = Number(user.email_verified_at) > 0;
      form.elements.phone_verified.checked = Number(user.phone_verified_at) > 0;
      form.elements.admin_access.checked = !!(data.system && Number(data.system.is_active) === 1);
      form.elements.admin_role.value = data.system && data.system.role ? data.system.role : 'manager';
      this.syncCurrentAccountProtection();
      this.syncAdminAccess();
      Object.keys(extra).forEach(function (id) {
        var input = form.elements['extra[' + id + ']'];
        if (!input) { return; }
        if (input.type === 'checkbox') { input.checked = String(extra[id]) === '1'; }
        else { input.value = extra[id] == null ? '' : extra[id]; }
      });
      document.querySelector('[data-customer-editor-title]').textContent = (user.firstname || user.lastname) ? [user.firstname, user.lastname].filter(Boolean).join(' ') : (user.user_name || 'Пользователь сайта');
      document.querySelector('[data-customer-meta-id]').textContent = '#' + user.id;
      document.querySelector('[data-customer-meta-created]').textContent = this.formatTimestamp(user.reg_time);
      document.querySelector('[data-customer-meta-visit]').textContent = this.formatTimestamp(user.last_visit);
    },

    updateCustomerRow: function (user) {
      var row = document.querySelector('[data-customer-row][data-id="' + Number(user.id) + '"]');
      var fullName = [user.firstname, user.lastname].filter(Boolean).join(' ') || user.user_name;
      var name;
      var contacts;
      if (!row) { return; }
      name = row.querySelector('[data-customer-name]');
      contacts = row.querySelector('[data-customer-contacts]');
      if (name) { name.innerHTML = '<b>' + this.escape(fullName) + '</b><small class="mono">' + this.escape(user.user_name) + '</small>'; }
      if (contacts) {
        contacts.innerHTML = '<span>' + this.escape(user.email || user.phone) + '</span>'
          + (user.email && user.phone ? '<small>' + this.escape(user.phone) + '</small>' : '');
      }
      if (row.querySelector('[data-customer-company]')) { row.querySelector('[data-customer-company]').textContent = user.company || '—'; }
      if (row.querySelector('[data-customer-toggle]')) { row.querySelector('[data-customer-toggle]').checked = String(user.status) === '1'; }
    },

    syncAdminAccess: function () {
      var form = document.querySelector('[data-customer-editor]');
      var field = form ? form.querySelector('[data-customer-admin-role]') : null;
      var toggle = form && form.elements.admin_access ? form.elements.admin_access : null;
      var isCurrent = form && form.dataset.current === '1';
      var canManage = form && form.dataset.canManageAdminAccess === '1';
      if (!field || !toggle) { return; }
      field.hidden = !toggle.checked;
      if (form.elements.admin_role) { form.elements.admin_role.disabled = !toggle.checked || !canManage || isCurrent; }
    },

    syncCurrentAccountProtection: function () {
      var form = document.querySelector('[data-customer-editor]');
      var isCurrent = form && form.dataset.current === '1';
      var canManage = form && form.dataset.canManageAdminAccess === '1';
      var accountHint = form ? form.querySelector('[data-customer-account-hint]') : null;
      var adminHint = form ? form.querySelector('[data-customer-admin-hint]') : null;
      var groupHint = form ? form.querySelector('[data-customer-group-hint]') : null;
      var roleHint = form ? form.querySelector('[data-customer-admin-role-hint]') : null;
      var accountCard = form ? form.querySelector('[data-customer-account-card]') : null;
      var adminCard = form ? form.querySelector('[data-customer-admin-card]') : null;
      if (!form) { return; }
      form.elements.user_group.disabled = isCurrent;
      form.elements.status.disabled = isCurrent;
      form.elements.admin_access.disabled = isCurrent || !canManage;
      if (accountCard) { accountCard.classList.toggle('is-locked', isCurrent); }
      if (adminCard) { adminCard.classList.toggle('is-locked', isCurrent || !canManage); }
      if (accountHint) {
        accountHint.textContent = isCurrent
          ? 'Собственную учётную запись нельзя отключить.'
          : 'Может входить на сайт и в личный кабинет.';
      }
      if (adminHint) {
        adminHint.textContent = isCurrent
          ? 'Собственный доступ к панели нельзя отключить.'
          : (canManage
            ? 'Та же учётная запись сможет работать в административном интерфейсе.'
            : 'Изменение требует права управления системными пользователями.');
      }
      if (groupHint) {
        groupHint.textContent = isCurrent
          ? 'Собственную публичную группу изменяйте через другого администратора.'
          : 'Определяет права пользователя на публичной части сайта.';
      }
      if (roleHint) {
        roleHint.textContent = isCurrent
          ? 'Собственную роль изменяйте через другого администратора.'
          : 'Права роли настраиваются в разделе «Роли и права».';
      }
    },

    formatTimestamp: function (value) {
      var timestamp = Number(value) || 0;
      if (timestamp < 946684800) { return '—'; }
      return new Date(timestamp * 1000).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },

    escape: function (value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
      });
    },

    deleteField: function (button) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить поле?',
        message: 'Значения этого поля у пользователей сайта также будут удалены.',
        confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () { self.request(button.getAttribute('data-url'), new FormData()).then(function () { window.location.reload(); }); }
      });
    },

    deleteCustomer: function (button) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить пользователя сайта?',
        message: 'Аккаунт будет отключён, а его публичные сессии завершены. История заказов и связанные записи сохранятся.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          self.request(button.getAttribute('data-url'), new FormData()).then(function () {
            window.location.reload();
          });
        }
      });
    },

    toggleField: function (input) {
      var self = this;
      var item = input.closest('[data-field-id]');
      this.request(input.getAttribute('data-url'), new FormData()).then(function (response) {
        var active = !!Number(response.data && response.data.is_active);
        input.checked = active;
        item.classList.toggle('is-disabled', !active);
        var preview = document.querySelector('[data-preview-field="' + item.getAttribute('data-field-id') + '"]');
        var field = JSON.parse(item.getAttribute('data-field'));
        if (preview) { preview.hidden = !active || !Number(field.show_profile); }
      }).catch(function () { input.checked = !input.checked; });
    },

    fieldForm: function (data) {
      var form = document.querySelector('[data-customer-field]');
      if (!form) { return; }
      form.reset();
      form.dataset.id = data ? data.id : '0';
      document.querySelector('[data-field-title]').textContent = data ? 'Редактирование поля' : 'Новое поле';
      ['name', 'code', 'type', 'position'].forEach(function (key) {
        if (data && form.elements[key]) { form.elements[key].value = data[key] || ''; }
      });
      form.elements.is_required.checked = !!(data && Number(data.is_required));
      form.elements.is_active.checked = !data || !!Number(data.is_active);
      form.elements.show_registration.checked = !!(data && Number(data.show_registration));
      form.elements.show_profile.checked = !data || !!Number(data.show_profile);
      this.renderOptions(data ? data.options : '');
      this.updateOptionsVisibility();
    },

    renderOptions: function (value) {
      var list = document.querySelector('[data-field-options]');
      if (!list) { return; }
      list.innerHTML = '';
      var values = String(value || '').split(/\r?\n/).filter(function (item) { return item.trim() !== ''; });
      if (values.length === 0) { values.push(''); }
      var self = this;
      values.forEach(function (item) { self.addOption(item); });
      this.syncOptions();
    },

    addOption: function (value) {
      var list = document.querySelector('[data-field-options]');
      if (!list) { return; }
      var row = document.createElement('div');
      row.className = 'customers-option-row';
      row.setAttribute('data-field-option', '');
      row.innerHTML = '<i class="ti ti-grip-vertical text-muted"></i><input class="input" type="text" value=""><button class="btn btn-ghost btn-icon ax-act ax-act-danger" type="button" data-field-option-remove data-tooltip="Удалить вариант" aria-label="Удалить вариант"><i class="ti ti-x"></i></button>';
      row.querySelector('input').value = value || '';
      list.appendChild(row);
    },

    syncOptions: function () {
      var hidden = document.querySelector('[data-customer-field] [name="options"]');
      if (!hidden) { return; }
      var values = [];
      document.querySelectorAll('[data-field-options] input').forEach(function (input) {
        if (input.value.trim() !== '') { values.push(input.value.trim()); }
      });
      hidden.value = values.join('\n');
    },

    updateOptionsVisibility: function () {
      var type = document.querySelector('[data-customer-field] [name="type"]');
      var section = document.querySelector('[data-field-options-section]');
      if (type && section) { section.hidden = type.value !== 'select'; }
    },

    syncAuthFieldRows: function () {
      document.querySelectorAll('[data-auth-field-row]').forEach(function (row) {
        var show = row.querySelector('[data-auth-show]');
        var required = row.querySelector('[data-auth-required]');
        if (!show || !required) { return; }
        if (!show.checked) { required.checked = false; }
        required.disabled = !show.checked || show.disabled;
        row.classList.toggle('is-disabled', !show.checked);
      });
    },

    syncAuthMode: function () {
      var mode = document.querySelector('[data-registration-mode]');
      var emailOnly = document.querySelector('[data-auth-email-only]');
      if (!mode || !emailOnly) { return; }
      emailOnly.hidden = mode.value !== 'email';
    },

    setCheckoutTemplate: function (value) {
      var form = document.querySelector('[data-customer-auth]');
      var textarea = form ? form.elements.checkout_access_template : null;
      if (!textarea) { return; }
      textarea.value = value;
      if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.setValue(value); textarea._adminxCodeMirror.focus(); }
    },

    authTemplateForm: function () {
      return document.querySelector('[data-auth-form-template]');
    },

    authTemplateEditor: function () {
      var form = this.authTemplateForm();
      var textarea = form ? form.elements.template : null;
      return textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror : null;
    },

    setAuthTemplate: function (value) {
      var form = this.authTemplateForm();
      if (!form) { return; }
      form.elements.template.value = value || '';
      var editor = this.authTemplateEditor();
      if (editor) { editor.setValue(value || ''); editor.clearHistory(); }
    },

    setAuthTemplateState: function (customized) {
      var state = document.querySelector('[data-auth-form-state]');
      if (!state) { return; }
      state.textContent = customized ? 'Изменён' : 'Штатный шаблон';
      state.className = 'badge ' + (customized ? 'badge-info' : 'badge-neutral');
    },

    openAuthTemplate: function (key) {
      var form = this.authTemplateForm();
      if (!form || !key) { return; }
      var self = this;
      this.authTemplateKey = key;
      form.elements.key.value = key;
      var error = form.querySelector('[data-auth-form-error]');
      if (error) { error.textContent = ''; }
      Adminx.Loader.show();
      Adminx.Ajax.request(form.dataset.base + '/system/customers/forms/' + encodeURIComponent(key)).then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!response.success || !response.data) { throw new Error(response.message || 'Не удалось загрузить форму'); }
        document.querySelector('[data-auth-form-title]').textContent = response.data.label || 'Шаблон формы';
        document.querySelector('[data-auth-form-description]').textContent = response.data.description || 'Публичная Twig-разметка';
        self.setAuthTemplate(response.data.template || '');
        self.setAuthTemplateState(!!Number(response.data.customized));
        Adminx.Drawer.open('customerAuthFormDrawer');
        window.setTimeout(function () {
          var editor = self.authTemplateEditor();
          if (editor) {
            editor.getWrapperElement().style.height = '';
            editor.refresh();
            editor.focus();
          }
        }, 80);
      }).catch(function (error) {
        Adminx.Loader.hide();
        Adminx.Toast.show(error.message || 'Не удалось загрузить форму', 'error');
      });
    },

    saveAuthTemplate: function () {
      var form = this.authTemplateForm();
      if (!form || !this.authTemplateKey) { return; }
      var editor = this.authTemplateEditor();
      if (editor) { editor.save(); }
      var self = this;
      var error = form.querySelector('[data-auth-form-error]');
      if (error) { error.textContent = ''; }
      this.request(form.dataset.base + '/system/customers/forms/' + encodeURIComponent(this.authTemplateKey), new FormData(form)).then(function () {
        self.setAuthTemplateState(true);
      }).catch(function (failure) {
        if (error) { error.textContent = failure.message || 'Проверьте Twig-синтаксис'; }
      });
    },

    resetAuthTemplate: function () {
      var form = this.authTemplateForm();
      if (!form || !this.authTemplateKey) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Восстановить штатную форму?',
        message: 'Текущая разметка будет заменена версией из системы.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          self.request(form.dataset.base + '/system/customers/forms/' + encodeURIComponent(self.authTemplateKey) + '/reset', new FormData()).then(function (response) {
            self.setAuthTemplate(response.data && response.data.template ? response.data.template : '');
            self.setAuthTemplateState(false);
          });
        }
      });
    },

    insertAuthTemplateTag: function (value) {
      if (!value) { return; }
      var editor = this.authTemplateEditor();
      if (editor) { editor.replaceSelection(value, 'around'); editor.focus(); editor.save(); return; }
      var form = this.authTemplateForm();
      var textarea = form ? form.elements.template : null;
      if (!textarea) { return; }
      var start = textarea.selectionStart || 0;
      textarea.value = textarea.value.slice(0, start) + value + textarea.value.slice(textarea.selectionEnd || start);
      textarea.selectionStart = textarea.selectionEnd = start + value.length;
      textarea.focus();
    },

    toggleAuthTemplateFullscreen: function () {
      var editor = this.authTemplateEditor();
      if (editor && Adminx.CodeEditor) { Adminx.CodeEditor.toggleFullscreen(editor); }
    },

    initSortable: function () {
      var list = document.querySelector('[data-fields-sortable]');
      if (!list) { return; }
      var self = this;
      list.addEventListener('dragstart', function (event) {
        var item = event.target.closest('[data-field-id]');
        if (!item || !event.target.closest('.customers-field-handle')) { event.preventDefault(); return; }
        self.draggedField = item;
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
      });
      list.addEventListener('dragover', function (event) {
        var target = event.target.closest('[data-field-id]');
        if (!self.draggedField || !target || target === self.draggedField) { return; }
        event.preventDefault();
        var rect = target.getBoundingClientRect();
        list.insertBefore(self.draggedField, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
      });
      list.addEventListener('dragend', function () {
        if (!self.draggedField) { return; }
        self.draggedField.classList.remove('is-dragging');
        self.draggedField = null;
        var order = [];
        list.querySelectorAll('[data-field-id]').forEach(function (item) { order.push(Number(item.getAttribute('data-field-id'))); });
        var data = new FormData();
        data.append('order', JSON.stringify(order));
        self.request(list.getAttribute('data-reorder-url'), data).catch(function () { window.location.reload(); });
      });
    }
  };

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { Adminx.Customers.init(); }); }
  else { Adminx.Customers.init(); }
})(window, document);
