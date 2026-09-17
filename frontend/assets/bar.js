(function wcBundlesBar() {
  'use strict';

  const bar = document.querySelector('.wc-bundles-bar');
  const purchase = document.querySelector('.wc-bundles .wc-bundles-purchase');

  if (!bar || !purchase) {
    return;
  }

  function setHeight() {
    document.documentElement.style.setProperty('--wc-bundles-bar-height', `${bar.offsetHeight}px`);
  }

  function toggle(entries) {
    const visible = !entries.at(-1).isIntersecting;
    bar.classList.toggle('is-visible', visible);
    document.body.classList.toggle('wc-bundles-bar-visible', visible);
  }

  new ResizeObserver(setHeight).observe(bar);
  new IntersectionObserver(toggle).observe(purchase);
})();
