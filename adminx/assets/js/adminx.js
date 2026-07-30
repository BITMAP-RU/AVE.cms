/**
 * Общий слой JS новой админки (/adminx).
 *
 * Только переиспользуемые примитивы уровня всего приложения (ТЗ §4):
 * Loader, Ajax (fetch под контракт success/error), Sidebar, Theme, Dropdown,
 * Toast. Логика конкретных разделов живёт в modules/<Module>/assets/<module>.js
 * и цепляется к этому же объекту (Adminx.<Module> = {...}).
 *
 * Без jQuery: fetch, querySelector, addEventListener, classList.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || {};
  Adminx.i18n = window.AdminxI18n || {};
  Adminx.t = function (key, fallback) {
    return Object.prototype.hasOwnProperty.call(Adminx.i18n, key) ? Adminx.i18n[key] : (fallback || key);
  };
  Adminx.phrases = window.AdminxPhrases || {};
  Adminx.phraseCache = {};
  Adminx.tr = function (text) {
    var translated = String(text == null ? '' : text);
    if (Object.prototype.hasOwnProperty.call(Adminx.phraseCache, translated)) {
      return Adminx.phraseCache[translated];
    }
    if (Object.prototype.hasOwnProperty.call(Adminx.phrases, translated)) {
      Adminx.phraseCache[translated] = Adminx.phrases[translated];
      return Adminx.phrases[translated];
    }
    var sourceText = translated;
    Object.keys(Adminx.phrases).forEach(function (source) {
      var startsWithWord = /^[\p{L}\p{N}_]/u.test(source);
      var endsWithWord = /[\p{L}\p{N}_]$/u.test(source);
      if (startsWithWord || endsWithWord) {
        var escaped = source.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        translated = translated.replace(
          new RegExp(
            (startsWithWord ? '(^|[^\\p{L}\\p{N}_])' : '()') +
            '(' + escaped + ')' +
            (endsWithWord ? '(?=$|[^\\p{L}\\p{N}_])' : ''),
            'gu'
          ),
          function (match, prefix) { return prefix + Adminx.phrases[source]; }
        );
      } else {
        translated = translated.split(source).join(Adminx.phrases[source]);
      }
    });
    Adminx.phraseCache[sourceText] = translated;
    return translated;
  };

  Adminx.base = function () {
    var m = document.querySelector('meta[name="adminx-base"]');
    return m ? m.getAttribute('content') : '';
  };

  Adminx.csrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };

  // ------------------------------------------------------------------ //
  Adminx.Loader = {
    show: function () { document.body.classList.add('is-loading'); },
    hide: function () { document.body.classList.remove('is-loading'); }
  };

  // ------------------------------------------------------------------ //
  //  Повторное подтверждение пароля для операций с исполняемым кодом.
  // ------------------------------------------------------------------ //
  Adminx.ReAuth = {
    pending: null,

    confirm: function (config) {
      if (this.pending) { return this.pending; }
      var self = this;
      this.pending = this.open(config || {}).then(function (confirmed) {
        self.pending = null;
        return confirmed;
      }, function () {
        self.pending = null;
        return false;
      });
      return this.pending;
    },

    open: function (config) {
      return new Promise(function (resolve) {
        var wasLoading = document.body.classList.contains('is-loading');
        Adminx.Loader.hide();
        var overlay = document.createElement('div');
        overlay.className = 'overlay open reauth-overlay';
        overlay.innerHTML = '<form class="modal reauth-modal" data-reauth-form>' +
          '<div class="modal-header"><span class="dialog-icon warning"><i class="ti ti-shield-lock"></i></span>' +
          '<div><h3>' + Adminx.t('reauth_title', 'Подтвердите пароль') + '</h3><p class="reauth-subtitle">' + Adminx.t('reauth_subtitle', 'Защита чувствительной операции') + '</p></div>' +
          '<button class="modal-close" type="button" data-reauth-cancel aria-label="' + Adminx.t('btn_close', 'Закрыть') + '"><i class="ti ti-x"></i></button></div>' +
          '<div class="modal-body"><p class="reauth-reason" data-reauth-reason></p>' +
          '<label class="field"><span class="field-label">' + Adminx.t('field_current_password', 'Пароль текущего пользователя') + '</span>' +
          '<div class="input-wrap"><i class="ti ti-lock"></i><input class="input" type="password" name="password" autocomplete="current-password" required></div>' +
          '<span class="field-error" data-reauth-error hidden></span></label>' +
          '<p class="reauth-hint">' + Adminx.t('reauth_hint', 'После подтверждения действие продолжится автоматически. Повторный запрос пароля появится через несколько минут.') + '</p></div>' +
          '<div class="modal-footer"><button class="btn btn-secondary" type="button" data-reauth-cancel>' + Adminx.t('btn_cancel', 'Отмена') + '</button>' +
          '<button class="btn btn-primary" type="submit"><i class="ti ti-shield-check"></i> ' + Adminx.t('btn_confirm', 'Подтвердить') + '</button></div></form>';

        var form = overlay.querySelector('[data-reauth-form]');
        var input = form.elements.password;
        var submit = form.querySelector('[type="submit"]');
        var error = form.querySelector('[data-reauth-error]');
        overlay.querySelector('[data-reauth-reason]').textContent = config.reason || Adminx.t('reauth_reason', 'Это действие изменяет исполняемый код системы.');

        var finish = function (confirmed) {
          document.removeEventListener('keydown', onKeydown);
          overlay.remove();
          if (wasLoading && confirmed) { Adminx.Loader.show(); }
          resolve(confirmed);
        };
        var onKeydown = function (event) {
          if (event.key === 'Escape' && !submit.disabled) { finish(false); }
        };

        overlay.querySelectorAll('[data-reauth-cancel]').forEach(function (button) {
          button.addEventListener('click', function () { if (!submit.disabled) { finish(false); } });
        });
        overlay.addEventListener('click', function (event) {
          if (event.target === overlay && !submit.disabled) { finish(false); }
        });
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          error.hidden = true;
          submit.disabled = true;
          var data = new FormData();
          data.append('password', input.value);
          fetch(config.reauth_url || (Adminx.base() + '/reauth'), {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': Adminx.csrf(),
              'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: data
          }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
              return { ok: response.ok, body: body };
            });
          }).then(function (result) {
            if (result.ok && result.body.success) {
              finish(true);
              return;
            }
            error.textContent = result.body.message || Adminx.t('reauth_error', 'Не удалось подтвердить пароль');
            error.hidden = false;
            input.select();
            submit.disabled = false;
          }).catch(function () {
            error.textContent = Adminx.t('server_unavailable', 'Сервер недоступен. Повторите попытку.');
            error.hidden = false;
            submit.disabled = false;
          });
        });

        document.body.appendChild(overlay);
        document.addEventListener('keydown', onKeydown);
        window.setTimeout(function () { input.focus(); }, 20);
      });
    }
  };

  // ------------------------------------------------------------------ //
  //  Ajax: единый JSON-контракт success/error из App\Common\Controller
  // ------------------------------------------------------------------ //
  Adminx.Ajax = {
    request: function (url, options) {
      options = options || {};
      var headers = options.headers || {};
      headers['X-Requested-With'] = 'XMLHttpRequest';
      headers['Accept'] = 'application/json';
      if ((options.method || 'GET').toUpperCase() !== 'GET') {
        headers['X-CSRF-Token'] = Adminx.csrf();
      }
      return fetch(url, {
        method: options.method || 'GET',
        headers: headers,
        credentials: 'same-origin',
        body: options.body || null
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { ok: res.ok, status: res.status, data: data };
        });
      }).then(function (payload) {
        var responseData = payload.data && payload.data.data ? payload.data.data : {};
        if (payload.status !== 428 || !responseData.reauth_required || options._reauthRetried) {
          return payload;
        }

        return Adminx.ReAuth.confirm(responseData).then(function (confirmed) {
          if (!confirmed) { return payload; }
          var retryOptions = {};
          Object.keys(options).forEach(function (key) { retryOptions[key] = options[key]; });
          retryOptions._reauthRetried = true;
          return Adminx.Ajax.request(url, retryOptions);
        });
      });
    },

    post: function (url, formData) {
      return this.request(url, { method: 'POST', body: formData });
    },

    /** Обработать стандартный ответ: тост + редирект. */
    handle: function (payload) {
      var d = payload.data || {};
      if (d.message) {
        Adminx.Toast.show(Adminx.tr(d.message), d.success ? 'success' : 'error');
      }
      if (d.redirect) {
        window.location.href = d.redirect;
      }
      return d;
    }
  };

  // ------------------------------------------------------------------ //
  Adminx.Sidebar = {
    KEY: 'adminx-sidebar',
    GROUPS_KEY: 'adminx-sidebar-groups',
    init: function () {
      var self = this;
      var toggle = document.getElementById('sidebarToggle');
      var backdrop = document.getElementById('sidebarBackdrop');
      if (toggle) {
        toggle.addEventListener('click', function () {
          if (window.matchMedia('(max-width: 900px)').matches) {
            document.body.classList.toggle('sidebar-open');
          } else {
            var collapsed = document.body.classList.toggle('sidebar-collapsed');
            //-- Запоминаем состояние, чтобы оно пережило переход по разделам.
            try { localStorage.setItem(self.KEY, collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
          }
        });
      }
      if (backdrop) {
        backdrop.addEventListener('click', function () {
          document.body.classList.remove('sidebar-open');
        });
      }
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-nav-sub]');
        if (!trigger) { return; }
        var submenu = document.getElementById(trigger.getAttribute('data-nav-sub'));
        if (!submenu) { return; }
        var open = trigger.getAttribute('aria-expanded') !== 'true';
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        submenu.classList.toggle('open', open);
      });

      this.initGroups();
      this.initFlyout();
    },

    //-- Группы меню: пользователь оставляет открытыми только нужные разделы,
    //-- а группа текущей страницы раскрывается независимо от сохранённого состояния.
    initGroups: function () {
      var sidebar = document.getElementById('sidebar');
      if (!sidebar) { return; }
      var groups = sidebar.querySelectorAll('[data-nav-group]');
      var saved = {};
      try {
        var parsed = JSON.parse(localStorage.getItem(this.GROUPS_KEY) || '{}');
        saved = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
      } catch (e) {}

      var hasActive = !!sidebar.querySelector('.nav-item.active');
      groups.forEach(function (group, index) {
        var trigger = group.querySelector(':scope > [data-nav-group-toggle]');
        if (!trigger) { return; }
        var key = group.getAttribute('data-nav-group-key') || '';
        var active = !!group.querySelector('.nav-item.active');
        var current = active || (!hasActive && index === 0);
        var open = current || (Object.prototype.hasOwnProperty.call(saved, key) ? saved[key] === true : false);
        group.classList.toggle('is-collapsed', !open);
        group.classList.toggle('has-active', active);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      sidebar.classList.add('nav-groups-ready');
      window.requestAnimationFrame(function () {
        sidebar.classList.add('nav-groups-animated');
      });

      sidebar.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-nav-group-toggle]');
        if (!trigger || !sidebar.contains(trigger)) { return; }
        var group = trigger.closest('[data-nav-group]');
        var key = group ? group.getAttribute('data-nav-group-key') || '' : '';
        if (!group || key === '') { return; }
        var open = group.classList.contains('is-collapsed');
        group.classList.toggle('is-collapsed', !open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        saved[key] = open;
        try { localStorage.setItem(Adminx.Sidebar.GROUPS_KEY, JSON.stringify(saved)); } catch (e) {}
      });
    },

    //-- Свёрнутый сайдбар: подменю показывается всплывающим окном при наведении,
    //-- чтобы вложенные пункты (Модули, Настройки…) оставались доступны.
    initFlyout: function () {
      var sidebar = document.getElementById('sidebar');
      if (!sidebar) { return; }
      var openSub = null, timer = null;
      var collapsed = function () {
        return document.body.classList.contains('sidebar-collapsed')
          && !window.matchMedia('(max-width: 900px)').matches;
      };
      var close = function () {
        if (!openSub) { return; }
        openSub.classList.remove('flyout-open');
        openSub.style.top = ''; openSub.style.left = '';
        openSub = null;
      };
      var cancel = function () { if (timer) { window.clearTimeout(timer); timer = null; } };
      var schedule = function () { cancel(); timer = window.setTimeout(close, 200); };
      var open = function (trigger, sub) {
        cancel();
        if (openSub && openSub !== sub) { close(); }
        var r = trigger.getBoundingClientRect();
        sub.style.left = (r.right + 6) + 'px';
        sub.style.top = r.top + 'px';
        sub.classList.add('flyout-open');
        openSub = sub;
        var sr = sub.getBoundingClientRect();
        if (sr.bottom > window.innerHeight - 10) {
          sub.style.top = Math.max(10, window.innerHeight - 10 - sr.height) + 'px';
        }
      };

      sidebar.querySelectorAll('[data-nav-sub]').forEach(function (trigger) {
        var sub = document.getElementById(trigger.getAttribute('data-nav-sub'));
        if (!sub) { return; }
        trigger.addEventListener('mouseenter', function () { if (collapsed()) { open(trigger, sub); } });
        trigger.addEventListener('mouseleave', function () { if (collapsed()) { schedule(); } });
        sub.addEventListener('mouseenter', function () { if (collapsed()) { cancel(); } });
        sub.addEventListener('mouseleave', function () { if (collapsed()) { schedule(); } });
        sub.addEventListener('click', function (e) { if (e.target.closest('a')) { close(); } });
      });

      window.addEventListener('resize', close);
      window.addEventListener('scroll', close, true);
    }
  };

  // ------------------------------------------------------------------ //
  Adminx.Theme = {
    KEY: 'adminx-theme',
    init: function () {
      var saved = null;
      try { saved = localStorage.getItem(this.KEY); } catch (e) {}
      if (saved) { document.documentElement.setAttribute('data-theme', saved); }
      var btn = document.getElementById('themeToggle');
      var self = this;
      if (btn) {
        this.sync(btn);
        btn.addEventListener('click', function () {
          self.toggle();
          self.sync(btn);
        });
      }
    },
    sync: function (btn) {
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      var icon = btn.querySelector('[data-theme-icon]');
      var label = btn.querySelector('[data-theme-label]');
      if (icon) {
        icon.className = dark ? 'ti ti-sun' : 'ti ti-moon';
      }
      if (label) {
        label.textContent = dark
          ? btn.getAttribute('data-theme-light-label')
          : btn.getAttribute('data-theme-dark-label');
      }
    },
    toggle: function () {
      var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', cur);
      try { localStorage.setItem(this.KEY, cur); } catch (e) {}
    }
  };

  // ------------------------------------------------------------------ //
  Adminx.Dropdown = {
    init: function () {
      document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-dropdown]');
        var openMenus = document.querySelectorAll('.dropdown.open');
        if (trigger) {
          var dd = trigger.closest('.dropdown');
          var wasOpen = dd.classList.contains('open');
          openMenus.forEach(function (m) { m.classList.remove('open'); });
          if (!wasOpen) { dd.classList.add('open'); }
          e.stopPropagation();
          return;
        }
        openMenus.forEach(function (m) {
          if (!m.contains(e.target)) { m.classList.remove('open'); }
        });
      });
    }
  };

  // ------------------------------------------------------------------ //
  //  Меню пользователя в подвале сайдбара. Сайдбар имеет overflow:hidden,
  //  поэтому всплывающее меню позиционируем fixed вручную (над триггером),
  //  чтобы оно не обрезалось и работало в свёрнутом состоянии.
  // ------------------------------------------------------------------ //
  Adminx.SidebarUser = {
    init: function () {
      var dd = document.getElementById('sidebarUserMenu');
      if (!dd) { return; }
      var trigger = dd.querySelector('[data-dropdown]');
      var menu = dd.querySelector('.dropdown-menu');
      if (!trigger || !menu) { return; }
      var place = function () {
        if (!dd.classList.contains('open')) { return; }
        var r = trigger.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.left = Math.round(r.left) + 'px';
        menu.style.right = 'auto';
        menu.style.top = 'auto';
        menu.style.bottom = Math.round(window.innerHeight - r.top + 6) + 'px';
        menu.style.minWidth = Math.max(220, Math.round(r.width)) + 'px';
      };
      // Dropdown.init переключает .open на том же клике; позиционируем после.
      trigger.addEventListener('click', function () { setTimeout(place, 0); });
      window.addEventListener('resize', place, { passive: true });
    }
  };

  // Generic tabs for new shared surfaces. Existing module tabs can migrate to
  // data-tab-target/data-tab-panel without adding another event listener.
  Adminx.Tabs = {
    activate: function (root, name) {
      if (!root) { return; }
      root.querySelectorAll('[data-tab-target]').forEach(function (tab) {
        var active = tab.getAttribute('data-tab-target') === name;
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.classList.toggle('active', active);
      });
      root.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-tab-panel') !== name;
      });
      if (Adminx.CodeEditor) {
        setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 0);
      }
    },
    init: function () {
      var self = this;
      document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-tab-target]');
        if (!tab) { return; }
        var root = tab.closest('[data-tabs]');
        if (root) { self.activate(root, tab.getAttribute('data-tab-target')); }
      });
    }
  };

  // ------------------------------------------------------------------ //
  //  Drawer: боковая панель (открытие по [data-open-drawer="id"], закрытие
  //  по [data-close-drawer], клику по оверлею и Esc).
  // ------------------------------------------------------------------ //
  Adminx.Drawer = {
    overlay: function () { return document.getElementById('drawerOverlay'); },

    _seq: 0,

    // Пересчёт стека открытых панелей: самая поздняя — сверху и активна,
    // остальные получают .drawer-under (затеняются) под ней.
    restack: function () {
      var open = [].slice.call(document.querySelectorAll('.drawer.show'));
      open.sort(function (a, b) {
        return (Number(a.dataset.drawerSeq) || 0) - (Number(b.dataset.drawerSeq) || 0);
      });
      open.forEach(function (d, i) {
        var isTop = i === open.length - 1;
        d.classList.toggle('drawer-under', !isTop);
        d.style.zIndex = String(195 + i * 2);
      });
    },

    // AdminKit (fix-pack) открывает drawer/overlay классом .show (не .open).
    open: function (id) {
      var el = document.getElementById(id);
      if (!el) { return; }
      el.dataset.drawerSeq = String(++this._seq);
      el.removeAttribute('hidden');
      el.classList.add('show');
      var ov = this.overlay();
      if (ov) { ov.classList.add('show'); }
      document.body.classList.add('drawer-active');
      document.documentElement.classList.add('drawer-active');
      this.restack();
    },

    close: function (id) {
      var drawer = id ? document.getElementById(id) : null;
      if (!drawer && typeof id === 'object' && id) { drawer = id; }
      if (!drawer) {
        var openDrawers = document.querySelectorAll('.drawer.show');
        drawer = openDrawers.length ? openDrawers[openDrawers.length - 1] : null;
      }
      if (drawer) {
        drawer.classList.remove('show');
        drawer.classList.remove('drawer-under');
        drawer.style.zIndex = '';
        window.setTimeout(function () {
          if (!drawer.classList.contains('show')) {
            drawer.setAttribute('hidden', 'hidden');
            document.dispatchEvent(new CustomEvent('adminx:drawer:closed', { detail: { drawer: drawer } }));
          }
        }, 230);
      }
      var ov = this.overlay();
      if (!document.querySelector('.drawer.show')) {
        if (ov) { ov.classList.remove('show'); }
        document.body.classList.remove('drawer-active');
        document.documentElement.classList.remove('drawer-active');
      }
      this.restack();
    },

    init: function () {
      var self = this;
      document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open-drawer]');
        if (opener) { self.open(opener.getAttribute('data-open-drawer')); return; }
        var closer = e.target.closest('[data-close-drawer]');
        if (closer) { self.close(closer.closest('.drawer')); return; }
        if (e.target.id === 'drawerOverlay') { self.close(); }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { self.close(); }
      });
    }
  };

  // ------------------------------------------------------------------ //
  Adminx.Toast = {
    region: function () {
      var r = document.getElementById('toastRegion');
      if (!r) {
        r = document.createElement('div');
        r.id = 'toastRegion';
        r.className = 'toast-region';
        document.body.appendChild(r);
      }
      return r;
    },
    show: function (message, type) {
      var el = document.createElement('div');
      el.className = 'toast toast-' + (type || 'info');
      el.textContent = Adminx.tr(message);
      this.region().appendChild(el);
      setTimeout(function () { el.classList.add('show'); }, 10);
      setTimeout(function () {
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 250);
      }, 3200);
    }
  };

  // ------------------------------------------------------------------ //
  //  Confirm: модальный диалог подтверждения (компонент AdminKit .modal),
  //  вместо браузерного window.confirm. Никаких нативных промтов.
  //
  //  Adminx.Confirm.open({ kind, title, message, confirmLabel, confirmClass,
  //                        cancelLabel, onConfirm });
  // ------------------------------------------------------------------ //
  Adminx.Confirm = {
    ICONS: {
      info:    ['info',    'ti-info-circle'],
      success: ['success', 'ti-circle-check'],
      warning: ['warning', 'ti-alert-triangle'],
      error:   ['error',   'ti-alert-circle']
    },

    open: function (cfg) {
      cfg = cfg || {};
      var kind = this.ICONS[cfg.kind] ? cfg.kind : 'warning';
      var ic = this.ICONS[kind];

      var overlay = document.createElement('div');
      overlay.className = 'overlay';

      var modal = document.createElement('div');
      modal.className = 'modal confirm-modal';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');

      modal.innerHTML =
        '<div class="modal-header">' +
          '<span class="dialog-icon ' + ic[0] + '"><i class="ti ' + ic[1] + '"></i></span>' +
          '<div style="flex:1">' +
            '<h3>' + esc(Adminx.tr(cfg.title || Adminx.t('confirm_title', 'Подтвердите действие'))) + '</h3>' +
          '</div>' +
          '<button class="modal-close" type="button" data-cancel aria-label="' + esc(Adminx.t('btn_close', 'Закрыть')) + '"><i class="ti ti-x"></i></button>' +
        '</div>' +
        (cfg.message ? '<div class="modal-body confirm-modal-body"><p class="text-secondary">' + esc(Adminx.tr(cfg.message)) + '</p></div>' : '') +
        '<div class="modal-footer">' +
          '<button class="btn btn-ghost" type="button" data-cancel>' + esc(Adminx.tr(cfg.cancelLabel || Adminx.t('btn_cancel', 'Отмена'))) + '</button>' +
          '<button class="btn ' + (cfg.confirmClass || 'btn-primary') + '" type="button" data-ok style="margin-left:auto">' + esc(Adminx.tr(cfg.confirmLabel || Adminx.t('confirm_action', 'Подтвердить'))) + '</button>' +
        '</div>';

      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      // показываем (AdminKit fix-pack: overlay открывается классом .show)
      requestAnimationFrame(function () { overlay.classList.add('show'); });

      var self = this;
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var onKey = function (e) { if (e.key === 'Escape') { close(); } };

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-cancel]')) { close(); return; }
        if (e.target.closest('[data-ok]')) {
          close();
          if (typeof cfg.onConfirm === 'function') { cfg.onConfirm(); }
        }
      });
      document.addEventListener('keydown', onKey);
      modal.querySelector('[data-ok]').focus();
    }
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  // ------------------------------------------------------------------ //
  //  Logout: подтверждение + POST через ajax-контракт (ТЗ §8.5)
  // ------------------------------------------------------------------ //
  Adminx.initLogout = function () {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-confirm-logout]');
      if (!btn) { return; }
      e.preventDefault();
      var form = document.getElementById('logoutForm');
      if (!form) { return; }

      Adminx.Confirm.open({
        kind: 'info',
        title: Adminx.t('logout_title', 'Выйти из админпанели?'),
        message: Adminx.t('logout_message', 'Текущая сессия будет завершена.'),
        confirmLabel: Adminx.t('btn_logout', 'Выйти'),
        confirmClass: 'btn-primary',
        onConfirm: function () {
          Adminx.Loader.show();
          var fd = new FormData(form);
          Adminx.Ajax.post(form.getAttribute('action'), fd).then(function (payload) {
            Adminx.Loader.hide();
            var d = Adminx.Ajax.handle(payload);
            if (!d.redirect) { window.location.href = Adminx.base() + '/login'; }
          }).catch(function () {
            Adminx.Loader.hide();
            form.submit();
          });
        }
      });
    });
  };

  // ------------------------------------------------------------------ //
  //  Тема CodeMirror по умолчанию — dracula. Проставляем data-theme всем
  //  код-редакторам ДО их инициализации (adminx.js грузится раньше
  //  editor-codemirror.js), явный data-theme не трогаем. CSS темы подключает
  //  константа CODEMIRROR_THEME. Работает и для динамики (adminx:content-ready).
  // ------------------------------------------------------------------ //
  Adminx.CODE_THEME = 'dracula';
  Adminx.applyCodeTheme = function (root) {
    (root || document).querySelectorAll('textarea[data-code-editor]:not([data-theme])').forEach(function (t) {
      t.setAttribute('data-theme', Adminx.CODE_THEME);
    });
  };

  // ------------------------------------------------------------------ //
  //  Копирование тега в буфер (эталон — раздел «Системные блоки»). Любой
  //  элемент с data-ax-copy="[tag:...]" копирует значение и показывает тост.
  // ------------------------------------------------------------------ //
  Adminx.copyTag = function (text) {
    text = String(text || '');
    if (!text) { return; }
    var ok = function () { Adminx.Toast.show(Adminx.t('tag_copied', 'Тег скопирован'), 'success'); };
    var fallback = function () {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      ta.style.top = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand('copy')) { ok(); } else { Adminx.Toast.show(text, 'info'); }
      } catch (e) { Adminx.Toast.show(text, 'info'); }
      ta.remove();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(ok).catch(fallback);
      return;
    }
    fallback();
  };

  Adminx.initCopyTag = function () {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-ax-copy]');
      if (!el) { return; }
      e.preventDefault();
      Adminx.copyTag(el.getAttribute('data-ax-copy'));
    });
  };

  Adminx.initSectionHelp = function () {
    var block = document.querySelector('[data-section-help]');
    if (!block) { return; }
    var header = document.querySelector('.page-header');
    var details = block.querySelector('details');
    //-- Инфо-аккордеон всегда свёрнут по умолчанию; открывается только по клику.
    if (details) { details.open = false; }
    if (header && header.parentNode) { header.insertAdjacentElement('afterend', block); }
    block.removeAttribute('hidden');
  };

  // ------------------------------------------------------------------ //
  //  Tooltip: плавающая подсказка на уровне <body>. CSS-тултип (::after)
  //  обрезался любыми блоками с overflow (таблицы, канбан-колонки и т.п.);
  //  портал во <body> с position:fixed выходит за пределы любых блоков.
  // ------------------------------------------------------------------ //
  Adminx.Tooltip = {
    el: null,
    target: null,
    init: function () {
      var self = this;
      document.addEventListener('pointerover', function (e) {
        var t = e.target.closest ? e.target.closest('[data-tooltip]') : null;
        if (t) { self.show(t); }
      });
      document.addEventListener('pointerout', function (e) {
        var t = e.target.closest ? e.target.closest('[data-tooltip]') : null;
        if (t && t === self.target) { self.hide(); }
      });
      document.addEventListener('focusin', function (e) {
        var t = e.target.closest ? e.target.closest('[data-tooltip]') : null;
        if (t) { self.show(t); } else { self.hide(); }
      });
      document.addEventListener('click', function () { self.hide(); });
      window.addEventListener('scroll', function () { self.hide(); }, true);
      window.addEventListener('resize', function () { self.hide(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { self.hide(); } });
    },
    ensure: function () {
      if (this.el) { return this.el; }
      var el = document.createElement('div');
      el.className = 'ax-tooltip';
      el.setAttribute('role', 'tooltip');
      document.body.appendChild(el);
      this.el = el;
      return el;
    },
    show: function (target) {
      var text = target.getAttribute('data-tooltip');
      if (!text) { return; }
      this.target = target;
      var el = this.ensure();
      el.textContent = text;
      this.position(target, el);
      el.classList.add('show');
    },
    position: function (target, el) {
      var r = target.getBoundingClientRect();
      el.style.maxWidth = Math.min(280, window.innerWidth - 16) + 'px';
      var tw = el.offsetWidth;
      var th = el.offsetHeight;
      var gap = 7;
      var placement = target.getAttribute('data-tooltip-position') || 'top';
      if (placement === 'right') {
        var rightTop = r.top + r.height / 2 - th / 2;
        var rightLeft = r.right + gap;
        if (rightLeft + tw > window.innerWidth - 8) { rightLeft = r.left - tw - gap; }
        el.style.top = Math.round(Math.max(8, Math.min(rightTop, window.innerHeight - th - 8))) + 'px';
        el.style.left = Math.round(Math.max(8, rightLeft)) + 'px';
        return;
      }
      var top = r.top - th - gap;
      if (top < 8) { top = r.bottom + gap; }
      var left = r.left + r.width / 2 - tw / 2;
      left = Math.max(8, Math.min(left, window.innerWidth - tw - 8));
      el.style.top = Math.round(top) + 'px';
      el.style.left = Math.round(left) + 'px';
    },
    hide: function () {
      this.target = null;
      if (this.el) { this.el.classList.remove('show'); }
    }
  };

  // ------------------------------------------------------------------ //
  //  Help: контекстная справка раздела в правой AJAX-панели.
  //  Триггер — любой элемент с data-ax-help="<slug>" (топбар, кнопка в
  //  разделе, подсказка у компонента). Контент грузится из /help/panel.
  // ------------------------------------------------------------------ //
  Adminx.HelpPanel = {
    cache: {},

    fill: function (drawer, d) {
      var title = drawer.querySelector('#helpDrawerTitle');
      var body = drawer.querySelector('[data-help-body]');
      var full = drawer.querySelector('[data-help-full]');
      if (title) { title.textContent = d.title || Adminx.t('help_title', 'Справка'); }
      if (body) { body.innerHTML = d.html || ''; Adminx.applyCodeTheme(body); body.scrollTop = 0; }
      if (full && d.url) { full.setAttribute('href', d.url); }
    },

    open: function (slug) {
      if (!slug) { return; }
      var drawer = document.getElementById('helpDrawer');
      if (!drawer) { return; }
      Adminx.Drawer.open('helpDrawer');
      if (this.cache[slug]) { this.fill(drawer, this.cache[slug]); return; }

      var body = drawer.querySelector('[data-help-body]');
      if (body) { body.innerHTML = '<div class="help-drawer-state"><i class="ti ti-loader-2"></i>' + Adminx.t('help_loading', 'Загрузка…') + '</div>'; }

      Adminx.Ajax.request(Adminx.base() + '/help/panel?doc=' + encodeURIComponent(slug)).then(function (p) {
        var d = p.data || {};
        if (!p.ok || !d.success) {
          if (body) { body.innerHTML = '<div class="help-drawer-state"><i class="ti ti-file-off"></i>' + Adminx.t('help_not_found', 'Справка для этого раздела не найдена.') + '</div>'; }
          return;
        }
        Adminx.HelpPanel.cache[slug] = d;
        Adminx.HelpPanel.fill(drawer, d);
      }).catch(function () {
        if (body) { body.innerHTML = '<div class="help-drawer-state"><i class="ti ti-alert-triangle"></i>' + Adminx.t('help_load_error', 'Не удалось загрузить справку.') + '</div>'; }
      });
    },

    init: function () {
      document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-ax-help]') : null;
        if (!el) { return; }
        e.preventDefault();
        e.stopPropagation();
        Adminx.HelpPanel.open(el.getAttribute('data-ax-help'));
      });
    }
  };

  // ------------------------------------------------------------------ //
  Adminx.init = function () {
    Adminx.applyCodeTheme(document);
    Adminx.Sidebar.init();
    Adminx.Theme.init();
    Adminx.Dropdown.init();
    Adminx.SidebarUser.init();
    Adminx.Tabs.init();
    Adminx.Drawer.init();
    Adminx.Tooltip.init();
    Adminx.initLogout();
    Adminx.initCopyTag();
    Adminx.initSectionHelp();
    Adminx.HelpPanel.init();
  };

  document.addEventListener('adminx:content-ready', function (e) {
    Adminx.applyCodeTheme(e.detail && e.detail.root ? e.detail.root : document);
  });

  window.Adminx = Adminx;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Adminx.init);
  } else {
    Adminx.init();
  }
})(window, document);
