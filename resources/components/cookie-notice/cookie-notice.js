// The cookie notice: the choice is kept in a cookie and the allowed scripts start at once, without a reload.
(function () {
  var notice = document.querySelector('[data-fe="cookie-notice"]');
  function activate(categories) {
    document.querySelectorAll('template[data-fe-consent]').forEach(function (template) {
      if (categories.indexOf(template.getAttribute('data-fe-consent')) < 0) return;
      var holder = document.createElement('div');
      holder.appendChild(template.content.cloneNode(true));
      // Scripts made by a script don't run as written: each is made again, in order (async off)
      Array.prototype.slice.call(holder.querySelectorAll('script')).forEach(function (node) {
        var script = document.createElement('script');
        Array.prototype.slice.call(node.attributes).forEach(function (a) { script.setAttribute(a.name, a.value); });
        script.async = false;
        script.text = node.text;
        node.parentNode.replaceChild(script, node);
      });
      Array.prototype.slice.call(holder.childNodes).forEach(function (node) { document.body.appendChild(node); });
      template.remove();
    });
  }
  document.querySelectorAll('[data-fe-cookie-open]').forEach(function (link) {
    if (!notice) return;
    link.addEventListener('click', function (e) {
      e.preventDefault();
      notice.hidden = false;
      var settings = notice.querySelector('details');
      if (settings) settings.open = true;
      var first = notice.querySelector('input[name="categories[]"], button');
      if (first) first.focus();
    });
  });
  if (!notice) return;
  var form = notice.querySelector('form');
  form.addEventListener('click', function (e) {
    var button = e.target.closest('button[name="choice"]');
    if (!button) return;
    e.preventDefault();
    var categories = [];
    if (button.value === 'all') {
      notice.querySelectorAll('input[name="categories[]"]').forEach(function (box) { categories.push(box.value); box.checked = true; });
    } else if (button.value === 'custom') {
      notice.querySelectorAll('input[name="categories[]"]:checked').forEach(function (box) { categories.push(box.value); });
    }
    var value = 'v' + notice.getAttribute('data-version') + ':' + categories.join(',');
    document.cookie = notice.getAttribute('data-cookie') + '=' + encodeURIComponent(value) + '; Path=' + (notice.getAttribute('data-path') || '/') + '; Max-Age=' + (parseInt(notice.getAttribute('data-days'), 10) * 86400) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    notice.hidden = true;
    activate(categories);
  });
})();
