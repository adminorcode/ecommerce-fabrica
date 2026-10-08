(() => {
  const config = window.petshopProductConfig;
  if (!config) return;
  const form = document.querySelector('form.variations_form');
  const lead = document.querySelector('[data-petshop-production-lead]');
  const leadRow = document.querySelector('[data-petshop-production-row]');
  const defaultLead = lead?.textContent || '';
  const shippingForm = document.querySelector('[data-petshop-shipping-form]');
  const result = document.querySelector('[data-petshop-shipping-result]');
  const quantityTotal = document.querySelector('[data-petshop-quantity-total]');
  const quantityTotalLabel = document.querySelector('[data-petshop-quantity-total-label]');
  const quantityTotalValue = document.querySelector('[data-petshop-quantity-total-value]');
  const cartForm = document.querySelector('form.cart');
  const formatPostcode = (postcode) => postcode.replace(/^(\d{5})(\d{3})$/, '$1-$2');
  const numberFormat = (config.priceFormat && typeof config.priceFormat === 'object') ? config.priceFormat : {};
  let currentUnitPrice = Number.parseFloat(quantityTotal?.dataset.unitPrice || '');

  const decimalSeparator = numberFormat.decimalSeparator || '.';
  const thousandSeparator = numberFormat.thousandSeparator || ',';
  const decimals = Number.isInteger(numberFormat.decimals) ? numberFormat.decimals : 2;
  const priceFormat = numberFormat.priceFormat || '%1$s%2$s';
  const currencySymbol = numberFormat.currencySymbol || '';

  const formatAmount = (value) => {
    const fixed = value.toFixed(Math.max(0, decimals));
    const [integer, decimal = ''] = fixed.split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
    const amount = decimals > 0 ? `${grouped}${decimalSeparator}${decimal}` : grouped;
    return priceFormat.replace('%1$s', currencySymbol).replace('%2$s', amount);
  };

  const quantityInput = () => cartForm?.querySelector('input.qty');

  const currentQuantity = () => {
    const input = quantityInput();
    if (!input) return 1;
    const value = Number.parseFloat(input.value || '');
    const min = Number.parseFloat(input.min || '');
    const max = Number.parseFloat(input.max || '');
    if (!Number.isFinite(value) || value <= 0) return Number.isFinite(min) && min > 0 ? min : 1;
    if (Number.isFinite(max) && max > 0 && value > max) return max;
    if (Number.isFinite(min) && min > 0 && value < min) return min;
    return value;
  };

  const summaryPrice = () => document.querySelector(
    '.entry-summary > .price, .summary > .price, .entry-summary > .petshop-product-price-row > .price, .summary > .petshop-product-price-row > .price'
  );

  const variationPrice = () => document.querySelector('.woocommerce-variation-price .price');

  const restorePriceRow = (row) => {
    const price = row.querySelector(':scope > .price');
    if (price) row.insertAdjacentElement('beforebegin', price);
    if (quantityTotal?.parentElement === row) row.insertAdjacentElement('beforebegin', quantityTotal);
    row.remove();
  };

  const placeQuantityTotal = (price = summaryPrice()) => {
    if (!quantityTotal || !price) return;
    const currentRow = quantityTotal.parentElement?.classList.contains('petshop-product-price-row')
      ? quantityTotal.parentElement
      : null;
    if (currentRow && price.parentElement === currentRow) return;
    if (currentRow) restorePriceRow(currentRow);
    const row = document.createElement('div');
    row.className = 'petshop-product-price-row';
    price.insertAdjacentElement('beforebegin', row);
    row.append(price, quantityTotal);
  };

  const detachFromSummaryPrice = () => {
    const row = quantityTotal?.closest('.petshop-product-price-row');
    if (!row || row.closest('.woocommerce-variation-price')) return;
    restorePriceRow(row);
    quantityTotal?.remove();
  };

  const updateQuantityTotal = () => {
    if (!quantityTotal || !quantityTotalValue) return;
    const quantity = currentQuantity();
    if (!Number.isFinite(currentUnitPrice) || currentUnitPrice < 0 || quantity <= 1) {
      quantityTotal.hidden = true;
      if (quantityTotalLabel) quantityTotalLabel.textContent = '';
      quantityTotalValue.textContent = '';
      return;
    }
    quantityTotal.hidden = false;
    if (quantityTotalLabel) quantityTotalLabel.textContent = `x${Number.isInteger(quantity) ? quantity : quantity}`;
    quantityTotalValue.textContent = formatAmount(currentUnitPrice * quantity);
  };

  cartForm?.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('input.qty')) {
      updateQuantityTotal();
    }
  });

  cartForm?.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('input.qty')) {
      updateQuantityTotal();
    }
  });

  if (form && window.jQuery) {
    const colorSelect = form.querySelector('select[name="attribute_pa_color"]');
    if (colorSelect) {
      const swatches = document.createElement('div');
      swatches.className = 'petshop-color-swatches';
      swatches.setAttribute('role', 'group');
      swatches.setAttribute('aria-label', colorSelect.closest('tr')?.querySelector('label')?.textContent?.trim() || 'Cor');
      [...colorSelect.options].filter((option) => option.value).forEach((option) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'petshop-color-swatch';
        button.dataset.value = option.value;
        button.setAttribute('aria-pressed', 'false');
        const sample = document.createElement('span');
        sample.className = 'petshop-color-swatch__sample';
        sample.setAttribute('aria-hidden', 'true');
        sample.style.backgroundColor = option.dataset.swatchColor || 'transparent';
        const name = document.createElement('span');
        name.textContent = option.textContent.trim();
        button.append(sample, name);
        button.addEventListener('click', () => {
          colorSelect.value = option.value;
          colorSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
        swatches.append(button);
      });
      const syncSwatches = () => swatches.querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', button.dataset.value === colorSelect.value ? 'true' : 'false'));
      colorSelect.addEventListener('change', syncSwatches);
      colorSelect.insertAdjacentElement('afterend', swatches);
      syncSwatches();
    }
    window.jQuery(form).on('found_variation', (_event, variation) => {
      if (lead) lead.textContent = variation.petshop_production_lead || defaultLead;
      if (leadRow) leadRow.hidden = !lead?.textContent.trim();
      const variationInput = shippingForm?.querySelector('[name="variation_id"]');
      if (variationInput) variationInput.value = variation.variation_id || '';
      currentUnitPrice = Number.parseFloat(variation.display_price);
      updateQuantityTotal();
      detachFromSummaryPrice();
    });
    window.jQuery(form).on('show_variation', () => {
      const variationId = form.querySelector('[name="variation_id"]')?.value || '';
      if (!variationId) {
        placeQuantityTotal(summaryPrice());
        return;
      }
      placeQuantityTotal(variationPrice() || summaryPrice());
    });
    window.jQuery(form).on('reset_data', () => {
      if (lead) lead.textContent = defaultLead;
      if (leadRow) leadRow.hidden = !defaultLead.trim();
      const variationInput = shippingForm?.querySelector('[name="variation_id"]');
      if (variationInput) variationInput.value = '';
      currentUnitPrice = Number.parseFloat(quantityTotal?.dataset.unitPrice || '');
      updateQuantityTotal();
      placeQuantityTotal(summaryPrice());
    });
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity() || !form.querySelector('[name="variation_id"]')?.value) {
        event.preventDefault();
        const notice = document.createElement('p');
        notice.className = 'woocommerce-error petshop-variation-error';
        notice.setAttribute('role', 'alert');
        notice.textContent = config.selectVariation;
        form.querySelector('.petshop-variation-error')?.remove();
        form.prepend(notice);
      }
    });
  }

  placeQuantityTotal();
  updateQuantityTotal();

  shippingForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(shippingForm);
    const postcode = String(data.get('postcode') || '').replace(/\D/g, '');
    if (postcode.length !== 8) {
      result.textContent = config.invalidPostcode;
      return;
    }
    data.set('postcode', postcode);
    data.set('action', 'petshop_calculate_shipping');
    data.set('nonce', config.nonce);
    result.textContent = config.calculating;
    const button = shippingForm.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.data?.message || config.genericError);
      result.replaceChildren();
      const destination = document.createElement('p');
      destination.className = 'petshop-shipping-calculator__destination';
      destination.textContent = `${config.deliveryTo} ${formatPostcode(postcode)}`;
      result.append(destination);
      const list = document.createElement('ul');
      list.className = 'petshop-shipping-options';
      payload.data.rates.forEach((rate) => {
        const item = document.createElement('li');
        item.className = 'petshop-shipping-option';

        const details = document.createElement('div');
        details.className = 'petshop-shipping-option__details';

        const titleRow = document.createElement('div');
        titleRow.className = 'petshop-shipping-option__title-row';

        const title = document.createElement('strong');
        title.className = 'petshop-shipping-option__title';
        title.textContent = rate.badge
          ? `${rate.carrierLabel || rate.displayLabel || rate.label} `
          : rate.carrierLabel || rate.displayLabel || rate.label;
        titleRow.append(title);

        if (rate.badge) {
          const badge = document.createElement('span');
          badge.className = 'petshop-shipping-option__badge';
          badge.textContent = rate.badge;
          titleRow.append(badge);
        }

        const estimate = document.createElement('span');
        estimate.className = 'petshop-shipping-option__estimate';
        estimate.textContent = rate.deliveryEstimate ? `${config.receiveIn} ${rate.deliveryEstimate}` : config.deliveryAtCheckout;

        details.append(titleRow, estimate);

        const price = document.createElement('span');
        price.className = 'petshop-shipping-option__price';
        price.textContent = rate.costText;

        item.append(details, price);
        list.append(item);
      });
      result.append(list);
      if (payload.data.productionLead) {
        const production = document.createElement('p');
        production.className = 'petshop-shipping-calculator__production';
        production.textContent = `${config.productionLabel}: ${payload.data.productionLead}`;
        result.append(production);
      }
      const note = document.createElement('p');
      note.className = 'petshop-shipping-calculator__note';
      note.textContent = payload.data.transportNote;
      result.append(note);
    } catch (error) {
      result.textContent = error.message || config.genericError;
    } finally {
      button.disabled = false;
    }
  });
})();
