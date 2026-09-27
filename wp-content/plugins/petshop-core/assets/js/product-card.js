(() => {
  'use strict';

  const config = window.petshopProductCardConfig || {};
  const i18n = config.i18n || {};
  const resolvedVariableCards = new WeakMap();

  const parseVariations = (root) => {
    try {
      return JSON.parse(root.dataset.variations || '[]');
    } catch (_error) {
      return [];
    }
  };

  const getCard = (element) => element.closest('li.product, .product');

  const getSelections = (root) => {
    const selections = {};
    root.querySelectorAll('[data-petshop-attribute-group]').forEach((group) => {
      const key = group.dataset.petshopAttributeGroup || '';
      const selected = group.querySelector('[data-petshop-attribute][aria-pressed="true"]');
      selections[key] = selected ? selected.dataset.value || '' : '';
    });
    return selections;
  };

  const isComplete = (root, selections) => {
    return Array.from(root.querySelectorAll('[data-petshop-attribute-group]')).every((group) => {
      const key = group.dataset.petshopAttributeGroup || '';
      return Boolean(selections[key]);
    });
  };

  const variationMatches = (variation, selections) => {
    const attributes = variation.attributes || {};

    return Object.entries(selections).every(([key, selected]) => {
      if (!selected) {
        return true;
      }

      const expected = attributes[key] ?? '';
      return expected === '' || expected === selected;
    });
  };

  const findVariation = (variations, selections) => {
    const matches = variations.filter((variation) => variationMatches(variation, selections));

    return matches.reduce((best, variation) => {
      if (!best) {
        return variation;
      }

      const score = (candidate) => Object.entries(selections).reduce((total, [key, selected]) => {
        if (!selected) {
          return total;
        }

        return total + ((candidate.attributes?.[key] ?? '') === selected ? 1 : 0);
      }, 0);

      return score(variation) > score(best) ? variation : best;
    }, null);
  };

  const findInitialVariation = (root, variations, selections) => {
    const initialVariationId = Number(root.dataset.initialVariationId || 0);
    if (!initialVariationId || root.dataset.petshopSelectionChanged === '1') {
      return null;
    }

    const variation = variations.find((candidate) => Number(candidate.id) === initialVariationId);
    return variation && variationMatches(variation, selections) ? variation : null;
  };

  const resolveVariation = (root, variations, selections) => {
    return findInitialVariation(root, variations, selections) || findVariation(variations, selections);
  };

  const variationPayload = (selections) => {
    return Object.entries(selections)
      .filter(([, value]) => Boolean(value))
      .map(([attribute, value]) => ({ attribute, value }));
  };

  const rememberResolvedVariation = (root, variation, selections) => {
    if (!variation) {
      resolvedVariableCards.delete(root);
      return null;
    }

    const resolved = {
      variation,
      cartItem: {
        id: Number(variation.id),
        variation: variationPayload(selections),
      },
    };
    resolvedVariableCards.set(root, resolved);

    return resolved;
  };

  const setStatus = (root, message) => {
    const status = root.querySelector('[data-petshop-card-status]');
    if (status) {
      status.textContent = message || '';
    }
  };

  const getImage = (card) => {
    if (!card) {
      return null;
    }

    return card.querySelector(
      '.ct-image-container img, img.wp-post-image, .woocommerce-loop-product__link img, img'
    );
  };

  const rememberOriginalImage = (image) => {
    if (!image || image.dataset.petshopOriginalSrc) {
      return;
    }

    image.dataset.petshopOriginalSrc = image.getAttribute('src') || '';
    image.dataset.petshopOriginalSrcset = image.getAttribute('srcset') || '';
  };

  const updateImage = (card, variation) => {
    const image = getImage(card);
    if (!image) {
      return;
    }

    rememberOriginalImage(image);

    if (variation && variation.image) {
      image.setAttribute('src', variation.image);
      image.removeAttribute('srcset');
      return;
    }

    if (image.dataset.petshopOriginalSrc) {
      image.setAttribute('src', image.dataset.petshopOriginalSrc);
    }

    if (image.dataset.petshopOriginalSrcset) {
      image.setAttribute('srcset', image.dataset.petshopOriginalSrcset);
    } else {
      image.removeAttribute('srcset');
    }
  };

  const updatePrice = (card, variation) => {
    if (!card || !variation || !variation.priceHtml) {
      return;
    }

    const price = card.querySelector('[data-petshop-card-price]');
    if (price) {
      price.innerHTML = variation.priceHtml;
    }
  };

  const setBuyButtonState = (card, enabled) => {
    if (!card) {
      return;
    }

    const button = card.querySelector('[data-petshop-buy-now][data-product-type="variable"]');
    if (!button) {
      return;
    }

    button.disabled = false;
    button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    button.classList.toggle('is-disabled', !enabled);
  };

  const focusPendingAttribute = (root, selections) => {
    const groups = Array.from(root.querySelectorAll('[data-petshop-attribute-group]'));
    const pending = groups.find((group) => {
      const key = group.dataset.petshopAttributeGroup || '';
      return !selections[key];
    });

    const targetGroup = pending || groups[0];
    const target = targetGroup?.querySelector('[data-petshop-attribute]');
    if (target) {
      window.setTimeout(() => {
        target.focus({ preventScroll: true });
      }, 0);
    }
  };

  const syncVariableCard = (root, shouldFocus = false) => {
    const variations = parseVariations(root);
    const selections = getSelections(root);
    const card = getCard(root);

    if (!isComplete(root, selections)) {
      setBuyButtonState(card, false);
      setStatus(root, i18n.selectOption || 'Selecione uma opção disponível.');
      resolvedVariableCards.delete(root);
      if (shouldFocus) {
        focusPendingAttribute(root, selections);
      }
      return null;
    }

    const variation = resolveVariation(root, variations, selections);

    if (!variation) {
      setBuyButtonState(card, false);
      setStatus(root, i18n.unavailable || 'Esta combinação está indisponível.');
      resolvedVariableCards.delete(root);
      if (shouldFocus) {
        focusPendingAttribute(root, selections);
      }
      return null;
    }

    updatePrice(card, variation);
    updateImage(card, variation);

    const available = Boolean(variation.purchasable && variation.inStock);
    setBuyButtonState(card, available);
    setStatus(root, available ? '' : (i18n.unavailable || 'Esta combinação está indisponível.'));

    if (!available && shouldFocus) {
      focusPendingAttribute(root, selections);
    }

    return available ? rememberResolvedVariation(root, variation, selections) : null;
  };

  const initializeVariableCard = (root) => {
    const card = getCard(root);
    const image = getImage(card);
    rememberOriginalImage(image);
    syncVariableCard(root);
  };

  const refreshCartUis = (cart) => {
    document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', {
      bubbles: true,
      detail: {
        preserveCartData: false,
        cart,
      },
    }));

    if (window.jQuery) {
      window.jQuery(document.body).trigger('wc_fragment_refresh');
    }
  };

  const addToCart = async (button, cartItem) => {
    if (!config.endpoint) {
      throw new Error('Store API endpoint unavailable.');
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.classList.add('is-loading');
    button.textContent = i18n.adding || 'Adicionando…';

    try {
      const response = await fetch(config.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Content-Type': 'application/json',
          'Nonce': config.nonce || '',
        },
        body: JSON.stringify({
          id: Number(cartItem.id),
          quantity: 1,
          ...(cartItem.variation ? { variation: cartItem.variation } : {}),
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(payload?.message || i18n.error || 'Não foi possível adicionar ao carrinho.');
      }

      refreshCartUis(payload);
      button.classList.add('is-added');
      button.textContent = i18n.added || 'Adicionado ao carrinho.';

      window.setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('is-added');
      }, 1400);

      return payload;
    } finally {
      button.classList.remove('is-loading');
      button.disabled = false;
    }
  };

  document.addEventListener('click', async (event) => {
    const chip = event.target.closest('[data-petshop-attribute]');
    if (chip) {
      event.preventDefault();

      const root = chip.closest('[data-petshop-variable-card]');
      if (!root) {
        return;
      }

      const group = chip.closest('[data-petshop-attribute-group]');
      if (!group) {
        return;
      }

      if (chip.getAttribute('aria-pressed') !== 'true') {
        root.dataset.petshopSelectionChanged = '1';
      }

      group.querySelectorAll('[data-petshop-attribute]').forEach((candidate) => {
        const selected = candidate === chip;
        candidate.classList.toggle('is-selected', selected);
        candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });

      syncVariableCard(root);
      return;
    }

    const button = event.target.closest('[data-petshop-buy-now]');
    if (!button) {
      return;
    }

    event.preventDefault();

    if (button.disabled) {
      return;
    }

    const card = getCard(button);
    const productId = Number(button.dataset.productId || 0);
    const productType = button.dataset.productType || 'simple';

    if (!card || !productId) {
      return;
    }

    try {
      if (productType === 'variable') {
        const root = card.querySelector('[data-petshop-variable-card]');
        if (!root) {
          return;
        }

        const resolved = syncVariableCard(root, true);
        if (!resolved) {
          return;
        }

        await addToCart(button, resolvedVariableCards.get(root)?.cartItem || resolved.cartItem);
        return;
      }

      await addToCart(button, { id: productId });
    } catch (error) {
      const root = card.querySelector('[data-petshop-variable-card]');
      if (root) {
        setStatus(root, error?.message || i18n.error || 'Não foi possível adicionar ao carrinho.');
      } else {
        window.alert(error?.message || i18n.error || 'Não foi possível adicionar ao carrinho.');
      }
    }
  });

  document.querySelectorAll('[data-petshop-variable-card]').forEach(initializeVariableCard);
})();
