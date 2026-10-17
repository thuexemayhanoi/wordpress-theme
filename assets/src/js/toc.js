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

  if (!('IntersectionObserver' in window)) {
    return;
  }

  const byTarget = new Map();
  links.forEach((link) => {
    const target = targetOf(link);
    if (target) {
      byTarget.set(target, link);
    }
  });

  let active = null;
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }
        const link = byTarget.get(entry.target);
        if (link && link !== active) {
          if (active) {
            active.removeAttribute('aria-current');
          }
          link.setAttribute('aria-current', 'true');
          active = link;
        }
      });
    },
    { rootMargin: '-15% 0px -75% 0px' }
  );
  byTarget.forEach((link, target) => observer.observe(target));
}
