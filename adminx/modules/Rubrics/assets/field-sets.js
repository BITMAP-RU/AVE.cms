(function (window, document) {
  'use strict';

  var Adminx = window.Adminx = window.Adminx || {};

  function init() {
    var root = document.querySelector('[data-field-sets-root]');
    if (!root) { return; }
    var base = root.getAttribute('data-base') || Adminx.base();

    root.addEventListener('click', function (event) {
      var mode = event.target.closest('[data-field-set-mode]');
      if (mode) {
        var segmented = mode.closest('.segmented');
        segmented.querySelectorAll('[data-field-set-mode]').forEach(function (button) {
          button.setAttribute('aria-pressed', button === mode ? 'true' : 'false');
        });
        return;
      }

      var apply = event.target.closest('[data-field-set-apply]');
      if (!apply) { return; }
      var card = apply.closest('[data-field-set]');
      var rubric = card.querySelector('[data-field-set-rubric]').value;
      var selectedMode = card.querySelector('[data-field-set-mode][aria-pressed="true"]');
      if (!rubric) {
        Adminx.Toast.show('Выберите рубрику', 'warning');
        return;
      }

      apply.disabled = true;
      var data = new FormData();
      data.append('_csrf', Adminx.csrf());
      data.append('mode', selectedMode ? selectedMode.getAttribute('data-field-set-mode') : 'copy');
      fetch(base + '/rubrics/' + rubric + '/field-sets/' + encodeURIComponent(card.getAttribute('data-field-set')) + '/apply', {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (!json.ok) { throw new Error(json.message || 'Не удалось добавить набор'); }
          Adminx.Toast.show(json.message || 'Набор добавлен', 'success');
        })
        .catch(function (error) { Adminx.Toast.show(error.message, 'error'); })
        .finally(function () { apply.disabled = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})(window, document);
