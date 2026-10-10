(() => {
    'use strict';
    const config = window.petshopCartOperationsConfig || {};
    const key = `petshop.shipping-quote-preference.v1:${config.storeKey || location.origin}`;
    const account = String(config.account || 0);
    const queryId = () => typeof crypto.randomUUID === 'function' ? crypto.randomUUID()
        : Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
    const clear = () => { try { sessionStorage.removeItem(key); } catch (_) { /* Storage can be disabled. */ } };
    const read = () => {
        try {
            const value = JSON.parse(sessionStorage.getItem(key) || 'null');
            const age = Date.now() - Number(value?.createdAt);
            if (!value || value.version !== 1 || !/^\d{8}$/.test(value.postcode)
                || !value.queryId || value.account !== account || !Number.isFinite(age) || age < 0 || age > 1800000) {
                clear();
                return null;
            }
            return value;
        } catch (_) { return null; }
    };
    const remember = (postcode) => {
        if (!/^\d{8}$/.test(postcode)) return;
        try {
            sessionStorage.setItem(key, JSON.stringify({ version: 1, postcode, createdAt: Date.now(),
                queryId: queryId(), account }));
        } catch (_) { /* Quoting and buying do not depend on storage. */ }
    };
    const consume = (queryId) => { if (read()?.queryId === queryId) clear(); };
    // Reading on every navigation discards preferences from a previous account.
    read();
    window.petshopQuotePreference = { read, remember, consume, clear };
})();
