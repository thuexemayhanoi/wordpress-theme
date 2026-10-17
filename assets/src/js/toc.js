/**
 * Table of contents: open on wide screens, collapsed on small ones; keeps focus and the current
 * section in sync. The list itself is server-rendered, so it works (and is indexable) without this.
 */
function targetOf(link) {
  const id = decodeURIComponent((link.getAttribute('href') || '').slice(1));
  return id ? document.getElementById(id) : null;
}

export function initToc() {
  const toc = document.querySelector('.toc');
  if (!toc) {
    return;
  }

  const details = toc.querySelector('details');
  const links = Array.from(toc.querySelectorAll('a[href^="#"]'));

  if (details) {
    const wide = window.matchMedia('(min-width: 1100px)');
    const apply = () => {
      details.open = wide.matches;
    };
    apply();
    if (wide.addEventListener) {
      wide.addEventListener('change', apply);
    }
  }

  // Move focus to the heading that was jumped to, so keyboard and screen-reader users land there.
  links.forEach((link) => {
    link.addEventListener('click', () => {
      const target = targetOf(link);
      if (target) {
        target.setAttribute('tabindex', '-1');
        window.setTimeout(() => target.focus({ preventScroll: true }), 0);
      }
    });
  });

  // The current section is the last heading that has reached the line where an anchor jump lands: the top of the
  // viewport plus the sticky header (html { scroll-padding-top }). That line is what the stylesheet says it is, so the
  // marker is right at every viewport height and header height; a band measured in percent of the viewport was not.
  const entries = [];
  links.forEach((link) => {
    const target = targetOf(link);
    if (target) {
      entries.push({ target, link });
    }
  });
  if (!entries.length) {
    return;
  }

  let active = null;
  let frame = 0;

  const landingLine = () => {
    const padding = parseFloat(window.getComputedStyle(document.documentElement).scrollPaddingTop);
    return (Number.isFinite(padding) ? padding : 0) + 2;
  };

  const update = () => {
    frame = 0;
    const line = landingLine();
    let current = null;
    for (const entry of entries) {
      if (entry.target.getBoundingClientRect().top <= line) {
        current = entry.link;
      } else {
        break;
      }
    }
    if (current !== active) {
      if (active) {
        active.removeAttribute('aria-current');
      }
      if (current) {
        current.setAttribute('aria-current', 'true');
      }
      active = current;
    }
  };

  const schedule = () => {
    if (!frame) {
      frame = window.requestAnimationFrame(update);
    }
  };

  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  update();
}
