(() => {
    const checkout = window.wc?.blocksCheckout;
    const element = window.wp?.element;
    const data = window.wp?.data;
    if (!checkout || !element || !data) return;
    const { createElement: el, useEffect, useRef, useState } = element;
    const { __: t } = window.wp.i18n;
    const blockName = 'petshop/cart-shipping-quote';
    const digits = (value) => String(value || '').replace(/\D/g, '').slice(0, 8);
    const format = (value) => digits(value).replace(/^(\d{5})(\d{0,3})$/, '$1-$2').replace(/-$/, '');
    checkout.registerCheckoutFilters('petshop-cart-shipping-quote', {
        additionalCartCheckoutInnerBlockTypes: (allowed, _extensions, args) =>
            args?.block === 'woocommerce/cart-totals-block' && !allowed.includes(blockName) ? [...allowed, blockName] : allowed,
    });
    const ShippingQuote = () => {
        const [postcode, setPostcode] = useState('');
        const [status, setStatus] = useState('');
        const [pending, setPending] = useState(false);
        const [transportPending, setTransportPending] = useState(false);
        const [quantityDraft, setQuantityDraft] = useState(false);
        const [destinationUnconfirmed, setDestinationUnconfirmed] = useState(false);
        const submittedPostcode = useRef('');
        const revision = useRef(0);
        const mounted = useRef(true);
        const initialized = useRef(false);
        const cart = data.useSelect((select) => select('wc/store/cart').getCartData(), []);
        const nativePending = data.useSelect((select) => window.petshopCartOperations.busy(select), []);
        useEffect(() => window.petshopCartRequestCoordinator?.subscribe((pending) => {
            setTransportPending(pending);
            setQuantityDraft([...window.petshopCartRequestCoordinator.desiredQuantities().keys()]
                .some((key) => !window.petshopCartRequestCoordinator.quantityFailed(key)));
        }), []);
        useEffect(() => {
            const root = document.querySelector('.wp-block-woocommerce-cart');
            root?.classList.toggle('petshop-cart-updating', pending || transportPending || nativePending || quantityDraft || destinationUnconfirmed);
            return () => root?.classList.remove('petshop-cart-updating');
        }, [pending, transportPending, nativePending, quantityDraft, destinationUnconfirmed]);
        useEffect(() => {
            if (!pending && !transportPending && !nativePending && submittedPostcode.current === digits(postcode)
                && digits(cart.shippingAddress?.postcode) === digits(postcode)) setDestinationUnconfirmed(false);
        }, [pending, transportPending, nativePending, postcode, cart]);
        const run = async (normalized, generation, preference) => {
            setPending(true);
            setStatus(t('Atualizando opções de entrega…', 'petshop-core'));
            try {
                const updated = await window.petshopCartOperations.setQuoteDestination(normalized,
                    () => mounted.current && revision.current === generation);
                if (preference) window.petshopQuotePreference.consume(preference.queryId);
                if (!mounted.current || revision.current !== generation) return;
                setDestinationUnconfirmed(false);
                const requiresAddress = updated.extensions?.['petshop-shipping-quote']?.requires_address;
                const rates = (updated.shippingRates || []).flatMap((pack) => pack.shipping_rates || []);
                setStatus(requiresAddress
                    ? t('Complete o endereço no checkout para consultar a entrega.', 'petshop-core')
                    : rates.length ? '' : t('Nenhuma opção foi retornada. Complete o endereço no checkout para confirmar a entrega.', 'petshop-core'));
            } catch (error) {
                if (error.name === 'AbortError') {
                    if (mounted.current && revision.current === generation) setStatus('');
                    return;
                }
                if (mounted.current && revision.current === generation) setStatus(error.message && !error.message.startsWith('petshop_')
                    ? error.message : t('Não foi possível calcular a entrega. Tente novamente.', 'petshop-core'));
            } finally {
                if (mounted.current && revision.current === generation) setPending(false);
            }
        };
        useEffect(() => () => { mounted.current = false; }, []);
        useEffect(() => {
            if (initialized.current || !cart?.items?.length) return;
            initialized.current = true;
            const preference = window.petshopQuotePreference.read();
            setPostcode(format(preference?.postcode || cart.shippingAddress?.postcode));
            if (preference) void run(preference.postcode, revision.current, preference);
        }, [cart]);
        const submit = (event) => {
            event.preventDefault();
            if (digits(postcode).length !== 8) {
                setStatus(t('Informe um CEP com 8 dígitos.', 'petshop-core'));
                return;
            }
            revision.current++;
            submittedPostcode.current = digits(postcode);
            window.petshopCartRequestCoordinator?.invalidateQuote();
            void run(digits(postcode), revision.current);
        };
        const selectRate = async (rateId, packageId) => {
            if (pending) return;
            setPending(true);
            setStatus(t('Atualizando opções de entrega…', 'petshop-core'));
            try {
                await window.petshopCartOperations.selectShippingRate(rateId, packageId);
                if (mounted.current) setStatus('');
            } catch (error) {
                if (mounted.current) setStatus(error.message && !error.message.startsWith('petshop_')
                    ? error.message : t('Não foi possível selecionar a entrega. Tente novamente.', 'petshop-core'));
            } finally {
                if (mounted.current) setPending(false);
            }
        };
        const packages = digits(postcode) === digits(cart.shippingAddress?.postcode) ? cart.shippingRates || [] : [];
        const amount = (rate) => Number(rate.price) + (window.wc.wcSettings.getSetting('displayCartPricesIncludingTax', false) ? Number(rate.taxes || 0) : 0);
        return el('section', { className: 'petshop-cart-shipping', 'aria-busy': pending || transportPending || nativePending || quantityDraft, 'aria-label': t('Calcular entrega', 'petshop-core') },
            el('form', { className: 'petshop-cart-shipping__form', onSubmit: submit, 'data-petshop-cart-shipping-form': true }, [
                el('label', { key: 'label', htmlFor: 'petshop-cart-shipping-postcode' }, t('CEP', 'petshop-core')),
                el('div', { key: 'row', className: 'petshop-cart-shipping__row' }, [
                    el('input', { key: 'input', id: 'petshop-cart-shipping-postcode', name: 'postcode', inputMode: 'numeric',
                        autoComplete: 'postal-code', maxLength: 9, placeholder: '00000-000', required: true, value: postcode,
                        onChange: (event) => {
                            revision.current++;
                            window.petshopCartRequestCoordinator?.invalidateQuote();
                            const preference = window.petshopQuotePreference.read();
                            if (preference) window.petshopQuotePreference.consume(preference.queryId);
                            setDestinationUnconfirmed(true);
                            setPending(false);
                            setStatus('');
                            setPostcode(format(event.target.value));
                        } }),
                    el('button', { key: 'button', className: 'petshop-cart-shipping__button', type: 'submit', disabled: pending }, t('Calcular', 'petshop-core')),
                ]),
                el('p', { key: 'status', className: 'petshop-cart-shipping__status', 'data-petshop-cart-shipping-status': true, role: 'status', 'aria-live': 'polite' }, status || (transportPending || nativePending || quantityDraft ? t('Atualizando opções de entrega…', 'petshop-core') : destinationUnconfirmed ? t('Calcule a entrega para o CEP informado.', 'petshop-core') : '')),
            ]),
            packages.map((pack) => el('div', { key: pack.package_id, role: 'group', 'aria-label': packages.length > 1 ? pack.name : t('Opções de entrega', 'petshop-core'), className: 'wc-block-components-shipping-rates-control',
                'data-petshop-shipping-package': pack.package_id }, [
                el('p', { key: 'legend' }, packages.length > 1 ? pack.name : t('Opções de entrega', 'petshop-core')),
                el(window.wc.blocksComponents.RadioControl, {
                    key: 'options', id: `petshop-shipping-package-${pack.package_id}`, disabled: pending || nativePending || transportPending || quantityDraft,
                    selected: (pack.shipping_rates || []).find((rate) => rate.selected)?.rate_id || '',
                    onChange: (rateId) => void selectRate(rateId, pack.package_id),
                    options: (pack.shipping_rates || []).map((rate) => ({
                        value: rate.rate_id, label: rate.name,
                        secondaryLabel: el(window.wc.blocksComponents.FormattedMonetaryAmount, {
                            value: amount(rate), currency: window.wc.priceFormat.getCurrencyFromPriceResponse(rate),
                        }),
                    })),
                }),
            ])));
    };
    checkout.registerCheckoutBlock({ metadata: { name: blockName, parent: ['woocommerce/cart-totals-block'] }, component: ShippingQuote });
})();
