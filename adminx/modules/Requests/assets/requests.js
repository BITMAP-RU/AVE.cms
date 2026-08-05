/**
 * JS раздела «Запросы». Список: копирование/удаление. Редактор: сохранение
 * (ajax), палитра тегов + проверка PHP (lint) + разворот редактора, условия
 * (CRUD + drag-and-drop сортировка). Всё через ajax, подтверждения — Adminx.Confirm.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Requests = {
    activeField: null,
    dragRow: null,
	explainSearchTimer: null,
	currentRevisionId: 0,

    init: function () {
      this.form = document.getElementById('requestForm');
      this.condTable = document.getElementById('condTable');
      this.palette = document.querySelector('[data-req-tags-panel]');
      this.auditPage = document.querySelector('[data-native-audit-page]');
	  this.auditRunning = false;
      var self = this;
	  this.initEditorMode();
	  this.initPresentation();
	  this.initSortBuilder();

      document.addEventListener('click', function (e) { self.onClick(e); });

      // активный редактор — тот, в котором фокус
      document.addEventListener('focusin', function (e) {
        var f = e.target.closest('[data-req-code-field]');
        if (f) { self.activeField = f; }
      });

      // поиск по тегам + фильтр списка запросов
      document.addEventListener('input', function (e) {
		if (e.target.matches('[data-req-tags-search]')) { self.filterTags(e.target.value); }
		if (e.target.matches('[data-requests-search]')) { self.filterList(e.target.value); }
		if (e.target.matches('[data-request-explain-search]')) { self.explainSearchInput(e.target); }
		if (e.target.matches('[data-request-sort-seed-input]')) { self.syncSortRules(); }
		if (e.target.matches('[data-cf="condition_value_key"], [data-cf="condition_value_constant"], [data-cf="condition_value"]')) { self.conditionValueRefresh(e.target.closest('[data-condition-value]')); }
		if (e.target.matches('[data-condition-smart-input]')) { self.conditionSmartValue(e.target); }
		});
		document.addEventListener('change', function (e) {
			if (e.target.matches('[data-group-title]')) { self.groupTitle(e.target); }
			if (e.target.matches('[data-request-preview-renderer-advanced]')) { self.selectPresentation(e.target.value); }
			if (e.target.matches('[data-request-sort-source]')) { self.sortSourceChanged(e.target); }
		if (e.target.matches('[data-cf="condition_value_source"], [data-cf="condition_value_mode"], [data-cf="condition_field_id"], [data-cf="condition_compare"]')) {
		  var conditionRow = e.target.closest('tr');
		  if (e.target.matches('[data-cf="condition_field_id"]')) { self.conditionFieldRefresh(conditionRow, true); }
		  else if (e.target.matches('[data-cf="condition_compare"], [data-cf="condition_value_source"]')) { self.conditionFieldRefresh(conditionRow, false); }
		  self.conditionValueRefresh(conditionRow.querySelector('[data-condition-value]'), e.target);
		  self.syncEditorAdvancedOptions();
		}
		if (e.target.matches('[data-condition-smart-select]')) { self.conditionSmartValue(e.target); }
      });

      // сохранение запроса
      if (this.form) {
        this.form.addEventListener('submit', function (e) { e.preventDefault(); self.save(); });
      }

      // drag-and-drop условий
      if (this.condTable) {
			this.condTable.querySelectorAll('tr[data-cond-id]').forEach(function (row) { self.conditionFieldRefresh(row, false); });
        this.condTable.addEventListener('dragstart', function (e) { self.dragStart(e); });
        this.condTable.addEventListener('dragover', function (e) { self.dragOver(e); });
        this.condTable.addEventListener('drop', function (e) { self.drop(e); });
        this.condTable.addEventListener('dragend', function () { self.dragEnd(); });
      }

      // Esc закрывает fullscreen/палитру
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var fs = document.querySelector('.req-code-field.is-fullscreen');
        if (fs) { self.fullscreen(fs, false); }
        else if (self.palette && !self.palette.hidden) { self.closePalette(); }
      });
    },

    base: function () { return (this.form && this.form.getAttribute('data-base')) || Adminx.base(); },
	escape: function (value) {
	  return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character];
	  });
	},

	initEditorMode: function () {
	  if (!document.querySelector('[data-request-editor-mode]')) { return; }
	  var mode = 'basic';
	  try { mode = window.localStorage.getItem('adminx.requests.editorMode') === 'advanced' ? 'advanced' : 'basic'; } catch (e) {}
	  this.setEditorMode(mode, false);
	},

	setEditorMode: function (mode, remember) {
	  mode = mode === 'advanced' ? 'advanced' : 'basic';
	  this.editorMode = mode;
	  document.querySelectorAll('[data-request-advanced]').forEach(function (node) {
		node.hidden = mode !== 'advanced';
	  });
	  document.querySelectorAll('[data-request-editor-mode-value]').forEach(function (button) {
		var active = button.getAttribute('data-request-editor-mode-value') === mode;
		button.classList.toggle('is-active', active);
		button.setAttribute('aria-pressed', active ? 'true' : 'false');
	  });
	  var description = document.querySelector('[data-request-editor-mode-description]');
	  if (description) {
		description.textContent = mode === 'advanced'
		  ? 'Кеш, интеграции, совместимость, контракт результата и Native executor.'
		  : 'Основные параметры, условия и оформление результата.';
	  }
	  this.syncEditorAdvancedOptions();
	  if (mode === 'advanced') {
		window.requestAnimationFrame(function () {
		  document.querySelectorAll('[data-req-code-field]').forEach(function (field) {
			var textarea = field.querySelector('textarea[data-code-editor]');
			if (textarea && textarea._adminxCodeMirror) { textarea._adminxCodeMirror.refresh(); }
		  });
		});
	  }
	  if (remember !== false) {
		try { window.localStorage.setItem('adminx.requests.editorMode', mode); } catch (e) {}
	  }
	},

	initPresentation: function () {
	  var select = document.querySelector('[data-request-preview-renderer]');
	  if (!select) { return; }
	  this.selectPresentation(select.value, false);
	},

	selectPresentation: function (code) {
	  var select = document.querySelector('[data-request-preview-renderer]');
	  if (!select) { return; }
	  var available = Array.prototype.some.call(select.options, function (option) { return option.value === code; });
	  if (!available) { code = 'data_cards'; }
	  select.value = code;
	  var advanced = document.querySelector('[data-request-preview-renderer-advanced]');
	  if (advanced) { advanced.value = code; }
	  document.querySelectorAll('[data-request-presentation-value]').forEach(function (button) {
		var active = button.getAttribute('data-request-presentation-value') === code;
		button.classList.toggle('is-active', active);
		button.setAttribute('aria-pressed', active ? 'true' : 'false');
	  });
	},

	openCodeEditor: function () {
	  this.setEditorMode('advanced');
	  var field = document.getElementById('requestSiteTemplates');
	  if (!field) { return; }
	  window.requestAnimationFrame(function () {
		field.scrollIntoView({behavior: 'smooth', block: 'start'});
		var textarea = field.querySelector('textarea[data-code-editor]');
		if (textarea && textarea._adminxCodeMirror) { textarea._adminxCodeMirror.focus(); }
		else if (textarea) { textarea.focus(); }
	  });
	},

	initSortBuilder: function () {
	  this.sortList = document.querySelector('[data-request-sort-list]');
	  if (!this.sortList) { return; }
	  var self = this;
	  this.sortList.querySelectorAll('[data-request-sort-source]').forEach(function (select) {
		select.setAttribute('data-sort-source-value', select.value);
	  });
	  this.sortList.addEventListener('dragstart', function (event) { self.sortDragStart(event); });
	  this.sortList.addEventListener('dragover', function (event) { self.sortDragOver(event); });
	  this.sortList.addEventListener('drop', function (event) { self.sortDrop(event); });
	  this.sortList.addEventListener('dragend', function () { self.sortDragEnd(); });
	  this.syncSortRules();
	},

	sortAdd: function (preferred) {
	  if (!this.sortList) { return; }
	  var rows = this.sortList.querySelectorAll('[data-request-sort-rule]');
	  if (rows.length >= 8) { Adminx.Toast.show('Можно добавить не больше восьми уровней', 'warning'); return; }
	  var randomSelected = Array.from(this.sortList.querySelectorAll('[data-request-sort-source]')).some(function (select) { return select.value === 'random:RAND()'; });
	  if (randomSelected) { Adminx.Toast.show('Случайный порядок используется без других уровней', 'warning'); return; }

	  var template = document.querySelector('[data-request-sort-template]');
	  if (!template) { return; }
	  var row = template.content.firstElementChild.cloneNode(true);
	  var select = row.querySelector('[data-request-sort-source]');
	  var used = {};
	  this.sortList.querySelectorAll('[data-request-sort-source]').forEach(function (current) { used[current.value] = true; });
	  var target = preferred || '';
	  if (!target || used[target]) {
		var option = Array.from(select.options).find(function (candidate) { return candidate.value !== 'random:RAND()' && !used[candidate.value]; });
		target = option ? option.value : '';
	  }
	  if (!target) { Adminx.Toast.show('Все доступные поля уже добавлены', 'warning'); return; }
	  select.value = target;
	  select.setAttribute('data-sort-source-value', target);
	  if (preferred === 'document:Id') {
		row.querySelectorAll('[data-request-sort-direction]').forEach(function (button) {
		  var active = button.getAttribute('data-request-sort-direction') === 'ASC';
		  button.classList.toggle('is-active', active);
		  button.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
	  }
	  this.sortList.appendChild(row);
	  this.syncSortRules();
	},

	sortSourceChanged: function (select) {
	  var previous = select.getAttribute('data-sort-source-value') || '';
	  var values = Array.from(this.sortList.querySelectorAll('[data-request-sort-source]')).filter(function (current) { return current !== select; }).map(function (current) { return current.value; });
	  if (values.indexOf(select.value) !== -1) {
		select.value = previous;
		Adminx.Toast.show('Это поле уже участвует в сортировке', 'warning');
		return;
	  }
	  if ((select.value === 'random:RAND()' && values.length) || (select.value !== 'random:RAND()' && values.indexOf('random:RAND()') !== -1)) {
		select.value = previous;
		Adminx.Toast.show('Случайный порядок нельзя сочетать с другими уровнями', 'warning');
		return;
	  }
	  select.setAttribute('data-sort-source-value', select.value);
	  var row = select.closest('[data-request-sort-rule]');
	  if (row) { row.classList.toggle('is-random', select.value === 'random:RAND()'); }
	  this.syncSortRules();
	  if (row && select.value === 'random:RAND()') {
		window.requestAnimationFrame(function () {
		  var seed = row.querySelector('[data-request-sort-seed-input]');
		  if (seed) { seed.focus(); }
		});
	  }
	},

	sortDirection: function (button) {
	  var row = button.closest('[data-request-sort-rule]');
	  if (!row || row.classList.contains('is-random')) { return; }
	  row.querySelectorAll('[data-request-sort-direction]').forEach(function (item) {
		var active = item === button;
		item.classList.toggle('is-active', active);
		item.setAttribute('aria-pressed', active ? 'true' : 'false');
	  });
	  this.syncSortRules();
	},

	sortRemove: function (button) {
	  var row = button.closest('[data-request-sort-rule]');
	  if (row) { row.remove(); this.syncSortRules(); }
	},

	sortStable: function () {
	  if (!this.sortList) { return; }
	  var hasId = Array.from(this.sortList.querySelectorAll('[data-request-sort-source]')).some(function (select) { return select.value === 'document:Id'; });
	  if (hasId) { Adminx.Toast.show('Финальный порядок по ID уже добавлен', 'info'); return; }
	  this.sortAdd('document:Id');
	},

	syncSortRules: function () {
	  if (!this.sortList) { return; }
	  var rules = [];
	  this.sortList.querySelectorAll('[data-request-sort-rule]').forEach(function (row, index) {
		var position = row.querySelector('[data-request-sort-position]');
		if (position) { position.textContent = String(index + 1); }
		var select = row.querySelector('[data-request-sort-source]');
		if (!select) { return; }
		var separator = select.value.indexOf(':');
		var source = separator >= 0 ? select.value.slice(0, separator) : '';
		var key = separator >= 0 ? select.value.slice(separator + 1) : '';
		var activeDirection = row.querySelector('[data-request-sort-direction].is-active');
		var rule = {source: source, key: source === 'field' ? parseInt(key, 10) : key, direction: source === 'random' ? 'ASC' : (activeDirection ? activeDirection.getAttribute('data-request-sort-direction') : 'ASC')};
		if (source === 'random') {
		  var seedInput = row.querySelector('[data-request-sort-seed-input]');
		  var seed = seedInput ? seedInput.value.trim() : '';
		  if (seed !== '') { rule.seed = seed; }
		}
		rules.push(rule);
	  });
	  var input = document.querySelector('[data-request-sort-rules]');
	  if (input) { input.value = JSON.stringify(rules); }
	  var empty = document.querySelector('[data-request-sort-empty]');
	  if (empty) { empty.hidden = rules.length > 0; }
	  var stable = document.querySelector('[data-request-sort-stable]');
	  if (stable) {
		stable.disabled = rules.some(function (rule) { return rule.source === 'random' || (rule.source === 'document' && rule.key === 'Id'); });
	  }
	},

	sortDragStart: function (event) {
	  var handle = event.target.closest('[data-request-sort-handle]');
	  if (!handle) { event.preventDefault(); return; }
	  this.sortDragRow = handle.closest('[data-request-sort-rule]');
	  if (!this.sortDragRow) { return; }
	  this.sortDragRow.classList.add('is-dragging');
	  event.dataTransfer.effectAllowed = 'move';
	  event.dataTransfer.setData('text/plain', 'request-sort');
	},

	sortDragOver: function (event) {
	  if (!this.sortDragRow || !this.sortList) { return; }
	  event.preventDefault();
	  var target = event.target.closest('[data-request-sort-rule]');
	  if (!target || target === this.sortDragRow) { return; }
	  var rect = target.getBoundingClientRect();
	  this.sortList.insertBefore(this.sortDragRow, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
	},

	sortDrop: function (event) {
	  if (!this.sortDragRow) { return; }
	  event.preventDefault();
	  this.sortDragEnd();
	  this.syncSortRules();
	},

	sortDragEnd: function () {
	  if (this.sortDragRow) { this.sortDragRow.classList.remove('is-dragging'); }
	  this.sortDragRow = null;
	},

	syncEditorAdvancedOptions: function () {
	  var basic = this.editorMode !== 'advanced';
	  document.querySelectorAll('[data-request-advanced-option]').forEach(function (option) {
		option.hidden = basic && !option.selected;
	  });
	},

    onClick: function (e) {
      var self = this;
	  var editorMode = e.target.closest('[data-request-editor-mode-value]');
	  if (editorMode) { return this.setEditorMode(editorMode.getAttribute('data-request-editor-mode-value')); }
	  var presentation = e.target.closest('[data-request-presentation-value]');
	  if (presentation) { return this.selectPresentation(presentation.getAttribute('data-request-presentation-value')); }
	  if (e.target.closest('[data-request-open-code]')) { return this.openCodeEditor(); }
	  if (e.target.closest('[data-request-sort-add]')) { return this.sortAdd(); }
	  if (e.target.closest('[data-request-sort-stable]')) { return this.sortStable(); }
	  var sortDirection = e.target.closest('[data-request-sort-direction]');
	  if (sortDirection) { return this.sortDirection(sortDirection); }
	  var sortRemove = e.target.closest('[data-request-sort-remove]');
	  if (sortRemove) { return this.sortRemove(sortRemove); }
      // список
      var copy = e.target.closest('[data-request-copy]');
      if (copy) { return this.copy(copy.closest('tr')); }
      var del = e.target.closest('[data-request-delete]');
      if (del) { return this.remove(del.closest('tr')); }
	  if (e.target.closest('[data-request-revisions]')) { return this.openRevisions(); }
	  var revision = e.target.closest('[data-request-revision-open]');
	  if (revision) { return this.loadRevision(revision.getAttribute('data-request-revision-open')); }
	  if (e.target.closest('[data-request-revision-restore]')) { return this.restoreRevision(); }

      // массовая сверка Legacy и Native
      var auditOne = e.target.closest('[data-native-audit-one]');
      if (auditOne) { return this.auditOne(auditOne.closest('[data-native-audit-row]'), auditOne); }
      if (e.target.closest('[data-native-audit-all]')) { return this.auditAll(e.target.closest('[data-native-audit-all]')); }
      if (e.target.closest('[data-native-audit-stabilize]')) { return this.auditStabilize(); }
      if (e.target.closest('[data-native-audit-activate]')) { return this.auditActivate(); }
      if (e.target.closest('[data-native-audit-rollback]')) { return this.auditRollback(); }

      if (e.target.closest('[data-requests-reset]')) {
        var input = document.querySelector('[data-requests-search]');
        if (input) { input.value = ''; }
        return this.filterList('');
      }

      // редактор: тулбар
      var tags = e.target.closest('[data-req-tags-toggle]');
      if (tags) { return this.openPalette(tags.closest('[data-req-code-field]')); }
      if (e.target.closest('[data-req-tags-close]')) { return this.closePalette(); }
      var lint = e.target.closest('[data-req-lint]');
      if (lint) { return this.lint(lint.closest('[data-req-code-field]')); }
      var full = e.target.closest('[data-req-fullscreen]');
      if (full) { return this.fullscreen(full.closest('[data-req-code-field]')); }
	  var preview = e.target.closest('[data-request-preview]');
	  if (preview) { return this.preview(preview); }
	  var explainOption = e.target.closest('[data-request-explain-option]');
	  if (explainOption) { return this.selectExplainDocument(explainOption); }
	  var explainRun = e.target.closest('[data-request-explain-run]');
	  if (explainRun) { return this.explainSelected(explainRun); }
	  var previewExplain = e.target.closest('[data-preview-explain]');
	  if (previewExplain) { return this.explain(parseInt(previewExplain.getAttribute('data-preview-explain') || '0', 10)); }

      // палитра
      var tab = e.target.closest('[data-req-tag-tab]');
      if (tab) { return this.activateGroup(tab.getAttribute('data-req-tag-tab')); }
      var tag = e.target.closest('[data-req-tag]');
      if (tag) { return this.insertTag(tag); }

      // условия
	      var condAdd = e.target.closest('[data-cond-add]');
	      if (condAdd) { return this.condAdd(condAdd.closest('[data-cond-group]')); }
		  var booleanChoice = e.target.closest('[data-condition-boolean]');
		  if (booleanChoice) { return this.conditionBooleanValue(booleanChoice); }
		  var documentPick = e.target.closest('[data-condition-document-pick]');
		  if (documentPick) { return this.conditionDocumentPicker(documentPick.closest('tr')); }
      var save = e.target.closest('[data-cond-save]');
      if (save) { return this.condSave(save.closest('tr')); }
      var cdel = e.target.closest('[data-cond-delete]');
      if (cdel) { return this.condDelete(cdel.closest('tr')); }
      var groupOperator = e.target.closest('[data-group-operator]');
      if (groupOperator) { return this.groupOperator(groupOperator); }
      var groupAdd = e.target.closest('[data-group-add]');
      if (groupAdd) { return this.groupAdd(groupAdd.closest('[data-cond-group]')); }
      var groupDelete = e.target.closest('[data-group-delete]');
      if (groupDelete) { return this.groupDelete(groupDelete.closest('[data-cond-group]')); }
    },

    // ------------------------------------------------------------------ //
    //  Список
    // ------------------------------------------------------------------ //
    auditBase: function () {
      return this.auditPage ? this.auditPage.getAttribute('data-base') : Adminx.base();
    },

    auditRequest: function (url) {
      return Adminx.Ajax.post(url, new FormData()).then(function (payload) {
        var data = payload.data || {};
        if (!payload.ok || !data.success) {
          throw new Error(data.message || 'Не удалось выполнить проверку');
        }

        return data;
      });
    },

    auditSetBusy: function (busy) {
      this.auditRunning = busy;
      if (!this.auditPage) { return; }
      this.auditPage.querySelectorAll('[data-native-audit-one]').forEach(function (button) {
        button.disabled = busy;
      });
      document.querySelectorAll('[data-native-audit-all], [data-native-audit-stabilize], [data-native-audit-activate], [data-native-audit-rollback]').forEach(function (button) {
        var unavailable = button.hasAttribute('data-native-audit-activate')
          ? button.getAttribute('data-audit-enabled') !== '1'
          : (button.hasAttribute('data-native-audit-stabilize') && button.getAttribute('data-audit-enabled') !== '1');
        button.disabled = busy || unavailable;
      });
    },

    auditProgress: function (done, total, title) {
      var progress = this.auditPage && this.auditPage.querySelector('[data-native-audit-progress]');
      if (!progress) { return; }
      progress.hidden = false;
      var heading = progress.querySelector('[data-native-audit-progress-title]');
      var copy = progress.querySelector('[data-native-audit-progress-copy]');
      var bar = progress.querySelector('[data-native-audit-progress-bar]');
      if (heading) { heading.textContent = title || 'Проверка запросов'; }
      if (copy) { copy.textContent = done + ' из ' + total; }
      if (bar) { bar.style.width = (total ? Math.round(done / total * 100) : 0) + '%'; }
    },

    auditState: function (row, comparison) {
      if (!row) { return; }
      var result = row.querySelector('[data-audit-result]');
      var status = comparison && comparison.status ? comparison.status : 'error';
      var labels = {
        matched: ['is-success', 'ti-circle-check', 'Совпадает'],
        unstable: ['is-warning', 'ti-arrows-sort', 'Порядок не закреплён'],
        unsupported: ['is-warning', 'ti-code', 'Остаётся Legacy'],
        order_diff: ['is-warning', 'ti-arrows-sort', 'Отличается порядок'],
        mismatch: ['is-danger', 'ti-alert-triangle', 'Результаты различаются'],
        incomplete: ['is-warning', 'ti-hourglass', 'Нужна ручная проверка'],
        error: ['is-danger', 'ti-alert-triangle', 'Ошибка проверки']
      };
      var label = labels[status] || labels.error;
      var detail = '';
      if (comparison) {
        if (comparison.reasons && comparison.reasons.length) {
          detail = comparison.reasons.join(' ');
		} else if (comparison.legacy && comparison.native) {
			detail = 'Legacy: ' + comparison.legacy.total + ' · Native: ' + comparison.native.total;
			if (comparison.runtime_verification && comparison.runtime_verification.length > 1) {
				detail += ' · проверено состояний: ' + comparison.runtime_verification.length;
			}
			if (comparison.missing_ids && comparison.missing_ids.length) { detail += ' · нет ID: ' + comparison.missing_ids.slice(0, 8).join(', '); }
          if (comparison.extra_ids && comparison.extra_ids.length) { detail += ' · лишние ID: ' + comparison.extra_ids.slice(0, 8).join(', '); }
        }
		var orderDetail = this.auditOrderDetail(comparison.order_diagnostics || {});
		if (orderDetail) { detail += (detail ? ' ' : '') + orderDetail; }
      }
      result.replaceChildren();
      var strong = document.createElement('b');
      strong.className = label[0];
      var icon = document.createElement('i');
      icon.className = 'ti ' + label[1];
      strong.appendChild(icon);
      strong.appendChild(document.createTextNode(label[2]));
      result.appendChild(strong);
      if (detail) {
        var small = document.createElement('small');
        small.textContent = detail;
		small.setAttribute('data-tooltip', detail);
        result.appendChild(small);
      }
      row.setAttribute('data-audit-verified', status === 'matched' ? '1' : '0');
      row.setAttribute('data-audit-status', status);
    },

	auditOrderDetail: function (diagnostics) {
	  if (!diagnostics || !diagnostics.position) { return ''; }
	  var detail = 'Позиция ' + diagnostics.position + ': Legacy #' + diagnostics.legacy_id + ', Native #' + diagnostics.native_id + '.';
	  var criterion = diagnostics.criteria && diagnostics.criteria.length ? diagnostics.criteria[0] : null;
	  if (criterion) {
		detail += ' ' + criterion.label + ': ' + criterion.legacy_value + ' / ' + criterion.native_value + '.';
	  }
	  return detail;
	},

    auditOne: function (row, button, quiet) {
      if (!row || this.auditRunning && !quiet) { return Promise.resolve(false); }
      var self = this;
      var id = row.getAttribute('data-native-audit-row');
      var icon = button && button.querySelector('i');
      if (button) { button.disabled = true; }
      if (icon) { icon.classList.add('is-spinning'); }
      return this.auditRequest(this.auditBase() + '/requests/' + encodeURIComponent(id) + '/native-audit').then(function (data) {
        self.auditState(row, data.data && data.data.comparison ? data.data.comparison : {});
        self.auditRefreshStats();
        if (!quiet) { Adminx.Toast.show(data.message || 'Запрос проверен', 'success'); }
        return true;
      }).catch(function (error) {
        self.auditState(row, { status: 'error', reasons: [error.message] });
        if (!quiet) { Adminx.Toast.show(error.message, 'error'); }
        return false;
      }).then(function (result) {
        if (button) { button.disabled = false; }
        if (icon) { icon.classList.remove('is-spinning'); }
        return result;
      });
    },

    auditAll: function () {
      if (!this.auditPage || this.auditRunning) { return; }
      var self = this;
      var rows = Array.prototype.slice.call(this.auditPage.querySelectorAll('[data-native-audit-row]'));
      this.auditSetBusy(true);
      this.auditProgress(0, rows.length, 'Подготовка Shadow');
      this.auditRequest(this.auditBase() + '/requests/native-audit/prepare').then(function () {
        rows.forEach(function (row) {
          var mode = row.querySelector('[data-audit-mode]');
          if (mode) { mode.className = 'badge badge-blue'; mode.textContent = 'Shadow'; }
        });
        var done = 0;
        var matched = 0;
        var run = function () {
          if (done >= rows.length) {
            self.auditSetBusy(false);
            self.auditRefreshStats();
            self.auditProgress(done, rows.length, 'Проверка завершена');
            Adminx.Toast.show('Проверка завершена: совпало ' + matched + ' из ' + rows.length, matched ? 'success' : 'warning');
            return;
          }
          self.auditProgress(done, rows.length, 'Проверяется запрос #' + rows[done].getAttribute('data-native-audit-row'));
          var button = rows[done].querySelector('[data-native-audit-one]');
          self.auditOne(rows[done], button, true).then(function (ok) {
            if (ok && rows[done].getAttribute('data-audit-verified') === '1') { matched++; }
            done++;
            self.auditProgress(done, rows.length, 'Проверка запросов');
            window.setTimeout(run, 30);
          });
        };
        run();
      }).catch(function (error) {
        self.auditSetBusy(false);
        self.auditProgress(0, rows.length, 'Проверка остановлена');
        Adminx.Toast.show(error.message, 'error');
      });
    },

    auditRefreshStats: function () {
      if (!this.auditPage) { return; }
      var rows = this.auditPage.querySelectorAll('[data-native-audit-row]');
      var verified = this.auditPage.querySelectorAll('[data-audit-verified="1"]').length;
      var nativeCount = 0;
      rows.forEach(function (row) {
        var mode = row.querySelector('[data-audit-mode]');
        if (mode && mode.textContent.trim().toLowerCase() === 'native') { nativeCount++; }
      });
      var verifiedStat = document.querySelector('[data-audit-stat="verified"]');
      var nativeStat = document.querySelector('[data-audit-stat="native"]');
      if (verifiedStat) { verifiedStat.textContent = String(verified); }
      if (nativeStat) { nativeStat.textContent = String(nativeCount); }
      var activate = document.querySelector('[data-native-audit-activate]');
      if (activate) {
        activate.setAttribute('data-audit-enabled', verified ? '1' : '0');
        activate.disabled = this.auditRunning || !verified;
      }
      var unstable = this.auditPage.querySelectorAll('[data-audit-status="unstable"], [data-audit-status="order_diff"]').length;
      var stabilize = document.querySelector('[data-native-audit-stabilize]');
      if (stabilize) {
        stabilize.setAttribute('data-audit-enabled', unstable ? '1' : '0');
        stabilize.disabled = this.auditRunning || !unstable;
      }
    },

    auditStabilize: function () {
      if (!this.auditPage || this.auditRunning) { return; }
      var self = this;
      var rows = Array.prototype.slice.call(this.auditPage.querySelectorAll('[data-audit-status="unstable"], [data-audit-status="order_diff"]'));
      if (!rows.length) {
        Adminx.Toast.show('Запросов с незакреплённым порядком нет', 'info');
        return;
      }

      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Закрепить порядок без изменения выдачи?',
        message: 'Для каждого запроса полная Legacy-последовательность будет сверена с ID по возрастанию и убыванию. Native включится только при точном совпадении; любое расхождение автоматически отменит изменение.',
        confirmLabel: 'Проверить и закрепить',
        onConfirm: function () {
          self.auditSetBusy(true);
          var done = 0;
          var applied = 0;
          var skipped = 0;
          var run = function () {
            if (done >= rows.length) {
              self.auditSetBusy(false);
              self.auditRefreshStats();
              self.auditProgress(done, rows.length, 'Порядок проверен');
              var message = 'Порядок закреплён: ' + applied;
              if (skipped) { message += ' · без изменений: ' + skipped; }
              Adminx.Toast.show(message, applied ? 'success' : 'warning');
              window.setTimeout(function () { window.location.reload(); }, 650);
              return;
            }

            var row = rows[done];
            var id = row.getAttribute('data-native-audit-row');
			var originalStatus = row.getAttribute('data-audit-status') || 'unstable';
            self.auditProgress(done, rows.length, 'Проверяется порядок запроса #' + id);
            self.auditRequest(self.auditBase() + '/requests/' + encodeURIComponent(id) + '/native-stabilize').then(function (data) {
              var result = data.data || {};
              if (result.status === 'applied' && result.comparison) {
                applied++;
                self.auditState(row, result.comparison);
                var mode = row.querySelector('[data-audit-mode]');
                if (mode) { mode.className = 'badge badge-green'; mode.textContent = 'Native'; }
                var plan = row.querySelector('[data-audit-plan]');
                if (plan) { plan.className = 'badge badge-blue'; plan.textContent = 'Готов'; }
              } else {
                skipped++;
                var reason = result.message || 'Безопасный порядок не найден';
                self.auditState(row, { status: originalStatus, reasons: [reason] });
              }
            }).catch(function (error) {
              skipped++;
              self.auditState(row, { status: 'error', reasons: [error.message] });
            }).then(function () {
              done++;
              self.auditRefreshStats();
              self.auditProgress(done, rows.length, 'Закрепление порядка');
              window.setTimeout(run, 30);
            });
          };

          self.auditProgress(0, rows.length, 'Подготовка безопасной проверки');
          run();
        }
      });
    },

    auditActivate: function () {
      if (this.auditRunning) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Включить Native executor?',
        message: 'Новый путь включится только у запросов с точным совпадением текущего плана. Остальные продолжат работать через Legacy.',
        confirmLabel: 'Включить подтверждённые',
        onConfirm: function () {
          self.auditSetBusy(true);
          self.auditRequest(self.auditBase() + '/requests/native-audit/activate').then(function (data) {
            Adminx.Toast.show((data.message || 'Native включён') + ': ' + ((data.data && data.data.count) || 0), 'success');
            window.location.reload();
          }).catch(function (error) {
            self.auditSetBusy(false);
            Adminx.Toast.show(error.message, 'error');
          });
        }
      });
    },

    auditRollback: function () {
      if (this.auditRunning) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Вернуть все запросы в Legacy?',
        message: 'Публичный сайт сразу вернётся к прежнему executor. Результаты проверки сохранятся.',
        confirmLabel: 'Вернуть Legacy',
        onConfirm: function () {
          self.auditSetBusy(true);
          self.auditRequest(self.auditBase() + '/requests/native-audit/rollback').then(function (data) {
            Adminx.Toast.show(data.message || 'Legacy восстановлен', 'success');
            window.location.reload();
          }).catch(function (error) {
            self.auditSetBusy(false);
            Adminx.Toast.show(error.message, 'error');
          });
        }
      });
    },

	openRevisions: function () {
	  var id = parseInt(this.form ? this.form.getAttribute('data-id') : '0', 10) || 0;
	  if (!id) { return; }
	  this.currentRevisionId = 0;
	  var list = document.querySelector('[data-request-revisions-list]');
	  var detail = document.querySelector('[data-request-revision-detail]');
	  var restore = document.querySelector('[data-request-revision-restore]');
	  if (list) { list.innerHTML = '<div class="empty-state">Загрузка...</div>'; }
	  if (detail) { detail.innerHTML = '<div class="empty-state">Выберите ревизию слева.</div>'; }
	  if (restore) { restore.disabled = true; }
	  if (Adminx.Drawer) { Adminx.Drawer.open('requestRevisionsDrawer'); }
	  var self = this;
	  Adminx.Ajax.request(this.base() + '/requests/' + id + '/revisions').then(function (payload) {
		var response = payload.data || {};
		if (!response.success) { throw new Error(response.message || 'Не удалось загрузить ревизии'); }
		self.renderRevisions((response.data && response.data.revisions) || []);
	  }).catch(function (error) { Adminx.Toast.show(error.message || 'Не удалось загрузить ревизии', 'error'); });
	},

	renderRevisions: function (items) {
	  var self = this;
	  var list = document.querySelector('[data-request-revisions-list]');
	  var meta = document.querySelector('[data-request-revisions-meta]');
	  if (meta) { meta.textContent = items.length ? ('Снимков: ' + items.length + '. Параметры, группы и условия сохраняются вместе.') : 'История пока пуста'; }
	  if (!list) { return; }
	  if (!items.length) { list.innerHTML = '<div class="empty-state">Первый снимок появится после сохранения запроса.</div>'; return; }
	  list.innerHTML = items.map(function (item) {
		return '<button class="request-revision-row" type="button" data-request-revision-open="' + item.id + '"><span class="icon-tile"><i class="ti ti-history"></i></span><span><b>Ревизия #' + item.id + '</b><small>' + self.escape(item.created_label || '-') + (item.author_name ? ' · ' + self.escape(item.author_name) : '') + '</small></span><em class="badge ' + self.escape(item.badge || 'badge-gray') + '">' + self.escape(item.action_label || item.action || '') + '</em></button>';
	  }).join('');
	  this.loadRevision(items[0].id);
	},

	loadRevision: function (id) {
	  var self = this;
	  id = parseInt(id, 10) || 0;
	  if (!id) { return; }
	  Adminx.Ajax.request(this.base() + '/requests/revisions/' + id).then(function (payload) {
		var response = payload.data || {};
		if (!response.success) { throw new Error(response.message || 'Не удалось загрузить ревизию'); }
		self.showRevision(response.data || {});
	  }).catch(function (error) { Adminx.Toast.show(error.message || 'Не удалось загрузить ревизию', 'error'); });
	},

	showRevision: function (item) {
	  this.currentRevisionId = parseInt(item.id, 10) || 0;
	  document.querySelectorAll('[data-request-revision-open]').forEach(function (row) {
		row.classList.toggle('is-active', row.getAttribute('data-request-revision-open') === String(item.id));
	  });
	  var snapshot = item.snapshot || {};
	  var comparison = item.comparison || {};
	  var parts = [
		{key: 'request', title: 'Параметры и шаблоны', count: Object.keys(snapshot.request || {}).length},
		{key: 'groups', title: 'Группы условий', count: (snapshot.groups || []).length},
		{key: 'conditions', title: 'Условия', count: (snapshot.conditions || []).length}
	  ];
	  var changedCount = parts.filter(function (part) { return comparison.items && comparison.items[part.key] && comparison.items[part.key].changed; }).length;
	  var detail = document.querySelector('[data-request-revision-detail]');
	  var self = this;
	  if (detail) {
		detail.innerHTML = '<div class="request-revision-detail-head"><div><h4>Ревизия #' + this.currentRevisionId + '</h4><p>' + this.escape(item.created_label || '-') + (item.author_name ? ' · ' + this.escape(item.author_name) : '') + '</p></div><span class="badge ' + (changedCount ? 'badge-amber' : 'badge-green') + '">' + (changedCount ? ('изменятся разделы: ' + changedCount) : 'совпадает') + '</span></div><div class="request-revision-parts">' + parts.map(function (part) {
		  var changed = !!(comparison.items && comparison.items[part.key] && comparison.items[part.key].changed);
		  return '<article class="' + (changed ? 'is-changed' : 'is-unchanged') + '"><span class="icon-tile"><i class="ti ' + (part.key === 'request' ? 'ti-template' : (part.key === 'groups' ? 'ti-folders' : 'ti-filter')) + '"></i></span><div><b>' + self.escape(part.title) + '</b><small>Элементов: ' + part.count + '</small></div><span class="badge ' + (changed ? 'badge-amber' : 'badge-gray') + '">' + (changed ? 'изменится' : 'совпадает') + '</span></article>';
		}).join('') + '</div>';
	  }
	  var restore = document.querySelector('[data-request-revision-restore]');
	  if (restore) { restore.disabled = !this.currentRevisionId || !changedCount; }
	},

	restoreRevision: function () {
	  if (!this.currentRevisionId) { return; }
	  var self = this;
	  Adminx.Confirm.open({
		kind: 'warning', title: 'Восстановить запрос?', message: 'Параметры, шаблоны и всё дерево условий будут возвращены вместе. Текущее состояние сохранится резервной ревизией.', confirmLabel: 'Восстановить',
		onConfirm: function () {
		  var data = new FormData(); data.set('_csrf', Adminx.csrf());
		  Adminx.Ajax.post(self.base() + '/requests/revisions/' + self.currentRevisionId + '/restore', data).then(function (payload) {
			var response = payload.data || {};
			if (!response.success) { throw new Error(response.message || 'Не удалось восстановить запрос'); }
			Adminx.Toast.show(response.message || 'Запрос восстановлен', 'success');
			window.location.reload();
		  }).catch(function (error) { Adminx.Toast.show(error.message || 'Не удалось восстановить запрос', 'error'); });
		}
	  });
	},

    filterList: function (query) {
      var root = document.querySelector('[data-requests-page]');
      if (!root) { return; }
      var q = String(query || '').trim().toLowerCase();
      var rows = root.querySelectorAll('[data-requests-list] tr[data-request-row]');
      var visible = 0;
      rows.forEach(function (row) {
        var show = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
        row.hidden = !show;
        if (show) { visible++; }
      });
      var count = root.querySelector('[data-requests-count]');
      if (count) { count.textContent = String(visible); }
      var summary = root.querySelector('[data-requests-summary]');
      if (summary) { summary.textContent = q ? (visible + ' из ' + rows.length + ' по фильтру.') : (rows.length + ' запросов в списке.'); }
      var empty = root.querySelector('[data-requests-empty]');
      if (empty) { empty.hidden = !(q && visible === 0); }
      var reset = root.querySelector('[data-requests-reset]');
      if (reset) { reset.hidden = !q; }
    },

    copy: function (row) {
      var base = Adminx.base();
      Adminx.Loader.show();
      Adminx.Ajax.post(base + '/requests/' + row.dataset.id + '/copy').then(function (p) {
        Adminx.Loader.hide();
        var d = p.data || {};
        if (d.success && d.redirect) { window.location.href = d.redirect; }
        else { Adminx.Toast.show(d.message || 'Не удалось', 'error'); }
      }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
    },

    remove: function (row) {
      var base = Adminx.base();
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить запрос?',
        message: 'Запрос «' + row.dataset.alias + '» и его условия будут удалены.',
        confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(base + '/requests/' + row.dataset.id + '/delete').then(function (p) {
            Adminx.Loader.hide();
            var d = p.data || {};
            if (d.success) { row.remove(); Adminx.Toast.show(d.message, 'success'); }
            else { Adminx.Toast.show(d.message || 'Ошибка', 'error'); }
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    // ------------------------------------------------------------------ //
    //  Сохранение запроса
    // ------------------------------------------------------------------ //
    save: function () {
      var self = this;
      this.form.querySelectorAll('[data-error]').forEach(function (s) { s.textContent = ''; });
      var id = this.form.getAttribute('data-id') || '0';
      var url = this.base() + '/requests' + (id !== '0' ? '/' + id : '');
      Adminx.Loader.show();
      Adminx.Ajax.post(url, new FormData(this.form)).then(function (p) {
        Adminx.Loader.hide();
        var d = p.data || {};
        if (d.success) {
          if (d.redirect) { window.location.href = d.redirect; return; }
          Adminx.Toast.show(d.message || 'Сохранено', 'success');
          return;
        }
        var errors = d.errors || {};
        Object.keys(errors).forEach(function (f) {
          var span = self.form.querySelector('[data-error="' + f + '"]');
          if (span) { span.textContent = errors[f]; }
          var inp = self.form.querySelector('[name="' + f + '"]');
          if (inp) { inp.classList.add('is-invalid'); }
        });
        Adminx.Toast.show(d.message || 'Проверьте поля', 'error');
      }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
    },

	preview: function (button) {
	  if (!this.form || !button || button.disabled) { return; }
	  var id = parseInt(this.form.getAttribute('data-id') || '0', 10);
	  if (id <= 0) { return; }

	  var fd = new FormData();
	  this.form.querySelectorAll('[name="result_system[]"]:checked').forEach(function (input) {
		fd.append('result_system[]', input.value);
	  });
	  this.form.querySelectorAll('[name="result_fields[]"]:checked').forEach(function (input) {
		fd.append('result_fields[]', input.value);
	  });
	  var limit = document.querySelector('[data-request-preview-limit]');
	  fd.append('limit', limit ? limit.value : '10');
	  var renderer = document.querySelector('[data-request-preview-renderer]');
	  fd.append('renderer', renderer ? renderer.value : 'data_cards');

	  var self = this;
	  button.disabled = true;
	  button.classList.add('is-loading');
	  Adminx.Ajax.post(this.base() + '/requests/' + id + '/preview', fd).then(function (payload) {
		var response = payload.data || {};
		if (!response.success) {
		  Adminx.Toast.show(response.message || 'Предпросмотр не выполнен', 'error');
		  return;
		}

		self.renderPreview(response.data || {});
		Adminx.Toast.show(response.message || 'Предпросмотр обновлён', 'success');
	  }).catch(function () {
		Adminx.Toast.show('Ошибка сети при построении предпросмотра', 'error');
	  }).then(function () {
		button.disabled = false;
		button.classList.remove('is-loading');
	  });
	},

	renderPreview: function (data) {
	  var section = document.querySelector('[data-request-preview-section]');
	  if (!section) { return; }
	  var empty = section.querySelector('[data-request-preview-empty]');
	  var result = section.querySelector('[data-request-preview-result]');
	  if (empty) { empty.hidden = true; }
	  if (result) { result.hidden = false; }

	  this.setText(section, '[data-preview-total]', String(data.total || 0));
	  this.setText(section, '[data-preview-returned]', String((data.items || []).length));
	  this.setText(section, '[data-preview-time]', this.previewMilliseconds(data.query_time));
	  this.renderPreviewPlan(section.querySelector('[data-request-preview-plan]'), data.plan || {});
	  this.renderExecutorComparison(section.querySelector('[data-request-executor-comparison]'), data.executor_comparison || {});
	  this.renderPreviewItems(section.querySelector('[data-request-preview-items]'), data);

	  var sql = section.querySelector('[data-request-preview-sql]');
	  if (sql) { sql.textContent = data.sql || 'SQL доступен только пользователю с правом управления запросами.'; }
	},

	renderExecutorComparison: function (root, comparison) {
	  if (!root) { return; }
	  root.textContent = '';
	  var status = comparison.status || '';
	  if (!status) { root.hidden = true; return; }
	  root.hidden = false;
	  var states = {
		matched: ['is-success', 'ti-circle-check', 'Legacy и Native совпали', 'Количество, состав и порядок документов одинаковы.'],
		unstable: ['is-warning', 'ti-arrows-sort', 'Состав совпал, порядок не закреплён', 'Публичный вывод остаётся на Legacy, пока одинаковые значения сортировки не получат безопасный финальный порядок.'],
		order_diff: ['is-warning', 'ti-arrows-sort', 'Состав совпал, порядок отличается', 'До переключения проверьте сортировку запроса.'],
		mismatch: ['is-danger', 'ti-alert-triangle', 'Результаты различаются', 'Native включать рано: сравните отсутствующие и лишние ID.'],
		incomplete: ['is-warning', 'ti-database-exclamation', 'Выборка слишком велика для автоподтверждения', 'Проверены первые 5000 документов. Оставьте Legacy или разбейте представление на более узкие запросы.'],
		unsupported: ['is-warning', 'ti-code', 'Запрос пока требует Legacy', 'В плане есть PHP, свободный SQL или неподдерживаемая операция.'],
		error: ['is-danger', 'ti-alert-circle', 'Сравнение не выполнено', 'Legacy продолжает работать; ошибка записана в системный журнал.']
	  };
	  var state = states[status] || states.error;
	  var nativeOption = document.querySelector('[data-native-executor-option]');
	  var nativeState = document.querySelector('[data-native-plan-state]');
	  if (nativeOption && !nativeOption.selected) { nativeOption.disabled = status !== 'matched'; }
	  if (nativeState) {
		nativeState.className = 'badge ' + (status === 'matched' ? 'badge-green' : (status === 'unsupported' ? 'badge-amber' : 'badge-blue'));
		nativeState.textContent = status === 'matched'
		  ? 'Native подтверждён'
		  : (status === 'unsupported' ? 'Требуется Legacy' : (status === 'unstable' ? 'Нужен стабильный порядок' : 'Нужно проверить'));
	  }
	  root.className = 'request-executor-comparison ' + state[0];
	  var icon = document.createElement('span');
	  icon.className = 'icon-tile';
	  icon.innerHTML = '<i class="ti ' + state[1] + '"></i>';
	  var copy = document.createElement('div');
	  var title = document.createElement('b');
	  title.textContent = state[2];
	  var description = document.createElement('span');
	  description.textContent = state[3];
	  copy.appendChild(title);
	  copy.appendChild(description);
	  root.appendChild(icon);
	  root.appendChild(copy);

	  var details = [];
	  if (comparison.legacy && comparison.native) {
		details.push('Legacy: ' + comparison.legacy.total);
		details.push('Native: ' + comparison.native.total);
	  }
	  if (comparison.runtime_verification && comparison.runtime_verification.length > 1) {
		details.push('Runtime-состояний: ' + comparison.runtime_verification.length);
	  }
	  if ((comparison.missing_ids || []).length) { details.push('Нет в Native: #' + comparison.missing_ids.join(', #')); }
	  if ((comparison.extra_ids || []).length) { details.push('Лишние в Native: #' + comparison.extra_ids.join(', #')); }
	  (comparison.reasons || []).forEach(function (reason) { details.push(reason); });
	  if (details.length) {
		var meta = document.createElement('small');
		meta.textContent = details.join(' · ');
		root.appendChild(meta);
	  }
	},

	setText: function (root, selector, value) {
	  var node = root && root.querySelector(selector);
	  if (node) { node.textContent = value; }
	},

	previewMilliseconds: function (seconds) {
	  var value = Number(seconds || 0) * 1000;
	  if (!isFinite(value)) { value = 0; }
	  return (value < 10 ? value.toFixed(2) : value.toFixed(0)) + ' мс';
	},

	renderPreviewPlan: function (root, plan) {
	  if (!root) { return; }
	  root.textContent = '';
	  var items = [
		['ti-category', 'Рубрика #' + (plan.rubric_id || 0)],
		['ti-brackets', (plan.groups || 0) + ' групп'],
		['ti-filter-check', (plan.conditions || 0) + ' активных условий'],
		['ti-sort-descending', plan.order || 'Без явной сортировки'],
		['ti-calendar-check', plan.publication || 'Проверка публикации'],
	  ];
	  items.forEach(function (item) {
		var chip = document.createElement('span');
		var icon = document.createElement('i');
		icon.className = 'ti ' + item[0];
		chip.appendChild(icon);
		chip.appendChild(document.createTextNode(item[1]));
		root.appendChild(chip);
	  });
	},

	renderPreviewItems: function (root, data) {
	  if (!root) { return; }
	  root.textContent = '';
	  var items = Array.isArray(data.items) ? data.items : [];
	  var renderer = data.renderer || {};
	  if (renderer.presentation === 'json') {
		var json = document.createElement('pre');
		json.className = 'request-preview-json';
		json.textContent = JSON.stringify({
		  total: Number(data.total || 0),
		  limit: Number(data.limit || 0),
		  items: items
		}, null, 2);
		root.appendChild(json);
		return;
	  }
	  if (!items.length) {
		var empty = document.createElement('div');
		empty.className = 'request-preview-no-items';
		empty.innerHTML = '<i class="ti ti-filter-off"></i><b>Документы не найдены</b><span>Проверьте условия, статус публикации и выбранную рубрику.</span>';
		root.appendChild(empty);
		return;
	  }

	  var systemOptions = data.system_options || {};
	  var fieldOptions = data.field_options || {};
	  if (renderer.presentation === 'list') {
		this.renderPreviewList(root, items, systemOptions, fieldOptions);
		return;
	  }
	  if (renderer.presentation === 'table') {
		this.renderPreviewTable(root, items, systemOptions, fieldOptions);
		return;
	  }
	  items.forEach(function (item, index) {
		var card = document.createElement('article');
		card.className = 'request-preview-item';
		card.style.setProperty('--preview-order', String(Math.min(index, 8)));
		var head = document.createElement('header');
		var title = document.createElement('div');
		var titleText = item.system && item.system.title ? String(item.system.title) : 'Документ #' + item.document_id;
		title.innerHTML = '<span class="icon-tile"><i class="ti ti-file-text"></i></span>';
		var copy = document.createElement('span');
		var strong = document.createElement('b');
		strong.textContent = titleText;
		var small = document.createElement('small');
		small.textContent = '#' + item.document_id;
		copy.appendChild(strong);
		copy.appendChild(small);
		title.appendChild(copy);
		head.appendChild(title);
		var explain = document.createElement('button');
		explain.className = 'btn btn-ghost btn-icon btn-sm ax-act ax-act-view';
		explain.type = 'button';
		explain.setAttribute('data-preview-explain', String(item.document_id));
		explain.setAttribute('data-tooltip', 'Объяснить результат');
		explain.setAttribute('aria-label', 'Объяснить результат');
		explain.innerHTML = '<i class="ti ti-route"></i>';
		head.appendChild(explain);
		card.appendChild(head);

		var values = document.createElement('dl');
		values.className = 'request-preview-values';
		Object.keys(item.system || {}).forEach(function (key) {
		  if (key === 'title') { return; }
		  Adminx.Requests.appendPreviewValue(values, systemOptions[key] || key, item.system[key], key);
		});
		Object.keys(item.fields || {}).forEach(function (key) {
		  var field = item.fields[key] || {};
		  var option = fieldOptions[key] || {};
		  var label = option.title || field.alias || ('Поле #' + key);
		  Adminx.Requests.appendPreviewValue(values, label, field.value, field.alias || key);
		});
		card.appendChild(values);
		root.appendChild(card);
	  });
	},

	renderPreviewList: function (root, items, systemOptions, fieldOptions) {
	  items.forEach(function (item, index) {
		var row = document.createElement('article');
		row.className = 'request-preview-list-item';
		row.style.setProperty('--preview-order', String(Math.min(index, 8)));

		var main = document.createElement('div');
		main.className = 'request-preview-list-main';
		main.innerHTML = '<span class="icon-tile"><i class="ti ti-file-text"></i></span>';
		var copy = document.createElement('span');
		var title = document.createElement('b');
		title.textContent = Adminx.Requests.previewTitle(item);
		var id = document.createElement('small');
		id.textContent = '#' + item.document_id;
		copy.appendChild(title);
		copy.appendChild(id);
		main.appendChild(copy);
		row.appendChild(main);

		var values = document.createElement('div');
		values.className = 'request-preview-list-values';
		Adminx.Requests.previewEntries(item, systemOptions, fieldOptions).slice(0, 3).forEach(function (entry) {
		  var value = document.createElement('span');
		  var label = document.createElement('small');
		  label.textContent = entry.label;
		  var text = document.createElement('b');
		  text.textContent = Adminx.Requests.previewValue(entry.value);
		  value.appendChild(label);
		  value.appendChild(text);
		  values.appendChild(value);
		});
		row.appendChild(values);
		row.appendChild(Adminx.Requests.previewExplainButton(item.document_id));
		root.appendChild(row);
	  });
	},

	renderPreviewTable: function (root, items, systemOptions, fieldOptions) {
	  var columns = [];
	  var known = {};
	  items.forEach(function (item) {
		Adminx.Requests.previewEntries(item, systemOptions, fieldOptions).forEach(function (entry) {
		  if (!known[entry.key]) { known[entry.key] = true; columns.push({key: entry.key, label: entry.label}); }
		});
	  });

	  var wrap = document.createElement('div');
	  wrap.className = 'request-preview-table-wrap';
	  var table = document.createElement('table');
	  table.className = 'table request-preview-table';
	  var head = document.createElement('thead');
	  var headRow = document.createElement('tr');
	  var titleHead = document.createElement('th');
	  titleHead.textContent = 'Материал';
	  headRow.appendChild(titleHead);
	  columns.forEach(function (column) {
		var th = document.createElement('th');
		th.textContent = column.label;
		headRow.appendChild(th);
	  });
	  var actionHead = document.createElement('th');
	  actionHead.className = 'request-preview-table-action';
	  headRow.appendChild(actionHead);
	  head.appendChild(headRow);
	  table.appendChild(head);

	  var body = document.createElement('tbody');
	  items.forEach(function (item) {
		var values = {};
		Adminx.Requests.previewEntries(item, systemOptions, fieldOptions).forEach(function (entry) { values[entry.key] = entry.value; });
		var tr = document.createElement('tr');
		var titleCell = document.createElement('td');
		titleCell.className = 'request-preview-table-title';
		var title = document.createElement('b');
		title.textContent = Adminx.Requests.previewTitle(item);
		var id = document.createElement('small');
		id.textContent = '#' + item.document_id;
		titleCell.appendChild(title);
		titleCell.appendChild(id);
		tr.appendChild(titleCell);
		columns.forEach(function (column) {
		  var td = document.createElement('td');
		  td.textContent = Adminx.Requests.previewValue(Object.prototype.hasOwnProperty.call(values, column.key) ? values[column.key] : null);
		  tr.appendChild(td);
		});
		var action = document.createElement('td');
		action.className = 'request-preview-table-action';
		action.appendChild(Adminx.Requests.previewExplainButton(item.document_id));
		tr.appendChild(action);
		body.appendChild(tr);
	  });
	  table.appendChild(body);
	  wrap.appendChild(table);
	  root.appendChild(wrap);
	},

	previewTitle: function (item) {
	  return item.system && item.system.title ? String(item.system.title) : 'Документ #' + item.document_id;
	},

	previewEntries: function (item, systemOptions, fieldOptions) {
	  var entries = [];
	  Object.keys(item.system || {}).forEach(function (key) {
		if (key !== 'title') { entries.push({key: 'system.' + key, label: systemOptions[key] || key, value: item.system[key]}); }
	  });
	  Object.keys(item.fields || {}).forEach(function (key) {
		var field = item.fields[key] || {};
		var option = fieldOptions[key] || {};
		entries.push({key: 'field.' + key, label: option.title || field.alias || ('Поле #' + key), value: field.value});
	  });
	  return entries;
	},

	previewExplainButton: function (documentId) {
	  var button = document.createElement('button');
	  button.className = 'btn btn-ghost btn-icon btn-sm ax-act ax-act-view';
	  button.type = 'button';
	  button.setAttribute('data-preview-explain', String(documentId));
	  button.setAttribute('data-tooltip', 'Объяснить результат');
	  button.setAttribute('aria-label', 'Объяснить результат');
	  button.innerHTML = '<i class="ti ti-route"></i>';
	  return button;
	},

	appendPreviewValue: function (root, label, value, code) {
	  var group = document.createElement('div');
	  var term = document.createElement('dt');
	  term.textContent = label;
	  var system = document.createElement('small');
	  system.textContent = code;
	  term.appendChild(system);
	  var description = document.createElement('dd');
	  description.textContent = this.previewValue(value);
	  group.appendChild(term);
	  group.appendChild(description);
	  root.appendChild(group);
	},

	previewValue: function (value) {
	  if (value === null || typeof value === 'undefined' || value === '') { return '—'; }
	  if (typeof value === 'boolean') { return value ? 'Да' : 'Нет'; }
	  if (typeof value === 'object') {
		try { return JSON.stringify(value, null, 2); } catch (e) { return '[структурированные данные]'; }
	  }
	  return String(value);
	},

	explainSearchInput: function (input) {
	  input.removeAttribute('data-document-id');
	  var button = document.querySelector('[data-request-explain-run]');
	  var directId = /^\s*#?\d+\s*$/.test(input.value) ? parseInt(input.value.replace(/\D/g, ''), 10) : 0;
	  if (directId > 0) {
		input.setAttribute('data-document-id', String(directId));
		if (button) { button.disabled = false; }
	  } else if (button) {
		button.disabled = true;
	  }

	  window.clearTimeout(this.explainSearchTimer);
	  var self = this;
	  this.explainSearchTimer = window.setTimeout(function () { self.searchExplainDocuments(input); }, 220);
	},

	searchExplainDocuments: function (input) {
	  if (!this.form || !input) { return; }
	  var id = parseInt(this.form.getAttribute('data-id') || '0', 10);
	  if (id <= 0) { return; }
	  var options = document.querySelector('[data-request-explain-options]');
	  if (!options) { return; }
	  var query = String(input.value || '').trim();
	  var url = this.base() + '/requests/' + id + '/explain-documents?q=' + encodeURIComponent(query) + '&limit=12';
	  options.hidden = false;
	  options.innerHTML = '<div class="request-explain-options-state"><i class="ti ti-loader-2"></i>Поиск…</div>';
	  fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (response) {
		if (!response.ok) { throw new Error('HTTP ' + response.status); }
		return response.json();
	  }).then(function (payload) {
		var response = payload || {};
		var items = response.data && response.data.items ? response.data.items : [];
		Adminx.Requests.renderExplainOptions(options, items);
	  }).catch(function () {
		options.innerHTML = '<div class="request-explain-options-state is-error"><i class="ti ti-alert-circle"></i>Поиск недоступен</div>';
	  });
	},

	renderExplainOptions: function (root, items) {
	  root.textContent = '';
	  if (!items.length) {
		root.innerHTML = '<div class="request-explain-options-state"><i class="ti ti-file-off"></i>Документы не найдены</div>';
		return;
	  }

	  items.forEach(function (item) {
		var button = document.createElement('button');
		button.type = 'button';
		button.setAttribute('data-request-explain-option', String(item.id));
		button.setAttribute('data-title', item.title || ('Документ #' + item.id));
		var icon = document.createElement('span');
		icon.className = 'icon-tile';
		icon.innerHTML = '<i class="ti ti-file-text"></i>';
		var copy = document.createElement('span');
		var title = document.createElement('b');
		title.textContent = item.title || ('Документ #' + item.id);
		var meta = document.createElement('small');
		meta.textContent = '#' + item.id + (item.alias ? ' · ' + item.alias : '') + (item.deleted ? ' · удалён' : (!item.status ? ' · снят с публикации' : ''));
		copy.appendChild(title);
		copy.appendChild(meta);
		button.appendChild(icon);
		button.appendChild(copy);
		root.appendChild(button);
	  });
	},

	selectExplainDocument: function (option) {
	  var input = document.querySelector('[data-request-explain-search]');
	  var root = document.querySelector('[data-request-explain-options]');
	  var id = parseInt(option.getAttribute('data-request-explain-option') || '0', 10);
	  if (!input || id <= 0) { return; }
	  input.value = '#' + id + ' · ' + (option.getAttribute('data-title') || 'Документ');
	  input.setAttribute('data-document-id', String(id));
	  if (root) { root.hidden = true; }
	  var run = document.querySelector('[data-request-explain-run]');
	  if (run) { run.disabled = false; run.focus(); }
	},

	explainSelected: function (button) {
	  var input = document.querySelector('[data-request-explain-search]');
	  var id = input ? parseInt(input.getAttribute('data-document-id') || '0', 10) : 0;
	  if (id > 0) { this.explain(id, button); }
	},

	explain: function (documentId, button) {
	  if (!this.form || documentId <= 0) { return; }
	  var requestId = parseInt(this.form.getAttribute('data-id') || '0', 10);
	  if (requestId <= 0) { return; }
	  var drawer = document.getElementById('requestExplainDrawer');
	  if (!drawer) { return; }
	  this.resetExplainDrawer(drawer);
	  if (Adminx.Drawer) { Adminx.Drawer.open('requestExplainDrawer'); }
	  if (button) { button.disabled = true; }
	  var fd = new FormData();
	  fd.append('document_id', String(documentId));
	  Adminx.Ajax.post(this.base() + '/requests/' + requestId + '/explain', fd).then(function (payload) {
		var response = payload.data || {};
		if (!response.success) {
		  Adminx.Requests.renderExplainError(drawer, response.message || 'Проверка не выполнена');
		  return;
		}
		Adminx.Requests.renderExplain(drawer, response.data || {});
	  }).catch(function () {
		Adminx.Requests.renderExplainError(drawer, 'Ошибка сети при проверке документа');
	  }).then(function () {
		if (button) { button.disabled = false; }
	  });
	},

	resetExplainDrawer: function (drawer) {
	  var loading = drawer.querySelector('[data-request-explain-loading]');
	  var content = drawer.querySelector('[data-request-explain-content]');
	  if (loading) { loading.hidden = false; loading.innerHTML = '<i class="ti ti-loader-2"></i><span>Проверяем запрос…</span>'; }
	  if (content) { content.hidden = true; }
	  this.setText(drawer, '[data-request-explain-title]', 'Проверка документа');
	  this.setText(drawer, '[data-request-explain-subtitle]', 'Условия запроса и фактический итог executor.');
	  this.setText(drawer, '[data-request-explain-footer]', 'Выполняется');
	  var footer = drawer.querySelector('[data-request-explain-footer]');
	  if (footer) { footer.className = 'badge badge-gray'; }
	  var icon = drawer.querySelector('[data-request-explain-icon]');
	  if (icon) { icon.className = 'dialog-icon info'; icon.innerHTML = '<i class="ti ti-route"></i>'; }
	},

	renderExplainError: function (drawer, message) {
	  var loading = drawer.querySelector('[data-request-explain-loading]');
	  if (loading) { loading.hidden = false; loading.innerHTML = '<i class="ti ti-alert-triangle"></i><span></span>'; loading.querySelector('span').textContent = message; }
	  this.setText(drawer, '[data-request-explain-footer]', 'Ошибка');
	  var footer = drawer.querySelector('[data-request-explain-footer]');
	  if (footer) { footer.className = 'badge badge-red'; }
	  var icon = drawer.querySelector('[data-request-explain-icon]');
	  if (icon) { icon.className = 'dialog-icon danger'; icon.innerHTML = '<i class="ti ti-alert-triangle"></i>'; }
	},

	renderExplain: function (drawer, data) {
	  var loading = drawer.querySelector('[data-request-explain-loading]');
	  var content = drawer.querySelector('[data-request-explain-content]');
	  if (loading) { loading.hidden = true; }
	  if (content) { content.hidden = false; }
	  var documentData = data.document || {};
	  var matched = !!data.matched;
	  this.setText(drawer, '[data-request-explain-title]', documentData.title || ('Документ #' + documentData.id));
	  this.setText(drawer, '[data-request-explain-subtitle]', '#' + documentData.id + (documentData.alias ? ' · ' + documentData.alias : ''));
	  this.setText(drawer, '[data-request-explain-footer]', matched ? 'Входит в запрос' : 'Исключён');
	  var footer = drawer.querySelector('[data-request-explain-footer]');
	  if (footer) { footer.className = 'badge ' + (matched ? 'badge-green' : 'badge-red'); }
	  var icon = drawer.querySelector('[data-request-explain-icon]');
	  if (icon) { icon.className = 'dialog-icon ' + (matched ? 'success' : 'danger'); icon.innerHTML = '<i class="ti ti-' + (matched ? 'circle-check' : 'circle-x') + '"></i>'; }
	  this.renderExplainChecks(drawer.querySelector('[data-request-explain-base]'), data.base || []);
	  this.renderExplainTree(drawer.querySelector('[data-request-explain-tree]'), data.groups || []);
	  this.renderExplainNotes(drawer.querySelector('[data-request-explain-notes]'), data.notes || []);
	},

	renderExplainChecks: function (root, checks) {
	  if (!root) { return; }
	  root.textContent = '';
	  checks.forEach(function (check) {
		var item = document.createElement('article');
		item.className = 'request-explain-check is-' + check.state;
		item.innerHTML = '<i class="ti ti-' + (check.state === 'passed' ? 'check' : 'x') + '"></i><div><b></b><span></span></div>';
		item.querySelector('b').textContent = check.label || check.code;
		item.querySelector('span').textContent = check.detail || '';
		root.appendChild(item);
	  });
	},

	renderExplainTree: function (root, groups) {
	  if (!root) { return; }
	  root.textContent = '';
	  if (!groups.length) {
		root.innerHTML = '<div class="request-explain-tree-empty"><i class="ti ti-filter-off"></i><span>Активных условий нет. Результат определяется системными ограничениями.</span></div>';
		return;
	  }
	  groups.forEach(function (group) { root.appendChild(Adminx.Requests.explainGroupNode(group)); });
	},

	explainGroupNode: function (group) {
	  var section = document.createElement('section');
	  section.className = 'request-explain-group is-' + (group.state || 'empty');
	  var head = document.createElement('header');
	  head.innerHTML = '<span class="request-explain-group-state"><i class="ti"></i></span><div><b></b><small></small></div><span class="badge"></span>';
	  head.querySelector('b').textContent = group.title || ('Группа #' + group.id);
	  head.querySelector('small').textContent = group.operator === 'OR' ? 'Достаточно одного совпадения' : 'Должны совпасть все элементы';
	  head.querySelector('.badge').textContent = group.operator === 'OR' ? 'ИЛИ' : 'И';
	  head.querySelector('.badge').className = 'badge ' + (group.operator === 'OR' ? 'badge-amber' : 'badge-blue');
	  head.querySelector('.ti').className = 'ti ti-' + this.explainStateIcon(group.state);
	  section.appendChild(head);
	  var body = document.createElement('div');
	  body.className = 'request-explain-group-body';
	  (group.conditions || []).forEach(function (condition) { body.appendChild(Adminx.Requests.explainConditionNode(condition)); });
	  (group.children || []).forEach(function (child) { body.appendChild(Adminx.Requests.explainGroupNode(child)); });
	  if (!body.children.length) { body.innerHTML = '<div class="request-explain-tree-empty">В группе нет активных условий</div>'; }
	  section.appendChild(body);
	  return section;
	},

	explainConditionNode: function (condition) {
	  var item = document.createElement('article');
	  item.className = 'request-explain-condition is-' + (condition.state || 'unknown');
	  item.innerHTML = '<span class="request-explain-condition-state"><i class="ti"></i></span><div class="request-explain-condition-copy"><div><b></b><code></code></div><dl><div><dt>Фактически</dt><dd data-actual></dd></div><div><dt>Условие</dt><dd data-expected></dd></div></dl><p hidden></p></div>';
	  item.querySelector('.ti').className = 'ti ti-' + this.explainStateIcon(condition.state);
	  item.querySelector('b').textContent = condition.field || ('Поле #' + condition.field_id);
	  item.querySelector('code').textContent = (condition.alias || ('#' + condition.field_id)) + ' ' + condition.operator;
	  item.querySelector('[data-actual]').textContent = this.previewValue(condition.actual);
	  item.querySelector('[data-expected]').textContent = this.previewValue(condition.expected);
	  var note = item.querySelector('p');
	  if (condition.note) { note.hidden = false; note.textContent = condition.note; }
	  return item;
	},

	explainStateIcon: function (state) {
	  if (state === 'passed') { return 'check'; }
	  if (state === 'failed') { return 'x'; }
	  if (state === 'unknown') { return 'help'; }
	  return 'minus';
	},

	renderExplainNotes: function (root, notes) {
	  if (!root) { return; }
	  root.textContent = '';
	  notes.forEach(function (note) {
		var item = document.createElement('span');
		item.innerHTML = '<i class="ti ti-info-circle"></i>';
		item.appendChild(document.createTextNode(note));
		root.appendChild(item);
	  });
	},

    // ------------------------------------------------------------------ //
    //  Редактор кода: fullscreen / lint / палитра тегов
    // ------------------------------------------------------------------ //
    cmOf: function (field) {
      var ta = field && field.querySelector('textarea[data-code-editor]');
      return ta && ta._adminxCodeMirror ? ta._adminxCodeMirror : null;
    },

    codeOf: function (field) {
      var cm = this.cmOf(field);
      if (cm) { cm.save(); return cm.getValue(); }
      var ta = field && field.querySelector('textarea');
      return ta ? ta.value : '';
    },

    fullscreen: function (field, force) {
      if (!field) { return; }
      var on = typeof force === 'boolean' ? force : !field.classList.contains('is-fullscreen');
      field.classList.toggle('is-fullscreen', on);
      document.body.classList.toggle('req-code-fullscreen-open', on);
      var cm = this.cmOf(field);
      if (cm) { setTimeout(function () { cm.refresh(); cm.focus(); }, 30); }
    },

    lint: function (field) {
      if (!field) { return; }
      var out = field.querySelector('[data-req-lint-result]');
      if (out) { out.textContent = 'Проверка…'; out.classList.remove('is-ok', 'is-error'); }
      var fd = new FormData();
      fd.append('code', this.codeOf(field));
      Adminx.Ajax.post(this.base() + '/requests/lint', fd).then(function (p) {
        var d = p.data || {};
        if (d.success) {
          if (out) { out.textContent = d.message; out.classList.add('is-ok'); }
          Adminx.Toast.show(d.message || 'Синтаксис без ошибок', 'success');
        } else {
          var detail = (d.errors && d.errors.code) ? d.errors.code : (d.message || 'Ошибка синтаксиса');
          if (out) { out.textContent = detail; out.classList.add('is-error'); }
          Adminx.Toast.show(d.message || 'Ошибка синтаксиса', 'error');
        }
      }).catch(function () {
        if (out) { out.textContent = 'Не удалось выполнить проверку.'; out.classList.add('is-error'); }
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    openPalette: function (field) {
      if (!this.palette || !field) { return; }
      this.activeField = field;
      var textarea = field.querySelector('textarea[data-req-editor]');
      this.paletteScope = textarea && textarea.name === 'request_template_item' ? 'item' : 'main';
      // переносим палитру под тулбар активного редактора
      var ta = field.querySelector('textarea[data-code-editor]');
      var host = ta ? ta.parentNode : field.querySelector('.card-body');
      if (ta) { host.insertBefore(this.palette, ta); } else { host.appendChild(this.palette); }
      this.palette.hidden = false;
      this.applyPaletteScope();
      var search = this.palette.querySelector('[data-req-tags-search]');
      if (search) { search.value = ''; this.filterTags(''); search.focus(); }
    },

    closePalette: function () { if (this.palette) { this.palette.hidden = true; } },

    applyPaletteScope: function () {
      if (!this.palette) { return; }
      var scope = this.paletteScope || 'main';
      this.palette.querySelectorAll('[data-req-tag-scope]').forEach(function (node) {
        var target = node.getAttribute('data-req-tag-scope') || 'all';
        node.hidden = target !== 'all' && target !== scope;
        if (node.hidden) { node.classList.remove('is-active'); }
      });
      var first = this.palette.querySelector('[data-req-tag-tab]:not([hidden])');
      if (first) { this.activateGroup(first.getAttribute('data-req-tag-tab')); }
    },

    activateGroup: function (index) {
      if (!this.palette) { return; }
      this.palette.querySelectorAll('[data-req-tag-tab]').forEach(function (t) {
        t.classList.toggle('is-active', !t.hidden && t.getAttribute('data-req-tag-tab') === String(index));
      });
      this.palette.querySelectorAll('[data-req-tag-panel]').forEach(function (g) {
        g.classList.toggle('is-active', !g.hidden && g.getAttribute('data-req-tag-panel') === String(index));
      });
    },

    filterTags: function (query) {
      if (!this.palette) { return; }
      query = (query || '').trim().toLowerCase();
      var shown = 0;
      this.palette.querySelectorAll('[data-req-tag]').forEach(function (b) {
        var group = b.closest('[data-req-tag-group]');
        var scope = group ? (group.getAttribute('data-req-tag-scope') || 'all') : '';
        var allowed = group && (scope === 'all' || scope === (this.paletteScope || 'main'));
        var match = allowed && (query === '' || b.textContent.toLowerCase().indexOf(query) !== -1);
        b.hidden = !match;
        if (match) { shown++; }
      }, this);
      // При поиске показываем все подходящие группы активного редактора.
      this.palette.querySelectorAll('[data-req-tag-group]').forEach(function (g) {
        var scope = g.getAttribute('data-req-tag-scope') || 'all';
        var allowed = scope === 'all' || scope === (this.paletteScope || 'main');
        g.hidden = !allowed || (query !== '' && !g.querySelector('[data-req-tag]:not([hidden])'));
        if (query !== '') { g.classList.toggle('is-active', !g.hidden); }
      }, this);
      this.palette.querySelectorAll('[data-req-tag-tab]').forEach(function (tab) {
        var scope = tab.getAttribute('data-req-tag-scope') || 'all';
        var allowed = scope === 'all' || scope === (this.paletteScope || 'main');
        var panel = this.palette.querySelector('[data-req-tag-panel="' + tab.getAttribute('data-req-tag-tab') + '"]');
        tab.hidden = !allowed || (query !== '' && (!panel || panel.hidden));
      }, this);
      if (query === '') {
        var active = this.palette.querySelector('[data-req-tag-tab].is-active:not([hidden])');
        var first = active || this.palette.querySelector('[data-req-tag-tab]:not([hidden])');
        if (first) { this.activateGroup(first.getAttribute('data-req-tag-tab')); }
      }
      var empty = this.palette.querySelector('[data-req-tags-empty]');
      if (empty) { empty.hidden = shown !== 0; }
    },

    insertTag: function (button) {
      var value = button.getAttribute('data-req-tag') || '';
      var select = button.getAttribute('data-req-tag-select') || '';
      var cm = this.cmOf(this.activeField);
      if (!value || !cm) { this.closePalette(); return; }
      var start = cm.indexFromPos(cm.getCursor());
      cm.replaceSelection(value, 'around');
      cm.focus();
      if (select && value.indexOf(select) !== -1) {
        cm.setSelection(cm.posFromIndex(start + value.indexOf(select)), cm.posFromIndex(start + value.indexOf(select) + select.length));
      } else {
        cm.setCursor(cm.posFromIndex(start + value.length));
      }
      cm.save();
      this.closePalette();
    },

    // ------------------------------------------------------------------ //
    //  Условия
    // ------------------------------------------------------------------ //
    condAdd: function (group) {
      if (!this.condTable || !group) { return; }
      var tpl = document.querySelector('[data-cond-tpl]');
      var body = group.querySelector(':scope > .req-condition-table-wrap [data-cond-body]');
      if (!tpl || !body) { return; }
      var empty = body.querySelector('[data-cond-empty]');
      if (empty) { empty.remove(); }
	      var row = tpl.content.firstElementChild.cloneNode(true);
	      body.appendChild(row);
		  this.conditionFieldRefresh(row, true);
	      var f = row.querySelector('[data-cf="condition_field_id"]');
      if (f) { f.focus(); }
    },

	conditionFieldRefresh: function (row, changed) {
	  if (!row) { return; }
	  var field = row.querySelector('[data-cf="condition_field_id"]');
	  var option = field && field.options[field.selectedIndex];
	  if (!option) { return; }
	  var meta = row.querySelector('[data-condition-field-meta]');
	  var typeLabel = option.getAttribute('data-cond-type-label') || option.getAttribute('data-cond-type') || 'Поле';
	  var alias = option.getAttribute('data-cond-alias') || ('field_' + option.value);
	  if (meta) { meta.textContent = ''; meta.hidden = true; }
	  if (field) { field.title = 'Тип: ' + typeLabel + '. Системное имя: ' + alias; }
	  var mode = row.querySelector('[data-cf="condition_value_mode"]');
	  if (changed && mode && option.getAttribute('data-cond-mode')) { mode.value = option.getAttribute('data-cond-mode'); }
	  this.conditionOperatorRefresh(row, option, changed);
	  this.conditionSmartControl(row, option);
	  this.conditionValueRefresh(row.querySelector('[data-condition-value]'));
	},

	conditionOperatorRefresh: function (row, fieldOption, changed) {
	  var compare = row.querySelector('[data-cf="condition_compare"]');
	  var source = row.querySelector('[data-cf="condition_value_source"]');
	  if (!compare) { return; }
	  var allowed = this.conditionJson(fieldOption.getAttribute('data-cond-operators'), []);
	  var legacy = source && source.value === 'legacy';
	  Array.prototype.forEach.call(compare.options, function (option) {
		var visible = legacy || allowed.indexOf(option.value) !== -1 || (!changed && option.selected);
		option.hidden = !visible;
		option.disabled = !visible;
	  });
	  if (changed && compare.options[compare.selectedIndex] && compare.options[compare.selectedIndex].disabled) {
		for (var i = 0; i < compare.options.length; i++) {
		  if (!compare.options[i].disabled) { compare.value = compare.options[i].value; break; }
		}
	  }
	},

	conditionSmartControl: function (row, fieldOption) {
	  var editor = row.querySelector('[data-condition-value]');
	  if (!editor) { return; }
	  var source = editor.querySelector('[data-cf="condition_value_source"]');
	  var storage = editor.querySelector('[data-condition-literal-storage]');
	  var root = editor.querySelector('[data-condition-smart-control]');
	  if (!storage || !root) { return; }
	  root.innerHTML = '';
	  storage.hidden = false;
	  storage.classList.remove('mono');
	  if (!source || source.value !== 'literal') { return; }
	  var kind = fieldOption.getAttribute('data-cond-kind') || 'text';
	  var compare = row.querySelector('[data-cf="condition_compare"]');
	  var operator = compare ? compare.value : '==';
	  var options = this.conditionJson(fieldOption.getAttribute('data-cond-options'), []);
	  var disabled = storage.disabled;
	  if (kind === 'text' && options.length === 0) {
		storage.placeholder = operator === 'IN=' || operator === 'NOTIN=' ? 'Значения через запятую' : 'Введите значение';
		return;
	  }
	  if (kind === 'boolean') {
		storage.hidden = true;
		root.innerHTML = '<span class="req-condition-boolean" role="group" aria-label="Логическое значение">'
		  + '<button type="button" data-condition-boolean="1"><i class="ti ti-check"></i>Да</button>'
		  + '<button type="button" data-condition-boolean="0"><i class="ti ti-x"></i>Нет</button></span>';
		root.querySelectorAll('[data-condition-boolean]').forEach(function (button) {
		  button.disabled = disabled;
		  button.setAttribute('aria-pressed', button.getAttribute('data-condition-boolean') === storage.value ? 'true' : 'false');
		});
		return;
	  }
	  if ((kind === 'choice' || kind === 'choice_list') && options.length) {
		storage.hidden = true;
		var select = document.createElement('select');
		select.className = 'select req-condition-choice';
		select.setAttribute('data-condition-smart-select', '');
		select.disabled = disabled;
		var multiple = kind === 'choice_list' || operator === 'IN=' || operator === 'NOTIN=';
		select.multiple = multiple;
		if (multiple) { select.size = Math.min(4, Math.max(2, options.length)); }
		else {
		  var empty = document.createElement('option'); empty.value = ''; empty.textContent = '— выберите —'; select.appendChild(empty);
		}
		var selected = multiple ? this.conditionCsv(storage.value) : [String(storage.value || '')];
		options.forEach(function (item) {
		  var option = document.createElement('option');
		  option.value = String(item.value == null ? '' : item.value);
		  option.textContent = String(item.label == null ? option.value : item.label);
		  option.selected = selected.indexOf(option.value) !== -1;
		  select.appendChild(option);
		});
		root.appendChild(select);
		return;
	  }
	  if (kind === 'relation') {
		storage.hidden = true;
		var relation = document.createElement('div');
		relation.className = 'req-condition-document';
		relation.innerHTML = '<div class="input-wrap"><i class="ti ti-file"></i><input class="input" type="text" inputmode="numeric" data-condition-smart-input placeholder="ID документа"></div>'
		  + '<button class="btn btn-secondary btn-icon" type="button" data-condition-document-pick data-tooltip="Найти документ" aria-label="Найти документ"><i class="ti ti-file-search"></i></button>';
		relation.querySelector('input').value = storage.value || '';
		relation.querySelector('input').disabled = disabled;
		relation.querySelector('button').disabled = disabled;
		root.appendChild(relation);
		return;
	  }
	  if (kind === 'date' || kind === 'datetime') {
		storage.hidden = true;
		var date = document.createElement('input');
		date.className = 'input'; date.type = kind === 'date' ? 'date' : 'datetime-local';
		date.setAttribute('data-condition-smart-input', ''); date.setAttribute('data-condition-date-kind', kind);
		var dateValue = this.conditionDateInput(storage.value, kind);
		date.value = dateValue.value; date.setAttribute('data-condition-date-format', dateValue.format); date.disabled = disabled;
		root.appendChild(date);
		return;
	  }
	  storage.hidden = true;
	  var input = document.createElement('input');
	  input.className = 'input'; input.type = 'text'; input.setAttribute('data-condition-smart-input', ''); input.value = storage.value || ''; input.disabled = disabled;
	  if (kind === 'number') { input.inputMode = 'decimal'; input.placeholder = operator === 'SEGMENT' || operator === 'INTERVAL' ? 'Например, 10, 50' : 'Введите число'; }
	  else if (kind === 'relation_list') { input.placeholder = 'ID документов через запятую'; }
	  else { input.placeholder = 'Введите значение'; }
	  root.appendChild(input);
	},

	conditionSmartValue: function (control) {
	  var editor = control.closest('[data-condition-value]');
	  var storage = editor && editor.querySelector('[data-condition-literal-storage]');
	  if (!storage) { return; }
	  if (control.matches('select[multiple]')) {
		var values = Array.prototype.filter.call(control.options, function (item) { return item.selected; }).map(function (item) { return item.value; });
		storage.value = this.conditionCsvEncode(values);
	  } else if (control.hasAttribute('data-condition-date-kind')) {
		storage.value = this.conditionDateStorage(control.value, control.getAttribute('data-condition-date-kind'), control.getAttribute('data-condition-date-format'));
	  } else { storage.value = control.value; }
	  storage.dispatchEvent(new Event('input', { bubbles: true }));
	},

	conditionBooleanValue: function (button) {
	  var editor = button.closest('[data-condition-value]');
	  var storage = editor && editor.querySelector('[data-condition-literal-storage]');
	  if (!storage) { return; }
	  storage.value = button.getAttribute('data-condition-boolean');
	  button.parentNode.querySelectorAll('[data-condition-boolean]').forEach(function (item) {
		item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
	  });
	  storage.dispatchEvent(new Event('input', { bubbles: true }));
	},

	conditionJson: function (value, fallback) {
	  try { var parsed = JSON.parse(value || ''); return parsed || fallback; } catch (e) { return fallback; }
	},

	conditionCsv: function (value) {
	  var matches = String(value || '').match(/(?:^|,)\s*(?:"([^"]*(?:""[^"]*)*)"|([^,]*))/g) || [];
	  return matches.map(function (item) { return item.replace(/^\s*,?\s*/, '').replace(/^"|"$/g, '').replace(/""/g, '"').trim(); }).filter(Boolean);
	},

	conditionCsvEncode: function (values) {
	  return values.map(function (value) {
		value = String(value); return /[,"]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
	  }).join(',');
	},

	conditionDateInput: function (value, kind) {
	  value = String(value || '').trim();
	  var format = kind === 'datetime' ? 'timestamp' : 'dmy';
	  var date = null;
	  if (/^\d{9,10}$/.test(value)) { date = new Date(parseInt(value, 10) * 1000); format = 'timestamp'; }
	  else if (/^\d{2}\.\d{2}\.\d{4}$/.test(value)) { var p = value.split('.'); date = new Date(p[2] + '-' + p[1] + '-' + p[0] + 'T00:00:00'); format = 'dmy'; }
	  else if (/^\d{4}-\d{2}-\d{2}/.test(value)) { date = new Date(value.replace(' ', 'T')); format = 'iso'; }
	  if (!date || isNaN(date.getTime())) { return { value: '', format: format }; }
	  var pad = function (number) { return String(number).padStart(2, '0'); };
	  var result = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
	  if (kind === 'datetime') { result += 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes()); }
	  return { value: result, format: format };
	},

	conditionDateStorage: function (value, kind, format) {
	  if (!value) { return ''; }
	  if (format === 'timestamp' || kind === 'datetime') { return String(Math.floor(new Date(value).getTime() / 1000)); }
	  if (format === 'dmy') { var parts = value.split('-'); return parts[2] + '.' + parts[1] + '.' + parts[0]; }
	  return value;
	},

	conditionDocumentPicker: function (row) {
	  var self = this;
	  var field = row.querySelector('[data-cf="condition_field_id"]');
	  var option = field && field.options[field.selectedIndex];
	  var overlay = document.createElement('div');
	  overlay.className = 'overlay request-condition-picker-overlay';
	  overlay.innerHTML = '<div class="modal picker-modal request-condition-picker" role="dialog" aria-modal="true" aria-labelledby="requestConditionPickerTitle">'
		+ '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div><h3 id="requestConditionPickerTitle">Выбрать документ</h3><p class="text-secondary">Поиск по ID, названию или адресу.</p></div><button class="modal-close" type="button" data-condition-picker-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
		+ '<div class="modal-body"><div class="input-wrap request-condition-picker-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="Начните вводить название" data-condition-picker-search></div><div class="request-condition-picker-status" data-condition-picker-status>Загрузка...</div><div class="request-condition-picker-list" data-condition-picker-list></div></div>'
		+ '<div class="modal-footer"><div class="mf-left" data-condition-picker-count></div><button class="btn btn-ghost" type="button" data-condition-picker-close>Закрыть</button></div></div>';
	  document.body.appendChild(overlay);
	  requestAnimationFrame(function () { overlay.classList.add('show'); });
	  var search = overlay.querySelector('[data-condition-picker-search]');
	  var list = overlay.querySelector('[data-condition-picker-list]');
	  var status = overlay.querySelector('[data-condition-picker-status]');
	  var count = overlay.querySelector('[data-condition-picker-count]');
	  var close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); };
	  var load = function () {
		var params = new URLSearchParams(); params.set('q', search.value.trim()); params.set('limit', '30');
		if (option && option.getAttribute('data-cond-rubrics')) { params.set('rubric_id', option.getAttribute('data-cond-rubrics')); }
		status.hidden = false; status.textContent = 'Загрузка...'; list.innerHTML = '';
		fetch(self.base() + '/requests/documents/picker?' + params.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
		  .then(function (response) { return response.json(); })
		  .then(function (payload) {
			var items = payload && payload.data && payload.data.items ? payload.data.items : [];
			items.forEach(function (item) {
			  var button = document.createElement('button'); button.type = 'button'; button.className = 'request-condition-picker-item'; button.setAttribute('data-condition-picker-item', String(item.id));
			  var main = document.createElement('span'); main.innerHTML = '<b></b><small></small>'; main.querySelector('b').textContent = item.title || 'Без названия'; main.querySelector('small').textContent = '/' + String(item.alias || '').replace(/^\/+/, '');
			  var id = document.createElement('span'); id.className = 'request-condition-picker-id'; id.textContent = '#' + item.id;
			  var badge = document.createElement('span'); badge.className = 'badge badge-blue'; badge.textContent = item.rubric_title || ('Рубрика #' + item.rubric_id);
			  button.appendChild(id); button.appendChild(main); button.appendChild(badge); list.appendChild(button);
			});
			status.hidden = items.length > 0; status.textContent = search.value.trim() ? 'Ничего не найдено' : 'Документы не найдены'; count.textContent = items.length ? 'Найдено: ' + items.length : '';
		  }).catch(function () { status.hidden = false; status.textContent = 'Не удалось загрузить документы'; });
	  };
	  var timer = null;
	  search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 220); });
	  overlay.addEventListener('click', function (event) {
		if (event.target === overlay || event.target.closest('[data-condition-picker-close]')) { close(); return; }
		var item = event.target.closest('[data-condition-picker-item]');
		if (!item) { return; }
		var storage = row.querySelector('[data-condition-literal-storage]'); if (storage) { storage.value = item.getAttribute('data-condition-picker-item'); }
		self.conditionSmartControl(row, option); self.conditionValueRefresh(row.querySelector('[data-condition-value]')); close();
	  });
	  load(); search.focus();
	},

	conditionValueRefresh: function (editor, trigger) {
	  if (!editor) { return; }
	  var row = editor.closest('tr');
	  var source = editor.querySelector('[data-cf="condition_value_source"]');
	  var literal = editor.querySelector('[data-condition-literal]');
	  var input = editor.querySelector('[data-condition-input]');
	  var key = editor.querySelector('[data-cf="condition_value_key"]');
	  var mode = editor.querySelector('[data-cf="condition_value_mode"]');
	  var constant = editor.querySelector('[data-condition-constant]');
	  var tag = editor.querySelector('[data-condition-tag]');
	  var technical = editor.querySelector('[data-condition-technical]');
	  var literalLabel = editor.querySelector('[data-condition-literal-label]');
	  var preview = editor.querySelector('[data-condition-preview-text]');
	  var isInput = source && source.value === 'input';
	  if (literal) { literal.hidden = isInput; }
	  if (input) { input.hidden = !isInput; }
	  if (constant) { constant.hidden = !mode || mode.value !== 'presence_constant'; }
	  if (technical) { technical.hidden = !isInput; }
	  if (literalLabel) { literalLabel.textContent = source && source.value === 'legacy' ? 'Сохранённый PHP-код' : 'Значение для сравнения'; }
	  if (isInput && key && !key.value && trigger && trigger.matches('[data-cf="condition_value_source"], [data-cf="condition_field_id"]')) {
		var field = row && row.querySelector('[data-cf="condition_field_id"]');
		var option = field && field.options[field.selectedIndex];
		key.value = option ? (option.getAttribute('data-cond-alias') || ('field_' + option.value)) : '';
	  }
	  if (tag) { tag.textContent = key && key.value ? '[tag:cond:' + key.value.trim() + ']' : '[tag:cond:alias]'; }
	  if (preview) { preview.textContent = this.conditionValuePreview(row, source, key, mode); }
	},

	conditionValuePreview: function (row, source, key, mode) {
	  var field = row && row.querySelector('[data-cf="condition_field_id"]');
	  var compare = row && row.querySelector('[data-cf="condition_compare"]');
	  var literal = row && row.querySelector('[data-cf="condition_value"]');
	  var constant = row && row.querySelector('[data-cf="condition_value_constant"]');
	  var fieldText = field && field.options[field.selectedIndex] ? field.options[field.selectedIndex].textContent.trim() : 'выбранное поле';
	  fieldText = fieldText.replace(/^#\d+\s*·\s*/, '').replace(/\s*·\s*#\d+$/, '');
	  var compareLabels = {'==':'равно','!=':'не равно','%%':'содержит','%':'начинается с','--':'не содержит','!-':'не начинается с','>':'больше','<':'меньше','>=':'больше или равно','<=':'меньше или равно','N>':'больше','N<':'меньше','N>=':'больше или равно','N<=':'меньше или равно','IN=':'входит в список','NOTIN=':'не входит в список','ANY':'совпадает с одним из значений','FRE':'проверяется свободным условием'};
	  var relation = compare && compareLabels[compare.value] ? compareLabels[compare.value] : 'сравнивается со значением';
	  var sourceValue = source ? source.value : 'literal';
	  if (sourceValue === 'legacy') {
		return 'Поле «' + fieldText + '» ' + relation + ' результатом сохранённого PHP-кода.';
	  }
	  if (sourceValue !== 'input') {
		var fixed = literal ? literal.value.trim() : '';
		return 'Поле «' + fieldText + '» ' + relation + ' фиксированное значение' + (fixed ? ' «' + fixed + '».' : '.');
	  }

	  var parameter = key && key.value.trim() ? key.value.trim() : 'параметр';
	  var modeValue = mode ? mode.value : 'string_scalar';
	  var valueDescription = 'значением параметра «' + parameter + '»';
	  if (modeValue === 'integer_scalar') { valueDescription = 'целым числом из параметра «' + parameter + '»'; }
	  else if (modeValue === 'decimal_scalar') { valueDescription = 'десятичным числом из параметра «' + parameter + '»'; }
	  else if (modeValue === 'string_list' || modeValue === 'integer_list') { valueDescription = 'одним из значений параметра «' + parameter + '»'; }
	  else if (modeValue === 'integer_range' || modeValue === 'decimal_range') { valueDescription = 'диапазоном min/max из параметра «' + parameter + '»'; }
	  else if (modeValue === 'integer_member') { valueDescription = 'значение параметра «' + parameter + '» в формате |значение|'; }
	  else if (modeValue === 'integer_member_list') { valueDescription = 'одним из значений параметра «' + parameter + '» в формате |значение|'; }
	  else if (modeValue === 'presence_constant') {
		var constantValue = constant ? constant.value.trim() : '';
		valueDescription = 'значением «' + (constantValue || 'укажите константу') + '», когда параметр «' + parameter + '» заполнен';
	  }

	  return 'Поле «' + fieldText + '» ' + relation + ' ' + valueDescription + '. Пустой параметр не участвует в фильтрации.';
	},

    condSave: function (row) {
      var reqId = this.condTable.getAttribute('data-req-id');
      var fd = new FormData();
      fd.append('cond_id', row.getAttribute('data-cond-id') || '0');
      fd.append('condition_group_id', row.closest('[data-cond-body]').getAttribute('data-group-id') || '0');
      row.querySelectorAll('[data-cf]').forEach(function (el) {
        fd.append(el.getAttribute('data-cf'), el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value);
      });
      Adminx.Loader.show();
      Adminx.Ajax.post(this.base() + '/requests/' + reqId + '/conditions', fd).then(function (p) {
        Adminx.Loader.hide();
        var d = p.data || {};
        if (d.success) {
          if (d.data && d.data.id) { row.setAttribute('data-cond-id', d.data.id); row.removeAttribute('data-cond-new'); }
          Adminx.Toast.show(d.message || 'Сохранено', 'success');
        } else { Adminx.Toast.show(d.message || 'Ошибка', 'error'); }
      }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
    },

    condDelete: function (row) {
      var self = this;
      var condId = row.getAttribute('data-cond-id') || '0';
      if (condId === '0') { row.remove(); this.normalizeEmptyGroups(); return; }
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить условие?', confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/requests/conditions/' + condId + '/delete').then(function (p) {
            Adminx.Loader.hide();
            var d = p.data || {};
            if (d.success) { row.remove(); self.normalizeEmptyGroups(); Adminx.Toast.show(d.message, 'success'); }
            else { Adminx.Toast.show(d.message || 'Ошибка', 'error'); }
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    groupOperator: function (button) {
      var group = button.closest('[data-cond-group]');
      var fd = new FormData();
      fd.append('group_id', group.getAttribute('data-group-id'));
      fd.append('parent_id', group.getAttribute('data-parent-id') || '0');
      fd.append('group_operator', button.getAttribute('data-group-operator'));
      var title = group.querySelector(':scope > .req-condition-group-head [data-group-title]');
      fd.append('group_title', title ? title.value : '');
      this.saveGroup(fd, function () {
        group.querySelectorAll(':scope > .req-condition-group-head [data-group-operator]').forEach(function (item) {
          item.classList.toggle('is-active', item === button);
        });
        var hint = group.querySelector(':scope > .req-condition-group-head .req-condition-group-title span:last-child');
        if (hint) { hint.textContent = button.getAttribute('data-group-operator') === 'AND' ? 'Должны выполняться все элементы внутри' : 'Достаточно любого элемента внутри'; }
      });
    },

    groupAdd: function (parent) {
      var self = this;
      var fd = new FormData();
      fd.append('parent_id', parent.getAttribute('data-group-id'));
      fd.append('group_operator', 'AND');
      fd.append('group_title', '');
      this.saveGroup(fd, function (data) {
        var tpl = document.querySelector('[data-group-tpl]');
        if (!tpl || !data.id) { return; }
        var group = tpl.content.firstElementChild.cloneNode(true);
        var depth = parseInt(parent.style.getPropertyValue('--condition-depth') || '0', 10) + 1;
        group.setAttribute('data-group-id', data.id);
        group.setAttribute('data-parent-id', parent.getAttribute('data-group-id'));
        self.setGroupDepth(group, depth);
        group.querySelector('[data-cond-body]').setAttribute('data-group-id', data.id);
        parent.querySelector(':scope > [data-group-children]').appendChild(group);
        Adminx.Tooltip && Adminx.Tooltip.init && Adminx.Tooltip.init(group);
      });
    },

    groupTitle: function (input) {
      var group = input.closest('[data-cond-group]');
      var active = group.querySelector(':scope > .req-condition-group-head [data-group-operator].is-active');
      var fd = new FormData();
      fd.append('group_id', group.getAttribute('data-group-id'));
      fd.append('parent_id', group.getAttribute('data-parent-id') || '0');
      fd.append('group_operator', active ? active.getAttribute('data-group-operator') : 'AND');
      fd.append('group_title', input.value.trim());
      this.saveGroup(fd);
    },

    setGroupDepth: function (group, depth) {
      group.style.setProperty('--condition-depth', depth);
      var add = group.querySelector(':scope > .req-condition-group-head [data-group-add]');
      if (add && depth >= 3) { add.remove(); }
      group.querySelectorAll(':scope > [data-group-children] > [data-cond-group]').forEach(function (child) {
        Adminx.Requests.setGroupDepth(child, depth + 1);
      });
    },

    normalizeEmptyGroups: function () {
      if (!this.condTable) { return; }
      this.condTable.querySelectorAll('[data-cond-body]').forEach(function (body) {
        var rows = body.querySelectorAll(':scope > tr[data-cond-id]');
        var empty = body.querySelector(':scope > [data-cond-empty]');
        if (rows.length && empty) { empty.remove(); }
        if (!rows.length && !empty) {
          var row = document.createElement('tr');
          row.setAttribute('data-cond-empty', '');
          row.innerHTML = '<td colspan="6" class="ax-empty">В этой группе пока нет условий</td>';
          body.appendChild(row);
        }
      });
    },

    saveGroup: function (fd, done) {
      var reqId = this.condTable.getAttribute('data-req-id');
      Adminx.Ajax.post(this.base() + '/requests/' + reqId + '/condition-groups', fd).then(function (p) {
        var d = p.data || {};
        if (!d.success) { Adminx.Toast.show(d.message || 'Не удалось сохранить группу', 'error'); return; }
        if (done) { done(d.data || {}); }
        Adminx.Toast.show(d.message || 'Группа сохранена', 'success');
      }).catch(function () { Adminx.Toast.show('Ошибка сети', 'error'); });
    },

    groupDelete: function (group) {
      var self = this;
      Adminx.Confirm.open({
        kind: 'error', title: 'Удалить группу?',
        message: 'Условия и вложенные группы будут перенесены на уровень выше.',
        confirmLabel: 'Удалить', confirmClass: 'btn-danger',
        onConfirm: function () {
          var reqId = self.condTable.getAttribute('data-req-id');
          var parent = group.parentNode.closest('[data-cond-group]');
          Adminx.Ajax.post(self.base() + '/requests/' + reqId + '/condition-groups/' + group.getAttribute('data-group-id') + '/delete').then(function (p) {
            var d = p.data || {};
            if (!d.success) { Adminx.Toast.show(d.message || 'Не удалось удалить группу', 'error'); return; }
            var parentBody = parent.querySelector(':scope > .req-condition-table-wrap [data-cond-body]');
            var parentEmpty = parentBody.querySelector(':scope > [data-cond-empty]');
            if (parentEmpty) { parentEmpty.remove(); }
            group.querySelectorAll(':scope > .req-condition-table-wrap tbody > tr[data-cond-id]').forEach(function (row) { parentBody.appendChild(row); });
            var parentChildren = parent.querySelector(':scope > [data-group-children]');
            group.querySelectorAll(':scope > [data-group-children] > [data-cond-group]').forEach(function (child) {
              child.setAttribute('data-parent-id', parent.getAttribute('data-group-id'));
              self.setGroupDepth(child, parseInt(parent.style.getPropertyValue('--condition-depth') || '0', 10) + 1);
              parentChildren.appendChild(child);
            });
            group.remove();
            self.normalizeEmptyGroups();
            Adminx.Toast.show(d.message, 'success');
          }).catch(function () { Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    // ---- drag-and-drop сортировка условий ----
    dragStart: function (e) {
      var handle = e.target.closest('[data-cond-drag]');
      if (!handle) { return; }
      this.dragRow = handle.closest('tr');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
      var row = this.dragRow;
      setTimeout(function () { row.classList.add('cond-dragging'); }, 0);
    },

    dragOver: function (e) {
      if (!this.dragRow) { return; }
      e.preventDefault();
      var row = e.target.closest('tr');
      var targetBody = e.target.closest('[data-cond-body]');
      if (!targetBody) { return; }
      var empty = targetBody.querySelector(':scope > [data-cond-empty]');
      if (empty) { empty.remove(); }
      if (!row || row === this.dragRow || !row.hasAttribute('data-cond-id')) {
        targetBody.appendChild(this.dragRow);
        return;
      }
      var rect = row.getBoundingClientRect();
      var body = row.parentNode;
      if (e.clientY > rect.top + rect.height / 2) { body.insertBefore(this.dragRow, row.nextSibling); }
      else { body.insertBefore(this.dragRow, row); }
    },

    drop: function (e) { if (this.dragRow) { e.preventDefault(); } },

    dragEnd: function () {
      if (!this.dragRow) { return; }
      this.dragRow.classList.remove('cond-dragging');
      this.dragRow = null;
      this.normalizeEmptyGroups();
      this.persistOrder();
    },

    persistOrder: function () {
      var reqId = this.condTable.getAttribute('data-req-id');
      var items = [];
      this.condTable.querySelectorAll('[data-cond-body] tr[data-cond-id]').forEach(function (r) {
        var id = r.getAttribute('data-cond-id');
        if (id && id !== '0') { items.push({ id: id, group_id: r.closest('[data-cond-body]').getAttribute('data-group-id') }); }
      });
      if (!items.length) { return; }
      var fd = new FormData();
      items.forEach(function (item, index) {
        fd.append('items[' + index + '][id]', item.id);
        fd.append('items[' + index + '][group_id]', item.group_id);
      });
      Adminx.Ajax.post(this.base() + '/requests/' + reqId + '/conditions/reorder', fd).then(function (p) {
        var d = p.data || {};
        Adminx.Toast.show(d.success ? 'Порядок сохранён' : (d.message || 'Ошибка'), d.success ? 'success' : 'error');
      }).catch(function () { Adminx.Toast.show('Ошибка сети', 'error'); });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Requests.init(); });
  } else {
    Adminx.Requests.init();
  }
})(window, document);
