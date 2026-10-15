/* OpenFilter headless example — plain JavaScript, no build, no dependencies. MIT. */
(() => {
  'use strict';
  const params = new URLSearchParams(location.search);
  const endpoint = params.get('endpoint') || '/graphql';
  const store = params.get('store');
  const OWN = ['category', 'store', 'endpoint'];
  const $ = (id) => document.getElementById(id);
  const PRODUCTS = `query ($filter: ProductAttributeFilterInput) {
    products(filter: $filter, pageSize: 12) {
      total_count
      items { uid name small_image { url label } price_range { minimum_price { final_price { value currency } } } }
      openfilter_aggregations {
        code filter_key label url_param display_mode multiselect logic tooltip
        options { value label url_value count selected depth from to swatch { type value } }
        range { min max from to }
      }
    }
  }`;

  /** Picks by filter key: Set of values; ranges as "from-to". */
  let picks = new Map();
  let category = params.get('category');
  let aggregations = [];

  async function graphql(query, variables) {
    const headers = { 'Content-Type': 'application/json' };
    if (store) headers.Store = store;
    const response = await fetch(endpoint, { method: 'POST', headers, body: JSON.stringify({ query, variables }) });
    const json = await response.json();
    if (json.errors) throw new Error(json.errors.map((e) => e.message).join('; '));
    return json.data;
  }

  /** The `filter` argument of `products` for the current picks. */
  function filterArgument() {
    const filter = { category_id: { eq: String(category) } };
    for (const [key, values] of picks) {
      const aggregation = aggregations.find((a) => a.filter_key === key);
      const list = [...values];
      if (aggregation && (aggregation.range || aggregation.display_mode === 'ranges')) {
        const [from, to] = list[0].split('-');
        filter[key] = Object.fromEntries(Object.entries({ from, to }).filter(([, v]) => v !== ''));
      } else if (key === 'category_id') {
        filter.category_id = { eq: list[0] };
      } else {
        filter[key] = list.length > 1 || aggregation?.multiselect ? { in: list } : { eq: list[0] };
      }
    }
    return filter;
  }

  /** The storefront's URL format: url_param=url_value,url_value (ranges as from-to). */
  function writeUrl() {
    const url = new URLSearchParams();
    OWN.forEach((name) => params.get(name) && url.set(name, params.get(name)));
    url.set('category', category);
    for (const [key, values] of picks) {
      const aggregation = aggregations.find((a) => a.filter_key === key);
      if (!aggregation) continue;
      const urlValues = [...values].map((v) => aggregation.options.find((o) => o.value === v)?.url_value ?? v);
      url.set(aggregation.url_param, urlValues.join(','));
    }
    history.replaceState(null, '', `?${url.toString().replace(/%2C/g, ',')}`);
  }

  /** Picks from a URL in the storefront's format, once the aggregations name the parameters. */
  function readUrl() {
    for (const aggregation of aggregations) {
      const raw = params.get(aggregation.url_param);
      if (!raw || aggregation.filter_key === 'category_id') continue;
      const values = raw.split(',').map((v) => aggregation.options.find((o) => o.url_value === v)?.value ?? v);
      picks.set(aggregation.filter_key, new Set(aggregation.range || aggregation.display_mode === 'ranges' ? [raw] : values));
    }
  }

  function toggle(key, value, multiple) {
    const values = picks.get(key) ?? new Set();
    if (values.has(value)) values.delete(value);
    else { if (!multiple) values.clear(); values.add(value); }
    if (values.size) picks.set(key, values); else picks.delete(key);
    load();
  }

  function element(tag, attributes = {}, ...children) {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) {
      if (name.startsWith('on')) node.addEventListener(name.slice(2), value);
      else if (value !== null && value !== undefined && value !== false) node.setAttribute(name, value === true ? '' : value);
    }
    node.append(...children.filter((c) => c !== null && c !== undefined));
    return node;
  }

  function renderFilters() {
    const nav = $('filters');
    const open = new Set([...nav.querySelectorAll('details[open]')].map((d) => d.dataset.code));
    nav.replaceChildren(...aggregations.map((aggregation) => {
      const picked = picks.has(aggregation.filter_key);
      const details = element('details', { 'data-code': aggregation.code, open: open.has(aggregation.code) || picked || aggregations.indexOf(aggregation) < 3 },
        element('summary', {}, aggregation.label));
      if (aggregation.tooltip) details.append(element('p', { class: 'hint' }, aggregation.tooltip));
      if (aggregation.logic === 'and') details.append(element('p', { class: 'hint' }, 'Products have all picked values.'));
      if (aggregation.range) details.append(renderRange(aggregation));
      else details.append(renderOptions(aggregation));
      return details;
    }));
  }

  function renderOptions(aggregation) {
    const multiple = aggregation.multiselect && aggregation.display_mode !== 'ranges' && aggregation.filter_key !== 'category_id';
    return element('ul', {}, ...aggregation.options.map((option) => element('li', { class: option.depth ? `depth-${option.depth}` : null },
      element('label', {},
        element('input', {
          type: multiple ? 'checkbox' : 'radio', name: aggregation.code, value: option.value,
          checked: option.selected || picks.get(aggregation.filter_key)?.has(option.value),
          onchange: () => aggregation.filter_key === 'category_id' ? drillDown(option.value) : toggle(aggregation.filter_key, option.value, multiple),
        }),
        option.swatch?.type === 'color' ? element('span', { class: 'swatch', style: `background:${option.swatch.value}`, 'aria-hidden': 'true' }) : null,
        element('span', {}, option.label),
        element('span', { class: 'count' }, String(option.count))))));
  }

  function renderRange(aggregation) {
    const { min, max, from, to } = aggregation.range;
    const input = (name, value, label) => element('label', {}, label,
      element('input', { type: 'number', name, min: Math.floor(min), max: Math.ceil(max), step: 'any', value: value ?? '', placeholder: String(name === 'from' ? Math.floor(min) : Math.ceil(max)) }));
    const form = element('form', { class: 'range', onsubmit: (event) => {
      event.preventDefault();
      const data = new FormData(form);
      const value = `${data.get('from') || ''}-${data.get('to') || ''}`;
      if (value === '-') picks.delete(aggregation.filter_key); else picks.set(aggregation.filter_key, new Set([value]));
      load();
    } }, input('from', from, 'From'), input('to', to, 'To'), element('button', { type: 'submit' }, 'Apply'));
    return form;
  }

  function drillDown(id) {
    category = id;
    picks = new Map();
    load();
  }

  function renderChips() {
    const chips = [];
    for (const [key, values] of picks) {
      const aggregation = aggregations.find((a) => a.filter_key === key);
      for (const value of values) {
        const label = aggregation?.options.find((o) => o.value === value)?.label ?? value;
        chips.push(element('button', { type: 'button', 'aria-label': `Remove ${aggregation?.label ?? key}: ${label}`, onclick: () => {
          values.delete(value);
          if (!values.size) picks.delete(key);
          load();
        } }, `${label} ×`));
      }
    }
    if (chips.length) chips.push(element('button', { type: 'button', onclick: () => { picks = new Map(); load(); } }, 'Clear all'));
    $('chips').replaceChildren(...chips);
  }

  function renderProducts(products) {
    const money = (price) => new Intl.NumberFormat(document.documentElement.lang, { style: 'currency', currency: price.currency }).format(price.value);
    $('products').replaceChildren(...products.items.map((item) => element('li', {},
      element('img', { src: item.small_image?.url, alt: '', loading: 'lazy', width: 240, height: 320 }),
      element('h2', {}, item.name),
      element('p', {}, money(item.price_range.minimum_price.final_price)))));
    $('count').textContent = `${products.total_count} products`;
  }

  async function load() {
    document.body.setAttribute('aria-busy', 'true');
    $('error').hidden = true;
    try {
      const data = await graphql(PRODUCTS, { filter: filterArgument() });
      aggregations = data.products.openfilter_aggregations ?? [];
      writeUrl();
      renderFilters();
      renderChips();
      renderProducts(data.products);
    } catch (error) {
      $('error').textContent = `Could not load the products: ${error.message}`;
      $('error').hidden = false;
    } finally {
      document.body.removeAttribute('aria-busy');
    }
  }

  async function start() {
    if (!category) {
      const data = await graphql('{ categoryList(filters: {}) { children { id name product_count } } }', {});
      category = data.categoryList[0]?.children.find((c) => c.product_count > 0)?.id;
    }
    const names = await graphql('query ($id: String) { categoryList(filters: { ids: { eq: $id } }) { name } }', { id: String(category) });
    $('title').textContent = names.categoryList[0]?.name ?? 'Products';
    // One request names the URL parameters, then the picks of the URL apply.
    const first = await graphql(PRODUCTS, { filter: { category_id: { eq: String(category) } } });
    aggregations = first.products.openfilter_aggregations ?? [];
    readUrl();
    await load();
  }

  start().catch((error) => {
    $('error').textContent = `Could not load the products: ${error.message}`;
    $('error').hidden = false;
  });
})();
