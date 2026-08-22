(() => {
  const panel = document.querySelector('#petshop-catalog-filter-panel');
  const form = panel?.querySelector('.petshop-catalog-filter');
  if (!panel || !form) return;

  document.documentElement.classList.add('petshop-catalog-filter-enhanced');

  const openButton = document.querySelector('[data-petshop-filter-open]');
  const closeButton = panel.querySelector('[data-petshop-filter-close]');
  const backdrop = document.querySelector('[data-petshop-filter-backdrop]');
  const search = form.querySelector('#petshop-category-search');
  const categoryList = form.querySelector('#petshop-category-options');
  const options = [...form.querySelectorAll('#petshop-category-options > li')];
  const moreButton = form.querySelector('[data-petshop-filter-more]');
  const status = form.querySelector('[data-petshop-category-status]');
  const facetButtons = [...form.querySelectorAll('.petshop-catalog-filter__facet-toggle')];
  const mediaQuery = window.matchMedia('(max-width: 1179px)');
  const collapsedLimit = 6;
  let returnFocus = null;
  let expandedCategories = false;

  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
  const focusable = () => [...panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled])')]
    .filter((element) => !element.hidden && !element.closest('[hidden]') && element.offsetParent !== null);

  const isDrawerMode = () => mediaQuery.matches;

  const close = () => {
    panel.classList.remove('is-open');
    panel.setAttribute('aria-modal', 'false');
    backdrop?.setAttribute('hidden', '');
    document.documentElement.classList.remove('petshop-filter-open');
    document.body.classList.remove('petshop-filter-open');
    openButton?.setAttribute('aria-expanded', 'false');
    returnFocus?.focus({ preventScroll: true });
    window.setTimeout(() => returnFocus?.focus({ preventScroll: true }), 0);
  };

  const open = () => {
    returnFocus = document.activeElement;
    panel.classList.add('is-open');
    panel.setAttribute('aria-modal', isDrawerMode() ? 'true' : 'false');
    backdrop?.removeAttribute('hidden');
    document.documentElement.classList.add('petshop-filter-open');
    document.body.classList.add('petshop-filter-open');
    openButton?.setAttribute('aria-expanded', 'true');
    const focusPanel = () => {
      if (panel.classList.contains('is-open')) panel.focus({ preventScroll: true });
    };
    panel.addEventListener('transitionend', focusPanel, { once: true });
    window.setTimeout(focusPanel, 220);
  };

  const setFacetOpen = (button, openFacet) => {
    const panelId = button.getAttribute('aria-controls');
    const facetPanel = panelId ? document.getElementById(panelId) : null;
    button.setAttribute('aria-expanded', openFacet ? 'true' : 'false');
    if (facetPanel) facetPanel.hidden = !openFacet;
  };

  const visibleCategoryOptions = () => options.filter((option) => option.dataset.petshopSearchHidden !== 'true');

  const syncCategoryLimit = () => {
    if (!options.length) return;
    const visibleOptions = visibleCategoryOptions();
    const query = search?.value.trim() || '';
    const shouldCollapse = !expandedCategories && query === '';
    options.forEach((option) => {
      const searchHidden = option.dataset.petshopSearchHidden === 'true';
      const visibleIndex = visibleOptions.indexOf(option);
      option.hidden = searchHidden || (shouldCollapse && visibleIndex >= collapsedLimit);
    });
    if (!moreButton) return;
    const needsToggle = visibleOptions.length > collapsedLimit && query === '';
    moreButton.hidden = !needsToggle;
    moreButton.textContent = expandedCategories ? moreButton.dataset.lessLabel : moreButton.dataset.moreLabel;
    moreButton.setAttribute('aria-expanded', expandedCategories ? 'true' : 'false');
  };

  facetButtons.forEach((button) => {
    button.addEventListener('click', () => {
      setFacetOpen(button, button.getAttribute('aria-expanded') !== 'true');
    });
    button.addEventListener('keydown', (event) => {
      if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
      event.preventDefault();
      const index = facetButtons.indexOf(button);
      const next = event.key === 'ArrowDown'
        ? facetButtons[(index + 1) % facetButtons.length]
        : facetButtons[(index - 1 + facetButtons.length) % facetButtons.length];
      next?.focus();
    });
  });

  moreButton?.addEventListener('click', () => {
    expandedCategories = !expandedCategories;
    syncCategoryLimit();
  });

  openButton?.addEventListener('click', open);
  closeButton?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);

  form.addEventListener('submit', () => {
    form.querySelectorAll('input,select').forEach((control) => {
      if (!control.value) control.disabled = true;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && panel.classList.contains('is-open')) close();
  });

  panel.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !panel.classList.contains('is-open')) return;
    const items = focusable();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  search?.addEventListener('input', () => {
    const query = normalize(search.value);
    let visible = 0;
    options.forEach((option) => {
      const matches = normalize(option.querySelector('.petshop-catalog-filter__name')?.textContent || '').includes(query);
      option.dataset.petshopSearchHidden = matches ? 'false' : 'true';
      if (matches) visible += 1;
    });
    expandedCategories = query !== '';
    syncCategoryLimit();
    if (status) status.textContent = `${visible} categoria${visible === 1 ? '' : 's'} encontrada${visible === 1 ? '' : 's'}.`;
  });

  mediaQuery.addEventListener('change', () => {
    if (!isDrawerMode()) close();
  });

  categoryList?.setAttribute('data-petshop-collapsible-list', 'true');
  syncCategoryLimit();
})();
