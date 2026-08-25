import { Editor, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TextAlign from '@tiptap/extension-text-align';

var EnhancedImage = Image.extend({
  addAttributes: function () {
    return Object.assign({}, this.parent ? this.parent() : {}, {
      class: { default: null },
      webp: { default: null }
    });
  },

  parseHTML: function () {
    return [
      {
        tag: 'picture',
        getAttrs: function (element) {
          var image = element.querySelector('img[src]');
          var source = element.querySelector('source[type="image/webp"], source[srcset$=".webp"]');
          if (!image) { return false; }
          return {
            src: image.getAttribute('src'),
            alt: image.getAttribute('alt'),
            title: image.getAttribute('title'),
            class: image.getAttribute('class'),
            width: image.getAttribute('width'),
            height: image.getAttribute('height'),
            webp: source ? source.getAttribute('srcset') : null
          };
        }
      },
      { tag: 'img[src]:not([src^="data:"])' }
    ];
  },

  renderHTML: function (context) {
    var attributes = Object.assign({}, context.HTMLAttributes || {});
    var webp = attributes.webp;
    delete attributes.webp;
    var image = ['img', mergeAttributes(this.options.HTMLAttributes, attributes)];
    if (!webp) { return image; }
    return ['picture', {}, ['source', { srcset: webp, type: 'image/webp' }], image];
  }
});

(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function el(tag, attrs, html) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) {
      if (key === 'class') {
        node.className = attrs[key];
      } else if (key === 'dataset') {
        Object.keys(attrs[key]).forEach(function (name) { node.dataset[name] = attrs[key][name]; });
      } else {
        node.setAttribute(key, attrs[key]);
      }
    });
    if (html != null) {
      node.innerHTML = html;
    }
    return node;
  }

  function icon(name) {
    return '<i class="ti ti-' + name + '"></i>';
  }

  function promptValue(title, value, done) {
    Adminx.RichEditor.modalInput({ title: title, value: value || '' }, done);
  }

  Adminx.RichEditor = {
    instances: [],

    init: function (root) {
      root = root || document;
      var nodes = root.querySelectorAll('textarea[data-rich-editor]:not([data-rich-editor-ready])');
      Array.prototype.forEach.call(nodes, this.create.bind(this));
    },

    extensions: function () {
      return [
        StarterKit.configure({ link: false }),
        Link.configure({
          openOnClick: false,
          autolink: true,
          protocols: ['http', 'https', 'mailto', 'tel'],
          HTMLAttributes: { rel: 'noopener noreferrer nofollow' }
        }),
        EnhancedImage.configure({ allowBase64: false }),
        Table.configure({ resizable: true }),
        TableRow,
        TableHeader,
        TableCell,
        TextAlign.configure({ types: ['heading', 'paragraph'] })
      ];
    },

    create: function (textarea) {
      var preset = textarea.getAttribute('data-rich-preset') || 'full';
      var root = el('div', { class: 'rich-editor rich-editor-' + preset });
      var toolbar = el('div', { class: 'rich-editor-toolbar', role: 'toolbar' });
      var surface = el('div', { class: 'rich-editor-surface' });
      var editorEl = el('div', { class: 'rich-editor-content' });
      var configuredHeight = parseInt(textarea.getAttribute('data-editor-height'), 10) || 0;
      if (configuredHeight > 0) {
        configuredHeight = Math.max(140, Math.min(900, configuredHeight));
        root.classList.add('rich-editor-fixed-height');
        root.style.setProperty('--rich-editor-height', configuredHeight + 'px');
      }

      textarea.style.display = 'none';
      textarea.parentNode.insertBefore(root, textarea);
      root.appendChild(toolbar);
      root.appendChild(surface);
      surface.appendChild(editorEl);
      root.appendChild(textarea);

      var editor = new Editor({
        element: editorEl,
        extensions: this.extensions(),
        content: textarea.value || '',
        editorProps: {
          attributes: {
            class: 'rich-editor-prose',
            spellcheck: textarea.getAttribute('spellcheck') || 'true'
          },
          handleDoubleClickOn: function (view, pos, node, nodePos) {
            if (!node || node.type.name !== 'image') { return false; }
            editor.chain().setNodeSelection(nodePos).run();
            Adminx.RichEditor.openImageEditor(editor, node.attrs, nodePos);
            return true;
          }
        },
        onUpdate: function () {
          textarea.value = editor.getHTML();
          textarea.setAttribute('data-rich-editor-dirty', '1');
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
        },
        onSelectionUpdate: function () {
          Adminx.RichEditor.syncToolbar(root, editor);
        },
        onTransaction: function () {
          Adminx.RichEditor.syncToolbar(root, editor);
        }
      });

      this.buildToolbar(toolbar, editor);
      textarea.setAttribute('data-rich-editor-ready', '1');
      textarea._adminxTiptap = editor;
      this.instances.push(editor);
      this.bindForm(textarea, editor);
      this.syncToolbar(root, editor);
    },

    button: function (name, tooltip, iconName, run) {
      var btn = el('button', {
        class: 'btn btn-ghost btn-icon btn-sm rich-editor-btn',
        type: 'button',
        'data-rich-command': name,
        'data-tooltip': tooltip,
        'aria-label': tooltip
      }, icon(iconName));
      btn.addEventListener('click', function () { run(); });
      return btn;
    },

    buildToolbar: function (toolbar, editor) {
      var group;
      var self = this;
      function addGroup() {
        group = el('div', { class: 'rich-editor-group' });
        toolbar.appendChild(group);
      }
      function add(name, tooltip, iconName, run) {
        group.appendChild(self.button(name, tooltip, iconName, run));
      }
      function addBlockSelect() {
        var select = el('select', {
          class: 'select rich-editor-block-select',
          'data-rich-block-select': '',
          'aria-label': 'Формат блока',
          'data-tooltip': 'Формат блока'
        });
        [
          ['paragraph', 'Абзац'],
          ['heading-1', 'Заголовок H1'],
          ['heading-2', 'Заголовок H2'],
          ['heading-3', 'Заголовок H3'],
          ['heading-4', 'Заголовок H4'],
          ['heading-5', 'Заголовок H5'],
          ['heading-6', 'Заголовок H6']
        ].forEach(function (item) {
          var option = el('option', { value: item[0] });
          option.textContent = item[1];
          select.appendChild(option);
        });
        select.addEventListener('change', function () {
          if (select.value === 'paragraph') {
            editor.chain().focus().setParagraph().run();
            return;
          }
          editor.chain().focus().setHeading({ level: parseInt(select.value.replace('heading-', ''), 10) }).run();
        });
        group.appendChild(select);
      }

      addGroup();
      add('undo', 'Отменить', 'arrow-back-up', function () { editor.chain().focus().undo().run(); });
      add('redo', 'Повторить', 'arrow-forward-up', function () { editor.chain().focus().redo().run(); });

      addGroup();
      add('bold', 'Жирный', 'bold', function () { editor.chain().focus().toggleBold().run(); });
      add('italic', 'Курсив', 'italic', function () { editor.chain().focus().toggleItalic().run(); });
      add('strike', 'Зачеркнуть', 'strikethrough', function () { editor.chain().focus().toggleStrike().run(); });
      add('code', 'Блок кода', 'code', function () { editor.chain().focus().toggleCodeBlock().run(); });

      addGroup();
      addBlockSelect();
      add('bulletList', 'Маркированный список', 'list', function () { editor.chain().focus().toggleBulletList().run(); });
      add('orderedList', 'Нумерованный список', 'list-numbers', function () { editor.chain().focus().toggleOrderedList().run(); });
      add('blockquote', 'Цитата', 'blockquote', function () { editor.chain().focus().toggleBlockquote().run(); });

      addGroup();
      add('alignLeft', 'По левому краю', 'align-left', function () { editor.chain().focus().setTextAlign('left').run(); });
      add('alignCenter', 'По центру', 'align-center', function () { editor.chain().focus().setTextAlign('center').run(); });
      add('alignRight', 'По правому краю', 'align-right', function () { editor.chain().focus().setTextAlign('right').run(); });

      addGroup();
      add('link', 'Ссылка', 'link', function () { self.openLink(editor); });
      add('image', 'Изображение', 'photo-plus', function () {
        self.openMediaPicker(editor);
      });
      add('table', 'Таблица', 'table-plus', function () {
        editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
      });
      add('showBlocks', 'Показать блоки', 'box-model-2', function () {
        var root = toolbar.closest('.rich-editor');
        if (!root) { return; }
        root.classList.toggle('rich-editor-show-blocks');
        self.syncToolbar(root, editor);
      });

      addGroup();
      if (window.CodeMirror) {
        add('source', 'Исходник', 'code-dots', function () { self.openSource(editor); });
      }
      add('clear', 'Очистить формат', 'eraser', function () { editor.chain().focus().unsetAllMarks().clearNodes().run(); });
    },

    syncToolbar: function (root, editor) {
      var states = {
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        strike: editor.isActive('strike'),
        code: editor.isActive('codeBlock'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
        blockquote: editor.isActive('blockquote'),
        alignLeft: editor.isActive({ textAlign: 'left' }),
        alignCenter: editor.isActive({ textAlign: 'center' }),
        alignRight: editor.isActive({ textAlign: 'right' }),
        link: editor.isActive('link'),
        showBlocks: root.classList.contains('rich-editor-show-blocks'),
        source: root.classList.contains('rich-editor-source-open')
      };
      Object.keys(states).forEach(function (name) {
        var btn = root.querySelector('[data-rich-command="' + name + '"]');
        if (btn) { btn.setAttribute('aria-pressed', states[name] ? 'true' : 'false'); }
      });
      var blockSelect = root.querySelector('[data-rich-block-select]');
      if (blockSelect) {
        var blockValue = 'paragraph';
        for (var level = 1; level <= 6; level++) {
          if (editor.isActive('heading', { level: level })) { blockValue = 'heading-' + level; break; }
        }
        blockSelect.value = blockValue;
      }
    },

    bindForm: function (textarea, editor) {
      var form = textarea.closest('form');
      var self = this;
      if (!form) { return; }
      form.addEventListener('submit', function () {
        self.syncTextarea(textarea, editor);
      });
    },

    syncTextarea: function (textarea, editor) {
      var root;
      if (!textarea) { return ''; }
      root = textarea.closest('.rich-editor');
      if (root && root.classList.contains('rich-editor-source-open') && root._richSourceEditor) {
        root._richSourceEditor.save();
        textarea.value = root._richSourceEditor.getValue();
        textarea.setAttribute('data-rich-editor-dirty', '1');
        return textarea.value;
      }
      if (editor && textarea.getAttribute('data-rich-editor-dirty') === '1') {
        textarea.value = editor.getHTML();
      }
      return textarea.value;
    },

    openSource: function (editor) {
      var root = editor.view.dom.closest('.rich-editor');
      if (!root || !window.CodeMirror) { return; }
      var surface = root.querySelector('.rich-editor-surface');
      var storage = root.querySelector('textarea[data-rich-editor]');
      var source = root.querySelector('[data-rich-inline-source]');
      var opening = !root.classList.contains('rich-editor-source-open');

      if (!opening) {
        var html = root._richSourceEditor ? root._richSourceEditor.getValue() : (storage ? storage.value : '');
        if (html !== editor.getHTML()) { editor.commands.setContent(html || '<p></p>'); }
        if (storage) { storage.value = editor.getHTML(); }
        root.classList.remove('rich-editor-source-open');
        this.setSourceControls(root, false);
        this.syncToolbar(root, editor);
        editor.commands.focus();
        return;
      }

      if (!source) {
        source = el('div', { class: 'rich-editor-inline-source', 'data-rich-inline-source': '' });
        var sourceTextarea = el('textarea', { 'data-rich-source-textarea': '' });
        source.appendChild(sourceTextarea);
        surface.parentNode.insertBefore(source, surface.nextSibling);
        root._richSourceEditor = window.CodeMirror.fromTextArea(sourceTextarea, {
          mode: 'htmlmixed',
          theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'material-ocean' : 'default',
          lineNumbers: true,
          lineWrapping: true,
          matchBrackets: true,
          autoCloseTags: true,
          styleActiveLine: true,
          indentUnit: 2
        });
        root._richSourceEditor.on('change', function (cm) {
          if (!storage || root._richSourceSyncing) { return; }
          storage.value = cm.getValue();
          storage.setAttribute('data-rich-editor-dirty', '1');
          storage.dispatchEvent(new Event('input', { bubbles: true }));
        });
      }

      var height = Math.max(260, Math.round(surface.getBoundingClientRect().height || 0));
      root._richSourceSyncing = true;
      root._richSourceEditor.setValue(editor.getHTML());
      root._richSourceSyncing = false;
      root._richSourceEditor.setSize('100%', height);
      root.classList.add('rich-editor-source-open');
      this.setSourceControls(root, true);
      this.syncToolbar(root, editor);
      setTimeout(function () { root._richSourceEditor.refresh(); root._richSourceEditor.focus(); }, 0);
    },

    setSourceControls: function (root, sourceOpen) {
      root.querySelectorAll('.rich-editor-toolbar [data-rich-command], .rich-editor-toolbar [data-rich-block-select]').forEach(function (control) {
        if (control.getAttribute('data-rich-command') === 'source') { return; }
        control.disabled = !!sourceOpen;
      });
    },

    openLink: function (editor) {
      var self = this;
      var attributes = editor.getAttributes('link') || {};
      var overlay = el('div', { class: 'overlay rich-editor-link-overlay' });
      var modal = el('div', { class: 'modal rich-editor-link-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'richEditorLinkTitle' });
      modal.innerHTML =
        '<div class="modal-header">' +
          '<span class="dialog-icon info"><i class="ti ti-link"></i></span>' +
          '<div style="flex:1"><h3 id="richEditorLinkTitle">Ссылка</h3><p class="text-secondary" style="margin-top:4px">Укажите адрес вручную или выберите его из системы.</p></div>' +
          '<button class="modal-close" type="button" data-link-cancel aria-label="Закрыть"><i class="ti ti-x"></i></button>' +
        '</div>' +
        '<div class="modal-body rich-editor-link-body">' +
          '<label class="field"><span class="field-label">Адрес ссылки</span><input class="input mono" type="text" value="' + this.escape(attributes.href || '') + '" placeholder="/catalog/item или https://example.com" data-link-url></label>' +
          '<div class="rich-editor-link-sources">' +
            '<button class="btn btn-secondary" type="button" data-link-document><i class="ti ti-file-search"></i>Выбрать документ</button>' +
            '<button class="btn btn-secondary" type="button" data-link-media><i class="ti ti-photo-search"></i>Выбрать из медиа</button>' +
          '</div>' +
          '<div class="rich-editor-link-selection text-secondary" data-link-selection>Можно использовать внутренний или внешний адрес.</div>' +
          '<label class="field"><span class="field-label">CSS-класс</span><input class="input mono" type="text" value="' + this.escape(attributes.class || '') + '" placeholder="Например: button button-primary" data-link-class><span class="field-hint">Можно указать несколько классов через пробел.</span></label>' +
        '</div>' +
        '<div class="modal-footer"><div class="mf-left"><button class="btn btn-ghost" type="button" data-link-unset><i class="ti ti-unlink"></i>Убрать ссылку</button></div>' +
          '<button class="btn btn-ghost" type="button" data-link-cancel>Отмена</button><button class="btn btn-primary" type="button" data-link-apply><i class="ti ti-check"></i>Применить</button></div>';
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });

      var urlInput = overlay.querySelector('[data-link-url]');
      var classInput = overlay.querySelector('[data-link-class]');
      var selection = overlay.querySelector('[data-link-selection]');
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var apply = function () {
        var href = String(urlInput.value || '').trim();
        var cssClass = String(classInput.value || '').trim().replace(/\s+/g, ' ');
        if (!href) {
          editor.chain().focus().unsetLink().run();
          close();
          return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href: href, class: cssClass || null }).run();
        close();
      };
      var setPicked = function (url, label) {
        urlInput.value = url || '';
        selection.textContent = label || 'Адрес выбран';
        urlInput.dispatchEvent(new Event('input', { bubbles: true }));
      };
      var onKey = function (event) {
        if (event.key === 'Escape') {
          if (document.querySelector('.rich-editor-document-picker-overlay.show, .media-picker-overlay.show')) { return; }
          close();
        }
        if (event.key === 'Enter' && event.target !== classInput) { event.preventDefault(); apply(); }
      };
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay || event.target.closest('[data-link-cancel]')) { close(); return; }
        if (event.target.closest('[data-link-apply]')) { apply(); return; }
        if (event.target.closest('[data-link-unset]')) { editor.chain().focus().unsetLink().run(); close(); return; }
        if (event.target.closest('[data-link-document]')) {
          self.openDocumentLinkPicker(function (item) {
            var alias = String(item.alias || '').trim();
            var url = alias ? (/^(?:[a-z]+:|\/|#)/i.test(alias) ? alias : '/' + alias) : '/index.php?id=' + item.id;
            setPicked(url, 'Документ #' + item.id + ' · ' + (item.title || 'Без названия'));
          });
          return;
        }
        if (event.target.closest('[data-link-media]') && Adminx.MediaPicker) {
          Adminx.MediaPicker.open({
            type: 'all',
            title: 'Файл для ссылки',
            description: 'Выберите изображение или документ из медиатеки.',
            onPick: function (file) { if (file && file.url) { setPicked(file.url, 'Медиа · ' + (file.name || file.url)); } },
            onUrl: function (url) { if (url) { setPicked(url, 'Внешний адрес медиа'); } }
          });
        }
      });
      document.addEventListener('keydown', onKey);
      urlInput.focus();
      urlInput.select();
    },

    openDocumentLinkPicker: function (done) {
      var self = this;
      var overlay = el('div', { class: 'overlay rich-editor-document-picker-overlay' });
      var modal = el('div', { class: 'modal picker-modal rich-editor-document-picker', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'richEditorDocumentPickerTitle' });
      modal.innerHTML =
        '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div style="flex:1"><h3 id="richEditorDocumentPickerTitle">Документ для ссылки</h3><p class="text-secondary" style="margin-top:4px">Поиск по ID, названию или alias.</p></div><button class="modal-close" type="button" data-document-link-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>' +
        '<div class="modal-body"><div class="input-wrap rich-editor-document-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="ID, название или alias" data-document-link-search></div><div class="rich-editor-document-status" data-document-link-status>Загрузка...</div><div class="rich-editor-document-list" data-document-link-list></div></div>' +
        '<div class="modal-footer"><div class="mf-left text-secondary" data-document-link-count></div><button class="btn btn-ghost" type="button" data-document-link-close>Закрыть</button></div>';
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });

      var search = overlay.querySelector('[data-document-link-search]');
      var list = overlay.querySelector('[data-document-link-list]');
      var status = overlay.querySelector('[data-document-link-status]');
      var count = overlay.querySelector('[data-document-link-count]');
      var items = [];
      var timer = null;
      var request = 0;
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 160);
      };
      var onKey = function (event) { if (event.key === 'Escape') { event.stopImmediatePropagation(); close(); } };
      var load = function () {
        var current = ++request;
        var params = new URLSearchParams();
        params.set('q', search.value.trim());
        params.set('limit', 40);
        status.hidden = false;
        status.textContent = 'Загрузка...';
        fetch((window.ADMINX_BASE || (Adminx.base ? Adminx.base() : '')) + '/documents/picker?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (payload) {
            if (current !== request) { return; }
            items = ((payload.data || {}).items) || [];
            list.innerHTML = items.map(function (item, index) {
              return '<button class="rich-editor-document-item" type="button" data-document-link-index="' + index + '"><span class="rich-editor-document-id">#' + self.escape(item.id) + '</span><span><b>' + self.escape(item.title || 'Без названия') + '</b><small>/' + self.escape(String(item.alias || '').replace(/^\/+/, '') || 'без alias') + '</small></span><em>' + self.escape(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</em></button>';
            }).join('');
            status.hidden = items.length > 0;
            status.textContent = search.value.trim() ? 'Ничего не найдено' : 'Документы не найдены';
            count.textContent = items.length ? 'Показано: ' + items.length : '';
          })
          .catch(function () { items = []; list.innerHTML = ''; status.hidden = false; status.textContent = 'Не удалось загрузить документы'; });
      };
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay || event.target.closest('[data-document-link-close]')) { close(); return; }
        var row = event.target.closest('[data-document-link-index]');
        if (!row) { return; }
        var item = items[parseInt(row.getAttribute('data-document-link-index'), 10)];
        if (item) { done(item); close(); }
      });
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 220); });
      document.addEventListener('keydown', onKey);
      load();
      search.focus();
    },

    openMediaPicker: function (editor) {
      this.openImageEditor(editor, {}, null);
    },

    openImageEditor: function (editor, current, nodePos) {
      var self = this;
      current = current || {};
      var editing = typeof nodePos === 'number';
      var overlay = el('div', { class: 'overlay rich-editor-image-overlay' });
      var modal = el('div', { class: 'modal rich-editor-image-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'richEditorImageTitle' });
      modal.innerHTML =
        '<div class="modal-header">' +
          '<span class="dialog-icon info"><i class="ti ti-photo-edit"></i></span>' +
          '<div style="flex:1"><h3 id="richEditorImageTitle">' + (editing ? 'Свойства изображения' : 'Вставка изображения') + '</h3><p class="text-secondary" style="margin-top:4px">Основной файл, WebP, оформление и пропорциональный размер.</p></div>' +
          '<button class="modal-close" type="button" data-image-cancel aria-label="Закрыть"><i class="ti ti-x"></i></button>' +
        '</div>' +
        '<div class="modal-body rich-editor-image-body">' +
          '<div class="rich-editor-image-preview" data-image-preview><div class="rich-editor-image-empty" data-image-empty><i class="ti ti-photo"></i><span>Изображение не выбрано</span></div><img alt="" data-image-preview-img hidden><div class="rich-editor-image-preview-size" data-image-preview-size></div></div>' +
          '<div class="rich-editor-image-settings">' +
            '<div class="field"><span class="field-label">Основное изображение</span><div class="input-group"><input class="input mono" type="text" value="' + this.escape(current.src || '') + '" placeholder="/uploads/image.jpg" data-image-src><button class="input-addon" type="button" data-image-pick="src" data-tooltip="Выбрать из медиа" aria-label="Выбрать основное изображение"><i class="ti ti-photo-search"></i></button></div></div>' +
            '<label class="switch rich-editor-image-picture-switch"><input type="checkbox" data-image-picture' + (current.webp ? ' checked' : '') + '><span></span><b>Использовать picture и WebP</b></label>' +
            '<div class="field" data-image-webp-field' + (current.webp ? '' : ' hidden') + '><span class="field-label">Источник WebP</span><div class="input-group"><input class="input mono" type="text" value="' + this.escape(current.webp || '') + '" placeholder="/uploads/image.webp" data-image-webp><button class="input-addon" type="button" data-image-pick="webp" data-tooltip="Выбрать WebP" aria-label="Выбрать WebP"><i class="ti ti-photo-search"></i></button></div></div>' +
            '<label class="field"><span class="field-label">Альтернативный текст</span><input class="input" type="text" value="' + this.escape(current.alt || '') + '" placeholder="Что изображено" data-image-alt></label>' +
            '<label class="field"><span class="field-label">CSS-класс</span><input class="input mono" type="text" value="' + this.escape(current.class || '') + '" placeholder="Например: content-image is-rounded" data-image-class><span class="field-hint">Можно указать несколько классов через пробел.</span></label>' +
            '<fieldset class="rich-editor-image-size"><legend>Размер</legend>' +
              '<div class="rich-editor-image-dimensions"><label class="field"><span class="field-label">Ширина, px</span><input class="input" type="number" min="1" step="1" value="' + this.escape(current.width || '') + '" data-image-width></label><span class="rich-editor-image-lock" data-tooltip="Пропорции сохраняются"><i class="ti ti-lock"></i></span><label class="field"><span class="field-label">Высота, px</span><input class="input" type="number" min="1" step="1" value="' + this.escape(current.height || '') + '" data-image-height></label></div>' +
              '<div class="rich-editor-image-scale"><input type="range" min="10" max="200" step="1" value="100" data-image-scale><output data-image-scale-value>100%</output><button class="btn btn-ghost btn-sm" type="button" data-image-original><i class="ti ti-aspect-ratio"></i>Исходный размер</button></div>' +
            '</fieldset>' +
          '</div>' +
        '</div>' +
        '<div class="modal-footer"><div class="mf-left">' + (editing ? '<button class="btn btn-danger-soft" type="button" data-image-remove><i class="ti ti-trash"></i>Удалить</button>' : '') + '</div><button class="btn btn-ghost" type="button" data-image-cancel>Отмена</button><button class="btn btn-primary" type="button" data-image-apply><i class="ti ti-check"></i>' + (editing ? 'Сохранить' : 'Вставить') + '</button></div>';
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });

      var srcInput = overlay.querySelector('[data-image-src]');
      var webpInput = overlay.querySelector('[data-image-webp]');
      var pictureInput = overlay.querySelector('[data-image-picture]');
      var webpField = overlay.querySelector('[data-image-webp-field]');
      var altInput = overlay.querySelector('[data-image-alt]');
      var classInput = overlay.querySelector('[data-image-class]');
      var widthInput = overlay.querySelector('[data-image-width]');
      var heightInput = overlay.querySelector('[data-image-height]');
      var scaleInput = overlay.querySelector('[data-image-scale]');
      var scaleValue = overlay.querySelector('[data-image-scale-value]');
      var preview = overlay.querySelector('[data-image-preview-img]');
      var empty = overlay.querySelector('[data-image-empty]');
      var previewSize = overlay.querySelector('[data-image-preview-size]');
      var state = {
        naturalWidth: parseInt(current.width, 10) || 0,
        naturalHeight: parseInt(current.height, 10) || 0,
        ratio: current.width && current.height ? parseInt(current.width, 10) / parseInt(current.height, 10) : 0,
        loadingSrc: ''
      };

      var updateScale = function () {
        var width = parseInt(widthInput.value, 10) || 0;
        var percent = state.naturalWidth && width ? Math.round(width / state.naturalWidth * 100) : 100;
        percent = Math.max(10, Math.min(200, percent));
        scaleInput.value = percent;
        scaleValue.textContent = percent + '%';
        previewSize.textContent = width && heightInput.value ? width + ' x ' + heightInput.value + ' px' : '';
      };
      var setDimensions = function (width, height, natural) {
        width = Math.max(1, Math.round(Number(width) || 0));
        height = Math.max(1, Math.round(Number(height) || 0));
        if (!width || !height) { return; }
        if (natural) {
          state.naturalWidth = width;
          state.naturalHeight = height;
          state.ratio = width / height;
        } else if (!state.ratio) {
          state.ratio = width / height;
        }
        widthInput.value = width;
        heightInput.value = height;
        updateScale();
      };
      var loadPreview = function (src, dimensions) {
        src = String(src || '').trim();
        state.loadingSrc = src;
        if (!src) {
          preview.hidden = true;
          empty.hidden = false;
          previewSize.textContent = '';
          return;
        }
        var probe = new window.Image();
        probe.onload = function () {
          if (state.loadingSrc !== src) { return; }
          preview.src = src;
          preview.hidden = false;
          empty.hidden = true;
          var naturalWidth = dimensions && dimensions.width ? dimensions.width : probe.naturalWidth;
          var naturalHeight = dimensions && dimensions.height ? dimensions.height : probe.naturalHeight;
          if (!widthInput.value || !heightInput.value || src !== current.src) {
            setDimensions(naturalWidth, naturalHeight, true);
          } else {
            state.naturalWidth = naturalWidth || parseInt(widthInput.value, 10);
            state.naturalHeight = naturalHeight || parseInt(heightInput.value, 10);
            state.ratio = state.naturalWidth / state.naturalHeight;
            updateScale();
          }
        };
        probe.onerror = function () {
          if (state.loadingSrc !== src) { return; }
          preview.hidden = true;
          empty.hidden = false;
          empty.querySelector('span').textContent = 'Не удалось загрузить изображение';
        };
        probe.src = src;
      };
      var choose = function (target) {
        if (!Adminx.MediaPicker) { return; }
        Adminx.MediaPicker.open({
          type: 'image',
          title: target === 'webp' ? 'Выбрать WebP' : 'Выбрать изображение',
          description: target === 'webp' ? 'Выберите файл WebP для тега source.' : 'Выберите основное изображение из медиатеки.',
          onPick: function (file) {
            if (!file || !file.url) { return; }
            if (target === 'webp') {
              webpInput.value = file.url;
              pictureInput.checked = true;
              webpField.hidden = false;
              return;
            }
            srcInput.value = file.url;
            if (!altInput.value && file.name) { altInput.value = String(file.name).replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '); }
            widthInput.value = '';
            heightInput.value = '';
            loadPreview(file.url, file);
          },
          onUrl: function (url) {
            if (!url) { return; }
            if (target === 'webp') {
              webpInput.value = url;
              pictureInput.checked = true;
              webpField.hidden = false;
            } else {
              srcInput.value = url;
              widthInput.value = '';
              heightInput.value = '';
              loadPreview(url);
            }
          }
        });
      };
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var apply = function () {
        var src = String(srcInput.value || '').trim();
        if (!src) { srcInput.focus(); return; }
        var attributes = {
          src: src,
          webp: pictureInput.checked ? String(webpInput.value || '').trim() || null : null,
          alt: String(altInput.value || '').trim() || null,
          class: String(classInput.value || '').trim().replace(/\s+/g, ' ') || null,
          width: parseInt(widthInput.value, 10) || null,
          height: parseInt(heightInput.value, 10) || null
        };
        if (editing) {
          editor.chain().focus().setNodeSelection(nodePos).updateAttributes('image', attributes).run();
        } else {
          editor.chain().focus().setImage(attributes).run();
        }
        close();
      };
      var onKey = function (event) {
        if (event.key === 'Escape') {
          if (document.querySelector('.media-picker-overlay.show')) { return; }
          close();
        }
      };

      pictureInput.addEventListener('change', function () { webpField.hidden = !pictureInput.checked; if (pictureInput.checked) { webpInput.focus(); } });
      srcInput.addEventListener('change', function () { widthInput.value = ''; heightInput.value = ''; loadPreview(srcInput.value); });
      widthInput.addEventListener('input', function () {
        var width = parseInt(widthInput.value, 10) || 0;
        if (width && state.ratio) { heightInput.value = Math.max(1, Math.round(width / state.ratio)); }
        updateScale();
      });
      heightInput.addEventListener('input', function () {
        var height = parseInt(heightInput.value, 10) || 0;
        if (height && state.ratio) { widthInput.value = Math.max(1, Math.round(height * state.ratio)); }
        updateScale();
      });
      scaleInput.addEventListener('input', function () {
        var percent = parseInt(scaleInput.value, 10) || 100;
        scaleValue.textContent = percent + '%';
        if (state.naturalWidth && state.naturalHeight) {
          widthInput.value = Math.max(1, Math.round(state.naturalWidth * percent / 100));
          heightInput.value = Math.max(1, Math.round(state.naturalHeight * percent / 100));
          previewSize.textContent = widthInput.value + ' x ' + heightInput.value + ' px';
        }
      });
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay || event.target.closest('[data-image-cancel]')) { close(); return; }
        var pick = event.target.closest('[data-image-pick]');
        if (pick) { choose(pick.getAttribute('data-image-pick')); return; }
        if (event.target.closest('[data-image-original]') && state.naturalWidth && state.naturalHeight) { setDimensions(state.naturalWidth, state.naturalHeight, false); return; }
        if (event.target.closest('[data-image-apply]')) { apply(); return; }
        if (event.target.closest('[data-image-remove]') && editing) { editor.chain().focus().setNodeSelection(nodePos).deleteSelection().run(); close(); }
      });
      document.addEventListener('keydown', onKey);
      if (current.src) { loadPreview(current.src); } else { srcInput.focus(); }
    },

    modalInput: function (cfg, done) {
      var overlay = el('div', { class: 'overlay' });
      var modal = el('div', { class: 'modal rich-editor-prompt', role: 'dialog', 'aria-modal': 'true' });
      modal.innerHTML =
        '<div class="modal-header">' +
          '<span class="dialog-icon info"><i class="ti ti-pencil"></i></span>' +
          '<div style="flex:1"><h3>' + this.escape(cfg.title || 'Введите значение') + '</h3></div>' +
          '<button class="modal-close" type="button" data-cancel aria-label="Закрыть"><i class="ti ti-x"></i></button>' +
        '</div>' +
        '<div class="modal-body"><input class="input" type="text" value="' + this.escape(cfg.value || '') + '" data-input></div>' +
        '<div class="modal-footer"><button class="btn btn-ghost" type="button" data-cancel>Отмена</button>' +
          '<button class="btn btn-primary" type="button" data-ok style="margin-left:auto">Применить</button></div>';
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });

      var input = overlay.querySelector('[data-input]');
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var apply = function () {
        var value = input.value || '';
        close();
        done(value);
      };
      var onKey = function (e) {
        if (e.key === 'Escape') { close(); }
        if (e.key === 'Enter') { apply(); }
      };
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-cancel]')) { close(); }
        if (e.target.closest('[data-ok]')) { apply(); }
      });
      document.addEventListener('keydown', onKey);
      input.focus();
      input.select();
    },

    escape: function (value) {
      return String(value == null ? '' : value).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
      });
    }
  };

  document.addEventListener('adminx:content-ready', function (e) {
    Adminx.RichEditor.init(e.detail && e.detail.root ? e.detail.root : document);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.RichEditor.init(); });
  } else {
    Adminx.RichEditor.init();
  }
})(window, document);
