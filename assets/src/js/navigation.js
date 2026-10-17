/**
 * Primary navigation: mobile drawer and accessible mega-menu panels.
 *
 * - Each panel is opened by a real <button aria-expanded>; the parent link stays a normal link.
 * - Escape closes the open panel (or the drawer) and returns focus to its button.
 * - On desktop with a mouse a panel also opens on hover (with a short intent delay); keyboard and
 *   touch always work through the button. Focus leaving a menu item closes its panel.
 * - Without this script the panels stay hidden and every top-level link still works.
 */
const MOBILE = '(max-width: 960px)';

export function initNavigation() {
  const nav = document.getElementById('primary-navigation');
  if (!nav) {
    return;
  }

  const toggle = document.querySelector('.nav-toggle');
  const mobile = window.matchMedia(MOBILE);
  const items = Array.from(nav.querySelectorAll('.menu-item-has-children'));

  const panelOf = (item) => item.querySelector(':scope > .sub-menu');
  const buttonOf = (item) => item.querySelector(':scope > .submenu-toggle');
  const isOpen = (item) => {
    const panel = panelOf(item);
    return Boolean(panel) && !panel.hidden;
  };

  function setPanel(item, open) {
    const panel = panelOf(item);
    const button = buttonOf(item);
    if (!panel || !button) {
      return;
    }
    panel.hidden = !open;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      document.dispatchEvent(new CustomEvent('nh-editorial:close-search'));
    }
  }

  function closePanels(except) {
    items.forEach((item) => {
      if (item !== except) {
        setPanel(item, false);
      }
    });
  }

  const drawerOpen = () => nav.classList.contains('is-open');

  function openDrawer(focusFirst) {
    nav.classList.add('is-open');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
    document.dispatchEvent(new CustomEvent('nh-editorial:close-search'));
    if (focusFirst) {
      const first = nav.querySelector('a');
      if (first) {
        first.focus();
      }
    }
  }

  function closeDrawer(returnFocus) {
    nav.classList.remove('is-open');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
    closePanels(null);
    if (returnFocus && toggle) {
      toggle.focus();
    }
  }

  items.forEach((item) => {
    const button = buttonOf(item);
    if (!button) {
      return;
    }

    button.addEventListener('click', () => {
      const open = !isOpen(item);
      if (!mobile.matches) {
        closePanels(item);
      }
      setPanel(item, open);
    });

    item.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && isOpen(item)) {
        setPanel(item, false);
        button.focus();
        event.stopPropagation();
      }
    });

    item.addEventListener('focusout', (event) => {
      if (!mobile.matches && !item.contains(event.relatedTarget)) {
        setPanel(item, false);
      }
    });
  });

  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    items.forEach((item) => {
      let timer = 0;
      item.addEventListener('pointerenter', (event) => {
        if (mobile.matches || event.pointerType !== 'mouse') {
          return;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
          closePanels(item);
          setPanel(item, true);
        }, 90);
      });
      item.addEventListener('pointerleave', (event) => {
        if (mobile.matches || event.pointerType !== 'mouse') {
          return;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
          if (!item.contains(document.activeElement)) {
            setPanel(item, false);
          }
        }, 220);
      });
    });
  }

  if (toggle) {
    toggle.addEventListener('click', (event) => {
      if (drawerOpen()) {
        closeDrawer(false);
      } else {
        // event.detail is 0 for keyboard activation: move focus into the opened menu.
        openDrawer(event.detail === 0);
      }
    });
  }

  document.addEventListener('click', (event) => {
    if (nav.contains(event.target) || (toggle && toggle.contains(event.target))) {
      return;
    }
    closePanels(null);
    if (mobile.matches && drawerOpen()) {
      closeDrawer(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }
    const wasDrawerOpen = drawerOpen();
    closePanels(null);
    if (wasDrawerOpen) {
      closeDrawer(true);
    }
  });

  document.addEventListener('nh-editorial:close-nav', () => {
    closePanels(null);
    if (drawerOpen()) {
      closeDrawer(false);
    }
  });

  const reset = () => closeDrawer(false);
  if (mobile.addEventListener) {
    mobile.addEventListener('change', reset);
  }
}
