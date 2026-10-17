/** Adds a subtle shadow to the sticky header once the page is scrolled. */
export function initHeader() {
  const header = document.querySelector('.site-header');
  if (!header) {
    return;
  }

  const update = () => header.classList.toggle('is-scrolled', window.scrollY > 4);
  let ticking = false;

  update();
  window.addEventListener(
    'scroll',
    () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(() => {
          update();
          ticking = false;
        });
      }
    },
    { passive: true }
  );
}
