(function wcBundlesInit() {
  'use strict';

  const bundle = document.querySelector('.wc-bundles');

  if (!bundle || typeof wcBundles === 'undefined') {
    return;
  }

  const form = bundle.querySelector('.wc-bundles-cart');
  const total = bundle.querySelector('.wc-bundles-total-value');
  const retry = bundle.querySelector('.wc-bundles-retry');
  const count = bundle.querySelector('.wc-bundles-count');
  const hint = bundle.querySelector('.wc-bundles-hint[data-ready]');

  if (!form || !total || !retry || !count || !hint) {
    return;
  }

  const initialTotal = total.innerHTML;
  const pendingHint = hint.textContent;
  const elements = bundle.querySelectorAll('.wc-bundles-item');
  const items = Array.from(elements, function prepareItem(element) {
    const summary = element.querySelector('.wc-bundles-selection');
    const thumbnail = element.querySelector('.wc-bundles-thumbnail');
    const price = element.querySelector('.wc-bundles-price');
    const availability = element.querySelector('.wc-bundles-availability');

    if (!summary || !price || !availability) {
      return null;
    }

    return {
      element,
      summary,
      thumbnail,
      price,
      availability,
      name: element.querySelector('.wc-bundles-item-title').textContent,
      groups: Array.from(element.querySelectorAll('[data-attribute]')),
      variations: JSON.parse(element.dataset.variations || '[]'),
      initialThumbnail: thumbnail ? thumbnail.outerHTML : '',
      initialPrice: price.innerHTML,
      message: '',
    };
  }).filter(Boolean);
  let controller;
  let lastSelection;
  let submitted = false;

  function setHtml(element, html) {
    if (element.innerHTML !== html) {
      element.innerHTML = html;
    }
  }

  function setThumbnail(item, thumbnail) {
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

  function isSelected(item) {
    return item.groups.every(function hasChoice(group) { return group.querySelector(':checked'); });
  }

  function isComplete(item) {
    return isSelected(item) && !item.message;
  }

  /**
   * Disable options that no buyable variation offers alongside the choices in earlier groups.
   *
   * Only earlier groups constrain an option, so the first attribute always stays
   * changeable and a new choice clears the later ones it rules out.
   */
  function refreshOptions(item) {
    if (!item.variations.length) {
      return;
    }

    const chosen = [];

    for (const group of item.groups) {
      for (const input of group.querySelectorAll('.wc-bundles-option-input')) {
        input.disabled = !item.variations.some(function offers(variation) {
          return [...chosen, input.value].every(function matches(wanted, index) {
            return variation[index] === '' || wanted === '' || variation[index] === wanted;
          });
        });

        if (input.disabled) {
          input.checked = false;
        }
      }

      chosen.push(group.querySelector(':checked')?.value ?? '');
    }
  }

  function render() {
    let ready = elements.length - items.length;

    for (const item of items) {
      const complete = isComplete(item);
      const error = item.message || (submitted && !complete ? wcBundles.choose.replace('%s', item.name) : '');
      const labels = item.groups.map(function describe(group) {
        const input = group.querySelector(':checked');
        const value = input ? input.nextElementSibling.textContent : '';
        group.querySelector('.wc-bundles-attribute-value').textContent = value;
        return input
          ? `${group.dataset.label}: ${value}`
          : wcBundles.select.replace('%s', group.dataset.label.toLocaleLowerCase());
      });

      ready += complete ? 1 : 0;
      item.summary.textContent = labels.join(' · ');
      item.availability.textContent = error;
      item.element.classList.toggle('has-error', error !== '');
    }

    const done = ready === elements.length;
    count.textContent = `${ready}/${elements.length}`;
    hint.textContent = done ? hint.dataset.ready : pendingHint;
  }

  function setStatus(item, message) {
    item.message = message;
    if (message) {
      item.element.open = true;
    }
  }

  async function syncSelections(force = false) {
    const selections = {};

    for (const item of items) {
      refreshOptions(item);
      if (isSelected(item)) {
        const attributes = {};
        for (const group of item.groups) {
          attributes[group.dataset.attribute] = group.querySelector(':checked').value;
        }
        selections[item.element.dataset.productId] = attributes;
      }
    }

    const key = JSON.stringify(selections);
    if (!force && key === lastSelection) {
      render();
      return;
    }
    lastSelection = key;
    controller?.abort();
    controller = null;
    retry.hidden = true;

    for (const item of items) {
      if (!selections[item.element.dataset.productId]) {
        setThumbnail(item, item.initialThumbnail);
        setHtml(item.price, item.initialPrice);
      }
      setStatus(item, '');
    }
    render();

    if (!Object.keys(selections).length) {
      setHtml(total, initialTotal);
      return;
    }

    const body = new URLSearchParams({bundle_id: wcBundles.bundleId});
    for (const element of elements) {
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
          setHtml(item.price, selection.price_html);
          setThumbnail(item, selection.thumbnail_html);
        }
        else {
          setHtml(item.price, item.initialPrice);
          setThumbnail(item, item.initialThumbnail);
        }
        setStatus(item, selection.message);
      }
      setHtml(total, result.data.total_html);
    }
    catch (error) {
      if (controller !== request) {
        return;
      }

      total.textContent = wcBundles.error;

      retry.hidden = false;
      for (const item of items) {
        if (selections[item.element.dataset.productId]) {
          setHtml(item.price, item.initialPrice);
          setThumbnail(item, item.initialThumbnail);
          setStatus(item, wcBundles.error);
        }
      }
    }
    finally {
      window.clearTimeout(timeout);
      render();
    }
  }

  function firstUnselected() {
    return items.find(function needsChoice(item) { return !isSelected(item); });
  }

  function onSelectionChange(event) {
    if (!event.target.matches('.wc-bundles-option-input')) {
      return;
    }

    syncSelections();

    // Finishing an item moves on to the next one that still needs choices
    const item = items.find(function owns(candidate) { return candidate.element.contains(event.target); });
    if (item && isSelected(item)) {
      const next = firstUnselected();
      if (next) {
        next.element.open = true;
      }
      else {
        item.element.open = false;
      }
    }
  }

  /**
   * The button stays enabled; an incomplete bundle points at what is missing instead.
   */
  function onSubmit(event) {
    const incomplete = items.filter(function needsWork(item) { return !isComplete(item); });

    if (!incomplete.length) {
      return;
    }

    event.preventDefault();
    submitted = true;
    render();

    const item = incomplete[0];
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const group = item.groups.find(function isEmpty(candidate) { return !candidate.querySelector(':checked'); }) || item.groups[0];
    item.element.open = true;
    item.element.scrollIntoView({block: 'center', behavior: reduce ? 'auto' : 'smooth'});
    group?.querySelector('.wc-bundles-option-input:not(:disabled)')?.focus({preventScroll: true});
  }

  function retrySelection() {
    syncSelections(true);
  }

  function restoreSelections(event) {
    syncSelections(event.persisted);
  }

  // Fail open: a map that rules out a whole attribute before any choice cannot be trusted
  for (const item of items) {
    const unusable = item.groups.some(function isRuledOut(group, index) {
      return !Array.from(group.querySelectorAll('.wc-bundles-option-input')).some(function isOffered(input) {
        return item.variations.some(function offers(variation) { return variation[index] === '' || variation[index] === input.value; });
      });
    });
    if (unusable) {
      item.variations = [];
    }
  }

  // Script-side validation replaces the browser's, which cannot reach options inside a closed item
  form.noValidate = true;
  form.addEventListener('submit', onSubmit);
  bundle.addEventListener('change', onSelectionChange);
  retry.addEventListener('click', retrySelection);
  window.addEventListener('pageshow', restoreSelections);

  // pageshow can wait seconds for images; the controls should be right from the start
  syncSelections();

  const first = firstUnselected();
  if (first) {
    first.element.open = true;
  }
})();
