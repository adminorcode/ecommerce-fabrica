(() => {
  const grid = document.querySelector('.petshop-category-grid');
  if (!grid) return;

  const cards = [...grid.querySelectorAll('[data-petshop-category-preview]')];
  if (!cards.length) return;

  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  let closeTimer = 0;
  let openCard = null;

  const previewOf = (card) => card.querySelector('.petshop-category-preview');
  const triggerOf = (card) => card.querySelector('.petshop-category-card__trigger');

  const clearCloseTimer = () => {
    if (closeTimer) {
      window.clearTimeout(closeTimer);
      closeTimer = 0;
    }
  };

  const placePreview = (card) => {
    const preview = previewOf(card);
    if (!preview) return;

    preview.classList.remove('is-flush-left', 'is-flush-right');
    preview.style.removeProperty('--petshop-preview-shift');

    const rect = preview.getBoundingClientRect();
    const pad = 12;
    if (rect.left < pad) {
      preview.classList.add('is-flush-left');
    } else if (rect.right > window.innerWidth - pad) {
      preview.classList.add('is-flush-right');
    }
  };

  const close = (card) => {
    if (!card) return;
    const preview = previewOf(card);
    const trigger = triggerOf(card);
    card.classList.remove('is-preview-open');
    if (preview) {
      preview.hidden = true;
      preview.setAttribute('aria-hidden', 'true');
      preview.classList.remove('is-flush-left', 'is-flush-right');
    }
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (openCard === card) openCard = null;
  };

  const closeAll = (except = null) => {
    for (const card of cards) {
      if (card !== except) close(card);
    }
  };

  const open = (card) => {
    clearCloseTimer();
    const preview = previewOf(card);
    if (!preview) return;

    closeAll(card);
    const trigger = triggerOf(card);
    preview.hidden = false;
    preview.setAttribute('aria-hidden', 'false');
    if (trigger) trigger.setAttribute('aria-expanded', 'true');
    openCard = card;

    requestAnimationFrame(() => {
      card.classList.add('is-preview-open');
      placePreview(card);
    });
  };

  const scheduleClose = (card) => {
    clearCloseTimer();
    closeTimer = window.setTimeout(() => close(card), 160);
  };

  for (const card of cards) {
    if (!previewOf(card)) continue;

    card.addEventListener('mouseenter', () => {
      if (!finePointer.matches) return;
      open(card);
    });
    card.addEventListener('mouseleave', () => {
      if (!finePointer.matches) return;
      scheduleClose(card);
    });

    card.addEventListener('focusin', () => open(card));
    card.addEventListener('focusout', (event) => {
      if (card.contains(event.relatedTarget)) return;
      scheduleClose(card);
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !openCard) return;
    const trigger = triggerOf(openCard);
    close(openCard);
    trigger?.focus();
  });

  window.addEventListener('resize', () => {
    if (openCard) placePreview(openCard);
  });
})();
