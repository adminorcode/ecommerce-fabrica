(() => {
  const rawFetch = window.fetch.bind(window);
  const intendedQty = new Map();
  const intendedAt = new Map();
  const shippingDebounceMs = Number(window.petshopCartQtyConfig?.shippingDebounceMs) || 1000;
  let heldMutations = [];
  let lastFlushResponse = null;
  let quietUntil = 0;

  const live = (key) => {
    const id = String(key || '');
    const wanted = intendedQty.get(id);
    if (!wanted) return null;
    if (Date.now() - (intendedAt.get(id) || 0) > 12000) return null;
    return wanted;
  };

  const lastChangeAt = () => Math.max(0, ...intendedAt.values(), 0);

  const remainingDebounce = () => {
    const last = lastChangeAt();
    if (!last) return 0;
    return Math.max(0, shippingDebounceMs - (Date.now() - last));
  };

  const set = (key, quantity) => {
    if (key == null || !Number.isFinite(quantity) || quantity < 0) return;
    intendedQty.set(String(key), quantity);
    intendedAt.set(String(key), Date.now());
  };

  const merge = (cart) => {
    if (!cart || !Array.isArray(cart.items) || intendedQty.size === 0) return cart;
    let changed = false;
    const items = cart.items.map((item) => {
      const wanted = live(item.key);
      if (!wanted || Number(item.quantity) === wanted) return item;
      changed = true;
      return { ...item, quantity: wanted };
    });
    return changed ? { ...cart, items } : cart;
  };

  const parseJsonBody = (body) => {
    if (!body) return null;
    if (typeof body === 'string') {
      try {
        return JSON.parse(body);
      } catch (_error) {
        return null;
      }
    }
    if (typeof body === 'object' && !(body instanceof FormData)) return body;
    return null;
  };

  const requestUrl = (resource) => {
    if (typeof resource === 'string') return resource;
    if (resource && typeof resource.url === 'string') return resource.url;
    return '';
  };

  const isImmediate = (config = {}) => {
    const headers = config.headers;
    if (!headers) return false;
    if (typeof headers.get === 'function') {
      return headers.get('X-Petshop-Qty-Flush') === '1';
    }
    return String(headers['X-Petshop-Qty-Flush'] || headers['x-petshop-qty-flush'] || '') === '1';
  };

  const mutationKindsFromPath = (path) => {
    const value = String(path || '');
    const kinds = new Set();
    if (/cart\/update-item/.test(value)) kinds.add('update-item');
    if (/cart\/update-customer/.test(value)) kinds.add('update-customer');
    if (/cart\/select-shipping-rate/.test(value)) kinds.add('select-shipping-rate');
    return kinds;
  };

  const mutationKinds = (url, config) => {
    const kinds = mutationKindsFromPath(url);
    if (!/\/wc\/store\/v1\/batch/.test(url)) return kinds;

    const payload = parseJsonBody(config?.body);
    (payload?.requests || []).forEach((request) => {
      mutationKindsFromPath(request.path || request.url).forEach((kind) => kinds.add(kind));
    });
    return kinds;
  };

  const shouldHoldBeforeFlush = (kinds) => kinds.size > 0 && remainingDebounce() > 0;
  const shouldSuppressAfterFlush = (kinds) => kinds.has('update-customer') && Date.now() < quietUntil;

  const maybeRewriteResponse = async (response) => {
    if (intendedQty.size === 0) return response;
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) return response;
    try {
      const data = await response.clone().json();
      if (!data) return response;
      let rewritten = null;
      if (Array.isArray(data.items)) {
        const merged = merge(data);
        rewritten = merged === data ? null : merged;
      }
      if (!rewritten) return response;
      return new Response(JSON.stringify(rewritten), {
        status: response.status,
        statusText: response.statusText,
        headers: response.headers,
      });
    } catch (_error) {
      return response;
    }
  };

  const beginFlush = () => {
    quietUntil = Date.now() + 15000;
  };

  const resolveHeld = (response) => {
    try {
      lastFlushResponse = response.clone();
    } catch (_error) {
      lastFlushResponse = response;
    }
    quietUntil = Date.now() + 8000;
    const jobs = heldMutations;
    heldMutations = [];
    jobs.forEach((job) => {
      window.clearTimeout(job.timer);
      try {
        job.resolve(response.clone());
      } catch (_error) {
        job.resolve(response);
      }
    });
  };

  const rejectHeld = (error) => {
    const jobs = heldMutations;
    heldMutations = [];
    jobs.forEach((job) => {
      window.clearTimeout(job.timer);
      job.reject(error);
    });
  };

  const holdMutation = () => new Promise((resolve, reject) => {
    const timer = window.setTimeout(() => {
      heldMutations = heldMutations.filter((job) => job.resolve !== resolve);
      reject(new Error('Petshop cart quantity mutation timed out.'));
    }, 15000);
    heldMutations.push({ resolve, reject, timer });
  });

  const suppressWithLastFlush = () => {
    if (!lastFlushResponse) return null;
    try {
      return lastFlushResponse.clone();
    } catch (_error) {
      return lastFlushResponse;
    }
  };

  window.fetch = async (resource, config = {}) => {
    const url = requestUrl(resource);

    if (isImmediate(config)) {
      const response = await maybeRewriteResponse(await rawFetch(resource, config));
      resolveHeld(response);
      return response;
    }

    const kinds = mutationKinds(url, config);
    if (shouldHoldBeforeFlush(kinds) || shouldSuppressAfterFlush(kinds)) {
      if (shouldSuppressAfterFlush(kinds)) {
        const replayed = suppressWithLastFlush();
        if (replayed) return replayed;
      }
      return holdMutation();
    }

    const response = await maybeRewriteResponse(await rawFetch(resource, config));
    if (/\/wc\/store\/v1\/cart/.test(url)) {
      const nonce = response.headers.get('Nonce');
      if (nonce) window.petshopCartQty.lastNonce = nonce;
    }
    return response;
  };

  window.petshopCartQty = {
    intendedQty,
    intendedAt,
    live,
    set,
    merge,
    rawFetch,
    shippingDebounceMs,
    remainingDebounce,
    beginFlush,
    settleHeld: resolveHeld,
    rejectHeld,
    lastNonce: '',
  };
})();
