(() => {
  const copy = window.petshopCartShipping;
  const qtyState = window.petshopCartQty;
  if (!copy || !qtyState) return;

  const digitsOf = (value) => String(value || '').replace(/\D/g, '').slice(0, 8);
  const formatPostcode = (digits) => digits.replace(/^(\d{5})(\d{3})$/, '$1-$2');
  const intendedQty = qtyState.intendedQty;
  const intendedAt = qtyState.intendedAt;
  const originalFetch = qtyState.rawFetch;
  let storeNonce = '';
  let intentGeneration = 0;
  let flushTimer = 0;
  let flushing = false;
  let paintFrame = 0;
  let paintQuietSince = 0;
  let dispatchWrapped = false;

  const rememberNonce = (response) => {
    const nonce = response?.headers?.get?.('Nonce');
    if (nonce) storeNonce = nonce;
  };

  const storeApi = {
    async cart() {
      const response = await originalFetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' });
      rememberNonce(response);
      return { response, body: await response.json(), nonce: response.headers.get('Nonce') || storeNonce };
    },
    async updateCustomer(postcode, nonce, current) {
      const shipping = { ...(current?.shipping_address || {}), country: 'BR', postcode };
      const response = await originalFetch('/wp-json/wc/store/v1/cart/update-customer', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Nonce: nonce },
        body: JSON.stringify({ shipping_address: shipping }),
      });
      rememberNonce(response);
      return { response, body: await response.json() };
    },
    async updateItem(key, quantity, nonce) {
      const response = await originalFetch('/wp-json/wc/store/v1/cart/update-item', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Nonce: nonce,
          'X-Petshop-Qty-Flush': '1',
        },
        body: JSON.stringify({ key, quantity: qtyState.live(key) || quantity }),
      });
      rememberNonce(response);
      return { response, body: await response.json() };
    },
  };

  const selectedRates = (cart) => (cart.shipping_rates || []).flatMap((pkg) => pkg.shipping_rates || []);

  const cartItems = () => {
    try {
      return window.wp?.data?.select('wc/store/cart')?.getCartData?.()?.items || [];
    } catch (_error) {
      return [];
    }
  };

  const setIntended = (key, quantity) => {
    qtyState.set(key, quantity);
    intentGeneration += 1;
  };

  const liveIntended = (key) => (flushing ? intendedQty.get(String(key || '')) : qtyState.live(key));

  const mergeIntended = (cart) => qtyState.merge(cart);

  const itemForRow = (row) => {
    const items = cartItems();
    if (items.length === 1) return items[0];
    if (!(row instanceof Element)) return null;
    const rows = [...document.querySelectorAll('.wc-block-cart-items__row, .wc-block-cart-item')];
    return items[rows.indexOf(row)] || null;
  };

  const paintQuantity = () => {
    const items = cartItems();
    document.querySelectorAll('.wc-block-cart-items__row, .wc-block-cart-item').forEach((row, index) => {
      const input = row.querySelector('.wc-block-components-quantity-selector__input');
      const wanted = liveIntended(items[index]?.key);
      if (!(input instanceof HTMLInputElement) || !wanted || document.activeElement === input) {
        return;
      }
      if (Number(input.value) !== wanted) {
        input.value = String(wanted);
      }
    });
  };

  const applyOptimistic = () => {
    paintQuantity();
  };

  const shippingSummaryTargets = () => {
    const totals = [...document.querySelectorAll('.wc-block-components-totals-shipping')];
    if (totals.length > 0) return totals;
    return [...document.querySelectorAll('.wp-block-woocommerce-cart-order-summary-shipping-block')];
  };

  const removeShippingSpinners = () => {
    document.querySelectorAll('[data-petshop-cart-shipping-spinner]').forEach((spinner) => {
      spinner.remove();
    });
  };

  const setShippingRefreshing = (refreshing) => {
    if (!refreshing) {
      removeShippingSpinners();
    }

    shippingSummaryTargets().forEach((target) => {
      if (!(target instanceof HTMLElement)) return;
      target.classList.toggle('wc-block-components-loading-mask', refreshing);
      target.classList.toggle('is-loading', refreshing);
      target.setAttribute('aria-busy', refreshing ? 'true' : 'false');

      target.querySelectorAll(':scope > :not([data-petshop-cart-shipping-spinner])').forEach((child) => {
        if (!(child instanceof HTMLElement)) return;
        child.classList.toggle('wc-block-components-loading-mask__children', refreshing);
        child.toggleAttribute('aria-hidden', refreshing);
      });

      let spinner = target.querySelector('[data-petshop-cart-shipping-spinner]');
      if (refreshing && !spinner) {
        spinner = document.createElement('span');
        spinner.className = 'wc-block-components-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        spinner.setAttribute('data-petshop-cart-shipping-spinner', '');
        target.append(spinner);
      }
      if (!refreshing && spinner) {
        spinner.remove();
      }
    });

    if (!refreshing) {
      window.setTimeout(removeShippingSpinners, 100);
      window.setTimeout(removeShippingSpinners, 500);
      window.setTimeout(removeShippingSpinners, 1500);
    }
  };

  const flushQuantities = async () => {
    if (flushing) {
      scheduleFlush();
      return;
    }
    if (intendedQty.size === 0) return;

    flushing = true;
    qtyState.beginFlush?.();
    setShippingRefreshing(true);
    try {
      do {
        const generation = intentGeneration;
        let nonce = storeNonce;
        if (!nonce) {
          nonce = (await storeApi.cart()).nonce;
        }
        for (const [key, quantity] of intendedQty.entries()) {
          const wanted = liveIntended(key) || quantity;
          if (!wanted) continue;
          const after = await storeApi.updateItem(key, wanted, nonce);
          if (after.response.ok && window.wp?.data?.dispatch && liveIntended(key)) {
            window.wp.data.dispatch('wc/store/cart').receiveCart(mergeIntended(after.body));
          }
          qtyState.settleHeld?.(after.response);
        }
        if (generation === intentGeneration) {
          break;
        }
      } while (true);
    } catch (error) {
      qtyState.rejectHeld?.(error);
      // The next quantity change retries the same intended value.
    } finally {
      flushing = false;
      window.requestAnimationFrame(() => setShippingRefreshing(false));
    }
  };

  const scheduleFlush = () => {
    window.clearTimeout(flushTimer);
    flushTimer = window.setTimeout(() => {
      flushTimer = 0;
      flushQuantities();
    }, qtyState.shippingDebounceMs || 1000);
  };

  const applyStep = (selector, direction) => {
    const row = selector.closest('.wc-block-cart-items__row, .wc-block-cart-item');
    const item = itemForRow(row);
    const key = item?.key;
    const input = selector.querySelector('.wc-block-components-quantity-selector__input');
    const displayed = Number(input?.value || 0);
    const remembered = liveIntended(key);
    const current = remembered && Date.now() - (intendedAt.get(String(key)) || 0) < 800
      ? remembered
      : (Number.isFinite(displayed) && displayed > 0 ? displayed : (remembered || 0));
    const minimum = Number(item?.quantity_limits?.minimum || 1);
    const maximum = Number(item?.quantity_limits?.maximum || 9999);
    const next = Math.min(maximum, Math.max(minimum, current + direction));
    if (!key || next === current) return;

    setIntended(key, next);
    if (input instanceof HTMLInputElement) {
      input.value = String(next);
    }
    applyOptimistic();
    startPaintLoop();
    scheduleFlush();
  };

  const startPaintLoop = () => {
    paintQuietSince = 0;
    if (paintFrame) return;
    const tick = () => {
      paintQuantity();
      unlockQuantityControls();
      const items = cartItems();
      const pendingPaint = flushing || flushTimer || [...document.querySelectorAll('.wc-block-cart-items__row, .wc-block-cart-item')].some((row, index) => {
        const wanted = liveIntended(items[index]?.key);
        const input = row.querySelector('.wc-block-components-quantity-selector__input');
        return Boolean(wanted) && (
          Number(items[index]?.quantity) !== wanted
          || Number(input?.value) !== wanted
        );
      });
      if (pendingPaint) {
        paintQuietSince = 0;
        paintFrame = window.requestAnimationFrame(tick);
        return;
      }
      paintQuietSince = paintQuietSince || Date.now();
      if (Date.now() - paintQuietSince < 3000) {
        paintFrame = window.requestAnimationFrame(tick);
        return;
      }
      paintFrame = 0;
    };
    paintFrame = window.requestAnimationFrame(tick);
  };

  const applyTypedQuantity = (input) => {
    const row = input.closest('.wc-block-cart-items__row, .wc-block-cart-item');
    const item = itemForRow(row);
    const next = Number(input.value);
    if (!item?.key || !Number.isFinite(next) || next < 1) return;
    const minimum = Number(item.quantity_limits?.minimum || 1);
    const maximum = Number(item.quantity_limits?.maximum || 9999);
    const quantity = Math.min(maximum, Math.max(minimum, next));
    setIntended(item.key, quantity);
    input.value = String(quantity);
    applyOptimistic();
    startPaintLoop();
    scheduleFlush();
  };

  const pointIn = (element, event) => {
    if (!(element instanceof Element)) return false;
    const rect = element.getBoundingClientRect();
    return event.clientX >= rect.left && event.clientX <= rect.right
      && event.clientY >= rect.top && event.clientY <= rect.bottom;
  };

  const directionFromEvent = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return { direction: 0, selector: null };
    const selector = target.closest('.wc-block-components-quantity-selector');
    if (!selector) return { direction: 0, selector: null };
    if (target.closest('.wc-block-components-quantity-selector__button--plus')) {
      return { direction: 1, selector };
    }
    if (target.closest('.wc-block-components-quantity-selector__button--minus')) {
      return { direction: -1, selector };
    }
    const plus = selector.querySelector('.wc-block-components-quantity-selector__button--plus');
    const minus = selector.querySelector('.wc-block-components-quantity-selector__button--minus');
    if (pointIn(plus, event)) return { direction: 1, selector };
    if (pointIn(minus, event)) return { direction: -1, selector };
    return { direction: 0, selector };
  };

  const unlockQuantityControls = () => {
    document.querySelectorAll(
      '.wc-block-cart .wc-block-components-quantity-selector__button, .wc-block-cart .wc-block-components-quantity-selector__input',
    ).forEach((element) => {
      if (!(element instanceof HTMLButtonElement) && !(element instanceof HTMLInputElement)) {
        return;
      }
      if (element.disabled) {
        element.disabled = false;
      }
      if (element.getAttribute('aria-disabled') === 'true') {
        element.setAttribute('aria-disabled', 'false');
      }
    });
  };

  const patchCartActions = (target) => {
    Object.keys(target).forEach((name) => {
      const fn = target[name];
      if (typeof fn !== 'function' || !/cart|receive|replace|apply|set/i.test(name)) {
        return;
      }
      if (fn.__petshopQtyLock) return;

      const patched = function patchedCartAction(first, ...rest) {
        if (!first || !Array.isArray(first.items)) {
          return fn.call(this, first, ...rest);
        }
        const result = fn.call(this, mergeIntended(first), ...rest);
        paintQuantity();
        unlockQuantityControls();
        return result;
      };
      patched.__petshopQtyLock = true;
      target[name] = patched;
    });
  };

  const wrapDataDispatch = () => {
    if (!window.wp?.data?.dispatch) return false;
    if (!dispatchWrapped) {
      const originalDispatch = window.wp.data.dispatch.bind(window.wp.data);
      const patchedTargets = new WeakSet();
      window.wp.data.dispatch = (store, ...rest) => {
        const target = originalDispatch(store, ...rest);
        if (
          target
          && (store === 'wc/store/cart' || store?.name === 'wc/store/cart')
          && !patchedTargets.has(target)
        ) {
          patchedTargets.add(target);
          patchCartActions(target);
        }
        return target;
      };
      dispatchWrapped = true;
    }

    try {
      const current = window.wp.data.dispatch('wc/store/cart');
      if (current) patchCartActions(current);
    } catch (_error) {
      return false;
    }
    return true;
  };

  const isQuantityStepEvent = (event) => {
    const { direction, selector } = directionFromEvent(event);
    if (!direction || !selector) return null;
    const row = selector.closest('.wc-block-cart-items__row, .wc-block-cart-item');
    if (!itemForRow(row)?.key) return null;
    return { direction, selector };
  };

  document.addEventListener('pointerdown', (event) => {
    if (!isQuantityStepEvent(event)) return;
    event.stopImmediatePropagation();
  }, true);

  document.addEventListener('click', (event) => {
    const step = isQuantityStepEvent(event);
    if (!step) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    applyStep(step.selector, step.direction);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const target = event.target instanceof Element ? event.target : null;
    const button = target?.closest('.wc-block-components-quantity-selector__button--plus, .wc-block-components-quantity-selector__button--minus');
    if (!button) return;
    const selector = button.closest('.wc-block-components-quantity-selector');
    if (!selector) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    applyStep(
      selector,
      button.classList.contains('wc-block-components-quantity-selector__button--plus') ? 1 : -1,
    );
  }, true);

  document.addEventListener('input', (event) => {
    const input = event.target instanceof HTMLInputElement
      && event.target.classList.contains('wc-block-components-quantity-selector__input')
      ? event.target
      : null;
    if (!input) return;
    applyTypedQuantity(input);
  }, true);

  document.addEventListener('change', (event) => {
    const input = event.target instanceof HTMLInputElement
      && event.target.classList.contains('wc-block-components-quantity-selector__input')
      ? event.target
      : null;
    if (!input) return;
    applyTypedQuantity(input);
  }, true);

  const started = Date.now();
  const patchTimer = window.setInterval(() => {
    if (wrapDataDispatch() || Date.now() - started > 15000) {
      window.clearInterval(patchTimer);
    }
  }, 50);
  wrapDataDispatch();

  unlockQuantityControls();
  new MutationObserver(unlockQuantityControls).observe(document.documentElement, {
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled', 'aria-disabled'],
  });

  const mount = () => {
    const root = document.querySelector('[data-petshop-cart-shipping]');
    const host = document.querySelector('.wc-block-cart__sidebar, .wp-block-woocommerce-cart-totals-block');
    if (!(root instanceof HTMLElement) || !host) return false;

    if (root.querySelector('[data-petshop-cart-shipping-form]')) {
      root.hidden = false;
      if (!host.contains(root)) host.prepend(root);
      return true;
    }

    root.hidden = false;
    root.innerHTML = '';
    const form = document.createElement('form');
    form.className = 'petshop-cart-shipping__form';
    form.setAttribute('data-petshop-cart-shipping-form', '');

    const label = document.createElement('label');
    label.setAttribute('for', 'petshop-cart-shipping-postcode');
    label.textContent = copy.label;

    const row = document.createElement('div');
    row.className = 'petshop-cart-shipping__row';

    const input = document.createElement('input');
    input.id = 'petshop-cart-shipping-postcode';
    input.name = 'postcode';
    input.inputMode = 'numeric';
    input.autocomplete = 'postal-code';
    input.maxLength = 9;
    input.placeholder = copy.placeholder;
    input.required = true;

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'petshop-cart-shipping__button';
    button.textContent = copy.button;

    const status = document.createElement('p');
    status.className = 'petshop-cart-shipping__status';
    status.dataset.petshopCartShippingStatus = '';
    status.setAttribute('aria-live', 'polite');

    row.append(input, button);
    form.append(label, row);
    root.append(form, status);
    if (!host.contains(root)) host.prepend(root);

    const setStatus = (message) => {
      status.textContent = message;
    };

    input.addEventListener('input', () => {
      const digits = digitsOf(input.value);
      input.value = digits.length > 5 ? formatPostcode(digits) : digits;
      if (status.textContent) setStatus('');
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const digits = digitsOf(input.value);
      if (digits.length !== 8) {
        setStatus(copy.invalid);
        return;
      }

      button.disabled = true;
      setStatus(copy.updating);
      try {
        const current = await storeApi.cart();
        const after = await storeApi.updateCustomer(digits, current.nonce, current.body);
        if (!after.response.ok) {
          setStatus(copy.error);
          return;
        }
        if (window.wp?.data?.dispatch) {
          try {
            window.wp.data.dispatch('wc/store/cart').receiveCart(mergeIntended(after.body));
          } catch (_error) {
            // The direct Store API response is still the source of truth.
          }
        }

        const rates = selectedRates(after.body);
        const persisted = String(after.body.shipping_address?.postcode || '').replace(/\D/g, '');
        if (persisted !== digits) {
          setStatus(copy.error);
          return;
        }
        if (rates.length === 0) {
          setStatus(copy.emptyRates);
          return;
        }
        setStatus('');
      } catch (_error) {
        setStatus(copy.error);
      } finally {
        button.disabled = false;
      }
    });

    return true;
  };

  const hidePluginWidgets = () => {
    document.querySelectorAll('#custom-postcode-form, .woo-better-info-block').forEach((node) => {
      if (node instanceof HTMLElement) node.hidden = true;
    });
  };

  const start = () => {
    hidePluginWidgets();
    mount();
    let remountTimer = 0;
    const observer = new MutationObserver(() => {
      hidePluginWidgets();
      window.clearTimeout(remountTimer);
      remountTimer = window.setTimeout(() => {
        mount();
      }, 80);
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
