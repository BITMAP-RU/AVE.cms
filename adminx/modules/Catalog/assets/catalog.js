(function (window, document) {
  'use strict';
  var Adminx = window.Adminx || (window.Adminx = {});
  Adminx.Catalog = {
    itemForm: null, settingsForm: null, dragItem: null, dragGroup: [], dragPlaceholder: null, dragGhost: null, dragPointerId: null, dragStartX: 0, dragStartY: 0, dragStarted: false, dragOrderSnapshot: '', orderSaving: false, filterOrder: [], sourceItemIds: [], sourceOptionsData: null, itemSaveTimer: null, settingsSaveTimer: null, conditionContext: null,
    init: function () {
      this.itemForm = document.getElementById('catalogItemForm');
      this.settingsForm = document.getElementById('catalogSettingsForm');
      var self = this;
      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-product-role-refresh]')) { self.refreshRolePreview(false); return; }
        if (e.target.closest('[data-product-selection-check]')) { self.refreshRolePreview(true); return; }
        var pageTab = e.target.closest('[data-catalog-page-tab]');
        if (pageTab) { self.tab('[data-catalog-page-tab]', '[data-catalog-page-panel]', pageTab.getAttribute('data-catalog-page-tab')); }
        var drawerTab = e.target.closest('[data-catalog-drawer-tab]');
        if (drawerTab) { self.tab('[data-catalog-drawer-tab]', '[data-catalog-drawer-panel]', drawerTab.getAttribute('data-catalog-drawer-tab')); }
        if (e.target.closest('[data-catalog-item-new]')) { self.newItem(0); }
        var indent = e.target.closest('[data-catalog-item-indent]');
        if (indent) { self.changeItemLevel(indent.closest('[data-catalog-item]'), parseInt(indent.getAttribute('data-catalog-item-indent'), 10) || 0); return; }
        var sibling = e.target.closest('[data-catalog-item-sibling]');
        if (sibling) { var siblingNode = sibling.closest('[data-catalog-item]'); self.newItem(Number(siblingNode.getAttribute('data-parent-id')) || 0); return; }
        var child = e.target.closest('[data-catalog-item-child]');
        if (child) { self.newItem(Number(child.getAttribute('data-catalog-item-child')) || 0); return; }
        var edit = e.target.closest('[data-catalog-item-edit]');
        if (edit) { self.editItem(edit.getAttribute('data-catalog-item-edit')); return; }
        var del = e.target.closest('[data-catalog-item-delete]');
        if (del) { self.deleteItem(del.getAttribute('data-catalog-item-delete')); return; }
        var treeRow = e.target.closest('[data-catalog-builder-item]');
        if (treeRow && !e.target.closest('button, input, label, a, select')) { self.editItem(treeRow.getAttribute('data-catalog-builder-item')); return; }
        if (e.target.closest('[data-catalog-document-pick]')) { self.openDocumentPicker(); }
        if (e.target.closest('[data-catalog-document-clear]')) { self.setDocument(null); self.scheduleItemSave(); }
        if (e.target.closest('[data-catalog-source-open]')) { self.openSourcePicker(); }
        if (e.target.closest('[data-catalog-source-clear]')) { self.setSources([]); self.scheduleItemSave(); }
        var conditionView = e.target.closest('[data-catalog-condition-view]');
        if (conditionView) { self.showCondition(conditionView.getAttribute('data-catalog-condition-view')); }
        var conditionSync = e.target.closest('[data-catalog-condition-sync]');
        if (conditionSync) { self.syncCondition(conditionSync.getAttribute('data-catalog-condition-sync')); }
        if (e.target.closest('[data-catalog-conditions-sync]')) { self.syncMissingConditions(); }
        if (e.target.closest('[data-catalog-filter-order]')) { self.openFilterOrder(); }
        if (e.target.closest('[data-catalog-filter-recompile]')) { self.recompileCurrent(); }
        if (e.target.closest('[data-catalog-recompile-all]')) { self.recompileAll(); }
      });
      document.addEventListener('input', function (e) {
        if (e.target.closest('[data-product-role-preview]')) { self.selectionChanged(); return; }
        if (e.target.matches('[data-catalog-tree-search]')) { self.search(e.target.value); }
      });
      document.addEventListener('change', function (e) {
        if (e.target.closest('[data-product-role-preview]')) { self.selectionChanged(); return; }
        if (e.target.matches('[data-catalog-item-status]')) { self.setTreeStatus(e.target); return; }
        if (self.itemForm && e.target.closest('#catalogItemForm')) {
          if (e.target.matches('[name="filters_use[]"]')) { self.syncFilterOrderSelection(); }
          self.updateCounts();
          if (e.target.matches('[name="status"], [name="parent_id"], [name="fields_use[]"], [name="filters_use[]"], [name^="filter_style["]')) { self.scheduleItemSave(); }
          return;
        }
        if (self.settingsForm && e.target.closest('#catalogSettingsForm')) { self.updateSettingsGroups(); self.updateCommerceSettings(); self.scheduleSettingsSave(); }
      });
      if (this.itemForm) { this.itemForm.addEventListener('submit', function (e) { e.preventDefault(); self.saveItem(false); }); }
      if (this.settingsForm) { this.settingsForm.addEventListener('submit', function (e) { e.preventDefault(); self.saveSettings(false); }); this.updateSettingsGroups(); this.updateCommerceSettings(); }
      var createForm = document.querySelector('[data-catalog-create]');
      if (createForm) { createForm.addEventListener('submit', function (e) { e.preventDefault(); self.createCatalog(createForm); }); }
      document.addEventListener('pointerdown', function (e) { self.treeDragStart(e); });
      document.addEventListener('pointermove', function (e) { self.treeDragOver(e); });
      document.addEventListener('pointerup', function (e) { self.treeDrop(e); });
      document.addEventListener('pointercancel', function () { self.treeDragEnd(); });
      this.normalizeTreeHierarchy();
			this.openRequestedItem();
      if (window.location.hash === '#settings') { this.tab('[data-catalog-page-tab]', '[data-catalog-page-panel]', 'settings'); }
    },
    selectionChanged: function () {
      this.rolePreviewSequence = (this.rolePreviewSequence || 0) + 1;
      var root = document.querySelector('[data-product-role-preview]');
      if (!root) { return; }
      var context = root.querySelector('[data-selection="context"]');
      root.querySelectorAll('[data-selection-context]').forEach(function (el) { el.hidden = !context || el.getAttribute('data-selection-context') !== context.value; });
      var result = root.querySelector('[data-selection-result]'), stale = root.querySelector('[data-selection-stale]');
      if (result) { result.hidden = true; if (stale) { stale.hidden = false; } }
    },
    refreshRolePreview: function (check) {
      var input = document.querySelector('[data-product-role-document]');
      if (!input || !input.checkValidity()) { if (input) { input.reportValidity(); } return; }
      var root = input.closest('[data-product-role-preview]');
      var invalid = Array.prototype.find.call(root.querySelectorAll('[data-selection-context]:not([hidden]) input'), function (el) { return !el.checkValidity(); });
      if (invalid) { invalid.reportValidity(); return; }
      var self = this, sequence = (this.rolePreviewSequence || 0) + 1;
      var errorMessage = root.getAttribute('data-error');
      this.rolePreviewSequence = sequence;
      var url = new URL(this.url() + '/product-preview', window.location.origin);
      url.searchParams.set('product_preview', input.value || '0');
      url.searchParams.set('selection_check', check === true ? '1' : '0');
      root.querySelectorAll('[data-selection]').forEach(function (el) { url.searchParams.set('selection_' + el.getAttribute('data-selection'), el.value); });
      root.setAttribute('aria-busy', 'true');
      root.querySelectorAll('button').forEach(function (el) { el.disabled = true; });
      fetch(url.toString(), { credentials: 'same-origin' }).then(function (response) {
        if (!response.ok) { throw new Error(errorMessage); }
        return response.text();
      }).then(function (html) {
        if (sequence !== self.rolePreviewSequence) { return; }
        var preview = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-product-role-preview]');
        var current = document.querySelector('[data-product-role-preview]');
        if (!preview || !current) { throw new Error(errorMessage); }
        current.replaceWith(preview);
      }).catch(function (error) { if (sequence === self.rolePreviewSequence) { Adminx.Toast.show(error.message, 'error'); } }).finally(function () {
        root.removeAttribute('aria-busy'); root.querySelectorAll('button').forEach(function (el) { el.disabled = false; });
      });
    },
    base: function () { return (this.itemForm || this.settingsForm).getAttribute('data-base'); },
    rubric: function () { return (this.itemForm || this.settingsForm).getAttribute('data-rubric'); },
    field: function () { return (this.itemForm || this.settingsForm).getAttribute('data-field'); },
    url: function () { return this.base() + '/catalog/' + this.rubric() + '/' + this.field(); },
    csrf: function () { var el = (this.itemForm || this.settingsForm).querySelector('[name="_csrf"]'); return el ? el.value : ''; },
    createCatalog: function (form) {
      Adminx.Loader.show();
      fetch(form.getAttribute('data-base') + '/catalog', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(this.json).then(function (payload) { Adminx.Toast.show(payload.message || 'Каталог создан', 'success'); window.location.href = payload.redirect || (payload.data && payload.data.redirect); })
        .catch(this.error).finally(function () { Adminx.Loader.hide(); });
    },
    tab: function (tabs, panels, value) {
      document.querySelectorAll(tabs).forEach(function (el) { var active = el.getAttribute(tabs.indexOf('drawer') >= 0 ? 'data-catalog-drawer-tab' : 'data-catalog-page-tab') === value; el.classList.toggle('is-active', active); el.setAttribute('aria-selected', active ? 'true' : 'false'); });
      document.querySelectorAll(panels).forEach(function (el) { el.hidden = el.getAttribute(panels.indexOf('drawer') >= 0 ? 'data-catalog-drawer-panel' : 'data-catalog-page-panel') !== value; });
    },
    newItem: function (parentId) {
      this.renderContextLinks([]);
      this.itemForm.reset(); this.itemForm.querySelector('[name="id"]').value = ''; this.itemForm.querySelector('[name="status"]').checked = true;
      this.itemForm.querySelectorAll('[name="parent_id"] option').forEach(function (option) { option.disabled = false; });
      var parent = this.itemForm.querySelector('[name="parent_id"]');
      if (parent) { parent.value = String(Number(parentId) || 0); }
      var defaultFields = this.settingsValues('fields_default[]'), defaultFilters = this.settingsValues('filters_default[]');
      this.checkValues('fields_use[]', defaultFields); this.checkValues('filters_use[]', defaultFilters);
      defaultFilters.forEach(function (id) { var source = this.settingsForm.querySelector('[name="filter_style[' + id + ']"]'), target = this.itemForm.querySelector('[name="filter_style[' + id + ']"]'); if (source && target) { target.value = source.value; } }, this);
      this.filterOrder = defaultFilters.slice(); this.syncFilterOrderInput();
      this.setDocument(null); this.setSources([]); this.setState('item', ''); this.renderConditionContext(null);
      document.querySelector('[data-catalog-drawer-title]').textContent = 'Новый раздел'; this.tab('[data-catalog-drawer-tab]', '[data-catalog-drawer-panel]', 'main'); this.updateCounts();
      Adminx.Drawer.open('catalogItemDrawer');
    },
    settingsValues: function (name) { return this.settingsForm ? Array.prototype.map.call(this.settingsForm.querySelectorAll('[name="' + name + '"]:checked'), function (input) { return String(input.value); }) : []; },
    updateSettingsGroups: function () { if (!this.settingsForm) { return; } this.settingsForm.querySelectorAll('[data-catalog-settings-group]').forEach(function (group) { var name = group.getAttribute('data-catalog-settings-group'), selected = group.querySelectorAll('[name="' + name + '"]:checked').length, badge = group.querySelector('[data-catalog-settings-selected]'); group.querySelectorAll('.catalog-choice-row, .catalog-filter-row').forEach(function (row) { var input = row.querySelector('[name="' + name + '"]'); row.classList.toggle('is-selected', !!input && input.checked); }); if (badge) { badge.textContent = 'Включено: ' + selected; badge.classList.toggle('badge-blue', selected > 0); badge.classList.toggle('badge-gray', selected === 0); } }); },
    updateCommerceSettings: function () { if (!this.settingsForm) { return; } var purpose = this.settingsForm.querySelector('[name="purpose"]'), visible = purpose && purpose.value === 'commerce'; this.settingsForm.querySelectorAll('[data-catalog-commerce-fields]').forEach(function (section) { section.hidden = !visible; }); },
    editItem: function (id, requestedTab) {
      var self = this; this.markSelectedItem(id); Adminx.Loader.show();
      fetch(this.base() + '/catalog/items/' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(this.json).then(function (payload) {
        self.fill(payload.data || {}); Adminx.Drawer.open('catalogItemDrawer');
				if (['main', 'fields', 'filters'].indexOf(requestedTab) >= 0) { self.tab('[data-catalog-drawer-tab]', '[data-catalog-drawer-panel]', requestedTab); }
      }).catch(this.error).finally(function () { Adminx.Loader.hide(); });
    },
		openRequestedItem: function () {
			if (!this.itemForm || typeof URLSearchParams === 'undefined') { return; }
			var params = new URLSearchParams(window.location.search), itemId = params.get('item');
			if (!itemId || !/^\d+$/.test(itemId)) { return; }
			this.editItem(itemId, params.get('tab') || 'main');
		},
    fill: function (item) {
      this.renderContextLinks(item.context_links || []);
      this.itemForm.reset(); this.itemForm.querySelector('[name="id"]').value = item.id || ''; this.itemForm.querySelector('[name="name"]').value = item.name || '';
      this.itemForm.querySelectorAll('[name="parent_id"] option').forEach(function (option) { option.disabled = false; });
      this.itemForm.querySelector('[name="parent_id"]').value = item.parent_id || 0; this.itemForm.querySelector('[name="status"]').checked = Number(item.status) === 1;
      this.setDocument(item.document_id ? { id: item.document_id, title: item.document_title || '', alias: item.document_alias || '' } : null);
      this.setSources(item.source_item_ids || []);
      this.filterOrder = (item.filters_use || []).map(function (id) { return String(id); }); this.syncFilterOrderInput(); this.checkValues('fields_use[]', item.fields_use || []); this.checkValues('filters_use[]', item.filters_use || []);
      Object.keys(item.filter_styles || {}).forEach(function (id) { var select = this.itemForm.querySelector('[name="filter_style[' + id + ']"]'); if (select) { select.value = item.filter_styles[id]; } }, this);
      var own = this.itemForm.querySelector('[name="parent_id"] option[value="' + item.id + '"]'); if (own) { own.disabled = true; }
      var treeItem = document.querySelector('[data-catalog-item][data-id="' + item.id + '"]');
      this.treeBranch(treeItem).slice(1).forEach(function (child) {
        var option = this.itemForm.querySelector('[name="parent_id"] option[value="' + child.getAttribute('data-id') + '"]');
        if (option) { option.disabled = true; }
      }, this);
      this.renderConditionContext(item.condition_context || null);
      document.querySelector('[data-catalog-drawer-title]').textContent = item.name || 'Раздел каталога'; this.markSelectedItem(item.id); this.tab('[data-catalog-drawer-tab]', '[data-catalog-drawer-panel]', 'main'); this.updateCounts(); this.setState('item', '');
    },
    checkValues: function (name, values) { var map = {}; values.forEach(function (id) { map[String(id)] = true; }); this.itemForm.querySelectorAll('[name="' + name + '"]').forEach(function (el) { el.checked = !!map[el.value]; }); },
    updateCounts: function () { ['fields', 'filters'].forEach(function (type) { var count = this.itemForm.querySelectorAll('[name="' + type + '_use[]"]:checked').length; var el = document.querySelector('[data-catalog-' + type + '-count]'); if (el) { el.textContent = count; } this.itemForm.querySelectorAll('[name="' + type + '_use[]"]').forEach(function (toggle) { var row = toggle.closest('.catalog-choice-row, .catalog-filter-row'); if (row) { row.classList.toggle('is-selected', toggle.checked); } }); this.itemForm.querySelectorAll('[data-catalog-field-group="' + type + '"]').forEach(function (group) { var selected = group.querySelectorAll('[name="' + type + '_use[]"]:checked').length; var badge = group.querySelector('[data-catalog-group-selected]'); if (badge) { badge.textContent = 'Включено: ' + selected; badge.classList.toggle('badge-blue', selected > 0); badge.classList.toggle('badge-gray', selected === 0); } }); }, this); var orderButton = this.itemForm.querySelector('[data-catalog-filter-order]'); if (orderButton) { orderButton.disabled = this.filterOrder.length < 2; } this.renderConditionContext(this.conditionContext); },
    syncFilterOrderSelection: function () { var selected = {}, next = []; this.itemForm.querySelectorAll('[name="filters_use[]"]:checked').forEach(function (input) { selected[input.value] = true; }); this.filterOrder.forEach(function (id) { if (selected[id]) { next.push(id); delete selected[id]; } }); this.itemForm.querySelectorAll('[name="filters_use[]"]:checked').forEach(function (input) { if (selected[input.value]) { next.push(input.value); delete selected[input.value]; } }); this.filterOrder = next; this.syncFilterOrderInput(); },
    syncFilterOrderInput: function () { var input = this.itemForm && this.itemForm.querySelector('[name="filters_order"]'); if (input) { input.value = this.filterOrder.join(','); } },
    openFilterOrder: function () { var self = this; this.syncFilterOrderSelection(); if (this.filterOrder.length < 2) { return; } var overlay = document.createElement('div'), rows = ''; this.filterOrder.forEach(function (id) { var input = self.itemForm.querySelector('[name="filters_use[]"][value="' + id + '"]'), row = input.closest('.catalog-filter-row'), title = row.querySelector('.catalog-choice-copy b').textContent.trim(), type = row.querySelector('.catalog-field-type b').textContent.trim(); rows += '<div class="catalog-filter-order-row" data-filter-order-id="' + self.esc(id) + '"><button class="catalog-filter-order-handle" type="button" draggable="true" aria-label="Перетащить"><i class="ti ti-grip-vertical"></i></button><span><b>' + self.esc(title) + '</b><small>' + self.esc(type) + ' · #' + self.esc(id) + '</small></span></div>'; }); overlay.className = 'overlay catalog-filter-order-overlay'; overlay.innerHTML = '<div class="modal catalog-filter-order-modal" role="dialog" aria-modal="true"><div class="modal-header"><span class="dialog-icon info"><i class="ti ti-arrows-sort"></i></span><div><h3>Порядок фильтров</h3><p class="text-secondary">' + this.filterOrder.length + ' включённых фильтров.</p></div><button class="modal-close" type="button" data-filter-order-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div><div class="modal-body"><div class="catalog-filter-order-list">' + rows + '</div></div><div class="modal-footer"><button class="btn btn-ghost" type="button" data-filter-order-close>Закрыть</button><button class="btn btn-primary" type="button" data-filter-order-save><i class="ti ti-device-floppy"></i>Сохранить порядок</button></div></div>'; document.body.appendChild(overlay); requestAnimationFrame(function () { overlay.classList.add('show'); }); var dragged = null, close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); }; overlay.addEventListener('dragstart', function (e) { var handle = e.target.closest('.catalog-filter-order-handle'), row = handle ? handle.closest('[data-filter-order-id]') : null; if (!row) { e.preventDefault(); return; } dragged = row; row.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', row.getAttribute('data-filter-order-id')); }); overlay.addEventListener('dragover', function (e) { var target = e.target.closest('[data-filter-order-id]'); if (!dragged || !target || target === dragged) { return; } e.preventDefault(); var rect = target.getBoundingClientRect(), reference = e.clientY < rect.top + rect.height / 2 ? target : target.nextSibling; if (reference !== dragged && dragged.nextSibling !== reference) { target.parentNode.insertBefore(dragged, reference); } }); overlay.addEventListener('dragend', function () { if (dragged) { dragged.classList.remove('is-dragging'); dragged = null; } }); overlay.addEventListener('click', function (e) { if (e.target === overlay || e.target.closest('[data-filter-order-close]')) { close(); return; } if (e.target.closest('[data-filter-order-save]')) { self.filterOrder = Array.prototype.map.call(overlay.querySelectorAll('[data-filter-order-id]'), function (row) { return row.getAttribute('data-filter-order-id'); }); self.syncFilterOrderInput(); self.scheduleItemSave(); close(); } }); },
    renderContextLinks: function (links) {
      var panel = this.itemForm && this.itemForm.querySelector('[data-catalog-context-links]');
      if (!panel) { return; }
      var list = panel.querySelector('nav');
      list.textContent = '';
      (links || []).forEach(function (link) {
        var anchor = document.createElement('a'), icon = document.createElement('i'), label = document.createElement('span');
        anchor.href = link.url; anchor.target = '_blank'; anchor.rel = 'noopener'; anchor.setAttribute('data-context-kind', link.kind);
        icon.className = 'ti ti-external-link'; icon.setAttribute('aria-hidden', 'true'); label.textContent = link.label;
        if (link.scope) { var scope = document.createElement('small'); scope.textContent = link.scope; label.appendChild(scope); }
        anchor.appendChild(icon); anchor.appendChild(label); list.appendChild(anchor);
      });
      panel.hidden = !list.children.length;
    },
    renderConditionContext: function (context) {
      this.conditionContext = context || { request: { id: 0, title: '' }, states: {}, summary: { synced: 0, staged: 0, missing: 0, different: 0, unsupported: 0 } };
      var request = this.conditionContext.request || {}, summary = this.conditionContext.summary || {}, requestEl = document.querySelector('[data-catalog-condition-request]'), summaryEl = document.querySelector('[data-catalog-condition-summary]'), bulk = document.querySelector('[data-catalog-conditions-sync]');
      if (requestEl) { requestEl.textContent = Number(request.id) > 0 ? ('#' + request.id + ' · ' + (request.title || 'Без названия')) : 'Запрос фильтра не выбран'; }
      if (summaryEl) { summaryEl.textContent = Number(request.id) > 0 ? ('Готово: ' + (summary.synced || 0) + ' · ждут проверки: ' + (summary.staged || 0) + ' · нет: ' + (summary.missing || 0) + ' · отличаются: ' + (summary.different || 0)) : 'Выберите запрос в настройках каталога'; }
      if (bulk) { bulk.disabled = Number(request.id) <= 0 || Number(summary.missing) <= 0 || !this.itemForm.querySelector('[name="id"]').value; } var recompile = this.itemForm.querySelector('[data-catalog-filter-recompile]'); if (recompile) { recompile.disabled = !this.itemForm.querySelector('[name="id"]').value || this.filterOrder.length === 0; }
      var states = this.conditionContext.states || {}, labels = { synced: 'Готово', staged: 'Ждёт Shadow', missing: 'Не создано', different: 'Отличается', unsupported: 'Недоступно', no_request: 'Нет запроса' }, classes = { synced: 'badge-green', staged: 'badge-blue', missing: 'badge-amber', different: 'badge-red', unsupported: 'badge-gray', no_request: 'badge-gray' };
      this.itemForm.querySelectorAll('[data-catalog-condition-field]').forEach(function (cell) {
        var id = cell.getAttribute('data-catalog-condition-field'), checked = this.itemForm.querySelector('[name="filters_use[]"][value="' + id + '"]').checked, state = checked ? states[id] : null, badge = cell.querySelector('[data-catalog-condition-status]'), view = cell.querySelector('[data-catalog-condition-view]'), sync = cell.querySelector('[data-catalog-condition-sync]');
        badge.className = 'badge ' + (state ? (classes[state.state] || 'badge-gray') : 'badge-gray'); badge.textContent = state ? (labels[state.state] || state.state) : (checked ? 'Ожидает сохранения' : 'Не включён');
        cell.setAttribute('data-condition-state', state ? state.state : 'disabled');
        view.hidden = !(state && ((state.generated && state.generated.value) || state.existing));
        sync.hidden = !(state && (state.state === 'missing' || state.state === 'different'));
      }, this);
    },
    showCondition: function (fieldId) {
      var state = this.conditionContext && this.conditionContext.states ? this.conditionContext.states[String(fieldId)] : null; if (!state) { return; }
      var row = this.itemForm.querySelector('[data-catalog-condition-field="' + fieldId + '"]').closest('.catalog-filter-row'), title = row.querySelector('.catalog-choice-copy b').textContent.trim(), overlay = document.createElement('div'), generated = state.generated || {}, existing = state.existing || null;
      overlay.className = 'overlay catalog-condition-overlay';
      overlay.innerHTML = '<div class="modal catalog-condition-modal" role="dialog" aria-modal="true"><div class="modal-header"><span class="dialog-icon info"><i class="ti ti-code-dots"></i></span><div><h3>' + this.esc(title) + '</h3><p class="text-secondary">Условие поля #' + this.esc(fieldId) + ' в запросе #' + this.esc((this.conditionContext.request || {}).id || '') + '.</p></div><button class="modal-close" type="button" data-catalog-condition-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div><div class="modal-body"><section class="catalog-condition-section"><div class="catalog-condition-section-head"><h4>Сгенерированное условие</h4><span class="badge badge-blue">' + this.esc(generated.compare || 'нет') + '</span></div><pre class="catalog-condition-code">' + this.esc(generated.value || 'Автоматическая генерация для этого типа не поддерживается.') + '</pre></section><section class="catalog-condition-section"><div class="catalog-condition-section-head"><h4>Текущее условие запроса</h4><span class="badge ' + (existing ? 'badge-gray' : 'badge-amber') + '">' + this.esc(existing ? existing.compare : 'не создано') + '</span></div><pre class="catalog-condition-code">' + this.esc(existing ? existing.value : 'Условие отсутствует.') + '</pre></section></div><div class="modal-footer"><button class="btn btn-ghost" type="button" data-catalog-condition-close>Закрыть</button></div></div>';
      document.body.appendChild(overlay); requestAnimationFrame(function () { overlay.classList.add('show'); });
      var close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); };
      overlay.addEventListener('click', function (e) { if (e.target === overlay || e.target.closest('[data-catalog-condition-close]')) { close(); } });
    },
    syncCondition: function (fieldId) {
      var state = this.conditionContext && this.conditionContext.states ? this.conditionContext.states[String(fieldId)] : null, self = this; if (!state) { return; }
      var run = function () { var data = new FormData(); data.append('_csrf', self.csrf()); if (state.state === 'different') { data.append('force', '1'); } self.ajax(self.url() + '/items/' + encodeURIComponent(self.itemForm.querySelector('[name="id"]').value) + '/conditions/' + encodeURIComponent(fieldId), data, function (payload) { self.renderConditionContext(payload.data.condition_context); Adminx.Toast.show(payload.message, 'success'); }); };
      if (state.state === 'different' && Adminx.Confirm) { Adminx.Confirm.open({ kind: 'warning', title: 'Заменить условие?', message: 'Текущее выражение отличается от автоматически сгенерированного. Оно будет заменено, затем запрос будет пересобран.', confirmLabel: 'Заменить', confirmClass: 'btn-warning', onConfirm: run }); return; }
      run();
    },
    syncMissingConditions: function () {
      var id = this.itemForm.querySelector('[name="id"]').value, self = this; if (!id) { return; } var data = new FormData(); data.append('_csrf', this.csrf()); this.ajax(this.url() + '/items/' + encodeURIComponent(id) + '/conditions/sync', data, function (payload) { self.renderConditionContext(payload.data.condition_context); Adminx.Toast.show(payload.message, 'success'); });
    },
    recompileCurrent: function () { var id = this.itemForm.querySelector('[name="id"]').value, self = this; if (!id) { return; } var data = new FormData(); data.append('_csrf', this.csrf()); this.ajax(this.url() + '/items/' + encodeURIComponent(id) + '/filters/recompile', data, function (payload) { Adminx.Toast.show(payload.message, 'success'); }); },
    recompileAll: function () { var self = this, ids = Array.prototype.filter.call(document.querySelectorAll('[data-catalog-item]'), function (item) { return Number(item.getAttribute('data-filter-count')) > 0; }).map(function (item) { return item.getAttribute('data-id'); }); if (!ids.length) { Adminx.Toast.show('В каталоге нет настроенных фильтров', 'info'); return; } var overlay = document.createElement('div'), done = 0, failed = [], stopped = false; overlay.className = 'overlay catalog-recompile-overlay'; overlay.innerHTML = '<div class="modal catalog-recompile-modal" role="dialog" aria-modal="true"><div class="modal-header"><span class="dialog-icon info"><i class="ti ti-refresh"></i></span><div><h3>Пересборка фильтров</h3><p class="text-secondary">Разделов: ' + ids.length + '</p></div><button class="modal-close" type="button" data-catalog-recompile-close aria-label="Закрыть" disabled><i class="ti ti-x"></i></button></div><div class="modal-body"><div class="catalog-recompile-status" data-catalog-recompile-status>Подготовка...</div><div class="progress"><div class="progress-bar" data-catalog-recompile-progress style="width:0%;--progress-color:var(--blue-500)"></div></div><div class="catalog-recompile-errors" data-catalog-recompile-errors></div></div><div class="modal-footer"><button class="btn btn-ghost" type="button" data-catalog-recompile-stop>Остановить</button><button class="btn btn-primary" type="button" data-catalog-recompile-close disabled>Закрыть</button></div></div>'; document.body.appendChild(overlay); requestAnimationFrame(function () { overlay.classList.add('show'); }); var status = overlay.querySelector('[data-catalog-recompile-status]'), bar = overlay.querySelector('[data-catalog-recompile-progress]'), errors = overlay.querySelector('[data-catalog-recompile-errors]'), close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); }, finish = function () { status.textContent = stopped ? ('Остановлено: ' + done + ' из ' + ids.length) : ('Готово: ' + done + ' из ' + ids.length); if (failed.length) { errors.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-triangle"></i><div>Ошибки: ' + failed.map(self.esc).join(', ') + '</div></div>'; } overlay.querySelectorAll('[data-catalog-recompile-close]').forEach(function (button) { button.disabled = false; }); overlay.querySelector('[data-catalog-recompile-stop]').hidden = true; }, run = function () { if (stopped || done >= ids.length) { finish(); return; } var id = ids[done], data = new FormData(); data.append('_csrf', self.csrf()); status.textContent = 'Раздел #' + id + ' · ' + (done + 1) + ' из ' + ids.length; fetch(self.url() + '/items/' + encodeURIComponent(id) + '/filters/recompile', { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(self.json).catch(function (error) { failed.push('#' + id + ': ' + ((error && error.message) || 'ошибка')); }).finally(function () { done++; bar.style.width = Math.round(done / ids.length * 100) + '%'; run(); }); }; overlay.addEventListener('click', function (e) { if (e.target.closest('[data-catalog-recompile-stop]')) { stopped = true; return; } if (e.target.closest('[data-catalog-recompile-close]') && !e.target.closest('[data-catalog-recompile-close]').disabled) { close(); } }); run(); },
    scheduleItemSave: function () {
      if (!this.itemForm || !this.itemForm.querySelector('[name="id"]').value) { return; }
      var self = this; clearTimeout(this.itemSaveTimer); this.setState('item', 'Сохранение...');
      this.itemSaveTimer = setTimeout(function () { self.saveItem(true); }, 180);
    },
    scheduleSettingsSave: function () {
      var self = this; clearTimeout(this.settingsSaveTimer); this.setState('settings', 'Сохранение...');
      this.settingsSaveTimer = setTimeout(function () { self.saveSettings(true); }, 180);
    },
    saveItem: function (auto) {
      var id = this.itemForm.querySelector('[name="id"]').value; var self = this;
      this.ajax(this.url() + '/items' + (id ? '/' + id : ''), new FormData(this.itemForm), function (payload) {
        if (payload.data && payload.data.context_links) { self.renderContextLinks(payload.data.context_links); }
        if (auto) { self.setState('item', 'Сохранено', 'ok'); self.syncTreeRow(id); if (payload.data && payload.data.condition_context) { self.renderConditionContext(payload.data.condition_context); } return; }
        Adminx.Toast.show(payload.message, 'success'); if (Adminx.Drawer) { Adminx.Drawer.close('catalogItemDrawer'); } window.location.reload();
      }, { quiet: !!auto, fail: function () { self.setState('item', 'Не сохранено', 'error'); } });
    },
    saveSettings: function (auto) { var self = this; this.ajax(this.url() + '/settings', new FormData(this.settingsForm), function (payload) { self.refreshRolePreview(); if (auto) { self.setState('settings', 'Сохранено', 'ok'); } else { self.setState('settings', 'Сохранено', 'ok'); Adminx.Toast.show(payload.message, 'success'); } }, { quiet: !!auto, fail: function () { self.setState('settings', 'Не сохранено', 'error'); } }); },
    setState: function (type, message, state) { var el = document.querySelector('[data-catalog-' + type + '-state]'); if (!el) { return; } el.textContent = message || ''; el.classList.toggle('is-ok', state === 'ok'); el.classList.toggle('is-error', state === 'error'); },
    syncTreeRow: function (id) { var row = document.querySelector('[data-catalog-item][data-id="' + id + '"]'); if (!row) { return; } var toggle = row.querySelector('[data-catalog-item-status]'), active = this.itemForm.querySelector('[name="status"]').checked, filterCount = this.itemForm.querySelectorAll('[name="filters_use[]"]:checked').length, name = row.querySelector('.catalog-tree-name b'); if (toggle) { toggle.checked = active; this.updateTreeStatus(toggle); } if (name) { name.textContent = this.itemForm.querySelector('[name="name"]').value || 'Без названия'; } row.setAttribute('data-filter-count', String(filterCount)); var count = row.querySelector('.catalog-tree-count'); if (count) { count.textContent = this.itemForm.querySelectorAll('[name="fields_use[]"]:checked').length + ' / ' + filterCount; } },
    updateTreeStatus: function (input) { var item = input.closest('[data-catalog-item]'), active = input.checked, label = item.querySelector('[data-catalog-item-status-label]'), control = input.closest('.switch'); item.classList.toggle('is-inactive', !active); if (label) { label.textContent = active ? 'активен' : 'скрыт'; } input.setAttribute('aria-label', active ? 'Скрыть раздел' : 'Включить раздел'); if (control) { control.setAttribute('data-tooltip', active ? 'Скрыть раздел' : 'Включить раздел'); } },
    setTreeStatus: function (input) { var self = this, id = input.getAttribute('data-catalog-item-status'), previous = !input.checked, data = new FormData(); this.updateTreeStatus(input); input.disabled = true; data.append('_csrf', this.csrf()); data.append('status', input.checked ? '1' : '0'); this.ajax(this.url() + '/items/' + encodeURIComponent(id) + '/status', data, function (payload) { input.checked = Number(payload.data.status) === 1; input.disabled = false; self.updateTreeStatus(input); }, { quiet: true, fail: function () { input.checked = previous; input.disabled = false; self.updateTreeStatus(input); } }); },
    setDocument: function (item) { if (!this.itemForm) { return; } var input = this.itemForm.querySelector('[name="document_id"]'); var label = this.itemForm.querySelector('[data-catalog-document-label]'); var clear = this.itemForm.querySelector('[data-catalog-document-clear]'); var id = item && item.id ? Number(item.id) : 0; input.value = id || ''; if (label) { label.textContent = id ? ('#' + id + ' · ' + (item.title || item.alias || 'Без названия')) : 'Документ не выбран'; } if (clear) { clear.disabled = !id; } },
    sourceOptions: function () {
      if (this.sourceOptionsData !== null) { return this.sourceOptionsData; }
      var node = document.querySelector('[data-catalog-source-options]');
      try { this.sourceOptionsData = node ? JSON.parse(node.textContent || '[]') : []; }
      catch (e) { this.sourceOptionsData = []; }
      return this.sourceOptionsData;
    },
    setSources: function (values) {
      if (!this.itemForm) { return; }
      var ownId = Number(this.itemForm.querySelector('[name="id"]').value) || 0, allowed = {}, selected = [];
      this.sourceOptions().forEach(function (item) { if (Number(item.id) !== ownId) { allowed[String(item.id)] = item; } });
      (values || []).forEach(function (id) { id = String(Number(id) || 0); if (allowed[id] && selected.indexOf(id) === -1) { selected.push(id); } });
      this.sourceItemIds = selected;
      var inputs = this.itemForm.querySelector('[data-catalog-source-inputs]'), label = this.itemForm.querySelector('[data-catalog-source-label]'), clear = this.itemForm.querySelector('[data-catalog-source-clear]');
      if (inputs) {
        inputs.innerHTML = '';
        selected.forEach(function (id) { var input = document.createElement('input'); input.type = 'hidden'; input.name = 'source_item_ids[]'; input.value = id; inputs.appendChild(input); });
      }
      if (label) {
        var names = selected.map(function (id) { return allowed[id].name; });
        label.textContent = names.length === 0 ? 'Товары только из этого раздела' : (names.slice(0, 2).join(', ') + (names.length > 2 ? ' · ещё ' + (names.length - 2) : ''));
      }
      if (clear) { clear.disabled = selected.length === 0; }
    },
    openSourcePicker: function () {
      var self = this, ownId = Number(this.itemForm.querySelector('[name="id"]').value) || 0;
      var options = this.sourceOptions().filter(function (item) { return Number(item.id) !== ownId; });
      var selected = {}; this.sourceItemIds.forEach(function (id) { selected[String(id)] = true; });
      var overlay = document.createElement('div'), rows = '';
      options.forEach(function (item) {
        var id = String(item.id), search = (id + ' ' + (item.path || item.name || '')).toLowerCase();
        rows += '<label class="catalog-source-option" data-catalog-source-option data-search="' + self.esc(search) + '"><input type="checkbox" value="' + self.esc(id) + '"' + (selected[id] ? ' checked' : '') + '><span><b>' + self.esc(item.name || 'Без названия') + '</b><small>' + self.esc(item.path || ('Раздел #' + id)) + '</small></span><em class="badge ' + (Number(item.status) === 1 ? 'badge-green' : 'badge-gray') + '">' + (Number(item.status) === 1 ? 'активен' : 'скрыт') + '</em></label>';
      });
      overlay.className = 'overlay catalog-source-overlay';
      overlay.innerHTML = '<div class="modal catalog-source-modal" role="dialog" aria-modal="true"><div class="modal-header"><span class="dialog-icon info"><i class="ti ti-folders"></i></span><div><h3>Источники товаров</h3><p class="text-secondary">Товары выбранных разделов появятся на текущей странице.</p></div><button class="modal-close" type="button" data-catalog-source-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div><div class="modal-body"><label class="input-wrap"><i class="ti ti-search"></i><input class="input" type="search" placeholder="Найти раздел по названию или ID" data-catalog-source-search></label><div class="catalog-source-options">' + rows + '</div><div class="catalog-source-empty" data-catalog-source-empty' + (options.length ? ' hidden' : '') + '>Подходящих разделов нет</div></div><div class="modal-footer"><span class="mf-left text-secondary text-sm" data-catalog-source-count></span><button class="btn btn-ghost" type="button" data-catalog-source-close>Закрыть</button><button class="btn btn-primary" type="button" data-catalog-source-save><i class="ti ti-check"></i>Применить</button></div></div>';
      document.body.appendChild(overlay); requestAnimationFrame(function () { overlay.classList.add('show'); });
      var search = overlay.querySelector('[data-catalog-source-search]'), count = overlay.querySelector('[data-catalog-source-count]'), empty = overlay.querySelector('[data-catalog-source-empty]');
      var update = function () { var checked = overlay.querySelectorAll('[data-catalog-source-option] input:checked').length; count.textContent = checked ? ('Выбрано: ' + checked) : 'Источники не выбраны'; };
      var filter = function () { var query = (search.value || '').trim().toLowerCase(), visible = 0; overlay.querySelectorAll('[data-catalog-source-option]').forEach(function (row) { var show = !query || row.getAttribute('data-search').indexOf(query) !== -1; row.hidden = !show; if (show) { visible++; } }); empty.hidden = visible > 0; };
      var close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); };
      overlay.addEventListener('change', update);
      overlay.addEventListener('click', function (e) { if (e.target === overlay || e.target.closest('[data-catalog-source-close]')) { close(); return; } if (e.target.closest('[data-catalog-source-save]')) { self.setSources(Array.prototype.map.call(overlay.querySelectorAll('[data-catalog-source-option] input:checked'), function (input) { return input.value; })); self.scheduleItemSave(); close(); } });
      search.addEventListener('input', filter); update(); search.focus();
    },
    openDocumentPicker: function () {
      var self = this, overlay = document.createElement('div');
      overlay.className = 'overlay catalog-document-overlay';
      overlay.innerHTML = '<div class="modal catalog-document-modal" role="dialog" aria-modal="true"><div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div><h3>Выбрать документ</h3><p class="text-secondary">Поиск по ID, названию или alias.</p></div><button class="modal-close" type="button" data-catalog-document-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div><div class="modal-body"><label class="input-wrap"><i class="ti ti-search"></i><input class="input" type="search" placeholder="Начните вводить название или ID" data-catalog-document-search></label><div class="catalog-document-status" data-catalog-document-status>Загрузка...</div><div class="catalog-document-results" data-catalog-document-results></div></div><div class="modal-footer"><span class="mf-left text-secondary text-sm" data-catalog-document-count></span><button class="btn btn-ghost" type="button" data-catalog-document-close>Закрыть</button></div></div>';
      overlay.querySelector('.catalog-document-modal').classList.add('picker-modal');
      document.body.appendChild(overlay); requestAnimationFrame(function () { overlay.classList.add('show'); });
      var search = overlay.querySelector('[data-catalog-document-search]'), results = overlay.querySelector('[data-catalog-document-results]'), status = overlay.querySelector('[data-catalog-document-status]'), count = overlay.querySelector('[data-catalog-document-count]'), timer = null;
      var close = function () { overlay.classList.remove('show'); setTimeout(function () { overlay.remove(); }, 160); };
      var load = function () { status.hidden = false; status.textContent = 'Загрузка...'; fetch(self.base() + '/catalog/documents?q=' + encodeURIComponent(search.value.trim()) + '&limit=30', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(self.json).then(function (payload) { var items = (payload.data || {}).items || []; results.innerHTML = ''; items.forEach(function (item) { results.insertAdjacentHTML('beforeend', '<button class="catalog-document-result" type="button" data-document-id="' + self.esc(item.id) + '" data-document-title="' + self.esc(item.title || '') + '" data-document-alias="' + self.esc(item.alias || '') + '"><span class="mono">#' + self.esc(item.id) + '</span><span><b>' + self.esc(item.title || 'Без названия') + '</b><small>' + self.esc(item.alias || '') + '</small></span><em>' + self.esc(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</em></button>'); }); status.hidden = items.length > 0; status.textContent = 'Ничего не найдено'; count.textContent = items.length ? 'Документов: ' + items.length : ''; }).catch(function () { status.hidden = false; status.textContent = 'Не удалось загрузить документы'; }); };
      overlay.addEventListener('click', function (e) { if (e.target === overlay || e.target.closest('[data-catalog-document-close]')) { close(); return; } var item = e.target.closest('[data-document-id]'); if (item) { self.setDocument({ id: item.getAttribute('data-document-id'), title: item.getAttribute('data-document-title'), alias: item.getAttribute('data-document-alias') }); self.scheduleItemSave(); close(); } });
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 220); }); load(); search.focus();
    },
    deleteItem: function (id) {
      var self = this, run = function () { var data = new FormData(); data.append('_csrf', self.csrf()); self.ajax(self.url() + '/items/' + id + '/delete', data, function (payload) { Adminx.Toast.show(payload.message, 'success'); window.location.reload(); }); };
      if (Adminx.Confirm) { Adminx.Confirm.open({ kind: 'error', title: 'Удалить раздел?', message: 'Будут удалены также все вложенные разделы. Документы останутся.', confirmLabel: 'Удалить', confirmClass: 'btn-danger', onConfirm: run }); } else if (confirm('Удалить раздел и все вложенные?')) { run(); }
    },
    markSelectedItem: function (id) {
      document.querySelectorAll('[data-catalog-builder-item]').forEach(function (row) {
        row.classList.toggle('is-selected', String(row.getAttribute('data-catalog-builder-item')) === String(id));
      });
    },
    treeRoot: function () { return document.querySelector('[data-catalog-tree]'); },
    treeNodes: function () {
      var root = this.treeRoot();
      return root ? Array.prototype.filter.call(root.children, function (node) { return node.matches('[data-catalog-item]'); }) : [];
    },
    search: function (value) {
      value = String(value || '').trim().toLowerCase();
      var nodes = this.treeNodes(), byId = {}, visible = {};
      nodes.forEach(function (node) { byId[String(node.getAttribute('data-id'))] = node; node.classList.add('is-filtered'); });
      if (!value) {
        nodes.forEach(function (node) { node.classList.remove('is-filtered'); });
      } else {
        nodes.forEach(function (node) {
          var name = node.querySelector('.catalog-tree-name');
          if (!name || name.textContent.toLowerCase().indexOf(value) < 0) { return; }
          var current = node;
          while (current && !visible[current.getAttribute('data-id')]) {
            visible[current.getAttribute('data-id')] = true;
            current = byId[String(current.getAttribute('data-parent-id'))] || null;
          }
        });
        Object.keys(visible).forEach(function (id) { if (byId[id]) { byId[id].classList.remove('is-filtered'); } });
      }
      var root = this.treeRoot();
      if (root) { root.classList.toggle('is-filtering', !!value); }
    },
    treeDragStart: function (e) {
      var handle = e.target.closest('[data-catalog-drag-handle]');
      var root = handle ? handle.closest('[data-catalog-tree]') : null;
      if (!handle || !root || handle.disabled || this.orderSaving || root.classList.contains('is-filtering') || (typeof e.button === 'number' && e.button !== 0)) { return; }
      this.dragItem = handle.closest('[data-catalog-item]');
      if (!this.dragItem) { return; }
      this.dragPointerId = e.pointerId;
      this.dragStartX = e.clientX;
      this.dragStartY = e.clientY;
      this.dragStarted = false;
      this.dragOrderSnapshot = this.treeOrderSignature();
      if (handle.setPointerCapture) { handle.setPointerCapture(e.pointerId); }
      e.preventDefault();
    },
    beginTreeDrag: function (e) {
      if (!this.dragItem || this.dragStarted) { return; }
      var row = this.dragItem.querySelector('[data-catalog-builder-item]');
      var firstRect = this.dragItem.getBoundingClientRect();
      var rowRect = row.getBoundingClientRect();
      this.dragStarted = true;
      this.dragGroup = this.treeBranch(this.dragItem);
      this.dragPlaceholder = document.createElement('li');
      this.dragPlaceholder.className = 'catalog-tree-placeholder';
      this.dragPlaceholder.setAttribute('aria-hidden', 'true');
      var lastRect = this.dragGroup[this.dragGroup.length - 1].getBoundingClientRect();
      this.dragPlaceholder.style.height = Math.max(52, lastRect.bottom - firstRect.top) + 'px';
      this.dragItem.parentNode.insertBefore(this.dragPlaceholder, this.dragItem);
      this.dragGhost = row.cloneNode(true);
      this.dragGhost.classList.remove('is-selected');
      this.dragGhost.classList.add('catalog-tree-ghost');
      this.dragGhost.style.width = Math.min(rowRect.width, 520, Math.max(1, window.innerWidth - 24)) + 'px';
      document.body.appendChild(this.dragGhost);
      this.positionTreeDragGhost(e);
      document.body.classList.add('catalog-tree-dragging');
      this.treeRoot().classList.add('is-drag-active');
      this.dragGroup.forEach(function (node, index) { node.classList.add(index === 0 ? 'is-dragging' : 'is-dragging-child'); });
    },
    treeDragOver: function (e) {
      if (!this.dragItem || e.pointerId !== this.dragPointerId) { return; }
      if (!this.dragStarted) {
        if (Math.abs(e.clientX - this.dragStartX) < 5 && Math.abs(e.clientY - this.dragStartY) < 5) { return; }
        this.beginTreeDrag(e);
      }
      e.preventDefault();
      this.positionTreeDragGhost(e);
      this.scrollTree(e.clientY);
      var target = null, bottom = false, self = this;
      this.treeNodes().some(function (node) {
        if (self.dragGroup.indexOf(node) !== -1 || node.classList.contains('is-filtered')) { return false; }
        var candidate = node.querySelector('[data-catalog-builder-item]');
        var rect = candidate ? candidate.getBoundingClientRect() : null;
        if (!rect || e.clientY >= rect.bottom) { return false; }
        target = node;
        bottom = e.clientY > rect.top + rect.height / 2;
        return true;
      });
      document.querySelectorAll('.catalog-tree-row.drag-over-top, .catalog-tree-row.drag-over-bottom').forEach(function (row) { row.classList.remove('drag-over-top', 'drag-over-bottom'); });
      if (!target) { this.treeRoot().appendChild(this.dragPlaceholder); return; }
      var targetRow = target.querySelector('[data-catalog-builder-item]');
      var branch = this.treeBranch(target);
      var anchor = bottom ? branch[branch.length - 1].nextSibling : target;
      if (anchor !== this.dragPlaceholder) { target.parentNode.insertBefore(this.dragPlaceholder, anchor); }
      targetRow.classList.toggle('drag-over-top', !bottom);
      targetRow.classList.toggle('drag-over-bottom', bottom);
    },
    treeDrop: function (e) {
      if (!this.dragItem || e.pointerId !== this.dragPointerId) { return; }
      e.preventDefault();
      if (!this.dragStarted || !this.dragPlaceholder || !this.dragPlaceholder.parentNode) { this.treeDragEnd(); return; }
      var placeholder = this.dragPlaceholder;
      this.dragGroup.forEach(function (node) { placeholder.parentNode.insertBefore(node, placeholder); });
      placeholder.parentNode.removeChild(placeholder);
      this.dragPlaceholder = null;
      this.normalizeTreeHierarchy();
      var changed = this.treeOrderSignature() !== this.dragOrderSnapshot;
      this.treeDragEnd();
      if (changed) { this.persistTreeOrder(); }
    },
    treeDragEnd: function () {
      this.dragGroup.forEach(function (node) { node.classList.remove('is-dragging', 'is-dragging-child'); });
      if (this.dragPlaceholder && this.dragPlaceholder.parentNode) { this.dragPlaceholder.parentNode.removeChild(this.dragPlaceholder); }
      document.querySelectorAll('.catalog-tree-row.drag-over-top, .catalog-tree-row.drag-over-bottom').forEach(function (row) { row.classList.remove('drag-over-top', 'drag-over-bottom'); });
      var root = this.treeRoot();
      if (root) { root.classList.remove('is-drag-active'); }
      if (this.dragGhost && this.dragGhost.parentNode) { this.dragGhost.parentNode.removeChild(this.dragGhost); }
      document.body.classList.remove('catalog-tree-dragging');
      this.dragItem = null;
      this.dragGroup = [];
      this.dragPlaceholder = null;
      this.dragGhost = null;
      this.dragPointerId = null;
      this.dragStarted = false;
      this.dragOrderSnapshot = '';
    },
    positionTreeDragGhost: function (e) {
      if (!this.dragGhost) { return; }
      var left = Math.min(e.clientX + 14, window.innerWidth - this.dragGhost.offsetWidth - 12);
      var top = Math.min(e.clientY + 12, window.innerHeight - this.dragGhost.offsetHeight - 12);
      this.dragGhost.style.transform = 'translate3d(' + Math.max(12, left) + 'px,' + Math.max(12, top) + 'px,0)';
    },
    scrollTree: function (clientY) {
      var edge = 72;
      if (clientY < edge) { window.scrollBy(0, -Math.ceil((edge - clientY) / 5)); }
      if (clientY > window.innerHeight - edge) { window.scrollBy(0, Math.ceil((clientY - window.innerHeight + edge) / 5)); }
    },
    treeOrderSignature: function () {
      return this.treeNodes().map(function (node) { return node.getAttribute('data-id') + ':' + node.getAttribute('data-level'); }).join('|');
    },
    treeBranch: function (node) {
      if (!node) { return []; }
      var branch = [node], level = parseInt(node.getAttribute('data-level'), 10) || 0, next = node.nextElementSibling;
      while (next) {
        if (next === this.dragPlaceholder) { next = next.nextElementSibling; continue; }
        if (!next.matches('[data-catalog-item]') || (parseInt(next.getAttribute('data-level'), 10) || 0) <= level) { break; }
        branch.push(next);
        next = next.nextElementSibling;
      }
      return branch;
    },
    canIndentItem: function (node) {
      var level = parseInt(node.getAttribute('data-level'), 10) || 0, previous = node.previousElementSibling;
      while (previous) {
        if (!previous.matches('[data-catalog-item]')) { previous = previous.previousElementSibling; continue; }
        var previousLevel = parseInt(previous.getAttribute('data-level'), 10) || 0;
        if (previousLevel < level) { return false; }
        if (previousLevel === level) { return true; }
        previous = previous.previousElementSibling;
      }
      return false;
    },
    changeItemLevel: function (node, delta) {
      if (!node || !delta || this.orderSaving) { return; }
      var root = this.treeRoot();
      if (root && root.classList.contains('is-filtering')) { Adminx.Toast.show('Очистите поиск перед изменением структуры', 'info'); return; }
      var level = parseInt(node.getAttribute('data-level'), 10) || 0;
      if (delta > 0 && !this.canIndentItem(node)) { return; }
      if (delta < 0 && level === 0) { return; }
      var shift = delta > 0 ? 1 : -1;
      this.treeBranch(node).forEach(function (branchNode) {
        var branchLevel = parseInt(branchNode.getAttribute('data-level'), 10) || 0;
        branchNode.setAttribute('data-level', String(Math.max(0, branchLevel + shift)));
      });
      this.normalizeTreeHierarchy();
      this.persistTreeOrder();
    },
    normalizeTreeHierarchy: function () {
      var nodes = this.treeNodes(), parents = [], positions = {}, previousLevel = 0, self = this;
      nodes.forEach(function (node, index) {
        var level = Math.max(0, parseInt(node.getAttribute('data-level'), 10) || 0);
        if (index === 0) { level = 0; }
        if (level > previousLevel + 1) { level = previousLevel + 1; }
        var parentId = level > 0 && parents[level - 1] ? parents[level - 1] : 0;
        if (level > 0 && !parentId) { level = 0; parentId = 0; }
        var id = parseInt(node.getAttribute('data-id'), 10) || 0;
        var key = String(parentId);
        var position = positions[key] || 0;
        positions[key] = position + 1;
        parents[level] = id;
        parents.length = level + 1;
        previousLevel = level;
        node.setAttribute('data-level', String(level));
        node.setAttribute('data-parent-id', String(parentId));
        node.setAttribute('data-position', String(position));
        node.style.setProperty('--catalog-indent', Math.min(level, 10) * 28 + 'px');
        var number = node.querySelector('.catalog-tree-position');
        if (number) { number.textContent = String(position + 1); }
        var badge = node.querySelector('[data-catalog-level-badge]');
        if (badge) { badge.textContent = 'уровень ' + (level + 1); }
      });
      nodes.forEach(function (node) {
        var level = parseInt(node.getAttribute('data-level'), 10) || 0;
        var decrease = node.querySelector('[data-catalog-item-indent="-1"]');
        var increase = node.querySelector('[data-catalog-item-indent="1"]');
        if (decrease) { decrease.disabled = level === 0; }
        if (increase) { increase.disabled = !self.canIndentItem(node); }
      });
    },
    persistTreeOrder: function () {
      if (this.orderSaving) { return; }
      this.normalizeTreeHierarchy();
      var rows = this.treeNodes().map(function (node) {
        return {
          id: Number(node.getAttribute('data-id')),
          parent_id: Number(node.getAttribute('data-parent-id')),
          position: Number(node.getAttribute('data-position'))
        };
      });
      var data = new FormData(), self = this, root = this.treeRoot();
      data.append('_csrf', this.csrf());
      data.append('order', JSON.stringify(rows));
      this.orderSaving = true;
      if (root) { root.classList.add('is-order-saving'); }
      this.ajax(this.url() + '/reorder', data, function (payload) {
        self.orderSaving = false;
        if (root) { root.classList.remove('is-order-saving'); }
        Adminx.Toast.show(payload.message || 'Порядок сохранён', 'success');
      }, { quiet: true, fail: function () {
        self.orderSaving = false;
        if (root) { root.classList.remove('is-order-saving'); }
        setTimeout(function () { window.location.reload(); }, 400);
      } });
    },
    ajax: function (url, data, done, options) { options = options || {}; if (!options.quiet) { Adminx.Loader.show(); } fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(this.json).then(function (payload) { if (!payload.success) { throw payload; } done(payload); }).catch(function (payload) { if (options.fail) { options.fail(payload); } Adminx.Catalog.error(payload); }).finally(function () { if (!options.quiet) { Adminx.Loader.hide(); } }); },
    json: function (res) { return res.json().then(function (payload) { if (!res.ok) { throw payload; } return payload; }); },
    esc: function (value) { var node = document.createElement('div'); node.textContent = String(value == null ? '' : value); return node.innerHTML; },
    error: function (payload) { Adminx.Toast.show((payload && payload.message) || 'Ошибка запроса', 'error'); }
  };
  document.addEventListener('DOMContentLoaded', function () { Adminx.Catalog.init(); });
})(window, document);
