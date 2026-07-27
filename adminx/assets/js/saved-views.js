(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function parse(value, fallback) {
    try { return JSON.parse(value); } catch (error) { return fallback; }
  }

  function formFor(root) {
    var selector = root.getAttribute('data-filter-form');
    return selector ? document.querySelector(selector) : null;
  }

  function fields(root) {
    return parse(root.getAttribute('data-fields') || '[]', []);
  }

  function currentFilters(root) {
    var form = formFor(root), result = {};
    if (!form) { return result; }
    fields(root).forEach(function (name) {
      var field = form.elements[name];
      result[name] = field ? String(field.value || '') : '';
    });
    return result;
  }

  function same(left, right, names) {
    return names.every(function (name) {
      return String(left[name] || '') === String(right[name] || '');
    });
  }

  function sync(root) {
    var select = root.querySelector('[data-saved-views-select]');
    var remove = root.querySelector('[data-saved-views-delete]');
    if (!select || !remove) { return; }
    var current = currentFilters(root), names = fields(root), matched = '';
    Array.prototype.some.call(select.options, function (option) {
      if (!option.value) { return false; }
      if (same(current, parse(option.getAttribute('data-filters') || '{}', {}), names)) {
        matched = option.value;
        return true;
      }
      return false;
    });
    select.value = matched;
    remove.disabled = !matched;
  }

  function render(root, views, selected) {
    var select = root.querySelector('[data-saved-views-select]');
    if (!select) { return; }
    select.innerHTML = '<option value="">Сохранённые представления</option>';
    (views || []).forEach(function (view) {
      var option = document.createElement('option');
      option.value = view.id;
      option.textContent = view.title;
      option.setAttribute('data-filters', JSON.stringify(view.filters || {}));
      select.appendChild(option);
    });
    select.value = selected || '';
    sync(root);
  }

  function apply(root, option) {
    var form = formFor(root);
    if (!form || !option || !option.value) { return; }
    var values = parse(option.getAttribute('data-filters') || '{}', {});
    fields(root).forEach(function (name) {
      if (form.elements[name]) { form.elements[name].value = values[name] || ''; }
    });
    if (form.elements.page) { form.elements.page.value = '1'; }
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
  }

  function request(root, url, data, successMessage) {
    Adminx.Loader.show();
    return Adminx.Ajax.post(url, data).then(function (payload) {
      var response = payload.data || {};
      if (!payload.ok || response.success === false) {
        throw new Error(response.message || 'Не удалось сохранить представление');
      }
      Adminx.Toast.show(response.message || successMessage, 'success');
      return response.data || {};
    }).catch(function (error) {
      Adminx.Toast.show(error.message || 'Не удалось сохранить представление', 'error');
      throw error;
    }).then(function (result) {
      Adminx.Loader.hide();
      return result;
    }, function (error) {
      Adminx.Loader.hide();
      throw error;
    });
  }

  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-saved-views-select]');
    if (!select) { return; }
    var root = select.closest('[data-saved-views]');
    root.querySelector('[data-saved-views-delete]').disabled = !select.value;
    apply(root, select.options[select.selectedIndex]);
  });

  document.addEventListener('click', function (event) {
    var create = event.target.closest('[data-saved-views-create]');
    if (create) {
      var createRoot = create.closest('[data-saved-views]');
      var editor = createRoot.querySelector('[data-saved-views-editor]');
      editor.hidden = false;
      var title = editor.querySelector('[data-saved-views-title]');
      title.value = '';
      title.focus();
      return;
    }

    var cancel = event.target.closest('[data-saved-views-cancel]');
    if (cancel) {
      cancel.closest('[data-saved-views-editor]').hidden = true;
      return;
    }

    var submit = event.target.closest('[data-saved-views-submit]');
    if (submit) {
      var submitRoot = submit.closest('[data-saved-views]');
      var input = submitRoot.querySelector('[data-saved-views-title]');
      var name = input.value.trim();
      if (!name) {
        input.focus();
        Adminx.Toast.show('Укажите название представления', 'warning');
        return;
      }
      submit.disabled = true;
      var data = new FormData();
      data.set('_csrf', submitRoot.getAttribute('data-csrf') || '');
      data.set('title', name);
      data.set('filters', JSON.stringify(currentFilters(submitRoot)));
      request(submitRoot, submitRoot.getAttribute('data-save-url'), data, 'Представление сохранено').then(function (result) {
        render(submitRoot, result.views || [], result.id || '');
        submitRoot.querySelector('[data-saved-views-editor]').hidden = true;
      }).then(function () { submit.disabled = false; }, function () { submit.disabled = false; });
      return;
    }

    var remove = event.target.closest('[data-saved-views-delete]');
    if (remove && !remove.disabled) {
      var removeRoot = remove.closest('[data-saved-views]');
      var select = removeRoot.querySelector('[data-saved-views-select]');
      var option = select.options[select.selectedIndex];
      if (!option || !option.value) { return; }
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить представление?',
        message: 'Набор фильтров «' + option.textContent + '» будет удалён только у вашей учётной записи.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          var data = new FormData();
          data.set('_csrf', removeRoot.getAttribute('data-csrf') || '');
          var url = removeRoot.getAttribute('data-delete-url').replace('__ID__', encodeURIComponent(option.value));
          return request(removeRoot, url, data, 'Представление удалено').then(function (result) {
            render(removeRoot, result.views || [], '');
          });
        }
      });
    }
  });

  document.addEventListener('keydown', function (event) {
    if (!event.target.matches('[data-saved-views-title]')) { return; }
    if (event.key === 'Enter') {
      event.preventDefault();
      event.target.closest('[data-saved-views]').querySelector('[data-saved-views-submit]').click();
    } else if (event.key === 'Escape') {
      event.target.closest('[data-saved-views-editor]').hidden = true;
    }
  });

  function syncAll(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('[data-saved-views]'), sync);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { syncAll(document); });
  } else {
    syncAll(document);
  }
  document.addEventListener('adminx:content-ready', function (event) {
    syncAll(event.detail && event.detail.root ? event.detail.root : document);
  });
})(window, document);
