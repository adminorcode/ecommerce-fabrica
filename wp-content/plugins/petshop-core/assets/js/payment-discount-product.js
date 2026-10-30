(function ($) {
  const fromSlots = () => document.querySelectorAll('[data-petshop-payment-discount-from]');
  const variationPriceHost = () => document.querySelector('.woocommerce-variation-price');
  const variationPrice = () => {
    const host = variationPriceHost();
    if (!host) {
      return null;
    }

    let price = host.querySelector('.price');
    if (!price) {
      price = document.createElement('span');
      price.className = 'price';
      host.prepend(price);
    }

    return price;
  };

  const innerHtml = (markup) => {
    const template = document.createElement('template');
    template.innerHTML = markup.trim();
    const node = template.content.querySelector('.petshop-payment-discount');
    return node ? node.innerHTML : markup;
  };

  const clearVariationLine = () => {
    variationPriceHost()?.querySelector('[data-petshop-payment-discount-live]')?.remove();
  };

  const hideFrom = () => {
    fromSlots().forEach((node) => node.classList.add('is-retired'));
  };

  const showFrom = () => {
    fromSlots().forEach((node) => node.classList.remove('is-retired'));
  };

  const discountMarkup = (variation) => (
    variation && typeof variation.petshop_payment_discount_html === 'string'
      ? variation.petshop_payment_discount_html
      : ''
  );

  const apply = (variation) => {
    const markup = discountMarkup(variation);
    hideFrom();
    clearVariationLine();
    if (markup === '') {
      return;
    }

    const price = variationPrice();
    if (!price) {
      return;
    }

    const line = document.createElement('span');
    line.className = 'petshop-payment-discount';
    line.setAttribute('data-petshop-payment-discount', '');
    line.setAttribute('data-petshop-payment-discount-live', '1');
    line.innerHTML = innerHtml(markup);
    price.append(line);
  };

  const restore = () => {
    clearVariationLine();
    showFrom();
  };

  $(function () {
    const form = document.querySelector('form.variations_form');
    if (!form) {
      return;
    }

    $(form).on('found_variation.petshopPaymentDiscount show_variation.petshopPaymentDiscount', (_event, variation) => {
      apply(variation);
    });
    $(form).on('reset_data.petshopPaymentDiscount hide_variation.petshopPaymentDiscount', restore);
    $(form).trigger('check_variations');
  });
}(jQuery));
