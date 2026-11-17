// Header menus: one open at a time, Escape and a click elsewhere close them.
(function () {
  var subs = Array.prototype.slice.call(document.querySelectorAll('[data-fe-sub], [data-fe-mobile]'));
  if (subs.length === 0) return;
  subs.forEach(function (details) {
    details.addEventListener('toggle', function () {
      if (!details.open) return;
      subs.forEach(function (other) { if (other !== details && !other.contains(details)) other.open = false; });
      var summary = details.querySelector('summary');
      if (summary) summary.setAttribute('aria-expanded', 'true');
    });
  });
  subs.forEach(function (details) {
    var summary = details.querySelector('summary');
    if (summary) summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
    details.addEventListener('toggle', function () { if (summary) summary.setAttribute('aria-expanded', details.open ? 'true' : 'false'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    subs.forEach(function (details) {
      if (details.open) { details.open = false; var s = details.querySelector('summary'); if (s && details.contains(document.activeElement)) s.focus(); }
    });
  });
  document.addEventListener('click', function (e) {
    subs.forEach(function (details) { if (details.open && !details.contains(e.target)) details.open = false; });
  });
})();
