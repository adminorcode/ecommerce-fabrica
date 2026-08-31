(() => {
  const config = window.petshopProductConfig;
  if (!config) return;
  const form = document.querySelector('form.variations_form');
  const lead = document.querySelector('[data-petshop-production-lead]');
  const leadRow = document.querySelector('[data-petshop-production-row]');
  const defaultLead = lead?.textContent || '';
  const shippingForm = document.querySelector('[data-petshop-shipping-form]');
  const result = document.querySelector('[data-petshop-shipping-result]');
  const formatPostcode = (postcode) => postcode.replace(/^(\d{5})(\d{3})$/, '$1-$2');

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
    });
    window.jQuery(form).on('reset_data', () => {
      if (lead) lead.textContent = defaultLead;
      if (leadRow) leadRow.hidden = !defaultLead.trim();
      const variationInput = shippingForm?.querySelector('[name="variation_id"]');
      if (variationInput) variationInput.value = '';
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
