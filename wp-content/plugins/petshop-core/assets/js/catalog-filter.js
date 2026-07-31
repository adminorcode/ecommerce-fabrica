(() => {
  const form = document.querySelector('.petshop-catalog-filter');
  if (!form) return;

  const search = form.querySelector('#petshop-category-search');
  const options = [...form.querySelectorAll('#petshop-category-options > li')];
  const status = form.querySelector('[data-petshop-category-status]');
  const normalize = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('pt-BR')
    .trim();

  const navigate = () => {
    const url = new URL(form.action, window.location.origin);
    const current = new URLSearchParams(window.location.search);
    const selected = [...form.querySelectorAll('input[name="petshop_categories[]"]:checked')]
      .map((input) => input.value);

    for (const [key, value] of current.entries()) {
      if (!['petshop_categories', 'petshop_categories[]', 'paged', 'product-page', 'add-to-cart'].includes(key)) {
        url.searchParams.append(key, value);
      }
    }
    if (selected.length) url.searchParams.set('petshop_categories', selected.join(','));
    window.location.assign(url.toString());
  };

  search?.addEventListener('input', () => {
    const query = normalize(search.value);
    let visible = 0;
    for (const option of options) {
      const matches = normalize(option.querySelector('.petshop-catalog-filter__name')?.textContent || '').includes(query);
      option.hidden = !matches;
      if (matches) visible += 1;
    }
    if (status) status.textContent = `${visible} categoria${visible === 1 ? '' : 's'} encontrada${visible === 1 ? '' : 's'}.`;
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    navigate();
  });
})();
