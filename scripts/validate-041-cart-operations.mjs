import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const sourcePath = new URL('../wp-content/plugins/petshop-core/assets/src/shared/cart-operations.js', import.meta.url);
const response = fs.existsSync(sourcePath) ? null : await fetch(`${process.env.PETSHOP_BASE_URL || 'http://wordpress'}/wp-content/plugins/petshop-core/assets/src/shared/cart-operations.js`);
if (response) assert(response.ok, 'Runtime operations asset must be delivered before validation');
const source = response ? await response.text() : fs.readFileSync(sourcePath, 'utf8');
let now = 0;
let pending = false;
let failure = null;
let callCount = 0;
let active = 0;
let maximumActive = 0;
let selectionFailure = null;
let ignoreSelection = false;
const snapshot = { items: [{ key: 'synthetic-line', quantity: 3 }], totals: { total_price: '7319' } };
const store = { getCartData: () => snapshot, hasPendingItemsOperations: () => pending };
const context = vm.createContext({
  Date: { now: () => now },
  setTimeout: (callback, delay) => { now += delay; queueMicrotask(callback); },
  window: { wp: { data: { select: () => store, dispatch: () => ({ selectShippingRate: async (rateId, packageId) => {
    if (selectionFailure) throw selectionFailure;
    if (ignoreSelection) return;
    snapshot.shippingRates.find((pack) => pack.package_id === packageId).shipping_rates.forEach((rate) => { rate.selected = rate.rate_id === rateId; });
  } }) } }, wc: { blocksCheckout: {
    extensionCartUpdate: async (operation) => {
      callCount++; active++; maximumActive = Math.max(maximumActive, active);
      try {
        assert.equal(operation.namespace, 'petshop-shipping-quote');
        assert.equal(operation.overwriteDirtyCustomerData, false);
        await Promise.resolve();
        if (failure) throw failure;
      } finally { active--; }
    },
  } } },
});
vm.runInContext(source, context);
const operations = context.window.petshopCartOperations;
pending = true;
await assert.rejects(operations.setQuoteDestination('01310100'), /petshop_cart_busy/);
assert.equal(callCount, 0, 'Pending quantity must prevent our destination operation from starting');
pending = false;
await assert.rejects(operations.setQuoteDestination('01310100', () => false), /petshop_quote_changed/);
assert.equal(callCount, 0, 'Stale intent must not submit a destination');
for (const message of ['expired nonce', 'HTTP 429', 'HTTP 503', 'connection lost']) {
  failure = Error(message);
  await assert.rejects(operations.setQuoteDestination('01310100'), (error) => error === failure);
}
failure = null;
const result = await operations.setQuoteDestination('91210320');
assert.equal(result, snapshot, 'Explicit retry must return the official snapshot unchanged');
const results = await Promise.all([operations.setQuoteDestination('01310100'), operations.setQuoteDestination('91210320')]);
assert(results.every((item) => item === snapshot));
assert.equal(maximumActive, 1, 'Own destination operations must remain serialized');
snapshot.shippingRates = [0, 7].map((package_id) => ({ package_id, shipping_rates: [
  { rate_id: 'old', selected: true }, { rate_id: 'same-id', selected: false },
] }));
const selectionStarted = now;
await operations.selectShippingRate('same-id', 7);
assert.equal(now, selectionStarted, 'Idle native selection must not add artificial quiet windows');
assert.equal(snapshot.shippingRates[0].shipping_rates[0].selected, true, 'Selection must not cross packages with identical rate IDs');
assert.equal(snapshot.shippingRates[1].shipping_rates[1].selected, true);
selectionFailure = Error('Synthetic selection failure');
await assert.rejects(operations.selectShippingRate('old', 7), (error) => error === selectionFailure);
selectionFailure = null; ignoreSelection = true;
await assert.rejects(operations.selectShippingRate('old', 7), /petshop_shipping_selection_failed/, 'Ignored native operation must not announce success');
ignoreSelection = false;
await operations.selectShippingRate('old', 7);
assert.equal(snapshot.shippingRates[1].shipping_rates[0].selected, true);
console.log('041 cart adapter: pending timeout, stale intent, public errors, explicit retry, queue and package-scoped selection passed (isolated public-contract test; browser consistency remains separate)');
