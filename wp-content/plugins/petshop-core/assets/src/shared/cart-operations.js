(() => {
    'use strict';
    const selectCart = () => window.wp.data.select('wc/store/cart');
    const cart = () => selectCart().getCartData();
    const busy = (select = window.wp.data.select) => {
        const store = select('wc/store/cart');
        const checkout = select('wc/store/checkout');
        return Boolean(checkout?.isCalculating?.() || store.isCustomerDataUpdating?.()
            || store.isAddressFieldsForShippingRatesUpdating?.() || store.isShippingRateBeingSelected?.()
            || store.isApplyingCoupon?.() || store.isRemovingCoupon?.() || store.hasPendingItemsOperations?.()
            || store.isCartDataStale?.() || (store.getCartData?.().items || []).some((item) =>
                store.isItemPendingQuantity?.(item.key) || store.isItemPendingDelete?.(item.key)));
    };
    const waitForIdle = async (isCurrent = () => true, quietTime = 500) => {
        const started = Date.now();
        let idleSince = Date.now();
        // Debounced native actions can precede their pending selector. This
        // only coordinates our operation; it never delays a native action.
        while (Date.now() - idleSince < quietTime || busy()) {
            if (!isCurrent()) throw new Error('petshop_quote_changed');
            if (Date.now() - started > 15000) throw new Error('petshop_cart_busy');
            if (busy()) idleSince = Date.now();
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (!isCurrent()) throw new Error('petshop_quote_changed');
    };
    let queue = Promise.resolve();
    const setQuoteDestination = (postcode, isCurrent = () => true) => {
        const operation = queue.catch(() => {}).then(async () => {
            await waitForIdle(isCurrent);
            await window.wc.blocksCheckout.extensionCartUpdate({ namespace: 'petshop-shipping-quote',
                data: { action: 'set_quote_destination', postcode }, overwriteDirtyCustomerData: false });
            // Receiving a destination can start the native address synchronization.
            // Keep our operation pending until that public store cycle completes.
            // WC 10.9.4 schedules address pushes after 1500 ms, so observe a
            // complete idle window before announcing this operation's result.
            await waitForIdle(isCurrent, 2000);
            return cart();
        });
        queue = operation;
        return operation;
    };
    const addItem = async (item) => {
        const result = await window.wp.data.dispatch('wc/store/cart').addItemToCart(item.id, 1, item.variation || []);
        document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', {
            bubbles: true, detail: { preserveCartData: true },
        }));
        return result;
    };
    const selectShippingRate = (rateId, packageId) => {
        const operation = queue.catch(() => {}).then(async () => {
            // Queue still waits for a preceding CEP operation's debounce window.
            // Selecting a rate itself has no delayed address write to anticipate.
            await waitForIdle(() => true, 0);
            await window.wp.data.dispatch('wc/store/cart').selectShippingRate(rateId, packageId);
            await waitForIdle(() => true, 0);
            const updated = cart();
            const selected = (updated.shippingRates || []).find((pack) => pack.package_id === packageId)
                ?.shipping_rates?.some((rate) => rate.rate_id === rateId && rate.selected);
            if (!selected) throw new Error('petshop_shipping_selection_failed');
            return updated;
        });
        queue = operation;
        return operation;
    };
    window.petshopCartOperations = { cart, busy, waitForIdle, setQuoteDestination, selectShippingRate, addItem };
})();
