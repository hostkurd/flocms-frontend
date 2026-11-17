// Tabs: the <details> become a tab list with arrow keys (WAI-ARIA tabs).
document.querySelectorAll('[data-fe="tabs"]').forEach(function (tabs) {
  var panels = Array.prototype.slice.call(tabs.querySelectorAll(':scope > .fe-tabs__panel'));
  if (panels.length < 2) return;
  var list = document.createElement('div');
  list.className = 'fe-tabs__list';
  list.setAttribute('role', 'tablist');
  if (tabs.getAttribute('aria-label')) list.setAttribute('aria-label', tabs.getAttribute('aria-label'));
  var buttons = panels.map(function (panel, i) {
    var id = panel.getAttribute('data-fe-tab');
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'fe-tabs__tab';
    button.id = id + '-tab';
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-controls', id);
    button.textContent = panel.querySelector('summary').textContent;
    var content = panel.querySelector('.fe-tabs__content');
    content.id = id;
    content.setAttribute('role', 'tabpanel');
    content.setAttribute('aria-labelledby', button.id);
    content.setAttribute('tabindex', '0');
    button.addEventListener('click', function () { select(i); });
    list.appendChild(button);
    return button;
  });
  function select(index, focus) {
    panels.forEach(function (panel, i) {
      var on = i === index;
      panel.open = on;
      panel.hidden = !on;
      buttons[i].setAttribute('aria-selected', on ? 'true' : 'false');
      buttons[i].tabIndex = on ? 0 : -1;
    });
    if (focus) buttons[index].focus();
  }
  list.addEventListener('keydown', function (e) {
    var i = buttons.indexOf(document.activeElement), rtl = getComputedStyle(tabs).direction === 'rtl';
    if (i < 0) return;
    var step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[e.key];
    if (e.key === 'Home') { e.preventDefault(); select(0, true); }
    else if (e.key === 'End') { e.preventDefault(); select(buttons.length - 1, true); }
    else if (step) { e.preventDefault(); select((i + step + buttons.length) % buttons.length, true); }
  });
  tabs.insertBefore(list, panels[0]);
  tabs.classList.add('is-ready');
  var start = panels.findIndex(function (p) { return p.open; });
  select(start < 0 ? 0 : start);
});
