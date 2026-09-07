(function (window, document) {
  'use strict';

  if (window.AvePhoneMask) {
    window.AvePhoneMask.refresh(document);
    return;
  }

  var selector = '[data-phone-mask]';

  function rawDigits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function phoneDigits(value) {
    var digits = rawDigits(value);
    if (!digits) { return ''; }
    if (digits.charAt(0) === '8') {
      digits = '7' + digits.slice(1);
    } else if (digits.charAt(0) !== '7') {
      digits = '7' + digits;
    }
    return digits.slice(0, 11);
  }

  function format(value) {
    var digits = phoneDigits(value);
    if (!digits) { return ''; }
    var local = digits.slice(1);
    var result = '+7';
    if (local.length > 0) { result += ' (' + local.slice(0, 3); }
    if (local.length >= 3) { result += ') ' + local.slice(3, 6); }
    if (local.length >= 6) { result += '-' + local.slice(6, 8); }
    if (local.length >= 8) { result += '-' + local.slice(8, 10); }
    return result;
  }

  function caretAfterDigits(value, count) {
    if (count <= 0) { return 0; }
    var seen = 0;
    for (var index = 0; index < value.length; index += 1) {
      if (/\d/.test(value.charAt(index))) { seen += 1; }
      if (seen >= count) { return index + 1; }
    }
    return value.length;
  }

  function validate(input) {
    var digits = phoneDigits(input.value);
    input.setCustomValidity(!digits || digits.length === 11 ? '' : 'Введите номер телефона полностью');
  }

  function prepare(input) {
    if (!input || !input.matches || !input.matches(selector)) { return; }
    input.type = 'tel';
    input.inputMode = 'tel';
    if (!input.autocomplete || input.autocomplete === 'off') { input.autocomplete = 'tel'; }
    if (!input.placeholder) { input.placeholder = '+7 (000) 000-00-00'; }
    if (!input.maxLength || input.maxLength < 0 || input.maxLength > 18) { input.maxLength = 18; }
    validate(input);
  }

  function apply(input) {
    if (!input || !input.matches || !input.matches(selector)) { return; }
    var source = input.value;
    var start = typeof input.selectionStart === 'number' ? input.selectionStart : source.length;
    var digitsBefore = rawDigits(source.slice(0, start)).length;
    var sourceDigits = rawDigits(source);
    if (sourceDigits && sourceDigits.charAt(0) !== '7' && sourceDigits.charAt(0) !== '8') {
      digitsBefore += 1;
    }
    input.value = format(source);
    prepare(input);
    if (document.activeElement === input && input.setSelectionRange) {
      var caret = caretAfterDigits(input.value, digitsBefore);
      input.setSelectionRange(caret, caret);
    }
  }

  function refresh(root) {
    root = root || document;
    if (root.matches && root.matches(selector)) {
      prepare(root);
      if (root.value) { apply(root); }
    }
    if (!root.querySelectorAll) { return; }
    Array.prototype.forEach.call(root.querySelectorAll(selector), function (input) {
      prepare(input);
      if (input.value) { apply(input); }
    });
  }

  document.addEventListener('focusin', function (event) {
    var input = event.target.closest ? event.target.closest(selector) : null;
    if (input) { prepare(input); }
  });

  document.addEventListener('input', function (event) {
    var input = event.target.closest ? event.target.closest(selector) : null;
    if (input) { apply(input); }
  });

  document.addEventListener('paste', function (event) {
    var input = event.target.closest ? event.target.closest(selector) : null;
    if (!input) { return; }
    window.setTimeout(function () { apply(input); }, 0);
  });

  document.addEventListener('keydown', function (event) {
    var input = event.target.closest ? event.target.closest(selector) : null;
    if (!input || event.key !== 'Backspace' || input.selectionStart !== input.selectionEnd) { return; }
    var caret = input.selectionStart;
    if (caret <= 0 || /\d/.test(input.value.charAt(caret - 1))) { return; }
    var digitIndex = caret - 1;
    while (digitIndex >= 0 && !/\d/.test(input.value.charAt(digitIndex))) { digitIndex -= 1; }
    if (digitIndex <= 1) { return; }
    event.preventDefault();
    input.value = input.value.slice(0, digitIndex) + input.value.slice(digitIndex + 1);
    input.setSelectionRange(digitIndex, digitIndex);
    apply(input);
  });

  document.addEventListener('focusout', function (event) {
    var input = event.target.closest ? event.target.closest(selector) : null;
    if (!input) { return; }
    if (phoneDigits(input.value) === '7') { input.value = ''; }
    validate(input);
  });

  document.addEventListener('submit', function (event) {
    if (!event.target.querySelectorAll) { return; }
    Array.prototype.forEach.call(event.target.querySelectorAll(selector), function (input) {
      apply(input);
    });
  }, true);

  window.AvePhoneMask = {
    apply: apply,
    format: format,
    refresh: refresh
  };
  refresh(document);
})(window, document);
