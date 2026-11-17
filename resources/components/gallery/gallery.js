// The gallery's lightbox: arrows, keyboard and swipe; without scripts the photos are plain links.
document.querySelectorAll('[data-fe="gallery"]').forEach(function (gallery) {
  var links = Array.prototype.slice.call(gallery.querySelectorAll('[data-fe-gallery-item]'));
  var box = gallery.querySelector('[data-fe-lightbox]');
  if (!box || typeof box.showModal !== 'function' || links.length === 0) return;
  var image = box.querySelector('[data-fe-lightbox-image]'), caption = box.querySelector('[data-fe-lightbox-caption]');
  var index = 0, opener = null;
  function show(i) {
    index = (i + links.length) % links.length;
    var link = links[index], img = link.querySelector('img'), cap = link.parentNode.querySelector('figcaption');
    image.src = link.href; image.alt = img ? img.alt : '';
    caption.textContent = cap ? cap.textContent : '';
  }
  links.forEach(function (link, i) {
    link.addEventListener('click', function (e) { e.preventDefault(); opener = link; show(i); box.showModal(); });
  });
  var many = links.length > 1;
  box.querySelector('[data-fe-lightbox-prev]').hidden = !many;
  box.querySelector('[data-fe-lightbox-next]').hidden = !many;
  box.querySelector('[data-fe-lightbox-prev]').addEventListener('click', function () { show(index - 1); });
  box.querySelector('[data-fe-lightbox-next]').addEventListener('click', function () { show(index + 1); });
  box.querySelector('[data-fe-lightbox-close]').addEventListener('click', function () { box.close(); });
  box.addEventListener('click', function (e) { if (e.target === box) box.close(); });
  box.addEventListener('keydown', function (e) {
    var rtl = getComputedStyle(gallery).direction === 'rtl';
    if (e.key === 'ArrowRight' && many) show(index + (rtl ? -1 : 1));
    if (e.key === 'ArrowLeft' && many) show(index + (rtl ? 1 : -1));
  });
  var startX = null;
  box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (startX === null || !many) return;
    var dx = e.changedTouches[0].clientX - startX, rtl = getComputedStyle(gallery).direction === 'rtl';
    if (Math.abs(dx) > 40) show(index + ((dx < 0) !== rtl ? 1 : -1));
    startX = null;
  });
  box.addEventListener('close', function () { if (opener) opener.focus(); });
});
