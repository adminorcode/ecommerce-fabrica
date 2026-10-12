import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { Script, createContext } from 'node:vm';

const rootDir = process.cwd();
const source = readFileSync(join(rootDir, 'wp-content/plugins/petshop-core/assets/js/product-card.js'), 'utf8');

const dataName = (attribute) => attribute
  .replace(/^data-/, '')
  .replace(/-([a-z])/g, (_match, letter) => letter.toUpperCase());

class ClassList {
  constructor(element) {
    this.element = element;
    this.values = new Set();
  }

  add(value) {
    this.values.add(value);
  }

  remove(value) {
    this.values.delete(value);
  }

  toggle(value, force) {
    if (force === undefined ? !this.values.has(value) : force) {
      this.add(value);
      return true;
    }

    this.remove(value);
    return false;
  }

  contains(value) {
    return this.values.has(value);
  }
}

class Element {
  constructor(tagName, options = {}) {
    this.tagName = tagName.toLowerCase();
    this.children = [];
    this.parentElement = null;
    this.dataset = { ...(options.dataset || {}) };
    this.attributes = new Map();
    this.classList = new ClassList(this);
    this.textContent = options.textContent || '';
    this.innerHTML = options.innerHTML || '';
    this.disabled = false;

    for (const className of options.classNames || []) {
      this.classList.add(className);
    }
  }

  append(...children) {
    for (const child of children) {
      child.parentElement = this;
      this.children.push(child);
    }
  }

  prepend(...children) {
    for (const child of children) child.parentElement = this;
    this.children.unshift(...children);
  }

  replaceChildren(...children) {
    this.children = [];
    this.append(...children);
  }

  setAttribute(name, value) {
    const stringValue = String(value);
    this.attributes.set(name, stringValue);
    if (name.startsWith('data-')) {
      this.dataset[dataName(name)] = stringValue;
    }
  }

  getAttribute(name) {
    if (name.startsWith('data-')) {
      return this.dataset[dataName(name)] ?? null;
    }

    return this.attributes.get(name) ?? null;
  }

  removeAttribute(name) {
    this.attributes.delete(name);
    if (name.startsWith('data-')) {
      delete this.dataset[dataName(name)];
    }
  }

  focus() {
    this.focused = true;
  }

  dispatchEvent() {
    return true;
  }

  matches(selector) {
    return selector.split(',').some((part) => this.matchesSingle(part.trim()));
  }

