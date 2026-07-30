(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function controls(root) {
    var target = root.getAttribute('data-bulk-target') || '';
    return {
      items: Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item="' + target + '"]')),
      all: Array.prototype.slice.call(document.querySelectorAll('[data-bulk-all="' + target + '"]'))
    };
  }

  function update(root) {
    var nodes = controls(root);
    var selected = nodes.items.filter(function (item) { return item.checked && !item.disabled; });
    var available = nodes.items.filter(function (item) { return !item.disabled; });
    var count = root.querySelector('[data-bulk-count]');
    root.hidden = selected.length === 0;
    root.classList.toggle('visible', selected.length > 0);
    if (count) { count.textContent = selected.length; }
    nodes.all.forEach(function (all) {
      all.checked = available.length > 0 && selected.length === available.length;
      all.indeterminate = selected.length > 0 && selected.length < available.length;
    });
  }

  function clear(root) {
    var nodes = controls(root);
    nodes.items.concat(nodes.all).forEach(function (item) {
      item.checked = false;
      item.indeterminate = false;
    });
    update(root);
  }

  function apply(root) {
    var nodes = controls(root);
    var ids = nodes.items.filter(function (item) {
      return item.checked && !item.disabled;
    }).map(function (item) {
      return item.value;
    });
    var select = root.querySelector('[data-bulk-action]');
    var option = select && select.options[select.selectedIndex] ? select.options[select.selectedIndex] : null;
    if (!select || !select.value || !ids.length) {
      Adminx.Toast.show(Adminx.tr('Выберите записи и действие'), 'warning');
      return;
    }

    var endpoint = root.getAttribute('data-bulk-endpoint') || '';
    var label = option.getAttribute('data-label') || option.textContent || select.value;
    var kind = option.getAttribute('data-kind') || 'warning';
    Adminx.Confirm.open({
      kind: kind,
      title: Adminx.tr('Выполнить массовое действие?'),
      message: Adminx.tr('Выбрано записей') + ': ' + ids.length + '. ' + Adminx.tr('Действие') + ': ' + label + '.',
      confirmLabel: Adminx.tr('Применить'),
      onConfirm: function () {
        var data = new FormData();
        data.append('action', select.value);
        ids.forEach(function (id) { data.append('ids[]', id); });
        Adminx.Loader.show();
        Adminx.Ajax.post(endpoint, data).then(function (payload) {
          Adminx.Loader.hide();
          var body = payload.data || {};
          if (!payload.ok || body.success === false) {
            Adminx.Toast.show(body.message || Adminx.tr('Не удалось выполнить действие'), 'error');
            return;
          }

          var result = body.data || {};
          var message = (body.message || Adminx.tr('Готово')) + ' · ' + Adminx.tr('обработано') + ' ' + (result.done || 0);
          if (result.skipped) { message += ', ' + Adminx.tr('пропущено') + ' ' + result.skipped; }
          Adminx.Toast.show(message, result.errors && result.errors.length ? 'warning' : 'success');
          document.dispatchEvent(new CustomEvent('adminx:bulk-complete', {
            detail: { root: root, result: result }
          }));
          if (root.getAttribute('data-bulk-reload') !== 'false') {
            window.location.reload();
          } else {
            clear(root);
          }
        }).catch(function () {
          Adminx.Loader.hide();
          Adminx.Toast.show(Adminx.tr('Сервер недоступен. Повторите попытку.'), 'error');
        });
      }
    });
  }

  document.addEventListener('change', function (event) {
    var item = event.target.closest('[data-bulk-item]');
    var all = event.target.closest('[data-bulk-all]');
    if (!item && !all) { return; }
    var target = (item || all).getAttribute(item ? 'data-bulk-item' : 'data-bulk-all');
    var root = document.querySelector('[data-bulk-actions][data-bulk-target="' + target + '"]');
    if (!root) { return; }
    if (all) {
      controls(root).items.forEach(function (checkbox) {
        if (!checkbox.disabled) { checkbox.checked = all.checked; }
      });
    }
    update(root);
  });

  document.addEventListener('click', function (event) {
    var applyButton = event.target.closest('[data-bulk-apply]');
    var clearButton = event.target.closest('[data-bulk-clear]');
    var root = (applyButton || clearButton) ? (applyButton || clearButton).closest('[data-bulk-actions]') : null;
    if (!root) { return; }
    if (applyButton) { apply(root); } else { clear(root); }
  });

  document.querySelectorAll('[data-bulk-actions]').forEach(update);
  Adminx.BulkActions = { update: update, clear: clear };
})(window, document);
