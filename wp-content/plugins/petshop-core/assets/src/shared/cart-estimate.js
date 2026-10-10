(() => {
    'use strict';
    const amount = (item, quantity) => {
        const prices = item?.prices;
        if (!Number.isSafeInteger(quantity) || quantity < 1 || !/^\d{1,16}$/.test(prices?.price || '')
            || !/^[A-Z]{3}$/.test(prices.currency_code || '') || !Number.isInteger(prices.currency_minor_unit)
            || prices.currency_minor_unit < 0 || prices.currency_minor_unit > 6) return null;
        const value = BigInt(prices.price) * BigInt(quantity);
        return value <= BigInt(Number.MAX_SAFE_INTEGER) ? Number(value) : null;
    };
    const total = (entries) => {
        if (!entries.length) return null;
        const values = entries.map(({ item, quantity }) => amount(item, quantity));
        const currencies = new Set(entries.map(({ item }) => `${item?.prices?.currency_code}:${item?.prices?.currency_minor_unit}`));
        if (values.some((value) => value === null) || currencies.size !== 1) return null;
        const sum = values.reduce((value, next) => value + BigInt(next), 0n);
        return sum <= BigInt(Number.MAX_SAFE_INTEGER) ? Number(sum) : null;
    };
    // Presentation only: never put estimates into the transactional cart store.
    window.petshopCartEstimate = { amount, total };
})();
