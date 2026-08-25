/**
 * JS раздела «Рубрики и поля»: AJAX-фильтры, drawer-CRUD и сортировка.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  Adminx.Rubrics = {
    form: null,
    fieldForm: null,
    groupForm: null,
    templateForm: null,
    extraTemplateForm: null,
    builderForm: null,
    currentRubricId: 0,
    currentGroups: [],
    currentFields: [],
    currentFieldSetLinks: [],
    fieldSetMode: 'copy',
    currentTemplateFields: [],
    activeTemplateTextarea: null,
    filterTimer: null,
    aliasTimer: null,
    fieldAliasTimer: null,
    dragRubricRow: null,
    dragGroupNode: null,
    dragFieldNode: null,
    dragBuilderFieldId: 0,
    builderSelectedId: 0,
    builderDrafts: {},
    builderDirty: false,
    builderHydrating: false,
    builderSettingsReady: false,
    adminViewForm: null,
    adminViewState: null,
    adminViewFields: [],
    adminViewPreview: [],
    adminViewSelected: '',
    adminViewDragSource: '',
    templateRegistryLoaded: false,
    templateRegistryLoading: false,
    currentSchemaRevisionRubricId: 0,
    currentSchemaRevisionId: 0,
    currentSchemaRevisionFingerprint: '',
    pendingExtraTemplateId: 0,

    init: function () {
      this.form = document.getElementById('rubricForm');
      this.fieldForm = document.getElementById('rubricFieldForm');
      this.groupForm = document.getElementById('rubricGroupForm');
      this.templateForm = document.getElementById('rubricTemplateForm');
      this.extraTemplateForm = document.getElementById('rubricExtraTemplateForm');
      this.builderForm = document.querySelector('[data-builder-inspector]');
      this.adminViewForm = document.getElementById('rubricAdminViewForm');
      this.syncFieldTypeOptions('');
      this.bind();
      this.openFromLocation();
    },

    base: function () {
      return (this.form && this.form.getAttribute('data-base')) || Adminx.base();
    },

    openFromLocation: function () {
      var params = new URLSearchParams(window.location.search);
      var rubricId = parseInt(params.get('templates'), 10) || 0;
      var editId = parseInt(params.get('edit'), 10) || 0;
      var create = params.get('create') === '1';
      var row;
      if (rubricId) {
        row = document.querySelector('[data-rubric-row][data-id="' + rubricId + '"]');
        if (!row) { return; }
        this.pendingExtraTemplateId = parseInt(params.get('template'), 10) || 0;
        if (Adminx.Drawer) { Adminx.Drawer.open('rubricTemplatesDrawer'); }
        this.openTemplates(row);
        return;
      }
      if (editId) {
        row = document.querySelector('[data-rubric-row][data-id="' + editId + '"]');
        if (!row) { return; }
        if (Adminx.Drawer) { Adminx.Drawer.open('rubricDrawer'); }
        this.fillRubricEdit(row);
        return;
      }
      if (create) {
        if (Adminx.Drawer) { Adminx.Drawer.open('rubricDrawer'); }
        this.fillRubricNew(params.get('purpose'));
      }
    },

    bind: function () {
      var self = this;

      document.addEventListener('click', function (e) {
        var cmWrap = e.target.closest('.CodeMirror');
        if (cmWrap) { self.captureCodeMirror(cmWrap); }
        var mainTab = e.target.closest('[data-rubrics-main-tab]');
        if (mainTab) { self.activateMainTab(mainTab.getAttribute('data-rubrics-main-tab')); }
        if (e.target.closest('[data-rubrics-filter-reset]')) { self.resetFilters(); }
        if (e.target.closest('[data-rubric-new]')) { self.fillRubricNew('content'); }
        var edit = e.target.closest('[data-rubric-edit]');
        if (edit) { self.fillRubricEdit(edit.closest('[data-rubric-row]')); }
        var del = e.target.closest('[data-rubric-delete]');
        if (del) { self.deleteRubric(del.closest('[data-rubric-row]')); }
        var fields = e.target.closest('[data-rubric-fields]');
        if (fields) { self.openFields(fields.closest('[data-rubric-row]')); }
        var templates = e.target.closest('[data-rubric-templates]');
        if (templates) { self.openTemplates(templates.closest('[data-rubric-row]')); }
        var adminView = e.target.closest('[data-rubric-admin-view]');
        if (adminView) { self.openAdminView(adminView.closest('[data-rubric-row]')); }
        var schemaRevisions = e.target.closest('[data-rubric-schema-revisions]');
        if (schemaRevisions) { self.openSchemaRevisions(schemaRevisions.closest('[data-rubric-row]')); }
        var schemaRevisionOpen = e.target.closest('[data-schema-revision-open]');
        if (schemaRevisionOpen && !e.target.closest('[data-schema-revision-row-delete]')) {
          self.loadSchemaRevision(schemaRevisionOpen.getAttribute('data-schema-revision-open'));
        }
        var schemaRevisionRowDelete = e.target.closest('[data-schema-revision-row-delete]');
        if (schemaRevisionRowDelete) { self.deleteSchemaRevision(schemaRevisionRowDelete.getAttribute('data-schema-revision-row-delete')); }
        if (e.target.closest('[data-schema-revision-delete]')) { self.deleteSchemaRevision(); }
        if (e.target.closest('[data-schema-revisions-clear]')) { self.clearSchemaRevisions(); }
        if (e.target.closest('[data-schema-revision-restore]')) { self.restoreSchemaRevision(); }
        if (e.target.closest('[data-rubric-trash-open]')) { self.openTrash(); }
        var trashRestore = e.target.closest('[data-rubric-trash-restore]');
        if (trashRestore) { self.restoreTrash(trashRestore.getAttribute('data-rubric-trash-restore')); }
        var trashPurge = e.target.closest('[data-rubric-trash-purge]');
        if (trashPurge) { self.purgeTrash(trashPurge.getAttribute('data-rubric-trash-purge')); }
        var adminViewMode = e.target.closest('[data-admin-view-mode]');
        if (adminViewMode) { self.setAdminViewMode(adminViewMode.getAttribute('data-admin-view-mode')); }
        var adminViewAdd = e.target.closest('[data-admin-view-add]');
        if (adminViewAdd) { self.addAdminViewItem(adminViewAdd.getAttribute('data-admin-view-add')); }
        var adminViewItem = e.target.closest('[data-admin-view-item]');
        if (adminViewItem && !e.target.closest('[data-admin-view-remove], [data-admin-view-move]')) {
          self.selectAdminViewItem(adminViewItem.getAttribute('data-source'));
        }
        var adminViewRemove = e.target.closest('[data-admin-view-remove]');
        if (adminViewRemove) { self.removeAdminViewItem(adminViewRemove.getAttribute('data-admin-view-remove')); }
        var adminViewMove = e.target.closest('[data-admin-view-move]');
        if (adminViewMove) { self.moveAdminViewItem(adminViewMove.getAttribute('data-source'), parseInt(adminViewMove.getAttribute('data-admin-view-move'), 10) || 0); }
        var adminViewToken = e.target.closest('[data-admin-view-token]');
        if (adminViewToken) { self.insertAdminViewToken(adminViewToken.getAttribute('data-admin-view-token')); }
        if (e.target.closest('[data-admin-view-template-default]')) { self.setAdminViewTemplateExample(); }
        var templateTab = e.target.closest('[data-rubric-template-tab]');
        if (templateTab) { self.activateTemplateTab(templateTab.getAttribute('data-rubric-template-tab')); }
        if (e.target.closest('[data-rubric-og-default]')) { self.setOpenGraphExample(); }
        if (e.target.closest('[data-rubric-template-refresh]')) { self.loadTemplates(); }
        var templateTagsToggle = e.target.closest('[data-rubric-template-tags-toggle]');
        if (templateTagsToggle) { self.openTemplateTagPalette(templateTagsToggle.closest('.rubrics-code-field')); }
        if (e.target.closest('[data-rubric-template-tags-close]')) { self.closeTemplateTagPalette(); }
        var templateTagTab = e.target.closest('[data-rubric-template-tag-tab]');
        if (templateTagTab) { self.activateTemplateTagGroup(templateTagTab.getAttribute('data-rubric-template-tag-tab')); }
        var templateTag = e.target.closest('[data-rubric-template-tag]');
        if (templateTag) { self.insertTemplateTag(templateTag); }
        var fieldTplTag = e.target.closest('[data-field-tpl-tag]');
        if (fieldTplTag) { e.preventDefault(); self.insertFieldTemplateTag(fieldTplTag.getAttribute('data-field-tpl-tag')); }
        var codeLint = e.target.closest('[data-rubric-code-lint]');
        if (codeLint) { self.lintCodeField(codeLint.closest('.rubrics-code-field')); }
        var codeFull = e.target.closest('[data-rubric-code-fullscreen]');
        if (codeFull) { self.toggleCodeFieldFullscreen(codeFull.closest('.rubrics-code-field')); }
        if (e.target.closest('[data-extra-template-new]')) { self.fillExtraTemplateNew(); }
        var extraEdit = e.target.closest('[data-extra-template-edit]');
        if (extraEdit) { self.fillExtraTemplateEdit(extraEdit.closest('[data-extra-template-row]')); }
        var extraDelete = e.target.closest('[data-extra-template-delete]');
        if (extraDelete) { self.deleteExtraTemplate(extraDelete.closest('[data-extra-template-row]')); }
        if (e.target.closest('[data-field-new]')) { self.fillFieldNew(); }
        var builderReturn = e.target.closest('[data-builder-unplace]');
        if (builderReturn) {
          e.preventDefault();
          self.unplaceBuilderField(builderReturn.closest('[data-field-row]'));
        }
        var builderSelect = e.target.closest('[data-builder-select]');
        if (builderSelect && !e.target.closest('[data-field-edit], [data-field-delete], [data-field-tag-copy], [data-builder-unplace]')) {
          if (builderSelect.matches('[data-builder-palette-item]')) {
            self.placeBuilderField(builderSelect.getAttribute('data-id'), 0);
          } else {
            self.selectBuilderField(builderSelect.getAttribute('data-id'));
          }
        }
        if (e.target.closest('[data-builder-save]')) { self.saveBuilder(); }
        if (e.target.closest('[data-builder-advanced]')) { self.openBuilderAdvanced(); }
		if (e.target.closest('[data-builder-field-set-apply]')) { self.applyBuilderFieldSet(); }
		if (e.target.closest('[data-builder-field-set-export]')) { self.exportBuilderFieldSet(); }
		if (e.target.closest('[data-builder-field-set-import]')) { self.openBuilderFieldSetImport(); }
		var fieldSetMode = e.target.closest('[data-field-set-mode]');
		if (fieldSetMode) { self.setBuilderFieldSetMode(fieldSetMode.getAttribute('data-field-set-mode')); }
		var fieldSetSync = e.target.closest('[data-field-set-sync]');
		if (fieldSetSync) { self.previewLinkedFieldSet(fieldSetSync.getAttribute('data-field-set-sync')); }
		var fieldSetDetach = e.target.closest('[data-field-set-detach]');
		if (fieldSetDetach) { self.detachLinkedFieldSet(fieldSetDetach.getAttribute('data-field-set-detach')); }
        var fieldEdit = e.target.closest('[data-field-edit]');
        if (fieldEdit) {
          if (self.builderDirty && fieldEdit.closest('[data-builder-canvas]')) {
            Adminx.Toast.show('Сначала сохраните изменения конструктора', 'warning');
            return;
          }
          self.fillFieldEdit(fieldEdit.closest('[data-field-row]'));
          if (Adminx.Drawer) { Adminx.Drawer.open('rubricFieldDrawer'); }
        }
        var fieldDelete = e.target.closest('[data-field-delete]');
        if (fieldDelete) { self.deleteField(fieldDelete.closest('[data-field-row]')); }
        var fieldCopy = e.target.closest('[data-field-tag-copy]');
        if (fieldCopy) { self.copy(fieldCopy.getAttribute('data-field-tag-copy')); }
        var defaultBool = e.target.closest('[data-default-boolean]');
        if (defaultBool) {
          e.preventDefault();
          self.setDefaultBoolean(defaultBool.getAttribute('data-default-boolean'));
        }
        if (e.target.closest('[data-default-list-add]')) {
          e.preventDefault();
          self.addDefaultListRow();
        }
        var defaultListRemove = e.target.closest('[data-default-list-remove]');
        if (defaultListRemove) {
          e.preventDefault();
          self.removeDefaultListRow(defaultListRemove.closest('[data-default-list-row]'));
        }
        var settingsMapAdd = e.target.closest('[data-settings-map-add]');
        if (settingsMapAdd) {
          e.preventDefault();
          self.addSettingsMapRow(settingsMapAdd.closest('[data-settings-map]'));
        }
        var settingsMapRemove = e.target.closest('[data-settings-map-remove]');
        if (settingsMapRemove) {
          e.preventDefault();
          self.removeSettingsMapRow(settingsMapRemove.closest('[data-settings-map-row]'));
        }
        if (e.target.closest('[data-builder-condition-add]')) {
          e.preventDefault();
          self.addBuilderConditionRow(e.target.closest('[data-builder-condition-group]'));
        }
        if (e.target.closest('[data-builder-condition-group-add]')) {
          e.preventDefault();
          self.addBuilderConditionGroup(e.target.closest('[data-builder-condition-group]'));
        }
        var conditionRemove = e.target.closest('[data-builder-condition-remove]');
        if (conditionRemove) {
          e.preventDefault();
          self.removeBuilderConditionRow(conditionRemove.closest('[data-builder-condition-row]'));
        }
        var conditionGroupRemove = e.target.closest('[data-builder-condition-group-remove]');
        if (conditionGroupRemove) {
          e.preventDefault();
          self.removeBuilderConditionGroup(conditionGroupRemove.closest('[data-builder-condition-group]'));
        }
        if (e.target.closest('[data-default-media-pick]')) {
          e.preventDefault();
          self.openDefaultMediaPicker(null);
        }
        var mediaRowPick = e.target.closest('[data-default-media-row-pick]');
        if (mediaRowPick) {
          e.preventDefault();
          self.openDefaultMediaPicker(mediaRowPick.closest('[data-default-list-row]'));
        }
        if (e.target.closest('[data-default-relation-pick]')) {
          e.preventDefault();
          self.openDefaultRelationPicker(null);
        }
        var relationRowPick = e.target.closest('[data-default-relation-row-pick]');
        if (relationRowPick) {
          e.preventDefault();
          self.openDefaultRelationPicker(relationRowPick.closest('[data-default-list-row]'));
        }
        if (e.target.closest('[data-group-new]')) { self.fillGroupNew(); }
        var groupEdit = e.target.closest('[data-group-edit]');
        if (groupEdit) {
          self.fillGroupEdit(groupEdit.closest('[data-group-row]'));
          if (Adminx.Drawer) { Adminx.Drawer.open('rubricGroupDrawer'); }
        }
        var groupDelete = e.target.closest('[data-group-delete]');
        if (groupDelete) { self.deleteGroup(groupDelete.closest('[data-group-row]')); }
      });

      document.addEventListener('submit', function (e) {
        var filter = e.target.closest('.rubrics-filter');
        if (filter) {
          e.preventDefault();
          self.applyFilters(filter, true);
        }
      });

      document.addEventListener('change', function (e) {
        if (e.target.matches('[data-field-type-toggle]')) {
          self.toggleFieldType(e.target);
          return;
        }
        if (e.target.matches('[data-field-types-state]')) {
          self.filterFieldTypes();
        }
        if (e.target.matches('[data-rubric-preset]')) {
          self.syncRubricPresetHint();
        }
		if (e.target.matches('[data-builder-field-set]')) { self.syncBuilderFieldSet(); }
        var filter = e.target.closest('.rubrics-filter');
        if (filter && e.target.matches('select')) {
          self.applyFilters(filter, true);
        }
        if (self.fieldForm && e.target === self.fieldForm.elements.rubric_field_type) {
          self.syncFieldTypeOptions(e.target.value);
          self.syncNativeNumericType(e.target.value);
          self.loadTypeInfo(e.target.value);
        }
        if (e.target.matches('[data-default-editor-input]')) {
          self.syncDefaultEditorValue(e.target);
        }
        if (e.target.matches('[data-default-list-input], [data-default-list-key], [data-default-list-value]')) {
          self.syncDefaultListValue();
        }
        if (e.target.matches('[data-settings-map-key], [data-settings-map-label]')) {
          self.syncSettingsMap(e.target.closest('[data-settings-map]'));
        }
        if (e.target.matches('[name="rubric_field_settings[option_source]"]')) {
          self.syncOptionSourceFields(e.target.closest('[data-field-settings]'));
        }
        if (e.target.matches('[data-builder-conditions-enabled]')) {
          self.syncBuilderRuntime();
          self.setBuilderDirty(true);
        }
		var changedCondition = e.target.closest('[data-builder-condition]');
		if (changedCondition && e.target.matches('[data-builder-condition-enabled], [data-builder-condition-mode], [data-builder-condition-operator], [data-builder-condition-join], [data-builder-condition-options-enabled], [data-builder-condition-value-action], [data-builder-condition-action-select]')) {
			self.syncBuilderCondition(changedCondition);
		}
        if (self.builderForm && self.builderForm.contains(e.target)) {
          self.captureBuilderInspector();
          if (e.target.matches('[data-builder-group]')) { self.moveSelectedBuilderField(e.target.value); }
          if (e.target.matches('input[name="width"]')) { self.applyBuilderWidth(e.target.value); }
        }
        if (e.target.matches('[data-admin-view-format], input[name="admin_view_width"]')) {
          self.captureAdminViewInspector();
        }
      });

      document.addEventListener('input', function (e) {
        var filter = e.target.closest('.rubrics-filter');
        if (filter && e.target.matches('input[type="search"]')) {
          clearTimeout(self.filterTimer);
          self.filterTimer = setTimeout(function () { self.applyFilters(filter, true); }, 350);
        }
        if (self.form && e.target === self.form.elements.rubric_alias) {
          self.updateRubricAliasPreview();
          clearTimeout(self.aliasTimer);
          self.aliasTimer = setTimeout(function () { self.checkRubricAlias(false); }, 250);
        }
        if (self.fieldForm && e.target === self.fieldForm.elements.rubric_field_alias) {
          clearTimeout(self.fieldAliasTimer);
          self.fieldAliasTimer = setTimeout(function () { self.checkFieldAlias(false); }, 250);
        }
        if (e.target.matches('[data-default-editor-input]')) {
          self.syncDefaultEditorValue(e.target);
        }
        if (e.target.matches('[data-default-list-input], [data-default-list-key], [data-default-list-value]')) {
          self.syncDefaultListValue();
        }
        if (e.target.matches('[data-settings-map-key], [data-settings-map-label]')) {
          self.syncSettingsMap(e.target.closest('[data-settings-map]'));
        }
        if (e.target.matches('[data-builder-search]')) { self.filterBuilderPalette(e.target.value); }
        if (e.target.matches('[data-rubric-template-tags-search]')) { self.filterTemplateTags(e.target.value); }
        if (e.target.matches('[data-field-types-search]')) { self.filterFieldTypes(); }
		if (self.builderForm && self.builderForm.contains(e.target)) {
		  self.captureBuilderInspector();
		  if (e.target.matches('[name="rubric_field_settings[options]"]')) { self.syncBuilderConditionOptionSource(e.target.value); }
		}
        if (e.target.matches('[data-admin-view-search]')) { self.renderAdminViewFields(e.target.value); }
        if (e.target.matches('[data-admin-view-label]')) { self.captureAdminViewInspector(); }
        if (self.adminViewForm && e.target === self.adminViewForm.elements.template) { self.renderAdminViewPreview(); }
      });

      document.addEventListener('focusin', function (e) {
        if (e.target && e.target.matches && e.target.matches('#rubricTemplateForm textarea, #rubricExtraTemplateForm textarea')) {
          self.activeTemplateTextarea = e.target;
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          if (document.querySelector('.rubrics-code-field.is-fullscreen')) { self.closeCodeFullscreen(); }
          else { self.closeTemplateTagPalette(); }
        }
      });

      window.addEventListener('popstate', function () {
        self.applyFilterUrl(window.location.href, false);
      });

      document.addEventListener('adminx:drawer:closed', function (e) {
        var drawer = e.detail && e.detail.drawer ? e.detail.drawer : null;
        if (drawer && drawer.id === 'rubricFieldDrawer') { self.resetFieldForm(); }
        if (drawer && (drawer.id === 'rubricTemplatesDrawer' || drawer.id === 'rubricExtraTemplateDrawer')) { self.closeTemplateTagPalette(); }
      });

      document.addEventListener('dragstart', function (e) {
        if (e.target.closest('[data-admin-view-item]')) { self.adminViewDragStart(e); return; }
        self.dragStart(e);
      });
      document.addEventListener('dragover', function (e) {
        if (self.adminViewDragSource) { self.adminViewDragOver(e); return; }
        self.dragOver(e);
      });
      document.addEventListener('drop', function (e) {
        if (self.adminViewDragSource) { self.adminViewDrop(e); return; }
        self.drop(e);
      });
      document.addEventListener('dragend', function () { self.adminViewDragEnd(); self.dragEnd(); });

      if (this.form) {
        this.form.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitRubric();
        });
      }
      if (this.fieldForm) {
        this.fieldForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitField();
        });
      }
      if (this.groupForm) {
        this.groupForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitGroup();
        });
      }
      if (this.templateForm) {
        this.templateForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitMainTemplate();
        });
      }
      if (this.extraTemplateForm) {
        this.extraTemplateForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitExtraTemplate();
        });
      }
      if (this.builderForm) {
        this.builderForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.saveBuilder();
        });
      }
      if (this.adminViewForm) {
        this.adminViewForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.saveAdminView();
        });
      }
    },

    filterUrl: function (form) {
      var params = new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function (key) {
        var value = String(params.get(key) || '');
        if (value === '') { params.delete(key); }
      });
      var query = params.toString();
      return (form.getAttribute('action') || (this.base() + '/rubrics')) + (query ? '?' + query : '');
    },

    applyFilters: function (form, push) {
      this.applyFilterUrl(this.filterUrl(form), push);
    },

    applyFilterUrl: function (url, push) {
      var self = this;
      Adminx.Loader.show();
      fetch(url, { method: 'GET', headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
        .then(function (res) {
          return res.text().then(function (html) {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            self.replaceList(html, url, push);
          });
        })
        .catch(function () { Adminx.Toast.show('Не удалось применить фильтры', 'error'); })
        .finally(function () { Adminx.Loader.hide(); });
    },

    replaceList: function (html, url, push) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      ['.rubrics-summary', '.rubrics-import-card', '.rubrics-panel'].forEach(function (selector) {
        var next = doc.querySelector(selector);
        var current = document.querySelector(selector);
        if (next && current) { current.replaceWith(next); }
      });
      if (push && window.history && window.history.pushState) {
        window.history.pushState({ adminxRubricsFilters: true }, '', url);
      }
    },

    activateMainTab: function (name) {
      name = name || 'rubrics';
      document.querySelectorAll('[data-rubrics-main-tab]').forEach(function (tab) {
        tab.setAttribute('aria-selected', tab.getAttribute('data-rubrics-main-tab') === name ? 'true' : 'false');
      });
      document.querySelectorAll('[data-rubrics-main-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-rubrics-main-panel') !== name;
      });
    },

    resetFilters: function () {
      var form = document.querySelector('.rubrics-filter');
      if (!form) { return; }
      form.reset();
      Array.prototype.forEach.call(form.querySelectorAll('input, select'), function (el) {
        if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
      });
      this.applyFilters(form, true);
    },

    toggleFieldType: function (input) {
      if (!input || !this.form) { return; }
      var self = this;
      var type = input.getAttribute('data-field-type-toggle') || '';
      var enabled = input.checked;
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      data.append('enabled', enabled ? '1' : '0');
      input.disabled = true;
      this.ajax(this.base() + '/rubrics/field-types/' + encodeURIComponent(type) + '/toggle', data, function (json) {
        var payload = json.data || {};
        var result = payload.type || {};
        var actual = !!result.enabled;
        var row = input.closest('[data-field-type-row]');
        input.checked = actual;
        input.disabled = false;
        input.setAttribute('aria-label', (actual ? 'Отключить' : 'Включить') + ' тип ' + (result.name || type));
        var switchRoot = input.closest('.rubrics-type-switch');
        if (switchRoot) {
          switchRoot.setAttribute('data-tooltip', actual ? 'Убрать из выбора новых полей' : 'Добавить в выбор новых полей');
        }
        if (row) {
          row.setAttribute('data-state', actual ? 'enabled' : 'disabled');
          row.classList.toggle('is-disabled', !actual);
        }
        var option = null;
        if (self.fieldForm) {
          Array.prototype.some.call(self.fieldForm.querySelectorAll('[data-field-type-option]'), function (candidate) {
            if (candidate.value !== type) { return false; }
            option = candidate;
            return true;
          });
        }
        if (option) {
          var label = option.getAttribute('data-field-type-label') || result.name || type;
          option.setAttribute('data-enabled', actual ? '1' : '0');
          option.textContent = label + (actual ? '' : ' (отключён)');
        }
        var summary = payload.summary || {};
        document.querySelectorAll('[data-field-types-enabled]').forEach(function (node) {
          if (typeof summary.enabled !== 'undefined') { node.textContent = summary.enabled; }
        });
        document.querySelectorAll('[data-field-types-disabled]').forEach(function (node) {
          if (typeof summary.disabled !== 'undefined') { node.textContent = summary.disabled; }
        });
        var currentType = self.fieldForm && self.fieldForm.elements.id.value
          ? self.fieldForm.elements.rubric_field_type.value
          : '';
        self.syncFieldTypeOptions(currentType);
        self.filterFieldTypes();
        Adminx.Toast.show(json.message || (actual ? 'Тип поля включён' : 'Тип поля отключён'), 'success');
      }, function (json) {
        input.checked = !enabled;
        input.disabled = false;
        Adminx.Toast.show(json.message || 'Не удалось изменить состояние типа поля', 'error');
      });
    },

    filterFieldTypes: function () {
      var search = document.querySelector('[data-field-types-search]');
      var state = document.querySelector('[data-field-types-state]');
      var needle = search ? String(search.value || '').trim().toLowerCase() : '';
      var selected = state ? String(state.value || '') : '';
      var visible = 0;
      document.querySelectorAll('[data-field-type-row]').forEach(function (row) {
        var matchesSearch = !needle || String(row.getAttribute('data-search') || '').indexOf(needle) !== -1;
        var rowState = row.getAttribute('data-state') || '';
        var isNew = row.getAttribute('data-new') === '1';
        var usage = parseInt(row.getAttribute('data-usage') || '0', 10);
        var matchesState = !selected
          || (selected === 'used' ? usage > 0 : (selected === 'new' ? isNew : rowState === selected));
        row.hidden = !(matchesSearch && matchesState);
        if (!row.hidden) { visible += 1; }
      });
      var empty = document.querySelector('[data-field-types-empty]');
      if (empty) { empty.hidden = visible > 0; }
    },

    syncFieldTypeOptions: function (currentType) {
      if (!this.fieldForm || !this.fieldForm.elements.rubric_field_type) { return; }
      var select = this.fieldForm.elements.rubric_field_type;
      currentType = String(currentType || '');
      Array.prototype.forEach.call(select.querySelectorAll('[data-field-type-option]'), function (option) {
        option.disabled = option.getAttribute('data-enabled') !== '1' && option.value !== currentType;
      });
      if (currentType) {
        select.value = currentType;
        return;
      }
      var selected = select.options[select.selectedIndex];
      if (!selected || selected.disabled) {
        var first = select.querySelector('[data-field-type-option][data-enabled="1"]');
        select.value = first ? first.value : '';
      }
    },

    ajax: function (url, data, success, error) {
      Adminx.Loader.show();
      fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          return res.json().then(function (json) {
            if (!res.ok || json.success === false) {
              var err = new Error(json.message || 'Ошибка запроса');
              err.payload = json;
              throw err;
            }
            return json;
          });
        })
        .then(function (json) {
          if (success) { success(json); }
        })
        .catch(function (err) {
          if (error) { error(err.payload || { message: err.message }); }
          else { Adminx.Toast.show(err.message || 'Ошибка запроса', 'error'); }
        })
        .finally(function () { Adminx.Loader.hide(); });
    },

    fillRubricNew: function (purpose) {
      this.clearErrors(this.form);
      this.form.reset();
      this.form.elements.id.value = '';
      this.form.elements.rubric_template_id.value = '1';
      var isDirectory = purpose === 'directory';
      if (this.form.elements.rubric_purpose) {
        this.form.elements.rubric_purpose.value = isDirectory ? 'directory' : 'content';
      }
      var presetWrap = this.form.querySelector('[data-rubric-preset-wrap]');
      if (presetWrap) { presetWrap.hidden = false; }
      if (this.form.elements.rubric_preset) { this.form.elements.rubric_preset.value = ''; }
      this.syncRubricPresetHint();
      this.setCodeValue(this.form.elements.rubric_code_start, '');
      this.setCodeValue(this.form.elements.rubric_code_end, '');
      this.setCodeValue(this.form.elements.rubric_start_code, '');
      if (this.form.elements.rubric_template_id.selectedIndex < 0) { this.form.elements.rubric_template_id.selectedIndex = 0; }
      document.getElementById('rubricDrawerTitle').textContent = isDirectory ? 'Новый справочник' : 'Новая рубрика';
      var subtitle = this.form.closest('.drawer').querySelector('[data-rubric-drawer-subtitle]');
      if (subtitle) {
        subtitle.textContent = isDirectory
          ? 'Настройте структуру записей справочника.'
          : 'Базовые параметры рубрики.';
      }
      var submitLabel = this.form.querySelector('[data-rubric-submit-label]');
      if (submitLabel) { submitLabel.textContent = isDirectory ? 'Создать справочник' : 'Сохранить'; }
      this.setHint('[data-rubric-alias-state]', '');
      this.updateRubricAliasPreview();
      this.refreshEditors();
    },

    fillRubricEdit: function (row) {
      if (!row) { return; }
      var self = this;
      this.clearErrors(this.form);
      fetch(this.base() + '/rubrics/' + row.getAttribute('data-id'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var item = json.data || {};
          self.form.elements.id.value = item.Id || '';
          self.form.elements.rubric_title.value = item.rubric_title || '';
          self.form.elements.rubric_alias.value = item.rubric_alias || '';
          if (self.form.elements.rubric_purpose) {
            self.form.elements.rubric_purpose.value = item.rubric_purpose === 'directory' ? 'directory' : 'content';
          }
          var presetWrap = self.form.querySelector('[data-rubric-preset-wrap]');
          if (presetWrap) { presetWrap.hidden = true; }
          self.form.elements.rubric_template_id.value = item.rubric_template_id || 1;
          if (self.form.elements.rubric_template_id.selectedIndex < 0) { self.form.elements.rubric_template_id.selectedIndex = 0; }
          self.form.elements.rubric_docs_active.value = String(item.rubric_docs_active || 0);
          self.form.elements.rubric_meta_gen.value = String(item.rubric_meta_gen || 0);
          self.form.elements.rubric_alias_history.value = String(item.rubric_alias_history || 0);
          self.form.elements.rubric_description.value = item.rubric_description || '';
          self.setCodeValue(self.form.elements.rubric_code_start, item.rubric_code_start || '');
          self.setCodeValue(self.form.elements.rubric_code_end, item.rubric_code_end || '');
          self.setCodeValue(self.form.elements.rubric_start_code, item.rubric_start_code || '');
          document.getElementById('rubricDrawerTitle').textContent = (item.rubric_purpose === 'directory'
            ? 'Редактирование справочника #'
            : 'Редактирование рубрики #') + item.Id;
          var subtitle = self.form.closest('.drawer').querySelector('[data-rubric-drawer-subtitle]');
          if (subtitle) {
            subtitle.textContent = item.rubric_purpose === 'directory'
              ? 'Настройка структуры записей справочника.'
              : 'Базовые параметры рубрики.';
          }
          var submitLabel = self.form.querySelector('[data-rubric-submit-label]');
          if (submitLabel) { submitLabel.textContent = 'Сохранить'; }
          self.setHint('[data-rubric-alias-state]', '');
          self.updateRubricAliasPreview();
          self.refreshEditors();
        });
    },

    submitRubric: function () {
      var self = this;
      var id = this.form.elements.id.value;
      var isDirectory = this.form.elements.rubric_purpose
        && this.form.elements.rubric_purpose.value === 'directory';
      this.saveEditors();
      this.clearErrors(this.form);
      this.checkRubricAlias(true, function (ok) {
        if (!ok) { return; }
        self.ajax(self.base() + '/rubrics' + (id ? '/' + id : ''), new FormData(self.form), function (json) {
          Adminx.Toast.show(json.message || 'Рубрика сохранена', 'success');
          if (Adminx.Drawer) { Adminx.Drawer.close(); }
          if (isDirectory) {
            window.location.href = self.base() + '/directories';
            return;
          }
          self.applyFilterUrl(window.location.href, false);
        }, function (json) { self.showErrors(self.form, json); });
      });
    },

    syncRubricPresetHint: function () {
      if (!this.form || !this.form.elements.rubric_preset) { return; }
      var select = this.form.elements.rubric_preset;
      var option = select.options[select.selectedIndex];
      var hint = this.form.querySelector('[data-rubric-preset-hint]');
      if (hint) {
        hint.textContent = option ? option.getAttribute('data-description') || '' : '';
      }
    },

    updateRubricAliasPreview: function () {
      if (!this.form) { return; }
      var output = this.form.querySelector('[data-rubric-alias-preview]');
      if (!output) { return; }
      var pattern = String(this.form.elements.rubric_alias.value || '').replace(/^\/+|\/+$/g, '');
      var now = new Date();
      var pad = function (value) { return String(value).padStart(2, '0'); };
      var replacements = {
        '%d': pad(now.getDate()), '%m': pad(now.getMonth() + 1), '%Y': String(now.getFullYear()),
        '%y': String(now.getFullYear()).slice(-2), '%H': pad(now.getHours()), '%M': pad(now.getMinutes()), '%S': pad(now.getSeconds())
      };
      Object.keys(replacements).forEach(function (token) { pattern = pattern.split(token).join(replacements[token]); });
      output.textContent = '/' + (pattern ? pattern + '/' : '') + 'document-alias';
    },

    checkRubricAlias: function (required, done) {
      var alias = this.form.elements.rubric_alias.value.trim();
      var id = this.form.elements.id.value || 0;
      if (!alias && !required) {
        this.setHint('[data-rubric-alias-state]', '');
        if (done) { done(false); }
        return;
      }
      var self = this;
      fetch(this.base() + '/rubrics/alias-check?alias=' + encodeURIComponent(alias) + '&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var data = json.data || {};
          self.setHint('[data-rubric-alias-state]', data.message || '', data.valid && data.available);
          if (done) { done(!!(data.valid && data.available)); }
        });
    },

    deleteRubric: function (row) {
      if (!row || !window.confirm('Удалить рубрику «' + row.getAttribute('data-title') + '»?')) { return; }
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      var self = this;
      this.ajax(this.base() + '/rubrics/' + row.getAttribute('data-id') + '/delete', data, function () {
        Adminx.Toast.show('Рубрика удалена', 'success');
        self.applyFilterUrl(window.location.href, false);
      });
    },

    openAdminView: function (row) {
      if (!row) { return; }
      this.currentRubricId = parseInt(row.getAttribute('data-id'), 10) || 0;
      this.adminViewSelected = '';
      this.loadAdminView();
    },

    loadAdminView: function () {
      var self = this;
      if (!this.currentRubricId || !this.adminViewForm) { return; }
      Adminx.Loader.show();
      fetch(this.base() + '/rubrics/' + this.currentRubricId + '/admin-view', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok || json.success === false) { throw new Error(json.message || 'Не удалось загрузить представление'); }
          return json.data || {};
        });
      }).then(function (data) {
        var rubric = data.rubric || {};
        self.adminViewFields = data.fields || [];
        self.adminViewPreview = data.preview || [];
        self.adminViewState = data.view || { mode: 'standard', items: [], template: '' };
        self.adminViewState.items = Array.isArray(self.adminViewState.items) ? self.adminViewState.items : [];
        self.adminViewState.template = self.adminViewState.template || '';
        self.adminViewForm.elements.rubric_id.value = self.currentRubricId;
        self.adminViewForm.elements.template.value = self.adminViewState.template;
        document.getElementById('rubricAdminViewTitle').textContent = 'Вид документов: ' + (rubric.rubric_title || '');
        var subtitle = document.querySelector('[data-admin-view-subtitle]');
        if (subtitle) { subtitle.textContent = 'Рубрика #' + self.currentRubricId + ' · настройка действует только в панели управления.'; }
        var documentsLink = document.querySelector('[data-admin-view-documents]');
        if (documentsLink) { documentsLink.href = self.base() + '/documents?rubric_id=' + self.currentRubricId; }
        self.renderAdminView();
      }).catch(function (error) {
        Adminx.Toast.show(error.message || 'Не удалось загрузить представление', 'error');
      }).finally(function () { Adminx.Loader.hide(); });
    },

    renderAdminView: function () {
      if (!this.adminViewState) { return; }
      var self = this;
      document.querySelectorAll('[data-admin-view-mode]').forEach(function (button) {
        button.setAttribute('aria-pressed', button.getAttribute('data-admin-view-mode') === self.adminViewState.mode ? 'true' : 'false');
      });
      var builder = document.querySelector('[data-admin-view-builder]');
      if (builder) { builder.classList.toggle('is-standard', this.adminViewState.mode === 'standard'); }
      var custom = document.querySelector('[data-admin-view-custom]');
      if (custom) { custom.hidden = this.adminViewState.mode !== 'custom'; }
      this.renderAdminViewFields('');
      this.renderAdminViewItems();
      this.renderAdminViewInspector();
      this.renderAdminViewTokens();
      this.renderAdminViewPreview();
    },

    renderAdminViewFields: function (search) {
      var root = document.querySelector('[data-admin-view-fields]');
      if (!root) { return; }
      var needle = String(search || '').trim().toLowerCase();
      var selected = {};
      (this.adminViewState ? this.adminViewState.items : []).forEach(function (item) { selected[item.source] = true; });
      var fields = this.adminViewFields.filter(function (field) {
        return !needle || (field.title + ' ' + field.alias + ' ' + field.type).toLowerCase().indexOf(needle) !== -1;
      });
      root.innerHTML = fields.length ? fields.map(function (field) {
        var used = !!selected[field.source];
        return '<button class="rubrics-admin-view-field' + (used ? ' is-added' : '') + '" type="button" data-admin-view-add="' + esc(field.source) + '"' + (used ? ' disabled' : '') + '>'
          + '<span class="rubrics-admin-view-field-icon"><i class="ti ti-' + (used ? 'check' : 'plus') + '"></i></span>'
          + '<span><b>' + esc(field.title) + '</b><small>' + esc(field.type) + (field.alias ? ' · ' + esc(field.alias) : '') + '</small></span>'
          + '</button>';
      }).join('') : '<div class="rubrics-admin-view-empty"><i class="ti ti-search-off"></i><span>Поля не найдены</span></div>';
      var count = document.querySelector('[data-admin-view-field-count]');
      if (count) { count.textContent = this.adminViewFields.length; }
    },

    renderAdminViewItems: function () {
      var root = document.querySelector('[data-admin-view-items]');
      if (!root || !this.adminViewState) { return; }
      var self = this;
      var items = this.adminViewState.items || [];
      root.innerHTML = items.length ? items.map(function (item, index) {
        return '<article class="rubrics-admin-view-item' + (self.adminViewSelected === item.source ? ' is-selected' : '') + '" draggable="true" data-admin-view-item data-source="' + esc(item.source) + '">'
          + '<button class="rubrics-admin-view-item-grip" type="button" aria-label="Перетащить поле"><i class="ti ti-grip-vertical"></i></button>'
          + '<span class="rubrics-admin-view-item-order">' + (index + 1) + '</span>'
          + '<span class="rubrics-admin-view-item-main"><b>' + esc(item.label) + '</b><small>' + esc(item.type || '') + ' · ' + esc(item.format || 'text') + '</small></span>'
          + '<span class="badge badge-gray">' + esc(item.width || 'medium') + '</span>'
          + '<span class="rubrics-admin-view-item-actions">'
          + '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-admin-view-move="-1" data-source="' + esc(item.source) + '" data-tooltip="Выше" aria-label="Выше"' + (index === 0 ? ' disabled' : '') + '><i class="ti ti-chevron-up"></i></button>'
          + '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-admin-view-move="1" data-source="' + esc(item.source) + '" data-tooltip="Ниже" aria-label="Ниже"' + (index === items.length - 1 ? ' disabled' : '') + '><i class="ti ti-chevron-down"></i></button>'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-admin-view-remove="' + esc(item.source) + '" data-tooltip="Убрать" aria-label="Убрать"><i class="ti ti-x"></i></button>'
          + '</span></article>';
      }).join('') : '<div class="rubrics-admin-view-empty rubrics-admin-view-empty-large"><i class="ti ti-layout-list"></i><b>Поля не добавлены</b><span>Выберите их в левой колонке. Название, состояние и действия документа останутся системными.</span></div>';
      var count = document.querySelector('[data-admin-view-item-count]');
      if (count) { count.textContent = items.length + ' / 10'; }
    },

    renderAdminViewInspector: function () {
      var empty = document.querySelector('[data-admin-view-inspector-empty]');
      var form = document.querySelector('[data-admin-view-inspector]');
      var item = this.adminViewItem(this.adminViewSelected);
      if (empty) { empty.hidden = !!item; }
      if (form) { form.hidden = !item; }
      if (!item || !form) { return; }
      form.querySelector('[data-admin-view-label]').value = item.label || '';
      form.querySelector('[data-admin-view-format]').value = item.format || 'text';
      var width = form.querySelector('input[name="admin_view_width"][value="' + (item.width || 'medium') + '"]');
      if (width) { width.checked = true; }
      var field = this.adminViewField(item.source);
      var meta = form.querySelector('[data-admin-view-field-meta]');
      if (meta && field) {
        meta.innerHTML = '<span><i class="ti ti-tag"></i>' + esc(field.alias || 'без алиаса') + '</span><span><i class="ti ti-box"></i>' + esc(field.type || '') + '</span><code>[field:' + esc(field.alias || field.id) + ']</code>';
      }
    },

    renderAdminViewTokens: function () {
      var root = document.querySelector('[data-admin-view-tokens]');
      if (!root || !this.adminViewState) { return; }
      var tokens = [
        { token: '[document:title]', label: 'Название' },
        { token: '[document:id]', label: 'ID' },
        { token: '[document:alias]', label: 'Alias' }
      ];
      this.adminViewState.items.forEach(function (item) {
        tokens.push({ token: '[field:' + (item.alias || item.field_id) + ']', label: item.label });
      });
      root.innerHTML = tokens.map(function (item) {
        return '<button type="button" data-admin-view-token="' + esc(item.token) + '"><span>' + esc(item.label) + '</span><code>' + esc(item.token) + '</code></button>';
      }).join('');
    },

    renderAdminViewPreview: function () {
      var root = document.querySelector('[data-admin-view-preview]');
      if (!root || !this.adminViewState) { return; }
      var count = document.querySelector('[data-admin-view-preview-count]');
      if (count) { count.textContent = this.adminViewPreview.length; }
      if (this.adminViewState.mode === 'standard') {
        root.innerHTML = '<div class="rubrics-admin-view-standard"><i class="ti ti-list-details"></i><div><b>Стандартный список документов</b><span>ID, название, рубрика, состояние, дата и системные действия.</span></div></div>';
        return;
      }
      if (!this.adminViewPreview.length) {
        root.innerHTML = '<div class="rubrics-admin-view-empty"><i class="ti ti-file-off"></i><span>В рубрике пока нет документов</span></div>';
        return;
      }
      var self = this;
      if (this.adminViewState.mode === 'table') {
        root.innerHTML = '<div class="rubrics-admin-view-preview-table"><table><thead><tr><th>Документ</th>'
          + this.adminViewState.items.map(function (item) { return '<th>' + esc(item.label) + '</th>'; }).join('')
          + '<th></th></tr></thead><tbody>' + this.adminViewPreview.map(function (document) {
            return '<tr><td><b>' + esc(document.document_title) + '</b><small>#' + esc(document.Id) + '</small></td>'
              + self.adminViewState.items.map(function (item) { return '<td>' + self.adminViewValueHtml(document, item) + '</td>'; }).join('')
              + '<td><i class="ti ti-pencil"></i></td></tr>';
          }).join('') + '</tbody></table></div>';
        return;
      }
      root.innerHTML = '<div class="rubrics-admin-view-preview-grid">' + this.adminViewPreview.map(function (document) {
        var content = self.adminViewState.mode === 'custom'
          ? self.adminViewCustomHtml(document)
          : '<div class="rubrics-admin-view-preview-fields">' + self.adminViewState.items.map(function (item) {
              return '<div class="is-' + esc(item.width || 'medium') + '"><small>' + esc(item.label) + '</small>' + self.adminViewValueHtml(document, item) + '</div>';
            }).join('') + '</div>';
        return '<article class="rubrics-admin-view-preview-card"><header><div><b>' + esc(document.document_title) + '</b><small>#' + esc(document.Id) + ' · ' + esc(document.document_alias || 'без alias') + '</small></div><i class="ti ti-pencil"></i></header><div class="rubrics-admin-view-preview-content">' + content + '</div></article>';
      }).join('') + '</div>';
    },

    adminViewValueHtml: function (document, item) {
      var values = document.admin_field_values || {};
      var value = values[item.source] || { text: '', image: '' };
      if (item.format === 'image') {
        return value.image ? '<img class="rubrics-admin-view-preview-image" src="' + esc(value.image) + '" alt="">' : '<span class="text-muted">—</span>';
      }
      if (item.format === 'badge') { return '<span class="badge badge-blue">' + esc(value.text || '—') + '</span>'; }
      return '<span>' + esc(value.text || '—') + '</span>';
    },

    adminViewCustomHtml: function (document) {
      var template = this.adminViewForm ? this.adminViewForm.elements.template.value : '';
      if (!template) { template = '<strong>[document:title]</strong><small>#[document:id] · [document:alias]</small>'; }
      var replace = {
        '[document:id]': String(document.Id || ''),
        '[document:title]': esc(document.document_title || ''),
        '[document:alias]': esc(document.document_alias || '')
      };
      var values = document.admin_field_values || {};
      (this.adminViewState.items || []).forEach(function (item) {
        var value = values[item.source] || { text: '' };
        replace['[field:' + item.field_id + ']'] = esc(value.text || '');
        if (item.alias) { replace['[field:' + item.alias + ']'] = esc(value.text || ''); }
      });
      Object.keys(replace).forEach(function (token) { template = template.split(token).join(replace[token]); });
      var parsed = new DOMParser().parseFromString('<div>' + template + '</div>', 'text/html');
      parsed.querySelectorAll('script,style,iframe,object,embed,form').forEach(function (node) { node.remove(); });
      parsed.querySelectorAll('*').forEach(function (node) {
        Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
          if (/^on/i.test(attribute.name) || /javascript\s*:/i.test(attribute.value)) { node.removeAttribute(attribute.name); }
        });
      });
      return parsed.body.firstElementChild ? parsed.body.firstElementChild.innerHTML : '';
    },

    setAdminViewMode: function (mode) {
      if (!this.adminViewState || ['standard', 'table', 'cards', 'custom'].indexOf(mode) === -1) { return; }
      this.adminViewState.mode = mode;
      this.renderAdminView();
    },

    addAdminViewItem: function (source) {
      if (!this.adminViewState || this.adminViewState.items.length >= 10 || this.adminViewItem(source)) {
        if (this.adminViewState && this.adminViewState.items.length >= 10) { Adminx.Toast.show('Можно добавить не больше 10 полей', 'warning'); }
        return;
      }
      var field = this.adminViewField(source);
      if (!field) { return; }
      this.adminViewState.items.push({ source: field.source, field_id: field.id, label: field.title, format: field.format || 'text', width: 'medium', type: field.type, alias: field.alias || '' });
      this.adminViewSelected = source;
      if (this.adminViewState.mode === 'standard') { this.adminViewState.mode = 'table'; }
      this.renderAdminView();
    },

    removeAdminViewItem: function (source) {
      if (!this.adminViewState) { return; }
      this.adminViewState.items = this.adminViewState.items.filter(function (item) { return item.source !== source; });
      if (this.adminViewSelected === source) { this.adminViewSelected = ''; }
      this.renderAdminView();
    },

    selectAdminViewItem: function (source) {
      this.adminViewSelected = source || '';
      this.renderAdminViewItems();
      this.renderAdminViewInspector();
    },

    captureAdminViewInspector: function () {
      var item = this.adminViewItem(this.adminViewSelected);
      var form = document.querySelector('[data-admin-view-inspector]');
      if (!item || !form) { return; }
      item.label = form.querySelector('[data-admin-view-label]').value.trim() || (this.adminViewField(item.source) || {}).title || item.label;
      item.format = form.querySelector('[data-admin-view-format]').value || 'text';
      var width = form.querySelector('input[name="admin_view_width"]:checked');
      item.width = width ? width.value : 'medium';
      this.renderAdminViewItems();
      this.renderAdminViewTokens();
      this.renderAdminViewPreview();
    },

    moveAdminViewItem: function (source, direction) {
      if (!this.adminViewState || !direction) { return; }
      var index = this.adminViewState.items.findIndex(function (item) { return item.source === source; });
      var target = index + direction;
      if (index < 0 || target < 0 || target >= this.adminViewState.items.length) { return; }
      var item = this.adminViewState.items.splice(index, 1)[0];
      this.adminViewState.items.splice(target, 0, item);
      this.renderAdminView();
    },

    adminViewDragStart: function (event) {
      var row = event.target.closest('[data-admin-view-item]');
      if (!row) { return; }
      this.adminViewDragSource = row.getAttribute('data-source') || '';
      row.classList.add('is-dragging');
      if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.adminViewDragSource); }
    },

    adminViewDragOver: function (event) {
      var target = event.target.closest('[data-admin-view-item]');
      if (!target || target.getAttribute('data-source') === this.adminViewDragSource) { return; }
      event.preventDefault();
      document.querySelectorAll('[data-admin-view-item]').forEach(function (item) { item.classList.remove('is-drop-before', 'is-drop-after'); });
      var rect = target.getBoundingClientRect();
      target.classList.add(event.clientY < rect.top + rect.height / 2 ? 'is-drop-before' : 'is-drop-after');
    },

    adminViewDrop: function (event) {
      var target = event.target.closest('[data-admin-view-item]');
      if (!target || !this.adminViewState) { this.adminViewDragEnd(); return; }
      event.preventDefault();
      var sourceIndex = this.adminViewState.items.findIndex(function (item) { return item.source === this.adminViewDragSource; }, this);
      var targetSource = target.getAttribute('data-source');
      var targetIndex = this.adminViewState.items.findIndex(function (item) { return item.source === targetSource; });
      if (sourceIndex >= 0 && targetIndex >= 0 && sourceIndex !== targetIndex) {
        var moved = this.adminViewState.items.splice(sourceIndex, 1)[0];
        if (sourceIndex < targetIndex) { targetIndex -= 1; }
        var rect = target.getBoundingClientRect();
        if (event.clientY >= rect.top + rect.height / 2) { targetIndex += 1; }
        this.adminViewState.items.splice(targetIndex, 0, moved);
      }
      this.adminViewDragEnd();
      this.renderAdminView();
    },

    adminViewDragEnd: function () {
      this.adminViewDragSource = '';
      document.querySelectorAll('[data-admin-view-item]').forEach(function (item) { item.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after'); });
    },

    setAdminViewTemplateExample: function () {
      if (!this.adminViewForm) { return; }
      this.adminViewForm.elements.template.value = '<div class="admin-document-summary">\n  <strong>[document:title]</strong>\n  <small>#[document:id] · [document:alias]</small>\n</div>';
      this.renderAdminViewPreview();
    },

    insertAdminViewToken: function (token) {
      if (!this.adminViewForm) { return; }
      var textarea = this.adminViewForm.elements.template;
      var start = textarea.selectionStart || 0;
      var end = textarea.selectionEnd || 0;
      textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
      textarea.focus();
      textarea.setSelectionRange(start + token.length, start + token.length);
      this.renderAdminViewPreview();
    },

    saveAdminView: function () {
      if (!this.adminViewState || !this.adminViewForm) { return; }
      var data = new FormData(this.adminViewForm);
      data.append('mode', this.adminViewState.mode || 'standard');
      data.append('items', JSON.stringify(this.adminViewState.items || []));
      var self = this;
      this.ajax(this.base() + '/rubrics/' + this.currentRubricId + '/admin-view', data, function (json) {
        if (json.data && json.data.view) { self.adminViewState = json.data.view; }
        Adminx.Toast.show(json.message || 'Представление документов сохранено', 'success');
        self.renderAdminView();
      });
    },

    adminViewField: function (source) {
      return this.adminViewFields.find(function (field) { return field.source === source; }) || null;
    },

    adminViewItem: function (source) {
      if (!this.adminViewState || !source) { return null; }
      return this.adminViewState.items.find(function (item) { return item.source === source; }) || null;
    },

    openTemplates: function (row) {
      if (!row) { return; }
      this.closeTemplateTagPalette();
      this.currentRubricId = parseInt(row.getAttribute('data-id'), 10) || 0;
      this.activateTemplateTab('main');
      this.loadTemplates();
    },

    loadTemplates: function () {
      var self = this;
      if (!this.currentRubricId) { return; }
      fetch(this.base() + '/rubrics/' + this.currentRubricId + '/templates', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var data = json.data || {};
          var rubric = data.rubric || {};
          self.currentTemplateFields = data.fields || [];
          document.getElementById('rubricTemplatesTitle').textContent = 'Шаблоны: ' + (rubric.rubric_title || '');
          var sub = document.querySelector('[data-rubric-templates-subtitle]');
          if (sub) { sub.textContent = 'Рубрика #' + self.currentRubricId + ', ' + (data.templates || []).length + ' дополнительных шаблонов.'; }
          self.fillMainTemplateForm(rubric);
          self.renderTemplateTags(data.fields || []);
          self.renderExtraTemplates(data.templates || []);
          self.refreshEditors();
          if (self.pendingExtraTemplateId) {
            var row = document.querySelector('[data-extra-template-row][data-id="' + self.pendingExtraTemplateId + '"]');
            self.pendingExtraTemplateId = 0;
            if (row) {
              if (Adminx.Drawer) { Adminx.Drawer.open('rubricExtraTemplateDrawer'); }
              self.fillExtraTemplateEdit(row);
            }
          }
        })
        .catch(function () { Adminx.Toast.show('Не удалось загрузить шаблоны рубрики', 'error'); });
    },

    fillMainTemplateForm: function (rubric) {
      if (!this.templateForm) { return; }
      this.templateForm.elements.rubric_id.value = this.currentRubricId;
      this.setCodeValue(this.templateForm.elements.rubric_header_template, rubric.rubric_header_template || '');
      this.setCodeValue(this.templateForm.elements.rubric_og_template, rubric.rubric_og_template || '');
      this.setCodeValue(this.templateForm.elements.rubric_template, rubric.rubric_template || '');
      this.setCodeValue(this.templateForm.elements.rubric_footer_template, rubric.rubric_footer_template || '');
      this.setCodeValue(this.templateForm.elements.rubric_teaser_template, rubric.rubric_teaser_template || '');
      this.activeTemplateTextarea = this.templateForm.elements.rubric_template;
    },

    setOpenGraphExample: function () {
      var textarea = this.templateForm ? this.templateForm.elements.rubric_og_template : null;
      var self = this;
      var markup = '<meta property="og:type" content="article">\n'
        + '<meta property="og:site_name" content="[tag:og:sitename]">\n'
        + '<meta property="og:title" content="[tag:og:title]">\n'
        + '<meta property="og:description" content="[tag:og:description]">\n'
        + '<meta property="og:url" content="[tag:og:url]">\n'
        + '<meta name="twitter:card" content="summary_large_image">\n'
        + '<meta name="twitter:title" content="[tag:og:title]">\n'
        + '<meta name="twitter:description" content="[tag:og:description]">';
      if (!textarea) { return; }
      var current = textarea._adminxCodeMirror ? textarea._adminxCodeMirror.getValue() : textarea.value;
      if (String(current || '').trim() === '') {
        this.setCodeValue(textarea, markup);
        this.activeTemplateTextarea = textarea;
        return;
      }
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Заменить OG-разметку?',
        message: 'Текущий код в этой вкладке будет заменён базовой разметкой.',
        confirmLabel: 'Заменить',
        onConfirm: function () { self.setCodeValue(textarea, markup); self.activeTemplateTextarea = textarea; }
      });
    },

    submitMainTemplate: function () {
      var self = this;
      this.saveEditors();
      this.ajax(this.base() + '/rubrics/' + this.currentRubricId + '/templates/main', new FormData(this.templateForm), function () {
        Adminx.Toast.show('Шаблоны рубрики сохранены', 'success');
        self.loadTemplates();
      });
    },

    activateTemplateTab: function (name) {
      name = name || 'main';
      this.closeTemplateTagPalette();
      document.querySelectorAll('[data-rubric-template-tab]').forEach(function (tab) {
        tab.setAttribute('aria-selected', tab.getAttribute('data-rubric-template-tab') === name ? 'true' : 'false');
      });
      document.querySelectorAll('[data-rubric-template-panel]').forEach(function (panel) {
        panel.classList.toggle('active', panel.getAttribute('data-rubric-template-panel') === name);
      });
      this.refreshEditors();
    },

    renderTemplateTags: function (fields) {
      var root = document.querySelector('[data-rubric-template-tags]');
      var count = document.querySelector('[data-rubric-template-tags-count]');
      if (!root) { return; }
      if (count) { count.textContent = String(fields.length); }
      if (!fields.length) {
        root.innerHTML = '<div class="rubrics-template-tag-empty">Поля не найдены.</div>';
        return;
      }
      root.innerHTML = fields.map(function (field) {
        return '<button class="rubrics-template-tag" type="button" data-rubric-template-tag="' + esc(field.tag) + '">'
          + '<code>' + esc(field.tag) + '</code><span>' + esc(field.title) + ' · ' + esc(field.type_label) + '</span></button>';
      }).join('');
    },

	openTemplateTagPalette: function (field) {
      var palette = document.querySelector('[data-rubric-template-tags-panel]');
      var textarea = field ? field.querySelector('textarea[data-code-editor]') : null;
      var anchor = textarea && textarea._adminxCodeMirror ? textarea._adminxCodeMirror.getWrapperElement() : textarea;
      var search;
      if (!palette || !field || !textarea) { return; }
	  this.activeTemplateTextarea = textarea;
	  this.loadTemplateRegistryTags();
	  field.insertBefore(palette, anchor || field.firstChild);
      palette.hidden = false;
      search = palette.querySelector('[data-rubric-template-tags-search]');
      if (search) {
        search.value = '';
        this.filterTemplateTags('');
        search.focus();
      }
	},

	loadTemplateRegistryTags: function () {
	  var self = this;
	  if (this.templateRegistryLoaded || this.templateRegistryLoading) { return; }
	  this.templateRegistryLoading = true;
	  fetch(this.base() + '/rubrics/template-tags', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
		.then(function (res) { return res.json(); })
		.then(function (json) {
		  self.renderTemplateRegistryTags((json.data && json.data.groups) || []);
		  self.templateRegistryLoaded = true;
		})
		.catch(function () { Adminx.Toast.show('Не удалось загрузить реестр тегов', 'error'); })
		.finally(function () { self.templateRegistryLoading = false; });
	},

	renderTemplateRegistryTags: function (groups) {
	  var nav = document.querySelector('[data-rubric-template-tag-nav]');
	  var root = document.querySelector('[data-rubric-template-tag-groups]');
	  if (!nav || !root) { return; }
	  groups.forEach(function (group, index) {
		var items = group && Array.isArray(group.items) ? group.items : [];
		if (!items.length) { return; }
		var name = 'registry-' + index;
		nav.insertAdjacentHTML('beforeend', '<button type="button" data-rubric-template-tag-tab="' + name + '"><span>'
		  + esc(group.title || 'Теги') + '</span><b>' + items.length + '</b></button>');
		var buttons = items.map(function (tag) {
		  var select = tag.select ? ' data-rubric-template-tag-select="' + esc(tag.select) + '"' : '';
		  return '<button class="rubrics-template-tag" type="button" data-rubric-template-tag="' + esc(tag.value || '') + '"' + select + '>'
			+ '<code>' + esc(tag.label || tag.value || '') + '</code><span>' + esc(tag.description || '') + '</span></button>';
		}).join('');
		root.querySelector('[data-rubric-template-tags-empty]').insertAdjacentHTML('beforebegin',
		  '<section class="rubrics-template-tag-group" data-rubric-template-tag-group data-rubric-template-tag-panel="' + name + '">'
		  + '<div class="rubrics-template-tag-list">' + buttons + '</div></section>');
	  });
	},

    closeTemplateTagPalette: function () {
      var palette = document.querySelector('[data-rubric-template-tags-panel]');
      if (palette) { palette.hidden = true; }
    },

    activateTemplateTagGroup: function (name) {
      var palette = document.querySelector('[data-rubric-template-tags-panel]');
      if (!palette) { return; }
      palette.querySelectorAll('[data-rubric-template-tag-tab]').forEach(function (tab) {
        tab.classList.toggle('is-active', tab.getAttribute('data-rubric-template-tag-tab') === String(name));
      });
      palette.querySelectorAll('[data-rubric-template-tag-panel]').forEach(function (group) {
        group.classList.toggle('is-active', group.getAttribute('data-rubric-template-tag-panel') === String(name));
      });
    },

    filterTemplateTags: function (query) {
      var palette = document.querySelector('[data-rubric-template-tags-panel]');
      var shown = 0;
      if (!palette) { return; }
      query = String(query || '').trim().toLowerCase();
      palette.classList.toggle('is-searching', query !== '');
      palette.querySelectorAll('[data-rubric-template-tag]').forEach(function (button) {
        var visible = query === '' || button.textContent.toLowerCase().indexOf(query) !== -1;
        button.hidden = !visible;
        if (visible) { shown++; }
      });
      palette.querySelectorAll('[data-rubric-template-tag-group]').forEach(function (group) {
        group.hidden = query !== '' && !group.querySelector('[data-rubric-template-tag]:not([hidden])');
      });
      var empty = palette.querySelector('[data-rubric-template-tags-empty]');
      if (empty) { empty.hidden = shown > 0; }
    },

    renderExtraTemplates: function (templates) {
      var root = document.querySelector('[data-extra-template-list]');
      var count = document.querySelector('[data-extra-template-count]');
      if (count) { count.textContent = String(templates.length); }
      if (!root) { return; }
      if (!templates.length) {
        root.innerHTML = '<div class="empty-state">Дополнительные шаблоны не созданы.</div>';
        return;
      }
      root.innerHTML = templates.map(function (item) {
        return '<article class="rubrics-extra-item" data-extra-template-row data-id="' + item.id + '" data-title="' + esc(item.title) + '">'
          + '<div><b>' + esc(item.title) + '</b><small>Шаблон #' + item.id + '</small></div>'
          + '<div class="rubrics-extra-meta"><span>' + esc(item.created_label) + '</span><span>' + esc(item.size_label) + '</span><span>' + item.docs_count + ' документов</span></div>'
          + '<div class="cluster rubrics-actions">'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-edit" type="button" data-extra-template-edit data-open-drawer="rubricExtraTemplateDrawer" data-tooltip="Изменить" aria-label="Изменить"><i class="ti ti-pencil"></i></button>'
          + (item.can_delete
            ? '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-extra-template-delete data-tooltip="Удалить" aria-label="Удалить"><i class="ti ti-trash"></i></button>'
            : '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-locked" type="button" disabled data-tooltip="Используется документами" aria-label="Используется документами"><i class="ti ti-lock"></i></button>')
          + '</div></article>';
      }).join('');
    },

    fillExtraTemplateNew: function () {
      this.clearErrors(this.extraTemplateForm);
      this.extraTemplateForm.reset();
      this.extraTemplateForm.elements.id.value = '';
      this.extraTemplateForm.elements.rubric_id.value = this.currentRubricId;
      this.setCodeValue(this.extraTemplateForm.elements.template, '');
      document.getElementById('rubricExtraTemplateTitle').textContent = 'Новый дополнительный шаблон';
      this.updateExtraTemplateDocumentLink({ rubric_id: this.currentRubricId, docs_count: 0 });
      this.activeTemplateTextarea = this.extraTemplateForm.elements.template;
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricExtraTemplateDrawer'); }
      this.refreshEditors();
    },

    fillExtraTemplateEdit: function (row) {
      if (!row) { return; }
      var self = this;
      this.clearErrors(this.extraTemplateForm);
      fetch(this.base() + '/rubrics/templates/' + row.getAttribute('data-id'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var item = json.data || {};
          self.extraTemplateForm.elements.id.value = item.id || '';
          self.extraTemplateForm.elements.rubric_id.value = item.rubric_id || self.currentRubricId;
          self.extraTemplateForm.elements.title.value = item.title || '';
          self.setCodeValue(self.extraTemplateForm.elements.template, item.template || '');
          document.getElementById('rubricExtraTemplateTitle').textContent = 'Редактирование шаблона #' + item.id;
          self.updateExtraTemplateDocumentLink(item);
          self.activeTemplateTextarea = self.extraTemplateForm.elements.template;
          self.refreshEditors();
        });
    },

    updateExtraTemplateDocumentLink: function (item) {
      var link = document.querySelector('[data-extra-template-document-link]');
      var label;
      if (!link) { return; }
      item = item || {};
      if ((parseInt(item.docs_count, 10) || 0) === 1 && (parseInt(item.first_document_id, 10) || 0) > 0) {
        link.href = this.base() + '/documents/' + parseInt(item.first_document_id, 10) + '/edit';
        label = item.first_document_title
          ? (link.getAttribute('data-edit-prefix') || '') + item.first_document_title
          : link.getAttribute('data-edit-content');
      } else {
        link.href = this.base() + '/documents?rubric_id=' + (parseInt(item.rubric_id, 10) || this.currentRubricId || 0);
        label = link.getAttribute('data-documents-label');
      }
      link.querySelector('span').textContent = label;
    },

    submitExtraTemplate: function () {
      var self = this;
      var id = this.extraTemplateForm.elements.id.value;
      var rubricId = this.extraTemplateForm.elements.rubric_id.value || this.currentRubricId;
      this.clearErrors(this.extraTemplateForm);
      this.saveEditors();
      this.ajax(this.base() + (id ? '/rubrics/templates/' + id : '/rubrics/' + rubricId + '/templates'), new FormData(this.extraTemplateForm), function () {
        Adminx.Toast.show('Дополнительный шаблон сохранён', 'success');
        if (Adminx.Drawer) { Adminx.Drawer.close('rubricExtraTemplateDrawer'); }
        self.loadTemplates();
        self.applyFilterUrl(window.location.href, false);
      }, function (json) { self.showErrors(self.extraTemplateForm, json); });
    },

    deleteExtraTemplate: function (row) {
      if (!row || !window.confirm('Удалить дополнительный шаблон «' + row.getAttribute('data-title') + '»?')) { return; }
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      var self = this;
      this.ajax(this.base() + '/rubrics/templates/' + row.getAttribute('data-id') + '/delete', data, function () {
        Adminx.Toast.show('Дополнительный шаблон удалён', 'success');
        self.loadTemplates();
        self.applyFilterUrl(window.location.href, false);
      });
    },

    insertTemplateTag: function (source) {
      var textarea = this.visibleTemplateTextarea() || this.activeTemplateTextarea || (this.templateForm && this.templateForm.elements.rubric_template);
      var tag = typeof source === 'string' ? source : source.getAttribute('data-rubric-template-tag');
      var select = typeof source === 'string' ? '' : (source.getAttribute('data-rubric-template-tag-select') || '');
      var cm;
      if (!textarea || !tag) { return; }
      cm = textarea._adminxCodeMirror;
      if (cm) {
        var start = cm.indexFromPos(cm.getCursor());
        cm.replaceSelection(tag, 'around');
        cm.focus();
        if (select && tag.indexOf(select) !== -1) {
          cm.setSelection(cm.posFromIndex(start + tag.indexOf(select)), cm.posFromIndex(start + tag.indexOf(select) + select.length));
        } else {
          cm.setCursor(cm.posFromIndex(start + tag.length));
        }
        cm.save();
        this.closeTemplateTagPalette();
        return;
      }
      var start = textarea.selectionStart || 0;
      var end = textarea.selectionEnd || 0;
      textarea.value = textarea.value.slice(0, start) + tag + textarea.value.slice(end);
      if (select && tag.indexOf(select) !== -1) {
        textarea.selectionStart = start + tag.indexOf(select);
        textarea.selectionEnd = textarea.selectionStart + select.length;
      } else {
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
      }
      textarea.focus();
      this.closeTemplateTagPalette();
    },

    setCodeLintResult: function (el, message, kind) {
      if (!el) { return; }
      el.textContent = message || '';
      el.classList.remove('is-ok', 'is-error');
      if (kind) { el.classList.add(kind === 'error' ? 'is-error' : 'is-ok'); }
    },

    lintCodeField: function (field) {
      if (!field) { return; }
      var textarea = field.querySelector('textarea[data-code-editor]');
      if (!textarea) { return; }
      if (textarea._adminxCodeMirror) { textarea._adminxCodeMirror.save(); }
      var result = field.querySelector('[data-rubric-code-lint-result]');
      this.setCodeLintResult(result, 'Проверка...', '');
      var fd = new FormData();
      fd.set('_csrf', Adminx.csrf());
      fd.set('code', textarea.value || '');
      var self = this;
      Adminx.Ajax.post(this.base() + '/rubrics/template/lint', fd).then(function (payload) {
        var d = payload.data || {};
        if (d.success) {
          var okMsg = (d.data && d.data.message) || d.message || 'Синтаксис PHP без ошибок.';
          self.setCodeLintResult(result, okMsg, 'ok');
          Adminx.Toast.show(okMsg, 'success');
          return;
        }
        var detail = d.errors && d.errors.code ? d.errors.code : (d.message || 'В PHP-коде есть ошибка.');
        self.setCodeLintResult(result, detail, 'error');
        Adminx.Toast.show(d.message || 'В PHP-коде есть ошибка.', 'error');
      }).catch(function () {
        self.setCodeLintResult(result, 'Не удалось выполнить проверку.', 'error');
        Adminx.Toast.show('Ошибка сети', 'error');
      });
    },

    toggleCodeFieldFullscreen: function (field, force) {
      if (!field) { return; }
      var next = typeof force === 'boolean' ? force : !field.classList.contains('is-fullscreen');
      // Одновременно раскрыт только один редактор.
      document.querySelectorAll('.rubrics-code-field.is-fullscreen').forEach(function (other) {
        if (other !== field) { other.classList.remove('is-fullscreen'); }
      });
      field.classList.toggle('is-fullscreen', next);
      document.body.classList.toggle('rubrics-code-fullscreen-open', next);
      var button = field.querySelector('[data-rubric-code-fullscreen]');
      if (button) {
        button.setAttribute('data-tooltip', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.setAttribute('aria-label', next ? 'Свернуть редактор' : 'Развернуть редактор');
        button.innerHTML = next ? '<i class="ti ti-arrows-minimize"></i>' : '<i class="ti ti-arrows-maximize"></i>';
      }
      var textarea = field.querySelector('textarea[data-code-editor]');
      var cm = textarea ? textarea._adminxCodeMirror : null;
      if (cm) {
        var height = parseInt(textarea.getAttribute('data-height'), 10) || 360;
        setTimeout(function () {
          cm.refresh();
          cm.setSize('100%', next ? '100%' : height);
        }, 40);
      }
    },

    closeCodeFullscreen: function () {
      var field = document.querySelector('.rubrics-code-field.is-fullscreen');
      if (field) { this.toggleCodeFieldFullscreen(field, false); }
    },

    captureCodeMirror: function (wrapper) {
      if (!window.Adminx || !Adminx.CodeEditor || !wrapper) { return; }
      Adminx.CodeEditor.instances.forEach(function (editor) {
        if (editor.getWrapperElement && editor.getWrapperElement() === wrapper) {
          var textarea = editor.getTextArea();
          if (textarea && textarea.closest('#rubricTemplateForm, #rubricExtraTemplateForm')) {
            Adminx.Rubrics.activeTemplateTextarea = textarea;
          }
          if (textarea && textarea.closest('#rubricFieldForm')) {
            Adminx.Rubrics.activeFieldTemplateTextarea = textarea;
          }
        }
      });
    },

    visibleTemplateTextarea: function () {
      if (this.activeTemplateTextarea && this.activeTemplateTextarea.name && this.isCodeTextareaVisible(this.activeTemplateTextarea)) {
        return this.activeTemplateTextarea;
      }
      var panel = document.querySelector('[data-rubric-template-panel].active');
      var textarea;
      if (panel) {
        if (panel.getAttribute('data-rubric-template-panel') === 'main' && this.templateForm) {
          textarea = this.templateForm.elements.rubric_template;
          if (textarea) { return textarea; }
        }
        textarea = panel.querySelector('textarea[data-code-editor]');
        if (textarea) { return textarea; }
      }
      if (this.extraTemplateForm && !document.getElementById('rubricExtraTemplateDrawer').hidden) {
        return this.extraTemplateForm.elements.template;
      }
      return null;
    },

    isCodeTextareaVisible: function (textarea) {
      var wrapper;
      if (!textarea) { return false; }
      if (textarea._adminxCodeMirror) {
        wrapper = textarea._adminxCodeMirror.getWrapperElement();
        return !!(wrapper && wrapper.offsetParent !== null);
      }
      return textarea.offsetParent !== null;
    },

    setCodeValue: function (textarea, value) {
      if (!textarea) { return; }
      textarea.value = value || '';
      if (textarea._adminxCodeMirror) {
        textarea._adminxCodeMirror.setValue(value || '');
        textarea._adminxCodeMirror.save();
      }
    },

    saveEditors: function () {
      if (!window.Adminx || !Adminx.CodeEditor) { return; }
      Adminx.CodeEditor.instances.forEach(function (editor) { editor.save(); });
    },

    refreshEditors: function () {
      if (!window.Adminx || !Adminx.CodeEditor) { return; }
      setTimeout(function () { Adminx.CodeEditor.refreshAll(); }, 80);
    },

    openFields: function (row) {
      if (!row) { return; }
      this.currentRubricId = parseInt(row.getAttribute('data-id'), 10) || 0;
      this.loadFields();
    },

    loadFields: function () {
      var self = this;
      fetch(this.base() + '/rubrics/' + this.currentRubricId + '/fields', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var data = json.data || {};
          self.currentGroups = data.groups || [];
          self.currentFieldSetLinks = data.field_set_links || [];
		  self.linkedFieldSetsAvailable = !!data.linked_field_sets_available;
		  if (!self.linkedFieldSetsAvailable) { self.fieldSetMode = 'copy'; }
          self.currentFields = [];
          (data.grouped_fields || []).forEach(function (group) {
            (group.items || []).forEach(function (field) { self.currentFields.push(field); });
          });
          (data.available_fields || []).forEach(function (field) { self.currentFields.push(field); });
          document.getElementById('rubricFieldsTitle').textContent = 'Поля: ' + ((data.rubric || {}).rubric_title || '');
          var sub = document.querySelector('[data-rubric-fields-subtitle]');
          if (sub) { sub.textContent = 'Рубрика #' + self.currentRubricId + ' · ' + self.currentFields.length + ' полей · форма редактора документа'; }
          var conditionsToggle = document.querySelector('[data-builder-conditions-enabled]');
          if (conditionsToggle) { conditionsToggle.checked = !!((data.rubric || {}).form_conditions_enabled); }
          self.syncBuilderRuntime();
          self.builderDrafts = {};
          self.builderSelectedId = 0;
          self.setBuilderDirty(false);
          self.renderGroups(data.grouped_fields || []);
		  self.syncBuilderFieldSet();
		  self.renderLinkedFieldSets();
          self.fillGroupSelect();
          if (Adminx.Drawer) { Adminx.Drawer.open('rubricFieldsDrawer'); }
        });
    },

    renderGroups: function (groups) {
      var canvas = '';
      var palette = '';
      var placed = {};
      var availableCount = 0;
      var self = this;
      var colors = [
        ['var(--blue-100)', 'var(--blue-600)'],
        ['var(--green-100)', 'var(--green-600)'],
        ['var(--cyan-100)', 'var(--cyan-600)'],
        ['var(--amber-100)', 'var(--amber-600)'],
        ['var(--blue-50)', 'var(--blue-700)']
      ];

      groups.forEach(function (group) {
        (group.items || []).forEach(function (field) { placed[parseInt(field.Id, 10)] = true; });
      });

      this.currentFields.forEach(function (field) {
        if (placed[parseInt(field.Id, 10)]) { return; }
        availableCount++;
        palette += self.builderPaletteMarkup(field);
      });

      groups.forEach(function (group, index) {
        var color = colors[index % colors.length];
		var groupCondition = group.condition && typeof group.condition === 'object' ? group.condition : {};
        var groupAttrs = group.id ? ' draggable="true" data-group-row data-id="' + group.id + '" data-title="' + esc(group.title) + '" data-description="' + esc(group.description || '') + '" data-condition="' + esc(JSON.stringify(groupCondition)) + '"' : '';
        canvas += '<section class="rubrics-builder-group" data-field-group-drop="' + group.id + '"' + groupAttrs + '>'
          + '<div class="rubrics-builder-group-head">'
          + (group.id ? '<button class="btn btn-ghost btn-icon btn-sm rubrics-group-drag" type="button" draggable="true" data-group-drag-handle data-tooltip-right="Перетащить группу" aria-label="Перетащить группу"><i class="ti ti-grip-vertical"></i></button>' : '')
          + '<span class="icon-tile rubrics-head-icon" style="--tile-bg:' + color[0] + ';--tile-fg:' + color[1] + '"><i class="ti ' + (group.id ? 'ti-folder' : 'ti-layout-navbar') + '"></i></span>'
          + '<div><h3>' + esc(group.id ? group.title : 'Основные поля') + '</h3><p>' + esc(group.description || (group.id ? 'Группа полей документа' : 'Поля без отдельной группы')) + '</p></div>'
		  + (groupCondition.tree ? '<span class="badge badge-cyan"><i class="ti ti-adjustments-horizontal"></i>условие</span>' : '')
          + '<span class="badge badge-blue" data-builder-group-count>' + (group.items || []).length + '</span>'
          + (group.id ? '<div class="cluster rubrics-actions"><button class="btn btn-ghost btn-icon btn-sm rubrics-action-edit" type="button" data-group-edit data-tooltip-left="Изменить группу" aria-label="Изменить группу"><i class="ti ti-pencil"></i></button><button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-group-delete data-tooltip-left="Удалить группу" aria-label="Удалить группу"><i class="ti ti-trash"></i></button></div>' : '')
          + '</div><div class="rubrics-builder-grid" data-field-sortable>';
        (group.items || []).forEach(function (field) {
          canvas += self.builderFieldMarkup(field, group.id);
        });
        if (!(group.items || []).length) {
          canvas += '<div class="rubrics-builder-drop-empty"><i class="ti ti-drag-drop"></i><span>Перетащите поле в эту группу</span></div>';
        }
        canvas += '</div></section>';
      });
      var canvasRoot = document.querySelector('[data-builder-canvas]');
      var paletteRoot = document.querySelector('[data-builder-palette]');
      var count = document.querySelector('[data-builder-field-count]');
      if (canvasRoot) { canvasRoot.innerHTML = canvas || '<div class="empty-state">Добавьте первое поле рубрики.</div>'; }
      if (paletteRoot) { paletteRoot.innerHTML = palette || '<div class="rubrics-builder-palette-empty"><i class="ti ti-circle-check"></i><b>Все поля размещены</b><span>Новых полей для добавления нет.</span></div>'; }
      if (count) { count.textContent = availableCount; }
      this.updateBuilderGroupCounts();
    },

	syncBuilderFieldSet: function () {
	  var select = document.querySelector('[data-builder-field-set]');
	  var button = document.querySelector('[data-builder-field-set-apply]');
	  var exportButton = document.querySelector('[data-builder-field-set-export]');
	  var importButton = document.querySelector('[data-builder-field-set-import]');
	  var hint = document.querySelector('[data-builder-field-set-hint]');
	  var option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
	  var selectedCode = select ? String(select.value || '') : '';
	  var alreadyLinked = this.fieldSetMode === 'linked' && (this.currentFieldSetLinks || []).some(function (link) {
		return String(link.code || '') === selectedCode;
	  });
	  if (button) { button.disabled = !select || !selectedCode || !this.currentRubricId || alreadyLinked || (this.fieldSetMode === 'linked' && !this.linkedFieldSetsAvailable); }
	  if (exportButton) { exportButton.disabled = !this.currentRubricId || !this.currentFields.length; }
	  if (importButton) { importButton.disabled = !this.currentRubricId; }
	  document.querySelectorAll('[data-field-set-mode="linked"]').forEach(function (modeButton) {
		modeButton.disabled = !Adminx.Rubrics.linkedFieldSetsAvailable;
	  });
	  if (hint) {
		var description = option && select.value
		  ? (option.getAttribute('data-description') || 'Поля будут добавлены в текущую рубрику.')
		  : 'Готовая группа полей без изменения существующих данных.';
		if (!this.linkedFieldSetsAvailable) {
		  hint.textContent = 'Связанный режим станет доступен после применения миграций рубрик.';
		} else if (alreadyLinked) {
		  hint.textContent = 'Этот набор уже связан с рубрикой. Его обновления находятся ниже.';
		} else {
		  hint.textContent = this.fieldSetMode === 'linked'
			? description + ' Обновления набора можно будет применить после предварительной проверки.'
			: description;
		}
	  }
	},

	setBuilderFieldSetMode: function (mode) {
	  if (mode === 'linked' && !this.linkedFieldSetsAvailable) {
		Adminx.Toast.show('Сначала примените миграции рубрик', 'warning');
		return;
	  }
	  this.fieldSetMode = mode === 'linked' ? 'linked' : 'copy';
	  document.querySelectorAll('[data-field-set-mode]').forEach(function (button) {
		button.setAttribute('aria-pressed', button.getAttribute('data-field-set-mode') === Adminx.Rubrics.fieldSetMode ? 'true' : 'false');
	  });
	  this.syncBuilderFieldSet();
	},

	renderLinkedFieldSets: function () {
	  var root = document.querySelector('[data-builder-field-set-links]');
	  if (!root) { return; }
	  var links = this.currentFieldSetLinks || [];
	  root.hidden = !links.length;
	  if (!links.length) { root.innerHTML = ''; return; }
	  root.innerHTML = '<div class="rubrics-field-set-links-head"><i class="ti ti-link"></i><b>Связанные наборы</b><span class="badge badge-blue">' + links.length + '</span></div>'
		+ links.map(function (link) {
		  var state = !link.available ? 'Источник недоступен' : (link.update_available ? 'Есть обновление' : 'Актуален');
		  var badge = !link.available ? 'badge-red' : (link.update_available ? 'badge-amber' : 'badge-green');
		  return '<article><span><b>' + esc(link.title || link.code) + '</b><small><code>' + esc(link.code) + '</code></small></span>'
			+ '<span class="badge ' + badge + '">' + state + '</span>'
			+ '<div class="cluster">' + (link.available && link.update_available ? '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-field-set-sync="' + esc(link.code) + '" data-tooltip-left="Проверить обновление" aria-label="Проверить обновление"><i class="ti ti-refresh"></i></button>' : '')
			+ '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-field-set-detach="' + esc(link.code) + '" data-tooltip-left="Удалить связь" aria-label="Удалить связь"><i class="ti ti-unlink"></i></button></div></article>';
		}).join('');
	},

	applyBuilderFieldSet: function () {
	  var select = document.querySelector('[data-builder-field-set]');
	  var button = document.querySelector('[data-builder-field-set-apply]');
	  var code = select ? String(select.value || '') : '';
	  if (!code || !this.currentRubricId) { return; }
	  if (this.builderDirty) { Adminx.Toast.show('Сначала сохраните изменения конструктора', 'warning'); return; }
	  var self = this;
	  if (button) { button.disabled = true; button.classList.add('is-loading'); }
	  fetch(this.base() + '/rubrics/' + this.currentRubricId + '/field-sets/' + encodeURIComponent(code) + '/preview', {
		headers: { 'Accept': 'application/json' },
		credentials: 'same-origin'
	  }).then(function (response) {
		return response.json();
	  }).then(function (json) {
		var preview = json.data || {};
		if (!json.success) { throw new Error(json.message || 'Не удалось проверить набор'); }
		if (preview.conflicts && preview.conflicts.length) {
		  Adminx.Toast.show('Конфликт полей: ' + preview.conflicts.join(', '), 'error');
		  return;
		}

		Adminx.Confirm.open({
		  kind: 'warning',
		  title: 'Добавить набор «' + (preview.title || code) + '»?',
		  message: 'Будет создано полей: ' + (preview.create || 0) + '. Уже есть и останутся без изменений: ' + (preview.reuse || 0) + '.',
		  confirmLabel: 'Добавить набор',
		  onConfirm: function () {
			var data = new FormData();
			data.append('_csrf', self.form.elements._csrf.value);
			data.append('mode', self.fieldSetMode);
			self.ajax(self.base() + '/rubrics/' + self.currentRubricId + '/field-sets/' + encodeURIComponent(code) + '/apply', data, function (result) {
			  Adminx.Toast.show(result.message || 'Набор полей добавлен', 'success');
			  select.value = '';
			  self.loadFields();
			  self.applyFilterUrl(window.location.href, false);
			});
		  }
		});
	  }).catch(function (error) {
		Adminx.Toast.show(error.message || 'Не удалось проверить набор', 'error');
	  }).then(function () {
		if (button) { button.classList.remove('is-loading'); }
		self.syncBuilderFieldSet();
	  });
	},

	exportBuilderFieldSet: function () {
	  if (!this.currentRubricId || !this.currentFields.length) { return; }
	  if (this.builderDirty) { Adminx.Toast.show('Сначала сохраните изменения конструктора', 'warning'); return; }
	  var button = document.querySelector('[data-builder-field-set-export]');
	  if (button) { button.disabled = true; button.classList.add('is-loading'); }
	  var self = this;
	  fetch(this.base() + '/rubrics/' + this.currentRubricId + '/field-sets/export', {
		headers: { 'Accept': 'application/json' },
		credentials: 'same-origin'
	  }).then(function (response) {
		return response.json();
	  }).then(function (json) {
		if (!json.success) { throw new Error(json.message || 'Не удалось подготовить набор'); }
		var data = json.data || {};
		var blob = new Blob([JSON.stringify(data.descriptor || {}, null, 2) + '\n'], { type: 'application/json;charset=utf-8' });
		var url = URL.createObjectURL(blob);
		var link = document.createElement('a');
		link.href = url;
		link.download = data.filename || ('rubric-' + self.currentRubricId + '-field-set.json');
		document.body.appendChild(link);
		link.click();
		link.remove();
		setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
		Adminx.Toast.show(json.message || 'Набор полей экспортирован', 'success');
	  }).catch(function (error) {
		Adminx.Toast.show(error.message || 'Не удалось экспортировать набор', 'error');
	  }).then(function () {
		if (button) { button.classList.remove('is-loading'); }
		self.syncBuilderFieldSet();
	  });
	},

	openBuilderFieldSetImport: function () {
	  if (!this.currentRubricId) { return; }
	  if (this.builderDirty) { Adminx.Toast.show('Сначала сохраните изменения конструктора', 'warning'); return; }
	  var self = this;
	  var overlay = document.createElement('div');
	  overlay.className = 'overlay rubrics-field-set-overlay';
	  overlay.innerHTML = '<div class="modal rubrics-field-set-modal" role="dialog" aria-modal="true" aria-labelledby="rubricsFieldSetImportTitle">'
		+ '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-package-import"></i></span><div><h3 id="rubricsFieldSetImportTitle">Импорт набора полей</h3><p class="text-secondary">Проверка схемы перед добавлением в текущую рубрику.</p></div><button class="modal-close" type="button" data-field-set-import-close aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
		+ '<div class="modal-body"><label class="rubrics-field-set-dropzone" data-field-set-dropzone><i class="ti ti-file-type-json"></i><span><b>Выберите JSON набора</b><small>AVE.cms field-set, не более 256 КБ</small></span><input type="file" accept=".json,application/json" data-field-set-import-file hidden></label><div class="rubrics-field-set-import-state" data-field-set-import-state><i class="ti ti-shield-check"></i><span><b>Сначала выполняется проверка</b><small>Документы и существующие поля не меняются.</small></span></div><div data-field-set-import-preview hidden></div></div>'
		+ '<div class="modal-footer"><span class="mf-left text-secondary text-sm" data-field-set-import-name>Файл не выбран</span><button class="btn btn-ghost" type="button" data-field-set-import-close>Закрыть</button><button class="btn btn-primary" type="button" data-field-set-import-apply disabled><i class="ti ti-package-import"></i>Импортировать</button></div></div>';
	  document.body.appendChild(overlay);
	  requestAnimationFrame(function () { overlay.classList.add('show'); });
	  var fileInput = overlay.querySelector('[data-field-set-import-file]');
	  var dropzone = overlay.querySelector('[data-field-set-dropzone]');
	  var previewRoot = overlay.querySelector('[data-field-set-import-preview]');
	  var stateRoot = overlay.querySelector('[data-field-set-import-state]');
	  var applyButton = overlay.querySelector('[data-field-set-import-apply]');
	  var nameRoot = overlay.querySelector('[data-field-set-import-name]');
	  var descriptor = '';
	  var fingerprint = '';
	  var importMode = self.fieldSetMode;
	  var importHasConflicts = false;
	  var close = function () {
		overlay.classList.remove('show');
		setTimeout(function () { overlay.remove(); }, 160);
	  };
	  var showError = function (message) {
		fingerprint = '';
		importHasConflicts = true;
		applyButton.disabled = true;
		previewRoot.hidden = false;
		previewRoot.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-triangle alert-ic"></i><div><b>Набор не готов к импорту</b><p>' + esc(message) + '</p></div></div>';
		stateRoot.hidden = true;
	  };
	  var preview = function (file) {
		if (!file) { return; }
		nameRoot.textContent = file.name || 'field-set.json';
		if (file.size > 262144) { showError('Файл превышает 256 КБ'); return; }
		var reader = new FileReader();
		reader.onerror = function () { showError('Не удалось прочитать выбранный файл'); };
		reader.onload = function () {
		  descriptor = String(reader.result || '');
		  stateRoot.hidden = false;
		  stateRoot.classList.add('is-loading');
		  stateRoot.innerHTML = '<i class="ti ti-loader-2"></i><span><b>Проверяем набор</b><small>Сравниваем alias и типы полей.</small></span>';
		  previewRoot.hidden = true;
		  applyButton.disabled = true;
		  var data = new FormData();
		  data.append('_csrf', self.form.elements._csrf.value);
		  data.append('descriptor', descriptor);
		  fetch(self.base() + '/rubrics/' + self.currentRubricId + '/field-sets/import/preview', {
			method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
		  }).then(function (response) { return response.json(); }).then(function (json) {
			if (!json.success) { throw new Error(json.message || 'Набор не прошёл проверку'); }
			var result = json.data || {};
			fingerprint = result.fingerprint || '';
			var conflicts = result.conflicts || [];
			importHasConflicts = !!conflicts.length;
			stateRoot.hidden = true;
			previewRoot.hidden = false;
			previewRoot.innerHTML = '<div class="rubrics-field-set-preview-head"><span class="icon-tile" style="--tile-bg:var(--green-100);--tile-fg:var(--green-600)"><i class="ti ti-file-check"></i></span><span><b>' + esc(result.title || 'Набор полей') + '</b><small>' + esc(result.description || 'Описание не задано') + '</small></span></div>'
			  + '<div class="rubrics-field-set-preview-grid"><span><b>' + (result.field_count || 0) + '</b><small>всего полей</small></span><span><b>' + (result.create || 0) + '</b><small>будет создано</small></span><span><b>' + (result.reuse || 0) + '</b><small>уже существует</small></span><span class="' + (conflicts.length ? 'is-danger' : '') + '"><b>' + conflicts.length + '</b><small>конфликтов</small></span></div>'
			  + '<div class="rubrics-field-set-import-mode"><span>После импорта</span><div class="segmented"><button type="button" data-import-field-set-mode="copy" aria-pressed="' + (importMode === 'copy' ? 'true' : 'false') + '">Независимая копия</button><button type="button" data-import-field-set-mode="linked" aria-pressed="' + (importMode === 'linked' ? 'true' : 'false') + '"' + (!result.linked_available ? ' disabled' : '') + '>Связанный набор</button></div></div>'
			  + (conflicts.length ? '<div class="alert alert-error"><i class="ti ti-alert-triangle alert-ic"></i><div><b>Конфликт типов</b><p>' + esc(conflicts.join(', ')) + '</p></div></div>' : '<div class="alert alert-info"><i class="ti ti-copy-check alert-ic"></i><div><b>' + (result.source_schema_match ? 'Исходная схема распознана' : 'Режим копии') + '</b><p>' + (result.source_schema_match ? 'Поля исходной рубрики будут переиспользованы без дублей.' : 'Созданные поля станут независимой частью текущей рубрики.' + (result.generated_aliases ? ' Для полей без системного имени подготовлено alias: ' + result.generated_aliases + '.' : '')) + '</p></div></div>');
			applyButton.disabled = !!conflicts.length || !fingerprint || (!(parseInt(result.create, 10) > 0) && importMode !== 'linked');
		  }).catch(function (error) {
			showError(error.message || 'Набор не прошёл проверку');
		  }).then(function () {
			stateRoot.classList.remove('is-loading');
		  });
		};
		reader.readAsText(file, 'UTF-8');
	  };
	  fileInput.addEventListener('change', function () { preview(fileInput.files && fileInput.files[0]); });
	  dropzone.addEventListener('dragover', function (event) { event.preventDefault(); dropzone.classList.add('is-dragover'); });
	  dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('is-dragover'); });
	  dropzone.addEventListener('drop', function (event) {
		event.preventDefault();
		dropzone.classList.remove('is-dragover');
		preview(event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null);
	  });
	  overlay.addEventListener('click', function (event) {
		var modeButton = event.target.closest('[data-import-field-set-mode]');
		if (modeButton && !modeButton.disabled) {
		  importMode = modeButton.getAttribute('data-import-field-set-mode') === 'linked' ? 'linked' : 'copy';
		  overlay.querySelectorAll('[data-import-field-set-mode]').forEach(function (button) { button.setAttribute('aria-pressed', button === modeButton ? 'true' : 'false'); });
		  applyButton.disabled = !fingerprint || importHasConflicts;
		  return;
		}
		if (event.target === overlay || event.target.closest('[data-field-set-import-close]')) { close(); return; }
		if (!event.target.closest('[data-field-set-import-apply]') || applyButton.disabled) { return; }
		applyButton.disabled = true;
		applyButton.classList.add('is-loading');
		var data = new FormData();
		data.append('_csrf', self.form.elements._csrf.value);
		data.append('descriptor', descriptor);
		data.append('fingerprint', fingerprint);
		data.append('mode', importMode);
		fetch(self.base() + '/rubrics/' + self.currentRubricId + '/field-sets/import/apply', {
		  method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
		}).then(function (response) { return response.json(); }).then(function (json) {
		  if (!json.success) { throw new Error(json.message || 'Не удалось импортировать набор'); }
		  Adminx.Toast.show(json.message || 'Набор полей импортирован', 'success');
		  close();
		  self.loadFields();
		  self.applyFilterUrl(window.location.href, false);
		}).catch(function (error) {
		  showError(error.message || 'Не удалось импортировать набор');
		}).then(function () {
		  applyButton.classList.remove('is-loading');
		});
	  });
	},

	previewLinkedFieldSet: function (code) {
	  if (!code || !this.currentRubricId) { return; }
	  var self = this;
	  fetch(this.base() + '/rubrics/' + this.currentRubricId + '/field-set-links/' + encodeURIComponent(code) + '/preview', {
		headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
	  }).then(function (response) { return response.json(); }).then(function (json) {
		if (!json.success) { throw new Error(json.message || 'Не удалось проверить набор'); }
		var data = json.data || {};
		if (data.conflicts && data.conflicts.length) {
		  Adminx.Toast.show('Нужна ручная проверка: ' + data.conflicts.join(', '), 'error');
		  return;
		}
		Adminx.Confirm.open({
		  kind: 'warning',
		  title: 'Синхронизировать «' + (data.title || code) + '»?',
		  message: 'Новых полей: ' + (data.created || 0) + '. Обновляемых: ' + (data.updated || 0) + '. Снятых с формы: ' + (data.detached || 0) + '. Локальные настройки будут сохранены.',
		  confirmLabel: 'Синхронизировать',
		  onConfirm: function () {
			var form = new FormData();
			form.append('_csrf', self.form.elements._csrf.value);
			form.append('fingerprint', data.fingerprint || '');
			self.ajax(self.base() + '/rubrics/' + self.currentRubricId + '/field-set-links/' + encodeURIComponent(code) + '/sync', form, function (result) {
			  Adminx.Toast.show(result.message || 'Набор синхронизирован', 'success');
			  self.loadFields();
			});
		  }
		});
	  }).catch(function (error) { Adminx.Toast.show(error.message || 'Не удалось проверить набор', 'error'); });
	},

	detachLinkedFieldSet: function (code) {
	  if (!code || !this.currentRubricId) { return; }
	  var self = this;
	  Adminx.Confirm.open({
		kind: 'danger',
		title: 'Удалить связь с набором?',
		message: 'Поля и данные документов останутся в рубрике, но больше не будут получать обновления набора.',
		confirmLabel: 'Удалить связь',
		onConfirm: function () {
		  var form = new FormData();
		  form.append('_csrf', self.form.elements._csrf.value);
		  self.ajax(self.base() + '/rubrics/' + self.currentRubricId + '/field-set-links/' + encodeURIComponent(code) + '/detach', form, function (result) {
			Adminx.Toast.show(result.message || 'Связь удалена', 'success');
			self.loadFields();
		  });
		}
	  });
	},

    builderPaletteMarkup: function (field) {
      return '<button class="rubrics-builder-palette-item" type="button" draggable="true" data-builder-palette-item data-builder-select data-id="' + field.Id + '" data-builder-search-text="' + esc((field.rubric_field_title || '') + ' ' + (field.rubric_field_alias || '') + ' ' + (field.type_label || '')) + '">'
        + '<span class="rubrics-builder-palette-icon"><i class="ti ' + this.builderTypeIcon(field.rubric_field_type) + '"></i></span>'
        + '<span><b>' + esc(field.rubric_field_title) + '</b><small>' + esc(field.type_label) + ' · #' + field.Id + '</small></span>'
        + '<i class="ti ti-arrow-right rubrics-builder-palette-grip" aria-hidden="true"></i></button>';
    },

    builderFieldMarkup: function (field, groupId) {
      var layout = field.layout || {};
      var width = layout.width || this.builderAutoWidth(field);
      var conditional = field.condition && field.condition.tree && Array.isArray(field.condition.tree.items) && field.condition.tree.items.length;
      return '<article class="rubrics-builder-field is-' + esc(width) + '" draggable="true" data-field-row data-builder-select data-id="' + field.Id + '" data-group="' + groupId + '" data-width="' + esc(width) + '">'
        + '<div class="rubrics-builder-field-top"><button class="rubrics-builder-field-handle" type="button" draggable="true" data-field-drag-handle aria-label="Перетащить поле"><i class="ti ti-grip-vertical"></i></button>'
        + '<span class="rubrics-builder-field-type"><i class="ti ' + this.builderTypeIcon(field.rubric_field_type) + '"></i>' + esc(field.type_label) + '</span>'
        + (conditional ? '<span class="rubrics-builder-field-condition" data-tooltip="Условное поле" aria-label="Условное поле"><i class="ti ti-git-branch"></i></span>' : '')
        + '<span class="rubrics-builder-field-size">' + this.builderWidthLabel(width) + '</span></div>'
        + '<div class="rubrics-builder-field-main"><b>' + esc(field.rubric_field_title) + (layout.required ? '<em>*</em>' : '') + '</b><code>' + esc(field.rubric_field_alias || ('field_' + field.Id)) + '</code></div>'
        + '<div class="rubrics-builder-field-preview">' + (layout.prefix ? '<span>' + esc(layout.prefix) + '</span>' : '') + '<i>' + esc(this.builderPlaceholder(field)) + '</i>' + (layout.suffix ? '<span>' + esc(layout.suffix) + '</span>' : '') + '</div>'
        + '<div class="rubrics-builder-field-actions"><button class="btn btn-ghost btn-icon btn-sm rubrics-action-return" type="button" data-builder-unplace data-tooltip-left="Вернуть в доступные" aria-label="Вернуть в доступные"><i class="ti ti-arrow-back-up"></i></button><button class="btn btn-ghost btn-icon btn-sm rubrics-action-edit" type="button" data-field-edit data-tooltip-left="Полные настройки" aria-label="Полные настройки"><i class="ti ti-settings"></i></button><button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-field-delete data-tooltip-left="Удалить поле" aria-label="Удалить поле"><i class="ti ti-trash"></i></button></div>'
        + '</article>';
    },

    refreshBuilderPalette: function () {
      var root = document.querySelector('[data-builder-palette]');
      var count = document.querySelector('[data-builder-field-count]');
      if (!root) { return; }
      var items = root.querySelectorAll('[data-builder-palette-item]');
      if (!items.length) {
        root.innerHTML = '<div class="rubrics-builder-palette-empty"><i class="ti ti-circle-check"></i><b>Все поля размещены</b><span>Новых полей для добавления нет.</span></div>';
      }
      if (count) { count.textContent = items.length; }
      var search = document.querySelector('[data-builder-search]');
      if (search && search.value) { this.filterBuilderPalette(search.value); }
    },

    resetBuilderInspector: function () {
      this.builderSelectedId = 0;
      document.querySelectorAll('[data-builder-select].is-selected').forEach(function (node) { node.classList.remove('is-selected'); });
      var empty = document.querySelector('[data-builder-inspector-empty]');
      if (empty) { empty.hidden = false; }
      if (this.builderForm) { this.builderForm.hidden = true; }
    },

    unplaceBuilderField: function (row) {
      if (!row) { return; }
      var id = parseInt(row.getAttribute('data-id'), 10) || 0;
      var field = this.builderField(id);
      var palette = document.querySelector('[data-builder-palette]');
      if (!field || !palette) { return; }
      if (this.builderSelectedId === id) { this.captureBuilderInspector(); }
      if (!palette.querySelector('[data-builder-palette-item][data-id="' + id + '"]')) {
        var empty = palette.querySelector('.rubrics-builder-palette-empty');
        if (empty) { empty.remove(); }
        palette.insertAdjacentHTML('beforeend', this.builderPaletteMarkup(field));
      }
      field.layout = field.layout || {};
      field.layout.placed = false;
      row.remove();
      this.resetBuilderInspector();
      this.cleanupBuilderEmptyStates();
      this.updateBuilderGroupCounts();
      this.refreshBuilderPalette();
      this.setBuilderDirty(true);
    },

    placeBuilderField: function (id, groupId, beforeNode) {
      id = parseInt(id, 10) || 0;
      groupId = parseInt(groupId, 10) || 0;
      var field = this.builderField(id);
      var target = document.querySelector('[data-builder-canvas] [data-field-group-drop="' + groupId + '"] [data-field-sortable]');
      if (!field || !target) { return; }
      var existing = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + id + '"]');
      if (existing) { this.selectBuilderField(id); return; }
      var empty = target.querySelector('.rubrics-builder-drop-empty');
      if (empty) { empty.remove(); }
      target.insertAdjacentHTML('beforeend', this.builderFieldMarkup(field, groupId));
      var row = target.querySelector('[data-field-row][data-id="' + id + '"]');
      if (row && beforeNode && beforeNode.parentNode === target) { target.insertBefore(row, beforeNode); }
      var paletteItem = document.querySelector('[data-builder-palette-item][data-id="' + id + '"]');
      if (paletteItem) { paletteItem.remove(); }
      field.layout = field.layout || {};
      field.layout.placed = true;
      field.rubric_field_group = groupId;
      this.refreshBuilderPalette();
      this.cleanupBuilderEmptyStates();
      this.updateBuilderGroupCounts();
      this.selectBuilderField(id);
      this.setBuilderDirty(true);
    },

    builderField: function (id) {
      id = parseInt(id, 10) || 0;
      for (var i = 0; i < this.currentFields.length; i++) {
        if (parseInt(this.currentFields[i].Id, 10) === id) { return this.currentFields[i]; }
      }
      return null;
    },

    builderTypeIcon: function (type) {
      type = String(type || '');
      if (type.indexOf('image') !== -1 || type.indexOf('file') !== -1) { return 'ti-photo'; }
      if (type.indexOf('date') !== -1) { return type === 'date_time' ? 'ti-calendar-time' : 'ti-calendar'; }
      if (type.indexOf('checkbox') !== -1 || type === 'boolean') { return 'ti-checkbox'; }
      if (type.indexOf('dropdown') !== -1 || type.indexOf('select') !== -1) { return 'ti-list'; }
      if (type.indexOf('rub') !== -1 || type.indexOf('doc_') !== -1) { return 'ti-link'; }
      if (type === 'contact') { return 'ti-address-book'; }
      if (type === 'color') { return 'ti-palette'; }
      if (type === 'range') { return 'ti-arrows-horizontal'; }
      if (type === 'dimensions') { return 'ti-box-model-2'; }
      if (type === 'packages') { return 'ti-packages'; }
      if (type === 'address') { return 'ti-map-pin'; }
      if (type === 'period') { return 'ti-calendar-event'; }
      if (type.indexOf('numeric') !== -1 || type.indexOf('number') !== -1) { return 'ti-123'; }
      if (type.indexOf('text') !== -1 || type.indexOf('editor') !== -1) { return 'ti-align-left'; }
      return 'ti-cursor-text';
    },

    builderAutoWidth: function (field) {
      var type = String((field || {}).rubric_field_type || '');
      var narrow = ['boolean', 'date', 'date_time', 'single_line', 'single_line_numeric', 'number', 'range', 'dropdown', 'drop_down', 'drop_down_key', 'doc_from_rub'];
      return narrow.indexOf(type) !== -1 ? 'half' : 'full';
    },

    builderWidthLabel: function (width) {
      return ({ full: '12/12', half: '6/12', third: '4/12', quarter: '3/12' })[width] || '12/12';
    },

    builderPlaceholder: function (field) {
      var type = String((field || {}).rubric_field_type || '');
      if (type.indexOf('image') !== -1 || type.indexOf('file') !== -1) { return 'Выбор файла'; }
      if (type.indexOf('checkbox') !== -1 || type === 'boolean') { return 'Переключатель'; }
      if (type.indexOf('dropdown') !== -1 || type.indexOf('select') !== -1) { return 'Выбор значения'; }
      if (type === 'date_time') { return 'Дата и время'; }
      if (type === 'contact') { return 'Email, телефон или ссылка'; }
      if (type === 'color') { return '#rrggbb'; }
      if (type === 'range') { return 'От — до'; }
      if (type === 'dimensions') { return 'Д × Ш × В'; }
      if (type === 'packages') { return 'Коробки: Д × Ш × В и вес'; }
      if (type === 'address') { return 'Город, улица, дом'; }
      if (type === 'period') { return 'Начало — окончание'; }
      if (type.indexOf('date') !== -1) { return 'ДД.ММ.ГГГГ'; }
      if (type.indexOf('text') !== -1 || type.indexOf('editor') !== -1) { return 'Текстовое содержимое'; }
      return 'Значение поля';
    },

    selectBuilderField: function (id) {
      id = parseInt(id, 10) || 0;
      var field = this.builderField(id);
      var form = this.builderForm;
      if (!field || !form) { return; }
      if (this.builderSelectedId && this.builderSelectedId !== id) { this.captureBuilderInspector(false); }
      this.builderSelectedId = id;
      document.querySelectorAll('[data-builder-select]').forEach(function (node) {
        node.classList.toggle('is-selected', parseInt(node.getAttribute('data-id'), 10) === id);
      });

      var draft = this.builderDrafts[id] || {};
      var layout = field.layout || (field.layout = {});
      var row = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + id + '"]');
      var width = row ? row.getAttribute('data-width') : (layout.width || this.builderAutoWidth(field));
      var empty = document.querySelector('[data-builder-inspector-empty]');
      if (empty) { empty.hidden = true; }
      form.hidden = false;
      this.builderHydrating = true;
      var typeLabel = form.querySelector('[data-builder-type-label]');
      var typeCode = form.querySelector('[data-builder-type-code]');
      if (typeLabel) { typeLabel.textContent = field.type_label || field.rubric_field_type || 'Неизвестный тип'; }
      if (typeCode) { typeCode.textContent = field.rubric_field_type || '—'; }
      form.elements.title.value = Object.prototype.hasOwnProperty.call(draft, 'title') ? draft.title : (field.rubric_field_title || ('Поле #' + id));
      this.fillBuilderGroupSelect(row ? row.closest('[data-field-group-drop]').getAttribute('data-field-group-drop') : field.rubric_field_group);
      var widthInput = form.querySelector('input[name="width"][value="' + width + '"]');
      if (widthInput) { widthInput.checked = true; }
      form.elements.prefix.value = layout.prefix || '';
      form.elements.suffix.value = layout.suffix || '';
      form.elements.default.value = Object.prototype.hasOwnProperty.call(draft, 'default') ? draft.default : (field.rubric_field_default || '');
      form.elements.description.value = Object.prototype.hasOwnProperty.call(draft, 'description') ? draft.description : (field.rubric_field_description || '');
      form.elements.search.checked = Object.prototype.hasOwnProperty.call(draft, 'search') ? !!draft.search : String(field.rubric_field_search) === '1';
      form.elements.numeric.checked = Object.prototype.hasOwnProperty.call(draft, 'numeric') ? !!draft.numeric : String(field.rubric_field_numeric) === '1';
      var nativeNumeric = ['number', 'date_time', 'period'].indexOf(String(field.rubric_field_type || '')) !== -1;
      if (nativeNumeric) { form.elements.numeric.checked = true; }
      form.elements.numeric.disabled = nativeNumeric;
      form.elements.numeric.title = nativeNumeric ? 'Этот тип всегда использует числовой индекс' : '';
      this.hydrateBuilderCondition(field, Object.prototype.hasOwnProperty.call(draft, 'condition') ? draft.condition : (field.condition || {}));
      this.builderHydrating = false;
      this.loadBuilderTypeSettings(field, draft.settings || null);
      if (row && row.scrollIntoView) { row.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    },

    fillBuilderGroupSelect: function (selected) {
      var select = this.builderForm && this.builderForm.querySelector('[data-builder-group]');
      if (!select) { return; }
      var html = '<option value="0">Основные поля</option>';
      this.currentGroups.forEach(function (group) {
        html += '<option value="' + group.id + '">' + esc(group.title) + '</option>';
      });
      select.innerHTML = html;
      select.value = String(parseInt(selected, 10) || 0);
    },

    loadBuilderTypeSettings: function (field, draftSettings) {
      var self = this;
      var root = document.querySelector('[data-builder-type-settings]');
      if (!root || !field) { return; }
      this.builderSettingsReady = false;
      this.toggleBuilderDefaultField({});
      root.innerHTML = '<div class="rubrics-builder-settings-loading"><i class="ti ti-loader-2"></i>Загрузка настроек типа...</div>';
      fetch(this.base() + '/rubrics/fields/' + field.Id + '/plugin', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (self.builderSelectedId !== parseInt(field.Id, 10)) { return; }
          var data = json.data || {};
          var type = data.type || {};
          root.innerHTML = type.settings_html
            ? '<div class="rubrics-builder-settings-head"><i class="ti ti-plug-connected"></i><b>Настройки типа</b></div>' + type.settings_html
            : '<p class="rubrics-plugin-empty">У этого типа нет дополнительных настроек.</p>';
          if (draftSettings !== null) { self.applyBuilderSettingsDraft(root, draftSettings); }
          self.hydrateSettingsMaps(root);
          self.syncOptionSourceFields(root);
          self.toggleBuilderDefaultField(type.admin_editor || {});
          self.builderSettingsReady = true;
        })
        .catch(function () { root.innerHTML = '<p class="rubrics-plugin-empty">Не удалось загрузить настройки типа.</p>'; });
    },

    applyBuilderSettingsDraft: function (root, settings) {
      Object.keys(settings || {}).forEach(function (key) {
        var controls = root.querySelectorAll('[name="rubric_field_settings[' + key.replace(/"/g, '\\"') + ']"]');
        controls.forEach(function (control) {
          if (control.type === 'checkbox') { control.checked = !!settings[key] && settings[key] !== '0'; }
          else if (Array.isArray(settings[key])) { control.value = settings[key].map(function (item) { return typeof item === 'object' ? ((item.value || '') + '=' + (item.label || '')) : item; }).join('\n'); }
          else { control.value = settings[key] == null ? '' : settings[key]; }
        });
      });
    },

    captureBuilderInspector: function (markDirty) {
      if (this.builderHydrating || !this.builderSelectedId || !this.builderForm || this.builderForm.hidden) { return; }
      var form = this.builderForm;
      var field = this.builderField(this.builderSelectedId);
      if (!field) { return; }
      var settings = {};
      form.querySelectorAll('[name^="rubric_field_settings["]').forEach(function (control) {
        var match = control.name.match(/^rubric_field_settings\[([^\]]+)\]$/);
        if (!match) { return; }
        if (control.type === 'checkbox') {
          if (control.checked) { settings[match[1]] = control.value || '1'; }
          else if (!Object.prototype.hasOwnProperty.call(settings, match[1])) { settings[match[1]] = '0'; }
        } else {
          settings[match[1]] = control.value;
        }
      });
      var layout = field.layout || (field.layout = {});
      layout.width = (form.querySelector('input[name="width"]:checked') || {}).value || 'full';
      layout.prefix = form.elements.prefix.value.trim();
      layout.suffix = form.elements.suffix.value.trim();
      var draft = {
        id: this.builderSelectedId,
        title: form.elements.title.value.trim(),
        default: form.elements.default.value,
        description: form.elements.description.value,
        search: form.elements.search.checked ? 1 : 0,
        numeric: form.elements.numeric.checked ? 1 : 0
      };
      draft.condition = this.captureBuilderCondition();
      if (this.builderSettingsReady) {
        draft.settings = settings;
      } else if (this.builderDrafts[this.builderSelectedId] && Object.prototype.hasOwnProperty.call(this.builderDrafts[this.builderSelectedId], 'settings')) {
        draft.settings = this.builderDrafts[this.builderSelectedId].settings;
      }
      this.builderDrafts[this.builderSelectedId] = draft;
      field.condition = draft.condition;
      if (draft.title) { field.rubric_field_title = draft.title; }
      this.updateBuilderFieldCard(field);
      if (markDirty !== false) { this.setBuilderDirty(true); }
    },

    builderConditionOperators: function () {
      return [
        ['equals', 'Равно'], ['not_equals', 'Не равно'], ['contains', 'Содержит'], ['not_contains', 'Не содержит'],
        ['empty', 'Не заполнено'], ['not_empty', 'Заполнено'], ['greater', 'Больше'], ['greater_or_equal', 'Больше или равно'],
        ['less', 'Меньше'], ['less_or_equal', 'Меньше или равно'], ['in', 'Одно из'], ['not_in', 'Не входит в']
      ];
    },

    builderConditionFieldOptions: function (selected, current) {
	  current = parseInt(current, 10) || 0;
      var html = '<option value="">Выберите поле</option>';
      this.currentFields.forEach(function (field) {
        if (parseInt(field.Id, 10) === parseInt(current, 10)) { return; }
        var alias = String(field.rubric_field_alias || '').trim().toLowerCase();
        var reference = 'id:' + parseInt(field.Id, 10);
        var label = String(field.rubric_field_title || field.rubric_field_alias || ('Поле #' + field.Id));
        html += '<option value="' + esc(reference) + '"' + (reference === selected ? ' selected' : '') + '>'
          + esc(label) + ' · ' + esc(alias || ('#' + field.Id)) + '</option>';
      });
      return html;
    },

    builderConditionRowMarkup: function (rule, current) {
      rule = rule || {};
      var operator = String(rule.operator || 'equals');
      var options = this.builderConditionOperators().map(function (item) {
        return '<option value="' + item[0] + '"' + (operator === item[0] ? ' selected' : '') + '>' + item[1] + '</option>';
      }).join('');
      var valueHidden = operator === 'empty' || operator === 'not_empty';
      return '<div class="rubrics-builder-condition-row" data-builder-condition-row>'
        + '<select class="select" data-builder-condition-field>' + this.builderConditionFieldOptions(String(rule.field || ''), current) + '</select>'
        + '<select class="select" data-builder-condition-operator>' + options + '</select>'
        + '<input class="input" type="text" value="' + esc(rule.value || '') + '" placeholder="Значение" data-builder-condition-value' + (valueHidden ? ' hidden' : '') + '>'
        + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-builder-condition-remove data-tooltip-left="Удалить условие" aria-label="Удалить условие"><i class="ti ti-trash"></i></button>'
        + '</div>';
    },

    builderConditionGroupMarkup: function (group, depth, isRoot, current) {
      group = group && typeof group === 'object' ? group : {};
      depth = Math.max(1, parseInt(depth, 10) || 1);
      var operator = group.operator === 'or' ? 'or' : 'and';
      var items = Array.isArray(group.items) ? group.items : [];
      var self = this;
      var body = items.map(function (item) {
        return item && Array.isArray(item.items)
          ? self.builderConditionGroupMarkup(item, depth + 1, false, current)
          : self.builderConditionRowMarkup(item || {}, current);
      }).join('');
      var title = isRoot ? 'Основная группа' : 'Вложенная группа';
      var hint = operator === 'or' ? 'Достаточно любого правила внутри' : 'Должны совпасть все правила внутри';
      var tools = '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-builder-condition-add data-tooltip-left="Добавить условие" aria-label="Добавить условие"><i class="ti ti-plus"></i></button>';
      if (depth < 4) {
        tools += '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-builder-condition-group-add data-tooltip-left="Добавить группу" aria-label="Добавить вложенную группу"><i class="ti ti-brackets"></i></button>';
      }
      if (!isRoot) {
        tools += '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-builder-condition-group-remove data-tooltip-left="Удалить группу" aria-label="Удалить группу"><i class="ti ti-trash"></i></button>';
      }

      return '<section class="rubrics-builder-condition-group' + (isRoot ? ' is-root' : '') + '" data-builder-condition-group data-condition-depth="' + depth + '">'
        + '<header><span class="rubrics-builder-condition-bracket" aria-hidden="true"></span><div class="rubrics-builder-condition-group-copy"><b>' + title + '</b><small data-builder-condition-group-hint>' + hint + '</small></div>'
        + '<select class="select" data-builder-condition-join aria-label="Логика группы"><option value="and"' + (operator === 'and' ? ' selected' : '') + '>Все · И</option><option value="or"' + (operator === 'or' ? ' selected' : '') + '>Любое · ИЛИ</option></select>'
        + '<div class="rubrics-builder-condition-group-tools">' + tools + '</div></header>'
        + '<div class="rubrics-builder-condition-items" data-builder-condition-items>' + body + '</div></section>';
    },

    hydrateBuilderConditionRoot: function (root, condition, current) {
      if (!root) { return; }
      condition = condition && typeof condition === 'object' ? condition : {};
      var tree = condition.tree && typeof condition.tree === 'object' ? condition.tree : {};
      var items = Array.isArray(tree.items) ? tree.items : [];
      root._conditionOriginal = condition;
      root.querySelector('[data-builder-condition-enabled]').checked = !!items.length;
      root.querySelector('[data-builder-condition-mode]').value = ['show', 'hide', 'lock'].indexOf(condition.mode) !== -1 ? condition.mode : 'show';
      var required = root.querySelector('[data-builder-condition-required]');
	  if (required) { required.checked = !!condition.required; }
      var rows = root.querySelector('[data-builder-condition-rows]');
      if (rows) {
        rows.innerHTML = this.builderConditionGroupMarkup({ operator: tree.operator, items: items }, 1, true, current);
      }
	  this.syncBuilderCondition(root);
	},

	hydrateBuilderCondition: function (field, condition) {
	  var root = this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null;
	  this.hydrateBuilderConditionRoot(root, condition, this.builderSelectedId);
	  this.hydrateBuilderConditionOptions(root, field, condition);
	  this.hydrateBuilderConditionAction(root, field, condition);
	},

	hydrateBuilderConditionOptions: function (root, field, condition) {
	  if (!root) { return; }
	  var section = root.querySelector('[data-builder-condition-values]');
	  var list = root.querySelector('[data-builder-condition-options]');
	  var toggle = root.querySelector('[data-builder-condition-options-enabled]');
	  if (!section || !list || !toggle) { return; }
	  var options = field && Array.isArray(field.condition_options) ? field.condition_options : [];
	  var allowed = condition && Array.isArray(condition.allowed_values) ? condition.allowed_values.map(String) : [];
	  section.hidden = options.length === 0;
	  toggle.checked = allowed.length > 0;
	  list.hidden = !toggle.checked;
	  list.innerHTML = options.map(function (option) {
		var value = String(option.value == null ? '' : option.value);
		var label = String(option.label == null ? value : option.label);
		return '<label><input type="checkbox" value="' + esc(value) + '" data-builder-condition-option'
		  + (allowed.indexOf(value) !== -1 ? ' checked' : '') + '><span>' + esc(label) + '</span><small>' + esc(value) + '</small></label>';
	  }).join('');
	},

	hydrateBuilderConditionAction: function (root, field, condition) {
	  if (!root) { return; }
	  var section = root.querySelector('[data-builder-condition-action]');
	  var action = root.querySelector('[data-builder-condition-value-action]');
	  var row = root.querySelector('[data-builder-condition-action-value]');
	  var select = root.querySelector('[data-builder-condition-action-select]');
	  var input = root.querySelector('[data-builder-condition-action-input]');
	  if (!section || !action || !row || !select || !input) { return; }
	  var supported = !!(field && field.condition_value_action);
	  var options = field && Array.isArray(field.condition_action_options) ? field.condition_action_options : [];
	  var value = condition && Object.prototype.hasOwnProperty.call(condition, 'action_value') ? String(condition.action_value) : '';
	  var selectedAction = condition && ['set', 'clear'].indexOf(String(condition.value_action || '')) !== -1 ? String(condition.value_action) : '';
	  section.hidden = !supported;
	  action.value = supported ? selectedAction : '';
	  select.innerHTML = options.map(function (option) {
		var optionValue = String(option.value == null ? '' : option.value);
		var optionLabel = String(option.label == null ? optionValue : option.label);
		return '<option value="' + esc(optionValue) + '">' + esc(optionLabel) + '</option>';
	  }).join('');
	  select.hidden = options.length === 0;
	  input.hidden = options.length > 0;
	  if (options.length) { select.value = value; }
	  else { input.value = value; }
	  row.hidden = !supported || selectedAction !== 'set';
	},

	syncBuilderConditionOptionSource: function (raw) {
	  var field = this.builderField(this.builderSelectedId);
	  var root = this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null;
	  if (!field || !root) { return; }
	  var current = this.captureBuilderConditionRoot(root);
	  var source = String(raw || '').replace(/\r\n?/g, '\n').split('\n').map(function (line) { return line.trim(); }).filter(Boolean);
	  field.condition_options = source.map(function (line) {
		var match = line.match(/^([^=:|]+)\s*(?:=>|=|:|\|)\s*(.+)$/);
		return match
		  ? { value: match[1].trim(), label: match[2].trim() }
		  : { value: line, label: line };
	  });
	  field.condition_action_options = field.condition_options;
	  this.hydrateBuilderConditionOptions(root, field, current);
	  this.hydrateBuilderConditionAction(root, field, current);
	  this.syncBuilderCondition(root);
	},

    syncBuilderCondition: function (root) {
	  root = root || (this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null);
      if (!root) { return; }
      var enabled = root.querySelector('[data-builder-condition-enabled]').checked;
      var body = root.querySelector('[data-builder-condition-body]');
      if (body) { body.hidden = !enabled; }
	  var optionToggle = root.querySelector('[data-builder-condition-options-enabled]');
	  var optionList = root.querySelector('[data-builder-condition-options]');
		if (optionToggle && optionList) {
		  optionList.hidden = !optionToggle.checked;
		  if (optionToggle.checked && !optionList.querySelector('[data-builder-condition-option]:checked')) {
			optionList.querySelectorAll('[data-builder-condition-option]').forEach(function (control) { control.checked = true; });
		  }
		}
		var action = root.querySelector('[data-builder-condition-value-action]');
		var actionValue = root.querySelector('[data-builder-condition-action-value]');
		if (action && actionValue) { actionValue.hidden = action.value !== 'set'; }
      root.querySelectorAll('[data-builder-condition-row]').forEach(function (row) {
        var operator = row.querySelector('[data-builder-condition-operator]');
        var value = row.querySelector('[data-builder-condition-value]');
        if (value) { value.hidden = operator && (operator.value === 'empty' || operator.value === 'not_empty'); }
      });
      root.querySelectorAll('[data-builder-condition-group]').forEach(function (group) {
        var join = group.querySelector(':scope > header [data-builder-condition-join]');
        var hint = group.querySelector(':scope > header [data-builder-condition-group-hint]');
        if (join && hint) { hint.textContent = join.value === 'or' ? 'Достаточно любого правила внутри' : 'Должны совпасть все правила внутри'; }
      });
      if (enabled && !root.querySelector('[data-builder-condition-row]')) {
		this.addBuilderConditionRow(root.querySelector('[data-builder-condition-group]'), false);
      }
    },

    captureBuilderConditionGroup: function (group) {
      if (!group) { return { operator: 'and', items: [] }; }
      var join = group.querySelector(':scope > header [data-builder-condition-join]');
      var itemsRoot = group.querySelector(':scope > [data-builder-condition-items]');
      var items = [];
      Array.prototype.forEach.call(itemsRoot ? itemsRoot.children : [], function (item) {
        if (item.matches('[data-builder-condition-group]')) {
          items.push(Adminx.Rubrics.captureBuilderConditionGroup(item));
          return;
        }
        if (!item.matches('[data-builder-condition-row]')) { return; }
        var field = item.querySelector('[data-builder-condition-field]');
        var operator = item.querySelector('[data-builder-condition-operator]');
        var value = item.querySelector('[data-builder-condition-value]');
        if (!field || !field.value) { return; }
        items.push({
          field: field.value,
          operator: operator ? operator.value : 'equals',
          value: value && !value.hidden ? value.value : ''
        });
      });

      return { operator: join && join.value === 'or' ? 'or' : 'and', items: items };
    },

    captureBuilderConditionRoot: function (root) {
      if (!root || !root.querySelector('[data-builder-condition-enabled]').checked) { return {}; }
	  var required = root.querySelector('[data-builder-condition-required]');
	  var result = {
        enabled: true,
        mode: ['show', 'hide', 'lock'].indexOf(root.querySelector('[data-builder-condition-mode]').value) !== -1
          ? root.querySelector('[data-builder-condition-mode]').value
          : 'show',
		required: required ? required.checked : false,
        tree: this.captureBuilderConditionGroup(root.querySelector('[data-builder-condition-group]'))
      };
	  var optionToggle = root.querySelector('[data-builder-condition-options-enabled]');
	  if (optionToggle && optionToggle.checked) {
		result.allowed_values = Array.prototype.map.call(
		  root.querySelectorAll('[data-builder-condition-option]:checked'),
		  function (control) { return control.value; }
		);
	  }
	  var action = root.querySelector('[data-builder-condition-value-action]');
	  if (action && ['set', 'clear'].indexOf(action.value) !== -1) {
		result.value_action = action.value;
		if (action.value === 'set') {
		  var actionSelect = root.querySelector('[data-builder-condition-action-select]');
		  var actionInput = root.querySelector('[data-builder-condition-action-input]');
		  result.action_value = actionSelect && !actionSelect.hidden ? actionSelect.value : (actionInput ? actionInput.value : '');
		}
	  }

	  return result;
    },

	captureBuilderCondition: function () {
	  return this.captureBuilderConditionRoot(this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null);
	},

    addBuilderConditionRow: function (group, markDirty) {
	  var root = group ? group.closest('[data-builder-condition]') : null;
	  root = root || (this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null);
      group = group || (root ? root.querySelector('[data-builder-condition-group]') : null);
      var rows = group ? group.querySelector(':scope > [data-builder-condition-items]') : null;
      if (!rows) { return; }
      if (root && root.querySelectorAll('[data-builder-condition-row]').length >= 32) {
        Adminx.Toast.show('В одном условии допускается не более 32 правил', 'warning');
        return;
      }
	  var current = root && root.getAttribute('data-condition-scope') === 'group' ? 0 : this.builderSelectedId;
      rows.insertAdjacentHTML('beforeend', this.builderConditionRowMarkup({}, current));
	  if (markDirty !== false && root && root.getAttribute('data-condition-scope') !== 'group') { this.captureBuilderInspector(); }
      var added = rows.querySelectorAll(':scope > [data-builder-condition-row]');
      var select = added.length ? added[added.length - 1].querySelector('[data-builder-condition-field]') : null;
      if (select) { select.focus(); }
    },

    addBuilderConditionGroup: function (group) {
      var items = group ? group.querySelector(':scope > [data-builder-condition-items]') : null;
      var depth = group ? parseInt(group.getAttribute('data-condition-depth'), 10) || 1 : 1;
      if (!items || depth >= 4) { return; }
	  var root = group ? group.closest('[data-builder-condition]') : null;
	  root = root || (this.builderForm ? this.builderForm.querySelector('[data-builder-condition]') : null);
      if (root && root.querySelectorAll('[data-builder-condition-row]').length >= 32) {
        Adminx.Toast.show('В одном условии допускается не более 32 правил', 'warning');
        return;
      }
	  var current = root && root.getAttribute('data-condition-scope') === 'group' ? 0 : this.builderSelectedId;
      items.insertAdjacentHTML('beforeend', this.builderConditionGroupMarkup({ operator: 'and', items: [{}] }, depth + 1, false, current));
	  if (!root || root.getAttribute('data-condition-scope') !== 'group') { this.captureBuilderInspector(); }
      var groups = items.querySelectorAll(':scope > [data-builder-condition-group]');
      var select = groups.length ? groups[groups.length - 1].querySelector('[data-builder-condition-field]') : null;
      if (select) { select.focus(); }
    },

    removeBuilderConditionRow: function (row) {
	  var root = row ? row.closest('[data-builder-condition]') : null;
      if (row && row.parentNode) { row.parentNode.removeChild(row); }
	  if (!root || root.getAttribute('data-condition-scope') !== 'group') { this.captureBuilderInspector(); }
    },

    removeBuilderConditionGroup: function (group) {
      if (!group || group.classList.contains('is-root')) { return; }
	  var root = group.closest('[data-builder-condition]');
      group.remove();
	  if (!root || root.getAttribute('data-condition-scope') !== 'group') { this.captureBuilderInspector(); }
    },

    toggleBuilderDefaultField: function (editor) {
      var field = this.builderForm ? this.builderForm.querySelector('[data-builder-default-field]') : null;
      if (field) { field.hidden = !!editor.default_is_configuration; }
    },

    settingsMapRows: function (map) {
      if (!map) { return []; }
      return Array.prototype.map.call(map.querySelectorAll('[data-settings-map-row]'), function (row) {
        var key = row.querySelector('[data-settings-map-key]');
        var label = row.querySelector('[data-settings-map-label]');
        return {
          key: key ? key.value.trim() : '',
          label: label ? label.value.trim() : ''
        };
      });
    },

    settingsMapRowMarkup: function (key, label) {
      return '<div class="ax-settings-map-row" data-settings-map-row>'
        + '<input class="input mono" type="text" value="' + esc(key || '') + '" placeholder="Например, 0" data-settings-map-key>'
        + '<input class="input" type="text" value="' + esc(label || '') + '" placeholder="Название значения" data-settings-map-label>'
        + '<button class="btn btn-ghost btn-icon btn-sm ax-settings-map-remove" type="button" data-settings-map-remove data-tooltip-left="Удалить" aria-label="Удалить значение"><i class="ti ti-trash"></i></button>'
        + '</div>';
    },

    syncSettingsMap: function (map) {
      var storage = map ? map.querySelector('[data-settings-map-storage]') : null;
      if (!storage) { return; }
      storage.value = this.settingsMapRows(map).filter(function (row) {
        return row.key !== '' || row.label !== '';
      }).map(function (row) {
        return row.key + '=' + row.label;
      }).join('\n');
    },

    hydrateSettingsMaps: function (root) {
      var self = this;
      (root || document).querySelectorAll('[data-settings-map]').forEach(function (map) {
        var storage = map.querySelector('[data-settings-map-storage]');
        var rows = map.querySelector('[data-settings-map-rows]');
        if (!storage || !rows) { return; }
        var values = String(storage.value || '').split(/\r?\n/).filter(function (line) { return line.trim() !== ''; });
        rows.innerHTML = values.map(function (line) {
          var split = line.indexOf('=');
          var key = split === -1 ? line : line.slice(0, split);
          var label = split === -1 ? line : line.slice(split + 1);
          return self.settingsMapRowMarkup(key.trim(), label.trim().replace(/\|\s*$/, ''));
        }).join('') || self.settingsMapRowMarkup('', '');
      });
    },

    syncOptionSourceFields: function (root) {
      root = root || document;
      var source = root.querySelector('[name="rubric_field_settings[option_source]"]');
      if (!source) { return; }
      var shared = source.value === 'directory';
      var directory = root.querySelector('[data-field-setting-key="directory_id"]');
      var options = root.querySelector('[data-field-setting-key="options"]');
      if (directory) { directory.hidden = !shared; }
      if (options) { options.hidden = shared; }
    },

    addSettingsMapRow: function (map) {
      var rows = map ? map.querySelector('[data-settings-map-rows]') : null;
      if (!rows) { return; }
      rows.insertAdjacentHTML('beforeend', this.settingsMapRowMarkup('', ''));
      this.syncSettingsMap(map);
      var inputs = rows.querySelectorAll('[data-settings-map-key]');
      if (inputs.length) { inputs[inputs.length - 1].focus(); }
      if (this.builderForm && this.builderForm.contains(map)) { this.captureBuilderInspector(); }
    },

    removeSettingsMapRow: function (row) {
      var map = row ? row.closest('[data-settings-map]') : null;
      var rows = map ? map.querySelector('[data-settings-map-rows]') : null;
      if (!map || !rows || !row) { return; }
      row.remove();
      if (!rows.querySelector('[data-settings-map-row]')) {
        rows.innerHTML = this.settingsMapRowMarkup('', '');
      }
      this.syncSettingsMap(map);
      if (this.builderForm && this.builderForm.contains(map)) { this.captureBuilderInspector(); }
    },

    applyBuilderWidth: function (width) {
      var row = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + this.builderSelectedId + '"]');
      if (!row) { return; }
      ['full', 'half', 'third', 'quarter'].forEach(function (name) { row.classList.toggle('is-' + name, name === width); });
      row.setAttribute('data-width', width);
      var label = row.querySelector('.rubrics-builder-field-size');
      if (label) { label.textContent = this.builderWidthLabel(width); }
      this.setBuilderDirty(true);
    },

    moveSelectedBuilderField: function (groupId) {
      var row = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + this.builderSelectedId + '"]');
      var target = document.querySelector('[data-builder-canvas] [data-field-group-drop="' + (parseInt(groupId, 10) || 0) + '"] [data-field-sortable]');
      if (!row || !target || row.parentNode === target) { return; }
      target.appendChild(row);
      row.setAttribute('data-group', String(parseInt(groupId, 10) || 0));
      this.cleanupBuilderEmptyStates();
      this.updateBuilderGroupCounts();
      this.setBuilderDirty(true);
    },

    updateBuilderFieldCard: function (field) {
      var row = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + field.Id + '"]');
      if (!row) { return; }
      var layout = field.layout || {};
      var conditional = field.condition && field.condition.tree && Array.isArray(field.condition.tree.items) && field.condition.tree.items.length;
      var top = row.querySelector('.rubrics-builder-field-top');
      var marker = row.querySelector('.rubrics-builder-field-condition');
      if (conditional && !marker && top) {
        var size = top.querySelector('.rubrics-builder-field-size');
        var html = '<span class="rubrics-builder-field-condition" data-tooltip="Условное поле" aria-label="Условное поле"><i class="ti ti-git-branch"></i></span>';
        if (size) { size.insertAdjacentHTML('beforebegin', html); } else { top.insertAdjacentHTML('beforeend', html); }
      } else if (!conditional && marker) {
        marker.remove();
      }
      var title = row.querySelector('.rubrics-builder-field-main > b');
      if (title) { title.innerHTML = esc(field.rubric_field_title || ('Поле #' + field.Id)) + (layout.required ? '<em>*</em>' : ''); }
      var preview = row.querySelector('.rubrics-builder-field-preview');
      if (preview) {
        preview.innerHTML = (layout.prefix ? '<span>' + esc(layout.prefix) + '</span>' : '')
          + '<i>' + esc(this.builderPlaceholder(field)) + '</i>'
          + (layout.suffix ? '<span>' + esc(layout.suffix) + '</span>' : '');
      }
    },

    filterBuilderPalette: function (query) {
      query = String(query || '').trim().toLowerCase();
      document.querySelectorAll('[data-builder-palette-item]').forEach(function (item) {
        item.hidden = query !== '' && String(item.getAttribute('data-builder-search-text') || '').toLowerCase().indexOf(query) === -1;
      });
    },

    updateBuilderGroupCounts: function () {
      document.querySelectorAll('[data-builder-canvas] [data-field-group-drop]').forEach(function (group) {
        var count = group.querySelector('[data-builder-group-count]');
        if (count) { count.textContent = group.querySelectorAll('[data-field-row]').length; }
      });
    },

    cleanupBuilderEmptyStates: function () {
      document.querySelectorAll('[data-builder-canvas] [data-field-sortable]').forEach(function (list) {
        var empty = list.querySelector('.rubrics-builder-drop-empty');
        var hasFields = !!list.querySelector('[data-field-row]');
        if (empty && hasFields) { empty.remove(); }
        if (!empty && !hasFields) { list.innerHTML = '<div class="rubrics-builder-drop-empty"><i class="ti ti-drag-drop"></i><span>Перетащите поле в эту группу</span></div>'; }
      });
    },

    setBuilderDirty: function (dirty) {
      this.builderDirty = !!dirty;
      var button = document.querySelector('[data-builder-save]');
      var state = document.querySelector('[data-builder-save-state]');
      if (button) { button.disabled = !this.builderDirty; }
      if (state) {
        state.hidden = !this.builderDirty;
      }
    },

    syncBuilderRuntime: function () {
      var root = document.querySelector('[data-builder-runtime]');
      var toggle = document.querySelector('[data-builder-conditions-enabled]');
      var state = document.querySelector('[data-builder-runtime-state]');
      if (!root || !toggle || !state) { return; }
      var enabled = !!toggle.checked;
      root.classList.toggle('is-enabled', enabled);
      state.className = 'badge ' + (enabled ? 'badge-green' : 'badge-gray');
      state.textContent = enabled ? 'Включены' : 'Выключены';
    },

    openBuilderAdvanced: function () {
      var row = document.querySelector('[data-builder-canvas] [data-field-row][data-id="' + this.builderSelectedId + '"]');
      if (!row) { return; }
      if (this.builderDirty) {
        Adminx.Toast.show('Сначала сохраните изменения конструктора', 'warning');
        return;
      }
      this.fillFieldEdit(row);
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricFieldDrawer'); }
    },

    saveBuilder: function () {
      var self = this;
      if (!this.builderDirty) { return; }
      this.captureBuilderInspector();
      var payload = this.builderSavePayload();
      var previewData = this.builderFormData(payload, '');
      this.ajax(this.base() + '/rubrics/' + this.currentRubricId + '/fields/builder/preview', previewData, function (json) {
        var impact = (json.data || {}).impact || {};
        var persist = function () { self.persistBuilder(payload, impact.fingerprint || ''); };
        if (impact.requires_confirmation) {
          self.showBuilderImpact(impact, persist);
          return;
        }
        persist();
      });
    },

    builderSavePayload: function () {
      var self = this;
      var layout = [];
      document.querySelectorAll('[data-builder-canvas] [data-field-group-drop]').forEach(function (group) {
        var groupId = parseInt(group.getAttribute('data-field-group-drop'), 10) || 0;
        group.querySelectorAll('[data-field-row]').forEach(function (row) {
          var field = self.builderField(row.getAttribute('data-id'));
          var fieldLayout = field && field.layout ? field.layout : {};
          layout.push({
            id: parseInt(row.getAttribute('data-id'), 10),
            group_id: groupId,
            width: row.getAttribute('data-width') || 'full',
            prefix: fieldLayout.prefix || '',
            suffix: fieldLayout.suffix || ''
          });
        });
      });
      var drafts = Object.keys(this.builderDrafts).map(function (id) { return self.builderDrafts[id]; });

      return {
        layout: layout,
        drafts: drafts,
        conditionsEnabled: !!(document.querySelector('[data-builder-conditions-enabled]') || {}).checked
      };
    },

    builderFormData: function (payload, fingerprint) {
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      data.append('layout', JSON.stringify(payload.layout || []));
      data.append('drafts', JSON.stringify(payload.drafts || []));
      data.append('conditions_enabled', payload.conditionsEnabled ? '1' : '0');
      if (fingerprint) { data.append('impact_fingerprint', fingerprint); }

      return data;
    },

    persistBuilder: function (payload, fingerprint) {
      var self = this;
      var data = this.builderFormData(payload, fingerprint);
      this.ajax(this.base() + '/rubrics/' + this.currentRubricId + '/fields/builder', data, function () {
        self.setBuilderDirty(false);
        self.builderDrafts = {};
        Adminx.Toast.show('Раскладка полей сохранена', 'success');
        self.loadFields();
      });
    },

    showBuilderImpact: function (impact, onConfirm) {
      var overlay = document.createElement('div');
      var enabled = !!impact.enabled_after;
      var total = parseInt(impact.documents_total, 10) || 0;
      var analyzed = parseInt(impact.documents_analyzed, 10) || 0;
      var details = Array.isArray(impact.details) ? impact.details : [];
      var detailsHtml = details.map(function (item) {
        return '<div class="rubrics-impact-row">'
          + '<span><b>' + esc(item.title || ('Поле #' + item.id)) + '</b><small>' + esc(item.alias || ('field_' + item.id)) + '</small></span>'
          + '<span><b>' + (parseInt(item.changed_documents, 10) || 0) + '</b><small>изменится</small></span>'
          + '<span><b>' + (parseInt(item.hidden_before, 10) || 0) + ' → ' + (parseInt(item.hidden_after, 10) || 0) + '</b><small>скрыто · заблокировано ' + (parseInt(item.locked_before, 10) || 0) + ' → ' + (parseInt(item.locked_after, 10) || 0) + ' · ограничено ' + (parseInt(item.limited_before, 10) || 0) + ' → ' + (parseInt(item.limited_after, 10) || 0) + ' · автодействие ' + (parseInt(item.action_before, 10) || 0) + ' → ' + (parseInt(item.action_after, 10) || 0) + ' · обязательно ' + (parseInt(item.required_before, 10) || 0) + ' → ' + (parseInt(item.required_after, 10) || 0) + '</small></span>'
          + '</div>';
      }).join('');
      if (!detailsHtml) { detailsHtml = '<div class="empty-state">Правила полей не изменились.</div>'; }
      overlay.className = 'overlay rubrics-builder-impact-overlay';
      overlay.innerHTML = '<div class="modal modal-lg rubrics-builder-impact" role="dialog" aria-modal="true" aria-labelledby="rubricsBuilderImpactTitle">'
        + '<div class="modal-header"><span class="dialog-icon warning"><i class="ti ti-git-compare"></i></span><div class="rubrics-impact-heading"><h3 id="rubricsBuilderImpactTitle">Проверьте влияние условий</h3><p>Изменения затронут форму уже созданных документов.</p></div><button class="modal-close" type="button" data-impact-cancel aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
        + '<div class="modal-body"><div class="rubrics-impact-mode is-' + (enabled ? 'enabled' : 'disabled') + '"><i class="ti ' + (enabled ? 'ti-bolt' : 'ti-bolt-off') + '"></i><span><b>Условия будут ' + (enabled ? 'включены' : 'выключены') + '</b><small>' + (enabled ? 'Форма и серверная проверка начнут учитывать правила.' : 'Все поля снова будут доступны без условных правил.') + '</small></span></div>'
        + '<div class="rubrics-impact-summary"><span><b>' + (parseInt(impact.changed_fields, 10) || 0) + '</b><small>полей с изменениями</small></span><span><b>' + (parseInt(impact.documents_changed, 10) || 0) + '</b><small>форм изменят поведение</small></span><span><b>' + (parseInt(impact.hidden_after, 10) || 0) + '</b><small>скрыто · заблокировано ' + (parseInt(impact.locked_after, 10) || 0) + ' · автодействий ' + (parseInt(impact.action_after, 10) || 0) + '</small></span></div>'
        + (impact.truncated ? '<p class="rubrics-impact-notice"><i class="ti ti-info-circle"></i>Проверены ' + analyzed + ' из ' + total + ' документов. Итоговые числа для всей рубрики могут быть больше.</p>' : '')
        + '<div class="rubrics-impact-list">' + detailsHtml + '</div></div>'
        + '<div class="modal-footer"><div class="mf-left text-secondary text-sm">Документов проверено: ' + analyzed + '</div><button class="btn btn-ghost" type="button" data-impact-cancel>Вернуться</button><button class="btn btn-primary" type="button" data-impact-confirm><i class="ti ti-check"></i>Применить изменения</button></div></div>';
      document.body.appendChild(overlay);
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var onKey = function (e) { if (e.key === 'Escape') { close(); } };
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-impact-cancel]')) { close(); return; }
        if (e.target.closest('[data-impact-confirm]')) {
          close();
          onConfirm();
        }
      });
      document.addEventListener('keydown', onKey);
      requestAnimationFrame(function () {
        overlay.classList.add('show');
        var button = overlay.querySelector('[data-impact-confirm]');
        if (button) { button.focus(); }
      });
    },

    renderGroupList: function (groups) {
      var html = '';
      groups.forEach(function (group) {
		var condition = group.condition && typeof group.condition === 'object' ? group.condition : {};
        html += '<div class="rubrics-group-row" draggable="true" data-group-row data-id="' + group.id + '" data-title="' + esc(group.title) + '" data-description="' + esc(group.description) + '" data-condition="' + esc(JSON.stringify(condition)) + '">'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-group-drag" type="button" data-group-drag-handle data-tooltip-right="Перетащить" aria-label="Перетащить"><i class="ti ti-grip-vertical"></i></button>'
		  + '<div><b>' + esc(group.title) + '</b><small>' + esc(group.fields_count) + ' полей' + (condition.tree ? ' · есть условие' : '') + '</small></div>'
          + '<div class="cluster rubrics-actions">'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-edit" type="button" data-group-edit data-open-drawer="rubricGroupDrawer" data-tooltip-left="Изменить" aria-label="Изменить"><i class="ti ti-pencil"></i></button>'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-group-delete data-tooltip-left="Удалить" aria-label="Удалить"><i class="ti ti-trash"></i></button>'
          + '</div></div>';
      });
      if (!html) { html = '<div class="empty-state">Группы не созданы.</div>'; }
      var root = document.querySelector('[data-rubric-groups-list]');
      if (root) { root.innerHTML = html; }
    },

    fillFieldNew: function () {
      this.clearErrors(this.fieldForm);
      this.fieldForm.reset();
      this.fieldForm.elements.id.value = '';
      this.fieldForm.elements.rubric_id.value = this.currentRubricId;
      this.syncFieldTypeOptions('');
      document.getElementById('rubricFieldTitle').textContent = 'Новое поле';
      this.fillGroupSelect();
      this.setHint('[data-field-alias-state]', '');
      this.syncFieldTemplateEditors();
      this.syncNativeNumericType(this.fieldForm.elements.rubric_field_type.value);
      this.loadTypeInfo(this.fieldForm.elements.rubric_field_type.value);
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricFieldDrawer'); }
    },

    resetFieldForm: function () {
      if (!this.fieldForm) { return; }
      clearTimeout(this.fieldAliasTimer);
      this.fieldAliasTimer = null;
      this.activeFieldTemplateTextarea = null;
      this.clearErrors(this.fieldForm);
      this.fieldForm.reset();
      this.fieldForm.elements.id.value = '';
      this.fieldForm.elements.rubric_id.value = this.currentRubricId || '';
      this.setHint('[data-field-alias-state]', '');
      this.syncFieldTypeOptions('');
      ['rubric_field_template', 'rubric_field_template_request'].forEach(function (name) {
        var textarea = this.fieldForm.elements[name];
        if (textarea && textarea._adminxCodeMirror) {
          textarea._adminxCodeMirror.setValue('');
          textarea._adminxCodeMirror.save();
        }
      }, this);
      var plugin = document.querySelector('[data-field-plugin-body]');
      if (plugin) { plugin.innerHTML = '<div class="empty-state">Выберите тип поля.</div>'; }
      var title = document.getElementById('rubricFieldTitle');
      if (title) { title.textContent = 'Новое поле'; }
    },

    /** Перенести значения textarea шаблонов поля в CodeMirror и обновить отрисовку. */
    syncFieldTemplateEditors: function () {
      var form = this.fieldForm;
      if (!form) { return; }
      ['rubric_field_template', 'rubric_field_template_request'].forEach(function (name) {
        var ta = form.elements[name];
        if (ta && ta._adminxCodeMirror) { ta._adminxCodeMirror.setValue(ta.value || ''); }
      });
      this.refreshEditors();
    },

    /** Вставить тег из легенды в активный (или первый) редактор шаблона поля. */
    insertFieldTemplateTag: function (tag) {
      if (!tag || !this.fieldForm) { return; }
      var ta = this.activeFieldTemplateTextarea;
      if (!ta || !ta.closest || !ta.closest('#rubricFieldForm')) {
        ta = this.fieldForm.elements.rubric_field_template;
      }
      if (!ta) { return; }
      var cm = ta._adminxCodeMirror;
      if (cm) {
        cm.replaceSelection(tag);
        cm.focus();
        cm.save();
        Adminx.Toast.show('Тег вставлен', 'success');
        return;
      }
      var start = ta.selectionStart || 0;
      ta.value = ta.value.slice(0, start) + tag + ta.value.slice(ta.selectionEnd || start);
      ta.focus();
    },

    fillFieldEdit: function (row) {
      if (!row) { return; }
      var self = this;
      this.clearErrors(this.fieldForm);
      fetch(this.base() + '/rubrics/fields/' + row.getAttribute('data-id'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var item = json.data || {};
          self.fillGroupSelect();
          self.fieldForm.elements.id.value = item.Id || '';
          self.fieldForm.elements.rubric_id.value = item.rubric_id || self.currentRubricId;
          self.fieldForm.elements.rubric_field_title.value = item.rubric_field_title || '';
          self.fieldForm.elements.rubric_field_alias.value = item.rubric_field_alias || '';
          self.syncFieldTypeOptions(item.rubric_field_type || '');
          self.fieldForm.elements.rubric_field_type.value = item.rubric_field_type || '';
          self.fieldForm.elements.rubric_field_group.value = String(item.rubric_field_group || 0);
          self.fieldForm.elements.rubric_field_search.value = String(item.rubric_field_search || 0);
          self.fieldForm.elements.rubric_field_numeric.value = String(item.rubric_field_numeric || 0);
          self.syncNativeNumericType(item.rubric_field_type || '');
          self.fieldForm.elements.rubric_field_default.value = item.rubric_field_default || '';
          self.fieldForm.elements.rubric_field_description.value = item.rubric_field_description || '';
          self.fieldForm.elements.rubric_field_template.value = item.rubric_field_template || '';
          self.fieldForm.elements.rubric_field_template_request.value = item.rubric_field_template_request || '';
          var L = item.layout || {};
          var el = self.fieldForm.elements;
          if (el['rubric_field_layout[width]']) { el['rubric_field_layout[width]'].value = L.width || ''; }
          if (el['rubric_field_layout[prefix]']) { el['rubric_field_layout[prefix]'].value = L.prefix || ''; }
          if (el['rubric_field_layout[suffix]']) { el['rubric_field_layout[suffix]'].value = L.suffix || ''; }
          document.getElementById('rubricFieldTitle').textContent = 'Редактирование поля #' + item.Id;
          self.setHint('[data-field-alias-state]', '');
          self.syncFieldTemplateEditors();
          self.loadFieldPlugin(item.Id);
        });
    },

    loadTypeInfo: function (type) {
      var root = document.querySelector('[data-field-plugin-body]');
      var self = this;
      if (!root || !type) { return; }
      var defaultWrap = document.querySelector('.rubrics-default-field');
      if (defaultWrap) { defaultWrap.hidden = false; }
      root.innerHTML = '<div class="empty-state">Загрузка типа поля...</div>';
      fetch(this.base() + '/rubrics/field-types/' + encodeURIComponent(type), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          self.renderPluginInfo({ type: json.data || null, field: null, render: null });
        })
        .catch(function () {
          root.innerHTML = '<div class="empty-state">Не удалось загрузить тип поля.</div>';
        });
    },

    syncNativeNumericType: function (type) {
      if (!this.fieldForm || !this.fieldForm.elements.rubric_field_numeric) { return; }
      var numeric = this.fieldForm.elements.rubric_field_numeric;
      var inherent = ['number', 'date_time', 'period'].indexOf(String(type || '')) !== -1;
      if (inherent) { numeric.value = '1'; }
      numeric.disabled = inherent;
      numeric.title = inherent ? 'Этот тип всегда использует числовой индекс' : '';
    },

    loadFieldPlugin: function (id) {
      var root = document.querySelector('[data-field-plugin-body]');
      var self = this;
      if (!root || !id) { return; }
      var defaultWrap = document.querySelector('.rubrics-default-field');
      if (defaultWrap) { defaultWrap.hidden = false; }
      root.innerHTML = '<div class="empty-state">Загрузка настроек типа...</div>';
      fetch(this.base() + '/rubrics/fields/' + encodeURIComponent(id) + '/plugin', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          self.renderPluginInfo(json.data || {});
        })
        .catch(function () {
          root.innerHTML = '<div class="empty-state">Не удалось загрузить настройки типа.</div>';
        });
    },

    renderFieldSettings: function (html) {
      if (!html) { return ''; }
      return '<div class="rubrics-field-settings-block">'
        + '<div class="rubrics-plugin-subhead"><i class="ti ti-adjustments"></i><b>Настройки поля</b></div>'
        + html + '</div>';
    },

    renderPluginInfo: function (data) {
      var root = document.querySelector('[data-field-plugin-body]');
      var type = data.type || null;
      var render = data.render || null;
      var statusClass;
      var statusText;
      if (!root) { return; }
      if (!type) {
        root.innerHTML = '<div class="empty-state">Тип поля не зарегистрирован.</div>';
        return;
      }
      this.applyFieldAdminEditor(type.admin_editor || {});
      statusClass = type.status === 'ok' ? 'badge-green' : (type.status === 'error' ? 'badge-red' : 'badge-amber');
      statusText = type.status === 'ok' ? 'готов' : (type.status === 'error' ? 'ошибка' : 'проверить');
      var settings = this.renderFieldSettings(type.settings_html || '');
      // Показываем только реальные настройки типа. Диагностика плагина
      // (источник/функция/шаблоны/ассеты/preview) — не для контент-редактора;
      // проблемы показываем лишь если они есть.
      var issues = (type.issues && type.issues.length) ? this.renderPluginIssues(type.issues) : '';
      root.innerHTML = '<div class="rubrics-plugin-meta">'
        + '<div><b>' + esc(type.name) + '</b><small class="mono">' + esc(type.id) + '</small></div>'
        + '<span class="badge ' + statusClass + '">' + statusText + '</span>'
        + '</div>'
        + (settings || '<p class="rubrics-plugin-empty">У этого типа нет дополнительных настроек.</p>')
        + issues;
      this.hydrateSettingsMaps(root);
      this.syncOptionSourceFields(root);
    },

    renderAdminEditor: function (editor) {
      var controls = editor.controls || [];
      var status = editor.status === 'native' ? 'adminx' : 'fallback';
      var badge = editor.status === 'native' ? 'badge-green' : 'badge-amber';
      if (!editor || !editor.title) { return ''; }
      return '<div class="rubrics-admin-editor">'
        + '<div class="rubrics-admin-editor-head">'
        + '<span class="icon-tile rubrics-admin-editor-icon" style="--tile-bg:var(--blue-100);--tile-fg:var(--blue-600)"><i class="ti ti-' + esc(editor.icon || 'settings') + '"></i></span>'
        + '<div><b>' + esc(editor.title) + '</b><small>' + esc(editor.summary || '') + '</small></div>'
        + '<span class="badge ' + badge + '">' + status + '</span>'
        + '</div>'
        + '<div class="rubrics-admin-editor-controls">'
        + controls.map(function (control) {
          return '<span><i class="ti ti-adjustments"></i><b>' + esc(control.label || control.name) + '</b><small>' + esc(Adminx.Rubrics.kindLabel(control.kind || 'text')) + '</small></span>';
        }).join('')
        + '</div>'
        + this.renderAdminEntityFields(editor.entity_fields || [])
        + '</div>';
    },

    renderAdminEntityFields: function (fields) {
      if (!fields.length) { return ''; }
      return '<div class="rubrics-admin-editor-entity">'
        + '<b>Сущность поля</b>'
        + '<div>' + fields.map(function (field) {
          return '<code>' + esc(field) + '</code>';
        }).join('') + '</div>'
        + '</div>';
    },

    kindLabel: function (kind) {
      var map = {
        text: 'короткий текст',
        textarea: 'многострочный текст',
        lines: 'список строк',
        key_value: 'ключ и значение',
        boolean: 'да / нет',
        date: 'дата',
        datetime: 'дата и время',
        number: 'число',
        range: 'диапазон',
        dimensions: 'три размера',
        packages: 'список упаковок',
        address: 'структурированный адрес',
        period: 'период дат',
        pipe: 'части через |',
        pipe_list: 'строки с колонками',
        media: 'медиафайл',
        media_list: 'список файлов',
        relation: 'документ',
        relation_list: 'список документов',
        url: 'ссылка',
        code: 'код'
      };
      return map[kind] || kind;
    },

    applyFieldAdminEditor: function (editor) {
      var self = this;
      var controls = editor.controls || [];
      var byName = {};
      controls.forEach(function (control) {
        if (control.name) { byName[control.name] = control; }
      });
      var defaultWrap = document.querySelector('.rubrics-default-field');
      if (defaultWrap) { defaultWrap.hidden = !!editor.default_is_configuration; }
      ['rubric_field_default', 'rubric_field_template', 'rubric_field_template_request'].forEach(function (name) {
        var control = byName[name] || {};
        var label = document.querySelector('[data-field-control-label="' + name + '"]');
        var hint = document.querySelector('[data-field-control-hint="' + name + '"]');
        var input = self.fieldForm ? self.fieldForm.elements[name] : null;
        if (label && control.label) { label.textContent = control.label; }
        if (hint) { hint.textContent = control.hint || ''; }
        if (input) {
          input.setAttribute('data-admin-editor-kind', control.kind || 'text');
          input.placeholder = self.placeholderForControl(control.kind || 'text');
          if (name === 'rubric_field_default') {
            input.rows = self.rowsForControl(control.kind || 'text');
            input.value = self.storageValueToEditor(input.value, control.kind || 'text');
            self.renderDefaultEditor(control.kind || 'text');
          }
        }
      });
    },

    renderDefaultEditor: function (kind) {
      var storage = this.defaultStorage();
      var root = document.querySelector('[data-field-default-editor]');
      var value;
      if (!storage || !root) { return; }
      value = this.storageValueToEditor(storage.value, kind);
      storage.value = value;
      storage.hidden = false;
      root.hidden = true;
      root.innerHTML = '';
      storage.classList.toggle('mono', kind === 'code');

      if (['text', 'url', 'media', 'date', 'datetime', 'number', 'pipe', 'relation'].indexOf(kind) !== -1) {
        var inputType = kind === 'date' ? 'date' : (kind === 'datetime' ? 'datetime-local' : (kind === 'url' ? 'url' : (kind === 'number' || kind === 'relation' ? 'number' : 'text')));
        var inputIcon = kind === 'date' ? 'calendar' : (kind === 'datetime' ? 'calendar-time' : (kind === 'url' ? 'link' : (kind === 'media' ? 'photo' : (kind === 'relation' ? 'file-search' : (kind === 'number' ? 'number' : 'forms')))));
        storage.hidden = true;
        root.hidden = false;
        root.innerHTML = '<div class="rubrics-default-input-wrap' + (kind === 'media' || kind === 'relation' ? ' has-action' : '') + '">'
          + '<i class="ti ti-' + inputIcon + '"></i>'
          + '<input class="input" type="' + inputType + '" value="' + esc(value) + '" placeholder="' + esc(this.placeholderForControl(kind)) + '" data-default-editor-input>'
          + (kind === 'media' ? '<button class="btn btn-secondary btn-icon btn-sm rubrics-default-media-btn" type="button" data-default-media-pick data-tooltip="Выбрать файл" aria-label="Выбрать файл"><i class="ti ti-photo-plus"></i></button>' : '')
          + (kind === 'relation' ? '<button class="btn btn-secondary btn-icon btn-sm rubrics-default-relation-btn" type="button" data-default-relation-pick data-tooltip="Выбрать документ" aria-label="Выбрать документ"><i class="ti ti-file-search"></i></button>' : '')
          + '</div>';
        return;
      }

      if (kind === 'boolean') {
        storage.hidden = true;
        root.hidden = false;
        root.innerHTML = '<div class="rubrics-default-boolean" role="group" aria-label="Значение по умолчанию">'
          + '<button class="rubrics-default-choice" type="button" data-default-boolean="1" aria-pressed="false"><i class="ti ti-check"></i><span>Включено</span></button>'
          + '<button class="rubrics-default-choice" type="button" data-default-boolean="0" aria-pressed="false"><i class="ti ti-x"></i><span>Выключено</span></button>'
          + '</div>';
        this.setDefaultBoolean(value === '1' ? '1' : '0');
        return;
      }

      if (['lines', 'key_value', 'media_list', 'pipe_list', 'relation_list', 'packages'].indexOf(kind) !== -1) {
        storage.hidden = true;
        root.hidden = false;
        root.setAttribute('data-default-list-kind', kind);
        root.innerHTML = '<div class="rubrics-default-list" data-default-list>'
          + '<div class="rubrics-default-list-rows" data-default-list-rows></div>'
          + '<button class="btn btn-secondary btn-sm rubrics-default-list-add" type="button" data-default-list-add><i class="ti ti-plus"></i>Добавить строку</button>'
          + '</div>';
        this.renderDefaultListRows(this.defaultListParts(value, kind), kind);
      }
    },

    defaultStorage: function () {
      return this.fieldForm ? this.fieldForm.querySelector('[data-field-default-storage]') : null;
    },

    syncDefaultEditorValue: function (input) {
      var storage = this.defaultStorage();
      if (!storage || !input) { return; }
      storage.value = input.value || '';
    },

    setDefaultBoolean: function (value) {
      var storage = this.defaultStorage();
      value = value === '1' ? '1' : '0';
      if (storage) { storage.value = value; }
      document.querySelectorAll('[data-default-boolean]').forEach(function (btn) {
        var active = btn.getAttribute('data-default-boolean') === value;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    },

    defaultListParts: function (value, kind) {
      value = String(value == null ? '' : value);
      if (kind === 'media_list') {
        return value.split(/[\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean);
      }
      if (kind === 'pipe_list') {
        return value.split(/[,\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean);
      }
      if (kind === 'relation_list') {
        return value.split(/[|,\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean);
      }
      if (kind === 'packages') {
        return value.split(/[\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean);
      }
      return value.split(/[,\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean);
    },

    renderDefaultListRows: function (parts, kind) {
      var root = document.querySelector('[data-default-list-rows]');
      if (!root) { return; }
      if (!parts.length) { parts = ['']; }
      root.classList.toggle('is-key-value', kind === 'key_value');
      root.innerHTML = parts.map(function (part, index) {
        var placeholder = kind === 'key_value' ? 'key|Название' : (kind === 'media_list' ? '/uploads/file.webp' : (kind === 'relation_list' ? 'ID документа' : (kind === 'pipe_list' ? 'Колонка1|Колонка2|Колонка3' : (kind === 'packages' ? 'длина|ширина|высота|вес' : 'Вариант'))));
        var keyValue;
        if (kind === 'key_value') {
          keyValue = part.split('|');
          return '<div class="rubrics-default-list-row is-key-value" data-default-list-row>'
            + '<span class="rubrics-default-list-index">' + (index + 1) + '</span>'
            + '<input class="input" type="text" value="' + esc(keyValue[0] || '') + '" placeholder="Ключ" data-default-list-key>'
            + '<input class="input" type="text" value="' + esc(keyValue.slice(1).join('|') || '') + '" placeholder="Название" data-default-list-value>'
            + '<button class="btn btn-ghost btn-icon btn-sm rubrics-default-list-remove" type="button" data-default-list-remove data-tooltip="Удалить строку" aria-label="Удалить строку"><i class="ti ti-trash"></i></button>'
            + '</div>';
        }
        return '<div class="rubrics-default-list-row' + (kind === 'media_list' || kind === 'relation_list' ? ' is-media-list' : '') + '" data-default-list-row>'
          + '<span class="rubrics-default-list-index">' + (index + 1) + '</span>'
          + '<input class="input" type="' + (kind === 'relation_list' ? 'number' : 'text') + '" value="' + esc(part) + '" placeholder="' + esc(placeholder) + '" data-default-list-input>'
          + (kind === 'media_list' ? '<button class="btn btn-secondary btn-icon btn-sm rubrics-default-list-pick" type="button" data-default-media-row-pick data-tooltip="Выбрать файл" aria-label="Выбрать файл"><i class="ti ti-photo-plus"></i></button>' : '')
          + (kind === 'relation_list' ? '<button class="btn btn-secondary btn-icon btn-sm rubrics-default-list-pick" type="button" data-default-relation-row-pick data-tooltip="Выбрать документ" aria-label="Выбрать документ"><i class="ti ti-file-search"></i></button>' : '')
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-default-list-remove" type="button" data-default-list-remove data-tooltip="Удалить строку" aria-label="Удалить строку"><i class="ti ti-trash"></i></button>'
          + '</div>';
      }).join('');
      this.syncDefaultListValue();
    },

    addDefaultListRow: function () {
      var root = document.querySelector('[data-default-list-rows]');
      var kindRoot = document.querySelector('[data-default-list-kind]');
      var kind = kindRoot ? kindRoot.getAttribute('data-default-list-kind') : 'lines';
      var parts = this.readDefaultListInputs();
      parts.push('');
      this.renderDefaultListRows(parts, kind);
      var inputs = root ? root.querySelectorAll('[data-default-list-input], [data-default-list-key]') : [];
      if (inputs.length) { inputs[inputs.length - 1].focus(); }
    },

    removeDefaultListRow: function (row) {
      var kindRoot = document.querySelector('[data-default-list-kind]');
      var kind = kindRoot ? kindRoot.getAttribute('data-default-list-kind') : 'lines';
      var parts;
      if (row) { row.remove(); }
      parts = this.readDefaultListInputs();
      this.renderDefaultListRows(parts.length ? parts : [''], kind);
    },

    readDefaultListInputs: function () {
      var keyRows = document.querySelectorAll('[data-default-list-row].is-key-value');
      if (keyRows.length) {
        return Array.prototype.map.call(keyRows, function (row) {
          var key = row.querySelector('[data-default-list-key]');
          var value = row.querySelector('[data-default-list-value]');
          key = key ? key.value.trim() : '';
          value = value ? value.value.trim() : '';
          return key || value ? key + '|' + value : '';
        });
      }
      return Array.prototype.map.call(document.querySelectorAll('[data-default-list-input]'), function (input) {
        return input.value || '';
      });
    },

    syncDefaultListValue: function () {
      var storage = this.defaultStorage();
      var kindRoot = document.querySelector('[data-default-list-kind]');
      var kind = kindRoot ? kindRoot.getAttribute('data-default-list-kind') : 'lines';
      var separator = kind === 'media_list' ? '\n' : '\n';
      if (!storage) { return; }
      storage.value = this.readDefaultListInputs().map(function (part) {
        return String(part || '').trim();
      }).filter(Boolean).join(separator);
    },

    openDefaultMediaPicker: function (row) {
      var self = this;
      Adminx.MediaPicker.open({
        type: 'image',
        title: 'Выбрать файл',
        description: 'Файл будет записан в значение по умолчанию поля.',
        onPick: function (file) { if (file && file.url) { self.applyPickedMedia(file.url, row); } }
      });
    },

    applyPickedMedia: function (url, row) {
      var input;
      if (row) {
        input = row.querySelector('[data-default-list-input]');
        if (input) {
          input.value = url;
          this.syncDefaultListValue();
        }
        return;
      }
      input = document.querySelector('[data-default-editor-input]');
      if (input) {
        input.value = url;
        this.syncDefaultEditorValue(input);
      }
    },

    openDefaultRelationPicker: function (row) {
      var self = this;
      var overlay = document.createElement('div');
      overlay.className = 'overlay rubrics-relation-picker-overlay';
      overlay.innerHTML = '<div class="modal picker-modal rubrics-relation-picker" role="dialog" aria-modal="true">'
        + '<div class="modal-header"><span class="dialog-icon info"><i class="ti ti-file-search"></i></span><div style="flex:1"><h3>Выбрать документ</h3><p class="text-secondary" style="margin-top:4px">В значение будет записан ID документа.</p></div><button class="modal-close" type="button" data-relation-cancel aria-label="Закрыть"><i class="ti ti-x"></i></button></div>'
        + '<div class="modal-body"><div class="rubrics-relation-tools"><div class="input-icon rubrics-relation-search"><i class="ti ti-search"></i><input class="input" type="search" placeholder="ID, название или алиас" data-relation-search></div></div><div class="rubrics-relation-status" data-relation-status>Загрузка...</div><div class="rubrics-relation-list" data-relation-list></div></div>'
        + '<div class="modal-footer"><div class="mf-left rubrics-relation-count" data-relation-count></div><button class="btn btn-ghost" type="button" data-relation-cancel>Закрыть</button></div>'
        + '</div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.add('show'); });
      this.bindDefaultRelationPicker(overlay, row);
    },

    bindDefaultRelationPicker: function (overlay, targetRow) {
      var self = this;
      var list = overlay.querySelector('[data-relation-list]');
      var status = overlay.querySelector('[data-relation-status]');
      var search = overlay.querySelector('[data-relation-search]');
      var count = overlay.querySelector('[data-relation-count]');
      var close = function () {
        overlay.classList.remove('show');
        setTimeout(function () { overlay.remove(); document.removeEventListener('keydown', onKey); }, 180);
      };
      var render = function (items) {
        list.innerHTML = '';
        (items || []).forEach(function (item) {
          list.insertAdjacentHTML('beforeend', '<button class="rubrics-relation-item" type="button" data-relation-id="' + esc(item.id) + '">'
            + '<span class="rubrics-relation-id">#' + esc(item.id) + '</span>'
            + '<span class="rubrics-relation-main"><b>' + esc(item.title || 'Без названия') + '</b><small>' + esc(item.alias || '') + '</small></span>'
            + '<span class="badge badge-blue">' + esc(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</span>'
            + '</button>');
        });
        status.hidden = items && items.length > 0;
        status.textContent = search.value.trim() ? 'Ничего не найдено' : 'Начните вводить название или ID';
        count.textContent = items && items.length ? 'Документов: ' + items.length : '';
      };
      var load = function () {
        status.textContent = 'Загрузка...';
        status.hidden = false;
        var params = new URLSearchParams();
        params.set('q', search.value.trim());
        params.set('limit', 20);
        fetch(self.base() + '/rubrics/documents/picker?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (res) { return res.json(); })
          .then(function (json) {
            var data = json.data && json.data.success ? json.data.data : (json.data || {});
            render(data.items || []);
          })
          .catch(function () {
            list.innerHTML = '';
            status.textContent = 'Не удалось загрузить документы';
            status.hidden = false;
          });
      };
      var apply = function (id) {
        self.applyPickedRelation(id, targetRow);
        close();
      };
      var timer = null;
      var onKey = function (e) { if (e.key === 'Escape') { close(); } };
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-relation-cancel]')) { close(); return; }
        var item = e.target.closest('[data-relation-id]');
        if (item) { apply(item.getAttribute('data-relation-id')); }
      });
      search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(load, 220);
      });
      document.addEventListener('keydown', onKey);
      load();
      search.focus();
    },

    applyPickedRelation: function (id, row) {
      var input;
      if (row) {
        input = row.querySelector('[data-default-list-input]');
        if (input) {
          input.value = id;
          this.syncDefaultListValue();
        }
        return;
      }
      input = document.querySelector('[data-default-editor-input]');
      if (input) {
        input.value = id;
        this.syncDefaultEditorValue(input);
      }
    },

    storageValueToEditor: function (value, kind) {
      value = String(value == null ? '' : value);
      if (kind === 'packages' && value.trim().charAt(0) === '[') {
        try {
          var packages = JSON.parse(value);
          if (Array.isArray(packages)) {
            return packages.map(function (item) {
              item = item || {};
              return [item.length || '', item.width || '', item.height || '', item.weight || ''].join('|');
            }).join('\n');
          }
        } catch (ignore) {}
      }
      if (kind === 'address' && value.trim().charAt(0) === '{') {
        try {
          var address = JSON.parse(value) || {};
          return ['postal_code', 'region', 'city', 'street', 'building', 'unit', 'latitude', 'longitude'].map(function (key) {
            return address[key] || '';
          }).join('|');
        } catch (ignoreAddress) {}
      }
      if (kind === 'period' && value.trim().charAt(0) === '{') {
        try {
          var period = JSON.parse(value) || {};
          var periodPart = function (timestamp) {
            if (!/^\d+$/.test(String(timestamp || ''))) { return ''; }
            var date = new Date(parseInt(timestamp, 10) * 1000);
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            return date.getFullYear() + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
          };
          return periodPart(period.start) + '|' + periodPart(period.end);
        } catch (ignorePeriod) {}
      }
      if ((kind === 'lines' || kind === 'key_value') && value.indexOf('\n') === -1) {
        if (kind === 'lines' && value.indexOf('|') !== -1 && value.indexOf(',') === -1) {
          return value.split('|').map(function (part) { return part.trim(); }).filter(Boolean).join('\n');
        }
        return value.split(',').map(function (part) { return part.trim(); }).filter(Boolean).join('\n');
      }
      if (kind === 'pipe_list' && value.indexOf('\n') === -1) {
        return value.split(',').map(function (part) { return part.trim(); }).filter(Boolean).join('\n');
      }
      if (kind === 'relation_list' && value.indexOf('\n') === -1) {
        return value.split(/[|,]+/).map(function (part) { return part.trim(); }).filter(Boolean).join('\n');
      }
      if (kind === 'boolean') {
        value = value.trim().toLowerCase();
        return ['1', 'true', 'yes', 'on', 'да', 'вкл', 'включено'].indexOf(value) !== -1 ? '1' : '0';
      }
      if (kind === 'date' && /^\d{9,10}$/.test(value)) {
        var date = new Date(parseInt(value, 10) * 1000);
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
      }
      if (kind === 'datetime' && /^\d{9,10}$/.test(value)) {
        var dateTime = new Date(parseInt(value, 10) * 1000);
        var dateTimeMonth = String(dateTime.getMonth() + 1).padStart(2, '0');
        var dateTimeDay = String(dateTime.getDate()).padStart(2, '0');
        var dateTimeHours = String(dateTime.getHours()).padStart(2, '0');
        var dateTimeMinutes = String(dateTime.getMinutes()).padStart(2, '0');
        return dateTime.getFullYear() + '-' + dateTimeMonth + '-' + dateTimeDay + 'T' + dateTimeHours + ':' + dateTimeMinutes;
      }
      return value;
    },

    editorValueToStorage: function (value, kind) {
      value = String(value == null ? '' : value);
      if (kind === 'lines' || kind === 'key_value' || kind === 'pipe_list' || kind === 'relation_list') {
        return value.split(/[,\r\n]+/).map(function (part) { return part.trim(); }).filter(Boolean).join(',');
      }
      if (kind === 'boolean') {
        value = value.trim().toLowerCase();
        return ['1', 'true', 'yes', 'on', 'да', 'вкл', 'включено'].indexOf(value) !== -1 ? '1' : '0';
      }
      return value;
    },

    placeholderForControl: function (kind) {
      var map = {
        text: 'Текстовое значение',
        textarea: 'Текст или параметры поля',
        lines: 'Один вариант на строку',
        key_value: 'key|Название',
        boolean: '0 или 1',
        date: 'YYYY-MM-DD',
        datetime: 'YYYY-MM-DD HH:MM',
        number: '0',
        range: 'минимум|максимум',
        dimensions: 'длина|ширина|высота',
        packages: 'длина|ширина|высота|вес',
        address: 'индекс|регион|город|улица|дом|помещение|широта|долгота',
        period: 'YYYY-MM-DD|YYYY-MM-DD',
        pipe: 'значение1|значение2',
        pipe_list: 'Колонка1|Колонка2|Колонка3',
        relation: 'ID документа',
        relation_list: 'ID документа',
        media: '/uploads/example.webp',
        media_list: '/uploads/one.webp\n/uploads/two.webp',
        url: '/catalog',
        code: '<?php echo $field_value; ?>'
      };
      return map[kind] || '';
    },

    rowsForControl: function (kind) {
      if (kind === 'text' || kind === 'boolean' || kind === 'date' || kind === 'datetime' || kind === 'url' || kind === 'media' || kind === 'number' || kind === 'range' || kind === 'dimensions' || kind === 'period' || kind === 'pipe' || kind === 'relation') { return 2; }
      if (kind === 'address') { return 3; }
      if (kind === 'lines' || kind === 'key_value' || kind === 'media_list' || kind === 'pipe_list' || kind === 'relation_list' || kind === 'packages') { return 7; }
      if (kind === 'code') { return 8; }
      return 4;
    },

    renderPluginIssues: function (issues) {
      if (!issues.length) {
        return '<div class="rubrics-plugin-ok"><i class="ti ti-circle-check"></i>Критичных проблем не найдено.</div>';
      }
      return '<div class="rubrics-plugin-issues">' + issues.map(function (issue) {
        return '<span><i class="ti ti-alert-triangle"></i>' + esc(issue) + '</span>';
      }).join('') + '</div>';
    },

    renderPluginFiles: function (title, files) {
      if (!files.length) { return ''; }
      return '<div class="rubrics-plugin-files"><b>' + esc(title) + '</b>' + files.slice(0, 6).map(function (file) {
        return '<code>' + esc(file) + '</code>';
      }).join('') + (files.length > 6 ? '<small>+' + (files.length - 6) + '</small>' : '') + '</div>';
    },

    renderPluginPreview: function (render) {
      var statusClass;
      if (!render) { return ''; }
      statusClass = render.status === 'ok' ? 'is-ok' : (render.status === 'error' ? 'is-error' : 'is-warning');
      return '<div class="rubrics-plugin-render ' + statusClass + '">'
        + '<div class="rubrics-plugin-render-head">'
        + '<b>Preview edit-режима</b>'
        + '<span class="badge ' + (render.status === 'ok' ? 'badge-green' : (render.status === 'error' ? 'badge-red' : 'badge-amber')) + '">' + esc(render.status || 'info') + '</span>'
        + '</div>'
        + '<p>' + esc(render.message || '') + '</p>'
        + this.renderPluginNotes(render.notes || [])
        + (render.html ? '<div class="rubrics-plugin-render-frame">' + render.html + '</div>' : '')
        + this.renderPluginFiles('Preview assets', render.assets || [])
        + '</div>';
    },

    renderPluginNotes: function (notes) {
      if (!notes.length) { return ''; }
      return '<div class="rubrics-plugin-notes">' + notes.map(function (note) {
        return '<span><i class="ti ti-info-circle"></i>' + esc(note) + '</span>';
      }).join('') + '</div>';
    },

    submitField: function () {
      var self = this;
      var id = this.fieldForm.elements.id.value;
      var rubricId = this.fieldForm.elements.rubric_id.value || this.currentRubricId;
      if (window.Adminx && Adminx.CodeEditor && Adminx.CodeEditor.syncAll) { Adminx.CodeEditor.syncAll(this.fieldForm); }
      this.clearErrors(this.fieldForm);
      this.checkFieldAlias(true, function (ok) {
        if (!ok) { return; }
        self.ajax(self.base() + (id ? '/rubrics/fields/' + id : '/rubrics/' + rubricId + '/fields'), self.fieldFormData(), function () {
          Adminx.Toast.show('Поле сохранено', 'success');
          if (Adminx.Drawer) { Adminx.Drawer.close('rubricFieldDrawer'); }
          self.loadFields();
          self.applyFilterUrl(window.location.href, false);
        }, function (json) { self.showErrors(self.fieldForm, json); });
      });
    },

    fieldFormData: function () {
      var data = new FormData(this.fieldForm);
      var input = this.fieldForm.elements.rubric_field_default;
      var kind = input ? input.getAttribute('data-admin-editor-kind') : '';
      if (input) {
        data.set('rubric_field_default', this.editorValueToStorage(input.value, kind));
      }
      return data;
    },

    checkFieldAlias: function (required, done) {
      var alias = this.fieldForm.elements.rubric_field_alias.value.trim();
      var id = this.fieldForm.elements.id.value || 0;
      var rubricId = this.fieldForm.elements.rubric_id.value || this.currentRubricId;
      if (!alias && !required) {
        this.setHint('[data-field-alias-state]', '');
        if (done) { done(false); }
        return;
      }
      var self = this;
      fetch(this.base() + '/rubrics/fields/alias-check?alias=' + encodeURIComponent(alias) + '&rubric_id=' + encodeURIComponent(rubricId) + '&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var data = json.data || {};
          self.setHint('[data-field-alias-state]', data.message || '', data.valid && data.available);
          if (done) { done(!!(data.valid && data.available)); }
        });
    },

    deleteField: function (row) {
      if (!row || !window.confirm('Удалить поле? Значения документов для этого поля тоже будут удалены.')) { return; }
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      var self = this;
      this.ajax(this.base() + '/rubrics/fields/' + row.getAttribute('data-id') + '/delete', data, function () {
        Adminx.Toast.show('Поле удалено', 'success');
        self.loadFields();
        self.applyFilterUrl(window.location.href, false);
      });
    },

    openTrash: function () {
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricTrashDrawer'); }
      this.refreshTrash();
    },

    refreshTrash: function () {
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/rubrics/trash').then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!response.success || !response.data) {
          Adminx.Toast.show(response.message || 'Не удалось загрузить корзину', 'error');
          return;
        }
        self.renderTrash(response.data.items || []);
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети при загрузке корзины', 'error');
      });
    },

    renderTrash: function (items) {
      var list = document.querySelector('[data-rubric-trash-list]');
      var count = document.querySelector('[data-rubric-trash-count]');
      if (count) { count.textContent = items.length ? (items.length + ' в корзине') : 'Корзина пуста'; }
      if (!list) { return; }
      if (!items.length) {
        list.innerHTML = '<div class="empty-state">Корзина пуста. Удалённые рубрики появятся здесь.</div>';
        return;
      }
      list.innerHTML = items.map(function (item) {
        return '<div class="rubrics-revision-row">'
          + '<span class="icon-tile rubrics-revision-row-icon"><i class="ti ti-trash"></i></span>'
          + '<div class="rubrics-revision-row-main"><div><b>' + esc(item.rubric_title || ('Рубрика #' + item.rubric_id)) + '</b>'
          + (item.rubric_alias ? '<span class="badge badge-gray">' + esc(item.rubric_alias) + '</span>' : '') + '</div>'
          + '<small>Удалена ' + esc(item.deleted_label || '-') + (item.author_name ? ' · ' + esc(item.author_name) : '') + '</small></div>'
          + '<div class="rubrics-revision-row-counts"><span>' + item.fields_count + ' полей</span><span>' + item.groups_count + ' групп</span></div>'
          + '<div class="cluster">'
          + '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-rubric-trash-restore="' + item.id + '" data-tooltip="Восстановить" aria-label="Восстановить"><i class="ti ti-restore"></i></button>'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-rubric-trash-purge="' + item.id + '" data-tooltip="Удалить окончательно" aria-label="Удалить окончательно"><i class="ti ti-trash-x"></i></button>'
          + '</div></div>';
      }).join('');
    },

    restoreTrash: function (id) {
      id = parseInt(id, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Восстановить рубрику?',
        message: 'Рубрика вернётся со всей схемой и прежними идентификаторами.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/rubrics/trash/' + id + '/restore').then(function (payload) {
            Adminx.Loader.hide();
            var response = payload.data || {};
            if (!response.success) { Adminx.Toast.show(response.message || 'Не удалось восстановить', 'error'); return; }
            Adminx.Toast.show(response.message || 'Рубрика восстановлена', 'success');
            window.location.reload();
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    purgeTrash: function (id) {
      id = parseInt(id, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить рубрику окончательно?',
        message: 'Слепок будет стёрт без возможности восстановления.',
        confirmLabel: 'Удалить окончательно',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/rubrics/trash/' + id + '/purge').then(function (payload) {
            Adminx.Loader.hide();
            var response = payload.data || {};
            if (!response.success) { Adminx.Toast.show(response.message || 'Не удалось удалить', 'error'); return; }
            Adminx.Toast.show(response.message || 'Удалено окончательно', 'success');
            self.refreshTrash();
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    openSchemaRevisions: function (row) {
      if (!row) { return; }
      this.currentSchemaRevisionRubricId = parseInt(row.getAttribute('data-id'), 10) || 0;
      this.currentSchemaRevisionId = 0;
      this.currentSchemaRevisionFingerprint = '';
      var title = document.getElementById('rubricSchemaRevisionsTitle');
      var subtitle = document.querySelector('[data-schema-revisions-subtitle]');
      if (title) { title.textContent = 'Ревизии: ' + (row.getAttribute('data-title') || ('#' + this.currentSchemaRevisionRubricId)); }
      if (subtitle) { subtitle.textContent = 'Поля, группы, раскладка и условия формы. Значения документов в снимок не входят.'; }
      this.resetSchemaRevisionPreview();
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricSchemaRevisionsDrawer'); }
      this.refreshSchemaRevisions();
    },

    refreshSchemaRevisions: function () {
      if (!this.currentSchemaRevisionRubricId) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/rubrics/' + this.currentSchemaRevisionRubricId + '/schema-revisions').then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!response.success || !response.data) {
          Adminx.Toast.show(response.message || 'Не удалось загрузить ревизии', 'error');
          return;
        }
        self.renderSchemaRevisions(response.data.revisions || []);
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети при загрузке ревизий', 'error');
      });
    },

    renderSchemaRevisions: function (items) {
      var list = document.querySelector('[data-schema-revisions-list]');
      var count = document.querySelector('[data-schema-revisions-count]');
      var clear = document.querySelector('[data-schema-revisions-clear]');
      if (count) { count.textContent = items.length ? (items.length + ' снимков') : 'История пока пустая'; }
      if (clear) { clear.disabled = !items.length; }
      if (!list) { return; }
      if (!items.length) {
        list.innerHTML = '<div class="empty-state">Ревизий пока нет. Первый снимок появится после изменения полей или конструктора.</div>';
        this.resetSchemaRevisionPreview();
        return;
      }
      list.innerHTML = items.map(function (item) {
        return '<div class="rubrics-revision-row" data-schema-revision-open="' + item.id + '">'
          + '<span class="icon-tile rubrics-revision-row-icon"><i class="ti ti-history"></i></span>'
          + '<div class="rubrics-revision-row-main"><div><b>Снимок #' + item.id + '</b><span class="badge ' + esc(item.badge || 'badge-gray') + '">' + esc(item.action_label || item.action) + '</span></div>'
          + '<small>' + esc(item.created_label || '-') + (item.author_name ? ' · ' + esc(item.author_name) : '') + '</small>'
          + '<p>' + esc(item.comment || 'Схема рубрики') + '</p></div>'
          + '<div class="rubrics-revision-row-counts"><span>' + item.fields_count + ' полей</span><span>' + item.groups_count + ' групп</span></div>'
          + '<button class="btn btn-ghost btn-icon btn-sm rubrics-action-danger" type="button" data-schema-revision-row-delete="' + item.id + '" data-tooltip="Удалить снимок" aria-label="Удалить снимок"><i class="ti ti-trash"></i></button>'
          + '</div>';
      }).join('');
      this.loadSchemaRevision(items[0].id);
    },

    loadSchemaRevision: function (id) {
      id = parseInt(id, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Loader.show();
      Adminx.Ajax.request(this.base() + '/rubrics/schema-revisions/' + id).then(function (payload) {
        Adminx.Loader.hide();
        var response = payload.data || {};
        if (!response.success || !response.data) {
          Adminx.Toast.show(response.message || 'Не удалось открыть ревизию', 'error');
          return;
        }
        self.showSchemaRevision(response.data.revision || {}, response.data.impact || {});
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети при загрузке ревизии', 'error');
      });
    },

    showSchemaRevision: function (revision, impact) {
      this.currentSchemaRevisionId = parseInt(revision.id, 10) || 0;
      this.currentSchemaRevisionFingerprint = impact.fingerprint || '';
      document.querySelectorAll('[data-schema-revision-open]').forEach(function (row) {
        row.classList.toggle('is-active', row.getAttribute('data-schema-revision-open') === String(revision.id));
      });
      var title = document.querySelector('[data-schema-revision-title]');
      var meta = document.querySelector('[data-schema-revision-meta]');
      var preview = document.querySelector('[data-schema-revision-preview]');
      var remove = document.querySelector('[data-schema-revision-delete]');
      var restore = document.querySelector('[data-schema-revision-restore]');
      if (title) { title.textContent = 'Снимок #' + revision.id + ' · ' + (revision.action_label || revision.action || 'Схема'); }
      if (meta) { meta.textContent = (revision.created_label || '-') + (revision.author_name ? ' · ' + revision.author_name : '') + (revision.comment ? ' · ' + revision.comment : ''); }
      if (preview) { preview.innerHTML = this.renderSchemaImpact(impact); }
      if (remove) { remove.disabled = !this.currentSchemaRevisionId; }
      if (restore) { restore.disabled = !this.currentSchemaRevisionId || !impact.can_restore; }
    },

    renderSchemaImpact: function (impact) {
      var summary = impact.summary || {};
      var fields = impact.fields || {};
      var groups = impact.groups || {};
      var blockers = impact.blockers || [];
      var conditionText = impact.conditions_before === impact.conditions_after
        ? (impact.conditions_after ? 'Условия формы останутся включены' : 'Условия формы останутся выключены')
        : ('Условия формы: ' + (impact.conditions_before ? 'включены' : 'выключены') + ' → ' + (impact.conditions_after ? 'включены' : 'выключены'));
      var html = '<div class="rubrics-revision-notice ' + (blockers.length ? 'is-danger' : 'is-safe') + '">'
        + '<i class="ti ' + (blockers.length ? 'ti-alert-triangle' : 'ti-shield-check') + '"></i><div><b>' + (blockers.length ? 'Восстановление заблокировано' : 'Данные документов останутся на месте') + '</b>'
        + '<span>' + (blockers.length ? esc(blockers.join(' · ')) : 'Новые поля не удаляются: они перейдут в библиотеку конструктора. Значения документов не стираются.') + '</span></div></div>'
        + '<div class="rubrics-revision-metrics">'
        + this.schemaMetric(summary.fields_restore || 0, 'Вернётся полей', 'cyan', 'ti-arrow-back-up')
        + this.schemaMetric(summary.fields_change || 0, 'Изменится полей', 'blue', 'ti-pencil')
        + this.schemaMetric(summary.fields_preserve || 0, 'Сохранится новых', 'green', 'ti-shield-check')
        + this.schemaMetric(impact.documents_with_values || 0, 'С данными', 'amber', 'ti-file-database')
        + '</div>'
        + '<div class="rubrics-revision-condition"><i class="ti ti-adjustments-horizontal"></i><span>' + esc(conditionText) + '</span><small>Всего документов в рубрике: ' + (impact.documents_total || 0) + '</small></div>'
        + this.renderSchemaDependencies(impact.dependencies || {})
        + '<div class="rubrics-revision-changes">'
        + this.renderSchemaChangeGroup('Поля', fields)
        + this.renderSchemaChangeGroup('Группы', groups)
        + '</div>';
      return html;
    },

    schemaMetric: function (value, label, color, icon) {
      return '<div class="rubrics-revision-metric is-' + color + '"><i class="ti ' + icon + '"></i><div><b>' + value + '</b><span>' + esc(label) + '</span></div></div>';
    },

    renderSchemaDependencies: function (dependencies) {
      var summary = dependencies.summary || {};
      var warnings = dependencies.warnings || [];
      var sections = [
        { key: 'requests', label: 'Запросы', icon: 'ti-filter-code' },
        { key: 'templates', label: 'Шаблоны', icon: 'ti-template' },
        { key: 'api', label: 'API-контракты', icon: 'ti-api' },
        { key: 'modules', label: 'Модули', icon: 'ti-plug-connected' }
      ];
      var total = sections.reduce(function (count, section) {
        return count + (dependencies[section.key] || []).length;
      }, 0);
      var body = sections.map(function (section) {
        var items = dependencies[section.key] || [];
        if (!items.length) { return ''; }
        return '<details class="rubrics-revision-dependency-group">'
          + '<summary><span><i class="ti ' + section.icon + '"></i>' + esc(section.label) + '</span><b>' + items.length + '</b></summary>'
          + '<div class="rubrics-revision-dependency-list">' + items.map(function (item) {
            return '<article><div><b>' + esc(item.title || 'Зависимость') + '</b><small>' + esc(item.meta || '') + '</small></div>'
              + '<ul>' + (item.details || []).map(function (detail) { return '<li>' + esc(detail) + '</li>'; }).join('') + '</ul></article>';
          }).join('') + '</div></details>';
      }).join('');
      var warningHtml = warnings.length
        ? '<div class="rubrics-revision-dependency-warning"><i class="ti ti-alert-circle"></i><span>' + esc(warnings.join(' · ')) + '</span></div>'
        : '';

      return '<section class="rubrics-revision-dependencies">'
        + '<div class="rubrics-revision-dependencies-head"><div><h5>Зависимости схемы</h5><p>'
        + (total ? 'После восстановления проверьте перечисленные места.' : 'Прямых ссылок на изменяемые поля не найдено.')
        + '</p></div><span>' + total + '</span></div>'
        + '<div class="rubrics-revision-dependency-summary">'
        + '<span><i class="ti ti-filter-code"></i><b>' + (summary.requests || 0) + '</b> запросов</span>'
        + '<span><i class="ti ti-template"></i><b>' + (summary.templates || 0) + '</b> шаблонов</span>'
        + '<span><i class="ti ti-api"></i><b>' + (summary.api || 0) + '</b> API</span>'
        + '<span><i class="ti ti-plug-connected"></i><b>' + (summary.modules || 0) + '</b> модулей</span>'
        + '</div>' + warningHtml + body + '</section>';
    },

    renderSchemaChangeGroup: function (title, changes) {
      var parts = [
        { key: 'restore', label: 'Будут возвращены', tone: 'cyan' },
        { key: 'preserve', label: 'Останутся без удаления', tone: 'green' }
      ];
      var content = parts.map(function (part) {
        var items = changes[part.key] || [];
        if (!items.length) { return ''; }
        return '<div class="rubrics-revision-change-set is-' + part.tone + '"><b>' + part.label + '</b><div>' + items.map(function (item) {
          return '<span><code>#' + item.id + '</code><span><b>' + esc(item.title || '-') + '</b>'
            + (item.meta ? '<small>' + esc(item.meta) + '</small>' : '') + '</span></span>';
        }).join('') + '</div></div>';
      }).join('');
      var changed = (changes.change || []).map(function (item) {
        var details = item.changes || [];
        return '<article class="rubrics-revision-change-item">'
          + '<header><div><b>' + esc(item.title || '-') + '</b>'
          + (item.meta ? '<small>' + esc(item.meta) + '</small>' : '') + '</div>'
          + '<span><code>#' + item.id + '</code>' + details.length + ' изм.</span></header>'
          + '<div class="rubrics-revision-change-details">' + details.map(function (detail) {
            return '<div class="rubrics-revision-change-detail"><b>' + esc(detail.label || 'Настройка') + '</b>'
              + '<span class="is-before">' + esc(detail.before || 'Не задано') + '</span>'
              + '<i class="ti ti-arrow-right" aria-hidden="true"></i>'
              + '<span class="is-after">' + esc(detail.after || 'Не задано') + '</span></div>';
          }).join('') + '</div></article>';
      }).join('');
      var changedBlock = changed
        ? '<div class="rubrics-revision-change-list"><b class="rubrics-revision-change-list-title">Будут изменены</b>' + changed + '</div>'
        : '';
      return '<section><h5>' + esc(title) + '</h5>' + (changedBlock + content || '<p class="text-secondary">Изменений нет.</p>') + '</section>';
    },

    resetSchemaRevisionPreview: function () {
      this.currentSchemaRevisionId = 0;
      this.currentSchemaRevisionFingerprint = '';
      var preview = document.querySelector('[data-schema-revision-preview]');
      var title = document.querySelector('[data-schema-revision-title]');
      var meta = document.querySelector('[data-schema-revision-meta]');
      var remove = document.querySelector('[data-schema-revision-delete]');
      var restore = document.querySelector('[data-schema-revision-restore]');
      if (title) { title.textContent = 'Выберите ревизию'; }
      if (meta) { meta.textContent = 'Здесь появится точный список изменений до восстановления.'; }
      if (preview) { preview.innerHTML = '<div class="rubrics-revision-empty"><i class="ti ti-history"></i><b>Снимок не выбран</b><span>Выберите запись в истории выше.</span></div>'; }
      if (remove) { remove.disabled = true; }
      if (restore) { restore.disabled = true; }
    },

    deleteSchemaRevision: function (id) {
      id = parseInt(id || this.currentSchemaRevisionId, 10) || 0;
      if (!id) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить снимок схемы?',
        message: 'Эту точку восстановления нельзя будет вернуть.',
        confirmLabel: 'Удалить',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/rubrics/schema-revisions/' + id + '/delete').then(function (payload) {
            Adminx.Loader.hide();
            var response = payload.data || {};
            if (!response.success) { Adminx.Toast.show(response.message || 'Не удалось удалить снимок', 'error'); return; }
            Adminx.Toast.show(response.message || 'Снимок удалён', 'success');
            self.resetSchemaRevisionPreview();
            self.refreshSchemaRevisions();
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    clearSchemaRevisions: function () {
      if (!this.currentSchemaRevisionRubricId) { return; }
      var self = this;
      Adminx.Confirm.open({
        kind: 'danger',
        title: 'Удалить всю историю схемы?',
        message: 'Все точки восстановления этой рубрики будут удалены.',
        confirmLabel: 'Удалить все',
        onConfirm: function () {
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/rubrics/' + self.currentSchemaRevisionRubricId + '/schema-revisions/delete').then(function (payload) {
            Adminx.Loader.hide();
            var response = payload.data || {};
            if (!response.success) { Adminx.Toast.show(response.message || 'Не удалось очистить историю', 'error'); return; }
            Adminx.Toast.show(response.message || 'История очищена', 'success');
            self.resetSchemaRevisionPreview();
            self.refreshSchemaRevisions();
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    restoreSchemaRevision: function () {
      if (!this.currentSchemaRevisionId || !this.currentSchemaRevisionFingerprint) { return; }
      var self = this;
      var id = this.currentSchemaRevisionId;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Восстановить схему рубрики?',
        message: 'Текущая схема сначала сохранится отдельным снимком. Новые поля и значения документов не удаляются.',
        confirmLabel: 'Восстановить',
        onConfirm: function () {
          var data = new FormData();
          data.append('fingerprint', self.currentSchemaRevisionFingerprint);
          Adminx.Loader.show();
          Adminx.Ajax.post(self.base() + '/rubrics/schema-revisions/' + id + '/restore', data).then(function (payload) {
            Adminx.Loader.hide();
            var response = payload.data || {};
            if (!response.success) { Adminx.Toast.show(response.message || 'Не удалось восстановить схему', 'error'); return; }
            Adminx.Toast.show(response.message || 'Схема восстановлена', 'success');
            self.resetSchemaRevisionPreview();
            self.refreshSchemaRevisions();
            if (self.currentRubricId === self.currentSchemaRevisionRubricId) { self.loadFields(); }
          }).catch(function () { Adminx.Loader.hide(); Adminx.Toast.show('Ошибка сети', 'error'); });
        }
      });
    },

    fillGroupNew: function () {
      this.clearErrors(this.groupForm);
      this.groupForm.reset();
      this.groupForm.elements.id.value = '';
      this.groupForm.elements.rubric_id.value = this.currentRubricId;
	  this.groupForm.elements.group_condition.value = '{}';
	  this.hydrateBuilderConditionRoot(this.groupForm.querySelector('[data-builder-condition]'), {}, 0);
      document.getElementById('rubricGroupTitle').textContent = 'Новая группа';
      if (Adminx.Drawer) { Adminx.Drawer.open('rubricGroupDrawer'); }
    },

    fillGroupEdit: function (row) {
      if (!row) { return; }
      this.clearErrors(this.groupForm);
      this.groupForm.elements.id.value = row.getAttribute('data-id');
      this.groupForm.elements.rubric_id.value = this.currentRubricId;
      this.groupForm.elements.group_title.value = row.getAttribute('data-title') || '';
      this.groupForm.elements.group_description.value = row.getAttribute('data-description') || '';
	  var condition = {};
	  try { condition = JSON.parse(row.getAttribute('data-condition') || '{}'); } catch (e) {}
	  this.hydrateBuilderConditionRoot(this.groupForm.querySelector('[data-builder-condition]'), condition, 0);
      document.getElementById('rubricGroupTitle').textContent = 'Редактирование группы #' + row.getAttribute('data-id');
    },

    submitGroup: function (confirmed, fingerprint) {
      var id = this.groupForm.elements.id.value;
      var rubricId = this.groupForm.elements.rubric_id.value || this.currentRubricId;
      var self = this;
      this.clearErrors(this.groupForm);
	  var conditionRoot = this.groupForm.querySelector('[data-builder-condition]');
	  var condition = this.captureBuilderConditionRoot(conditionRoot);
	  if (id && !confirmed && JSON.stringify(conditionRoot._conditionOriginal || {}) !== JSON.stringify(condition)) {
		this.previewGroupCondition(id, condition);
		return;
	  }
	  this.groupForm.elements.group_condition.value = JSON.stringify(condition);
	  var data = new FormData(this.groupForm);
	  if (fingerprint) { data.append('impact_fingerprint', fingerprint); }
	  this.ajax(this.base() + (id ? '/rubrics/groups/' + id : '/rubrics/' + rubricId + '/groups'), data, function () {
        Adminx.Toast.show('Группа сохранена', 'success');
        if (Adminx.Drawer) { Adminx.Drawer.close('rubricGroupDrawer'); }
        self.loadFields();
      }, function (json) { self.showErrors(self.groupForm, json); });
    },

	previewGroupCondition: function (id, condition) {
	  var self = this;
	  var data = new FormData();
	  data.append('_csrf', this.form.elements._csrf.value);
	  data.append('group_condition', JSON.stringify(condition || {}));
	  this.ajax(this.base() + '/rubrics/groups/' + id + '/condition/preview', data, function (json) {
		var impact = (json.data || {}).impact || {};
		var persist = function () { self.submitGroup(true, impact.fingerprint || ''); };
		if (impact.requires_confirmation) {
		  self.showBuilderImpact(impact, persist);
		  return;
		}
		persist();
	  });
	},

    deleteGroup: function (row) {
      if (!row || !window.confirm('Удалить группу? Поля будут перенесены в «Без группы».')) { return; }
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      var self = this;
      this.ajax(this.base() + '/rubrics/groups/' + row.getAttribute('data-id') + '/delete', data, function () {
        Adminx.Toast.show('Группа удалена', 'success');
        self.loadFields();
      });
    },

    fillGroupSelect: function () {
      if (!this.fieldForm) { return; }
      var select = this.fieldForm.querySelector('[data-field-groups-select]');
      if (!select) { return; }
      var value = select.value;
      select.innerHTML = '<option value="0">Без группы</option>' + this.currentGroups.map(function (group) {
        return '<option value="' + group.id + '">' + esc(group.title) + '</option>';
      }).join('');
      select.value = value || '0';
    },

    dragStart: function (e) {
      var rubricHandle = e.target.closest('[data-rubric-drag-handle]');
      var groupHandle = e.target.closest('[data-group-drag-handle]');
      var fieldHandle = e.target.closest('[data-field-drag-handle]');
      var paletteField = e.target.closest('[data-builder-palette-item]');
      if (rubricHandle) {
        this.dragRubricRow = rubricHandle.closest('[data-rubric-row]');
        e.dataTransfer.effectAllowed = 'move';
      } else if (groupHandle) {
        this.dragGroupNode = groupHandle.closest('[data-group-row]');
        e.dataTransfer.effectAllowed = 'move';
      } else if (fieldHandle) {
        this.dragFieldNode = fieldHandle.closest('[data-field-row]');
        e.dataTransfer.effectAllowed = 'move';
      } else if (paletteField) {
        this.dragBuilderFieldId = parseInt(paletteField.getAttribute('data-id'), 10) || 0;
        this.dragFieldNode = null;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(this.dragBuilderFieldId));
      }
      if (this.dragFieldNode) { this.dragFieldNode.classList.add('is-dragging'); }
    },

    dragOver: function (e) {
      var row = e.target.closest('[data-rubric-row]');
      var group = e.target.closest('[data-group-row]');
      var field = e.target.closest('[data-field-row]');
      var fieldDrop = e.target.closest('[data-field-group-drop]');
      var palette = e.target.closest('[data-builder-palette]');
      if (this.dragRubricRow && row && row !== this.dragRubricRow) {
        e.preventDefault();
        row.parentNode.insertBefore(this.dragRubricRow, this.before(e, row) ? row : row.nextSibling);
      } else if (this.dragGroupNode && group && group !== this.dragGroupNode) {
        e.preventDefault();
        group.parentNode.insertBefore(this.dragGroupNode, this.before(e, group) ? group : group.nextSibling);
      } else if (this.dragFieldNode && (field || fieldDrop)) {
        e.preventDefault();
        if (field && field !== this.dragFieldNode) {
          field.parentNode.insertBefore(this.dragFieldNode, this.beforeBuilder(e, field) ? field : field.nextSibling);
        } else if (fieldDrop) {
          var list = fieldDrop.querySelector('[data-field-sortable]');
          if (list && !list.contains(this.dragFieldNode)) { list.appendChild(this.dragFieldNode); }
        }
      } else if (this.dragFieldNode && palette) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      } else if (this.dragBuilderFieldId && fieldDrop) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      }
    },

    drop: function (e) {
      if (this.dragRubricRow) {
        e.preventDefault();
        this.saveRubricOrder();
      } else if (this.dragGroupNode) {
        e.preventDefault();
        this.saveGroupOrder();
      } else if (this.dragFieldNode && e.target.closest('[data-builder-palette]')) {
        e.preventDefault();
        this.unplaceBuilderField(this.dragFieldNode);
      } else if (this.dragBuilderFieldId) {
        var fieldDrop = e.target.closest('[data-field-group-drop]');
        if (!fieldDrop) { return; }
        e.preventDefault();
        var targetField = e.target.closest('[data-field-row]');
        this.placeBuilderField(
          this.dragBuilderFieldId,
          fieldDrop.getAttribute('data-field-group-drop'),
          targetField && this.beforeBuilder(e, targetField) ? targetField : (targetField ? targetField.nextSibling : null)
        );
      } else if (this.dragFieldNode) {
        e.preventDefault();
        this.saveFieldOrder();
      }
    },

    dragEnd: function () {
      if (this.dragFieldNode) { this.dragFieldNode.classList.remove('is-dragging'); }
      this.dragRubricRow = null;
      this.dragGroupNode = null;
      this.dragFieldNode = null;
      this.dragBuilderFieldId = 0;
    },

    before: function (event, target) {
      var rect = target.getBoundingClientRect();
      return (event.clientY - rect.top) < rect.height / 2;
    },

    beforeBuilder: function (event, target) {
      if (!target.closest('[data-builder-canvas]')) { return this.before(event, target); }
      var rect = target.getBoundingClientRect();
      var y = event.clientY - rect.top;
      if (y < rect.height * 0.3) { return true; }
      if (y > rect.height * 0.7) { return false; }
      return event.clientX < rect.left + rect.width / 2;
    },

    saveRubricOrder: function () {
      var ids = Array.prototype.map.call(document.querySelectorAll('[data-rubric-row]'), function (row) { return row.getAttribute('data-id'); });
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      data.append('order', JSON.stringify(ids));
      this.ajax(this.base() + '/rubrics/reorder', data, function () { Adminx.Toast.show('Порядок рубрик сохранён', 'success'); });
    },

    saveGroupOrder: function () {
      var ids = Array.prototype.map.call(document.querySelectorAll('[data-group-row]'), function (row) { return row.getAttribute('data-id'); });
      var data = new FormData();
      data.append('_csrf', this.form.elements._csrf.value);
      data.append('order', JSON.stringify(ids));
      this.ajax(this.base() + '/rubrics/' + this.currentRubricId + '/groups/reorder', data, function () { Adminx.Toast.show('Порядок групп сохранён', 'success'); });
    },

    saveFieldOrder: function () {
      var self = this;
      document.querySelectorAll('[data-builder-canvas] [data-field-group-drop]').forEach(function (group) {
        var groupId = parseInt(group.getAttribute('data-field-group-drop'), 10) || 0;
        group.querySelectorAll('[data-field-row]').forEach(function (row) {
          row.setAttribute('data-group', String(groupId));
          var field = self.builderField(row.getAttribute('data-id'));
          if (field) { field.rubric_field_group = groupId; }
        });
      });
      this.cleanupBuilderEmptyStates();
      this.updateBuilderGroupCounts();
      if (this.dragFieldNode) { this.selectBuilderField(this.dragFieldNode.getAttribute('data-id')); }
      this.setBuilderDirty(true);
    },

    copy: function (text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { Adminx.Toast.show('Тег скопирован', 'success'); });
      }
    },

    setHint: function (selector, text, ok) {
      var el = document.querySelector(selector);
      if (!el) { return; }
      el.textContent = text || '';
      el.classList.toggle('is-ok', !!ok);
      el.classList.toggle('is-error', !!text && !ok);
    },

    clearErrors: function (form) {
      if (!form) { return; }
      Array.prototype.forEach.call(form.querySelectorAll('[data-error]'), function (el) { el.textContent = ''; });
    },

    showErrors: function (form, json) {
      Adminx.Toast.show((json && json.message) || 'Проверьте форму', 'error');
      var errors = (json && json.errors) || {};
      Object.keys(errors).forEach(function (name) {
        var el = form.querySelector('[data-error="' + name + '"]');
        if (el) { el.textContent = errors[name]; }
      });
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.rubrics-panel')) {
      Adminx.Rubrics.init();
    }
  });
})(window, document);
