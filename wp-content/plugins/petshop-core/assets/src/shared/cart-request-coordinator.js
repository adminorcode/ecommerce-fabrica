(() => {
    'use strict';
    if (document.body?.classList.contains('wp-admin') || !document.querySelector('.wp-block-woocommerce-cart') || !window.wp?.apiFetch?.use) return;
    const listeners = new Set();
    const queue = [];
    const deferredQuantityCancellations = [];
    const revisions = new WeakMap();
    const latestRevisions = new Map();
    const desires = new Map();
    const failures = new Set();
    const rechecking = new Set();
    const uncertain = new Set();
    const lostResponses = [];
    let revalidationRequired = false;
    let revalidationPending = false;
    let revision = 0;
    let active = null;
    const path = (request) => {
        const raw = String(request.path || request.url || '');
        const url = new URL(raw, window.location.href);
        return url.searchParams.get('rest_route') || url.pathname;
    };
    const operations = (options) => /\/wc\/store\/v1\/batch$/.test(path(options))
        ? options.data?.requests || [] : [options];
    const isCart = (options) => operations(options).length > 0 && operations(options).every((request) => /\/wc\/store\/v1\/cart(?:\/|$)/.test(path(request)));
    const isQuote = (options) => operations(options).every((request) => /\/cart\/extensions$/.test(path(request))
        && (request.body || request.data)?.namespace === 'petshop-shipping-quote');
    const isQuantity = (options) => operations(options).every((request) => /\/cart\/update-item$/.test(path(request)));
    const isCartRead = (options) => operations(options).every((request) => (request.method || 'GET') === 'GET' && /\/cart$/.test(path(request)));
    const replaceable = (options) => isQuote(options) || isQuantity(options) || isCartRead(options);
    const identity = (options) => {
        const requests = operations(options);
        if (isQuantity(options) && requests.length === 1) return `quantity:${(requests[0].data || requests[0].body)?.key}`;
        if (isQuote(options)) return 'quote';
        if (isCartRead(options)) return 'read';
        return null;
    };
    const quantityFor = (job, key) => operations(job.options).find((request) => /\/cart\/update-item$/.test(path(request)) && (request.data || request.body)?.key === key);
    const pending = () => Boolean(active || queue.length || deferredQuantityCancellations.length || revalidationRequired || revalidationPending);
    const notify = () => listeners.forEach((listener) => listener(pending()));
    const cancelled = () => new DOMException('Superseded cart operation', 'AbortError');
    const serverAnswered = (error) => {
        if (!error || error.code === 'offline_error' || error.code === 'fetch_error') return false;
        if (error instanceof Response) return true;
        if (Number.isInteger(error.data?.status) || Number.isInteger(error.status)) return true;
        return typeof error.code === 'string';
    };
    // A dropped connection has no HTTP status. The write may already exist, so
    // recover from the official cart instead of repeating add, remove or purchase.
    const outcomeUnknown = (error) => Boolean(error) && error.name !== 'AbortError' && !serverAnswered(error);
    const cancelQueued = (job) => {
        if (isQuantity(job.options)) deferredQuantityCancellations.push(() => job.reject(cancelled()));
        else job.reject(cancelled());
    };
    const recordFailure = (job, error, requests = operations(job.options)) => {
        requests.forEach((request) => {
            if (!/\/cart\/update-item$/.test(path(request))) return;
            const key = (request.data || request.body)?.key;
            if (operations(job.options).length > 1 && desires.has(key) && desires.get(key) !== Number((request.data || request.body)?.quantity)) return;
            const accepted = error.data?.cart?.items?.find((item) => item.key === key)?.quantity;
            if (Number.isInteger(accepted) && accepted > 0) desires.set(key, accepted);
            else if (error.data?.status === 400 && ['invalid_quantity', 'readonly_quantity'].includes(error.code)) {
                // Real WC quantity-limit errors have no cart snapshot. A prior
                // discarded write may have succeeded, so reread the full cart.
                const lastConfirmed = window.wp.data.select('wc/store/cart').getCartItem(key)?.quantity;
                if (Number.isInteger(lastConfirmed)) desires.set(key, lastConfirmed);
                rechecking.add(key);
                revalidationRequired = true;
            }
            else failures.add(key);
        });
    };
    const settle = () => {
        if (!active && !queue.length && revalidationRequired && !revalidationPending) {
            revalidationRequired = false;
            revalidationPending = true;
            const keys = [...rechecking];
            const uncertainKeys = [...uncertain];
            const jobs = lostResponses.splice(0);
            const dispatch = window.wp.data.dispatch('wc/store/cart');
            void dispatch.syncCartWithIAPIStore({}).then(() => {
                const cart = window.wp.data.select('wc/store/cart');
                for (const key of keys) {
                    if (!rechecking.has(key)) continue;
                    const quantity = cart.getCartItem(key)?.quantity;
                    if (Number.isInteger(quantity)) desires.set(key, quantity);
                    else desires.delete(key);
                    rechecking.delete(key);
                    failures.delete(key);
                }
                for (const key of uncertainKeys) {
                    if (!uncertain.has(key)) continue;
                    const official = cart.getCartItem(key)?.quantity;
                    uncertain.delete(key);
                    if (Number.isInteger(official) && official === desires.get(key)) failures.delete(key);
                    else if (desires.has(key)) failures.add(key);
                }
                const snapshot = cart.getCartData?.();
                jobs.forEach((job) => job.resolve(snapshot));
            }).catch((error) => {
                if (error.name === 'AbortError') {
                    jobs.forEach((job) => lostResponses.push(job));
                    revalidationRequired = keys.some((key) => rechecking.has(key)) || uncertainKeys.some((key) => uncertain.has(key)) || lostResponses.length > 0;
                } else {
                    keys.filter((key) => rechecking.has(key)).forEach((key) => failures.add(key));
                    uncertainKeys.forEach((key) => { uncertain.delete(key); if (desires.has(key)) failures.add(key); });
                    jobs.forEach((job) => job.reject(job.lostError || error));
                    if (keys.length) dispatch.receiveError(error);
                }
            }).finally(() => { revalidationPending = false; setTimeout(settle, 0); });
        }
        if (!active && !queue.length && !revalidationPending) deferredQuantityCancellations.splice(0).forEach((reject) => reject());
        notify();
        void pump();
    };
    const pump = async () => {
        if (active || !queue.length) return;
        const job = queue.shift();
        active = job;
        notify();
        try {
            if (job.stale || job.options.signal?.aborted) throw cancelled();
            // Native quantity actions abort their old signal. Keep the physical
            // write alive until it finishes so a later write cannot overtake it.
            const response = await job.next({ ...job.options, signal: undefined });
            if (response instanceof Response) await response.clone().arrayBuffer();
            let failed = response instanceof Response && !response.ok;
            if (failed && !job.stale && !job.options.signal?.aborted) {
                // WC quantity requests use parse:false. Keep their original
                // Response for the native consumer while observing its error.
                const error = await response.clone().json().catch(() => ({}));
                recordFailure(job, error);
            }
            if (/\/wc\/store\/v1\/batch$/.test(path(job.options)) && !job.stale && !job.options.signal?.aborted) {
                // A successful batch transport can contain failed individual
                // writes. Observe each operation without changing its payload.
                const batch = response instanceof Response ? await response.clone().json().catch(() => ({})) : response;
                (batch?.responses || []).forEach((entry, index) => {
                    if (entry.status < 400) return;
                    failed = true;
                    const request = operations(job.options)[index];
                    if (request) recordFailure(job, entry.body || {}, [request]);
                });
            }
            if (job.stale || job.options.signal?.aborted || (job.discard && !(failed && isQuantity(job.options)))) throw cancelled();
            job.resolve(response);
        } catch (error) {
            if (error instanceof Response) await error.clone().arrayBuffer().catch(() => {});
            // A newer edit of another line makes a successful snapshot old,
            // but must not hide this line's current stock/nonce/network error.
            const obsolete = job.stale || job.options.signal?.aborted || (!isQuantity(job.options) && job.discard);
            if (!obsolete && error.name !== 'AbortError' && !outcomeUnknown(error) && isQuantity(job.options)) {
                // apiFetch(parse:false) rejects non-2xx with a raw Response.
                // Read a clone so Woo can still parse its original error body.
                const detail = error instanceof Response ? await error.clone().json().catch(() => ({})) : error;
                recordFailure(job, detail);
            }
            if (!obsolete && outcomeUnknown(error) && !isCartRead(job.options)) {
                job.lostError = error;
                if (isQuantity(job.options)) operations(job.options).forEach((request) => {
                    const body = request.data || request.body;
                    const key = body?.key;
                    if (!key) return;
                    const quantity = Number(body.quantity);
                    if (!desires.has(key) && Number.isInteger(quantity) && quantity > 0) desires.set(key, quantity);
                    uncertain.add(key);
                });
                lostResponses.push(job);
                revalidationRequired = true;
            } else if ((obsolete || (error.name === 'AbortError' && job.discard)) && isQuantity(job.options) && queue.length) {
                // Keep the native quantity hook pending until the latest full
                // result is applied; otherwise it resets the input to old data.
                deferredQuantityCancellations.push(() => job.reject(cancelled()));
            } else job.reject(obsolete ? cancelled() : error);
        }
        finally {
            active = null;
            // Let native consumers apply the result/nonce before the next call.
            setTimeout(settle, 0);
        }
    };
    window.wp.apiFetch.use((options, next) => {
        if (!isCart(options)) return next(options);
        // WP retries the original options after refreshing an expired nonce.
        // An intervening intent must prevent that old write from returning.
        const requestIdentity = identity(options);
        if (revisions.has(options) && requestIdentity && (!latestRevisions.has(requestIdentity)
            || revisions.get(options) < latestRevisions.get(requestIdentity))) return Promise.reject(cancelled());
        if (isQuantity(options)) operations(options).forEach((request) => failures.delete((request.data || request.body)?.key));
        if (!revisions.has(options)) {
            revisions.set(options, ++revision);
            if (requestIdentity) latestRevisions.set(requestIdentity, revision);
        }
        for (const job of [active, ...queue]) if (job && replaceable(job.options)) job.discard = true;
        return new Promise((resolve, reject) => {
            const job = { options, next, resolve, reject, stale: false };
            queue.push(job);
            if (requestIdentity) {
                if (active && identity(active.options) === requestIdentity) active.stale = true;
                for (const queued of queue.slice(0, -1)) {
                    if (identity(queued.options) !== requestIdentity) continue;
                    queue.splice(queue.indexOf(queued), 1);
                    cancelQueued(queued);
                }
            }
            notify();
            void pump();
        });
    });
    window.petshopCartRequestCoordinator = {
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        pending,
        desiredQuantities: () => new Map(desires),
        quantityFailed: (key) => failures.has(key),
        quantityRechecking: (key) => rechecking.has(key),
        retryQuantityCheck() { revalidationRequired = true; settle(); },
        clearQuantity(key) {
            if (!desires.has(key)) return;
            desires.delete(key);
            failures.delete(key);
            rechecking.delete(key);
            if (![active, ...queue].some((job) => job && quantityFor(job, key))) latestRevisions.delete(`quantity:${key}`);
            notify();
        },
        invalidateQuote() {
            for (const job of [active, ...queue]) if (job && isQuote(job.options)) job.stale = true;
        },
        noteQuantity(key, quantity) {
            if (!key || !Number.isInteger(quantity) || quantity < 1) return;
            desires.set(key, quantity);
            failures.delete(key);
            rechecking.delete(key);
            notify();
            const jobs = [active, ...queue].filter(Boolean);
            let superseded = false;
            for (const job of jobs) {
                const request = quantityFor(job, key);
                if (request && Number((request.data || request.body).quantity) !== quantity) {
                    // A batch may contain another line: execute it intact, then
                    // let the later single-line request supersede this value.
                    if (operations(job.options).length === 1) job.stale = true;
                    superseded = true;
                }
            }
            // WC returns before aborting when the desired quantity equals its
            // still-old store value. Send that otherwise-lost intent through the
            // official endpoint; apply only its complete, current response.
            const item = window.wp.data.select('wc/store/cart').getCartItem(key);
            const returningToDiscardedIntent = jobs.some((job) => {
                const request = quantityFor(job, key);
                return request && job.stale && Number((request.data || request.body).quantity) === quantity;
            });
            if (!superseded || (item?.quantity !== quantity && !returningToDiscardedIntent) || jobs.some((job) => {
                const request = quantityFor(job, key);
                return request && !job.stale && Number((request.data || request.body).quantity) === quantity;
            })) return;
            const dispatch = window.wp.data.dispatch('wc/store/cart');
            void window.wp.apiFetch({ url: window.petshopCartOperationsConfig.cartUpdateItemUrl, method: 'POST', data: { key, quantity } })
                .then(() => dispatch.syncCartWithIAPIStore({}))
                .catch((error) => { if (error.name !== 'AbortError') dispatch.receiveError(error); });
        },
    };
})();
