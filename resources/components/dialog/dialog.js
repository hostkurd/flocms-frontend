// Dialogs: <dialog> with showModal(); Escape and the backdrop close it, focus returns to the button.
document.querySelectorAll('[data-fe-dialog-open]').forEach(function (opener) {
  var dialog = document.getElementById(opener.getAttribute('data-fe-dialog-open'));
  if (!dialog || typeof dialog.showModal !== 'function') return;
  opener.addEventListener('click', function (e) { e.preventDefault(); dialog.showModal(); });
  dialog.addEventListener('click', function (e) {
    if (e.target === dialog || e.target.closest('[data-fe-dialog-close]')) { e.preventDefault(); dialog.close(); }
  });
  dialog.addEventListener('close', function () { opener.focus(); });
});
