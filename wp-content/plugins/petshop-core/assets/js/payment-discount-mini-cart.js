(function () {
  const namespace = 'petshop-payment-discount-price';

  const text = (value) => String(value || '').replace(/\s+/g, ' ').trim();

  const ensure = (parent, selector, className) => {
    let node = parent.querySelector(selector);
    if (!node) {
      node = document.createElement('span');
      node.className = className;
      parent.append(node);
    }
    return node;
  };

  const paint = () => {
    const items = window.wp?.data?.select?.('wc/store/cart')?.getCartData?.()?.items || [];
    document.querySelectorAll('.wc-block-mini-cart').forEach((root) => {
      root.querySelectorAll('[data-cart-item-key]').forEach((row) => {
        const prices = row.querySelector('.wc-block-cart-item__prices');
        const price = prices?.querySelector('.wc-block-components-product-price');
        if (!prices || !price) {
          return;
        }

        const key = row.getAttribute('data-cart-item-key');
        const data = items.find((item) => item.key === key)?.extensions?.[namespace];
        if (!data?.visible || !data.full || !data.discounted) {
          prices.querySelector('[data-petshop-payment-full]')?.remove();
          prices.querySelector('[data-petshop-payment-discount]')?.remove();
          prices.classList.remove('is-payment-price');
          return;
        }

        if (data.charged_is_discounted) {
          const fullNode = ensure(prices, '[data-petshop-payment-full]', 'petshop-payment-full');
          fullNode.setAttribute('data-petshop-payment-full', '1');
          if (fullNode.textContent !== data.full) {
            fullNode.textContent = data.full;
          }
          if (fullNode.nextElementSibling !== price) {
            prices.insertBefore(fullNode, price);
          }

          const label = text(data.label);
          const labelNode = ensure(prices, '[data-petshop-payment-discount]', 'petshop-payment-discount');
          labelNode.setAttribute('data-petshop-payment-discount', '1');
          if (labelNode.textContent !== label) {
            labelNode.textContent = label;
          }
          if (price.nextElementSibling !== labelNode) {
            price.after(labelNode);
          }
          prices.classList.add('is-payment-price');
          return;
        }

        prices.querySelector('[data-petshop-payment-full]')?.remove();
        prices.classList.remove('is-payment-price');
        const discountedLabel = text(data.label ? `${data.discounted} ${data.label}` : data.discounted);
        const discountNode = ensure(prices, '[data-petshop-payment-discount]', 'petshop-payment-discount');
        discountNode.setAttribute('data-petshop-payment-discount', '1');
        if (discountNode.textContent !== discountedLabel) {
          discountNode.textContent = discountedLabel;
        }
      });
    });
  };

  const start = () => {
    if (!window.wp?.data?.subscribe) {
      return;
    }
    let frame = 0;
    const schedule = () => {
      if (frame) {
        return;
      }
      frame = window.requestAnimationFrame(() => {
        frame = 0;
        paint();
      });
    };
    window.wp.data.subscribe(schedule);
    schedule();
    const host = document.querySelector('.petshop-commercial-header') || document.body;
    const observer = new MutationObserver(schedule);
    observer.observe(host, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
}());
