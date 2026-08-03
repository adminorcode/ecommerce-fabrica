(function () {
  const config = window.petshopWishlist;
  if (!config) {
    return;
  }

  const storageKey = 'petshop_wishlist';

  const readLocal = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
      return Array.isArray(parsed) ? parsed.map((id) => Number(id)).filter(Boolean) : [];
    } catch (error) {
      return [];
    }
  };

  const writeLocal = (ids) => {
    window.localStorage.setItem(storageKey, JSON.stringify(ids));
  };

  const activeIds = () => {
    const local = readLocal();
    const server = Array.isArray(config.productIds) ? config.productIds.map((id) => Number(id)).filter(Boolean) : [];
    return Array.from(new Set([...server, ...local]));
  };

  const syncButton = (button, isActive) => {
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    button.setAttribute('aria-label', isActive ? config.labels.remove : config.labels.add);
  };

  const syncAllButtons = () => {
    const ids = activeIds();
    document.querySelectorAll('.petshop-wishlist-toggle[data-product-id]').forEach((button) => {
      const productId = Number(button.getAttribute('data-product-id'));
      syncButton(button, ids.includes(productId));
    });
  };

  const toggleLocal = (productId) => {
    const ids = readLocal();
    const index = ids.indexOf(productId);
    if (index >= 0) {
      ids.splice(index, 1);
    } else {
      ids.push(productId);
    }
    writeLocal(ids);
    return ids;
  };

  const postWishlist = (action, payload) => {
    const body = new URLSearchParams({
      action,
      nonce: config.nonce,
      ...payload,
    });

    return window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
    }).then((response) => response.json());
  };

  const renderWishlistPage = () => {
    const container = document.querySelector('.petshop-wishlist-page, .petshop-wishlist-account');
    if (!container) {
      return;
    }

    const target = container.querySelector('.petshop-wishlist-page__products');
    if (!target) {
      return;
    }

    const ids = activeIds();
    if (ids.length === 0) {
      return;
    }

    postWishlist('petshop_render_wishlist', { ids: ids.join(',') })
      .then((payload) => {
        if (!payload || !payload.success || typeof payload.data?.html !== 'string') {
          throw new Error('wishlist render failed');
        }

        if (payload.data.html.trim() === '') {
          return;
        }

        target.innerHTML = payload.data.html;
        syncAllButtons();
      })
      .catch(() => {
        /* keep server-rendered fallback */
      });
  };

  const mergeGuestWishlist = () => {
    const local = readLocal();
    if (!config.loggedIn || local.length === 0) {
      return;
    }

    postWishlist('petshop_merge_wishlist', { productIds: local.join(',') })
      .then((payload) => {
        if (!payload || !payload.success || !Array.isArray(payload.data?.productIds)) {
          throw new Error('wishlist merge failed');
        }

        writeLocal(payload.data.productIds);
        config.productIds = payload.data.productIds;
        syncAllButtons();
        renderWishlistPage();
      })
      .catch(() => {
        syncAllButtons();
      });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.petshop-wishlist-toggle');
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const productId = Number(button.getAttribute('data-product-id'));
    if (!productId) {
      return;
    }

    const ids = activeIds();
    const willActivate = !ids.includes(productId);
    toggleLocal(productId);
    syncButton(button, willActivate);

    if (!config.loggedIn) {
      return;
    }

    postWishlist('petshop_toggle_wishlist', { productId: String(productId) })
      .then((payload) => {
        if (!payload || !payload.success || !Array.isArray(payload.data?.productIds)) {
          throw new Error('wishlist sync failed');
        }

        writeLocal(payload.data.productIds);
        config.productIds = payload.data.productIds;
        syncAllButtons();
      })
      .catch(() => {
        syncAllButtons();
      });
  });

  mergeGuestWishlist();
  renderWishlistPage();
  syncAllButtons();
})();
