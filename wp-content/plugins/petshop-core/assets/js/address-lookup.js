(() => {
    'use strict';

    const digits = (value) => String(value || '').replace(/\D/g, '');

    const setNativeValue = (field, value) => {
        if (!field || !value) {
            return;
        }

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
            neighborhood:
                findField(scope, [
                    '#billing_neighborhood',
                    '#shipping_neighborhood',
                    'input[name="billing_neighborhood"]',
                    'input[name="shipping_neighborhood"]'
                ]),
            city: findField(scope, citySelectors),
            state: findField(scope, stateSelectors)
        };
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
        box.style.marginTop = '6px';
        box.style.fontSize = '14px';

        postcode.insertAdjacentElement('afterend', box);

        return box;
    };

    const showMessage = (postcode, message, error = false) => {
        const box = getMessageBox(postcode);

        box.textContent = message;
        box.style.color = error ? '#b42318' : '';
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

        showMessage(postcode, 'Consultando CEP...');

        try {
            const response = await fetch(
                `https://viacep.com.br/ws/${cep}/json/`,
                {
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('ViaCEP indisponível');
            }

            const data = await response.json();

            if (data.erro) {
                showMessage(
                    postcode,
                    'CEP não encontrado. Confira o número informado.',
                    true
                );

                return;
            }

            const fields = getAddressFields(postcode);

            setNativeValue(fields.address, data.logradouro);
            setNativeValue(fields.neighborhood, data.bairro);
            setNativeValue(fields.city, data.localidade);
            setNativeValue(fields.state, data.uf);

            showMessage(
                postcode,
                'Endereço encontrado pelo CEP.'
            );
        } catch (error) {
            delete postcode.dataset.petshopLastCep;

            showMessage(
                postcode,
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

        const setter = Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value'
        )?.set;

        if (setter) {
            setter.call(field, formatted);
        } else {
            field.value = formatted;
        }

        field.dispatchEvent(
            new Event('input', { bubbles: true })
        );
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

    initialize();

    const observer = new MutationObserver(() => {
        initialize();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();