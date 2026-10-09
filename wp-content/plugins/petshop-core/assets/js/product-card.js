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

  const priceElement = (card) => card?.querySelector('[data-petshop-card-price]') || null;

  const updatePrice = (card, variation) => {
    const price = priceElement(card);
    if (!price) {
      return;
    }

    if (!variation || !variation.priceHtml) {
      price.textContent = '';
      return;
    }

    price.innerHTML = variation.priceHtml;
  };

  const variationSupports = (variation, key, value) => {
    const expected = variation.attributes?.[key] ?? '';
    return expected === '' || expected === value;
  };

  const optionIsPurchasable = (variations, key, value) => variations.some((variation) => {
    if (!variation.purchasable || !variation.inStock) {
      return false;
    }

    return variationSupports(variation, key, value);
  });

  const refreshChips = (root, variations) => {
    root.querySelectorAll('[data-petshop-attribute]').forEach((chip) => {
      const available = optionIsPurchasable(
        variations,
        chip.dataset.petshopAttribute || '',
        chip.dataset.value || ''
      );
      chip.disabled = !available;
      chip.setAttribute('aria-disabled', available ? 'false' : 'true');
      chip.classList.toggle('is-disabled', !available);
      if (!available && chip.getAttribute('aria-pressed') === 'true') {
        chip.setAttribute('aria-pressed', 'false');
        chip.classList.remove('is-selected');
      }
    });
  };

  const setBuyButtonState = (card, enabled) => {
    if (!card) {
      return;
    }

    const button = card.querySelector('[data-petshop-buy-now][data-product-type="variable"]');
    if (!button) {
      return;
    }

    const text = enabled
      ? (i18n.buyNow || 'Comprar agora')
      : (i18n.unavailable || 'Indisponível');
    const label = button.querySelector('.petshop-product-card__buy-label');

    button.disabled = false;
    button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    button.classList.toggle('is-unavailable', !enabled);
    button.setAttribute('aria-label', text);
    if (label) {
      label.textContent = text;
    }
  };

  const updateBadge = (card, variation, available) => {
    if (!card) {
      return;
    }

    let slot = card.querySelector('[data-petshop-card-badges]');
    if (!slot) {
      slot = document.createElement('div');
      slot.className = 'petshop-product-card__badges';
      slot.setAttribute('data-petshop-card-badges', '');
      (card.querySelector('figure') || card).prepend(slot);
    }

    const discount = available && variation && /^-\d+%$/.test(variation.discountLabel || '')
      ? variation.discountLabel
      : '';

    if (!available && variation) {
      slot.hidden = false;
      slot.replaceChildren(badgeNode('petshop-badge petshop-badge--soldout', i18n.soldOut || 'Esgotado'));
      return;
    }

    if (discount) {
      slot.hidden = false;
      slot.replaceChildren(badgeNode('petshop-badge petshop-badge--save', discount));
      return;
    }

    slot.hidden = true;
    slot.replaceChildren();
  };

  const badgeNode = (className, text) => {
    const node = document.createElement('span');
    node.className = className;
    node.textContent = text;
    return node;
  };

  const focusPendingAttribute = (root, selections) => {
    const groups = Array.from(root.querySelectorAll('[data-petshop-attribute-group]'));
    const pending = groups.find((group) => {
      const key = group.dataset.petshopAttributeGroup || '';
      return !selections[key];
    });

    const targetGroup = pending || groups[0];
    const target = targetGroup?.querySelector('[data-petshop-attribute]:not(:disabled)');
    if (target) {
      window.setTimeout(() => {
        target.focus({ preventScroll: true });
      }, 0);
    }
  };

  const syncVariableCard = (root, shouldFocus = false) => {
    const variations = parseVariations(root);
    refreshChips(root, variations);
    const selections = getSelections(root);
    const card = getCard(root);

    if (!isComplete(root, selections)) {
      updatePrice(card, null);
      updateImage(card, null);
      updateBadge(card, null, false);
      setBuyButtonState(card, false);
      setStatus(root, '');
      resolvedVariableCards.delete(root);
      if (shouldFocus) {
        focusPendingAttribute(root, selections);
      }
      return null;
    }

    const variation = resolveVariation(root, variations, selections);

    if (!variation) {
      updatePrice(card, null);
      updateImage(card, null);
      updateBadge(card, null, false);
      setBuyButtonState(card, false);
      setStatus(root, '');
      resolvedVariableCards.delete(root);
      if (shouldFocus) {
        focusPendingAttribute(root, selections);
      }
      return null;
    }

    updatePrice(card, variation);
    updateImage(card, variation);

    const available = Boolean(variation.purchasable && variation.inStock);
    updateBadge(card, variation, available);
    setBuyButtonState(card, available);
    setStatus(root, '');

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

  const addToCart = async (button, cartItem) => {
    if (!config.endpoint) {
      throw new Error('Store API endpoint unavailable.');
    }

    const label = button.querySelector('.petshop-product-card__buy-label');
    const originalText = label ? label.textContent : button.textContent;
    const setLabel = (text) => {
      if (label) {
        label.textContent = text;
        return;
      }

      button.textContent = text;
    };
    button.disabled = true;
    button.classList.add('is-loading');
    setLabel(i18n.adding || 'Adicionando…');

    try {
      const payload = await window.petshopCartOperations.addItem(cartItem);
      button.classList.add('is-added');
      setLabel(i18n.added || 'Adicionado ao carrinho.');

      window.setTimeout(() => {
        setLabel(originalText);
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

      if (chip.disabled || chip.getAttribute('aria-disabled') === 'true') {
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
