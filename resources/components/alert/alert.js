document.querySelectorAll('[data-fe="alert"] [data-fe-dismiss]').forEach(function (button) {
  button.addEventListener('click', function () { button.closest('[data-fe="alert"]').remove(); });
});
