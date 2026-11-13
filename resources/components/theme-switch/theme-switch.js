// The light/dark switch without a page load: the same cookie the server reads.
document.querySelectorAll('[data-fe="theme-switch"]').forEach(function (form) {
  form.addEventListener('click', function (event) {
    var button = event.target.closest('button[name="theme"]');
    if (!button) return;
    event.preventDefault();
    var theme = button.value;
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = 'fe_theme=' + theme + '; Path=' + (form.getAttribute('data-fe-path') || '/') + '; Max-Age=' + (365 * 86400) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    document.querySelectorAll('[data-fe="theme-switch"] button[value="' + (theme === 'dark' ? 'light' : 'dark') + '"]').forEach(function (other) {
      if (other.offsetParent !== null) other.focus();
    });
  });
});
