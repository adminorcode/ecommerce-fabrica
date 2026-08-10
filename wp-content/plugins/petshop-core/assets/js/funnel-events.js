(() => {
  const config = window.petshopFunnelConfig || {};
  const emit = (event, ecommerce = {}) => {
    if (!event) return;
    const detail = { event, ecommerce };
    document.dispatchEvent(new CustomEvent('petshop:analytics', { detail }));
    if (config.consent) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push(detail);
    }
  };

  if (config.initialEvent === 'purchase') {
    const key = `petshop-purchase-${config.initialData?.transaction_id || ''}`;
    if (!window.sessionStorage.getItem(key)) {
      emit(config.initialEvent, config.initialData);
      window.sessionStorage.setItem(key, '1');
    }
  } else {
    emit(config.initialEvent, config.initialData);
  }

  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', (_event, _fragments, _hash, button) => {
      emit('add_to_cart', { items: [{ item_id: String(button?.data?.('product_id') || '') }] });
    });
    window.jQuery('form.variations_form').on('found_variation', (_event, variation) => {
      emit('select_item', { items: [{ item_id: variation.sku || String(variation.variation_id), item_variant: variation.variation_id }] });
    });
  }
  document.body.addEventListener('wc-blocks_added_to_cart', (event) => emit('add_to_cart', event.detail || {}));
})();
