(() => {
    'use strict';
    const root = document.querySelector('.wp-block-woocommerce-cart');
    const element = window.wp?.element;
    const coordinator = window.petshopCartRequestCoordinator;
    if (document.body?.classList.contains('wp-admin') || !root || !element?.createRoot || !coordinator || !window.wc?.blocksComponents) return;
    const { createElement: el, createPortal, useEffect, useState, useRef } = element;
    const { __: t } = window.wp.i18n;
    const store = () => window.wp.data.select('wc/store/cart');
    const { amount, total } = window.petshopCartEstimate;
    const money = (item, quantity) => {
        const value = amount(item, quantity);
        return value === null ? t('Atualizando valor…', 'petshop-core') : el(window.wc.blocksComponents.FormattedMonetaryAmount, {
            value, currency: window.wc.priceFormat.getCurrencyFromPriceResponse(item.prices),
        });
    };
    const Feedback = () => {
        const [targets, setTargets] = useState([]);
        const [, refresh] = useState(0);
        const [loadingVisible, setLoadingVisible] = useState(false);
        const [completed, setCompleted] = useState(false);
        const wasPending = useRef(false);
        useEffect(() => {
            let frame = 0;
            let currentTargets = [];
            const normalizations = new Set();
            const update = () => {
                if (!frame) frame = requestAnimationFrame(() => { frame = 0; refresh((value) => value + 1); });
            };
            const reconcile = () => {
                const next = [];
                root.querySelectorAll('.wc-block-cart-items__row[data-cart-item-key]').forEach((row) => {
                    const cell = row.querySelector('.wc-block-cart-item__total');
                    if (!cell) return;
                    let mount = cell.querySelector('[data-petshop-line-mount]');
                    if (!mount) { mount = document.createElement('span'); mount.dataset.petshopLineMount = ''; cell.append(mount); }
                    next.push({ key: row.dataset.cartItemKey, mount });
                });
                const subtotal = root.querySelector('.wp-block-woocommerce-cart-order-summary-subtotal-block');
                if (subtotal) {
                    let mount = subtotal.querySelector('[data-petshop-subtotal-mount]');
                    if (!mount) { mount = document.createElement('span'); mount.dataset.petshopSubtotalMount = ''; subtotal.append(mount); }
                    next.push({ key: 'subtotal', mount });
                }
                const order = root.querySelector('.wc-block-components-totals-footer-item');
                if (order) {
                    let mount = order.querySelector('[data-petshop-order-mount]');
                    if (!mount) { mount = document.createElement('span'); mount.dataset.petshopOrderMount = ''; order.append(mount); }
                    next.push({ key: 'order', mount });
                }
                for (const key of normalizations) if (!next.some((entry) => entry.key === key)) normalizations.delete(key);
                if (currentTargets.length === next.length && currentTargets.every((entry, index) => entry.key === next[index].key && entry.mount === next[index].mount)) return;
                currentTargets = next;
                setTargets(next);
            };
            const editing = (event) => {
                if (event.type === 'click' && event.target.closest('.wc-block-cart__submit-button')
                    && (coordinator.pending() || window.petshopCartOperations.busy()
                        || [...coordinator.desiredQuantities()].some(([key, quantity]) => coordinator.quantityRechecking(key) || store().getCartItem(key)?.quantity !== quantity)
                        || root.classList.contains('petshop-cart-draft') || root.classList.contains('petshop-cart-updating'))) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                if (!event.target.closest('.wc-block-components-quantity-selector')) return;
                const capture = () => {
                    const row = event.target.closest('[data-cart-item-key]');
                    const input = row?.querySelector('.wc-block-components-quantity-selector__input');
                    if (!input) return;
                    const key = row.dataset.cartItemKey;
                    if (event.type === 'input') {
                        if (input.value === '' || !input.validity.valid) normalizations.add(key);
                        else normalizations.delete(key);
                    }
                    // Only reconcile a native clamp. A failed request can reset
                    // its input to the accepted value; merely blurring that
                    // reset must not erase the desired quantity or its retry.
                    if (event.type === 'focusout' && !normalizations.delete(key)) return;
                    coordinator.noteQuantity(key, Number(input.value));
                };
                if (event.type === 'input') capture(); else setTimeout(capture, 0);
            };
            root.addEventListener('input', editing, true);
            root.addEventListener('click', editing, true);
            root.addEventListener('focusout', editing, true);
            const observer = new MutationObserver(() => { reconcile(); update(); });
            observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
            const unsubscribe = coordinator.subscribe(update);
            let previousCart;
            let previousBusy;
            const unsubscribeStore = window.wp.data.subscribe(() => {
                const cart = store().getCartData();
                const busy = window.petshopCartOperations.busy();
                if (cart !== previousCart || busy !== previousBusy) { previousCart = cart; previousBusy = busy; update(); }
            });
            reconcile();
            return () => { observer.disconnect(); unsubscribe(); unsubscribeStore(); cancelAnimationFrame(frame); root.removeEventListener('input', editing, true); root.removeEventListener('click', editing, true); root.removeEventListener('focusout', editing, true); currentTargets.forEach(({ mount }) => mount.remove()); };
        }, []);
        const cart = store().getCartData();
        const busy = coordinator.pending() || window.petshopCartOperations.busy();
        const desires = coordinator.desiredQuantities();
        const items = cart.items || [];
        useEffect(() => {
            for (const [key, quantity] of coordinator.desiredQuantities()) {
                const item = items.find((entry) => entry.key === key);
                if (!item || (!busy && item.quantity === quantity && !coordinator.quantityRechecking(key))) coordinator.clearQuantity(key);
            }
        });
        const changed = items.filter((item) => desires.has(item.key) && (desires.get(item.key) !== item.quantity || coordinator.quantityRechecking(item.key)));
        const draft = changed.length > 0;
        const discountPending = store().isApplyingCoupon() || store().isRemovingCoupon();
        const failed = !busy && changed.some((item) => coordinator.quantityFailed(item.key));
        useEffect(() => {
            root.classList.toggle('petshop-cart-draft', draft);
        });
        useEffect(() => {
            const validation = window.wp.data.dispatch('wc/store/validation');
            const present = window.wp.data.select('wc/store/validation').getValidationError('petshop-cart-quantity');
            // Do not dispatch into WC during the native input debounce: its
            // rerender can cancel that uncommitted change. The synchronous CTA
            // guard already blocks drafts before the first request starts.
            if (draft && (busy || failed) && !present) validation.setValidationErrors({ 'petshop-cart-quantity': { message: t('Aguarde a confirmação das quantidades.', 'petshop-core'), hidden: true } });
            else if (!draft && present) validation.clearValidationError('petshop-cart-quantity');
        }, [draft, busy, failed]);
        useEffect(() => () => {
            root.classList.remove('petshop-cart-draft');
            window.wp.data.dispatch('wc/store/validation').clearValidationError('petshop-cart-quantity');
        }, []);
        const summaryPending = busy || draft || root.classList.contains('petshop-cart-updating');
        useEffect(() => {
            const button = root.querySelector('.wc-block-cart__submit-button');
            if (summaryPending) button?.setAttribute('aria-disabled', 'true'); else button?.removeAttribute('aria-disabled');
            let timer;
            if (summaryPending) {
                wasPending.current = true;
                setCompleted(false);
                timer = setTimeout(() => setLoadingVisible(true), 300);
            } else {
                setLoadingVisible(false);
                if (wasPending.current) { setCompleted(true); wasPending.current = false; }
            }
            return () => { clearTimeout(timer); button?.removeAttribute('aria-disabled'); };
        }, [summaryPending]);
        return targets.map(({ key, mount }) => {
            if (key === 'order') return createPortal(el('span', { className: 'petshop-cart-order-status', role: 'status', 'aria-atomic': 'true' },
                failed ? t('Quantidade não confirmada.', 'petshop-core') : summaryPending && loadingVisible ? t('Atualizando total…', 'petshop-core')
                    : !summaryPending && completed ? el('span', { className: 'screen-reader-text' }, t('Valores atualizados.', 'petshop-core')) : ''), mount, key);
            if (key === 'subtotal') {
                const sum = total(items.map((item) => ({ item, quantity: desires.get(item.key) ?? item.quantity })));
                return createPortal(draft || discountPending ? el('span', { className: 'petshop-cart-estimate', 'data-pending': 'true', 'data-petshop-subtotal-estimate': '' },
                    Number.isSafeInteger(sum) && items.length ? el(window.wc.blocksComponents.FormattedMonetaryAmount, { value: sum, currency: window.wc.priceFormat.getCurrencyFromPriceResponse(items[0].prices) }) : t('Atualizando valor…', 'petshop-core'),
                    el('small', {}, t('Estimativa antes dos descontos e da entrega', 'petshop-core'))) : null, mount, key);
            }
            const item = items.find((entry) => entry.key === key);
            const quantity = desires.get(key) ?? item?.quantity;
            const pending = Boolean(item && (discountPending || quantity !== item.quantity || coordinator.quantityRechecking(key)));
            let confirmed = null;
            if (item && /^\d{1,16}$/.test(item.totals?.line_total || '')) {
                const tax = window.wc.wcSettings.getSetting('displayCartPricesIncludingTax', false) ? item.totals.line_total_tax : '0';
                if (/^\d{1,16}$/.test(tax || '')) {
                    const value = BigInt(item.totals.line_total) + BigInt(tax);
                    if (value <= BigInt(Number.MAX_SAFE_INTEGER)) confirmed = Number(value);
                }
            }
            return createPortal(pending ? el('span', { className: 'petshop-cart-estimate', 'data-petshop-line-value': '', 'data-petshop-line-estimate': '', 'data-pending': 'true', 'aria-label': t('Valor estimado do produto', 'petshop-core') },
                coordinator.quantityRechecking(key) ? t('Revalidando quantidade…', 'petshop-core') : money(item, quantity), el('small', {}, t('Estimado antes dos descontos', 'petshop-core')),
                !busy && coordinator.quantityFailed(key) ? el('small', {}, window.wp.i18n.sprintf(t('Quantidade solicitada: %d', 'petshop-core'), quantity)) : null,
                !busy && coordinator.quantityFailed(key) ? el('button', { type: 'button', onClick: () => {
                    const dispatch = window.wp.data.dispatch('wc/store/cart');
                    if (coordinator.quantityRechecking(key)) { coordinator.retryQuantityCheck(); return; }
                    void dispatch.changeCartItemQuantity(key, quantity).catch((error) => dispatch.receiveError(error));
                } }, t('Tentar novamente', 'petshop-core')) : null) : confirmed !== null ? el('span', { className: 'petshop-cart-line-value', 'data-petshop-line-value': '' },
                    el(window.wc.blocksComponents.FormattedMonetaryAmount, { value: confirmed, currency: window.wc.priceFormat.getCurrencyFromPriceResponse(item.totals) })) : null, mount, key);
        });
    };
    const host = document.createElement('div');
    host.className = 'petshop-cart-feedback';
    root.append(host);
    element.createRoot(host).render(el(Feedback));
})();
