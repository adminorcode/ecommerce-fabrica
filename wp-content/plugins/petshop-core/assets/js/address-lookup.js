(() => {
    'use strict';

    const digits = (value) => String(value || '').replace(/\D/g, '');
    const checkoutStore = {
        nonce: '',
        noncePromise: null
    };
    const autoComplements = new Map();

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

    const getStoreNonce = async () => {
        const config = window.petshopAddressLookup || {};

        if (!config.storeApiCartUrl) {
            return '';
        }

        if (checkoutStore.nonce) {
            return checkoutStore.nonce;
        }

        if (!checkoutStore.noncePromise) {
            checkoutStore.noncePromise = fetch(config.storeApiCartUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json'
                }
            })
                .then((response) => {
                    checkoutStore.nonce = response.headers.get('Nonce') || '';
                    return checkoutStore.nonce;
                })
                .catch(() => '');
        }

        return checkoutStore.noncePromise;
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

    const collectAddress = (postcode, fields, lookup = {}) => {
        const scope = getScope(postcode);
        const type = getAddressType(postcode);
        const number = getFieldValue(scope, [
            `#${type}-petshop-number`,
            `#${type}-number`,
            `#${type}_number`,
            `input[name="${type}_number"]`,
            'input[name="petshop/number"]'
        ]);
        const neighborhood = fields.neighborhood?.value || lookup.bairro || '';

        return {
            first_name: getFieldValue(scope, [
                `#${type}-first_name`,
                `#${type}_first_name`,
                `input[name="${type}_first_name"]`,
                'input[autocomplete="given-name"]'
            ]),
            last_name: getFieldValue(scope, [
                `#${type}-last_name`,
                `#${type}_last_name`,
                `input[name="${type}_last_name"]`,
                'input[autocomplete="family-name"]'
            ]),
            company: getFieldValue(scope, [
                `#${type}-company`,
                `#${type}_company`,
                `input[name="${type}_company"]`,
                'input[autocomplete="organization"]'
            ]),
            country: getFieldValue(scope, [
                `#${type}-country`,
                `#${type}_country`,
                `select[name="${type}_country"]`,
                `input[name="${type}_country"]`,
                'select[autocomplete="country"]',
                'input[autocomplete="country"]'
            ]) || 'BR',
            address_1: fields.address?.value || lookup.logradouro || '',
            address_2: fields.complement?.value || lookup.complemento || '',
            city: fields.city?.value || lookup.localidade || '',
            state: fields.state?.value || lookup.uf || '',
            postcode: lookup.cep
                ? `${String(lookup.cep).slice(0, 5)}-${String(lookup.cep).slice(5)}`
                : (postcode.value || ''),
            'petshop/number': number || lookup.number || '',
            'petshop/neighborhood': neighborhood,
            phone: getFieldValue(scope, [
                `#${type}-phone`,
                `#${type}_phone`,
                `input[name="${type}_phone"]`,
                'input[autocomplete="tel"]'
            ]),
            email: getFieldValue(scope, [
                '#email',
                '#billing-email',
                '#billing_email',
                'input[name="email"]',
                'input[name="billing_email"]',
                'input[autocomplete="email"]'
            ])
        };
    };

    const applyLookupFields = (fields, data) => {
        setNativeValue(fields.address, data.logradouro);
        applyComplement(fields.complement, data.complemento || '');
        setNativeValue(fields.neighborhood, data.bairro);
        setNativeValue(fields.city, data.localidade);
        setNativeValue(fields.state, data.uf);
    };

    const syncStoreApiAddress = async (postcode, fields, lookup = {}) => {
        if (!document.body.classList.contains('woocommerce-checkout')) {
            return;
        }

        const config = window.petshopAddressLookup || {};

        if (!config.storeApiUpdateCustomerUrl) {
            return;
        }

        const nonce = await getStoreNonce();

        if (!nonce) {
            return;
        }

        const type = getAddressType(postcode);
        const address = collectAddress(postcode, fields, lookup);
        const payload = type === 'shipping'
            ? { shipping_address: address }
            : { billing_address: address };

        try {
            const response = await fetch(config.storeApiUpdateCustomerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Nonce: nonce
                },
                body: JSON.stringify(payload)
            });

            checkoutStore.nonce = response.headers.get('Nonce') || checkoutStore.nonce;
            const cart = await response.json().catch(() => null);

            if (response.ok && cart && window.wp?.data?.dispatch) {
                try {
                    window.wp.data.dispatch('wc/store/cart').receiveCart(cart);
                } catch (error) {
                    // The direct Store API response is still the source of truth.
                }
            }
        } catch (error) {
            // Native field events keep the form usable when Store API sync is unavailable.
        }
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
        const existing = postcode.parentElement?.querySelector(
            '.petshop-cep-message'
        );

        if (existing) {
            return existing;
        }

        const box = document.createElement('div');

        box.className = 'petshop-cep-message';
        box.setAttribute('aria-live', 'polite');

        postcode.insertAdjacentElement('afterend', box);

        return box;
    };

    const showMessage = (postcode, message, error = false) => {
        const box = getMessageBox(postcode);

        box.textContent = message;
        box.classList.toggle('is-error', error);
    };

    const lookup = async (postcode) => {
        const cep = digits(postcode.value);

        if (cep.length !== 8) {
            return;
        }

        if (postcode.dataset.petshopLastCep === cep) {
            return;
        }

        postcode.dataset.petshopLastCep = cep;

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
                    body: body.toString()
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
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
            const fields = getAddressFields(postcode);

            const numberBeforeLookup = getFieldValue(getScope(postcode), [
                '#billing-petshop-number',
                '#shipping-petshop-number',
                '#billing-number',
                '#shipping-number',
                'input[name="petshop/number"]'
            ]);
            applyLookupFields(fields, data);
            await syncStoreApiAddress(postcode, fields, { ...data, cep, number: numberBeforeLookup });
            const refreshed = getAddressFields(postcode);
            applyLookupFields(refreshed, data);
            if (numberBeforeLookup) {
                const numberField = findField(getScope(postcode), [
                    '#billing-petshop-number',
                    '#shipping-petshop-number',
                    '#billing-number',
                    '#shipping-number',
                    'input[name="petshop/number"]'
                ]);
                setNativeValue(numberField, numberBeforeLookup);
            }

            showMessage(
                postcode,
                config.found || 'Endereço encontrado pelo CEP.'
            );
        } catch (error) {
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

    const debounce = (fn, wait) => {
        let timer = 0;

        return () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, wait);
        };
    };

    initialize();

    const observer = new MutationObserver(debounce(initialize, 300));
    const observerRoot = document.querySelector(
        '.wc-block-checkout, .woocommerce-checkout, .woocommerce-account, form.woocommerce-address-form'
    ) || document.body;

    observer.observe(observerRoot, {
        childList: true,
        subtree: true
    });
})();
