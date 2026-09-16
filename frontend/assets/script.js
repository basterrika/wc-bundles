(function wcBundlesInit() {
  'use strict';

  const bundle = document.querySelector('.wc-bundles');

  if (!bundle || typeof wcBundles === 'undefined') {
    return;
  }

  const total = bundle.querySelector('.wc-bundles-total-value');
  const retry = bundle.querySelector('.wc-bundles-retry');

  if (!total || !retry) {
    return;
  }

  const totals = [total, ...document.querySelectorAll('.wc-bundles-bar-total')];
  const initialTotal = total.innerHTML;
  const items = Array.from(bundle.querySelectorAll('[data-summary-id]'), function prepareItem(element) {
    const summary = document.getElementById(element.dataset.summaryId);
    const image = element.querySelector('.wc-bundles-image');
    const thumbnail = summary?.closest('.wc-bundles-summary-item')?.querySelector('.wc-bundles-thumbnail');
    const price = element.querySelector('.wc-bundles-price');
    const availability = element.querySelector('.wc-bundles-availability');

    if (!summary || !image || !price || !availability) {
      return null;
    }

    return {
      element,
      summary,
      image,
      thumbnail,
      price,
      availability,
      groups: Array.from(element.querySelectorAll('[data-attribute]')),
      initialImage: image.innerHTML,
      initialThumbnail: thumbnail ? thumbnail.outerHTML : '',
      initialPrice: price.innerHTML,
      label: '',
      message: '',
    };
  }).filter(Boolean);
  let controller;
  let lastSelection;

  function setHtml(element, html) {
    if (element.innerHTML !== html) {
      element.innerHTML = html;
    }
  }

  function setTotal(html) {
    for (const element of totals) {
      setHtml(element, html);
    }
  }

  function setStatus(item, message) {
    item.message = message;
    item.availability.textContent = message;
    item.summary.textContent = item.label || item.summary.dataset.placeholder;
  }

  function setImages(item, image, thumbnail) {
    setHtml(item.image, image);
    if (!item.thumbnail || item.thumbnail.outerHTML === thumbnail) {
      return;
    }
    const template = document.createElement('template');
    template.innerHTML = thumbnail;
    const next = template.content.querySelector('.wc-bundles-thumbnail');
    if (next) {
      item.thumbnail.replaceWith(next);
      item.thumbnail = next;
    }
  }

  async function syncSelections(force = false) {
    const selections = {};

    for (const item of items) {
      const attributes = {};
      const labels = [];
      for (const group of item.groups) {
        const input = group.querySelector(':checked');
        if (input) {
          attributes[group.dataset.attribute] = input.value;
          labels.push(`${group.dataset.label}: ${input.nextElementSibling.textContent}`);
        }
      }
      item.label = labels.join(' · ');
      setStatus(item, item.message);
      if (item.groups.length && labels.length === item.groups.length) {
        selections[item.element.dataset.productId] = attributes;
      }
    }

    const key = JSON.stringify(selections);
    if (!force && key === lastSelection) {
      return;
    }
    lastSelection = key;
    controller?.abort();
    controller = null;
    retry.hidden = true;

    for (const item of items) {
      delete item.element.dataset.variationId;
      if (!selections[item.element.dataset.productId]) {
        setImages(item, item.initialImage, item.initialThumbnail);
        setHtml(item.price, item.initialPrice);
      }
      setStatus(item, '');
    }

    if (!Object.keys(selections).length) {
      setTotal(initialTotal);
      return;
    }

    const body = new URLSearchParams({bundle_id: wcBundles.bundleId});
    for (const element of bundle.querySelectorAll('[data-product-id]')) {
      body.append('items[]', element.dataset.productId);
    }
    for (const [id, attributes] of Object.entries(selections)) {
      for (const [name, value] of Object.entries(attributes)) {
        body.set(`selections[${id}][${name}]`, value);
      }
    }

    const request = new AbortController();
    controller = request;
    const timeout = window.setTimeout(function cancelSlowRequest() { request.abort(); }, 15000);

    try {
      const response = await fetch(wcBundles.url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        signal: request.signal,
      });
      if (!response.ok) {
        throw new Error('Selection request failed');
      }
      const result = await response.json();
      if (!result.success) {
        throw new Error('Selection unavailable');
      }
      if (controller !== request) {
        return;
      }

      for (const item of items) {
        const id = item.element.dataset.productId;
        if (!selections[id]) {
          continue;
        }
        const selection = result.data.items[id] || {variation_id: 0, message: wcBundles.unavailable};
        if (selection.variation_id) {
          item.element.dataset.variationId = selection.variation_id;
          setHtml(item.price, selection.price_html);
          setImages(item, selection.image_html, selection.thumbnail_html);
        }
        else {
          setHtml(item.price, item.initialPrice);
          setImages(item, item.initialImage, item.initialThumbnail);
        }
        setStatus(item, selection.message);
      }
      setTotal(result.data.total_html);
    }
    catch (error) {
      if (controller !== request) {
        return;
      }

      for (const element of totals) {
        element.textContent = wcBundles.error;
      }

      retry.hidden = false;
      for (const item of items) {
        if (selections[item.element.dataset.productId]) {
          delete item.element.dataset.variationId;
          setHtml(item.price, item.initialPrice);
          setImages(item, item.initialImage, item.initialThumbnail);
          setStatus(item, wcBundles.error);
        }
      }
    }
    finally {
      window.clearTimeout(timeout);
    }
  }

  function onSelectionChange(event) {
    if (event.target.matches('.wc-bundles-option-input')) {
      syncSelections();
    }
  }

  function retrySelection() {
    syncSelections(true);
  }

  function restoreSelections(event) {
    syncSelections(event.persisted);
  }

  bundle.addEventListener('change', onSelectionChange);
  retry.addEventListener('click', retrySelection);
  window.addEventListener('pageshow', restoreSelections);
  syncSelections();
})();
