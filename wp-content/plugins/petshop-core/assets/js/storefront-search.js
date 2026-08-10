(() => {
  const config = window.petshopSearchConfig;
  if (!config?.endpoint) return;

  document.querySelectorAll('form.woocommerce-product-search').forEach((form, formIndex) => {
    const input = form.querySelector('input[type="search"]');
    if (!input) return;

    const list = document.createElement('div');
    const listId = `petshop-search-suggestions-${formIndex}`;
    list.id = listId;
    list.className = 'petshop-search-suggestions';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', config.resultsLabel);
    list.hidden = true;
    form.append(list);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');

    let timer = 0;
    let controller = null;
    let activeIndex = -1;

    const close = () => {
      list.hidden = true;
      list.replaceChildren();
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    };

    const setActive = (nextIndex) => {
      const options = [...list.querySelectorAll('[role="option"]')];
      if (!options.length) return;
      activeIndex = (nextIndex + options.length) % options.length;
      options.forEach((option, index) => option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false'));
      input.setAttribute('aria-activedescendant', options[activeIndex].id);
    };

    const render = (products) => {
      list.replaceChildren();
      if (!products.length) {
        const empty = document.createElement('p');
        empty.className = 'petshop-search-suggestions__empty';
        empty.textContent = config.noResults;
        list.append(empty);
      } else {
        products.forEach((product, index) => {
          const link = document.createElement('a');
          link.id = `${listId}-option-${index}`;
          link.className = 'petshop-search-suggestions__option';
          link.href = product.permalink;
          link.setAttribute('role', 'option');
          link.setAttribute('aria-selected', 'false');

          if (product.images?.[0]?.thumbnail) {
            const image = document.createElement('img');
            image.src = product.images[0].thumbnail;
            image.alt = product.images[0].alt || '';
            image.width = 48;
            image.height = 48;
            link.append(image);
          }
          const name = document.createElement('span');
          name.textContent = product.name;
          link.append(name);
          list.append(link);
        });
      }
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      controller?.abort();
      const query = input.value.trim();
      if (query.length < Number(config.minimumCharacters || 2)) {
        close();
        return;
      }
      timer = window.setTimeout(async () => {
        controller = new AbortController();
        try {
          const endpoint = new URL(config.endpoint, window.location.origin);
          endpoint.searchParams.set('search', query);
          endpoint.searchParams.set('per_page', '5');
          const response = await fetch(endpoint, { signal: controller.signal, credentials: 'same-origin' });
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          render(await response.json());
        } catch (error) {
          if (error.name !== 'AbortError') close();
        }
      }, 250);
    });

    input.addEventListener('keydown', (event) => {
      if (list.hidden) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        const options = [...list.querySelectorAll('[role="option"]')];
        if (options[activeIndex]) {
          event.preventDefault();
          window.location.assign(options[activeIndex].href);
        }
      } else if (event.key === 'Escape') {
        close();
      }
    });

    document.addEventListener('click', (event) => {
      if (!form.contains(event.target)) close();
    });
  });
})();
