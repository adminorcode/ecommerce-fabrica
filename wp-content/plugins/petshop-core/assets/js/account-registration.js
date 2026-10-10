(() => {
    'use strict';

    const initialize = () => {
        const wrapper = document.querySelector('#customer_login');

        if (!wrapper || wrapper.dataset.petshopAccountUx === '1') {
            return;
        }

        const loginColumn = wrapper.querySelector('.u-column1.col-1');
        const registerColumn = wrapper.querySelector('.u-column2.col-2');
        const registerForm = registerColumn?.querySelector('form.register');

        if (!loginColumn || !registerColumn || !registerForm) {
            return;
        }

        wrapper.dataset.petshopAccountUx = '1';
        wrapper.classList.add('petshop-account-ux-ready');

        registerColumn.id = 'petshop-register-panel';

        const i18n = window.petshopAccountRegistration || {};
        const createButton = document.createElement('button');
        createButton.type = 'button';
        createButton.className =
            'woocommerce-Button button petshop-account-create';
        createButton.textContent = i18n.createAccount || 'Criar conta';
        createButton.setAttribute('aria-controls', 'petshop-register-panel');
        createButton.setAttribute('aria-expanded', 'false');

        const backButton = document.createElement('button');
        backButton.type = 'button';
        backButton.className = 'petshop-account-back';
        backButton.textContent = i18n.backToLogin || 'Voltar para entrar';

        loginColumn.appendChild(createButton);
        registerColumn.insertBefore(backButton, registerColumn.firstChild);

        const openRegistration = () => {
            wrapper.classList.add('petshop-register-open');
            createButton.setAttribute('aria-expanded', 'true');

            const firstField = registerForm.querySelector(
                'input:not([type="hidden"]), select, textarea'
            );

            if (firstField) {
                firstField.focus();
            }
        };

        const openLogin = () => {
            wrapper.classList.remove('petshop-register-open');
            createButton.setAttribute('aria-expanded', 'false');

            const username = loginColumn.querySelector('#username');

            if (username) {
                username.focus();
            }
        };

        createButton.addEventListener('click', openRegistration);
        backButton.addEventListener('click', openLogin);

        const registrationHasData = Array.from(
            registerForm.querySelectorAll('input, select, textarea')
        ).some((field) => {
            if (
                field.type === 'hidden' ||
                field.type === 'submit' ||
                field.name === 'register'
            ) {
                return false;
            }

            return String(field.value || '').trim() !== '';
        });

        if (registrationHasData) {
            openRegistration();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();