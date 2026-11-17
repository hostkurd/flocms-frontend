// The slider: one slide at a time, arrows, keyboard, swipe, autoplay with pause.
document.querySelectorAll('[data-fe="slider"]').forEach(function (slider) {
  var slides = Array.prototype.slice.call(slider.querySelectorAll('.fe-slider__slide'));
  if (slides.length < 2) return;
  var index = 0, timer = null, paused = false;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var current = slider.querySelector('[data-fe-current]');
  var bar = slider.querySelector('.fe-slider__bar span');
  var digits = current ? current.textContent.trim() : '1';
  var arabic = /[٠-٩]/.test(digits);
  function number(n) { var s = String(n); return arabic ? s.replace(/\d/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'[d]; }) : s; }
  function show(next, focus) {
    next = (next + slides.length) % slides.length;
    if (next === index) return;
    slides[index].classList.remove('is-current');
    slides[next].classList.add('is-current');
    if (!reduce) { slides[next].classList.add('is-entering'); setTimeout(function () { slides[next].classList.remove('is-entering'); }, 600); }
    index = next;
    if (current) current.textContent = number(index + 1);
    if (bar) bar.style.insetInlineStart = (100 / slides.length * index) + '%';
    if (focus) slides[index].focus({ preventScroll: true });
  }
  slides.forEach(function (slide) { slide.setAttribute('tabindex', '-1'); });
  var prev = slider.querySelector('[data-fe-prev]'), next = slider.querySelector('[data-fe-next]');
  if (prev) prev.addEventListener('click', function (e) { e.preventDefault(); user(); show(index - 1); });
  if (next) next.addEventListener('click', function (e) { e.preventDefault(); user(); show(index + 1); });
  slider.addEventListener('keydown', function (e) {
    var rtl = getComputedStyle(slider).direction === 'rtl';
    if (e.key === 'ArrowRight') { user(); show(index + (rtl ? -1 : 1)); }
    if (e.key === 'ArrowLeft') { user(); show(index + (rtl ? 1 : -1)); }
  });
  var startX = null;
  slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  slider.addEventListener('touchend', function (e) {
    if (startX === null) return;
    var dx = e.changedTouches[0].clientX - startX, rtl = getComputedStyle(slider).direction === 'rtl';
    if (Math.abs(dx) > 40) { user(); show(index + ((dx < 0) !== rtl ? 1 : -1)); }
    startX = null;
  });
  var seconds = parseInt(slider.getAttribute('data-autoplay') || '0', 10);
  var pause = slider.querySelector('[data-fe-pause]');
  var used = false;
  function start() { if (seconds > 0 && !paused && !reduce && !used) { clearInterval(timer); timer = setInterval(function () { show(index + 1); }, seconds * 1000); } }
  function stop() { clearInterval(timer); timer = null; }
  function user() { used = true; stop(); }
  if (pause) pause.addEventListener('click', function () {
    paused = !paused;
    used = false;
    if (paused) { stop(); } else { start(); }
    pause.setAttribute('aria-label', pause.getAttribute(paused ? 'data-label-play' : 'data-label-pause'));
    pause.querySelector('[data-icon-pause]').hidden = paused;
    pause.querySelector('[data-icon-play]').hidden = !paused;
  });
  slider.addEventListener('focusin', stop);
  slider.addEventListener('mouseenter', stop);
  slider.addEventListener('mouseleave', start);
  start();
});
