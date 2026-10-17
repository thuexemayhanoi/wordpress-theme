/**
 * Light / dark mode toggle. The saved or preferred mode is already applied before first paint by a
 * tiny inline script in <head>; this module wires the button and follows the system setting until the
 * visitor makes an explicit choice.
 */
const KEY = 'nh-editorial-color-mode';

function saved() {
  try {
    const value = window.localStorage.getItem(KEY);
    return value === 'dark' || value === 'light' ? value : null;
  } catch (error) {
    return null;
  }
}

export function initColorMode() {
  const button = document.querySelector('.mode-toggle');
  if (!button) {
    return;
  }

  const root = document.documentElement;
  const query = window.matchMedia('(prefers-color-scheme: dark)');
  const current = () => root.getAttribute('data-theme') || (query.matches ? 'dark' : 'light');
  const sync = () => button.setAttribute('aria-pressed', current() === 'dark' ? 'true' : 'false');

  sync();

  button.addEventListener('click', () => {
    const next = current() === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try {
      window.localStorage.setItem(KEY, next);
    } catch (error) {
      // Storage may be unavailable (private mode); the choice then lasts for this page only.
    }
    sync();
  });

  if (query.addEventListener) {
    query.addEventListener('change', (event) => {
      if (saved() === null) {
        root.setAttribute('data-theme', event.matches ? 'dark' : 'light');
        sync();
      }
    });
  }
}
