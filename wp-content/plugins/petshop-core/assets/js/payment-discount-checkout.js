(function () {
  const remember = (method) => {
    const update = window.wc?.blocksCheckout?.extensionCartUpdate;
    if (typeof update !== 'function' || !method) {
      return;
    }

    update({
      namespace: 'petshop-payment-discount',
      data: { paymentMethod: method },
    });
  };

  const activeMethod = () => {
    const select = window.wp?.data?.select;
    if (typeof select !== 'function') {
      return '';
    }

    const stores = ['wc/store/payment'];
    const key = window.wc?.wcBlocksData?.PAYMENT_STORE_KEY;
    if (typeof key === 'string' && key !== '') {
      stores.unshift(key);
    }

    for (const store of stores) {
      const payment = select(store);
      if (payment && typeof payment.getActivePaymentMethod === 'function') {
        return payment.getActivePaymentMethod() || '';
      }
    }

    return '';
  };

  if (window.wp?.data?.subscribe) {
    let last = '';
    window.wp.data.subscribe(() => {
      const method = activeMethod();
      if (!method || method === last || typeof window.wc?.blocksCheckout?.extensionCartUpdate !== 'function') {
        return;
      }
      last = method;
      remember(method);
    });
  }

  document.body.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.name !== 'payment_method') {
      return;
    }
    remember(input.value);
  });
}());
