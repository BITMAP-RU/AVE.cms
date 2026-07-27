/**
 * Единый медиа-пикер /adminx. Один компонент для всех мест, где выбирают файл
 * из медиатеки (поля документов, значения по умолчанию рубрик, навигация,
 * визуальный редактор). Дергает общий эндпоинт GET /media/picker.
 *
 * Использование:
 *   Adminx.MediaPicker.open({
 *     type: 'image' | 'file' | 'all',   // что показывать (по умолчанию image)
 *     dir: '/uploads',                  // стартовая папка
 *     title, description,               // тексты шапки (опц.)
 *     onPick: function (file) { ... },  // выбран файл из медиатеки
 *     onFolder: function (dir) { ... }, // выбрана открытая папка (опц.)
 *     onUrl:  function (url)  { ... }    // опц.: показать «вставить по ссылке»
 *   });
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  Adminx.MediaPicker = {
    open: function (options) {
      options = options || {};
      var type = (options.type === 'file' || options.type === 'all') ? options.type : 'image';
      var onPick = typeof options.onPick === 'function' ? options.onPick : function () {};
      var onFolder = typeof options.onFolder === 'function' ? options.onFolder : null;
      var onUrl = typeof options.onUrl === 'function' ? options.onUrl : null;
      var base = (Adminx.base && Adminx.base()) || '';
      var title = options.title || 'Выбрать файл';
      var description = options.description || (type === 'image' ? 'Выберите изображение из медиатеки.' : 'Выберите файл из медиатеки.');
      var placeholder = type === 'image' ? 'Поиск изображений' : 'Поиск файлов';

      var overlay = document.createElement('div');
      overlay.className = 'overlay media-picker-overlay';
      overlay.innerHTML =
        '<div class="modal picker-modal media-picker" role="dialog" aria-modal="true">'
        + '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-photo-plus"></i></span>'
        + '<div style="flex:1"><h3>' + esc(title) + '</h3><p class="text-secondary" style="margin-top:4px">' + esc(description) + '</p></div>'
        + '<button class="modal-close" type="button" data-mp-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
        + '<div class="modal-body">'
        + '<div class="media-picker-tools">'
        + '<div class="input-wrap media-picker-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="' + placeholder + '" data-mp-search></div>'
        + '<button class="btn btn-ghost btn-icon" type="button" data-mp-up data-tooltip="На уровень выше" aria-label="На уровень выше"><i class="ti ti-arrow-up"></i></button>'
        + (onUrl ? '<button class="btn btn-ghost btn-sm" type="button" data-mp-url-toggle><i class="ti ti-link"></i>По ссылке</button>' : '')
        + '</div>'
        + (onUrl ? '<div class="media-picker-url" data-mp-url hidden><input class="input" type="url" placeholder="https://…" data-mp-url-input><button class="btn btn-primary btn-sm" type="button" data-mp-url-apply><i class="ti ti-check"></i>Вставить</button></div>' : '')
        + '<div class="media-picker-crumbs" data-mp-crumbs></div>'
        + '<div class="media-picker-status" data-mp-status>Загрузка…</div>'
        + '<div class="media-picker-grid" data-mp-grid></div>'
        + '</div>'
        + '<div class="modal-footer"><div class="mf-left media-picker-count" data-mp-count></div>'
        + '<button class="btn btn-ghost btn-sm" type="button" data-mp-prev><i class="ti ti-chevron-left"></i>Назад</button>'
        + '<button class="btn btn-ghost btn-sm" type="button" data-mp-next>Дальше<i class="ti ti-chevron-right"></i></button>'
        + (onFolder ? '<button class="btn btn-primary" type="button" data-mp-folder-apply><i class="ti ti-folder-check"></i>' + esc(options.folderLabel || 'Выбрать эту папку') + '</button>' : '')
        + '<button class="btn btn-ghost" type="button" data-mp-close>Закрыть</button></div>'
        + '</div>';
      document.body.appendChild(overlay);
      window.requestAnimationFrame(function () { overlay.classList.add('show'); });

      var state = { dir: options.dir || '/uploads', parent: '', page: 1, pages: 1, q: '', type: type };
      var grid = overlay.querySelector('[data-mp-grid]');
      var crumbs = overlay.querySelector('[data-mp-crumbs]');
      var status = overlay.querySelector('[data-mp-status]');
      var count = overlay.querySelector('[data-mp-count]');
      var search = overlay.querySelector('[data-mp-search]');
      var timer = null;

      function close() {
        overlay.classList.remove('show');
        window.setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 160);
      }
      function onKey(e) { if (e.key === 'Escape') { close(); } }

      function renderCrumbs(items) {
        crumbs.innerHTML = '';
        (items || []).forEach(function (item, index) {
          if (index > 0) { crumbs.insertAdjacentHTML('beforeend', '<span class="media-picker-sep">/</span>'); }
          crumbs.insertAdjacentHTML('beforeend', '<button class="media-picker-crumb" type="button" data-mp-dir="' + esc(item.path || '/uploads') + '">' + esc(item.title || item.path || 'uploads') + '</button>');
        });
      }

      function render(data) {
        var folders = data.folders || [];
        var files = data.files || [];
        renderCrumbs(data.breadcrumbs || []);
        grid.innerHTML = '';
        folders.forEach(function (folder) {
          grid.insertAdjacentHTML('beforeend', '<button class="media-picker-item is-folder" type="button" data-mp-dir="' + esc(folder.path || '') + '"><span class="media-picker-thumb"><span class="media-picker-folder-icon"><i class="ti ti-folder"></i></span></span><span class="media-picker-meta"><b>' + esc(folder.name || '') + '</b><small><i class="ti ti-files"></i>' + esc(folder.count || 0) + ' объектов</small></span></button>');
        });
        files.forEach(function (file) {
          var thumb = file.thumb_url || file.preview_url || file.url || '';
          var isImage = /\.(jpe?g|png|gif|webp|bmp|svg)(\?.*)?$/i.test(thumb);
          var preview = isImage ? '<img src="' + esc(thumb) + '" alt="">' : '<i class="ti ti-file"></i>';
          var meta = (file.width && file.height ? file.width + 'x' + file.height : '') + (file.size_label ? ' · ' + file.size_label : '');
          grid.insertAdjacentHTML('beforeend', '<button class="media-picker-item" type="button" data-mp-file="' + esc(JSON.stringify(file)) + '"><span class="media-picker-thumb">' + preview + (file.extension ? '<span class="media-picker-extension">' + esc(file.extension) + '</span>' : '') + '</span><span class="media-picker-meta"><b>' + esc(file.name || '') + '</b><small>' + esc(meta || file.extension || 'Файл') + '</small></span></button>');
        });
        status.hidden = folders.length + files.length > 0;
        status.textContent = state.q ? 'Ничего не найдено' : (state.type === 'image' ? 'В этой папке нет изображений' : 'В этой папке нет файлов');
        count.textContent = data.total ? 'Файлов: ' + data.total + ', страница ' + state.page + ' из ' + state.pages : '';
        overlay.querySelector('[data-mp-prev]').disabled = state.page <= 1;
        overlay.querySelector('[data-mp-next]').disabled = state.page >= state.pages;
        overlay.querySelector('[data-mp-up]').disabled = !state.parent;
      }

      function load(dir, page) {
        state.dir = dir || state.dir;
        state.page = page || 1;
        status.textContent = 'Загрузка…';
        status.hidden = false;
        grid.innerHTML = '';
        var params = new URLSearchParams();
        params.set('dir', state.dir);
        params.set('page', state.page);
        params.set('per_page', 36);
        params.set('type', state.type);
        if (state.q) { params.set('q', state.q); }
        fetch(base + '/media/picker?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (res) { return res.json(); })
          .then(function (payload) {
            var data = (payload && payload.data) || {};
            state.dir = data.dir || state.dir;
            state.parent = data.parent_dir || '';
            state.page = data.page || 1;
            state.pages = data.pages || 1;
            render(data);
          })
          .catch(function () { status.textContent = 'Не удалось загрузить медиа'; status.hidden = false; });
      }

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-mp-close]')) { close(); return; }
        var dir = e.target.closest('[data-mp-dir]');
        if (dir) { load(dir.getAttribute('data-mp-dir') || '/uploads', 1); return; }
        var file = e.target.closest('[data-mp-file]');
        if (file && !onFolder) { onPick(JSON.parse(file.getAttribute('data-mp-file') || '{}')); close(); return; }
        if (e.target.closest('[data-mp-folder-apply]') && onFolder) { onFolder(state.dir); close(); return; }
        if (e.target.closest('[data-mp-prev]') && state.page > 1) { load(state.dir, state.page - 1); }
        if (e.target.closest('[data-mp-next]') && state.page < state.pages) { load(state.dir, state.page + 1); }
        if (e.target.closest('[data-mp-up]') && state.parent) { load(state.parent, 1); }
        if (onUrl) {
          if (e.target.closest('[data-mp-url-toggle]')) {
            var box = overlay.querySelector('[data-mp-url]');
            box.hidden = !box.hidden;
            if (!box.hidden) { box.querySelector('[data-mp-url-input]').focus(); }
          }
          if (e.target.closest('[data-mp-url-apply]')) {
            var value = overlay.querySelector('[data-mp-url-input]').value.trim();
            if (value) { onUrl(value); close(); }
          }
        }
      });

      search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { state.q = search.value.trim(); load(state.dir, 1); }, 220);
      });
      document.addEventListener('keydown', onKey);
      load(state.dir, 1);
      search.focus();
    }
  };
})(window, document);
