import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const sourcePath = new URL('../wp-content/plugins/petshop-core/assets/src/shared/quote-preference.js', import.meta.url);
const response = fs.existsSync(sourcePath) ? null : await fetch(`${process.env.PETSHOP_BASE_URL || 'http://wordpress'}/wp-content/plugins/petshop-core/assets/src/shared/quote-preference.js`);
if (response) assert(response.ok, 'Runtime preference asset must be delivered before validation');
const source = response ? await response.text() : fs.readFileSync(sourcePath, 'utf8');
const values = new Map();
let now = 1800000000000;
const load = ({ account = 0, blocked = false, secure = true, storeKey = 'http://shop.test/store' } = {}) => {
  const context = vm.createContext({ window: { petshopCartOperationsConfig: { account, storeKey } },
    location: { origin: 'http://shop.test' }, Date: { now: () => now }, Uint8Array,
    crypto: { ...(secure ? { randomUUID: () => 'synthetic-query-' + now } : {}), getRandomValues: (bytes) => bytes.fill(7) },
    sessionStorage: { getItem: (k) => { if (blocked) throw Error('blocked'); return values.get(k) || null; },
      setItem: (k,v) => { if (blocked) throw Error('blocked'); values.set(k,v); },
      removeItem: (k) => { if (blocked) throw Error('blocked'); values.delete(k); } },
  });
  vm.runInContext(source, context);
  return context.window.petshopQuotePreference;
};
let preference = load();
preference.remember('01310100');
const first = preference.read();
assert.equal(first.postcode, '01310100');
assert.deepEqual(Object.keys(first).sort(), ['version','postcode','createdAt','queryId','account'].sort(), 'Preference must not contain address or tokens');
now++;
preference.remember('91210320');
preference.consume(first.queryId);
assert.equal(preference.read().postcode, '91210320', 'Old completion must not consume a newer preference');
preference.consume(preference.read().queryId);
assert.equal(preference.read(), null);
preference.remember('01310100');
now += 1800001;
assert.equal(preference.read(), null, 'Expired preference must not revive');
preference = load({ account: 41 }); preference.remember('01310100');
assert.equal(load({ account: 0 }).read(), null, 'Logout must discard the previous account preference');
preference = load(); preference.remember('01310100');
assert.equal(load({ storeKey: 'http://shop.test/another-store' }).read(), null, 'Subdirectory stores must not share a preference');
preference = load({ blocked: true });
assert.doesNotThrow(() => preference.remember('01310100'));
assert.equal(preference.read(), null);
values.clear();
preference = load({ secure: false }); preference.remember('01310100');
assert.equal(preference.read()?.postcode, '01310100', 'HTTP storefront must retain preference when randomUUID is unavailable');
console.log('041 quote preference: passed (consumption, expiry, logout, subdirectory, blocked storage, HTTP crypto)');