  matchesSingle(selector) {
    if (selector === 'li.product') {
      return this.tagName === 'li' && this.classList.contains('product');
    }

    if (selector === '.product') {
      return this.classList.contains('product');
    }

    if (selector === 'img') {
      return this.tagName === 'img';
    }

    const dataMatches = [...selector.matchAll(/\[([^=\]]+)(?:="([^"]*)")?\]/g)];
    if (dataMatches.length > 0) {
      return dataMatches.every(([, name, expected]) => {
        const value = this.getAttribute(name);
        return expected === undefined ? value !== null : value === expected;
      });
    }

    if (selector.startsWith('.')) {
      return this.classList.contains(selector.slice(1));
    }

    return selector === this.tagName;
  }

  closest(selector) {
    let current = this;
    while (current) {
      if (current.matches(selector)) {
        return current;
      }
      current = current.parentElement;
    }

    return null;
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }

  querySelectorAll(selector) {
    const matches = [];
    const visit = (element) => {
      for (const child of element.children) {
        if (selector.includes('img') && child.tagName === 'img') {
          matches.push(child);
        } else if (child.matches(selector)) {
          matches.push(child);
        }
        visit(child);
      }
    };
    visit(this);

    return matches;
  }
}

const makeChip = (attribute, value, selected = false) => {
  const chip = new Element('button', {
    dataset: {
      petshopAttribute: attribute,
      value,
    },
    textContent: value,
  });
  chip.setAttribute('data-petshop-attribute', attribute);
  chip.setAttribute('data-value', value);
  chip.setAttribute('aria-pressed', selected ? 'true' : 'false');
  if (selected) chip.classList.add('is-selected');

  return chip;
};

const makeGroup = (attribute, chips) => {
  const group = new Element('fieldset', {
    dataset: {
      petshopAttributeGroup: attribute,
    },
  });
  group.setAttribute('data-petshop-attribute-group', attribute);
  group.append(...chips);

  return group;
};

const variations = [
  {
    id: 202,
    attributes: { size: 'small', color: 'red' },
    priceHtml: '<span>R$ 29,90</span>',
    image: 'variation-b.jpg',
    purchasable: true,
    inStock: true,
  },
  {
    id: 101,
    attributes: { size: '', color: 'red' },
    priceHtml: '<span>R$ 19,90</span>',
    image: 'variation-a.jpg',
    purchasable: true,
    inStock: true,
  },
  {
    id: 303,
    attributes: { size: 'medium', color: 'red' },
    priceHtml: '<span>R$ 39,90</span>',
    image: 'variation-c.jpg',
    purchasable: true,
    inStock: true,
  },
];

const card = new Element('li', { classNames: ['product'] });
const image = new Element('img');
image.setAttribute('src', 'original.jpg');
const price = new Element('span', { innerHTML: '<span>R$ 19,90</span>' });
price.setAttribute('data-petshop-card-price', '');
const status = new Element('p');
status.setAttribute('data-petshop-card-status', '');
const variableRoot = new Element('div', {
  dataset: {
    initialVariationId: '101',
    variations: JSON.stringify(variations),
  },
});
variableRoot.setAttribute('data-petshop-variable-card', '');
variableRoot.setAttribute('data-initial-variation-id', '101');
variableRoot.setAttribute('data-variations', JSON.stringify(variations));
const smallChip = makeChip('size', 'small', true);
const mediumChip = makeChip('size', 'medium', false);
const colorChip = makeChip('color', 'red', true);
variableRoot.append(
  makeGroup('size', [smallChip, mediumChip]),
  makeGroup('color', [colorChip]),
  status
);
const buyButton = new Element('button', {
  dataset: {
    petshopBuyNow: '1',
    productId: '77',
    productType: 'variable',
  },
  textContent: 'Comprar agora',
});
buyButton.setAttribute('data-petshop-buy-now', '1');
buyButton.setAttribute('data-product-id', '77');
buyButton.setAttribute('data-product-type', 'variable');
card.append(image, price, variableRoot, buyButton);

const listeners = new Map();
const document = {
  createElement(tagName) { return new Element(tagName); },
  body: new Element('body'),
  addEventListener(type, listener) {
    listeners.set(type, [...(listeners.get(type) || []), listener]);
  },
  querySelectorAll(selector) {
    return card.matches(selector) ? [card, ...card.querySelectorAll(selector)] : card.querySelectorAll(selector);
  },
};

const requests = [];
const context = createContext({
  window: {
    petshopProductCardConfig: {
      endpoint: '/wp-json/wc/store/v1/cart/add-item',
      nonce: 'nonce',
      i18n: {},
    },
    setTimeout(callback) {
      callback();
    },
    petshopCartOperations: {
      async addItem(item) {
        requests.push(JSON.parse(JSON.stringify({ id: Number(item.id), quantity: 1, ...(item.variation ? { variation: item.variation } : {}) })));
        return { items: [] };
      },
    },
  },
  document,
  CustomEvent: class CustomEvent {
    constructor(type, detail) {
      this.type = type;
      this.detail = detail;
    }
  },
  fetch: async (_url, options) => {
    requests.push(JSON.parse(options.body));
    return {
      ok: true,
      json: async () => ({ items: [] }),
    };
  },
  console,
});
context.window.document = document;
context.window.fetch = context.fetch;
context.window.CustomEvent = context.CustomEvent;

new Script(source, { filename: 'product-card.js' }).runInContext(context);

assert.equal(price.innerHTML, '<span>R$ 19,90</span>', 'card deve inicializar com o preco da variacao inicial do servidor');
assert.equal(image.getAttribute('src'), 'variation-a.jpg', 'card deve inicializar com a imagem da variacao inicial do servidor');

const click = async (target) => {
  const event = {
    target,
    preventDefault() {},
  };
  await Promise.all((listeners.get('click') || []).map((listener) => listener(event)));
};

await click(buyButton);
assert.equal(requests.length, 1, 'compra inicial deveria chamar Store API uma vez');
assert.deepEqual(
  requests[0],
  {
    id: 101,
    quantity: 1,
    variation: [
      { attribute: 'size', value: 'small' },
      { attribute: 'color', value: 'red' },
    ],
  },
  'compra inicial deve enviar a variacao inicial resolvida'
);

await click(mediumChip);
assert.equal(price.innerHTML, '<span>R$ 39,90</span>', 'troca real de chip deve resolver a nova variacao compativel');
assert.equal(image.getAttribute('src'), 'variation-c.jpg', 'troca real de chip deve atualizar para a imagem da nova variacao');

await click(buyButton);
assert.equal(requests.length, 2, 'segunda compra deveria chamar Store API uma vez');
assert.deepEqual(
  requests[1],
  {
    id: 303,
    quantity: 1,
    variation: [
      { attribute: 'size', value: 'medium' },
      { attribute: 'color', value: 'red' },
    ],
  },
  'compra apos troca real deve enviar a mesma variacao exibida no card'
);

console.log('validate-031-product-card: wildcard initial variation and resolved add-to-cart payload passed');
