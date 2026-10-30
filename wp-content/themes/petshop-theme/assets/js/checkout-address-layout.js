(() => {
  'use strict';
  const decorate = () => {
    document.querySelectorAll('.wc-block-checkout .wc-block-components-address-form').forEach((form) => {
      const firstName = form.querySelector('.wc-block-components-address-form__first_name');
      const country = form.querySelector('.wc-block-components-address-form__country');
      if (!firstName || !country) return;
      const step = form.closest('.wc-block-components-checkout-step, .wp-block-woocommerce-checkout-shipping-address-block, .wp-block-woocommerce-checkout-billing-address-block');
      if (step) step.classList.add('petshop-address-step');
      for (const [key, anchor] of [['recipient', firstName], ['address', country]]) {
        let heading = form.querySelector(`[data-petshop-address-heading="${key}"]`);
        if (!heading) {
          heading = document.createElement('h3');
          heading.dataset.petshopAddressHeading = key;
          heading.className = 'petshop-address-heading';
          heading.textContent = window.petshopAddressLayout[key];
        }
        if (heading.nextElementSibling !== anchor) form.insertBefore(heading, anchor);
      }
      // Open the native optional field; WooCommerce retains its state and events.
      const toggle = form.querySelector('.wc-block-components-address-form__address_2-toggle');
      if (toggle) toggle.click();
      // The native street component renders complement directly after street,
      // ignoring locale index. Keep the same parent and native field ownership.
      const number = form.querySelector('.wc-block-components-address-form__petshop-number');
      const complement = form.querySelector('.wc-block-components-address-form__address_2');
      if (number?.parentElement === form && complement?.parentElement === form
        && number.nextElementSibling !== complement) form.insertBefore(complement, number.nextSibling);
    });
  };
  let scheduled = false;
  const observer = new MutationObserver(() => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => { scheduled = false; decorate(); });
  });
  observer.observe(document.body, { childList: true, subtree: true });
  decorate();
})();
