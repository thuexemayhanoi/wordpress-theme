/**
 * Nguyen Ha Editorial: single deferred script. No framework, no network requests except the optional
 * search suggestions. Every feature is isolated so one failure cannot disable the others, and the
 * site is fully usable when this file does not load at all.
 */
import { initColorMode } from './color-mode.js';
import { initHeader } from './header.js';
import { initNavigation } from './navigation.js';
import { initSearch } from './search.js';
import { initToc } from './toc.js';

function ready(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback);
  } else {
    callback();
  }
}

ready(() => {
  [initHeader, initColorMode, initNavigation, initSearch, initToc].forEach((init) => {
    try {
      init();
    } catch (error) {
      if (window.console && window.console.warn) {
        window.console.warn('Nguyen Ha Editorial:', error);
      }
    }
  });
});
