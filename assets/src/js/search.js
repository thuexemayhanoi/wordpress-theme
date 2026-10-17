/**
 * Search: header panel toggle and live suggestions.
 *
 * Suggestions come from the core REST route /wp/v2/search (no external service). Only the five best
 * matches are requested; typing is debounced, older requests are aborted and results are cached per
 * phrase. A visually hidden live region announces the number of suggestions. Everything degrades to
 * the plain search form.
 */
const MIN_CHARS = 3;
const DELAY = 250;

function decode(text) {
  return new DOMParser().parseFromString(String(text), 'text/html').documentElement.textContent || '';
}

function isHttp(url) {
  try {
    const parsed = new URL(url, window.location.href);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch (error) {
    return false;
  }
}

function initPanel() {
  const toggle = document.querySelector('.search-toggle');
  const panel = document.getElementById('header-search');
  if (!toggle || !panel) {
    return;
  }
  const input = panel.querySelector('.search-field');

  function set(open, returnFocus) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      document.dispatchEvent(new CustomEvent('nh-editorial:close-nav'));
      if (input) {
        input.focus();
      }
    } else if (returnFocus) {
      toggle.focus();
    }
  }

  toggle.addEventListener('click', () => set(panel.hidden, false));

  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      set(false, true);
    }
  });

  document.addEventListener('click', (event) => {
    if (!panel.hidden && !panel.contains(event.target) && !toggle.contains(event.target)) {
      set(false, false);
    }
  });

  document.addEventListener('nh-editorial:close-search', () => {
    if (!panel.hidden) {
      set(false, false);
    }
  });
}

function initSuggest(form) {
  const endpoint = form.getAttribute('data-suggest-url');
  const input = form.querySelector('.search-field');
  const box = form.querySelector('.search-suggest');
  const list = form.querySelector('.search-suggest__list');
  const status = form.querySelector('.search-suggest__status');
  if (!endpoint || !input || !box || !list || !status) {
    return;
  }

  const labels = {
    none: form.getAttribute('data-label-none') || '',
    one: form.getAttribute('data-label-one') || '',
    count: form.getAttribute('data-label-count') || '%d',
    all: form.getAttribute('data-label-all') || '',
  };
  const cache = new Map();
  let timer = 0;
  let controller = null;

  function hide() {
    box.hidden = true;
    list.textContent = '';
  }

  function addLink(text, href, className) {
    const item = document.createElement('li');
    if (className) {
      item.className = className;
    }
    const link = document.createElement('a');
    link.href = href;
    link.textContent = text;
    item.appendChild(link);
    list.appendChild(item);
  }

  function render(items, query) {
    list.textContent = '';
    items.forEach((entry) => addLink(entry.title, entry.url));
    if (items.length) {
      const base = form.getAttribute('action') || '/';
      addLink(labels.all, base + (base.indexOf('?') === -1 ? '?' : '&') + 's=' + encodeURIComponent(query), 'search-suggest__all');
    }
    status.textContent = items.length === 1 && labels.one ? labels.one : items.length ? labels.count.replace('%d', String(items.length)) : labels.none;
    box.hidden = items.length === 0;
  }

  function request(query) {
    if (cache.has(query)) {
      return Promise.resolve(cache.get(query));
    }
    if (controller) {
      controller.abort();
    }
    controller = new AbortController();
    const url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'search=' + encodeURIComponent(query) + '&per_page=5&type=post&subtype=post&_fields=id,title,url';
    return fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Suggestion request failed');
        }
        return response.json();
      })
      .then((data) => {
        const items = (Array.isArray(data) ? data : [])
          .filter((entry) => entry && entry.title && isHttp(entry.url))
          .map((entry) => ({ title: decode(entry.title), url: entry.url }));
        cache.set(query, items);
        return items;
      });
  }

  input.addEventListener('input', () => {
    const query = input.value.trim();
    window.clearTimeout(timer);
    if (query.length < MIN_CHARS) {
      hide();
      status.textContent = '';
      return;
    }
    timer = window.setTimeout(() => {
      request(query)
        .then((items) => {
          if (input.value.trim() === query) {
            render(items, query);
          }
        })
        .catch(() => {
          // Network errors, aborts and blocked REST routes simply leave the plain form in place.
        });
    }, DELAY);
  });

  form.addEventListener('keydown', (event) => {
    const links = Array.from(list.querySelectorAll('a'));
    if (!links.length || box.hidden) {
      return;
    }
    const index = links.indexOf(document.activeElement);
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      links[Math.min(index + 1, links.length - 1)].focus();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (index <= 0) {
        input.focus();
      } else {
        links[index - 1].focus();
      }
    } else if (event.key === 'Escape') {
      hide();
      input.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (!form.contains(event.target)) {
      hide();
    }
  });
}

export function initSearch() {
  initPanel();
  document.querySelectorAll('form.has-suggest').forEach(initSuggest);
}
