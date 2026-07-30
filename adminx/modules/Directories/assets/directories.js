(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Directories = {
    page: null,
    directoryForm: null,
    itemForm: null,

    init: function () {
      this.page = document.querySelector('[data-directories-page]');
      if (!this.page) { return; }

      this.directoryForm = document.querySelector('[data-directory-form]');
      this.itemForm = document.querySelector('[data-directory-item-form]');
      var self = this;

      document.addEventListener('click', function (event) {
        if (event.target.closest('[data-directory-new]')) { self.newDirectory(); return; }
        var edit = event.target.closest('[data-directory-edit]');
        if (edit) { self.editDirectory(edit); return; }
        if (event.target.closest('[data-directory-delete]')) { self.deleteDirectory(); return; }
        if (event.target.closest('[data-directory-item-new]')) { self.newItem(); return; }
        var itemEdit = event.target.closest('[data-directory-item-edit]');
        if (itemEdit) { self.editItem(itemEdit); return; }
        var itemDelete = event.target.closest('[data-directory-item-delete]');
        if (itemDelete) { self.deleteItem(itemDelete); return; }
      });

      if (this.directoryForm) {
        this.directoryForm.addEventListener('submit', function (event) {
          event.preventDefault();
          var id = self.directoryForm.elements.id.value;
          self.submit(self.base() + '/directories' + (id ? '/' + id : ''), self.directoryForm);
        });
      }

      if (this.itemForm) {
        this.itemForm.addEventListener('submit', function (event) {
          event.preventDefault();
          var directoryId = self.itemForm.getAttribute('data-directory');
          var itemId = self.itemForm.elements.id.value;
          self.submit(self.base() + '/directories/' + directoryId + '/items' + (itemId ? '/' + itemId : ''), self.itemForm);
        });
      }
    },

    base: function () {
      return this.page.getAttribute('data-base') || '';
    },

    newDirectory: function () {
      if (!this.directoryForm) { return; }
      this.directoryForm.reset();
      this.directoryForm.elements.id.value = '';
      this.directoryForm.elements.is_active.checked = true;
      this.directoryForm.setAttribute('data-usage', '0');
      this.directoryForm.querySelector('[data-directory-delete]').hidden = true;
      document.querySelector('[data-directory-form-title]').textContent = 'Новый справочник';
      Adminx.Drawer.open('directoryDrawer');
    },

    editDirectory: function (button) {
      if (!this.directoryForm) { return; }
      this.directoryForm.reset();
      this.directoryForm.elements.id.value = button.getAttribute('data-id') || '';
      this.directoryForm.elements.name.value = button.getAttribute('data-name') || '';
      this.directoryForm.elements.code.value = button.getAttribute('data-code') || '';
      this.directoryForm.elements.description.value = button.getAttribute('data-description') || '';
      this.directoryForm.elements.is_active.checked = button.getAttribute('data-active') === '1';
      this.directoryForm.setAttribute('data-usage', button.getAttribute('data-usage') || '0');
      this.directoryForm.querySelector('[data-directory-delete]').hidden = false;
      document.querySelector('[data-directory-form-title]').textContent = 'Настройки справочника';
      Adminx.Drawer.open('directoryDrawer');
    },

    deleteDirectory: function () {
      if (!this.directoryForm) { return; }
      var self = this;
      var id = this.directoryForm.elements.id.value;
      var name = this.directoryForm.elements.name.value;
      var usage = parseInt(this.directoryForm.getAttribute('data-usage'), 10) || 0;
      Adminx.Confirm.open({
        kind: usage > 0 ? 'warning' : 'danger',
        title: usage > 0 ? 'Справочник используется' : 'Удалить справочник?',
        message: usage > 0
          ? 'Он подключён к полям: ' + usage + '. Сначала выберите для них другой источник значений.'
          : '«' + name + '» и все его значения будут удалены без восстановления.',
        confirmLabel: usage > 0 ? 'Понятно' : 'Удалить',
        confirmClass: usage > 0 ? 'btn-secondary' : 'btn-danger',
        onConfirm: function () {
          if (usage > 0) { return; }
          var data = new FormData();
          data.append('_csrf', self.csrf());
          self.submit(self.base() + '/directories/' + id + '/delete', data);
        }
      });
    },

    newItem: function () {
      if (!this.itemForm) { return; }
      this.itemForm.reset();
      this.itemForm.elements.id.value = '';
      this.itemForm.elements.sort_order.value = '0';
      this.itemForm.elements.is_active.checked = true;
      document.querySelector('[data-directory-item-title]').textContent = 'Новое значение';
      Adminx.Drawer.open('directoryItemDrawer');
    },

    editItem: function (button) {
      if (!this.itemForm) { return; }
      this.itemForm.reset();
      this.itemForm.elements.id.value = button.getAttribute('data-id') || '';
      this.itemForm.elements.item_key.value = button.getAttribute('data-key') || '';
      this.itemForm.elements.label.value = button.getAttribute('data-label') || '';
      this.itemForm.elements.sort_order.value = button.getAttribute('data-order') || '0';
      this.itemForm.elements.is_active.checked = button.getAttribute('data-active') === '1';
      document.querySelector('[data-directory-item-title]').textContent = 'Изменить значение';
      Adminx.Drawer.open('directoryItemDrawer');
    },

    deleteItem: function (button) {
      var self = this;
      var itemId = button.getAttribute('data-directory-item-delete');
      var directoryId = this.itemForm ? this.itemForm.getAttribute('data-directory') : '';
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить значение?',
        message: '«' + (button.getAttribute('data-label') || '') + '» исчезнет из выбора. Уже сохранённые документы сохранят ключ.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          var data = new FormData();
          data.append('_csrf', self.csrf());
          self.submit(self.base() + '/directories/' + directoryId + '/items/' + itemId + '/delete', data);
        }
      });
    },

    submit: function (url, body) {
      var self = this;
      Adminx.Loader.show();
      var data = body instanceof FormData ? body : new FormData(body);
      fetch(url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Не удалось сохранить данные');
          }

          return payload;
        });
      }).then(function (payload) {
        Adminx.Toast.show(payload.message || 'Сохранено', 'success');
        window.location.href = payload.redirect || window.location.href;
      }).catch(function (error) {
        Adminx.Toast.show(error.message || 'Ошибка запроса', 'danger');
      }).finally(function () {
        Adminx.Loader.hide();
      });
    },

    csrf: function () {
      var input = document.querySelector('[data-directory-form] [name="_csrf"]');
      return input ? input.value : '';
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Directories.init(); });
  } else {
    Adminx.Directories.init();
  }
})(window, document);
