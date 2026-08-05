/**
 * JS раздела «База данных»: вкладки, обслуживание таблиц (OPTIMIZE/REPAIR),
 * создание, проверка, восстановление и удаление резервных копий. Ajax по контракту success/error,
 * подтверждения — Adminx.Confirm.
 */
(function (window, document) {
  'use strict';

  var Adminx = window.Adminx || (window.Adminx = {});

  Adminx.Database = {
    init: function () {
      if (!document.querySelector('[data-db-panel]')) { return; }
      var self = this;
      if (new URLSearchParams(window.location.search).get('tab') === 'backups') { self.switchTab('backups'); }

      document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-db-tab]');
        if (tab) { self.switchTab(tab.getAttribute('data-db-tab')); return; }

        var opBtn = e.target.closest('[data-db-op]');
        if (opBtn) { self.maintain(opBtn.getAttribute('data-db-op'), opBtn.closest('tr').dataset.table, opBtn); return; }

        if (e.target.closest('[data-db-optimize-all]')) { self.optimizeAll(); return; }
        if (e.target.closest('[data-db-schema-update]')) { self.updateSchema(); return; }
        var backup = e.target.closest('[data-db-backup]');
        if (backup) { self.backup(); return; }
        if (e.target.closest('[data-db-backup-upload]')) {
          var fileInput = document.querySelector('[data-db-backup-file]');
          if (fileInput) { fileInput.click(); }
          return;
        }

        if (e.target.closest('[data-db-convert-innodb]')) { self.convertToInnoDb(); return; }

        var del = e.target.closest('[data-db-backup-delete]');
        if (del) { self.deleteBackup(del.closest('tr')); return; }

        var restore = e.target.closest('[data-db-backup-restore]');
        if (restore) { self.inspectBackup(restore.closest('tr'), restore); return; }

        if (e.target.closest('[data-db-restore-close]')) { self.closeRestoreProgress(); return; }
      });

      var keepForm = document.querySelector('[data-db-backup-keep-form]');
      if (keepForm) {
        keepForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.saveBackupKeep(keepForm);
        });
      }

      document.addEventListener('input', function (e) {
        var filter = e.target.closest('[data-db-table-filter]');
        if (filter) { self.filterTables(filter.value); }
      });

      document.addEventListener('change', function (e) {
        var input = e.target.closest('[data-db-backup-file]');
        if (input && input.files && input.files[0]) { self.uploadBackup(input); }
      });
    },

    filterTables: function (query) {
      query = (query || '').trim().toLowerCase();
      var rows = document.querySelectorAll('[data-db-panel="tables"] tbody tr[data-table]');
      var shown = 0;
      rows.forEach(function (row) {
        var match = query === '' || (row.getAttribute('data-table') || '').toLowerCase().indexOf(query) !== -1;
        row.hidden = !match;
        if (match) { shown++; }
      });
      var label = document.querySelector('[data-db-table-visible]');
      if (label) { label.textContent = shown + ' из ' + rows.length; }
    },

    base: function () { return Adminx.base(); },

    switchTab: function (name) {
      document.querySelectorAll('[data-db-tab]').forEach(function (t) {
        t.setAttribute('aria-selected', t.getAttribute('data-db-tab') === name ? 'true' : 'false');
      });
      document.querySelectorAll('[data-db-panel]').forEach(function (p) {
        p.hidden = p.getAttribute('data-db-panel') !== name;
      });
    },

    post: function (url, fd) {
      Adminx.Loader.show();
      return Adminx.Ajax.post(url, fd).then(function (payload) {
        Adminx.Loader.hide();
        return payload.data || {};
      }).catch(function () {
        Adminx.Loader.hide();
        Adminx.Toast.show('Ошибка сети', 'error');
        return { success: false };
      });
    },

    maintain: function (op, table, btn) {
      var fd = new FormData();
      fd.append('op', op);
      fd.append('table', table);
      if (btn) { btn.disabled = true; }
      this.post(this.base() + '/database/maintenance', fd).then(function (d) {
        if (btn) { btn.disabled = false; }
        Adminx.Toast.show(d.message || (d.success ? 'Готово' : 'Ошибка'), d.success ? 'success' : 'error');
      });
    },

    optimizeAll: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Оптимизировать все таблицы?',
        message: 'Будет выполнен OPTIMIZE для всех таблиц текущего префикса.',
        confirmLabel: 'Оптимизировать',
        onConfirm: function () {
          var fd = new FormData();
          fd.append('op', 'optimize');
          fd.append('table', '*');
          self.post(self.base() + '/database/maintenance', fd).then(function (d) {
            Adminx.Toast.show(d.message || 'Готово', d.success ? 'success' : 'error');
          });
        }
      });
    },

    updateSchema: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Применить миграции ядра?',
        message: 'Будут выполнены ещё не применённые миграции встроенных разделов и синхронизированы права. Данные из резервных копий эта операция не читает.',
        confirmLabel: 'Применить',
        onConfirm: function () {
          self.post(self.base() + '/database/schema/update', new FormData()).then(function (d) {
            Adminx.Toast.show(d.message || (d.success ? 'Готово' : 'Ошибка'), d.success ? 'success' : 'error');
            if (d.success) { window.setTimeout(function () { window.location.reload(); }, 500); }
          });
        }
      });
    },

    inspectBackup: function (row, button) {
      var self = this;
      var name = row.dataset.backup;
      var fd = new FormData();
      fd.append('file', name);
      button.disabled = true;
      this.post(this.base() + '/database/backup/inspect', fd).then(function (d) {
        button.disabled = false;
        if (!d.success) {
          Adminx.Toast.show(d.message || 'Не удалось проверить резервную копию', 'error');
          return;
        }

        var info = d.data || {};
        var message = 'Файл: ' + name + '. Таблиц: ' + (info.table_count || 0) +
          ', записей: ' + (info.inserts || 0) + ', дата копии: ' + (info.date || 'не указана') + '.';
        if (info.warnings && info.warnings.length) { message += ' Внимание: ' + info.warnings.join(' '); }
        message += ' Текущие данные будут заменены, но перед этим автоматически создастся страховочный бэкап.';

        Adminx.Confirm.open({
          kind: 'error',
          title: 'Восстановить базу из копии?',
          message: message,
          confirmLabel: 'Восстановить',
          confirmClass: 'btn-danger',
          onConfirm: function () { self.restoreBackup(name, info.token); }
        });
      });
    },

    restoreBackup: function (name, token) {
      var self = this;
      var fd = new FormData();
      fd.append('file', name);
      fd.append('restore_token', token || '');
      this.post(this.base() + '/database/backup/restore/start', fd).then(function (d) {
        if (!d.success || !d.data || !d.data.job || !d.data.run_token) {
          Adminx.Toast.show(d.message || 'Не удалось подготовить восстановление', 'error');
          return;
        }

        self.openRestoreProgress(d.data.job);
        self.runRestoreStream(d.data.job.id, d.data.run_token);
      });
    },

    openRestoreProgress: function (job) {
      var overlay = document.querySelector('[data-db-restore-progress]');
      if (!overlay) { return; }
      overlay.hidden = false;
      document.body.classList.add('database-restore-active');
      var close = overlay.querySelector('[data-db-restore-close]');
      var error = overlay.querySelector('[data-db-restore-error]');
      if (close) { close.hidden = true; }
      if (error) { error.hidden = true; }
      this.updateRestoreProgress(job || {});
    },

    closeRestoreProgress: function () {
      var overlay = document.querySelector('[data-db-restore-progress]');
      if (overlay) { overlay.hidden = true; }
      document.body.classList.remove('database-restore-active');
    },

    updateRestoreProgress: function (job) {
      var root = document.querySelector('[data-db-restore-progress]');
      if (!root || !job) { return; }
      var percent = Math.max(0, Math.min(100, parseInt(job.progress, 10) || 0));
      var title = root.querySelector('[data-db-restore-title]');
      var detail = root.querySelector('[data-db-restore-detail]');
      var counter = root.querySelector('[data-db-restore-counter]');
      var percentLabel = root.querySelector('[data-db-restore-percent]');
      var track = root.querySelector('[data-db-restore-track]');
      var bar = root.querySelector('[data-db-restore-bar]');
      if (title) { title.textContent = job.title || 'Восстановление базы'; }
      if (detail) { detail.textContent = job.detail || job.table || 'Выполняется операция'; }
      if (percentLabel) { percentLabel.textContent = percent + '%'; }
      if (track) { track.setAttribute('aria-valuenow', String(percent)); }
      if (bar) { bar.style.width = percent + '%'; }
      if (counter) {
        counter.textContent = job.total && job.current
          ? 'Таблица ' + job.current + ' из ' + job.total
          : (job.table ? job.table : 'Состояние сохраняется автоматически');
      }

      var order = ['verify', 'backup', 'clean', 'restore', 'cache'];
      var active = order.indexOf(job.stage);
      root.querySelectorAll('[data-restore-stage]').forEach(function (step) {
        var index = order.indexOf(step.getAttribute('data-restore-stage'));
        step.classList.toggle('is-active', index === active);
        step.classList.toggle('is-complete', active > index || job.status === 'completed');
      });

      root.classList.toggle('is-complete', job.status === 'completed');
      root.classList.toggle('is-error', job.status === 'failed');
      if (job.status === 'failed') { this.showRestoreError(job.error || job.detail || 'Восстановление остановлено'); }
    },

    showRestoreError: function (message) {
      var root = document.querySelector('[data-db-restore-progress]');
      if (!root) { return; }
      root.classList.add('is-error');
      var error = root.querySelector('[data-db-restore-error]');
      var close = root.querySelector('[data-db-restore-close]');
      if (error) { error.hidden = false; error.querySelector('span').textContent = message; }
      if (close) { close.hidden = false; }
    },

    finishRestore: function (payload) {
      var root = document.querySelector('[data-db-restore-progress]');
      if (root) {
        root.classList.add('is-complete');
        var close = root.querySelector('[data-db-restore-close]');
        if (close) { close.hidden = false; }
      }
      Adminx.Toast.show(payload.message || 'База восстановлена', 'success');
      window.setTimeout(function () {
        window.location.href = payload.redirect || Adminx.Database.base() + '/database?tab=backups';
      }, 900);
    },

    runRestoreStream: function (jobId, runToken) {
      var self = this;
      var terminal = false;
      var fd = new FormData();
      fd.append('job_id', jobId);
      fd.append('run_token', runToken);
      fd.append('_csrf', Adminx.csrf());
      fetch(this.base() + '/database/backup/restore/run', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/x-ndjson',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': Adminx.csrf()
        },
        body: fd
      }).then(function (response) {
        if (!response.ok || !response.body || !response.body.getReader) {
          throw new Error('Сервер не открыл поток восстановления');
        }

        var reader = response.body.getReader();
        var decoder = new TextDecoder('utf-8');
        var buffer = '';
        var consume = function () {
          return reader.read().then(function (chunk) {
            buffer += decoder.decode(chunk.value || new Uint8Array(), { stream: !chunk.done });
            var lines = buffer.split('\n');
            buffer = lines.pop();
            lines.forEach(function (line) {
              var type = self.handleRestoreEvent(line);
              if (type === 'complete' || type === 'error') { terminal = true; }
            });
            if (!chunk.done) { return consume(); }
            if (buffer.trim()) {
              var type = self.handleRestoreEvent(buffer);
              if (type === 'complete' || type === 'error') { terminal = true; }
            }
            if (!terminal) { self.pollRestoreStatus(jobId, 0); }
          });
        };
        return consume();
      }).catch(function () {
        self.pollRestoreStatus(jobId, 0);
      });
    },

    handleRestoreEvent: function (line) {
      if (!String(line || '').trim()) { return ''; }
      var payload;
      try { payload = JSON.parse(line); } catch (e) { return ''; }
      if (payload.type === 'progress' && payload.job) { this.updateRestoreProgress(payload.job); }
      if (payload.type === 'complete') { this.finishRestore(payload); }
      if (payload.type === 'error') { this.showRestoreError(payload.message || 'Восстановление остановлено'); }
      return payload.type || '';
    },

    pollRestoreStatus: function (jobId, attempt) {
      var self = this;
      var fd = new FormData();
      fd.append('job_id', jobId);
      Adminx.Ajax.post(this.base() + '/database/backup/restore/status', fd).then(function (payload) {
        var response = payload.data || {};
        var job = response.data ? response.data.job : null;
        if (!response.success || !job) {
          self.showRestoreError('Соединение потеряно. Проверьте список резервных копий после обновления страницы.');
          return;
        }

        self.updateRestoreProgress(job);
        if (job.status === 'completed') {
          self.finishRestore({ message: 'База восстановлена', result: job.result });
        } else if (job.status === 'failed') {
          self.showRestoreError(job.error || 'Восстановление остановлено');
        } else if (attempt < 180) {
          window.setTimeout(function () { self.pollRestoreStatus(jobId, attempt + 1); }, 2000);
        } else {
          self.showRestoreError('Восстановление выполняется дольше ожидаемого. Обновите страницу и проверьте состояние базы.');
        }
      }).catch(function () {
        if (attempt < 10) { window.setTimeout(function () { self.pollRestoreStatus(jobId, attempt + 1); }, 2000); }
        else { self.showRestoreError('Не удалось получить состояние восстановления.'); }
      });
    },

    saveBackupKeep: function (form) {
      if (!form.reportValidity()) { return; }
      this.post(this.base() + '/database/backup/keep', new FormData(form)).then(function (d) {
        if (d.success === false) { Adminx.Toast.show(d.message || 'Ошибка', 'error'); return; }
        Adminx.Toast.show(d.message || 'Сохранено', 'success');
        if (d.redirect) { window.location.href = d.redirect; } else { window.location.reload(); }
      });
    },

    backup: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'info',
        title: 'Создать резервную копию?',
        message: 'Будет создан gzip-дамп единой рабочей схемы.',
        confirmLabel: 'Создать',
        onConfirm: function () {
          var fd = new FormData();
          self.post(self.base() + '/database/backup', fd).then(function (d) {
            if (d.success) {
              Adminx.Toast.show(d.message, 'success');
              if (d.redirect) { window.location.href = d.redirect; } else { window.location.reload(); }
            } else {
              Adminx.Toast.show(d.message || 'Ошибка', 'error');
            }
          });
        }
      });
    },

    uploadBackup: function (input) {
      var self = this;
      var fd = new FormData();
      fd.append('backup', input.files[0]);
      this.post(this.base() + '/database/backup/upload', fd).then(function (d) {
        input.value = '';
        Adminx.Toast.show(d.message || (d.success ? 'Копия загружена' : 'Ошибка загрузки'), d.success ? 'success' : 'error');
        if (d.success) { window.location.href = d.redirect || self.base() + '/database'; }
      });
    },

    convertToInnoDb: function () {
      var self = this;
      Adminx.Confirm.open({
        kind: 'warning',
        title: 'Перевести MyISAM в InnoDB?',
        message: 'Сначала создайте полный бэкап. ALTER TABLE может временно заблокировать запись, поэтому на production выполняйте операцию в окно обслуживания.',
        confirmLabel: 'Перевести',
        onConfirm: function () {
          self.post(self.base() + '/database/engines/innodb', new FormData()).then(function (d) {
            Adminx.Toast.show(d.message || (d.success ? 'Готово' : 'Ошибка'), d.success ? 'success' : 'error');
            if (d.success) { window.location.reload(); }
          });
        }
      });
    },

    deleteBackup: function (row) {
      var self = this;
      var name = row.dataset.backup;
      Adminx.Confirm.open({
        kind: 'error',
        title: 'Удалить резервную копию?',
        message: name + ' будет удалён без возможности восстановления.',
        confirmLabel: 'Удалить',
        confirmClass: 'btn-danger',
        onConfirm: function () {
          var fd = new FormData();
          fd.append('file', name);
          self.post(self.base() + '/database/backup/delete', fd).then(function (d) {
            if (d.success) { row.remove(); Adminx.Toast.show(d.message, 'success'); }
            else { Adminx.Toast.show(d.message || 'Ошибка', 'error'); }
          });
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Adminx.Database.init(); });
  } else {
    Adminx.Database.init();
  }
})(window, document);
