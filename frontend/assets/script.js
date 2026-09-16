(function wcBundlesInit() {
  'use strict';

  const bundle = document.querySelector('.wc-bundles');

  if (!bundle) {
    return;
  }

  function updateSummary(item) {
    const summary = document.getElementById(item.dataset.summaryId);

    if (!summary) {
      return;
    }

    const selected = item.querySelectorAll('.wc-bundles-option-input:checked');
    const labels = Array.from(selected, function selectionLabel(input) {
      return `${input.closest('fieldset').dataset.label}: ${input.nextElementSibling.textContent}`;
    });

    const text = labels.join(' · ') || summary.dataset.placeholder;

    if (summary.textContent !== text) {
      summary.textContent = text;
    }
  }

  function onSelectionChange(event) {
    if (!event.target.matches('.wc-bundles-option-input')) {
      return;
    }

    updateSummary(event.target.closest('.wc-bundles-item'));
  }

  function syncSummaries() {
    bundle.querySelectorAll('[data-summary-id]').forEach(updateSummary);
  }

  bundle.addEventListener('change', onSelectionChange);
  window.addEventListener('pageshow', syncSummaries);
  syncSummaries();
})();
