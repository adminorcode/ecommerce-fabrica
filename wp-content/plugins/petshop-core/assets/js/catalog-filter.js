(() => {
  const panel = document.querySelector('#petshop-catalog-filter-panel');
  const form = panel?.querySelector('.petshop-catalog-filter');
  if (!panel || !form) return;
  document.documentElement.classList.add('petshop-catalog-filter-enhanced');

  const openButton = document.querySelector('[data-petshop-filter-open]');
  const closeButton = panel.querySelector('[data-petshop-filter-close]');
  const backdrop = document.querySelector('[data-petshop-filter-backdrop]');
  const search = form.querySelector('#petshop-category-search');
  const options = [...form.querySelectorAll('#petshop-category-options > li')];
  const status = form.querySelector('[data-petshop-category-status]');
  let returnFocus = null;

  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
  const focusable = () => [...panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled])')]
    .filter((element) => !element.hidden && !element.closest('[hidden]') && element.offsetParent !== null);
  const close = () => {
    panel.classList.remove('is-open');
    panel.setAttribute('aria-modal', 'false');
    backdrop?.setAttribute('hidden', '');
    document.documentElement.classList.remove('petshop-filter-open');
    openButton?.setAttribute('aria-expanded', 'false');
    returnFocus?.focus();
  };
  const open = () => {
    returnFocus = document.activeElement;
    panel.classList.add('is-open');
    panel.setAttribute('aria-modal', 'true');
    backdrop?.removeAttribute('hidden');
    document.documentElement.classList.add('petshop-filter-open');
    openButton?.setAttribute('aria-expanded', 'true');
    const focusPanel = () => {
      if (panel.classList.contains('is-open')) panel.focus({ preventScroll: true });
    };
    panel.addEventListener('transitionend', focusPanel, { once: true });
    window.setTimeout(focusPanel, 220);
  };

  openButton?.addEventListener('click', open);
  closeButton?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);
  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
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
      option.hidden = !matches;
      if (matches) visible += 1;
    });
    if (status) status.textContent = `${visible} categoria${visible === 1 ? '' : 's'} encontrada${visible === 1 ? '' : 's'}.`;
  });
})();
