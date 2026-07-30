(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-command-palette]');
  if (!root) { return; }
  var input = root.querySelector('[data-command-palette-input]');
  var results = root.querySelector('[data-command-palette-results]');
  var base = root.getAttribute('data-base') || '';
  var commands = [];
  var remote = [];
  var active = 0;
  var timer = null;
  var request = 0;

  try {
    commands = JSON.parse(root.querySelector('[data-command-palette-items]').textContent || '[]');
  } catch (error) {
    commands = [];
  }

  function normalized(value) {
    return String(value || '').toLocaleLowerCase();
  }

  function localItems(query) {
    query = normalized(query);
    return commands.filter(function (item) {
      return !query || normalized(item.title + ' ' + item.subtitle).indexOf(query) !== -1;
    }).slice(0, query ? 10 : 18).map(function (item) {
      return {
        group: 'Разделы',
        title: item.title,
        subtitle: item.subtitle,
        url: item.url,
        icon: item.icon
      };
    });
  }

  function allItems() {
    return localItems(input.value).concat(remote);
  }

  function empty(message) {
    results.innerHTML = '';
    var node = document.createElement('div');
    node.className = 'command-palette-empty';
    node.innerHTML = '<i class="ti ti-search-off"></i><span></span>';
    node.querySelector('span').textContent = message;
    results.appendChild(node);
  }

  function render() {
    var items = allItems();
    if (!items.length) {
      empty(input.value.trim().length < 2 ? 'Начните вводить название' : 'Ничего не найдено');
      return;
    }
    active = Math.max(0, Math.min(active, items.length - 1));
    results.innerHTML = '';
    var lastGroup = '';
    items.forEach(function (item, index) {
      if (item.group !== lastGroup) {
        var heading = document.createElement('div');
        heading.className = 'command-palette-group';
        heading.textContent = item.group;
        results.appendChild(heading);
        lastGroup = item.group;
      }
      var button = document.createElement('button');
      button.className = 'command-palette-item' + (index === active ? ' is-active' : '');
      button.type = 'button';
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', index === active ? 'true' : 'false');
      button.dataset.index = index;
      button.innerHTML = '<span class="icon-tile" style="--tile-bg:var(--blue-50);--tile-fg:var(--blue-700)"><i class="ti"></i></span><span class="command-palette-copy"><b></b><small></small></span><i class="ti ti-arrow-right"></i>';
      var iconClass = String(item.icon || 'ti-arrow-right').trim();
      if (!/(^|\s)ti(\s|$)/.test(iconClass)) { iconClass = 'ti ' + iconClass; }
      button.querySelector('.icon-tile .ti').className = iconClass;
      button.querySelector('b').textContent = item.title || '';
      button.querySelector('small').textContent = item.subtitle || '';
      results.appendChild(button);
    });
    var selected = results.querySelector('.command-palette-item.is-active');
    if (selected) { selected.scrollIntoView({ block: 'nearest' }); }
  }

  function search() {
    var query = input.value.trim();
    remote = [];
    active = 0;
    render();
    clearTimeout(timer);
    timer = null;
    var token = ++request;
    if (query.length < 2) { return; }
    timer = setTimeout(function () {
      fetch(base + '/search?q=' + encodeURIComponent(query), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (token !== request) { return; }
          remote = json.data && json.data.items ? json.data.items.map(function (item) {
            item.url = base + item.url;
            return item;
          }) : [];
          render();
        })
        .catch(function () {});
    }, 180);
  }

  function open() {
    clearTimeout(timer);
    timer = null;
    request++;
    root.hidden = false;
    document.body.classList.add('command-palette-open');
    input.value = '';
    remote = [];
    active = 0;
    render();
    window.setTimeout(function () { input.focus(); }, 0);
  }

  function close() {
    clearTimeout(timer);
    timer = null;
    request++;
    root.hidden = true;
    document.body.classList.remove('command-palette-open');
  }

  function use(index) {
    var item = allItems()[index];
    if (item && item.url) { window.location.assign(item.url); }
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-command-palette-open]')) { open(); return; }
    if (event.target.closest('[data-command-palette-close]')) { close(); return; }
    var item = event.target.closest('.command-palette-item');
    if (item) { use(parseInt(item.dataset.index, 10) || 0); }
  });

  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      root.hidden ? open() : close();
      return;
    }
    if (root.hidden) { return; }
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key === 'ArrowDown') { event.preventDefault(); active = Math.min(allItems().length - 1, active + 1); render(); }
    if (event.key === 'ArrowUp') { event.preventDefault(); active = Math.max(0, active - 1); render(); }
    if (event.key === 'Enter') { event.preventDefault(); use(active); }
  });

  input.addEventListener('input', search);
})(window, document);
