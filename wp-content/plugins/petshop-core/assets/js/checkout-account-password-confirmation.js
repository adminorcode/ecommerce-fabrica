(() => {
    'use strict';

    const FIELD_ID = 'petshop-checkout-password-confirmation';
    const ERROR_ID = 'petshop-checkout-password-confirmation-error';
    const VALIDATION_ID = 'petshop-checkout-password-confirmation';

    const getValidationStore = () => {
        return window.wc?.wcBlocksData?.validationStore || null;
    };

    const getValidationActions = () => {
        const store = getValidationStore();

        if (!store || !window.wp?.data) {
            return null;
        }

        return window.wp.data.dispatch(store);
    };

    const getValidationError = () => {
        const store = getValidationStore();

        if (!store || !window.wp?.data) {
            return null;
        }

        return window.wp.data.select(store).getValidationError(VALIDATION_ID);
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

    const validate = () => {
        const password = document.querySelector(
            `input[type="password"]:not(#${FIELD_ID})`
        );
        const confirmation = document.getElementById(FIELD_ID);
        const actions = getValidationActions();

        if (!password || !confirmation || !actions) {
            clearValidation();
            renderValidationError();
            return;
        }

        let message = '';

        if (!confirmation.value) {
            message = 'Confirme sua senha.';
        } else if (password.value !== confirmation.value) {
            message = 'As senhas não coincidem.';
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
        label.textContent = 'Confirmar senha';

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
        const password = document.querySelector(
            `input[type="password"]:not(#${FIELD_ID})`
        );

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

        const observer = new MutationObserver(sync);

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