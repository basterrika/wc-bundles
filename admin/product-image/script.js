document.getElementById('postimagediv')?.addEventListener('click', async(event) => {
  const link = event.target.closest('.wc-bundles-generate-image');

  if (!link) {
    return;
  }

  event.preventDefault();

  if (link.style.opacity) {
    return;
  }

  link.style.opacity = '0.5';

  const body = new URLSearchParams({
    action: 'wc_bundles_generate_image',
    nonce: link.dataset.nonce,
    post_id: document.getElementById('post_ID').value
  });

  // Paid before free, as the bundle lists them
  for (const option of document.querySelectorAll('#wc_bundles_item_ids option:checked, #wc_bundles_free_item_ids option:checked')) {
    body.append('item_ids[]', option.value);
  }

  try {
    const response = await fetch(window.ajaxurl, {
      method: 'POST',
      body
    });
    const result = await response.json().catch(() => null);

    if (!result?.success) {
      throw new Error(result?.data || response.statusText);
    }

    window.WPSetThumbnailHTML(result.data);
  }
  catch (error) {
    window.alert(error.message);
    link.style.opacity = '';
  }
});
