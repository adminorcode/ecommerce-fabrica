(() => {
  const header = document.querySelector('.petshop-commercial-header');
  const toggle = header?.querySelector('.petshop-commercial-header__menu-toggle');
  const panel = header?.querySelector('#petshop-commercial-menu-panel');
  const overlay = header?.querySelector('[data-petshop-menu-overlay]');
  const closeButton = header?.querySelector('.petshop-commercial-header__drawer-close');
  const menu = header?.querySelector('.petshop-commercial-menu');
  const desktopQuery = window.matchMedia('(min-width: 768px)');
  const hoverQuery = window.matchMedia('(hover: hover) and (pointer: fine)');

  if (
    !(header instanceof HTMLElement)
    || !(toggle instanceof HTMLElement)
    || !(panel instanceof HTMLElement)
    || !(menu instanceof HTMLElement)
  ) {
    return;
  }

  const parentItems = () => [...menu.querySelectorAll(':scope > .menu-item-has-children')];

  const parentLink = (item) => item.querySelector(':scope > a');

  const submenuToggle = (item) => item.querySelector(':scope > .petshop-commercial-menu__submenu-toggle');

  const usesDesktopHover = () => desktopQuery.matches && hoverQuery.matches;

  let suppressOpen = null;

  const setExpanded = (item, expanded) => {
    item.classList.toggle('is-submenu-open', expanded);
    const button = submenuToggle(item);
    const link = parentLink(item);
    if (button instanceof HTMLElement) {
      button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      const label = expanded
        ? button.getAttribute('data-close-label')
        : button.getAttribute('data-open-label');
      if (label) {
        button.setAttribute('aria-label', label);
      }
    }
    if (link instanceof HTMLElement && link.hasAttribute('aria-expanded')) {
      link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  };

  const closeSubmenus = (except = null) => {
    parentItems().forEach((item) => {
      if (item !== except) {
        setExpanded(item, false);
      }
    });
  };

  const openSubmenu = (item) => {
    if (suppressOpen === item) {
      return;
    }
    closeSubmenus(item);
    setExpanded(item, true);
  };

  const closeDrawer = () => {
    toggle.setAttribute('aria-expanded', 'false');
    header.classList.remove('is-menu-open');
    document.documentElement.classList.remove('petshop-menu-drawer-open');
    if (!desktopQuery.matches) {
      panel.setAttribute('aria-hidden', 'true');
      closeSubmenus();
    }
    if (overlay instanceof HTMLElement) {
      overlay.hidden = true;
    }
  };

  const openDrawer = () => {
    toggle.setAttribute('aria-expanded', 'true');
    header.classList.add('is-menu-open');
    document.documentElement.classList.add('petshop-menu-drawer-open');
    panel.setAttribute('aria-hidden', 'false');
    closeSubmenus();
    if (overlay instanceof HTMLElement) {
      overlay.hidden = false;
    }
  };

  toggle.addEventListener('click', () => {
    if (toggle.getAttribute('aria-expanded') === 'true') {
      closeDrawer();
      return;
    }
    openDrawer();
  });

  panel.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    if (target.closest('.petshop-commercial-menu__submenu-toggle')) {
      return;
    }
    if (target.closest('a')) {
      closeDrawer();
    }
  });

  menu.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const button = target.closest('.petshop-commercial-menu__submenu-toggle');
    if (!(button instanceof HTMLElement) || !menu.contains(button)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    const item = button.closest('.menu-item-has-children');
    if (!(item instanceof HTMLElement)) {
      return;
    }
    if (button.getAttribute('aria-expanded') === 'true') {
      setExpanded(item, false);
      return;
    }
    openSubmenu(item);
  });

  parentItems().forEach((item) => {
    item.addEventListener('mouseenter', () => {
      if (usesDesktopHover()) {
        suppressOpen = null;
        openSubmenu(item);
      }
    });
    item.addEventListener('mouseleave', () => {
      if (usesDesktopHover()) {
        setExpanded(item, false);
      }
    });
    item.addEventListener('focusin', () => {
      if (desktopQuery.matches) {
        openSubmenu(item);
      }
    });
    item.addEventListener('focusout', (event) => {
      if (!desktopQuery.matches) {
        return;
      }
      const next = event.relatedTarget;
      if (next instanceof Node && item.contains(next)) {
        return;
      }
      if (suppressOpen === item) {
        suppressOpen = null;
      }
      setExpanded(item, false);
    });
  });

  closeButton?.addEventListener('click', () => {
    closeDrawer();
    toggle.focus();
  });

  overlay?.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    const openItem = parentItems().find((item) => item.classList.contains('is-submenu-open'));
    if (desktopQuery.matches && openItem instanceof HTMLElement) {
      suppressOpen = openItem;
      setExpanded(openItem, false);
      const link = parentLink(openItem);
      if (link instanceof HTMLElement) {
        link.focus();
      }
      event.preventDefault();
      return;
    }

    if (!desktopQuery.matches && header.classList.contains('is-menu-open')) {
      closeDrawer();
      toggle.focus();
    }
  });

  desktopQuery.addEventListener('change', () => {
    closeDrawer();
    closeSubmenus();
    if (desktopQuery.matches) {
      panel.removeAttribute('aria-hidden');
    }
  });

  if (!desktopQuery.matches) {
    panel.setAttribute('aria-hidden', 'true');
  }
})();
