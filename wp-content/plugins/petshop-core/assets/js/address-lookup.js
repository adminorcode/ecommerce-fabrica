(() => {
    'use strict';

    const digits = (value) => String(value || '').replace(/\D/g, '');
    const autoComplements = new Map();
    const generations = new WeakMap();
    const controllers = new WeakMap();
    const revisionOf = (field) => Number(field?.dataset.petshopManualRevision || 0);
    const revisionSnapshot = (fields) => Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, revisionOf(field)]));
    const unchanged = (field, revisions, key) => revisionOf(field) === (revisions[key] || 0);
    const applyBlockAddress = (type, values) => {
        const actions = window.wp.data.dispatch('wc/store/cart');
        actions[type === 'shipping' ? 'setShippingAddress' : 'setBillingAddress'](values);
        if (type === 'shipping' && window.wp.data.select('wc/store/checkout').getUseShippingAsBilling?.()) {
            actions.setBillingAddress(values);
        } else if (type === 'billing' && window.wc?.wcSettings?.getSetting?.('forcedBillingAddress', false)) {
            actions.setShippingAddress(values);
        }
    };

    const setNativeValue = (field, value) => {
        if (!field || value === undefined || value === null) {
            return;
        }

        field.dataset.petshopProgrammaticValue = '1';

        const prototype = field instanceof HTMLSelectElement
            ? HTMLSelectElement.prototype
            : HTMLInputElement.prototype;

        const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');

        if (descriptor && descriptor.set) {
            descriptor.set.call(field, value);
        } else {
            field.value = value;
        }

        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        field.dispatchEvent(new Event('blur', { bubbles: true }));
        delete field.dataset.petshopProgrammaticValue;
    };

    const getScope = (postcode) => {
        return (
            postcode.closest('.wc-block-components-address-form') ||
            postcode.closest('.woocommerce-address-fields') ||
            postcode.closest('form') ||
            document
        );
    };

    const findField = (scope, selectors) => {
        for (const selector of selectors) {
            const field = scope.querySelector(selector);

            if (field) {
                return field;
            }
        }

        return null;
    };

    const getAddressFields = (postcode) => {
        const scope = getScope(postcode);

        const id = postcode.id || '';
        const name = postcode.name || '';

        const shipping =
            id.includes('shipping') ||
            name.includes('shipping');

        const billing =
            id.includes('billing') ||
            name.includes('billing');

        let addressSelectors = [
            'input[autocomplete="address-line1"]'
        ];

        let complementSelectors = [
            'input[autocomplete="address-line2"]'
        ];

        let citySelectors = [
            'input[autocomplete="address-level2"]'
        ];

        let stateSelectors = [
            'select[autocomplete="address-level1"]',
            'input[autocomplete="address-level1"]'
        ];

        if (shipping) {
            addressSelectors = [
                '#shipping-address_1',
                '#shipping_address_1',
                'input[name="shipping_address_1"]',
                ...addressSelectors
            ];

            complementSelectors = [
                '#shipping-address_2',
                '#shipping_address_2',
                'input[name="shipping_address_2"]',
                ...complementSelectors
            ];

            citySelectors = [
                '#shipping-city',
                '#shipping_city',
                'input[name="shipping_city"]',
                ...citySelectors
            ];

            stateSelectors = [
                '#shipping-state',
                '#shipping_state',
                'select[name="shipping_state"]',
                ...stateSelectors
            ];
        }

        if (billing) {
            addressSelectors = [
                '#billing-address_1',
                '#billing_address_1',
                'input[name="billing_address_1"]',
                ...addressSelectors
            ];

            complementSelectors = [
                '#billing-address_2',
                '#billing_address_2',
                'input[name="billing_address_2"]',
                ...complementSelectors
            ];

            citySelectors = [
                '#billing-city',
                '#billing_city',
                'input[name="billing_city"]',
                ...citySelectors
            ];

            stateSelectors = [
                '#billing-state',
                '#billing_state',
                'select[name="billing_state"]',
                ...stateSelectors
            ];
        }

        return {
            address: findField(scope, addressSelectors),
            complement: findField(scope, complementSelectors),
            neighborhood:
                findField(scope, [
                    '#billing_neighborhood',
                    '#shipping_neighborhood',
                    '#billing-neighborhood',
                    '#shipping-neighborhood',
                    '#billing-petshop-neighborhood',
                    '#shipping-petshop-neighborhood',
                    'input[name="billing_neighborhood"]',
                    'input[name="shipping_neighborhood"]',
                    'input[name="billing-neighborhood"]',
                    'input[name="shipping-neighborhood"]',
                    'input[name="petshop/neighborhood"]'
                ]),
            city: findField(scope, citySelectors),
            state: findField(scope, stateSelectors)
        };
    };

    const getFieldValue = (scope, selectors) => {
        const field = findField(scope, selectors);

        return field ? field.value : '';
    };

    const getAddressType = (postcode) => {
        const id = postcode.id || '';
        const name = postcode.name || '';

        return id.includes('shipping') || name.includes('shipping') ? 'shipping' : 'billing';
    };

    const applyLookupFields = (fields, data, revisions) => {
        if (unchanged(fields.address, revisions, 'address')) setNativeValue(fields.address, data.logradouro);
        if (unchanged(fields.complement, revisions, 'complement')) applyComplement(fields.complement, data.complemento || '');
        if (unchanged(fields.neighborhood, revisions, 'neighborhood')) setNativeValue(fields.neighborhood, data.bairro);
        if (unchanged(fields.city, revisions, 'city')) setNativeValue(fields.city, data.localidade);
        if (unchanged(fields.state, revisions, 'state')) setNativeValue(fields.state, data.uf);
    };

    const getComplementKey = (field) => {
        const id = field?.id || '';
        const name = field?.name || '';

        if (id.includes('shipping') || name.includes('shipping')) {
            return 'shipping';
        }

        return 'billing';
    };

    const applyComplement = (field, value) => {
        if (!field) {
            return;
        }

        const key = getComplementKey(field);

        if (value) {
            setNativeValue(field, value);
            field.dataset.petshopAutoComplement = value;
            field.dataset.petshopComplementDirty = '0';
            autoComplements.set(key, value);
            return;
        }

        const previous = field.dataset.petshopAutoComplement || autoComplements.get(key) || '';

        if (previous && field.value === previous && field.dataset.petshopComplementDirty !== '1') {
            setNativeValue(field, '');
            delete field.dataset.petshopAutoComplement;
            autoComplements.delete(key);
        }
    };

    const getMessageBox = (postcode) => {
        const blockScope = postcode.closest('.wc-block-components-address-form');
        const existing = blockScope
            ? blockScope.querySelector(`[data-petshop-cep-for="${postcode.id}"]`)
            : postcode.parentElement?.querySelector('.petshop-cep-message');

        if (existing) {
            return existing;
        }

        const box = document.createElement('div');

        box.className = 'petshop-cep-message';
        box.setAttribute('aria-live', 'polite');
        if (blockScope) {
            box.dataset.petshopCepFor = postcode.id;
            postcode.closest('.wc-block-components-text-input').insertAdjacentElement('afterend', box);
        } else {
            postcode.insertAdjacentElement('afterend', box);
        }

        return box;
    };

    const showMessage = (postcode, message, error = false) => {
        const box = getMessageBox(postcode);

        box.textContent = message;
        box.classList.toggle('is-error', error);
    };

    const lookup = async (postcode, initial = false) => {
        const cep = digits(postcode.value);
        if (cep.length === 8 && postcode.dataset.petshopLastCep === cep) return;
        const generation = (generations.get(postcode) || 0) + 1;
        generations.set(postcode, generation);
        controllers.get(postcode)?.abort();

        if (cep.length !== 8) {
            return;
        }

        if (postcode.dataset.petshopLastCep === cep) {
            return;
        }

        postcode.dataset.petshopLastCep = cep;
        const initialFields = getAddressFields(postcode);
        const lookupRevisions = revisionSnapshot(initialFields);
        // Still consult a saved CEP, but preserve address details already saved
        // by the customer. Only empty fields need hydration on entry.
        if (initial) Object.entries(initialFields).forEach(([key, field]) => {
            if (String(field?.value || '').trim()) lookupRevisions[key] = -1;
        });

        const config = window.petshopAddressLookup || {};

        showMessage(postcode, config.consulting || 'Consultando CEP...');

        if (!config.ajaxUrl || !config.action || !config.nonce) {
            delete postcode.dataset.petshopLastCep;
            showMessage(
                postcode,
                config.unavailable ||
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                true
            );
            return;
        }

        try {
            const controller = new AbortController();
            controllers.set(postcode, controller);
            const body = new URLSearchParams({
                action: config.action,
                nonce: config.nonce,
                cep
            });

            const response = await fetch(
                config.ajaxUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString(),
                    signal: controller.signal
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                if (generations.get(postcode) !== generation || digits(postcode.value) !== cep) return;
                delete postcode.dataset.petshopLastCep;

                showMessage(
                    postcode,
                    result?.data?.message ||
                        config.unavailable ||
                        'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                    true
                );

                return;
            }

            const data = result.data;
            if (generations.get(postcode) !== generation || digits(postcode.value) !== cep) {
                return;
            }
            const fields = getAddressFields(postcode);

            const numberBeforeLookup = getFieldValue(getScope(postcode), [
                '#billing-petshop-number',
                '#shipping-petshop-number',
                '#billing-number',
                '#shipping-number',
                'input[name="petshop/number"]'
            ]);
            if (postcode.closest('.wc-block-components-address-form') && window.wp?.data?.dispatch) {
                const type = getAddressType(postcode);
                const values = { postcode: cep };
                for (const [key, addressKey, lookupKey] of [
                    ['address', 'address_1', 'logradouro'], ['city', 'city', 'localidade'],
                    ['state', 'state', 'uf'], ['neighborhood', 'petshop/neighborhood', 'bairro'],
                    ['complement', 'address_2', 'complemento'],
                ]) {
                    if (unchanged(fields[key], lookupRevisions, key)
                        && !(key === 'complement' && fields[key]?.dataset.petshopComplementDirty === '1')) {
                        values[addressKey] = data[lookupKey] || '';
                    }
                }
                applyBlockAddress(type, values);
            } else {
                applyLookupFields(fields, data, lookupRevisions);
            }
            if (numberBeforeLookup && !postcode.closest('.wc-block-components-address-form')) {
                const numberField = findField(getScope(postcode), [
                    '#billing-petshop-number',
                    '#shipping-petshop-number',
                    '#billing-number',
                    '#shipping-number',
                    'input[name="petshop/number"]'
                ]);
                setNativeValue(numberField, numberBeforeLookup);
            }

            if (generations.get(postcode) !== generation || digits(postcode.value) !== cep) {
                return;
            }
            showMessage(
                postcode,
                config.found || 'Endereço encontrado pelo CEP.'
            );
        } catch (error) {
            if (error?.name === 'AbortError') return;
            if (generations.get(postcode) !== generation || digits(postcode.value) !== cep) return;
            delete postcode.dataset.petshopLastCep;

            showMessage(
                postcode,
                config.unavailable ||
                    'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                true
            );
        }
    };

    const bindPostcode = (postcode) => {
        if (postcode.dataset.petshopCepBound === '1') {
            return;
        }

        postcode.dataset.petshopCepBound = '1';

        postcode.addEventListener('blur', () => {
            lookup(postcode);
        });

        postcode.addEventListener('input', () => {
            const cep = digits(postcode.value);

            if (cep.length === 8) {
                lookup(postcode);
            } else {
                generations.set(postcode, (generations.get(postcode) || 0) + 1);
                controllers.get(postcode)?.abort();
                delete postcode.dataset.petshopLastCep;
            }
        });
    };

    const bindComplement = (field) => {
        if (field.dataset.petshopComplementBound === '1') {
            return;
        }

        field.dataset.petshopComplementBound = '1';

        field.addEventListener('input', () => {
            if (field.dataset.petshopProgrammaticValue === '1') {
                return;
            }

            field.dataset.petshopComplementDirty = '1';
            field.dataset.petshopManualRevision = String(revisionOf(field) + 1);
        });
    };

    // A hydrated React value does not dispatch a native input event. Wait for
    // cart resolution, then resolve each session CEP once without requiring blur.
    const lookupSessionPostcodes = () => {
        if (!document.querySelector('.wc-block-checkout') || !window.wp?.data?.select) return;
        if (window.petshopQuotePreference?.read()) return;
        const store = window.wp.data.select('wc/store/cart');
        if (!store.hasFinishedResolution('getCartData')) return;
        document.querySelectorAll('#shipping-postcode, #billing-postcode').forEach((postcode) => {
            if (postcode.dataset.petshopSessionCepInitialized === '1' || digits(postcode.value).length !== 8) return;
            postcode.dataset.petshopSessionCepInitialized = '1';
            void lookup(postcode, true);
        });
    };

    const bindAddressField = (field) => {
        if (field.dataset.petshopAddressRevisionBound === '1') return;
        field.dataset.petshopAddressRevisionBound = '1';
        field.addEventListener('input', () => {
            if (field.dataset.petshopProgrammaticValue !== '1') {
                field.dataset.petshopManualRevision = String(revisionOf(field) + 1);
            }
        });
    };

    const formatBrazilianPhone = (value) => {
        const phone = digits(value).slice(0, 11);

        if (phone.length === 0) {
            return '';
        }

        if (phone.length <= 2) {
            return `(${phone}`;
        }

        if (phone.length <= 6) {
            return `(${phone.slice(0, 2)}) ${phone.slice(2)}`;
        }

        if (phone.length <= 10) {
            return `(${phone.slice(0, 2)}) ${phone.slice(2, 6)}-${phone.slice(6)}`;
        }

        return `(${phone.slice(0, 2)}) ${phone.slice(2, 7)}-${phone.slice(7)}`;
    };

    const bindPhone = (field) => {
        if (field.dataset.petshopPhoneBound === '1') {
            return;
        }

        field.dataset.petshopPhoneBound = '1';

        field.addEventListener('input', () => {
            const formatted = formatBrazilianPhone(field.value);

            if (field.value === formatted) {
                return;
            }

            const selectionStart = field.selectionStart;
            const previousLength = field.value.length;
            const setter = Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value'
            )?.set;

            if (setter) {
                setter.call(field, formatted);
            } else {
                field.value = formatted;
            }

            if (typeof selectionStart === 'number') {
                const next = Math.max(0, selectionStart + (formatted.length - previousLength));
                field.setSelectionRange(next, next);
            }
        });
    };

    const initialize = () => {
        // WC 10.9.4 forwards the registered lowercase autocomplete attribute
        // as a React prop. Use a supported data attribute and the native DOM API.
        document.querySelectorAll('input[data-petshop-autocomplete]').forEach((field) => {
            const value = field.dataset.petshopAutocomplete;
            if (field.autocomplete !== value) field.autocomplete = value;
        });
        const postcodeSelectors = [
            '#billing_postcode',
            '#shipping_postcode',
            '#billing-postcode',
            '#shipping-postcode',
            'input[name="billing_postcode"]',
            'input[name="shipping_postcode"]',
            'input[autocomplete="postal-code"]'
        ];

        document
            .querySelectorAll(postcodeSelectors.join(','))
            .forEach(bindPostcode);

        const complementSelectors = [
            '#billing_address_2',
            '#shipping_address_2',
            '#billing-address_2',
            '#shipping-address_2',
            'input[name="billing_address_2"]',
            'input[name="shipping_address_2"]',
            'input[autocomplete="address-line2"]'
        ];

        document
            .querySelectorAll(complementSelectors.join(','))
            .forEach(bindComplement);

        document.querySelectorAll([
            'input[autocomplete="address-line1"]',
            'input[autocomplete="address-level2"]',
            'select[autocomplete="address-level1"]',
            'input[autocomplete="address-level1"]',
            '#billing-petshop-neighborhood', '#shipping-petshop-neighborhood',
        ].join(','))
            .forEach(bindAddressField);

        const phoneSelectors = [
            '#billing_phone',
            '#shipping_phone',
            '#billing-phone',
            '#shipping-phone',
            'input[name="billing_phone"]',
            'input[name="shipping_phone"]',
            'input[autocomplete="tel"]'
        ];

        document
            .querySelectorAll(phoneSelectors.join(','))
            .forEach(bindPhone);
    };

    let preferenceStarted = false;
    let addressEdited = false;
    document.addEventListener('input', (event) => {
        if (event.isTrusted && event.target.closest('.wc-block-components-address-form')) addressEdited = true;
    }, true);
    const consumeQuotePreferenceAtCheckout = async () => {
        if (preferenceStarted || addressEdited || !document.querySelector('.wc-block-checkout') || !window.petshopCartOperations) return;
        const preference = window.petshopQuotePreference?.read();
        if (!preference) return;
        let postcode = document.querySelector('#shipping-postcode, #billing-postcode');
        if (!(postcode instanceof HTMLInputElement)) return;
        preferenceStarted = true;
        try {
            const updated = await window.petshopCartOperations.setQuoteDestination(preference.postcode, () => !addressEdited);
            if (addressEdited) return;
            if (digits(updated.shippingAddress?.postcode) === preference.postcode
                && updated.shippingAddress?.address_1 && updated.shippingAddress?.city) {
                window.petshopQuotePreference.consume(preference.queryId);
                lookupSessionPostcodes();
                return;
            }
            postcode = document.querySelector('#shipping-postcode, #billing-postcode');
            if (!(postcode instanceof HTMLInputElement)) throw new Error('petshop_address_unavailable');
            const type = getAddressType(postcode);
            applyBlockAddress(type, { postcode: preference.postcode, city: '', address_1: '', address_2: '' });
            await new Promise((resolve) => setTimeout(resolve, 0));
            postcode = document.querySelector('#shipping-postcode, #billing-postcode');
            if (!(postcode instanceof HTMLInputElement)) throw new Error('petshop_address_unavailable');
            window.petshopQuotePreference.consume(preference.queryId);
            await lookup(postcode);
        } catch (_error) {
            if (addressEdited) return;
            showMessage(postcode, window.petshopAddressLookup.unavailable, true);
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.textContent = window.wp?.i18n?.__('Tentar novamente', 'petshop-core') || 'Tentar novamente';
            retry.addEventListener('click', () => {
                retry.remove();
                preferenceStarted = false;
                consumeQuotePreferenceAtCheckout();
            }, { once: true });
            getMessageBox(postcode).append(retry);
        }
    };
    const debounce = (fn, wait) => {
        let timer = 0;

        return () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, wait);
        };
    };

    initialize();
    consumeQuotePreferenceAtCheckout();
    lookupSessionPostcodes();
    if (window.wp?.data?.subscribe && document.querySelector('.wc-block-checkout')) {
        window.wp.data.subscribe(lookupSessionPostcodes, 'wc/store/cart');
    }

    const observer = new MutationObserver(debounce(() => { initialize(); consumeQuotePreferenceAtCheckout(); lookupSessionPostcodes(); }, 300));
    const observerRoot = document.querySelector(
        '.wc-block-checkout, .woocommerce-checkout, .woocommerce-account, form.woocommerce-address-form'
    ) || document.body;

    observer.observe(observerRoot, {
        childList: true,
        subtree: true
    });
})();
