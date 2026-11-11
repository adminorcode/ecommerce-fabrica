(() => {
    'use strict';

    const initialize = () => {
        const wrapper = document.querySelector('#customer_login');
        const scene = document.querySelector('.petshop-account-scene');
        const i18n = window.petshopAccountRegistration || {};

        if (scene) {
            const loginForm = scene.querySelector('form.login');
            const password = loginForm?.querySelector('#password');
            if (password && !loginForm.querySelector('.petshop-password-toggle')) {
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'petshop-password-toggle';
                toggle.setAttribute('aria-controls', password.id);
                toggle.setAttribute('aria-pressed', 'false');
                toggle.setAttribute('aria-label', i18n.showPassword || 'Mostrar senha');
                toggle.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
                const nativeToggle = password.parentElement.querySelector('.show-password-input');
                if (nativeToggle) nativeToggle.replaceWith(toggle);
                else password.after(toggle);
                // WooCommerce can append its delegated toggle in a later jQuery-ready callback.
                const observer = new MutationObserver(() => {
                    password.parentElement.querySelectorAll('.show-password-input').forEach(node => node.remove());
                    if (toggle.parentElement !== password.parentElement) password.parentElement.append(toggle);
                });
                observer.observe(loginForm, { childList: true, subtree: true });
                toggle.addEventListener('click', () => {
                    const show = password.type === 'password';
                    password.type = show ? 'text' : 'password';
                    toggle.setAttribute('aria-pressed', String(show));
                    toggle.setAttribute('aria-label', show ? i18n.hidePassword : i18n.showPassword);
                });
            }
            const submit = loginForm?.querySelector('[name="login"]');
            const remember = loginForm?.querySelector('.woocommerce-form-login__rememberme');
            const lost = loginForm?.querySelector('.woocommerce-LostPassword');
            if (submit && remember && lost && !loginForm.querySelector('.petshop-login-options')) {
                const options = document.createElement('div');
                options.className = 'petshop-login-options';
                loginForm.insertBefore(options, submit.closest('p'));
                options.append(remember, lost);
            }
            if (submit && !submit.querySelector('.petshop-login-arrow')) {
                const arrow = document.createElement('span');
                arrow.className = 'petshop-login-arrow';
                arrow.setAttribute('aria-hidden', 'true');
                arrow.textContent = '→';
                submit.append(arrow);
            }
        }

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

        if (scene) {
            const separator = document.createElement('div');
            separator.className = 'petshop-account-separator';
            separator.textContent = i18n.separator || 'ou';
            loginColumn.appendChild(separator);
        }
        loginColumn.appendChild(createButton);
        registerColumn.insertBefore(backButton, registerColumn.firstChild);

        const openRegistration = () => {
            wrapper.classList.add('petshop-register-open');
            scene?.classList.add('petshop-account-registering');
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
            scene?.classList.remove('petshop-account-registering');
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
