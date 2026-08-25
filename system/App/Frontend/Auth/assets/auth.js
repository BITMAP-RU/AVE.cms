(function (window, document) {
  'use strict';

  if (window.SystemPublicAuth) { return; }
  window.SystemPublicAuth = true;

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-auth-login], [data-auth-logout]');
    if (!form) { return; }
    event.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    var submit = form.querySelector('[type="submit"]');
    form.setAttribute('aria-busy', 'true');
    if (submit) { submit.disabled = true; }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        return { ok: response.ok, body: body };
      });
    }).then(function (result) {
      if (result.ok && result.body.redirect) {
        window.location.assign(result.body.redirect);
        return;
      }
      throw new Error(result.body.message || 'Не удалось выполнить операцию');
    }).catch(function (error) {
      form.removeAttribute('aria-busy');
      if (submit) { submit.disabled = false; }
      var message = form.querySelector('[data-auth-error]');
      if (!message) {
        message = document.createElement('div');
        message.className = form.classList.contains('system-account-form')
          ? 'system-account-alert system-auth-error'
          : 'alert alert-danger system-auth-error';
        message.setAttribute('data-auth-error', '');
        message.setAttribute('role', 'alert');
        message.setAttribute('tabindex', '-1');
        form.prepend(message);
      }
      message.textContent = error.message || 'Не удалось выполнить операцию';
      message.focus();
    });
  });

  function phoneMessage(form, text, error) {
    var message = form.querySelector('[data-phone-auth-message]');
    if (!message) { return; }
    message.hidden = !text;
    message.textContent = text || '';
    message.classList.toggle('is-error', !!error);
  }

  function phoneDigits(value) {
    var digits = String(value || '').replace(/\D/g, '');
    if (digits.charAt(0) === '8') { digits = '7' + digits.slice(1); }
    if (digits.charAt(0) !== '7') { digits = '7' + digits; }
    return digits.slice(0, 11);
  }

  function formatPhone(value) {
    var digits = phoneDigits(value);
    var local = digits.slice(1);
    var result = '+7';
    if (local.length > 0) { result += ' ' + local.slice(0, 3); }
    if (local.length > 3) { result += ' ' + local.slice(3, 6); }
    if (local.length > 6) { result += '-' + local.slice(6, 8); }
    if (local.length > 8) { result += '-' + local.slice(8, 10); }
    return result;
  }

  function applyPhoneMask(input) {
    if (!input) { return; }
    input.value = formatPhone(input.value);
  }

  document.addEventListener('focusin', function (event) {
    var input = event.target.closest('[data-phone-mask]');
    if (!input) { return; }
    applyPhoneMask(input);
  });

  document.addEventListener('input', function (event) {
    var input = event.target.closest('[data-phone-mask]');
    if (!input) { return; }
    applyPhoneMask(input);
  });

  function phoneCountdown(root, seconds) {
    var button = root.querySelector('[data-phone-auth-resend]');
    var remaining = Math.max(0, Number(seconds) || 0);
    if (!button) { return; }
    if (root.phoneAuthTimer) { window.clearInterval(root.phoneAuthTimer); }

    function render() {
      button.disabled = remaining > 0;
      button.textContent = remaining > 0
        ? 'Отправить ещё раз через ' + remaining + ' сек.'
        : 'Отправить код ещё раз';
      if (remaining <= 0 && root.phoneAuthTimer) {
        window.clearInterval(root.phoneAuthTimer);
        root.phoneAuthTimer = null;
      }
      remaining -= 1;
    }

    render();
    if (remaining >= 0) { root.phoneAuthTimer = window.setInterval(render, 1000); }
  }

  function phoneSubmit(form, done, messageForm, complete) {
    var submit = form.querySelector('[type="submit"]');
    var output = messageForm || form;
    form.setAttribute('aria-busy', 'true');
    if (submit) { submit.disabled = true; }
    phoneMessage(output, '', false);
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        return { ok: response.ok, body: body };
      });
    }).then(function (result) {
      if (!result.ok || !result.body.success) {
        throw new Error(result.body.message || 'Не удалось выполнить операцию');
      }
      done(result.body);
    }).catch(function (error) {
      phoneMessage(output, error.message || 'Не удалось выполнить операцию', true);
    }).then(function () {
      form.removeAttribute('aria-busy');
      if (submit) { submit.disabled = false; }
      if (complete) { complete(); }
    });
  }

  document.addEventListener('submit', function (event) {
    var requestForm = event.target.closest('[data-phone-auth-request]');
    if (requestForm) {
      event.preventDefault();
      if (!requestForm.checkValidity()) { requestForm.reportValidity(); return; }
      phoneSubmit(requestForm, function (body) {
        var root = requestForm.closest('[data-phone-auth]');
        var verifyForm = root.querySelector('[data-phone-auth-verify]');
        requestForm.hidden = true;
        verifyForm.hidden = false;
        verifyForm.elements.challenge.value = body.data.challenge;
        phoneMessage(verifyForm, body.message || 'Код отправлен.', false);
        phoneCountdown(root, body.data.resend_after);
        verifyForm.elements.code.focus();
      });
      return;
    }

    var verifyForm = event.target.closest('[data-phone-auth-verify]');
    if (!verifyForm) { return; }
    event.preventDefault();
    if (!verifyForm.checkValidity()) { verifyForm.reportValidity(); return; }
    phoneSubmit(verifyForm, function (body) {
      window.location.assign(body.redirect || '/');
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-phone-auth-back]');
    if (!button) { return; }
    var root = button.closest('[data-phone-auth]');
    var requestForm = root.querySelector('[data-phone-auth-request]');
    var verifyForm = root.querySelector('[data-phone-auth-verify]');
    if (root.phoneAuthTimer) {
      window.clearInterval(root.phoneAuthTimer);
      root.phoneAuthTimer = null;
    }
    verifyForm.hidden = true;
    requestForm.hidden = false;
    verifyForm.reset();
    phoneMessage(requestForm, '', false);
    requestForm.elements.phone.focus();
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-phone-auth-resend]');
    if (!button || button.disabled) { return; }
    var root = button.closest('[data-phone-auth]');
    var requestForm = root.querySelector('[data-phone-auth-request]');
    var verifyForm = root.querySelector('[data-phone-auth-verify]');
    var restarted = false;
    button.disabled = true;
    phoneSubmit(requestForm, function (body) {
      restarted = true;
      verifyForm.elements.challenge.value = body.data.challenge;
      verifyForm.elements.code.value = '';
      phoneMessage(verifyForm, body.message || 'Новый код отправлен.', false);
      phoneCountdown(root, body.data.resend_after);
      verifyForm.elements.code.focus();
    }, verifyForm, function () {
      if (!restarted) { button.disabled = false; }
    });
  });
})(window, document);
