(() => {
    const root = document.querySelector('[data-petshop-cart-shipping]');
    if (!root || !window.wp?.data?.dispatch) return;

    const translate = window.wp?.i18n?.__ || ((value) => value);
    const copy = {
        label: translate('CEP', 'petshop-core'),
        button: translate('Calcular', 'petshop-core'),
        invalid: translate('Informe um CEP com 8 dígitos.', 'petshop-core'),
        pending: translate('Atualizando opções de entrega…', 'petshop-core'),
        error: translate('Não foi possível calcular a entrega. Tente novamente.', 'petshop-core'),
    };
    let pending = false;
    let generation = 0;
    root.innerHTML = `<form><label>${copy.label}<input inputmode="numeric" autocomplete="postal-code" maxlength="9" placeholder="00000-000"></label><button type="submit">${copy.button}</button><p aria-live="polite"></p></form>`;
    const form = root.querySelector('form');
    const input = root.querySelector('input');
    const status = root.querySelector('p');
    const button = root.querySelector('button');
    const digits = (value) => String(value || '').replace(/\D/g, '').slice(0, 8);
    const preferenceKey = 'petshop.shipping-quote-preference.v1';

    const readPreference = () => {
        try {
            const value = JSON.parse(window.sessionStorage.getItem(preferenceKey) || 'null');
            if (!value || value.version !== 1 || !/^\d{8}$/.test(value.postcode || '') || Date.now() - Number(value.createdAt || 0) > 30 * 60 * 1000) {
                window.sessionStorage.removeItem(preferenceKey);
                return null;
            }
            return value;
        } catch (_error) {
            return null;
        }
    };

    const consumePreference = () => {
        try { window.sessionStorage.removeItem(preferenceKey); } catch (_error) { /* no-op */ }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const postcode = digits(input.value);
        if (postcode.length !== 8 || pending) {
            status.textContent = copy.invalid;
            return;
        }
        const request = ++generation;
        pending = true;
        root.setAttribute('aria-busy', 'true');
        button.disabled = true;
        status.textContent = copy.pending;
        try {
            await window.wp.data.dispatch('wc/store/cart').extensionCartUpdate({
                namespace: 'petshop-shipping-quote',
                data: { action: 'set_quote_destination', postcode },
                overwriteDirtyCustomerData: false,
            });
            if (request === generation) {
                const updated = window.wp.data.select('wc/store/cart')?.getCartData?.();
                const rates = (updated?.shipping_rates || []).flatMap((pack) => pack.shipping_rates || []);
                status.textContent = rates.length === 0
                    ? translate('Não há opção de entrega para este CEP.', 'petshop-core')
                    : '';
                consumePreference();
            }
        } catch (_error) {
            if (request === generation) status.textContent = copy.error;
        } finally {
            if (request === generation) {
                pending = false;
                button.disabled = false;
                root.setAttribute('aria-busy', 'false');
            }
        }
    });

    const preference = readPreference();
    const cart = window.wp.data.select('wc/store/cart')?.getCartData?.();
    const addressIsEmpty = !digits(cart?.shipping_address?.postcode)
        && !String(cart?.shipping_address?.address_1 || '').trim()
        && !String(cart?.shipping_address?.city || '').trim();
    if (preference && addressIsEmpty) {
        input.value = preference.postcode.replace(/^(\d{5})(\d{3})$/, '$1-$2');
        window.setTimeout(() => form.requestSubmit(), 0);
    }
})();
