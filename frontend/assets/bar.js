(function wcBundlesBar() {
  'use strict';

  const bar = document.querySelector('.wc-bundles-bar');
  const purchase = document.querySelector('.wc-bundles .wc-bundles-purchase');
  const button = bar?.querySelector('.wc-bundles-bar-purchase');

  if (!bar || !purchase || !button) {
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

  function forwardClick() {
    purchase.click();
  }

  new ResizeObserver(setHeight).observe(bar);
  new IntersectionObserver(toggle).observe(purchase);
  button.addEventListener('click', forwardClick);
})();
