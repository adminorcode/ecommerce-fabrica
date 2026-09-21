(() => {
    'use strict';

    const FIELD_ID = 'petshop-checkout-password-confirmation';
    const ERROR_ID = 'petshop-checkout-password-confirmation-error';
    const VALIDATION_ID = 'petshop-checkout-password-confirmation';
    const i18n = window.petshopCheckoutAccountPassword || {};
    const EXTENSION_NAMESPACE = i18n.namespace || 'petshop-account';

    const debounce = (fn, wait) => {
        let timer = 0;

        return () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, wait);
        };
    };

    const getStoreKey = (preferred, fallback) => {
        const data = window.wc?.wcBlocksData;

        return data?.[preferred] || data?.[fallback] || null;
    };

    const getValidationStore = () => {
        return getStoreKey('validationStore', 'VALIDATION_STORE_KEY');
    };

    const getCheckoutStore = () => {
        return getStoreKey('checkoutStore', 'CHECKOUT_STORE_KEY');
    };

    const getValidationActions = () => {
        const store = getValidationStore();

        if (!store || !window.wp?.data) {
            return null;
        }

        try {
            return window.wp.data.dispatch(store);
        } catch (error) {
            return null;
        }
    };

    const getValidationError = () => {
        const store = getValidationStore();

        if (!store || !window.wp?.data) {
            return null;
        }

        try {
            return window.wp.data.select(store).getValidationError(VALIDATION_ID);
        } catch (error) {
            return null;
        }
    };

    const findAccountPassword = () => {
        const selectors = [
            '.wc-block-components-address-form__password input[type="password"]',
            '.wc-block-components-create-account-password input[type="password"]',
            '.wc-block-components-create-account input[type="password"]',
            '.wc-block-checkout__create-account input[type="password"]',
            '#account-password',
            'input#reg_password',
        ];

        for (const selector of selectors) {
            const field = document.querySelector(`${selector}:not(#${FIELD_ID})`);

            if (field instanceof HTMLInputElement) {
                return field;
            }
        }

        return null;
    };

    const clearValidation = () => {
        const actions = getValidationActions();

        if (!actions) {
            return;
        }

        if (getValidationError()) {
            actions.clearValidationError(VALIDATION_ID);
        }
    };

    const renderValidationError = () => {
        const container = document.getElementById(ERROR_ID);

        if (!container) {
            return;
        }

        const error = getValidationError();

        if (error?.message && error.hidden === false) {
            container.textContent = error.message;
            container.hidden = false;
            return;
        }

        container.textContent = '';
        container.hidden = true;
    };

    let lastExtensionValue;

    const syncExtensionData = (confirmation) => {
        const value = confirmation?.value || '';

        if (lastExtensionValue === value) {
            return;
        }

        const store = getCheckoutStore();

        if (!store || !window.wp?.data) {
            return;
        }

        try {
            const actions = window.wp.data.dispatch(store);

            if (!actions?.setExtensionData) {
                return;
            }

            actions.setExtensionData(EXTENSION_NAMESPACE, {
                password_confirm: value,
            });
            lastExtensionValue = value;
        } catch (error) {
            return;
        }
    };

    const validate = () => {
        const password = findAccountPassword();
        const confirmation = document.getElementById(FIELD_ID);
        const actions = getValidationActions();

        syncExtensionData(confirmation);

        if (!password || !confirmation || !actions) {
            clearValidation();
            renderValidationError();
            return;
        }

        let message = '';

        if (!confirmation.value) {
            message = i18n.required || 'Confirme sua senha.';
        } else if (password.value !== confirmation.value) {
            message = i18n.mismatch || 'As senhas não coincidem.';
        }

        if (!message) {
            clearValidation();
            renderValidationError();
            return;
        }

        const currentError = getValidationError();

        actions.setValidationErrors({
            [VALIDATION_ID]: {
                message,
                hidden: currentError?.hidden ?? true,
            },
        });

        renderValidationError();
    };

    const removeConfirmationField = () => {
        const field = document.getElementById(FIELD_ID);

        if (field) {
            const wrapper = field.closest(
                '.petshop-checkout-password-confirmation'
            );

            if (wrapper) {
                wrapper.remove();
            }
        }

        syncExtensionData(null);
        clearValidation();
    };

    const createConfirmationField = (password) => {
        if (document.getElementById(FIELD_ID)) {
            return;
        }

        const passwordWrapper =
            password.closest('.wc-block-components-text-input') ||
            password.parentElement;

        if (!passwordWrapper) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className =
            'wc-block-components-text-input petshop-checkout-password-confirmation is-active';
        const input = document.createElement('input');
        input.type = 'password';
        input.id = FIELD_ID;
        input.autocomplete = 'new-password';
        input.required = true;

        const label = document.createElement('label');
        label.htmlFor = FIELD_ID;
        label.textContent = i18n.label || 'Confirmar senha';

        const error = document.createElement('div');
        error.id = ERROR_ID;
        error.className = 'wc-block-components-validation-error';
        error.setAttribute('role', 'alert');
        error.hidden = true;

        wrapper.append(input, label, error);
        passwordWrapper.insertAdjacentElement('afterend', wrapper);

        input.addEventListener('input', validate);
        password.addEventListener('input', validate);

        input.addEventListener('blur', () => {
            validate();

            const actions = getValidationActions();

            if (getValidationError()) {
                actions?.showValidationError(VALIDATION_ID);
                renderValidationError();
            }
        });

        validate();
    };

    const sync = () => {
        const password = findAccountPassword();

        if (!password) {
            removeConfirmationField();
            return;
        }

        createConfirmationField(password);
    };

    const initialize = () => {
        if (!window.wp?.data || !getValidationStore()) {
            return;
        }

        window.wp.data.subscribe(renderValidationError);

        sync();

        const observer = new MutationObserver(debounce(sync, 80));

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
